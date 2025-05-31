<?php

require_once __DIR__ . '/security.php';

define('DEFAULT_TASK', 'default');
define('END_RUNNING', 'done');

define('MIN_EXPECTED_ITEMS', 5);
define('MAX_EXPECTED_ITEMS_GROWTH', 2); // multiplier

abstract class Updater
{
	protected $state;
	
	private $isWeb; 
	private $name;
	private $dir;
	private $logFilename;
	private $logFile;
	private $stateFilename;
	private $lockFilename;
	
	private $maxExecTime;
	private $startTime;
	
	abstract protected function update($items_count);
	abstract protected function initState();
	
	public function __construct($file)
	{
		$this->startTime = time();
		
		$this->parse($file);
		$this->isWeb = isset($_SERVER['HTTP_HOST']);
		$this->logFilename = $this->name . '.log';
		$this->logFile = NULL;
		$this->stateFilename = $this->name . '.json';
		$this->lockFilename = $this->name . '.lock';
		
		chdir($this->dir);
		if ($this->isWeb)
		{
			if (isset($_REQUEST['no_log']))
			{
				$this->logFilename = NULL;
			}
			$this->maxExecTime = 25;
		}
		else
		{
			$this->maxExecTime = 180;// 3 minutes
		}
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
			
			$this->readState();

			$first_time = true;
			while (true)
			{
				$items_count = $this->getExpectedItemsCount();
				if (!$first_time && !$this->canDoOneMoreRun($items_count))
				{
					break;
				}
				$this->log('------ Task: ' . $this->state->task . ' ------');
				$this->log('Iteration: ' . $this->state->_stats->runs);
				$this->log('Expected items count: ' . $items_count . ' in ' . $this->timeLeft() . ' sec');
				$first_time = false;
				$time = time();
				$items_count = $this->update($items_count);
				$time = time() - $time; 
				if ($items_count > 0)
				{
					$this->updateTimeStats($items_count, $time);
				}
				$this->log('Actual items count: ' . $items_count . ' in ' . $time . ' sec');
				$this->log('Total items count: ' . $this->state->_stats->sum_items);
			}
			
			if ($this->state->task == END_RUNNING)
			{
				$this->deleteState();
			}
			else
			{
				$this->writeState();
			}
			
			if ($this->isWeb)
			{
				if ($this->state->task != END_RUNNING && !isset($_REQUEST['run_once']))
				{
					echo '<script>window.location.reload();</script>';
				}
				echo '</body>';
			}
			
			if ($this->state->task == END_RUNNING)
			{
				$this->log('Process complete.');
			}
		}
		catch (Exception $e)
		{
			Db::rollback();
			Exc::log($e, true);
			$this->log($e->getMessage());
		}
		
		if (!is_null($this->logFile))
		{
			fclose($this->logFile);
			$this->logFile = NULL;
		}
	}
	
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
	
	protected function readState()
	{
		$this->state = NULL;
		if (file_exists($this->stateFilename))
		{
			$file = fopen($this->stateFilename, 'r');
			if ($file !== false)
			{
				$this->state = fread($file, filesize($this->stateFilename));
				$this->state = json_decode($this->state);
				fclose($file);
			}
		}
		
		if (is_null($this->state))
		{
			$this->state = new stdClass();
			$this->state->task = DEFAULT_TASK;
			$this->resetTimeStats();
			$this->initState();
		}
	}

	protected function writeState()
	{
		$file = fopen($this->stateFilename, 'w');
		if ($file === false)
		{
			$this->log('Unable to write state.');
			return;
		}
		
		fwrite($file, json_encode($this->state));
		fclose($file);
	}
	
	protected function deleteState()
	{
		if (file_exists($this->stateFilename))
		{
			unlink($this->stateFilename);
		}
	}
	
	private function lock()
	{
		if (file_exists($this->lockFilename))
		{
			// It is possible that two instances are working at the same time rather than the previous one was updated
			// But we don't know how to detect it. So the rule is - don't run two instances at the same time.
			$this->log('WARNING! Previous run was timed out! Reducing batch parameters.');
			$this->resetTimeStats();
		}
		else if (($file = fopen($this->lockFilename, 'w')) !== false)
		{
			fclose($file);
		}
		else
		{
			$this->log('Unable to create lock file. This is ok we can proceed.');
		}
	}
	
	private function unlock()
	{
		if (file_exists($this->lockFilename))
		{
			unlink($this->lockFilename);
		}
	}
	
	protected function log($str)
	{
		if ($this->isWeb)
		{
			echo $str . " <br>\n";
		}
		
		if ($this->logFilename)
		{
			if (is_null($this->logFile))
			{
				$this->logFile = fopen($this->logFilename, 'a');
				fwrite($this->logFile, '------ ' . date('F d, Y H:i:s', time()) . "\n");
			}
			fwrite($this->logFile, $str . "\n");
		}
	}
	
	protected function resetTimeStats()
	{
		$stats = $this->state->_stats = new stdClass();
		$stats->runs = 0.0;
		$stats->sum_items = 0.0;
		$stats->sum_times = 0.0;
		$stats->sum_items_times = 0.0;
		$stats->sum_items_items = 0.0;
		$stats->last_items_count = 0;
	}
	
	private function updateTimeStats($items_count, $time_elapsed)
	{
		$stats = $this->state->_stats;
		++$stats->runs;
		$stats->sum_items += $items_count;
		$stats->sum_times += $time_elapsed;
		$stats->sum_items_times += $items_count * $time_elapsed;
		$stats->sum_items_items += $items_count * $items_count;
		$stats->last_items_count = $items_count;
	}
	
	protected function getAverageItemTime()
	{
		$stats = $this->state->_stats;
		$div = $stats->runs * $stats->sum_items_items - $stats->sum_items * $stats->sum_items;
		if ($div < -0.00001 || $div > 0.00001)
		{
			$result = ($stats->runs * $stats->sum_items_times - $stats->sum_items * $stats->sum_times) / $div;
		}
		else if ($stats->sum_items == 0)
		{
			$result = $this->maxExecTime / MIN_EXPECTED_ITEMS;
		}
		else
		{
			$result = $stats->sum_times / $stats->sum_items;
		}
		return max($result, 0.001);
	}
	
	protected function getAverageConstTime()
	{
		$stats = $this->state->_stats;
		if ($stats->runs <= 0)
		{
			return 0;
		}
		return ($stats->sum_times - $stats->sum_items * $this->getAverageItemTime()) / $stats->runs;
	}
	
	protected function canDoOneMoreItem()
	{
		return $this->timeLeft() > 2 * $this->getAverageItemTime();
	}
	
	protected function canDoOneMoreRun($items_count)
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		return $a * ($items_count + 1) + $b < $this->timeLeft();
	}

	
	protected function getExpectedItemsCount()
	{
		$a = $this->getAverageItemTime();
		$b = $this->getAverageConstTime();
		$time_left = $this->timeLeft();
		// $this->log('Av item time: ' . $a);
		// $this->log('Av const time: ' . $b);
		$result = max((int)floor(($this->timeLeft() - $b) / $a), MIN_EXPECTED_ITEMS);
		// $this->log('Can process: ' . $result);
		if ($this->state->_stats->last_items_count > 0)
		{
			$result = min($this->state->_stats->last_items_count * MAX_EXPECTED_ITEMS_GROWTH, $result);
		}
		return $result;
	}
	
	
	protected function setTask($task)
	{
		$this->resetTimeStats();
		$this->state->task = $task;
	}
	
	// seconds
	public function timeLeft()
	{
		return $this->startTime + $this->maxExecTime - time();
	}
}

?>