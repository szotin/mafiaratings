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
			$num = $_REQUEST['num'];
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
		if ($_profile == NULL)
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		$user_id = (int)$_REQUEST['ban'];
		if (isset($_REQUEST['club']))
		{
			$club_id = (int)$_REQUEST['club'];
			if (!$_profile->is_manager($club_id))
			{
				throw new FatalExc(get_label('No permissions'));
			}
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . UC_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Banned', NULL, $user_id, $club_id);
			}
		}
		else 
		{
			if (!$_profile->is_admin())
			{
				throw new FatalExc(get_label('No permissions'));
			}
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . U_FLAG_BANNED . ') WHERE id = ?', $user_id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Banned', NULL, $user_id);
			}
		}
	}
	else if (isset($_REQUEST['unban']))
	{
		if ($_profile == NULL)
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		$user_id = (int)$_REQUEST['unban'];
		if (isset($_REQUEST['club']))
		{
			$club_id = (int)$_REQUEST['club'];
			if (!$_profile->is_manager($club_id))
			{
				throw new FatalExc(get_label('No permissions'));
			}
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . UC_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Unbanned', NULL, $user_id, $club_id);
			}
		}
		else 
		{
			if (!$_profile->is_admin())
			{
				throw new FatalExc(get_label('No permissions'));
			}
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . U_FLAG_BANNED . ') WHERE id = ?', $user_id);
			if (Db::affected_rows() > 0)
			{
				db_log('user', 'Unbanned', NULL, $user_id);
			}
		}
	}
	else if (isset($_REQUEST['access']))
	{
		if ($_profile == NULL)
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = (int)$_REQUEST['id'];
		
		if (isset($_REQUEST['club']))
		{
			$club_id = (int)$_REQUEST['club'];
			if (!$_profile->is_manager($club_id))
			{
				throw new FatalExc(get_label('No permissions'));
			}
			
			list($flags) = Db::record(get_label('user'), 'SELECT flags FROM user_clubs uc WHERE uc.user_id = ? AND uc.club_id = ?', $user_id, $club_id);
			if (isset($_REQUEST['manager']))
			{
				if ((int)$_REQUEST['manager'])
				{
					$flags |= UC_PERM_MANAGER;
				}
				else
				{
					$flags &= ~UC_PERM_MANAGER;
				}
			}
			
			if (isset($_REQUEST['moder']))
			{
				if ((int)$_REQUEST['moder'])
				{
					$flags |= UC_PERM_MODER;
				}
				else
				{
					$flags &= ~UC_PERM_MODER;
				}
			}
			
			if (isset($_REQUEST['player']))
			{
				if ((int)$_REQUEST['player'])
				{
					$flags |= UC_PERM_PLAYER;
				}
				else
				{
					$flags &= ~UC_PERM_PLAYER;
				}
			}
			
			Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = ? WHERE user_id = ? AND club_id = ?', $flags, $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'flags=' . $flags;
				db_log('user', 'Changed', $log_details, $user_id, $club_id);
			}
		}
		else
		{
			if (!$_profile->is_admin())
			{
				throw new FatalExc(get_label('No permissions'));
			}
			list($flags) = Db::record(get_label('user'), 'SELECT flags FROM users WHERE id = ?', $user_id);
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
			Db::exec(get_label('user'), 'UPDATE users SET flags = ? WHERE id = ?', $flags, $user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'flags=' . $flags;
				db_log('user', 'Changed', $log_details, $user_id);
			}
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