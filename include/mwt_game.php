<?php

require_once __DIR__ . '/game.php';

define('GAME_FEATURE_MASK_MWT', 0x000010be); // DON_CHECKS | SHERIFF_CHECKS | DEATH | DEATH_ROUND | DEATH_TYPE | LEGACY | WARNINGS
define('MWT_CLUB_ID', 96);
define('MWT_CITY_ID', 110);

function get_mwt_user($mwt_id)
{
	global $_lang;
	$query = new DbQuery('SELECT u.id, nu.name' .
		' FROM users u' .
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE u.mwt_id = ?', $mwt_id);
	if ($row = $query->next())
	{
		return $row;
	}
	
	$name = 'MWT_' . $mwt_id;
	$email = 'user@mafiaworldtour.com';
	Db::exec(get_label('name'), 'INSERT INTO names (langs, name) VALUES (?, ?)', DB_ALL_LANGS, $name);
	list ($name_id) = Db::record(get_label('name'), 'SELECT LAST_INSERT_ID()');
	
	$langs = LANG_ALL;
	$lang = LANG_RUSSIAN;
	Db::exec(
		get_label('user'), 
		'INSERT INTO users (name_id, password, auth_key, email, flags, club_id, languages, reg_time, def_lang, city_id, games, games_won, rating, mwt_id) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?, 0, 0, ' . USER_INITIAL_RATING . ', ?)',
		$name_id, md5(rand_string(8)), '', $email, NEW_USER_FLAGS | USER_FLAG_IMPORTED, MWT_CLUB_ID, LANG_ALL, LANG_RUSSIAN, MWT_CITY_ID, $mwt_id);
	list ($user_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
	
	$log_details = new stdClass();
	$log_details->name = $name;
	$log_details->email = $email;
	$log_details->flags = NEW_USER_FLAGS;
	$log_details->langs = LANG_ALL;
	$log_details->def_lang = LANG_RUSSIAN;
	$log_details->city_id = MWT_CITY_ID;
	db_log(LOG_OBJECT_USER, 'created', $log_details, $user_id);
	return array($user_id, $name);
}

function convert_mwt_game($mwt_game)
{
	if (!isset($mwt_game->tournament_id))
	{
		throw new Exc('Tournament id is not set.');
	}
	if (!isset($mwt_game->result))
	{
		throw new Exc('Result id is not set.');
	}
	if (!isset($mwt_game->result->result))
	{
		throw new Exc('Game result id is not set.');
	}
	if (!isset($mwt_game->result->players))
	{
		throw new Exc('Players are not set.');
	}
	if (!isset($mwt_game->result->player_roles))
	{
		throw new Exc('Roles are not set.');
	}
	
	if (!isset($mwt_game->result->start_time))
	{
		throw new Exc('Start time is not set.');
	}
	$start = get_datetime($mwt_game->result->start_time)->getTimestamp();;
	if (!isset($mwt_game->result->end_time))
	{
		throw new Exc('End time is not set.');
	}
	$end = get_datetime($mwt_game->result->end_time)->getTimestamp();;
	
	$query = new DbQuery('SELECT id, start_time, duration, club_id, rules FROM tournaments WHERE mwt_id = ?', $mwt_game->tournament_id);
	if ($row = $query->next())
	{
		list($tournament_id, $tournament_start, $tournament_duration, $club_id, $rules_code) = $row;
		$tournament_end = max($tournament_start + $tournament_duration, $end);
		$tournament_start = min($tournament_start, $start);
		$tournament_duration = $tournament_end - $tournament_start;
		
		// TODO: get the right round from the game and put the game to the right round
		$query = new DbQuery('SELECT id FROM events WHERE tournament_id = ? AND round = 0 ORDER BY id LIMIT 1', $tournament_id);
		if ($row = $query->next())
		{
			list($round_id) = $row;
		}
		else
		{
			$query = new DbQuery('SELECT id FROM events WHERE tournament_id = ? ORDER BY id LIMIT 1', $tournament_id);
			if ($row = $query->next())
			{
				list($round_id) = $row;
			}
			else
			{
				throw new Exc('Round id not found for tournament ' . $tournament_id);
			}
		}
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET start_time = ?, duration = ? WHERE id = ?', $tournament_start, $tournament_duration, $tournament_id);
		Db::exec(get_label('round'), 'UPDATE events SET start_time = ?, duration = ? WHERE id = ?', $tournament_start, $tournament_duration, $round_id);
	}
	else
	{
		$club_id = MWT_CLUB_ID;
		list($address_id) = Db::record(get_label('address'), 'SELECT id FROM addresses WHERE club_id = ? ORDER BY id LIMIT 1', $club_id);
		list($scoring_id, $rules_code) = Db::record(get_label('club'), 'SELECT scoring_id, rules FROM clubs WHERE id = ?', $club_id);
		list($scoring_version) = Db::record(get_label('scoring'), 'SELECT MAX(version) FROM scoring_versions WHERE scoring_id = ?', $scoring_id);
		$tournament_flags = TOURNAMENT_FLAG_AWARD_MVP | TOURNAMENT_FLAG_AWARD_RED | TOURNAMENT_FLAG_AWARD_BLACK | TOURNAMENT_FLAG_IMPORTED;
		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, scoring_id, scoring_version, scoring_options, rules, flags, type, mwt_id) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			'MWT_' . $mwt_game->tournament_id, $club_id, $address_id, $start, $end - $start, LANG_ALL, $scoring_id, $scoring_version, '{}', $rules_code, $tournament_flags, TOURNAMENT_TYPE_FIIM_ONE_ROUND, $mwt_game->tournament_id);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		$log_details = new stdClass();
		$log_details->name = 'MWT_' . $mwt_game->tournament_id;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = LANG_ALL;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->rules_code = $rules_code;
		$log_details->flags = $tournament_flags;
		$log_details->type = TOURNAMENT_TYPE_FIIM_ONE_ROUND;
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id);
		
		$round_name = get_label('main round');
		
		$event_flags = EVENT_MASK_HIDDEN | EVENT_FLAG_ALL_CAN_REFEREE;
		Db::exec(
			get_label('round'), 
			'INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_version, scoring_options, tournament_id, rules) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$round_name, $address_id, $club_id, $start, $end - $start, $event_flags, LANG_ALL, $scoring_id, $scoring_version, '{}', $tournament_id, $rules_code);
		list ($round_id) = Db::record(get_label('round'), 'SELECT LAST_INSERT_ID()');
		$log_details = new stdClass();
		$log_details->name = $round_name;
		$log_details->tournament_id = $tournament_id;
		$log_details->club_id = $club_id;
		$log_details->address_id = $address_id;
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = LANG_ALL;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->rules_code = $rules_code;
		$log_details->flags = $event_flags;
		db_log(LOG_OBJECT_EVENT, 'round created', $log_details, $tournament_id, $club_id);
	}
	
	
	$game = new stdClass();
	$game->clubId = (int)$club_id;
	$game->eventId = (int)$round_id;
	$game->tournamentId = (int)$tournament_id;
	$game->startTime = $start;
	$game->endTime = $end;
	$game->language = get_lang_code(LANG_RUSSIAN);
	$game->rules = $rules_code;
	$game->winner = $mwt_game->result->result == 'black' ? 'maf' : 'civ';
	$game->moderator = new stdClass();
	if (isset($mwt_game->referee_user_id))
	{
		list($game->moderator->id, $game->moderator->name) = get_mwt_user($mwt_game->referee_user_id);
		$game->moderator->id = (int)$game->moderator->id;
	}
	else
	{
		$game->moderator->id = $_profile->user_id;
		$game->moderator->name = $_profile->user_name;
	}
	
	$game->players = array(
		new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass(), 
		new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass());
	for ($i = 0; $i < 10; ++$i)
	{
		$player = $game->players[$i];
		list($player->id, $player->name) = get_mwt_user($mwt_game->result->players[$i]);
		switch ($mwt_game->result->player_roles[$i])
		{
			case 'red':
				break;
			case 'black':
				$player->role = 'maf';
				break;
			case 'sheriff':
				$player->role = 'sheriff';
				break;
			case 'don':
				$player->role = 'don';
				break;
			default:
				throw new Exc('Unknown role ' . $mwt_game->result->player_roles[$i] . ' for player ' . ($i + 1));
		}
		if (isset($mwt_game->result->player_fouls))
		{
			$player->warnings = $mwt_game->result->player_fouls[$i];
		}
		if (isset($mwt_game->result->game_bonus) && $mwt_game->result->game_bonus[$i] != 0)
		{
			$player->bonus = $mwt_game->result->game_bonus[$i];
		}
		else if (isset($mwt_game->result->game_autobonus) && !$mwt_game->result->game_autobonus[$i])
		{
			$player->bonus = 'worstMove';
		}
		if (isset($mwt_game->result->penalty) && $mwt_game->result->penalty[$i] != 0)
		{
			if (!isset($player->bonus))
			{
				$player->bonus = -$mwt_game->result->penalty[$i];
			}
			else if (is_array($player->bonus))
			{
				$player->bonus[] = -$mwt_game->result->penalty[$i];
			}
			else
			{
				$player->bonus = array($player->bonus, -$mwt_game->result->penalty[$i]);
			}
		}
		if (isset($mwt_game->result->penalty_disciplinary) && $mwt_game->result->penalty_disciplinary[$i] != 0)
		{
			if (!isset($player->bonus))
			{
				$player->bonus = -$mwt_game->result->penalty_disciplinary[$i];
			}
			else if (is_array($player->bonus))
			{
				$player->bonus[] = -$mwt_game->result->penalty_disciplinary[$i];
			}
			else
			{
				$player->bonus = array($player->bonus, -$mwt_game->result->penalty_disciplinary[$i]);
			}
		}
	}
	if (isset($mwt_game->result->best_move_value) && isset($mwt_game->result->killed_first) && $mwt_game->result->killed_first >= 1 && $mwt_game->result->killed_first <= 10)
	{
		$player = $game->players[$mwt_game->result->killed_first - 1];
		$player->legacy = $mwt_game->result->best_move_value;
	}
	if (isset($mwt_game->result->leaves))
	{
		foreach ($mwt_game->result->leaves as $leave)
		{
			if (!isset($leave->p_num) || !is_numeric($leave->p_num) || $leave->p_num < 1 || $leave->p_num > 10)
			{
				continue;
			}
			if (!isset($leave->leave_type) || !is_numeric($leave->leave_type) || $leave->leave_type < 1 || $leave->leave_type > 4)
			{
				continue;
			}
			$player = $game->players[$leave->p_num - 1];
			$player->death = new stdClass();
			switch ($leave->leave_type)
			{
			case 1: // voted out
				$player->death->type = DEATH_TYPE_DAY;
				if (isset($leave->nights_before_count))
				{
					$player->death->round = $leave->nights_before_count;
				}
				else if (isset($leave->days_before_count))
				{
					$player->death->round = $leave->days_before_count - 1;
				}
				break;
			case 2: // night kill
				$player->death->type = DEATH_TYPE_NIGHT;
				if (isset($leave->nights_before_count))
				{
					$player->death->round = $leave->nights_before_count;
				}
				else if (isset($leave->days_before_count))
				{
					$player->death->round = $leave->days_before_count;
				}
				break;
			case 3: // mod kill
				if (isset($player->warnings) && $player->warnings >= 4)
				{
					$player->death->type = DEATH_TYPE_KICK_OUT;
				}
				else
				{
					$player->death->type = DEATH_TYPE_WARNINGS;
				}
				if (isset($leave->nights_before_count))
				{
					$player->death->round = $leave->nights_before_count;
				}
				else if (isset($leave->days_before_count))
				{
					$player->death->round = $leave->days_before_count - 1;
				}
				break;
			case 4: // opposite team wins
				$player->death->type = DEATH_TYPE_TEAM_KICK_OUT;
				if (isset($leave->nights_before_count))
				{
					$player->death->round = $leave->nights_before_count;
				}
				else if (isset($leave->days_before_count))
				{
					$player->death->round = $leave->days_before_count - 1;
				}
				break;
			}
		}
	}
	if (isset($mwt_game->result->reveal_don))
	{
		for ($i = 0; $i < count($mwt_game->result->reveal_don); ++$i)
		{
			$p_num = $mwt_game->result->reveal_don[$i];
			if ($p_num >= 1 && $p_num <= 10)
			{
				$player = $game->players[$p_num - 1];
				$player->don = $i + 1;
			}
		}
	}
	if (isset($mwt_game->result->reveal_sheriff))
	{
		for ($i = 0; $i < count($mwt_game->result->reveal_sheriff); ++$i)
		{
			$p_num = $mwt_game->result->reveal_sheriff[$i];
			if ($p_num >= 1 && $p_num <= 10)
			{
				$player = $game->players[$p_num - 1];
				$player->sheriff = $i + 1;
			}
		}
	}
	if (isset($mwt_game->result->comment))
	{
		$game->comment = $mwt_game->result->comment;
	}
	return $game;
}


?>