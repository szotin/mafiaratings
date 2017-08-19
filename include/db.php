<?php

require_once 'include/error.php';
require_once 'include/server.php';

class SQL
{
	protected $sql;
	protected $params;
	private $parsed_sql;
	
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
		return '\'' . mysql_real_escape_string($param) . '\'';
	}
	
	protected function _reset()
	{
		SQL::_reset();
		$this->query = NULL;
	}
	
	function __construct($obj = '')
	{
		$this->query = NULL;
		$this->parsed_sql = NULL;
		$this->params = NULL;
		
		$args = func_get_args();
		$this->_add($args);
	}
	
	public function exec($obj_name = NULL)
	{
		Db::connect();
		$parsed_sql = $this->get_parsed_sql();
		
		$this->query = mysql_query($parsed_sql);
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
			throw new FatalExc($message, $parsed_sql . '<br>' . mysql_error(), true);
		}
		return $this;
	}
	
	function next($obj_name = '')
	{
		if ($this->query == NULL)
		{
			$this->exec($obj_name);
		}
		return mysql_fetch_row($this->query);
	}
	
	function record($obj_name)
	{
		$row = $this->next($obj_name);
		if (!$row)
		{
			throw new FatalExc(get_label('Unable to find [0].', $obj_name), $this->get_parsed_sql() . '<br>' . mysql_error(), true);
		}
		return $row;
	}
	
	function num_rows($obj_name = NULL)
	{
		if ($this->query == NULL)
		{
			$this->exec($obj_name);
		}
		
		$row = mysql_num_rows($this->query);
		if ($row === false)
		{
			throw new FatalExc(get_label('Unable to get number of records'), $this->get_parsed_sql() . '<br>' . mysql_error(), true);
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
	
	static function init($name, $user, $password)
	{
		Db::$name = $name;
		Db::$user = $user;
		Db::$password = $password;
	}
	
	static function connect()
	{
		if (!Db::$connected)
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
			Db::$connected = true;
		}
	}

	static function disconnect()
	{
		if (Db::$connected)
		{
			if (Db::$trans_count > 0)
			{
				mysql_query('ROLLBACK');
			}
			Db::$trans_count = 0;
			mysql_close();
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
			if (!mysql_query('BEGIN'))
			{
				throw new FatalExc(get_label('Unable to start transaction.'), mysql_error(), true);
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
			if (!mysql_query('COMMIT'))
			{
				throw new FatalExc(get_label('Failed to commit transaction. Please try again.'), mysql_error(), true);
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
			mysql_query('ROLLBACK');
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
		return mysql_affected_rows();
	}
}

if (is_testing_server())
{
	Db::init('mafia', 'root', '');
}
else if (is_demo_server())
{
	Db::init('mafiawor_demomafia', 'mafiawor_demo', '4uyF6vHYTn7nOf67L');
}
else
{
	Db::init('mafiawor_mafia', 'mafiawor_php', 'sasha1203');
}

?>