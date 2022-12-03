<?php

require_once '../../include/api.php';
require_once '../../include/user_location.php';
require_once '../../include/user.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/image.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

function access_flags($flags)
{
	if (isset($_REQUEST['manager']))
	{
		if ((int)$_REQUEST['manager'])
		{
			$flags |= USER_PERM_MANAGER;
		}
		else
		{
			$flags &= ~USER_PERM_MANAGER;
		}
	}
	
	if (isset($_REQUEST['moder']))
	{
		if ((int)$_REQUEST['moder'])
		{
			$flags |= USER_PERM_REFEREE;
		}
		else
		{
			$flags &= ~USER_PERM_REFEREE;
		}
	}
	
	if (isset($_REQUEST['player']))
	{
		if ((int)$_REQUEST['player'])
		{
			$flags |= USER_PERM_PLAYER;
		}
		else
		{
			$flags &= ~USER_PERM_PLAYER;
		}
	}
	return $flags;
}

function delete_file($path)
{
	if (file_exists($path))
	{
		unlink($path);
	}
}

class ApiPage extends OpsApiPageBase
{
	function merge_users($src_id, $dst_id)
	{
		$query = new DbQuery('SELECT g.id, g.json, g.feature_flags FROM games g LEFT OUTER JOIN players p ON p.game_id = g.id AND p.user_id = ? WHERE p.user_id IS NOT NULL OR g.moderator_id = ? OR g.user_id = ?', $src_id, $src_id, $src_id);
		while ($row = $query->next())
		{
			list ($game_id, $json, $feature_flags) = $row;
			$game = new Game($json, $feature_flags);
			$game->change_user($src_id, $dst_id);
			$game->update();
		}
		
		list($src_name, $src_games_moderated, $src_games, $src_rating, $src_reg_time, $src_city_id, $src_club_id, $src_flags) = 
			Db::record(get_label('user'), 'SELECT name, games_moderated, games, rating, reg_time, city_id, club_id, flags FROM users WHERE id = ?', $src_id);
		
		Db::exec(get_label('email'), 'UPDATE emails SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('club'), 'DELETE FROM club_users WHERE user_id = ? AND club_id IN (SELECT club_id FROM (SELECT club_id FROM club_users WHERE user_id = ?) x)', $src_id, $dst_id);
		Db::exec(get_label('club'), 'UPDATE club_users SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('event'), 'DELETE FROM event_users WHERE user_id = ? AND event_id IN (SELECT event_id FROM (SELECT event_id FROM event_users WHERE user_id = ?) x)', $src_id, $dst_id);
		Db::exec(get_label('event'), 'UPDATE event_users SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('event'), 'UPDATE event_places SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('log'), 'UPDATE log SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
		Db::exec(get_label('event'), 'UPDATE tournament_places SET user_id = ? WHERE user_id = ?', $dst_id, $src_id);
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
		$query = new DbQuery('SELECT g.id, g.json, g.feature_flags FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = ?', $user_id);
		if ($row = $query->next())
		{
			do
			{
				list ($game_id, $json, $feature_flags) = $row;
				$game = new Game($json, $feature_flags);
				$game->change_user($user_id, -1);
				$game->update();
				
			} while ($row = $query->next());
			
			// todo let admin know that stats should be rebuilt
		}
		
		Db::exec(get_label('email'), 'DELETE FROM emails WHERE user_id = ?', $user_id);
		Db::exec(get_label('club'), 'DELETE FROM club_users WHERE user_id = ?', $user_id);
		Db::exec(get_label('event'), 'DELETE FROM event_users WHERE user_id = ?', $user_id);
		Db::exec(get_label('log'), 'DELETE FROM log WHERE user_id = ?', $user_id);
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE objection_id IN (SELECT id FROM (SELECT id FROM objections WHERE user_id = ?) x)', $user_id);
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE user_id = ?', $user_id);
		Db::exec(get_label('user'), 'DELETE FROM users WHERE id = ?', $user_id);
		
		db_log(LOG_OBJECT_USER, 'deleted', NULL, $user_id);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// ban
	//-------------------------------------------------------------------------------------------------------
	function ban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE club_users SET flags = (flags | ' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
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
	
	//-------------------------------------------------------------------------------------------------------
	// unban
	//-------------------------------------------------------------------------------------------------------
	function unban_op()
	{
		$user_id = (int)get_required_param('user_id');
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('user'), 'UPDATE club_users SET flags = (flags & ~' . USER_CLUB_FLAG_BANNED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
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
		$club_id = (int)get_optional_param('club_id', 0);
		$event_id = (int)get_optional_param('event_id', 0);
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		
		Db::begin();
		if ($event_id > 0)
		{
			list($club_id, $tour_id, $flags) = Db::record(get_label('event'), 'SELECT e.club_id, e.tournament_id, eu.flags FROM event_users eu JOIN events e ON e.id = eu.event_id WHERE eu.event_id = ? AND eu.user_id = ?', $event_id, $user_id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);
			$flags = access_flags($flags);
			
			Db::exec(get_label('user'), 'UPDATE event_users SET flags = ? WHERE user_id = ? AND event_id = ?', $flags, $user_id, $event_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->event_id = $event_id;
				$log_details->event_flags = $flags;
				db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id);
			}
		}
		else if ($tournament_id > 0)
		{
			list($club_id, $flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, tu.flags FROM tournament_users tu JOIN tournaments t ON t.id = tu.tournament_id WHERE tu.tournament_id = ? AND tu.user_id = ?', $tournament_id, $user_id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
			$flags = access_flags($flags);
			
			Db::exec(get_label('user'), 'UPDATE tournament_users SET flags = ? WHERE user_id = ? AND tournament_id = ?', $flags, $user_id, $tournament_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->tournament_id = $tournament_id;
				$log_details->tournament_flags = $flags;
				db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id);
			}
		}
		else if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			list($flags) = Db::record(get_label('club'), 'SELECT cu.flags FROM club_users cu JOIN clubs c ON c.id = cu.club_id WHERE cu.club_id = ? AND cu.user_id = ?', $club_id, $user_id);
			$flags = access_flags($flags);
			
			Db::exec(get_label('user'), 'UPDATE club_users SET flags = ? WHERE user_id = ? AND club_id = ?', $flags, $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->club_flags = $flags;
				db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id);
			}
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
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
		}
		Db::commit();
	}

	function access_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Set user permissions in the club.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('event_id', 'Event id.', 'whole site access is controlled (admin permission is requred for it).');
		$help->request_param('tournament_id', 'Tournament id.', 'whole site access is controlled (admin permission is requred for it).');
		$help->request_param('club_id', 'Club id.', 'whole site access is controlled (admin permission is requred for it).');
		$help->request_param('player', 'Player permission in the club. 1 to grand the permission, 0 to revoke it. Does not work when none of club_id, event_id, or tournament_id is set.', 'remains the same');
		$help->request_param('moder', 'Moderator permission in the club. 1 to grand the permission, 0 to revoke it. Does not work when none of club_id, event_id, or tournament_id is set.', 'remains the same');
		$help->request_param('manager', 'Manager permission in the club. 1 to grand the permission, 0 to revoke it. Does not work when none of club_id, event_id, or tournament_id is set.', 'remains the same');
		$help->request_param('admin', 'Administrator permission for the site. 1 to grand the permission, 0 to revoke it. Does not work when club_id, event_id, or tournament_id is set.', 'remains the same');
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

	//-------------------------------------------------------------------------------------------------------
	// join_club
	//-------------------------------------------------------------------------------------------------------
	function join_club_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$club_id = (int)get_required_param('club_id');
		
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
		
		Db::begin();
		list ($count) = Db::record(get_label('membership'), 'SELECT count(*) FROM club_users WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if ($count == 0)
		{
			Db::exec(get_label('membership'), 'INSERT INTO club_users (user_id, club_id, flags) values (?, ?, ' . USER_CLUB_NEW_PLAYER_FLAGS . ')', $user_id, $club_id);
			db_log(LOG_OBJECT_USER, 'joined club', NULL, $user_id, $club_id);
			if ($user_id == $owner_id)
			{
				$_profile->update_clubs();
			}
		}
		Db::commit();
		
		$this->response['club_id'] = $club_id;
		$this->response['user_id'] = $user_id;
	}
	
	function join_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Make user a club member.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('club_id', 'Club id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// quit_club
	//-------------------------------------------------------------------------------------------------------
	function quit_club_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$club_id = (int)get_required_param('club_id');
		
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
		
		Db::begin();
		Db::exec(get_label('membership'), 'DELETE FROM club_users WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'left club', NULL, $user_id, $club_id);
		}
		Db::commit();
		if ($user_id == $owner_id)
		{
			$_profile->update_clubs();
		}
		
		$this->response['club_id'] = $club_id;
		$this->response['user_id'] = $user_id;
	}
	
	function quit_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Remove user from the members of the club.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('club_id', 'Club id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// join_event
	//-------------------------------------------------------------------------------------------------------
	function join_event_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if ($count == 0)
		{
			Db::exec(get_label('registration'), 'INSERT INTO event_users (user_id, event_id, flags) values (?, ?, ' . USER_EVENT_NEW_PLAYER_FLAGS . ')', $user_id, $event_id);
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			db_log(LOG_OBJECT_USER, 'joined event', $log_details, $user_id, $club_id);
		}
		Db::commit();
		
		$this->response['event_id'] = $event_id;
		$this->response['user_id'] = $user_id;
	}
	
	function join_event_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Register user to an event.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('event_id', 'Event id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('event_id', 'Event id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// quit_event
	//-------------------------------------------------------------------------------------------------------
	function quit_event_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			db_log(LOG_OBJECT_USER, 'left event', $log_details, $user_id, $club_id);
		}
		Db::commit();
		
		$this->response['event_id'] = $event_id;
		$this->response['user_id'] = $user_id;
	}
	
	function quit_event_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Remove user from the registrations to the event.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('event_id', 'Event id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('event_id', 'Event id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// join_tournament
	//-------------------------------------------------------------------------------------------------------
	function join_tournament_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$tournament_id = (int)get_required_param('tournament_id');
		$team = get_optional_param('team', NULL);
		
		Db::begin();
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
		
		$query = new DbQuery('SELECT t.id, t.name FROM tournament_users u LEFT OUTER JOIN tournament_teams t ON u.team_id = t.id WHERE u.user_id = ? AND u.tournament_id = ?', $user_id, $tournament_id);
		if ($row = $query->next())
		{
			list($old_team_id, $old_team) = $row;
			if ($team != $old_team)
			{
				if ($team == NULL || empty($team))
				{
					Db::exec(get_label('registration'), 'UPDATE tournament_users SET team_id = NULL WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
				}
				else
				{
					$query = new DbQuery('SELECT id FROM tournament_teams WHERE name = ?', $team);
					if ($row = $query->next())
					{
						list($team_id) = $row;
					}
					else
					{
						Db::exec(get_label('team'), 'INSERT INTO tournament_teams (tournament_id, name) VALUES (?, ?)', $tournament_id, $team);
						list ($team_id) = Db::record(get_label('team'), 'SELECT LAST_INSERT_ID()');
					}
					Db::exec(get_label('registration'), 'UPDATE tournament_users SET team_id = ? WHERE user_id = ? AND tournament_id = ?', $team_id, $user_id, $tournament_id);
				}
				
				list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_users WHERE team_id = ? AND tournament_id = ?', $old_team_id, $tournament_id);
				if ($count <= 0)
				{
					Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE id = ?', $old_team_id);
				}
			}
		}
		else
		{
			$team_id = NULL;
			if ($team != NULL && !empty($team))
			{
				$query = new DbQuery('SELECT id FROM tournament_teams WHERE name = ?', $team);
				if ($row = $query->next())
				{
					list($team_id) = $row;
				}
				else
				{
					Db::exec(get_label('team'), 'INSERT INTO tournament_teams (tournament_id, name) VALUES (?, ?)', $tournament_id, $team);
					list ($team_id) = Db::record(get_label('team'), 'SELECT LAST_INSERT_ID()');
				}
			}
			Db::exec(get_label('registration'), 'INSERT INTO tournament_users (user_id, tournament_id, flags, team_id) values (?, ?, ' . USER_TOURNAMENT_NEW_PLAYER_FLAGS . ', ?)', $user_id, $tournament_id, $team_id);
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			if ($team_id != NULL)
			{
				$log_details->team = $team;
			}
			db_log(LOG_OBJECT_USER, 'joined tournament', $log_details, $user_id, $club_id);
		}
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
		$this->response['user_id'] = $user_id;
	}
	
	function join_tournament_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Register user to an tournament.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('tournament_id', 'Tournament id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// quit_tournament
	//-------------------------------------------------------------------------------------------------------
	function quit_tournament_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list($club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $tournament_id);
		
		list($team_id) = Db::record(get_label('registration'), 'SELECT team_id FROM tournament_users WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
		Db::exec(get_label('registration'), 'DELETE FROM tournament_users WHERE user_id = ? AND tournament_id = ?', $user_id, $tournament_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->tournament_id = $tournament_id;
			db_log(LOG_OBJECT_USER, 'left tournament', $log_details, $user_id, $club_id);
		}
		if (!is_null($team_id))
		{
			list($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM tournament_users WHERE team_id = ? AND tournament_id = ?', $team_id, $tournament_id);
			if ($count <= 0)
			{
				Db::exec(get_label('team'), 'DELETE FROM tournament_teams WHERE id = ?', $team_id);
			}
		}
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
		$this->response['user_id'] = $user_id;
	}
	
	function quit_tournament_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Remove user from the registrations to the tournament.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('tournament_id', 'Tournament id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('tournament_id', 'Tournament id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// edit
	//-------------------------------------------------------------------------------------------------------
	function edit_op()
	{
		global $_profile, $_lang_code;
		if ($_profile == NULL)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$user_id = (int)get_optional_param('user_id', $_profile->user_id);
		
		list($user_club_id, $user_name, $user_flags, $user_city_id, $user_country_id, $user_email, $user_langs, $user_phone) = Db::record(get_label('user'), 'SELECT u.club_id, u.name, u.flags, u.city_id, ct.country_id, u.email, u.languages, u.phone FROM users u JOIN cities ct ON ct.id = u.city_id WHERE u.id = ?', $user_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $user_club_id);
		
		$name = get_optional_param('name', $user_name);
		if ($name != $user_name)
		{
			check_user_name($name);
		}
		
		$email = get_optional_param('email', $user_email);
		if ($email != $user_email)
		{
			if (empty($email))
			{
				throw new Exc(get_label('Please enter [0].', get_label('email address')));
			}
			else if (!is_email($email))
			{
				throw new Exc(get_label('[0] is not a valid email address.', $email));
			}
			send_activation_email($user_id, $name, $email);
			echo get_label('You are trying to change your email address. Please check your email and click a link in it to finalize the change.');
		}
		
		$club_id = (int)get_optional_param('club_id', $user_club_id);
		if ($club_id <= 0)
		{
			$club_id = NULL;
		}
		
		$country_id = (int)$user_country_id;
		if (isset($_REQUEST['country_id']))
		{
			$country_id = (int)$_REQUEST['country_id'];
		}
		else if (isset($_REQUEST['country']))
		{
			$country_id = retrieve_country_id($_REQUEST['country']);
		}
		
		$city_id = $user_city_id;
		if (isset($_REQUEST['city_id']))
		{
			$city_id = (int)$_REQUEST['city_id'];
		}
		else if (isset($_REQUEST['city']))
		{
			$city_id = retrieve_city_id($_REQUEST['city'], $country_id, get_timezone());
		}
		
		$langs = (int)get_optional_param('langs', $user_langs);
		$phone = get_optional_param('phone', $user_phone);
		
		$flags = $user_flags;
		if (isset($_REQUEST['message_notify']))
		{
			if ($_REQUEST['message_notify'])
			{
				$flags |= USER_FLAG_MESSAGE_NOTIFY;
			}
			else
			{
				$flags &= ~USER_FLAG_MESSAGE_NOTIFY;
			}
		}
		
		if (isset($_REQUEST['photo_notify']))
		{
			if ($_REQUEST['photo_notify'])
			{
				$flags |= USER_FLAG_PHOTO_NOTIFY;
			}
			else
			{
				$flags &= ~USER_FLAG_PHOTO_NOTIFY;
			}
		}
		
		if (isset($_REQUEST['male']))
		{
			if ($_REQUEST['male'])
			{
				$flags |= USER_FLAG_MALE;
			}
			else
			{
				$flags &= ~USER_FLAG_MALE;
			}
		}
		
		$picture_uploaded = false;
		if (isset($_FILES['picture']))
		{
			upload_logo('picture', '../../' . USER_PICS_DIR, $user_id);
			
			$icon_version = (($flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET) + 1;
			if ($icon_version > USER_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~USER_ICON_MASK) + ($icon_version << USER_ICON_MASK_OFFSET);
			$picture_uploaded = true;
		}
		
		Db::begin();
		if (isset($_REQUEST['pwd1']))
		{
			$password1 = $_REQUEST['pwd1'];
			$password2 = get_required_param('pwd2');
			check_password($password1, $password2);
			Db::exec(get_label('user'), 'UPDATE users SET password = ? WHERE id = ?', md5($password1), $user_id);
			if ($flags & USER_FLAG_NO_PASSWORD)
			{
				$flags = $flags & ~USER_FLAG_NO_PASSWORD;
			}
			else
			{
				db_log(LOG_OBJECT_USER, 'changed password', NULL, $user_id);
			}
		}
		
		$update_clubs = false;
		Db::exec(
			get_label('user'), 
			'UPDATE users SET name = ?, flags = ?, city_id = ?, languages = ?, phone = ?, club_id = ? WHERE id = ?',
			$name, $flags, $city_id, $langs, $phone, $club_id, $user_id);
		if (Db::affected_rows() > 0)
		{
			list($is_member) = Db::record(get_label('membership'), 'SELECT count(*) FROM club_users WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if ($is_member <= 0 && !is_null($club_id))
			{
				Db::exec(get_label('membership'), 'INSERT INTO club_users (user_id, club_id, flags) values (?, ?, ' . USER_CLUB_NEW_PLAYER_FLAGS . ')', $user_id, $club_id);
				db_log(LOG_OBJECT_USER, 'joined club', NULL, $user_id, $club_id);
				$update_clubs = true;
			}
			
			$log_details = new stdClass();
			if ($user_flags != $flags)
			{
				$log_details->flags = $flags;
			}
			
			if ($picture_uploaded)
			{
				$log_details->picture_uploaded = true;
			}
			
			if ($user_name != $name)
			{
				$log_details->flags = $flags;
			}
			
			if ($user_city_id != $city_id)
			{
				$log_details->city_id = $city_id;
			}
			
			if ($user_langs != $langs)
			{
				$log_details->langs = $langs;
			}
				
			if (!is_null($club_id))
			{
				list ($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
				$log_details->club_id = $club_id;
				$log_details->club = $club_name;
			}
			db_log(LOG_OBJECT_USER, 'changed', $log_details, $user_id);
		}
		Db::commit();
		
		if ($_profile->user_id == $user_id)
		{
			$_profile->user_name = $name;
			$_profile->user_flags = $flags;
			$_profile->user_langs = $langs;
			$_profile->user_phone = $phone;
			$_profile->user_club_id = $club_id;
			if ($_profile->city_id != $city_id)
			{
				list ($_profile->country_id) = Db::record(get_label('city'), 'SELECT country_id FROM cities WHERE id = ?', $city_id);
				$_profile->city_id = $city_id;
			}
			if ($update_clubs)
			{
				$_profile->update_clubs();
			}
		}
	}
	
	function edit_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Change account settings.');
		$help->request_param('country_id', 'Country id.', 'remains the same unless <q>country</q> is set');
		$help->request_param('country', 'Country name. An alternative to <q>country_id</q>. It is used only if <q>country_id</q> is not set.', 'remains the same unless <q>country_id</q> is set');
		$help->request_param('city_id', 'City id.', 'remains the same unless <q>city</q> is set');
		$help->request_param('city', 'City name. An alternative to <q>city_id</q>. It is used only if <q>city_id</q> is not set.', 'remains the same unless <q>city_id</q> is not set');
		$help->request_param('club_id', 'User main club. If set to 0 or negative, user main club is set to none.', 'remains the same');
		$help->request_param('phone', 'User phone.', 'remains the same');
		$help->request_param('langs', 'User languages. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same');
		$help->request_param('male', '1 for male, 0 for female.', 'remains the same');
		$help->request_param('pwd1', 'User password.', 'remains the same');
		$help->request_param('pwd2', 'Password confirmation. Must be the same as <q>pwd1</q>. Must be set when <q>pwd1</q> is set. Ignored when <q>pwd1</q> is not set.', '-');
		$help->request_param('message_notify', '1 to notify user when someone replies to his/her message, 0 to turn notificetions off.', 'remains the same');
		$help->request_param('photo_notify', '1 to notify user when someone comments on his/her photo, 0 to turn notificetions off.', 'remains the same');
		$help->request_param('picture', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");

		$help->response_param('message', 'Localized user message when there is something to tell user.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// custom_photo
	//-------------------------------------------------------------------------------------------------------
	function custom_photo_op()
	{
		global $_profile, $_lang_code;
		$user_id = get_required_param('user_id');
		$event_id = get_optional_param('event_id', 0);
		$club_id = get_optional_param('club_id', 0);
		$tournament_id = get_optional_param('tournament_id', 0);
		$upload = isset($_FILES['picture']);
		
		if ($event_id > 0)
		{
			$query = new DbQuery('SELECT e.club_id, e.tournament_id, eu.flags FROM event_users eu JOIN events e ON e.id = eu.event_id WHERE eu.user_id = ? AND eu.event_id = ?', $user_id, $event_id);
			if ($row = $query->next())
			{
				list($club_id, $tour_id, $flags) = $row;
			}
			else
			{
				list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
				list($event_name) = Db::record(get_label('event'), 'SELECT name FROM events WHERE id = ?', $event_id);
				throw new Exc(get_label('[0] is not registered for [1]', $user_name, $event_name));
			}
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);

			if ($upload)
			{
				upload_logo('picture', '../../' . USER_PICS_DIR, $user_id, TNAIL_OPTION_FIT, 'e' . $event_id);
			
				$icon_version = (($flags & USER_EVENT_ICON_MASK) >> USER_EVENT_ICON_MASK_OFFSET) + 1;
				if ($icon_version > USER_EVENT_ICON_MAX_VERSION)
				{
					$icon_version = 1;
				}
				$flags = ($flags & ~USER_EVENT_ICON_MASK) + ($icon_version << USER_EVENT_ICON_MASK_OFFSET);
			}
			else
			{
				$filename = $user_id . '-e' . $event_id . '.png';
				delete_file('../../' . USER_PICS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . TNAILS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . ICONS_DIR . $filename);
				$flags = ($flags & ~USER_EVENT_ICON_MASK);
			}
			
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE event_users SET flags = ? WHERE user_id = ? AND event_id = ?', $flags, $user_id, $event_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->event_id = $event_id;
				db_log(LOG_OBJECT_USER, $upload ? 'event picture uploaded' : 'event picture reset', $log_details, $user_id);
			}
			Db::commit();
		}
		else if ($tournament_id > 0)
		{
			$query = new DbQuery('SELECT e.club_id, eu.flags FROM tournament_users eu JOIN tournaments e ON e.id = eu.tournament_id WHERE eu.user_id = ? AND eu.tournament_id = ?', $user_id, $tournament_id);
			if ($row = $query->next())
			{
				list($club_id, $flags) = $row;
			}
			else
			{
				list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
				list($tournament_name) = Db::record(get_label('tournament'), 'SELECT name FROM tournaments WHERE id = ?', $tournament_id);
				throw new Exc(get_label('[0] is not registered for [1]', $user_name, $tournament_name));
			}
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);

			if ($upload)
			{
				upload_logo('picture', '../../' . USER_PICS_DIR, $user_id, TNAIL_OPTION_FIT, 't' . $tournament_id);
				
				$icon_version = (($flags & USER_TOURNAMENT_ICON_MASK) >> USER_TOURNAMENT_ICON_MASK_OFFSET) + 1;
				if ($icon_version > USER_TOURNAMENT_ICON_MAX_VERSION)
				{
					$icon_version = 1;
				}
				$flags = ($flags & ~USER_TOURNAMENT_ICON_MASK) + ($icon_version << USER_TOURNAMENT_ICON_MASK_OFFSET);
			}
			else
			{
				$filename = $user_id . '-t' . $tournament_id . '.png';
				delete_file('../../' . USER_PICS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . TNAILS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . ICONS_DIR . $filename);
				$flags = ($flags & ~USER_EVENT_ICON_MASK);
			}
			
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE tournament_users SET flags = ? WHERE user_id = ? AND tournament_id = ?', $flags, $user_id, $tournament_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->tournament_id = $tournament_id;
				db_log(LOG_OBJECT_USER, $upload ? 'tournament picture uploaded' : 'tournament picture reset', $log_details, $user_id);
			}
			Db::commit();
		}
		else if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			$query = new DbQuery('SELECT flags FROM club_users WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
			}
			else
			{
				list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
				list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
				throw new Exc(get_label('[0] is not a member of [1]', $user_name, $club_name));
			}

			if ($upload)
			{
				upload_logo('picture', '../../' . USER_PICS_DIR, $user_id, TNAIL_OPTION_FIT, 'c' . $club_id);
				
				$icon_version = (($flags & USER_CLUB_ICON_MASK) >> USER_CLUB_ICON_MASK_OFFSET) + 1;
				if ($icon_version > USER_CLUB_ICON_MAX_VERSION)
				{
					$icon_version = 1;
				}
				$flags = ($flags & ~USER_CLUB_ICON_MASK) + ($icon_version << USER_CLUB_ICON_MASK_OFFSET);
			}
			else
			{
				$filename = $user_id . '-c' . $club_id . '.png';
				delete_file('../../' . USER_PICS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . TNAILS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . ICONS_DIR . $filename);
				$flags = ($flags & ~USER_CLUB_ICON_MASK);
			}
			
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE club_users SET flags = ? WHERE user_id = ? AND club_id = ?', $flags, $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->club_id = $club_id;
				db_log(LOG_OBJECT_USER, $upload ? 'club picture uploaded' : 'club picture reset', $log_details, $user_id);
			}
			Db::commit();		
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			list ($flags) = Db::record(get_label('user'), 'SELECT flags FROM users WHERE user_id = ?', $user_id);
			
			if ($upload)
			{
				upload_logo('picture', '../../' . USER_PICS_DIR, $user_id, TNAIL_OPTION_FIT);
				
				$icon_version = (($flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET) + 1;
				if ($icon_version > USER_ICON_MAX_VERSION)
				{
					$icon_version = 1;
				}
				$flags = ($flags & ~USER_ICON_MASK) + ($icon_version << USER_ICON_MASK_OFFSET);
			}
			else
			{
				$filename = $user_id . '.png';
				delete_file('../../' . USER_PICS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . TNAILS_DIR . $filename);
				delete_file('../../' . USER_PICS_DIR . ICONS_DIR . $filename);
				$flags = ($flags & ~USER_ICON_MASK);
			}
			
			Db::begin();
			Db::exec(get_label('user'), 'UPDATE users SET flags = ? WHERE id = ?', $flags, $user_id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, $upload ? 'picture uploaded' : 'picture reset', $log_details, $user_id);
			}
			Db::commit();		
		}
	}
	
	function custom_photo_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Upload custom user photo for a specific activity - event, tournament, or club.');
		$help->request_param('user_id', 'User id');
		$help->request_param('event_id', 'Event id. User must be registered for this event.', 'root user picture is uploaded.');
		$help->request_param('tournament_id', 'Tournament id. User must be registered for this tournament.', 'root user picture is uploaded.');
		$help->request_param('club_id', 'Club id. User must be a member of this club.', 'root user picture is uploaded.');
		$help->request_param('picture', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', 'the picture is reset to a default value');
		return $help;
	}
}
$page = new ApiPage();
$page->run('User Operations', CURRENT_VERSION);

?>