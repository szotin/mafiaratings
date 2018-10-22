<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// ban
	//-------------------------------------------------------------------------------------------------------
	function ban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		
		Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Banned', NULL, $user_id, $club_id);
		}
	}
	
	function ban_op_help()
	{
		$help = new ApiHelp('Ban user from the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function ban_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// unban
	//-------------------------------------------------------------------------------------------------------
	function unban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Unbanned', NULL, $user_id, $club_id);
		}
	}

	function unban_op_help()
	{
		$help = new ApiHelp('Unban user from the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function unban_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// access
	//-------------------------------------------------------------------------------------------------------
	function access_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		
		list($flags) = Db::record(get_label('user'), 'SELECT flags FROM user_clubs uc WHERE uc.user_id = ? AND uc.club_id = ?', $user_id, $club_id);
		if (isset($_REQUEST['manager']))
		{
			if ((int)$_REQUEST['manager'])
			{
				$flags |= USER_CLUB_PERM_MANAGER;
			}
			else
			{
				$flags &= ~USER_CLUB_PERM_MANAGER;
			}
		}
		
		if (isset($_REQUEST['moder']))
		{
			if ((int)$_REQUEST['moder'])
			{
				$flags |= USER_CLUB_PERM_MODER;
			}
			else
			{
				$flags &= ~USER_CLUB_PERM_MODER;
			}
		}
		
		if (isset($_REQUEST['player']))
		{
			if ((int)$_REQUEST['player'])
			{
				$flags |= USER_CLUB_PERM_PLAYER;
			}
			else
			{
				$flags &= ~USER_CLUB_PERM_PLAYER;
			}
		}
		
		Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = ? WHERE user_id = ? AND club_id = ?', $flags, $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'flags=' . $flags;
			db_log('user', 'Changed', $log_details, $user_id, $club_id);
		}
	}

	function access_op_help()
	{
		$help = new ApiHelp('Set user permissions in the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('player', 'Player permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		$help->request_param('moder', 'Moderator permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		$help->request_param('manager', 'Manager permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		return $help;
	}
	
	function access_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// site_ban
	//-------------------------------------------------------------------------------------------------------
	function site_ban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$this->check_permissions();
		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . USER_FLAG_BANNED . ') WHERE id = ?', $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Banned', NULL, $user_id);
		}
	}
	
	function site_ban_op_help()
	{
		$help = new ApiHelp('Ban user from ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	function site_ban_op_permissions()
	{
		return PERMISSION_ADMIN;
	}

	//-------------------------------------------------------------------------------------------------------
	// site_unban
	//-------------------------------------------------------------------------------------------------------
	function site_unban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$this->check_permissions();

		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . USER_FLAG_BANNED . ') WHERE id = ?', $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log('user', 'Unbanned', NULL, $user_id);
		}
	}

	function site_unban_op_help()
	{
		$help = new ApiHelp('Unban user from ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}

	function site_unban_op_permissions()
	{
		return PERMISSION_ADMIN;
	}

	//-------------------------------------------------------------------------------------------------------
	// site_access
	//-------------------------------------------------------------------------------------------------------
	function site_access_op()
	{
		$user_id = (int)get_required_param('user_id');
		$this->check_permissions();
		
		list($flags) = Db::record(get_label('user'), 'SELECT flags FROM users WHERE id = ?', $user_id);
		if (isset($_REQUEST['admin']))
		{
			if ((int)$_REQUEST['admin'])
			{
				$flags |= USER_PERM_ADMIN;
			}
			else
			{
				$flags &= ~USER_PERM_ADMIN;
			}
		}
		Db::exec(get_label('user'), 'UPDATE users SET flags = ? WHERE id = ?', $flags, $user_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'flags=' . $flags;
			db_log('user', 'Changed', $log_details, $user_id);
		}
	}

	function site_access_op_help()
	{
		$help = new ApiHelp('Set user permissions in ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('admin', 'Administrator permission in ' . PRODUCT_NAME . '. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		return $help;
	}
	
	function site_access_op_permissions()
	{
		return PERMISSION_ADMIN;
	}
}

$page = new ApiPage();
$page->run('User Operations', CURRENT_VERSION);

?>