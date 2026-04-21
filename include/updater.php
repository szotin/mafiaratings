<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

define('END_RUNNING', 'done');

define('MIN_EXPECTED_ITEMS', 5);
define('MAX_BATCH_TIME', 10); // seconds

define('LOG_TO_SCREEN', 1);
define('LOG_TO_FILE', 2);
define('LOG_ALL', 3);

define('LOG_LEVEL_NONE', 0);
define('LOG_LEVEL_ERROR', 1);
define('LOG_LEVEL_IMPORTANT', 2);
define('LOG_LEVEL_INFO', 3);
define('LOG_LEVEL_DEBUG', 4);

$_updater = null;
function updaterErrorHandler($errno, $errstr, $errfile, $errline)
{
	global $_updater;
	if (!is_null($_updater))
	{
		$_updater->errorHandler($errno, $errstr, $errfile, $errline);
	}
}

// Terminology: 
// 1. Task is a named update on a system.
// 2. Run is one run of the script.
// 3. Batch is a one execution unit for a number of items.
// 4. Item is one update item
// Task is executed in a number of runs. Every run consists of a number of batch executions. Every batch proceeds a number of items.
//
// For example you need to convert all the bagels in your system to cubes.
// class BagelConverter extends Updater
// {
//    // to_cube is a task name. You implement a method that converts bagels to cube by adding "_task" after task name and using it as a method name.
//    // $items_count - is the number of bagels to convert. Updater maintains stats about your runs and calculates how many items you are able to proceed during the allocated time.
//    // One run of this function executes one batch.
//    // The script does a number of runs, depending on the time budget.
//    // User runs the script until the task is complete.
//    // When this function returns 0, the Updater assumes that there is nothing to proceed and decides that the task is complete.
//    function to_cube_task($items_count)
//    {
//       // query next number of coverted bagels
//       $bagels = $this->queryNonConverted($items_count, $this->vars->last_bagel);
//       
//       // convert one by one
//       $count = 0;
//       foreach ($bagels as $bagel)
//       {
//          $bagel->convertToCube();
//          ++$count;
//
//          $this->vars->last_bagel = $bagel->id;
//
//          // check if you have enough time to proceed more bagels
//          if (!$this->canDoOneMoreItem())
//          {
//             break;
//          }
//       }
//       // Let Updater know how many items you were able to convert. So the Updater writes down the stats.
//       return $count;
//    }
//
//    // This optional function is called at the beginning of the task.
//    function to_cube_task_start()
//    {
//       // Set up variables needed for task execution
//       $this->vars->last_bagel = getFirstBagel()->id;
//    }
//
//    // This optional function is called at the end of the task.
//    function to_cube_task_end()
//    {
//       // Write down the results of the task execution
//       ...
//    }
//
//    // This optional function is called at the beginning of the run.
//    function to_cube_run_start()
//    {
//       // Do whatever you need at the beginning of the run
//       ...
//       $this->log('Run started');
//    }
//
//    // This optional function is called at the end of the run.
//    function to_cube_run_end()
//    {
//       // Do whatever you need at the end of the run
//       ...
//       $this->log('Run ended');
//    }
// }
//
// Options (set as protected properties in the subclass):
//    $loopTasks (default: false) - if set to true, the updater will restart from the first task
//       when all tasks are done and there is still time left, repeating indefinitely.
//
class Updater
{
	protected $state;
	
	private $isWeb; 
	private $name;
	private $dir;
	private $logTask;
	private $logFile;
	private $taskFilename;
	private $lockFilename;
	
	private $maxExecTime;
	private $startTime;
	
	private $task;
	private $stats;
	private $taskOnly;

	protected $loopTasks = false; // if true, restarts from the first task when all tasks are done
	protected $vars; // vars class members keep their values during task execution, event in different runs
	
	public function __construct($file)
	{
		global $_updater;
		
		$this->startTime = time();
		
		$this->parse($file);
		$this->isWeb = isset($_SERVER['HTTP_HOST']);
		$this->logTask = null;
		$this->logFile = null;
		$this->taskFilename = $this->name . '.task.txt';
		$this->lockFilename = $this->name . '.lock';
		$this->logLevel = LOG_LEVEL_INFO;
		$this->task = null;
		$this->stats = new stdClass();
		$this->taskOnly = null;
		$this->resetTimeStats();
		$this->vars = new stdClass();
		
		chdir($this->dir);
		$whole_exec_time = 0;
		if ($this->isWeb)
		{
			if (isset($_REQUEST['log_level']))
			{
				$this->setLogLevel($_REQUEST['log_level']);
			}
			
			$whole_exec_time = 0;
			if (isset($_REQUEST['time']))
			{
				$whole_exec_time = (int)$_REQUEST['time'];
			}
			if ($whole_exec_time <= 0)
			{
				$whole_exec_time = 30; // half a minute
			}
			
			if (isset($_REQUEST['task']))
			{
				$this->taskOnly = $_REQUEST['task'];
			}

			if (isset($_REQUEST['loop']))
			{
				$this->loopTasks = (bool)$_REQUEST['loop'];
			}
		}
		else
		{
			$whole_exec_time = 240;// 4 minutes
			$expect_param = 0; // 1 - log_level; 2 - time; 3 - task; 4 - loop
			if (isset($_SERVER['argv']))
			{
				foreach ($_SERVER['argv'] as $arg)
				{
					switch ($expect_param)
					{
					case 1: // log_level
						$this->setLogLevel($arg);
						$expect_param = 0;
						break;
					case 2: // time
						$whole_exec_time = (int)$arg;
						if ($whole_exec_time <= 0)
						{
							$whole_exec_time = 240; // 4 minutes
						}
						$expect_param = 0;
						break;
					case 3: // task
						$this->taskOnly = $arg;
						$expect_param = 0;
						break;
					case 4: // loop
						$this->loopTasks = (bool)$arg;
						$expect_param = 0;
						break;
					default:
						switch ($arg)
						{
						case '-log_level':
							$expect_param = 1;
							break;
						case '-time':
							$expect_param = 2;
							break;
						case '-task':
							$expect_param = 3;
							break;
						case '-loop':
							$expect_param = 4;
							break;
						}
						break;
					}
				}
			}
		}
		$this->maxExecTime = floor($whole_exec_time * 5 / 6); // Give a 1/6 time reserve
		set_time_limit($whole_exec_time);
		$_updater = $this;
		set_error_handler('updaterErrorHandler');
	}
	
	protected function getArg($name)
	{
		if ($this->isWeb)
		{
			if (isset($_REQUEST[$name]))
			{
				return $_REQUEST[$name];
			}
		}
		else if (isset($_SERVER['argv']))
		{
			$coming = false;
			$name = '-' . $name;
			foreach ($_SERVER['argv'] as $arg)
			{
				if ($coming)
				{
					return $arg;
				}
				else if ($arg == $name)
				{
					$coming = true;
				}
			}
		}
		return null;
	}
	
	protected function gasArg($name)
	{
		return !is_null(getArg($name));
	}
	
	public function errorHandler($errno, $errstr, $errfile, $errline)
	{
		if ($errno == 8192) // ignore: The mysql extension is deprecated and will be removed in the future: use mysqli or PDO instead
		{
			return;
		}
		$this->error($errno . ': ' . $errstr);
		$this->error($errfile . ': ' . $errline);
	}
	
	public function run()
	{
		try
		{
			date_default_timezone_set('America/Vancouver');
			if ($this->isWeb)
			{
				initiate_session();
				echo '<!DOCTYPE HTML><html><head><META content="text/html; charset=utf-8" http-equiv=Content-Type><script src="js/common.js"></script></head><body>';
			}
			
			$this->readTask();

			$first_time = true;
			while ($this->task != END_RUNNING)
			{
				$items_count = $this->getExpectedItemsCount();
				if (!$first_time && !$this->canDoOneMoreRun($items_count))
				{
					break;
				}
				$this->debug('Batch: ' . $this->stats->batches);
				$this->debug('Expected items count: ' . $items_count . ' in ' . min($this->timeLeft(), MAX_BATCH_TIME) . ' sec');
				$first_time = false;
				$time = time();
				$method = $this->task . '_task';
				$items_count = $this->$method($items_count);
				$time = time() - $time;
				if ($items_count > 0)
				{
					$this->updateTimeStats($items_count, $time);
				}
				else
				{
					$this->nextTask();
				}
				$this->debug('Actual items count: ' . $items_count . ' in ' . $time . ' sec');
			}
			
			$this->onTaskEnd(false);
			$this->writeTask();
			
			if ($this->task == END_RUNNING)
			{
				$this->log('Complete.', LOG_TO_SCREEN);
			}
			
			if ($this->isWeb)
			{
				$runs = isset($_REQUEST['runs']) ? (int)$_REQUEST['runs'] : 0;
				switch ($runs)
				{
				case 0:
					echo '<script>window.location.reload();</script>';
					break;
				case 1:
					break;
				default:
					echo '<script>goTo({runs:' . ($runs - 1) . '});</script>';
					break;
				}
				echo '</body>';
			}
		}
		catch (Exception $e)
		{
			Db::rollback();
			Exc::log($e, true);
			$this->error($e->getMessage());
		}
		
		if (!is_null($this->logFile))
		{
			fclose($this->logFile);
			$this->logFile = NULL;
		}
	}
	
	//------------------------------------------------------------------------------------------------------
	// Task functions
	//------------------------------------------------------------------------------------------------------
	private function parse($file)
	{
		// Get the current working directory to the directory of the script.
		// This script is sometimes called from the other directories - for auto sending, so we need to change the directory
		$pos = strrpos($file, '/');
		if ($pos === false)
		{
			$pos = strrpos($file, '\\');
			if ($pos === false)
			{
				$this->name = "updater";
				$this->dir = getcwd();
				return;
			}
		}
		$this->dir = substr($file, 0, $pos);
		$this->name = substr($file, $pos + 1);
		
		$pos = strrpos($this->name, '.');
		$this->name = substr($this->name, 0, $pos);
	}
	
	private function readTask()
	{
		if (!is_null($this->task))
		{
			throw new Exc('Wrong order of operations');
		}
		
		if (file_exists($this->taskFilename))
		{
			$file = fopen($this->taskFilename, 'r');
			if ($file !== false)
			{
				$this->task = fread($file, filesize($this->taskFilename));
				fclose($file);
				$this->onTaskStart(false);
				return $this->task;
			}
		}
		return $this->nextTask();
	}

	private function writeTask()
	{
		if (!is_null($this->task))
		{
			if ($this->task != END_RUNNING)
			{
				$file = fopen($this->taskFilename, 'w');
				if ($file === false)
				{
					$this->error('Unable to write state.');
					return;
				}
				
				fwrite($file, $this->task);
				fclose($file);
			}
			else if (file_exists($this->taskFilename))
			{
				unlink($this->taskFilename);
			}
		}
	}
	
	private function nextTask()
	{
		if ($this->task == END_RUNNING)
		{
			return $this->task;
		}
		else if (!is_null($this->task))
		{
			$this->onTaskEnd(true);
		}

		if (is_null($this->taskOnly))
		{
			$methods = get_class_methods(get_class($this));
			$next_one = is_null($this->task);
			$first_task = null;
			foreach ($methods as $method)
			{
				if (substr($method, -5) == '_task')
				{
					$task = substr($method, 0, -5);
					if ($first_task === null)
					{
						$first_task = $task;
					}
					if ($next_one)
					{
						$this->task = $task;
						$this->writeTask();
						$this->onTaskStart(true);
						return $task;
					}
					else if ($this->task == $task)
					{
						$next_one = true;
					}
				}
			}
			if ($this->loopTasks && $first_task !== null)
			{
				$this->task = $first_task;
				$this->writeTask();
				$this->onTaskStart(true);
				return $this->task;
			}
		}
		else if ($this->taskOnly != $this->task)
		{
			if (!method_exists($this, $this->taskOnly . '_task'))
			{
				throw new Exc('Task ' . $this->taskOnly . ' does not exist');
			}
			$this->task = $this->taskOnly;
			$this->writeTask();
			$this->onTaskStart(true);
			return $this->task;
		}
		else if ($this->loopTasks)
		{
			$this->task = $this->taskOnly;
			$this->writeTask();
			$this->onTaskStart(true);
			return $this->task;
		}
		$this->task = END_RUNNING;
		$this->writeTask();
		return $this->task;
	}
	
	private function getTaskNum()
	{
		if (!is_null($this->task))
		{
			$num = 0;
			$methods = get_class_methods(get_class($this));
			foreach ($methods as $method)
			{
				if (substr($method, -5) == '_task')
				{
					$task = substr($method, 0, -5);
					if ($this->task == $task)
					{
						return $num;
					}
					++$num;
				}
			}
		}
		return -1;
	}
	
	private function uniqueTaskName()
	{
		return get_class($this) . '.' . $this->task;
	}
	
	private function runOptionalMethod($name)
	{
		try
		{
			if (method_exists($this, $name))
			{
				$this->$name();
			}
		}
		catch (Exception $e)
		{
			$this->error('Error running ' . $name);
			$this->error($e->getMessage());
		}
	}
	
	// $real is false when it is another start of the sript with the same task
	private function onTaskStart($real)
	{
		if ($this->task == END_RUNNING)
		{
			return;
		}
		
		$query = new DbQuery('SELECT batches, items, times, items_times, items_items, last_items_count, current_run_items, vars FROM maintenance_tasks WHERE name = ? AND script_name = ?', $this->task, get_class($this));
		if ($row = $query->next())
		{
			list ($this->stats->batches, $this->stats->items, $this->stats->times, $this->stats->items_times, $this->stats->items_items, $this->stats->last_items_count, $this->stats->current_run_items, $vars) = $row;
		}
		else
		{
			$vars = "{}";
			$this->resetTimeStats();
		}
		
		if ($real)
		{
			$this->vars = new stdClass();
			$this->stats->current_run_items = 0;
			$this->debug('start    ' . $this->task);
			$this->runOptionalMethod($this->task . '_task_start');
		}
		else
		{
			$this->vars = json_decode($vars);
			$this->debug('continue ' . $this->task);
		}
		$this->runOptionalMethod($this->task . '_run_start');
	}
	
	// $real is false when the sript ends but task is not done yet
	private function onTaskEnd($real)
	{
		if ($this->task == END_RUNNING)
		{
			return;
		}
		
		if ($this->stats->current_run_items > 0)
		{
			$this->log($this->stats->current_run_items . ' items');
		}
		$this->runOptionalMethod($this->task . '_run_end');
		if ($real)
		{
			$this->runOptionalMethod($this->task . '_task_end');
			$this->vars = new stdClass();
			$this->stats->current_run_items = 0;
			$this->debug('end      ' . $this->task);
		}
		
		$vars = json_encode($this->vars);
		$script_name = get_class($this);
		Db::exec('script', 'INSERT IGNORE INTO maintenance_scripts (name, filename) VALUES (?, ?)', $script_name, $this->name);
		Db::exec('task',
			'INSERT INTO maintenance_tasks(script_name, name, num, batches, runs, items, times, items_times, items_items, last_items_count, current_run_items, vars) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'.
			' ON DUPLICATE KEY UPDATE batches = ?, runs = runs + 1, items = ?, times = ?, items_times = ?, items_items = ?, last_items_count = ?, current_run_items = ?, vars = ?',
			$script_name, $this->task, $this->getTaskNum(),
			$this->stats->batches, $this->stats->items, $this->stats->times, $this->stats->items_times, $this->stats->items_items, $this->stats->last_items_count, $this->stats->current_run_items, $vars,
			$this->stats->batches, $this->stats->items, $this->stats->times, $this->stats->items_times, $this->stats->items_items, $this->stats->last_items_count, $this->stats->current_run_items, $vars);
	}
	
	//------------------------------------------------------------------------------------------------------
	// Lock functions
	//------------------------------------------------------------------------------------------------------
	private function lock()
	{
		if (file_exists($this->lockFilename))
		{
			// It is possible that two instances are working at the same time rather than the previous one was updated
			// But we don't know how to detect it. So the rule is - don't run two instances at the same time.
			$this->error('WARNING! Previous run was timed out! Reducing batch parameters.');
			$this->resetTimeStats();
		}
		else if (($file = fopen($this->lockFilename, 'w')) !== false)
		{
			fclose($file);
		}
		else
		{
			$this->error('Unable to create lock file. This is ok we can proceed.');
		}
	}
	
	private function unlock()
	{
		if (file_exists($this->lockFilename))
		{
			unlink($this->lockFilename);
		}
	}
	
	//------------------------------------------------------------------------------------------------------
	// Logging functions
	//------------------------------------------------------------------------------------------------------
	protected function setLogLevel($str)
	{
		if (is_numeric($str))
		{
			$this->logLevel = (int)$str;
		}
		else switch ($str)
		{
		case 'none':
			$this->logLevel = LOG_LEVEL_NONE;
			break;
		case 'error':
			$this->logLevel = LOG_LEVEL_ERROR;
			break;
		case 'important':
			$this->logLevel = LOG_LEVEL_IMPORTANT;
			break;
		case 'info':
			$this->logLevel = LOG_LEVEL_INFO;
			break;
		case 'debug':
			$this->logLevel = LOG_LEVEL_DEBUG;
			break;
		}
		$this->logLevel = min(max($this->logLevel, LOG_LEVEL_NONE), LOG_LEVEL_DEBUG);
		return $this->logLevel;
	}
	
	protected function log($str, $flags = LOG_ALL, $level = LOG_LEVEL_INFO)
	{
		if ($level > $this->logLevel)
		{
			return;
		}
		
		$prefix = date('F d, Y H:i:s', time()) . ': ';
		if ($flags & LOG_TO_SCREEN)
		{
			if ($this->isWeb)
			{
				echo '<span style="color:gray;"><i>' . $prefix . '</i></span>';
				switch ($level)
				{
				case LOG_LEVEL_ERROR:
					echo '<span style="color:red;"><b>' . $str . "</b></span><br>\n";
					break;
				case LOG_LEVEL_IMPORTANT:
					echo '<b>' . $str . "</b><br>\n";
					break;
				case LOG_LEVEL_DEBUG:
					echo '<span style="color:green;"><i>' . $str . "</i></span><br>\n";
					break;
				default:
					echo $str . "<br>\n";
					break;
				}
			}
			else
			{
				echo $prefix;
				switch ($level)
				{
				case LOG_LEVEL_ERROR:
					echo '!!! ' . $str . "\n";
					break;
				case LOG_LEVEL_IMPORTANT:
					echo '-------------------- ' . $str . "\n";
					break;
				case LOG_LEVEL_DEBUG:
					echo '... ' . $str . "\n";
					break;
				default:
					echo $str . "\n";
					break;
				}
			}
		}
	
		if (($flags & LOG_TO_FILE) != 0 && !is_null($this->task) && $this->task != END_RUNNING)
		{
			switch ($level)
			{
			case LOG_LEVEL_ERROR:
				$str = '!!! ' . $str;
				break;
			case LOG_LEVEL_IMPORTANT:
				$str = '-------------------- ' . $str;
				break;
			case LOG_LEVEL_DEBUG:
				$str = '... ' . $str;
				break;
			}
			$str = $prefix . $str;
			
			if ($this->task != $this->logTask)
			{
				$logs_dir = 'logs';
				if (!is_dir($logs_dir))
				{
					mkdir($logs_dir);
				}
				$this->logFile = fopen($logs_dir . '/' . get_class($this) . '.' . $this->task . '.log', 'a');
				$this->logTask = $this->task;
			}
			fwrite($this->logFile, $str . "\n");
		}
	}
	
	protected function error($str, $flags = LOG_ALL)
	{
		$this->log($str, $flags, LOG_LEVEL_ERROR);
	}
	
	protected function important($str, $flags = LOG_ALL)
	{
		$this->log($str, $flags, LOG_LEVEL_IMPORTANT);
	}
	
	protected function info($str, $flags = LOG_ALL)
	{
		$this->log($str, $flags, LOG_LEVEL_INFO);
	}
	
	protected function debug($str, $flags = LOG_ALL)
	{
		$this->log($str, $flags, LOG_LEVEL_DEBUG);
	}
	
	//------------------------------------------------------------------------------------------------------
	// Stats functions
	//------------------------------------------------------------------------------------------------------
	protected function resetTimeStats()
	{
		$this->stats->batches = 0.0;
		$this->stats->items = 0.0;
		$this->stats->times = 0.0;
		$this->stats->items_times = 0.0;
		$this->stats->items_items = 0.0;
		$this->stats->last_items_count = 0;
		$this->stats->current_run_items = 0;
	}
	
	private function updateTimeStats($items_count, $time_elapsed)
	{
		++$this->stats->batches;
		$this->stats->current_run_items += $items_count;
		$this->stats->items += $items_count;
		$this->stats->times += $time_elapsed;
		$this->stats->items_times += $items_count * $time_elapsed;
		$this->stats->items_items += $items_count * $items_count;
		$this->stats->last_items_count = $items_count;
	}
	
	public static function averageItemTime($batches, $items, $times, $items_items, $items_times)
	{
		$div = $batches * $items_items - $items * $items;
		if ($div < -0.00001 || $div > 0.00001)
		{
			$result = ($batches * $items_times - $items * $times) / $div;
		}
		else if ($items == 0)
		{
			return 0;
		}
		else
		{
			$result = $times / $items;
		}
		return max($result, 0.001);
	}
	
	public static function averageConstTime($batches, $items, $times, $items_items, $items_times)
	{
		if ($batches <= 0)
		{
			return 0;
		}
		return ($times - $items * Updater::averageItemTime($batches, $items, $times, $items_items, $items_times)) / $batches;
	}
	
	protected function getAverageItemTime()
	{
		$stats = $this->stats;
		$result = Updater::averageItemTime($stats->batches, $stats->items, $stats->times, $stats->items_items, $stats->items_times);
		if ($result == 0)
		{
			$result = $this->maxExecTime / MIN_EXPECTED_ITEMS;
		}
		return $result;
	}
	
	protected function getAverageConstTime()
	{
		$stats = $this->stats;
		return Updater::averageConstTime($stats->batches, $stats->items, $stats->times, $stats->items_items, $stats->items_times);
	}
	
	protected function canDoOneMoreItem()
	{
		return $this->timeLeft() > $this->getAverageItemTime();
	}
	
	protected function canDoOneMoreRun($items_count)
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		return max($a * $items_count + $b, $a) <= $this->timeLeft();
	}

	
	protected function getExpectedItemsCount()
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		$this->debug('Av item time: ' . $a);
		$this->debug('Av const time: ' . $b);
		$time_left = min($this->timeLeft(), MAX_BATCH_TIME);
		$result = max((int)floor(($time_left - $b) / $a), MIN_EXPECTED_ITEMS);
		$this->debug('Can process: ' . $result);
		if ($this->stats->last_items_count > 0 && $this->stats->batches < 20)
		{
			$result = min(pow(2, $this->stats->batches) * MIN_EXPECTED_ITEMS, $result);
		}
		return $result;
	}
	
	// seconds
	public function timeLeft()
	{
		return $this->startTime + $this->maxExecTime - time();
	}
	
	// returns number of items processed by current task
	protected function itemsProcessed()
	{
		return $this->stats->current_run_items;
	}
}

?>