<?php

require_once 'include/session.php';
require_once 'include/user_location.php';

$result = array();
ob_start();

function check_admin_permission($user_id)
{
	global $_profile;
	if ($_profile == NULL && !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
}
	
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
	else if (isset($_REQUEST['ban']))
	{
		check_admin_permission($_REQUEST['ban']);
		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['ban']);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Banned', NULL, $_REQUEST['ban']);
		}
	}
	else if (isset($_REQUEST['unban']))
	{
		check_admin_permission($_REQUEST['unban']);
		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . U_FLAG_BANNED . ') WHERE id = ?', $_REQUEST['unban']);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Unbanned', NULL, $_REQUEST['unban']);
		}
	}
	else if (isset($_REQUEST['update']))
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = (int)$_REQUEST['id'];
		check_admin_permission($user_id);

		list($flags, $langs) = Db::record(get_label('user'), 'SELECT flags, languages FROM users WHERE id = ?', $user_id);
		if (isset($_REQUEST['male']))
		{
			if ((int)$_REQUEST['male'])
			{
				$flags |= U_FLAG_MALE;
			}
			else
			{
				$flags &= ~U_FLAG_MALE;
			}
		}
		
		if (isset($_REQUEST['admin']))
		{
			if ((int)$_REQUEST['admin'])
			{
				$flags |= U_PERM_ADMIN;
			}
			else
			{
				$flags &= ~U_PERM_ADMIN;
			}
		}
		
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		
		Db::exec(get_label('user'), 'UPDATE users SET flags = ?, languages = ? WHERE id = ?', $flags, $langs, $user_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'flags=' . $flags . "<br>langs=" . $langs;
			db_log('user', 'Changed', $log_details, $user_id);
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