<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

function cancel_game($game_id, $club_id, $league_id)
{
	Db::exec(get_label('game'), 'UPDATE games SET is_canceled = TRUE WHERE id = ?', $game_id);
	if (Db::affected_rows() > 0)
	{
		list($end_time, $is_rating) = Db::record(get_label('game'), 'SELECT end_time, is_rating FROM games WHERE id = ?', $game_id);
		
		Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated - 1 WHERE id = (SELECT moderator_id FROM games WHERE id = ?)', $game_id);
		Db::exec(get_label('user'), 'UPDATE players p JOIN users u ON u.id = p.user_id SET u.games = u.games - 1, u.games_won = u.games_won - p.won, u.rating = u.rating - p.rating_earned WHERE p.game_id = ?', $game_id);
		Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $game_id);
		Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $game_id);
		Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $game_id);
		Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $game_id);
		if ($is_rating)
		{
			$prev_game_id = NULL;
			$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
			if ($row = $query->next())
			{
				list($prev_game_id) = $row;
			}
			Game::rebuild_ratings($prev_game_id, $end_time);
		}
		
		$log_details = new stdClass();
		$log_details->canceled = true;
		db_log(LOG_OBJECT_GAME, 'changed', $log_details, $game_id, $club_id, $league_id);
	}
}

function uncancel_game($game_id, $club_id, $league_id)
{
	Db::exec(get_label('game'), 'UPDATE games SET is_canceled = FALSE WHERE id = ?', $game_id);
	if (Db::affected_rows() > 0)
	{
		Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated + 1 WHERE id = (SELECT moderator_id FROM games WHERE id = ?)', $game_id);
		$game = new Game($game_id);
		$game->update();
		$log_details = new stdClass();
		$log_details->canceled = false;
		db_log(LOG_OBJECT_GAME, 'changed', $log_details, $game_id, $club_id, $league_id);
	}
}

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		
		$message = get_required_param('message');
		if (trim($message) == '')
		{
			throw new Exc(get_label('The reason can not be empty'));
		}
		
		$accept = max(min((int)get_optional_param('accept', 0), 1), -1);
		
		Db::begin();
		$parent_id = (int)get_optional_param('parent_id', 0);
		if ($parent_id <= 0)
		{
			$parent_id = NULL;
			$game_id = (int)get_required_param('game_id');
			list ($club_id, $event_id, $tournament_id, $owner_id, $league_id) = Db::record(get_label('game'), 'SELECT g.club_id, g.event_id, g.tournament_id, g.user_id, t.league_id FROM games g JOIN events e ON e.id = g.event_id LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE g.id = ?', $game_id);
		}
		else
		{
			list ($game_id, $club_id, $event_id, $tournament_id, $owner_id, $league_id) = Db::record(get_label('game'), 'SELECT g.id, g.club_id, g.event_id, g.tournament_id, g.user_id, t.league_id FROM objections o JOIN games g ON g.id = o.game_id JOIN events e ON e.id = g.event_id LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE o.id = ?', $parent_id);
		}
		
		$user_id = $_profile->user_id;
		if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id))
		{
			$user_id = (int)get_optional_param('user_id', $user_id);
		}
		
		Db::exec(get_label('objection'), 'INSERT INTO objections (timestamp, user_id, game_id, message, objection_id, accept) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?, ?)', $user_id, $game_id, $message, $parent_id, $accept);
		list ($objection_id) = Db::record(get_label('objection'), 'SELECT LAST_INSERT_ID()');
		$log_details = new stdClass();
		$log_details->game_id = $game_id;
		$log_details->user_id = $user_id;
		$log_details->message = $message;
		db_log(LOG_OBJECT_OBJECTION, 'created', $log_details, $objection_id, $club_id, $league_id);
		
		if ($accept > 0)
		{
			cancel_game($game_id, $club_id, $league_id);
		}
		Db::commit();
		$this->response['objection_id'] = $objection_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Create game result objection.');
		$help->request_param('game_id', 'Game id.', 'parent_id must be set.');
		$help->request_param('parent_id', 'Objection id that this objection replyes to.', 'game_id is used to create top level objection.');
		$help->request_param('user_id', 'User id of the user who is objecting. Only the owner of the game and club managers are allowed to set it. For others this parameter is ignored.', 'current user id is used.');
		$help->request_param('message', 'Objection explanation.');
		$help->response_param('objection_id', 'Newly created objection id.');
		$help->request_param('accept', '1 to accept objection; -1 to decline; 0 to pospone the decision.', '0 is used.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$objection_id = (int)get_required_param('objection_id');
		
		Db::begin();
		list ($game_id, $owner_id, $club_id, $event_id, $tournament_id, $league_id, $old_message, $old_user_id, $parent_id, $old_accept) = 
			Db::record(
				get_label('objection'), 
				'SELECT g.id, g.user_id, g.club_id, g.event_id, g.tournament_id, t.league_id, o.message, o.user_id, o.objection_id, o.accept FROM objections o' .
				' JOIN games g ON g.id = o.game_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' WHERE o.id = ?', $objection_id);
				
		$accept = max(min((int)get_optional_param('accept', $old_accept), 1), -1);
				
		if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id))
		{
			$user_id = get_optional_param('user_id', $old_user_id);
		}
		else if ($old_user_id == $_profile->user_id)
		{
			$user_id = $old_user_id;
		}
		else
		{
			no_permission();
		}
		$message = get_optional_param('message', $old_message);
		
		Db::exec(get_label('objection'), 'UPDATE objections SET message = ?, user_id = ?, accept = ? WHERE id = ?', $message, $user_id, $accept, $objection_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($message != $old_message)
			{
				$log_details->message = $message;
			}
			if ($user_id != $old_user_id)
			{
				$log_details->user_id = $user_id;
			}
			if ($accept != $old_accept)
			{
				$log_details->accept = $accept;
			}
			db_log(LOG_OBJECT_OBJECTION, 'changed', $log_details, $objection_id, $club_id, $league_id);
		}
		
		if ($accept != $old_accept)
		{
			if ($accept > 0)
			{
				cancel_game($game_id, $club_id, $league_id);
			}
			else
			{
				list ($accept_count) = Db::record(get_label('objection'), 'SELECT count(*) FROM objections o WHERE game_id = ? AND accept = 1', $game_id);
				if ($accept_count == 0)
				{
					uncancel_game($game_id, $club_id, $league_id);
				}
			}
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change game result objection.');
		$help->request_param('user_id', 'User id of the user who is objecting. Only the owner of the game and club/event/tournament managers are allowed to set it. For others this parameter is ignored.', 'remains the same.');
		$help->request_param('message', 'Objection explanation.', 'remains the same.');
		$help->request_param('accept', '1 to accept objection; -1 to decline; 0 to pospone the decision.', 'remains the same.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$objection_id = (int)get_required_param('objection_id');
		
		Db::begin();
		list ($game_id, $club_id, $event_id, $tournament_id, $owner_id, $league_id, $accept) = 
			Db::record(get_label('objection'), 
				'SELECT g.id, g.club_id, g.event_id, g.tournament_id, g.user_id, t.league_id, o.accept FROM objections o' .
				' JOIN games g ON g.id = o.game_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' WHERE o.id = ?', $objection_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE objection_id = ?', $objection_id);
		Db::exec(get_label('objection'), 'DELETE FROM objections WHERE id = ?', $objection_id);
		db_log(LOG_OBJECT_OBJECTION, 'deleted', NULL, $objection_id, $club_id);
		
		if ($accept)
		{
			list ($accept_count) = Db::record(get_label('objection'), 'SELECT count(*) FROM objections o WHERE game_id = ? AND accept = 1', $game_id);
			if ($accept_count == 0)
			{
				uncancel_game($game_id, $club_id, $league_id);
			}
		}
		
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Delete objection.');
		$help->request_param('objection_id', 'Objection id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Objection Operations', CURRENT_VERSION);

?>