<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

class Player
{
	public $id;
	public $label;
	
	function __construct($row)
	{
		list ($this->id, $this->label) = $row;
	}
}

try
{
	initiate_session();
	
	$term = '';
	if (isset($_REQUEST['term']))
	{
		$term = $_REQUEST['term'];
	}
	
	$num = 16;
	if (isset($_REQUEST['num']) && is_numeric($_REQUEST['num']))
	{
		$num = $_REQUEST['mc'];
	}
	
	$players = array();
	if (isset($_REQUEST['nu']))
	{
		$players[] = new Player(array(0, $_REQUEST['nu']));
	}

	if ($term == '')
	{
		$query = new DbQuery('SELECT u.id, u.name FROM users u WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 ORDER BY rating DESC');
	}
	else
	{
		$query = new DbQuery('SELECT u.id, u.name FROM users u WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 AND u.name LIKE(?) ORDER BY u.name', $term . '%');
	}
	if ($num > 0)
	{
		$query->add(' LIMIT ' . $num);
	}
	
	while ($row = $query->next())
	{
		$players[] = new Player($row);
	}
	echo json_encode($players);
}
catch (Exception $e)
{
	Exc::log($e, true);
	echo json_encode(array('error' => $e->getMessage()));
}

?>