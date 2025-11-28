<?php

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';

define('END_RUNNING', 'done');

define('MIN_EXPECTED_ITEMS', 5);

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
//    function to_cube_task_start()
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
//    function to_cube_run_start()
//    {
//       // Do whatever you need at the end of the run
//       ...
//       $this->log('Run ended');
//    }
// }
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
		}
		else
		{
			$whole_exec_time = 240;// 4 minutes
			$expect_param = 0; // 1 - log_level; 2 - time; 3 - task
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
				check_permissions(PERMISSION_ADMIN);
				echo '<!DOCTYPE HTML><html><head><META content="text/html; charset=utf-8" http-equiv=Content-Type></head><body>';
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
				$this->debug('Expected items count: ' . $items_count . ' in ' . $this->timeLeft() . ' sec');
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
			
			if ($this->isWeb)
			{
				if ($this->task != END_RUNNING && !isset($_REQUEST['run_once']))
				{
					echo '<script>window.location.reload();</script>';
				}
				echo '</body>';
			}
			
			if ($this->task == END_RUNNING)
			{
				$this->log('Complete.', LOG_TO_SCREEN);
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
			foreach ($methods as $method)
			{
				if (substr($method, -5) == '_task')
				{
					$task = substr($method, 0, -5);
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
		$this->task = END_RUNNING;
		$this->writeTask();
		return $this->task;
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
		
		$query = new DbQuery('SELECT batches, items, times, items_times, items_items, last_items_count, current_run_items, vars FROM maintenance_tasks WHERE name = ?', $this->uniqueTaskName());
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
			$this->important(date('F d, Y H:i:s', time()) . ' - start    ' . $this->task);
			$this->runOptionalMethod($this->task . '_task_start');
		}
		else
		{
			$this->vars = json_decode($vars);
			$this->important(date('F d, Y H:i:s', time()) . ' - continue ' . $this->task);
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
		
		$this->log($this->stats->current_run_items . ' items');
		$this->runOptionalMethod($this->task . '_run_end');
		if ($real)
		{
			$this->runOptionalMethod($this->task . '_task_end');
			$this->vars = new stdClass();
			$this->stats->current_run_items = 0;
			$this->important(date('F d, Y H:i:s', time()) . ' - end      ' . $this->task);
		}
		
		$vars = json_encode($this->vars);
		Db::exec(get_label('task'),
			'INSERT INTO maintenance_tasks(name, batches, runs, items, times, items_times, items_items, last_items_count, current_run_items, vars) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?)'.
			' ON DUPLICATE KEY UPDATE batches = ?, runs = runs + 1, items = ?, times = ?, items_times = ?, items_items = ?, last_items_count = ?, current_run_items = ?, vars = ?',
			$this->uniqueTaskName(),
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
		
		if ($flags & LOG_TO_SCREEN)
		{
			if ($this->isWeb)
			{
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
	
	protected function getAverageItemTime()
	{
		$stats = $this->stats;
		$div = $stats->batches * $stats->items_items - $stats->items * $stats->items;
		if ($div < -0.00001 || $div > 0.00001)
		{
			$result = ($stats->batches * $stats->items_times - $stats->items * $stats->times) / $div;
		}
		else if ($stats->items == 0)
		{
			$result = $this->maxExecTime / MIN_EXPECTED_ITEMS;
		}
		else
		{
			$result = $stats->times / $stats->items;
		}
		return max($result, 0.001);
	}
	
	protected function getAverageConstTime()
	{
		$stats = $this->stats;
		if ($stats->batches <= 0)
		{
			return 0;
		}
		return ($stats->times - $stats->items * $this->getAverageItemTime()) / $stats->batches;
	}
	
	protected function canDoOneMoreItem()
	{
		return $this->timeLeft() > $this->getAverageItemTime();
	}
	
	protected function canDoOneMoreRun($items_count)
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		return $a * $items_count + $b <= $this->timeLeft();
	}

	
	protected function getExpectedItemsCount()
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		$time_left = $this->timeLeft();
		$this->debug('Av item time: ' . $a);
		$this->debug('Av const time: ' . $b);
		$result = max((int)floor(($this->timeLeft() - $b) / $a), MIN_EXPECTED_ITEMS);
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
}

?>