<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/game_stats.php';

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
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'banned', NULL, $user_id, $club_id);
		}
		Db::commit();
	}
	
	function ban_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Ban user from the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function merge_users($src_id, $dst_id)
	{
		$query = new DbQuery('SELECT g.id, g.log, g.canceled FROM games g LEFT OUTER JOIN players p ON p.game_id = g.id AND p.user_id = ? WHERE p.user_id IS NOT NULL OR g.moderator_id = ? OR g.user_id = ?', $src_id, $src_id, $src_id);
		while ($row = $query->next())
		{
			list ($game_id, $game_log, $is_canceled) = $row;
			$gs = new GameState();
			$gs->init_existing($game_id, $game_log, $is_canceled);
			if ($gs->change_user($src_id, $dst_id))
			{
				rebuild_game_stats($gs);
				Db::exec(get_label('game'), 'INSERT INTO rebuild_stats (time, action, email_sent) VALUES (UNIX_TIMESTAMP(), ?, 0)', 'Game ' . $game_id . ' is changed');
			}
		}
		
		list($src_name, $src_games_moderated, $src_games, $src_rating, $src_reg_time, $src_city_id, $src_club_id, $src_flags) = 
			Db::record(get_label('user'), 'SELECT name, games_moderated, games, rating, reg_time, city_id, club_id, flags FROM users WHERE id = ?', $src_id);
		
		Db::exec(get_label('email'), 'UPDATE emails SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE user_id = ? AND event_id IN (SELECT event_id FROM (SELECT event_id FROM registrations WHERE user_id = ?) x)', $src_id, $dst_id);
		Db::exec(get_label('registration'), 'UPDATE registrations SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('club'), 'DELETE FROM user_clubs WHERE user_id = ? AND club_id IN (SELECT club_id FROM (SELECT club_id FROM user_clubs WHERE user_id = ?) x)', $src_id, $dst_id);
		Db::exec(get_label('club'), 'UPDATE user_clubs SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('event'), 'DELETE FROM event_users WHERE user_id = ? AND event_id IN (SELECT event_id FROM (SELECT event_id FROM event_users WHERE user_id = ?) x)', $src_id, $dst_id);
		Db::exec(get_label('event'), 'UPDATE event_users SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('log'), 'UPDATE log SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('club'), 'UPDATE club_requests SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('league'), 'UPDATE league_requests SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('comment'), 'UPDATE event_comments SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('comment'), 'UPDATE game_comments SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('comment'), 'UPDATE photo_comments SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('comment'), 'UPDATE video_comments SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		list ($game_settings_count) = Db::record(get_label('settings'), 'SELECT count(*) FROM game_settings WHERE user_id = ?', $dst_id);
		if ($game_settings_count > 0)
		{
			Db::exec(get_label('settings'), 'DELETE FROM game_settings WHERE user_id = ?', $src_id);
		}
		else
		{
			Db::exec(get_label('settings'), 'UPDATE game_settings SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		}
		Db::exec(get_label('league manager'), 'UPDATE league_managers SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('photo'), 'UPDATE photos SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('photo album'), 'UPDATE photo_albums SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('photo'), 'UPDATE user_photos SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('stats'), 'UPDATE stats_calculators SET owner_id = ? WHERE owner_id = ?', $dst_id, $src_id);
		Db::exec(get_label('video'), 'UPDATE user_videos SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('video'), 'UPDATE videos SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('objection'), 'UPDATE objections SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('user'), 'DELETE FROM users WHERE id = ?', $src_id);
		
		$log_details = new stdClass();
		$log_details->id = (int)$src_id;
		$log_details->name = $src_name;
		if ($src_games_moderated > 0)
		{
			$log_details->games_moderated = (int)$src_games_moderated;
		}
		if ($src_games > 0)
		{
			$log_details->games_played = (int)$src_games;
			$log_details->rating = (float)$src_rating;
		}
		$log_details->reg_time = (int)$src_reg_time;
		$log_details->city_id = (int)$src_city_id;
		$log_details->club_id = (int)$src_club_id;
		$log_details->flags = (int)$src_flags;
		db_log(LOG_OBJECT_USER, 'merged', $log_details, $dst_id);
	}
	
	function delete_user($user_id)
	{
		list ($moderator_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE moderator_id = ? OR user_id = ?', $user_id, $user_id);
		if ($moderator_count > 0)
		{
			throw new Exc(get_label('Unable to delete user because they moderated some games. Try to merge them instead.'));
		}
		
		$query = new DbQuery('SELECT g.id, g.log, g.canceled FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = ?', $user_id);
		if ($row = $query->next())
		{
			do
			{
				list ($game_id, $game_log, $is_canceled) = $row;
				$gs = new GameState();
				$gs->init_existing($game_id, $game_log, $is_canceled);
				if ($gs->change_user($user_id, -1))
				{
					rebuild_game_stats($gs);
				}
				
			} while ($row = $query->next());
			
			// todo let admin know that stats should be rebuilt
		}
		
		Db::exec(get_label('email'), 'DELETE FROM emails WHERE user_id = ?', $user_id);
		Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE user_id = ?', $user_id);
		Db::exec(get_label('club'), 'DELETE FROM user_clubs WHERE user_id = ?', $user_id);
		Db::exec(get_label('event'), 'DELETE FROM event_users WHERE user_id = ?', $user_id);
		Db::exec(get_label('log'), 'DELETE FROM log WHERE user_id = ?', $user_id);
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE objection_id IN (SELECT id FROM (SELECT id FROM objections WHERE user_id = ?) x)', $user_id);
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE user_id = ?', $user_id);
		Db::exec(get_label('user'), 'DELETE FROM users WHERE id = ?', $user_id);
		
		db_log(LOG_OBJECT_USER, 'deleted', NULL, $user_id);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// unban
	//-------------------------------------------------------------------------------------------------------
	function unban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'unbanned', NULL, $user_id, $club_id);
		}
		Db::commit();
	}

	function unban_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Unban user from the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// access
	//-------------------------------------------------------------------------------------------------------
	function access_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
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
			$log_details = new stdClass();
			$log_details->club_flags = $flags;
			db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id);
		}
		Db::commit();
	}

	function access_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Set user permissions in the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('player', 'Player permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		$help->request_param('moder', 'Moderator permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		$help->request_param('manager', 'Manager permission in the club. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// site_ban
	//-------------------------------------------------------------------------------------------------------
	function site_ban_op()
	{
		$user_id = (int)get_required_param('user_id');
		check_permissions(PERMISSION_ADMIN);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . USER_FLAG_BANNED . ') WHERE id = ?', $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'banned', NULL, $user_id);
		}
		Db::commit();
	}
	
	function site_ban_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Ban user from ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// site_unban
	//-------------------------------------------------------------------------------------------------------
	function site_unban_op()
	{
		$user_id = (int)get_required_param('user_id');
		check_permissions(PERMISSION_ADMIN);

		Db::begin();
		Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . USER_FLAG_BANNED . ') WHERE id = ?', $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'unbanned', NULL, $user_id);
		}
		Db::commit();
	}

	function site_unban_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Unban user from ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// site_access
	//-------------------------------------------------------------------------------------------------------
	function site_access_op()
	{
		$user_id = (int)get_required_param('user_id');
		check_permissions(PERMISSION_ADMIN);
		
		Db::begin();
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
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_USER, 'site permissions changed', $log_details, $user_id);
		}
		Db::commit();
	}

	function site_access_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Set user permissions in ' . PRODUCT_NAME . '.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('admin', 'Administrator permission in ' . PRODUCT_NAME . '. 1 to grand the permission, 0 to revoke it.', 'remains the same');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// merge
	//-------------------------------------------------------------------------------------------------------
	function merge_op()
	{
		global $_profile;
		check_permissions(PERMISSION_USER);
		
		$dst_id = (int)get_optional_param('dst_id', $_profile->user_id);
		$src_id = (int)get_required_param('src_id');
		if ($src_id == $dst_id)
		{
			return;
		}
		
		list($user_name, $user_email) = Db::record(get_label('user'), 'SELECT name, email FROM users WHERE id = ?', $src_id);
		if ($dst_id != $_profile->user_id || $user_email != $_profile->user_email)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		Db::begin();
		$this->merge_users($src_id, $dst_id);
		Db::commit();
	}
	
	function merge_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Merge all games and stats of the provided user to current account. Email of the user must be the same as current account email.');
		$help->request_param('user_id', 'Source user id.');
		$help->request_param('dst_id', 'Target user id. Admin priviledges are requered when this id is different from current profile id.', 'current profile user id is used.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		check_permissions(PERMISSION_USER);
		
		$user_id = (int)get_required_param('user_id');
		list($user_name, $user_email) = Db::record(get_label('user'), 'SELECT name, email FROM users WHERE id = ?', $user_id);
		
		if ($user_id == $_profile->user_id)
		{
			return;
		}

		if ($user_email != $_profile->user_email)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		Db::begin();
		$this->delete_user($user_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Delete a user as if he/she never played any games. Email of the user must be the same as current account email.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// merge_all
	//-------------------------------------------------------------------------------------------------------
	function merge_all_op()
	{
		global $_profile;
		check_permissions(PERMISSION_USER);
		
		$user_id = (int)get_optional_param('user_id', $_profile->user_id);
		if ($user_id != $_profile->user_id)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		Db::begin();
		$query = new DbQuery('SELECT u2.id FROM users u1 JOIN users u2 ON u1.email = u2.email AND u1.id <> u2.id WHERE u1.id = ?', $user_id);
		while ($row = $query->next())
		{
			list ($src_id) = $row;
			$this->merge_users($src_id, $user_id);
		}
		Db::commit();
	}
	
	function merge_all_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Merge all users with the same email to current account. All their games, ratings, permissions, and actions are added to current account.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete_all
	//-------------------------------------------------------------------------------------------------------
	function delete_all_op()
	{
		global $_profile;
		check_permissions(PERMISSION_USER);
		
		$user_id = (int)get_optional_param('user_id', $_profile->user_id);
		if ($user_id != $_profile->user_id)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		Db::begin();
		$query = new DbQuery('SELECT u2.id FROM users u1 JOIN users u2 ON u1.email = u2.email AND u1.id <> u2.id WHERE u1.id = ?', $user_id);
		while ($row = $query->next())
		{
			list ($src_id) = $row;
			$this->delete_user($src_id);
		}
		Db::commit();
	}
	
	function delete_all_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Delete all users with the same email as current account.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
}
$page = new ApiPage();
$page->run('User Operations', CURRENT_VERSION);

?>