<?php

require_once __DIR__ . '/security.php';

abstract class Updater
{
	abstract protected function update($state);
	
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
	
	private function readState()
	{
		$state = NULL;
		if (file_exists($this->stateFilename))
		{
			$file = fopen($this->stateFilename, 'r');
			if ($file !== false)
			{
				$state = fread($file, filesize($this->stateFilename));
				$state = json_decode($state);
				fclose($file);
			}
		}
		
		if (is_null($state))
		{
			$state = new stdClass();
		}
		return $state;
	}

	private function writeState($state)
	{
		$file = fopen($this->stateFilename, 'w');
		if ($file === false)
		{
			$this->log('Unable to write state.');
			return;
		}
		
		fwrite($file, json_encode($state));
		fclose($file);
	}
	
	private function deleteState()
	{
		if (file_exists($this->stateFilename))
		{
			unlink($this->stateFilename);
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
	
	function __construct($file)
	{
		$this->parse($file);
		$this->isWeb = isset($_SERVER['HTTP_HOST']);
		$this->logFilename = $this->name . '.log';
		$this->logFile = NULL;
		$this->stateFilename = $this->name . '.json';
		
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
	
	function run()
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
			
			$exec_start_time = time();
			$state = $this->readState();
			$done = isset($state->done) && $state->done;
			
			$spent_time = 0;
			while (!$done && $spent_time < $this->maxExecTime)
			{
				$this->update($state);
				$done = isset($state->done) && $state->done;
				$spent_time = time() - $exec_start_time;
			}
			if ($done)
			{
				$this->deleteState();
			}
			else
			{
				$this->writeState($state);
			}
			
			$spent_time = time() - $exec_start_time;
			$this->log('Iteration took ' . $spent_time . ' sec.');

			if ($this->isWeb)
			{
				if (!$done && !isset($_REQUEST['run_once']))
				{
					echo '<script>window.location.reload();</script>';
				}
				echo '</body>';
			}
			
			if ($done)
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
}

?>