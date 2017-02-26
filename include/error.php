<?php

require_once 'include/log.php';

class FatalExc extends Exception
{
	private $details;
	private $for_log;

    public function __construct($message, $details = NULL, $for_log = false)
	{
		parent::__construct($message);
		if ($details === true)
		{
			$this->details = NULL;
			$this->for_log = true;
		}
		else
		{
			$this->details = $details;
			$this->for_log = $for_log;
		}
    }
	
	public function for_log()
	{
		return $this->for_log;
	}
	
	public function get_details()
	{
		return $this->details;
	}
	
	public function add_details($details)
	{
		if ($this->details == NULL)
		{
			$this->details = $details;
		}
		else
		{
			$this->details .= '<br>' . $details;
		}
	}
	
	public static function log($e, $force = false, $log_obj = 'error')
	{
		$details = '';
		$trace = $e->getTrace();
		foreach ($trace as $rec)
		{
			$details .= $rec['file'] . ': ' . $rec['line'] . '<br>';
		}
		
		if (method_exists($e, 'get_details'))
		{
			$details .= $e->get_details();
		}
		
		if (!$force && method_exists($e, 'for_log'))
		{
			$force = $e->for_log();
		}
		
		if ($force)
		{
			try
			{
				db_log($log_obj, $e->getMessage(), $details);
			}
			catch (Exception $exc)
			{
				// ???
			}
		}
	}
}

class Exc extends FatalExc
{
    public function __construct($message, $details = NULL, $for_log = false)
	{
		parent::__construct($message, $details, $for_log);
    }
}

class RedirectExc extends Exception
{
	private $url;
	
    public function __construct($url)
	{
		parent::__construct($url);
		$this->url = $url;
    }
	
	public function get_url()
	{
		return $this->url;
	}
}

?>