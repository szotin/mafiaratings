<?php

require_once __DIR__ . '/log.php';
require_once __DIR__ . '/server.php';
require_once __DIR__ . '/json.php';

class FatalExc extends Exception
{
	private $details;
	private $for_log;

    public function __construct($message, $details = NULL, $for_log = false)
	{
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
		if (is_testing_server() && !empty($this->details))
		{
			$message .= '<p>' . $this->details . '</p>';
		}
		if (is_null($message) || is_object($message) || is_array($message))
		{
			$message = formatted_json($message);
		}
		parent::__construct($message);
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
	
	public static function log($e, $force = false, $log_obj = LOG_OBJECT_ERROR)
	{
		$log_details = new stdClass();
		if (method_exists($e, 'get_details'))
		{
			$log_details->details = $e->get_details();
		}
		
		$log_details->trace = $e->getTrace();
		// foreach ($trace as $rec)
		// {
			// $details .= $rec['file'] . ': ' . $rec['line'] . '<br>';
		// }
		
		if (!$force && method_exists($e, 'for_log'))
		{
			$force = $e->for_log();
		}
		
		if ($force)
		{
			try
			{
				db_log($log_obj, $e->getMessage(), $log_details);
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
		if (is_null($message) || is_object($message) || is_array($message))
		{
			$message = formatted_json($message);
		}
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

class LoginExc extends Exception
{
	public $user_name;
	
    public function __construct($message = '', $user_name = NULL)
	{
		if (is_null($message) || is_object($message) || is_array($message))
		{
			$message = formatted_json($message);
		}
		parent::__construct($message);
		$this->user_name = $user_name;
    }
}

?>