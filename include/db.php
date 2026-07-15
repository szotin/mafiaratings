<?php

require_once __DIR__ . '/error.php';
require_once __DIR__ . '/server.php';
require_once __DIR__ . '/api_keys.php';

class SQL
{
	protected $sql;
	protected $params;
	// Must stay protected: DbQuery assigns it in its constructor, and a private property here
	// would silently create a separate dynamic property on the subclass instead.
	protected $parsed_sql;
	
	protected function _add_param($param)
	{
		if (is_a($param, 'SQL'))
		{
			$this->sql .= $param->sql;
			if ($param->params != NULL)
			{
				$this->_add_param($param->params);
			}
		}
		else if (!is_array($param))
		{
			if ($this->params == NULL)
			{
				$this->params = array();
			}
			$this->params[] = $param;
		}
		else foreach($param as $value)
		{
			$this->_add_param($value);
		}
	}
	
	protected function _add($args, $start = 0)
	{
		while (true)
		{
			$count = count($args);
			if ($count <= $start)
			{
				return;
			}
			
			$obj = $args[$start];
			if (!is_array($obj))
			{
				break;
			}
			
			if ($count > $start + 1 && is_numeric($args[1]))
			{
				$start = $args[1];
			}
			else
			{
				$start = 0;
			}
			$args = $obj;
		}
	
		if (is_string($obj))
		{
			$this->sql .= $obj;
			++$start;
		}
		
		for ($i = $start; $i < $count; ++$i)
		{
			$this->_add_param($args[$i]);
		}
		
/*		echo $this->sql . '<br>';
		if ($this->params != NULL)
		{
			print_r($this->params);
			echo '<br>';
		}
		echo '------------------------------------------------<br>';*/
	}
	
	function __construct()
	{
		$this->sql = '';
		$this->params = NULL;
		$this->parsed_sql = NULL;
		
		$args = func_get_args();
		$this->_add($args);
	}
	
	function set()
	{
		$this->_reset();
		
		$this->sql = '';
		$this->params = NULL;
		
		$args = func_get_args();
		$this->_add($args);
	}
	
	function add()
	{
		$this->_reset();
		
		$args = func_get_args();
		$this->_add($args);
	}
	
	function clear()
	{
		$this->set();
	}
	
	function get_sql()
	{
		return $this->sql;
	}
	
	protected function _reset()
	{
		$this->parsed_sql = NULL;
	}
	
	function get_parsed_sql()
	{
		if ($this->parsed_sql == NULL)
		{
			if ($this->params != NULL)
			{
				$p1 = 0;
				$this->parsed_sql = '';
				foreach ($this->params as $param)
				{
					$p2 = strpos($this->sql, '?', $p1);
					if ($p2 === false)
					{
						break;
					}
					$this->parsed_sql .= substr($this->sql, $p1, $p2 - $p1) . DbQuery::_param($param);
					$p1 = $p2 + 1;
				}
				$this->parsed_sql .= substr($this->sql, $p1);
			}
			else
			{
				$this->parsed_sql = $this->sql;
			}
		}
		return $this->parsed_sql;
	}
	
	function is_empty()
	{
		return empty($this->sql);
	}
}

class DbQuery extends SQL
{
	private $query;
	
	static function _param($param)
	{
		Db::connect();
		if ($param === NULL)
		{
			return 'NULL';
		}
		if (is_object($param) || is_array($param))
		{
			$param = json_encode($param);
		}
		return '\'' . Db::_escape($param) . '\'';
	}

	protected function _reset()
	{
		parent::_reset();
		$this->query = NULL;
	}

	function __construct($obj = '')
	{
		$this->query = NULL;
		$this->sql = '';
		$this->parsed_sql = NULL;
		$this->params = NULL;

		$args = func_get_args();
		$this->_add($args);
	}
	
	public function exec($obj_name = NULL)
	{
		Db::connect();
		$parsed_sql = $this->get_parsed_sql();
		
		$this->query = Db::_query($parsed_sql);
		if (!$this->query)
		{
			if ($obj_name != NULL)
			{
				if (strpos($parsed_sql, 'INSERT') === 0)
				{
					$message = get_label('Unable to create [0].', $obj_name);
				}
				else if (strpos($parsed_sql, 'UPDATE') === 0)
				{
					$message = get_label('Unable to change [0].', $obj_name);
				}
				else if (strpos($parsed_sql, 'DELETE') === 0)
				{
					$message = get_label('Unable to delete [0].', $obj_name);
				}
				else
				{
					$message = get_label('Unable to find [0].', $obj_name);
				}
			}
			else
			{
				$message = get_label('Query failed');
			}
			throw new FatalExc($message, $parsed_sql . '<br>' . Db::_error(), true);
		}
		return $this;
	}

	function next($obj_name = '')
	{
		if ($this->query == NULL)
		{
			$this->exec($obj_name);
		}
		return Db::_fetch_row($this->query);
	}

	function record($obj_name)
	{
		$row = $this->next($obj_name);
		if (!$row)
		{
			throw new FatalExc(get_label('Unable to find [0].', $obj_name), $this->get_parsed_sql() . '<br>' . Db::_error(), true);
		}
		return $row;
	}

	function num_rows($obj_name = NULL)
	{
		if ($this->query == NULL)
		{
			$this->exec($obj_name);
		}

		$row = Db::_num_rows($this->query);
		if ($row === false)
		{
			throw new FatalExc(get_label('Unable to get number of records'), $this->get_parsed_sql() . '<br>' . Db::_error(), true);
		}
		return $row;
	}
}

class DbCommiter
{
	public function commit()
	{
	}
	
	public function rollback()
	{
	}
}

class Db
{
	private static $connected = false;
	private static $error = NULL;
	private static $trans_count = 0;
	private static $commiters = NULL;
	private static $name;
	private static $user;
	private static $password;
	private static $link = NULL;
	private static $use_mysqli = NULL;

	static function init($name, $user, $password)
	{
		Db::$name = $name;
		Db::$user = $user;
		Db::$password = $password;
	}

	// The legacy ext/mysql extension is removed in PHP 7, while mysqli is available since PHP 5.0.
	// So mysqli is used whenever it is present, and ext/mysql stays as a fallback for old installations
	// that were built without mysqli. The driver is resolved once and cached, so that the per-query
	// cost is a boolean check rather than a function_exists() lookup.
	static function _use_mysqli()
	{
		if (Db::$use_mysqli === NULL)
		{
			Db::$use_mysqli = function_exists('mysqli_connect');
			if (!Db::$use_mysqli && !function_exists('mysql_connect'))
			{
				throw new FatalExc(get_label('Can not connect to the database'), 'Neither the mysqli nor the mysql PHP extension is available.', true);
			}
		}
		return Db::$use_mysqli;
	}

	static function _escape($str)
	{
		Db::connect();
		if (Db::$use_mysqli)
		{
			return mysqli_real_escape_string(Db::$link, $str);
		}
		return mysql_real_escape_string($str);
	}

	static function _query($sql)
	{
		Db::connect();
		if (Db::$use_mysqli)
		{
			return mysqli_query(Db::$link, $sql);
		}
		return mysql_query($sql);
	}

	static function _error()
	{
		// Resolved through the accessor rather than the field, because this can be reached
		// before the driver was picked, and on PHP 7+ the mysql_* branch would be fatal.
		if (Db::_use_mysqli())
		{
			if (Db::$link == NULL)
			{
				return mysqli_connect_error();
			}
			return mysqli_error(Db::$link);
		}
		return mysql_error();
	}

	static function _fetch_row($query)
	{
		if (Db::$use_mysqli)
		{
			// Queries that return no result set (INSERT/UPDATE/...) give true rather than a result
			// object. ext/mysql only warned about that, mysqli would raise a TypeError, so it is
			// filtered out here to keep the old behaviour.
			if (!($query instanceof mysqli_result))
			{
				return false;
			}
			$row = mysqli_fetch_row($query);
			// mysqli returns NULL when there are no more rows, ext/mysql returned false.
			if ($row === NULL)
			{
				return false;
			}
			return $row;
		}
		return mysql_fetch_row($query);
	}

	static function _num_rows($query)
	{
		if (Db::$use_mysqli)
		{
			if (!($query instanceof mysqli_result))
			{
				return false;
			}
			return mysqli_num_rows($query);
		}
		return mysql_num_rows($query);
	}

	static function connect()
	{
		if (!Db::$connected)
		{
			if (Db::_use_mysqli())
			{
				// Since PHP 8.1 mysqli throws exceptions on errors by default. Reporting is turned off
				// so that it keeps returning false and every error is reported through FatalExc below,
				// identically on all PHP versions.
				mysqli_report(MYSQLI_REPORT_OFF);

				Db::$link = @mysqli_connect('127.0.0.1', Db::$user, Db::$password);
				if (!Db::$link)
				{
					throw new FatalExc(get_label('Can not connect to the database'), mysqli_connect_error(), true);
				}

				if (!mysqli_select_db(Db::$link, Db::$name))
				{
					throw new FatalExc(get_label('Can not use mafia database'), mysqli_error(Db::$link), true);
				}

				// set_charset is used instead of a "set names" query because it also tells the client
				// library which charset is in use, which is what real_escape_string relies on.
				mysqli_set_charset(Db::$link, 'utf8');
			}
			else
			{
				if (!@mysql_connect('127.0.0.1', Db::$user, Db::$password))
				{
					throw new FatalExc(get_label('Can not connect to the database'), mysql_error(), true);
				}

				if (!mysql_select_db(Db::$name))
				{
					throw new FatalExc(get_label('Can not use mafia database'), mysql_error(), true);
				}

				mysql_query("set names utf8");
			}
			Db::$connected = true;
		}
	}

	static function disconnect()
	{
		if (Db::$connected)
		{
			if (Db::$trans_count > 0)
			{
				Db::_query('ROLLBACK');
			}
			Db::$trans_count = 0;
			if (Db::$use_mysqli)
			{
				mysqli_close(Db::$link);
				Db::$link = NULL;
			}
			else
			{
				mysql_close();
			}
			Db::$connected = false;
		}
	}

	static function add_commiter($commiter)
	{
		if (Db::$trans_count <= 0)
		{
			Db::$trans_count = 0;
			$commiter->commit();
		}
		else if (Db::$commiters == NULL)
		{
			Db::$commiters = array($commiter);
		}
		else
		{
			Db::$commiters[] = $commiter;
		}
	}

	static function begin()
	{
	//	echo 'begin<br>';
		if (Db::$trans_count <= 0)
		{
			Db::$trans_count = 0;
			Db::connect();
			if (!Db::_query('BEGIN'))
			{
				throw new FatalExc(get_label('Unable to start transaction.'), Db::_error(), true);
			}
		}
		++Db::$trans_count;
		return true;
	}

	static function commit()
	{
	//	echo 'commit<br>';
		--Db::$trans_count;
		if (Db::$trans_count < 0)
		{
			Db::$trans_count = 0;
			throw new FatalExc(get_label('Cannot commit transaction that was not started.'), NULL, true);
		}
		else if (Db::$trans_count == 0)
		{
			Db::connect();
			if (!Db::_query('COMMIT'))
			{
				throw new FatalExc(get_label('Failed to commit transaction. Please try again.'), Db::_error(), true);
			}
			
			if (Db::$commiters != NULL)
			{
				foreach (Db::$commiters as $commiter)
				{
					$commiter->commit();
				}
				Db::$commiters = NULL;
			}
		}
	}

	static function rollback()
	{
	//	echo 'rollback<br>';
		if (Db::$trans_count > 0)
		{
			Db::$trans_count = 0;
			Db::connect();
			Db::_query('ROLLBACK');
			if (Db::$commiters != NULL)
			{
				foreach (Db::$commiters as $commiter)
				{
					$commiter->rollback();
				}
				Db::$commiters = NULL;
			}
		}
	}
	
	static function exec($obj_name)
	{
		$args = func_get_args();
		$query = new DbQuery($args, 1);
		return $query->exec($obj_name);
	}
	
	static function exec_with_echo($obj_name)
	{
		$args = func_get_args();
		$query = new DbQuery($args, 1);
		echo $query->get_parsed_sql();
		return $query->exec($obj_name);
	}
	
	static function record($obj_name)
	{
		$args = func_get_args();
		$query = new DbQuery($args, 1);
		return $query->record($obj_name);
	}
	
	static function query()
	{
		$args = func_get_args();
		return new DbQuery($args);
	}
	
	static function affected_rows()
	{
		Db::connect();
		if (Db::$use_mysqli)
		{
			return mysqli_affected_rows(Db::$link);
		}
		return mysql_affected_rows();
	}
}

if (is_testing_server())
{
	Db::init('mafia', 'root', '');
}
else if (is_demo_server())
{
	Db::init(DB_DEMO_NAME, DB_DEMO_USER_NAME, DB_DEMO_PASSWORD);
}
else
{
	Db::init(DB_NAME, DB_USER_NAME, DB_PASSWORD);
}

?>