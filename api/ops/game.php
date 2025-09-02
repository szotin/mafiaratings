<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/video.php';
require_once '../../include/game.php';
require_once '../../include/tournament.php';
require_once '../../include/event.php';
require_once '../../include/user.php';
require_once '../../include/mwt_game.php';

define('CURRENT_VERSION', 4); // must match _version in js/src/game.js

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$json = get_required_param('json');
		if ($json == NULL)
		{
			throw new Exc(get_label('Invalid json format.'));
		}
		$json = check_json($json);
		
		$feature_flags = GAME_FEATURE_MASK_ALL;
		$game = new Game($json, $feature_flags);
		
		Db::begin();
		$this->response['rebuild_ratings'] = $game->create();
		Db::commit();
		
		if (isset($game->issues))
		{
			$text = get_label('The game contains the next issues:') . '<ul>';
			foreach ($game->issues as $issue)
			{
				$text .= '<li>' . $issue . '</li>';
			}
			$text .= '</ul>' . get_label('They are all fixed but the original version of the game is also saved. Please check Game Issues in the management menu.');
			$this->response['message'] = $text;
		}
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Create the game.');
		$param = $help->request_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		$param = $help->response_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$game_id = (int)get_required_param('game_id');
		$json = get_required_param('json');
		if ($json == NULL)
		{
			throw new Exc(get_label('Invalid json format.'));
		}
		$json = check_json($json);
		
		Db::begin();
		list($club_id, $user_id, $event_id, $tournament_id, $feature_flags) = Db::record(get_label('game'), 'SELECT club_id, user_id, event_id, tournament_id, feature_flags FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $user_id, $club_id, $event_id, $tournament_id);
		
		$game = new Game($json, $feature_flags);
		if (!isset($game->data->id))
		{
			$game->data->id = $game_id;
		}
		else if ($game->data->id != $game_id)
		{
			throw new Exc(get_label('Game id does not match the one in the game'));
		}	
		
		$this->response['rebuild_ratings'] = $game->update();
		Db::commit();
		
		if (isset($game->issues))
		{
			$text = get_label('The game contains the next issues:') . '<ul>';
			foreach ($game->issues as $issue)
			{
				$text .= '<li>' . $issue . '</li>';
			}
			$text .= '</ul>' . get_label('They are all fixed but the original version of the game is also saved. Please check Game Issues in the management menu.');
			$this->response['message'] = $text;
		}
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Change the game.');
		$help->request_param('game_id', 'Game id.');
		$param = $help->request_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		$param = $help->response_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$game_id = (int)get_required_param('game_id');
		
		Db::begin();
		list($club_id, $user_id, $event_id, $tournament_id, $end_time, $flags) = Db::record(get_label('game'), 'SELECT club_id, user_id, event_id, tournament_id, end_time, flags FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $user_id, $club_id, $event_id, $tournament_id);
		
		$prev_game_id = NULL;
		$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
		if ($row = $query->next())
		{
			list($prev_game_id) = $row;
		}
		
		if ($flags & GAME_FLAG_RATING)
		{
			Game::rebuild_ratings($prev_game_id, $end_time);
		}
		
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE game_id = ?', $prev_game_id, $game_id);
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET current_game_id = ? WHERE current_game_id = ?', $prev_game_id, $game_id);
		Db::exec(get_label('game'), 'UPDATE mwt_games SET game_id = NULL WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM dons WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM mafiosos WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM sheriffs WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM players WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM games WHERE id = ?', $game_id);
		
		db_log(LOG_OBJECT_GAME, 'deleted', NULL, $game_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Delete game.');
		$help->request_param('game_id', 'Game id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// mwt_create
	//-------------------------------------------------------------------------------------------------------
	function mwt_create_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		
		$json_str = get_required_param('json');
		$json = json_decode($json_str);
		if ($json == NULL)
		{
			throw new Exc(get_label('Invalid json format.'));
		}
		$game_id = NULL;
		$throw_error = get_optional_param('throw_error', false);
		
		Db::begin();
		try
		{
			$game = convert_mwt_game($json);
			
			if ($game->winner == 'maf')
			{
				$result_code = GAME_RESULT_MAFIA;
			}
			else if ($game->winner == 'civ')
			{
				$result_code = GAME_RESULT_TOWN;
			}
			else if ($game->winner == 'tie')
			{
				$result_code = GAME_RESULT_TIE;
			}
			else
			{
				throw new Exc(get_label('Unknown [0]', get_label('result')));
			}
			Db::exec(get_label('game'),
				'INSERT INTO games (club_id, event_id, tournament_id, moderator_id, user_id, language, start_time, end_time, result, rules) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$game->clubId, $game->eventId, $game->tournamentId, $game->moderator->id, $_profile->user_id, LANG_RUSSIAN,
				$game->startTime, $game->endTime, $result_code, $game->rules);
			list ($game->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
			$game->id = (int)$game->id;
			
			$game = new Game($game, GAME_FEATURE_MASK_MWT);
			$game->update();
			
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $game->data->tournamentId);
			Db::exec(get_label('round'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $game->data->eventId);
			Db::exec(get_label('game'), 'INSERT INTO mwt_games (user_id, time, json, game_id) VALUES (?, UNIX_TIMESTAMP(), ?, ?)', $_profile->user_id, $json_str, $game->data->id);
			Db::commit();
		}
		catch (Exception $e)
		{
			Db::rollback();
			Db::exec(get_label('game'), 'INSERT INTO mwt_games (user_id, time, json) VALUES (?, UNIX_TIMESTAMP(), ?)', $_profile->user_id, $json_str);
			Exc::log($e);
			if ($throw_error)
			{
				throw $e;
			}
		}
	}
	
	function mwt_create_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Add the game from MWT site.');
		$help->request_param('json', 'Game description in json format specific for mwt site.');
		$help->request_param('throw_error', '0 for not throwing errors; 1 for throwing.', '0');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$game_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO game_comments (time, user_id, comment, game_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $game_id, $lang);
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('game'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM games g' .
				' JOIN events e ON g.event_id = e.id' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE g.id = ?', $game_id);
		
		$query = new DbQuery(
			'(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM players p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE p.game_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM game_comments c'.
			' JOIN users u ON c.user_id = u.id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE c.game_id = ?)',
			$game_id, $game_id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
		
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
			
			date_default_timezone_set($event_timezone);
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'gid' => new Tag($game_id),
				'event_id' => new Tag($event_id),
				'event_name' => new Tag($event_name),
				'event_date' => new Tag(format_date($event_start_time, $event_timezone, false, $user_lang)),
				'event_time' => new Tag(date('H:i', $event_start_time)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'user_name' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_game.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, $user_lang, EMAIL_OBJ_GAME, $game_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Comment game.');
		$help->request_param('id', 'Game id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_issue
	//-------------------------------------------------------------------------------------------------------
	function delete_issue_op()
	{
		$game_id = (int)get_required_param('game_id');
		$feature_flags = (int)get_optional_param('features', -1);
		check_permissions(PERMISSION_ADMIN);
	
		Db::begin();
		if ($feature_flags < 0)
		{
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $game_id);
		}
		else
		{
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ? AND feature_flags = ?', $game_id, $feature_flags);
		}
		Db::commit();
	}
	
	// function delete_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_ADMIN, 'Delete game.');
		// $help->request_param('game_id', 'Game id.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// set_bonus
	//-------------------------------------------------------------------------------------------------------
	function set_bonus_op()
	{
		global $_profile, $_lang;
		$player_num = (int)get_required_param('player_num');
		if ($player_num < 1 || $player_num > 10)
		{
			throw new Exc(get_label('Invalid [0]', get_label('player number')));
		}
		
		$game_id = (int)get_optional_param('game_id', 0);
		if ($game_id <= 0)
		{
			$event_id = (int)get_required_param('event_id');
			$table_num = (int)get_required_param('table_num');
			$game_num = (int)get_required_param('game_num');
			
			$query = new DbQuery('SELECT id FROM games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			if ($row = $query->next())
			{
				list ($game_id) = $row;
				$game_id = (int)$game_id;
			}
		}
		
		$points = (float)get_optional_param('points', 0);
		$best_player = (int)get_optional_param('best_player', 0);
		$best_move = (int)get_optional_param('best_move', 0);
		$worst_move = (int)get_optional_param('worst_move', 0);
		
		Db::begin();
		if ($game_id > 0)
		{
			list($json, $feature_flags, $club_id, $user_id, $event_id, $tournament_id) = Db::record(get_label('game'), 'SELECT json, feature_flags, club_id, user_id, event_id, tournament_id FROM games WHERE id = ?', $game_id);
		}
		else
		{
			list($json, $club_id, $user_id, $event_id, $tournament_id) = Db::record(get_label('game'), 'SELECT g.game, e.club_id, g.user_id, g.event_id, e.tournament_id FROM current_games g JOIN events e ON e.id = g.event_id WHERE g.event_id = ? AND g.table_num = ? AND g.game_num = ?', $event_id, $table_num, $game_num);
			$feature_flags = GAME_FEATURE_MASK_ALL;
		}
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $user_id, $club_id, $event_id, $tournament_id);
		
		$game = new Game($json, $feature_flags);
		if ($game_id > 0 && $game->data->id != $game_id)
		{
			throw new Exc(get_label('Game id does not match the one in the game'));
		}	
		
		$bonus = 0;
		if ($points != 0)
		{
			$bonus = $points;
		}
		if ($best_player)
		{
			if (is_array($bonus))
			{
				$bonus[] = 'bestPlayer';
			}
			else if ($bonus != 0)
			{
				$bonus = array($bonus, 'bestPlayer');
			}
			else
			{
				$bonus = 'bestPlayer';
			}
		}
		if ($best_move)
		{
			if (is_array($bonus))
			{
				$bonus[] = 'bestMove';
			}
			else if ($bonus != 0)
			{
				$bonus = array($bonus, 'bestMove');
			}
			else
			{
				$bonus = 'bestMove';
			}
		}
		if ($worst_move)
		{
			if (is_array($bonus))
			{
				$bonus[] = 'worstMove';
			}
			else if ($bonus != 0)
			{
				$bonus = array($bonus, 'worstMove');
			}
			else
			{
				$bonus = 'worstMove';
			}
		}
		
		$player = $game->data->players[$player_num - 1];
		if ($bonus === 0)
		{
			unset($player->comment);
			unset($player->bonus);
		}
		else 
		{
			$player->comment = get_required_param('comment');
			if (empty($player->comment))
			{
				throw new Exc('Please enter comment.');
			}
			$player->bonus = $bonus;
		}
		
		if ($game_id > 0)
		{
			$game->update();
		}
		else
		{
			Db::exec(get_label('game'), 'UPDATE current_games SET game = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', json_encode($game->data), $event_id, $table_num, $game_num);
		}
		Db::commit();
	}
	
	function set_bonus_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Get currently playing game.');
		$help->request_param('game_id', 'Game id.');
		$help->request_param('player_num', 'Number of the player in the game from 1 to 10.');
		$help->request_param('points', 'Bonus points.', '0 is used');
		$help->request_param('best_player', 'Any non-zero value to award "best player" title to the player.', '0 is used');
		$help->request_param('best_move', 'Any non-zero value to award "best move" title to the player.', '0 is used');
		$help->request_param('worst_move', 'Any non-zero value to set "worst move" title to the player.', '0 is used');
		$help->request_param('comment', 'Comments about the bonus.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// own_current
	//-------------------------------------------------------------------------------------------------------
	function own_current_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$table_num = (int)get_required_param('table_num');
		$game_num = (int)get_required_param('game_num');
		
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
	
		Db::exec(get_label('game'), 'UPDATE current_games SET user_id = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', $_profile->user_id, $event_id, $table_num, $game_num);
	}
	
	function own_current_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Take over the currently playing game from the old owner.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table_num', 'Table number in the event. Starting from 1.');
		$help->request_param('game_num', 'Game number. Starting from 1.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// cancel_current
	//-------------------------------------------------------------------------------------------------------
	function cancel_current_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$table_num = (int)get_required_param('table_num');
		$game_num = (int)get_required_param('game_num');
		
		if ($event_id > 0)
		{
			list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
		
			Db::begin();
			$query = new DbQuery('SELECT user_id FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			if ($row = $query->next())
			{
				list ($user_id) = $row;
				if ($user_id != $_profile->user_id)
				{
					list($user_name) = Db::record(get_label('user'), 'SELECT n.name FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
					throw new Exc(get_label('The game is moderated by [0].', $user_name));
				}
			}
			Db::exec(get_label('game'), 'DELETE FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			Db::commit();
		}
		else
		{
			unset($_SESSION['demogame']);
		}
	}
	
	function cancel_current_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Cancel current game and delete it from the database. The game will start over again.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table_num', 'Table number in the event. Starting from 1.');
		$help->request_param('game_num', 'Game number. Starting from 1.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// get_current
	//-------------------------------------------------------------------------------------------------------
	function get_current_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$table_num = (int)get_required_param('table_num');
		$game_num = (int)get_required_param('game_num');
		$lod = (int)get_optional_param('lod', 0);
		
		$settings_flags = 0;
		$feature_flags = GAME_FEATURE_MASK_ALL;
		$prompt_sound = NULL;
		$end_sound = NULL;
		$obs_scenes = NULL;
		$query = new DbQuery('SELECT prompt_sound_id, end_sound_id, flags, feature_flags, obs_scenes FROM game_settings WHERE user_id = ?', $_profile->user_id);
		if ($row = $query->next())
		{
			list($prompt_sound, $end_sound, $settings_flags, $feature_flags, $obs_scenes) = $row;
		}
		
		if ($event_id > 0)
		{
			list($club_id, $tournament_id, $rules, $misc, $languages, $event_flags) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, rules, misc, languages, flags FROM events WHERE id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
			
			$langs = array();
			$lang = LANG_NO;
			while (($lang = get_next_lang($lang, $languages)) != LANG_NO)
			{
				$l = new stdClass();
				$l->code = get_lang_code($lang);
				$l->name = get_lang_str($lang);
				$langs[] = $l;
			}
			if (count($langs) == 0)
			{
				throw new Exc(get_label('Please check the event languages. None of them is specified.'));
			}
		
			$query = new DbQuery('SELECT game, log, user_id FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			if ($row = $query->next())
			{
				list ($game, $log, $user_id) = $row;
				if ($user_id != $_profile->user_id)
				{
					list($user_name) = Db::record(get_label('user'), 'SELECT n.name FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
					throw new Exc(get_label('The game is moderated by [0].', $user_name));
				}
				$game = json_decode($game);
				Game::convert_to_current_version($game);
				if ($lod > 0)
				{
					if (is_null($log))
					{
						$log = array();
					}
					else
					{
						$log = json_decode($log);
						if (!is_array($log))
						{
							$log = array();
						}
					}
				}
			}
			else
			{
				$query = new DbQuery('SELECT id FROM games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
				if ($row = $query->next())
				{
					list ($gid) = $row;
					throw new Exc(get_label('Game [0] table [1] has already been played (#[2]). Remove the existing game if you want to replay it.', $game_num, $table_num, $gid));
				}
				
				$seating = NULL;
				$misc = json_decode($misc);
				if (isset($misc->seating) && isset($misc->seating[$table_num - 1]) && isset($misc->seating[$table_num - 1][$game_num - 1]))
				{
					$seating = $misc->seating[$table_num - 1][$game_num - 1];
				}
				
				$game = new stdClass();
				$game->version = GAME_CURRENT_VERSION;
				$game->clubId = (int)$club_id;
				$game->eventId = $event_id;
				$game->tableNum = $table_num;
				$game->gameNum = $game_num;
				$game->language = $langs[0]->code;
				$game->rules = $rules;
				$game->features = Game::feature_flags_to_leters($feature_flags | GAME_NON_CONFIGURABLE_FEATURES);
				if (!is_null($tournament_id))
				{
					$game->tournamentId = (int)$tournament_id;
				}
				$game->moderator = new stdClass();
				if (!is_null($seating) && count($seating) > 10)
				{
					$game->moderator->id = $seating[10];
				}
				else
				{
					$game->moderator->id = 0;
				}
				$game->streaming = (($event_flags & EVENT_FLAG_STREAMING) != 0);

				
				$game->players = array(
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass(),
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass());
					
				for ($i = 0; $i < 10; ++$i)
				{
					$player = $game->players[$i];
					$player->id = 0;
					$player->name = '';
					if (isset($seating[$i]))
					{
						$query = new DbQuery('SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $seating[$i]);
						if ($row = $query->next())
						{
							$player->id = $seating[$i];
							list ($player->name) = $row;
						}
					}
				}
				$log = array();
			}
			
			$regs = get_event_reg_array($event_id);
		}
		else if (isset($_SESSION['demogame']))
		{
			$data = $_SESSION['demogame'];
			$game = $data->game;
			$regs = $data->regs;
			$langs = $data->langs;
			$log = $data->log;
		}
		else
		{
			$game = new stdClass();
			$regs = array();
			$langs = array();
			$log = array();
			
			$lang = LANG_NO;
			while (($lang = get_next_lang($lang, LANG_ALL)) != LANG_NO)
			{
				$l = new stdClass();
				$l->code = get_lang_code($lang);
				$l->name = get_lang_str($lang);
				$langs[] = $l;
			}
			
			$game->version = GAME_CURRENT_VERSION;
			$game->eventId = 0;
			if (is_null($_profile->user_club_id))
			{
				$game->clubId = (int)$_profile->user_club_id;
			}
			else if (count($_profile->clubs) > 0)
			{
				reset($_profile->clubs);
				$game->clubId = current($_profile->clubs)->id;
			}
			else
			{
				list($game->clubId) = Db::record(get_label('club'), 'SELECT MIN(id) FROM clubs');
			}
			$game->tableNum = 0;
			$game->gameNum = 0;
			$game->language = get_lang_code($_lang);
			$game->rules = default_rules_code();
			$game->features = Game::feature_flags_to_leters($feature_flags);
			$game->moderator = new stdClass();
			$game->moderator->id = 0;
			$game->streaming = false;
			
			$game->players = array(
				new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass(),
				new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass());
				
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $game->players[$i];
				$player->id = 0;
				$player->name = '';
			}
			
			$data = new stdClass();
			$data->game = $game;
			$data->regs = $regs;
			$data->langs = $langs;
			$data->log = $log;
			$_SESSION['demogame'] = $data;
		}
		
		if (is_null($prompt_sound) || is_null($end_sound))
		{
			list ($club_prompt_sound, $club_end_sound) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $game->clubId);
			if (is_null($prompt_sound))
			{
				$prompt_sound = is_null($club_prompt_sound) ? GAME_DEFAULT_PROMPT_SOUND : $club_prompt_sound;
			}
			if (is_null($end_sound))
			{
				$end_sound = is_null($club_end_sound) ? GAME_DEFAULT_END_SOUND : $club_end_sound;
			}
		}
		
		if (!is_null($obs_scenes))
		{
			$obs_scenes = json_decode($obs_scenes);
		}
		
		$this->response['game'] = $game;
		if ($lod > 0)
		{
			$this->response['log'] = $log;
		}
		$this->response['regs'] = $regs;
		$this->response['langs'] = $langs;
		$this->response['prompt_sound'] = 'sounds/' . $prompt_sound . '.mp3';
		$this->response['end_sound'] = 'sounds/' . $end_sound . '.mp3';
		$this->response['flags'] = $settings_flags;
		$this->response['obs_scenes'] = $obs_scenes;
	}
	
	function get_current_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Get currently playing game.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table_num', 'Table number in the event. Starting from 1.');
		$help->request_param('game_num', 'Game number. Starting from 1.');
		$help->response_param('game', 'Game description in json format.');
		$help->response_param('log', 'Array of the previous states of the game. It can be used to navigate through the game.', NULL, 1);
		$param = $help->response_param('regs', 'Array containing players registered for the event.');
		$param->response_param('id', 'User id');
		$param->response_param('name', 'Player name');
		$param->response_param('flags', 'Permission bit-flags: 1 - player; 2 - referee; 4 - manager.');
		$help->response_param('prompt_sound', 'URL of the sound to play in 10 sec before the speach end.');
		$help->response_param('end_sound', 'URL of the sound to play at the speach end.');
		$help->response_param('flags', 'Game settings flags: 1 - not used; 2 - start timer on speaches automatically; 4 - can change roles during arrangement; 8 - no timer blinking.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// set_current
	//-------------------------------------------------------------------------------------------------------
	function update_game_log(&$log, &$log_tail, $log_tail_index)
	{
		if ($log_tail_index < 0)
		{
			$log_tail_index = count($log);
		}
		
		$log_changed = false;
		$new_log_length = $log_tail_index + count($log_tail);
		for ($i = count($log); $i < $new_log_length; ++$i)
		{
			$log[] = NULL;
			$log_changed = true;
		}
		for ($i = $log_tail_index; $i < $new_log_length; ++$i)
		{
			$log[$i] = $log_tail[$i - $log_tail_index];
			$log_changed = true;
		}
		if (count($log) > $new_log_length)
		{
			$log = array_slice($log, 0, $new_log_length);
			$log_changed = true;
		}
		return $log_changed;
	}
	
	function set_current_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$table_num = (int)get_required_param('table_num');
		$game_num = (int)get_required_param('game_num');
		$game = get_required_param('game');
		$log_tail_index = get_optional_param('logIndex', -1);
		$log_tail = get_optional_param('log', NULL);
		if (!is_null($log_tail))
		{
			$log_tail = json_decode($log_tail);
			if (!is_array($log_tail))
			{
				throw new Exc('Log must be an array.');
			}
		}
		
		if ($event_id > 0)
		{
			list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
			
			Db::begin();
			$query = new DbQuery('SELECT id FROM games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			if ($row = $query->next())
			{
				list ($gid) = $row;
				throw new Exc(get_label('Game [0] table [1] has already been played (#[2]). Remove the existing game if you want to replay it.', $game_num, $table_num, $gid));
			}
			
			$query = new DbQuery('SELECT user_id, log FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
			if ($row = $query->next())
			{
				list ($user_id, $log) = $row;
				$log_changed = false;
				if (!is_null($log_tail))
				{
					if (is_null($log))
					{
						$log = array();
						$log_changed = true;
					}
					else
					{
						$log = json_decode($log);
						if (!is_array($log))
						{
							$log = array();
						}
					}
					$log_changed = $this->update_game_log($log, $log_tail, $log_tail_index);
				}
				
				if ($user_id != $_profile->user_id)
				{
					list($user_name) = Db::record(get_label('user'), 'SELECT n.name FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
					throw new Exc(get_label('The game is moderated by [0].', $user_name));
				}
				if ($log_changed)
				{
					$log = json_encode($log);
					Db::exec(get_label('game'), 'UPDATE current_games SET game = ?, log = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', $game, $log, $event_id, $table_num, $game_num);
				}
				else
				{
					Db::exec(get_label('game'), 'UPDATE current_games SET game = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', $game, $event_id, $table_num, $game_num);
				}
			}
			else if (is_null($log_tail))
			{
				Db::exec(get_label('game'), 'INSERT INTO current_games (event_id, table_num, game_num, user_id, game) VALUES (?, ?, ?, ?, ?)', $event_id, $table_num, $game_num, $_profile->user_id, $game);
			}
			else
			{
				$log_tail = json_encode($log_tail);
				Db::exec(get_label('game'), 'INSERT INTO current_games (event_id, table_num, game_num, user_id, game, log) VALUES (?, ?, ?, ?, ?, ?)', $event_id, $table_num, $game_num, $_profile->user_id, $game, $log_tail);
			}
			Db::commit();
		}
		else
		{
			// Demo game
			if (!isset($_SESSION['demogame']))
			{
				throw new Exc(get_label('Unknown [0]', get_label('event')));
			}
			
			$data = $_SESSION['demogame'];
			$data->game = json_decode($game);
			$this->update_game_log($data->log, $log_tail, $log_tail_index);
			$_SESSION['demogame'] = $data;
		}
	}
	
	function set_current_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Save currently played game state.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table_num', 'Table number in the event. Starting from 1.');
		$help->request_param('game_num', 'Game number. Starting from 1.');
		$help->request_param('game', 'Json string defining current game state.');
		$help->request_param('log', 'Addition to the array of previous states of the game.', 'log is not modified');
		$help->request_param('logIndex', 'At which index should the provided log be added to the prev states of the game. The records after the logIndex+log.length are cut.<p>Examples:<br>recorded log is []. We send logIndex:1,log:[1,2]. Result is [null,1,2].<br>We send logIndex:2,log[3,4,5]. Result is: [null,1,3,4,5]<br>We send logIndex:3,log:[]. Result is: [null,1,3,4].<br>We send logIndex:0,log:[1,2]. Result is: [1,2]', 'end of the log is used');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// report_bug
	//-------------------------------------------------------------------------------------------------------
	function report_bug_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$table_num = (int)get_required_param('table_num');
		$game_num = (int)get_required_param('game_num');
		$comment = get_required_param('comment');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
		
		list ($count) = Db::record(get_label('game'), 'SELECT COUNT(*) FROM bug_reports WHERE event_id = ? AND table_num = ? AND game_num = ?', $event_id, $table_num, $game_num);
		if ($count < 3) // allow only 3 bug reports per game
		{
			Db::exec(get_label('game'), 'INSERT INTO bug_reports (event_id, table_num, game_num, user_id, game, log, comment) SELECT event_id, table_num, game_num, user_id, game, log, ? FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $comment, $event_id, $table_num, $game_num);
		}
		Db::commit();
		
		$query = new DbQuery('SELECT id, email FROM users WHERE id = ' . MAIN_ADMIN_ID);
		while ($row = $query->next())
		{
			list($admin_id, $admin_email) = $row;
			// We are not checking if admin is unsubscribed, because this is a dedicated admin. This email should always go.
			$body = '<p>Hi, Admin!</p><p>' . $_profile->user_name . ' reported a bug.</p><p><a href="' . get_server_url() . '/game_bugs.php">Please check</a>.</p>';			
			$text_body = "Hi, Admin!\r\n\r\n" . $_profile->user_name . " reported a bug.\r\nPlease check: " . get_server_url() . '/game_bugs.php';
			send_email($admin_email, $body, $text_body, 'Bug report');
		}
	}
	
	function report_bug_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Report a bug on the currently playing game.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table_num', 'Table number in the event. Starting from 1.');
		$help->request_param('game_num', 'Game number. Starting from 1.');
		$help->request_param('comment', 'Explanation of the bug.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// bug_resolved
	//-------------------------------------------------------------------------------------------------------
	function bug_resolved_op()
	{
		global $_profile, $_lang;
		
		$bug_id = (int)get_required_param('bug_id');
		check_permissions(PERMISSION_ADMIN);
		Db::exec(get_label('game'), 'DELETE FROM bug_reports WHERE id = ?', $bug_id);
	}
	
	function bug_resolved_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Delete the bug because it is resolved.');
		$help->request_param('bug_id', 'Bug id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// settings
	//-------------------------------------------------------------------------------------------------------
	function settings_op()
	{
		global $_profile;
		
		$club_id = (int)get_optional_param('club_id', 0);
		$user_id = (int)get_optional_param('user_id', $_profile->user_id);
		
		check_permissions(PERMISSION_OWNER, $user_id);
		
		Db::begin();
		if ($club_id > 0)
		{
			list ($old_prompt_sound_id, $old_end_sound_id) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $club_id);
			
			$prompt_sound_id = (int)get_optional_param('prompt_sound_id', $old_prompt_sound_id);
			if ($prompt_sound_id <= 0)
			{
				$prompt_sound_id = NULL;
			}
			$end_sound_id = (int)get_optional_param('end_sound_id', $old_end_sound_id);
			if ($end_sound_id <= 0)
			{
				$end_sound_id = NULL;
			}
			
			if ($prompt_sound_id != $old_prompt_sound_id || $end_sound_id != $old_end_sound_id)
			{
				Db::exec(get_label('club'), 'UPDATE clubs SET prompt_sound_id = ?, end_sound_id = ? WHERE id = ?', $prompt_sound_id, $end_sound_id, $club_id);
				$log_details = new stdClass();
				if ($prompt_sound_id != $old_prompt_sound_id)
				{
					$log_details->prompt_sound_id = $prompt_sound_id;
				}
				if ($end_sound_id != $old_end_sound_id)
				{
					$log_details->end_sound_id = $end_sound_id;
				}
				db_log(LOG_OBJECT_CLUB, 'changed', $log_details, $club_id, $club_id);
			}
		}
		else
		{
			$exists = false;
			$old_flags = 0;
			$old_prompt_sound_id = NULL;
			$old_end_sound_id = NULL;
			
			$query = new DbQuery('SELECT flags, prompt_sound_id, end_sound_id FROM game_settings WHERE user_id = ?', $user_id);
			if ($row = $query->next())
			{
				list ($old_flags, $old_prompt_sound_id, $old_end_sound_id) = $row;
				$exists = true;
			}
			
			$obs_scenes = get_optional_param('obs_scenes', NULL);
			if (!is_null($obs_scenes))
			{
				// Make sure json is correct
				$obs_scenes = json_decode($obs_scenes);
				$correct = isset($obs_scenes->url) && isset($obs_scenes->password) && isset($obs_scenes->scenes) && is_array($obs_scenes->scenes);
				if ($correct)
				{
					for($i = 0; $i < count($obs_scenes->scenes); ++$i)
					{
						$s = $obs_scenes->scenes[$i];
						if (!isset($s->scene) || !isset($s->event))
						{
							$correct = false;
							break;
						}
					}
				}
				if (!$correct)
				{
					throw new Exc('Invalid obs scenes format');
				}
				$obs_scenes = json_encode($obs_scenes);
			}
			
			$flags = (int)get_optional_param('flags', $old_flags);
			$flags = ($flags & GAME_SETTINGS_EDITABLE_MASK) + ($old_flags & ~GAME_SETTINGS_EDITABLE_MASK);
			$flags |= GAME_NON_CONFIGURABLE_FEATURES;
			
			$prompt_sound_id = (int)get_optional_param('prompt_sound_id', $old_prompt_sound_id);
			if ($prompt_sound_id <= 0)
			{
				$prompt_sound_id = NULL;
			}
			
			$end_sound_id = (int)get_optional_param('end_sound_id', $old_end_sound_id);
			if ($end_sound_id <= 0)
			{
				$end_sound_id = NULL;
			}
			
			if (!$exists)
			{
				Db::exec(get_label('user'), 'INSERT INTO game_settings (user_id, flags, prompt_sound_id, end_sound_id, feature_flags) VALUES (?, ?, ?, ?, ?)', $user_id, $flags, $prompt_sound_id, $end_sound_id, GAME_FEATURE_MASK_ALL);
			}
			else if (is_null($obs_scenes))
			{
				Db::exec(get_label('user'), 'UPDATE game_settings SET prompt_sound_id = ?, end_sound_id = ?, flags = ? WHERE user_id = ?', $prompt_sound_id, $end_sound_id, $flags, $user_id);
			}
			else
			{
				Db::exec(get_label('user'), 'UPDATE game_settings SET prompt_sound_id = ?, end_sound_id = ?, flags = ?, obs_scenes = ? WHERE user_id = ?', $prompt_sound_id, $end_sound_id, $flags, $obs_scenes, $user_id);
			}

			// No need to log game_settings. They are changing too often when obs scenes are edited
			// $log_details = new stdClass();
			// if ($prompt_sound_id != $old_prompt_sound_id)
			// {
				// $log_details->prompt_sound_id = $prompt_sound_id;
			// }
			// if ($end_sound_id != $old_end_sound_id)
			// {
				// $log_details->end_sound_id = $end_sound_id;
			// }
			// if ($flags != $old_flags)
			// {
				// $log_details->flags = $flags;
			// }
			// db_log(LOG_OBJECT_USER, 'game settings', $log_details, $user_id);
		}
		Db::commit();
	}
	
	function settings_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Set game settings for a club or a user.');
		$help->request_param('club_id', 'Club id to set default game settings for.', 'the default settings are set for the current user.');
		$help->request_param('prompt_sound_id', 'Sound id for the ten second prompt before the end of the speech.', 'remains the same');
		$help->request_param('end_sound_id', 'Sound id for the the end of the speech.', 'remains the same');
		$help->request_param('flags', 'Game settings flags: 1 - not used; 2 - start timer on speaches automatically; 4 - can change roles during arrangement; 8 - no timer blinking.', 'remains the same');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Game Operations', CURRENT_VERSION);

?>