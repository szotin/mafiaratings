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
		'INSERT INTO users (name_id, password, auth_key, email, flags, club_id, languages, reg_time, def_lang, city_id, mwt_id) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?, ?)',
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
		$tournament_flags = TOURNAMENT_FLAG_AWARD_MVP | TOURNAMENT_FLAG_AWARD_RED | TOURNAMENT_FLAG_AWARD_BLACK;
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

function _compare_leaves($leave1, $leave2)
{
	if ($leave1->nights_before_count > $leave2->nights_before_count)
	{
		return 1;
	}
	else if ($leave1->nights_before_count < $leave2->nights_before_count)
	{
		return -1;
	}

	if ($leave1->days_before_count > $leave2->days_before_count)
	{
		return 1;
	}
	else if ($leave1->days_before_count < $leave2->days_before_count)
	{
		return -1;
	}
	return $leave1->leave_type - $leave2->leave_type;
}

function convert_game_to_mwt($game_id)
{
	global $_lang;
	
	list ($game_json, $feature_flags, $table_num, $game_num, $misc, $event_name, $event_round, $tournament_id, $mwt_tournament_id, $mwt_moderator_id, $moderator_name, $timezone) = Db::record(get_label('game'), 
		'SELECT g.json, g.feature_flags, g.table_num, g.game_num, e.misc, e.name, e.round, t.id, t.mwt_id, u.mwt_id, nu.name, c.timezone'.
		' FROM games g'.
		' JOIN events e ON e.id = g.event_id'.
		' JOIN addresses a ON a.id = e.address_id'.
		' JOIN cities c ON c.id = a.city_id'.
		' JOIN tournaments t ON t.id = g.tournament_id'.
		' JOIN users u ON u.id = g.moderator_id'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE g.id = ?', $game_id);
	if (is_null($table_num))
	{
		throw new Exc(get_label('Unknown [0]', get_label('table')));
	}
	if (is_null($game_num))
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	if (is_null($misc))
	{
		throw new Exc(get_label('Unknown [0]', get_label('seating')));
	}
	if (is_null($mwt_tournament_id))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	if (is_null($mwt_moderator_id))
	{
		throw new Exc(get_label('MWT user id is not set for the moderator [0]', $moderator_name));
	}
	$misc = json_decode($misc);
	
	$mwt_game = new stdClass();
	$mwt_game->tournament_id = $mwt_tournament_id;
	$mwt_game->table_number = $table_num;
	if (isset($misc->mwt_schema))
	{
		if ($table_num - 1 >= count($misc->mwt_schema))
		{
			throw new Exc(get_label('Unknown [0]', get_label('table')));
		}
		if ($game_num - 1 >= count($misc->mwt_schema[$table_num - 1]))
		{
			throw new Exc(get_label('Unknown [0]', get_label('game')));
		}
		$mwt_game->part_number = $misc->mwt_schema[$table_num - 1][$game_num - 1];
		if ($game_num > 1 && $mwt_game->part_number != $misc->mwt_schema[$table_num - 1][$game_num-2])
		{
			$mwt_game->session_number = 1;
		}
		else
		{
			$mwt_game->session_number = $game_num;
		}
	}
	else 
	{
		$mwt_game->session_number = $game_num;
		if ($event_round > 0)
		{
			list($max_round) = Db::record(get_label('round'), 'SELECT MAX() FROM events WHERE tournament_id = ?', $tournament_id);
			$mwt_game->part_number = 2 + $max_round - $event_round;
		}
		else
		{
			$mwt_game->part_number = 1;
		}
	}
	
	$mwt_game->referee_user_id = $mwt_moderator_id;
	
	$game = new Game($game_json, $feature_flags);
	$game = $game->data;
	$result = new stdClass();
	date_default_timezone_set($timezone);
	$result->start_time = date('Y-m-d G:i:s', $game->startTime);
	$result->end_time = date('Y-m-d G:i:s', $game->endTime);
	if ($game->winner == 'maf')
	{
		$result->result = 'black';
	}
	else
	{
		$result->result = 'red';
	}
	$result->black_count = 0;
	$result->red_count = 0;
	
	$players_list = '';
	$delim = '';
	foreach ($game->players as $player)
	{
		$players_list .= $delim . $player->id;
		$delim = ', ';
	}
	$player_map = array();
	$query = new DbQuery('SELECT id, mwt_id FROM users WHERE id IN('.$players_list.')');
	while ($row = $query->next())
	{
		list ($uid, $umwtid) = $row;
		$player_map[$uid] = $umwtid; 
	}
	
	$result->players = array();
	$result->is_replacement = array();
	$result->player_roles = array();
    $result->game_autobonus = array();
    $result->game_bonus = array();
    $result->penalty = array();
    $result->penalty_disciplinary = array();
	$result->reveal_don = array();
	$result->reveal_sheriff = array();
	$result->player_fouls = array();
	$result->leaves = array();
	$result->comment = '';
	$comment_delim = '';
	for ($i = 0; $i < 10; ++$i)
	{
		$player = $game->players[$i];
		$result->players[] = isset($player_map[$player->id]) ? $player_map[$player->id] : NULL;
		
		$game_autobonus = 1;
		$game_bonus = 0;
		$penalty = 0;
		$penalty_disciplinary = 0;

		if (isset($player->death) && isset($player->death->type))
		{
			// leave_type
			// 1 - заголосован днем
			// 2 - убит ночью
			// 3 - дисквалифицирован
			// 4 - дисквалифицирован на ппк	
			$leave = NULL;
			switch ($player->death->type)
			{
			case DEATH_TYPE_GIVE_UP:
			case DEATH_TYPE_KICK_OUT:
			case DEATH_TYPE_WARNINGS:
				$penalty_disciplinary = -0.5;
				$leave = new stdClass();
				$leave->leave_type = 3;
				$leave->votes_count = 0;
				$leave->nights_before_count = $player->death->round;
				$leave->days_before_count = $player->death->round;
				if (!Game::is_night($player->death->time))
				{
					++$leave->days_before_count;
				}
				break;
			case DEATH_TYPE_TEAM_KICK_OUT:
				$penalty_disciplinary = -0.7;
				$leave = new stdClass();
				$leave->leave_type = 4;
				$leave->votes_count = 0;
				$leave->nights_before_count = $player->death->round;
				$leave->days_before_count = $player->death->round;
				if (!Game::is_night($player->death->time))
				{
					++$leave->days_before_count;
				}
				break;
			case DEATH_TYPE_NIGHT:
				$leave = new stdClass();
				$leave->leave_type = 2;
				$leave->votes_count = 0;
				$leave->nights_before_count = $player->death->round;
				$leave->days_before_count = $player->death->round;
				if ($player->death->round == 1)
				{
					$result->killed_first = $i + 1;
					if (isset($player->legacy))
					{
						$result->best_move_value = $player->legacy;
						$result->best_move = 0;
						foreach ($player->legacy as $l)
						{
							$p = $game->players[$l-1]; 
							if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
							{
								++$result->best_move;
							}
						}
					}
				}
				break;
			case DEATH_TYPE_DAY:
				$leave = new stdClass();
				$leave->leave_type = 1;
				$leave->votes_count = 0;
				foreach ($game->players as $p)
				{
					if (isset($p->voting) && $player->death->round < count($p->voting) && $p->voting[$player->death->round] == $i + 1)
					{
						++$leave->votes_count;
					}
				}
				$leave->nights_before_count = $player->death->round;
				$leave->days_before_count = $player->death->round + 1;
				break;
			}
			
			if ($leave)
			{
				$leave->p_num = $i + 1;
				$result->leaves[] = $leave;
			}
		}
		
		if (!isset($player->role) || $player->role == 'civ')
		{
			$result->player_roles[] = "red";
		}
		else if ($player->role == 'maf')
		{
			$result->player_roles[] = "black";
		}
		else if ($player->role == 'don')
		{
			$result->player_roles[] = "don";
		}
		else if ($player->role == 'sheriff')
		{
			$result->player_roles[] = "sheriff";
		}
		else
		{
			// ???
			$result->player_roles[] = "red";
		}
		if (!isset($player->death))
		{
			if (!isset($player->role) || $player->role == 'civ')
			{
				++$result->red_count;
			}
			else if ($player->role == 'maf')
			{
				++$result->black_count;
			}
			else if ($player->role == 'don')
			{
				++$result->black_count;
			}
			else if ($player->role == 'sheriff')
			{
				++$result->red_count;
			}
			else
			{
				// ???
				++$result->red_count;
			}
		}
		
		if (isset($player->bonus))
		{
			if (is_array($player->bonus))
			{
				foreach ($player->bonus as $bonus)
				{
					if (is_numeric($bonus))
					{
						if ($bonus > 0)
						{
							$game_bonus += $bonus;
						}
						else
						{
							$penalty += $bonus;
						}
					}
					else if ($bonus == 'worstMove')
					{
						$game_autobonus = 0;
					}
				}
			}
			else if (is_numeric($player->bonus))
			{
				if ($player->bonus > 0)
				{
					$game_bonus += $player->bonus;
				}
				else
				{
					$penalty += $player->bonus;
				}
			}
			else if ($player->bonus == 'worstMove')
			{
				$game_autobonus = 0;
			}

		}
		
		$result->game_autobonus[] = $game_autobonus;
		$result->game_bonus[] = $game_bonus;
		$result->penalty[] = $penalty;
		$result->penalty_disciplinary[] = $penalty_disciplinary;
		$result->is_replacement[] = (!isset($misc->seating) || $misc->seating[$table_num - 1][$game_num - 1][$i] == $player->id) ? 0 : 1;
		
		if (isset($player->don))
		{
			for ($n = count($result->reveal_don); $n < $player->don; ++$n)
			{
				$result->reveal_don[] = NULL;
			}
			$result->reveal_don[$player->don - 1] = $i + 1;
		}
		
		if (isset($player->sheriff))
		{
			for ($n = count($result->reveal_sheriff); $n < $player->sheriff; ++$n)
			{
				$result->reveal_sheriff[] = NULL;
			}
			$result->reveal_sheriff[$player->sheriff - 1] = $i + 1;
		}
		
		$warnings = 0;
		if (isset($player->warnings))
		{
			if (is_array($player->warnings))
			{
				$warnings = count($player->warnings);
			}
			else
			{
				$warnings = (int)$player->warnings;
			}
		}
		$result->player_fouls[] = $warnings;
		
		if (isset($player->comment))
		{
			$result->comment .= $comment_delim . ($i + 1) . '. ' . $player->comment;
			$comment_delim = "\n";
		}
	}

	usort($result->leaves, '_compare_leaves');
	$num_players = 10;
	$beg = 0;
	for ($i = 0; $i < count($result->leaves); ++$i)
	{
		$leave = $result->leaves[$i];
		$leave->players_before_count = $num_players--;
		$leave->game_ended = 0;
		if ($i > 0)
		{
			$prev = $result->leaves[$beg];
			if ($leave->leave_type != 1 || $prev->leave_type != 1 || $leave->nights_before_count != $prev->nights_before_count || $leave->days_before_count != $prev->days_before_count)
			{
				for ($j = $beg; $j < $i; ++$j)
				{
					$result->leaves[$j]->leave_along_count = $i - $beg;
				}
				$beg = $i;
			}
		}
	}
	if ($i > 0)
	{
		$leave = $result->leaves[$i-1];
		$leave->game_ended = 1;
		for ($j = $beg; $j < $i; ++$j)
		{
			$result->leaves[$j]->leave_along_count = $i - $beg;
		}
	}
	
	$mwt_game->result = $result;
	throw new Exc('<pre>'.formatted_json($mwt_game).'</pre>');
//	throw new Exc('<pre>'.formatted_json($game).'</pre>');
}

?>