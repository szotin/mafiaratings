<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

$result = array();
ob_start();
	
try
{
	initiate_session();
	check_maintenance();

/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';*/
	
	if (isset($_REQUEST['list']))
	{
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
		
		if ($term == '')
		{
			$query = new DbQuery('SELECT u.id, u.name, NULL FROM users u WHERE (u.flags & ' . U_FLAG_BANNED . ') = 0 ORDER BY rating DESC');
		}
		else
		{
			$query = new DbQuery(
				'SELECT id, name, NULL FROM users ' .
					' WHERE name LIKE ? AND (flags & ' . U_FLAG_BANNED . ') = 0' .
					' UNION' .
					' SELECT DISTINCT u.id, u.name, r.nick_name FROM users u' . 
					' JOIN registrations r ON r.user_id = u.id' .
					' WHERE r.nick_name <> u.name AND (u.flags & ' . U_FLAG_BANNED . ') = 0 AND r.nick_name LIKE ? ORDER BY name',
				'%' . $term . '%',
				'%' . $term . '%');
		}
		if ($num > 0)
		{
			$query->add(' LIMIT ' . $num);
		}
		
		while ($row = $query->next())
		{
			$player = new stdClass();
			list ($player->id, $player->name, $nickname) = $row;
			$player->id = (int)$player->id;
			if ($nickname != NULL)
			{
				$player->nickname = $nickname;
				$player->label = $player->name . '(' . $nickname . ')';
			}
			else
			{
				$player->label = $player->name;
			}
			$result[] = $player;
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>