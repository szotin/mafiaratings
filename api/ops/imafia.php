<?php

require_once '../../include/api.php';
require_once '../../include/tournament.php';
require_once '../../include/game.php';
require_once '../../include/api_keys.php';

define('CURRENT_VERSION', 0);

function post($url, $headers = NULL, $params = NULL)
{
	$real_headers = array('Content-type: application/x-www-form-urlencoded');
	if (!is_null($headers))
	{
		foreach ($headers as $key => $value)
		{
			$real_headers[] = $key . ': ' . $value;
		}
	}
	
	$real_params = '';
	if (!is_null($params))
	{
		$real_params = http_build_query($params);
	}
	
	// use key 'http' even if you send the request to https://...
	$options = [
		'http' => [
			'header' => $real_headers,
			'method' => 'POST',
			'content' => $real_params,
		],
	];
	
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	if ($result === false) 
	{
		throw new Exc(get_label('Unable to connect to [0].', 'iMafia'));
	}
	
	$data = json_decode($result);
	if (is_null($result))
	{
		throw new Exc(get_label('Invalid response from [0]: [1]', 'iMafia', $result));
	}
	return $data;
}

function compare_players($player1, $player2)
{
	if (isset($player1->id))
	{
		if (!isset($player2->id))
		{
			return 1;
		}
	}
	else if (isset($player2->id))
	{
		return -1;
	}
	return strcmp($player1->imafia_name, $player2->imafia_name);
}

function compare_games($game1, $game2)
{
	if ($game1->stage != $game2->stage)
	{
		return $game1->stage - $game2->stage;
	}
	if ((int)$game1->name != (int)$game2->name)
	{
		return (int)$game1->name - (int)$game2->name;
	}
	return (int)$game1->table - (int)$game2->table;
}

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// import_games
	//-------------------------------------------------------------------------------------------------------
	private function get_players_mapping($content)
	{
		global $_lang;
		
		$players = array();
		if (!isset($content->games))
		{
			return $players;
		}
		foreach ($content->games as $game)
		{
			if (!isset($game->players))
			{
				continue;
			}
			
			if ($game->referees_id && !array_key_exists($game->referees_id, $players))
			{
				$ref_id = $game->referees_id;
				$p = new stdClass();
				$p->imafia_id = $game->referees_id;
				$p->imafia_name = $content->referees_array->$ref_id->name;
				$players[$game->referees_id] = $p;
			}
			foreach ($game->players as $number => $player)
			{
				if ($player->players_id && !array_key_exists($player->players_id, $players))
				{
					$p = new stdClass();
					$p->imafia_id = $player->players_id;
					$p->imafia_name = $player->name;
					$players[$player->players_id] = $p;
				}
			}
		}
		
		$players_list = '';
		$delim = '';
		foreach ($players as $imafia_id => $player)
		{
			$players_list .= $delim . $imafia_id;
			$delim = ',';
		}
		
		$query = new DbQuery('SELECT u.imafia_id, u.imafia_name, u.id, n.name, u.flags FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.imafia_id IN (' . $players_list . ')');
		while ($row = $query->next())
		{
			list ($imafia_id, $imafia_name, $id, $name, $flags) = $row;
			$players[$imafia_id]->id = $id;
			$players[$imafia_id]->name = $name;
			$players[$imafia_id]->flags = $flags;
			$players[$imafia_id]->old_imafia_name = $imafia_name;
		}
		return $players;
	}
	
	function import_games_op()
	{
		set_time_limit(180);
		
		$tournament_id = (int)get_required_param('tournament_id');
		$imafia_id = (int)get_required_param('imafia_id');
		$overwrite = (int)get_optional_param('overwrite', 0);
		if ($imafia_id <= 0)
		{
			$imafia_id = NULL;
		}
		
		Db::begin();
		list ($club_id, $old_imafia_id, $address_id, $start, $duration, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, $ops, $rules_code, $tournament_flags, $misc) = 
			Db::record(get_label('tournament'), 'SELECT club_id, imafia_id, address_id, start_time, duration, notes, langs, fee, currency_id, scoring_id, scoring_version, scoring_options, rules, flags, misc FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (is_null($misc))
		{
			$misc = new stdClass();
		}
		else
		{
			$misc = json_decode($misc);
		}
		
		if ($imafia_id != $old_imafia_id)
		{
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET imafia_id = ? WHERE id = ?', $imafia_id, $tournament_id);
			$log_details = new stdClass();
			$log_details->imafia_id = $imafia_id;
			db_log(LOG_OBJECT_TOURNAMENT, 'changed`', $log_details, $tournament_id, $club_id);
		}
		
		$content = post('https://imafia.org/api/tournament_games/' . $imafia_id . '?api_key=' . IMAFIA_API_KEY);
		$players = $this->get_players_mapping($content);
		
		$ready_for_import = true;
		foreach ($players as $imafia_user_id => $player)
		{
			if (!isset($player->id))
			{
				$ready_for_import = false;
			}
			else if (!empty($player->imafia_name) && $player->imafia_name != $player->old_imafia_name)
			{
				Db::exec(get_label('user'), 'UPDATE users SET imafia_name = ? WHERE id = ?', $player->imafia_name, $player->id);
			}
		}
		
		if ($ready_for_import)
		{
			usort($content->games, 'compare_games');
			$games_count = count($content->games);
			if ($games_count <= 0)
			{
				throw new Exc(get_label('No games received from [0]', 'iMafia'));
			}
			$end = $start + $duration;
			$game_start = (int)$start;
			
			$existing_games = array();
			if ($overwrite)
			{
				// cleanup games
				$prev_game_id = NULL;
				$query = new DbQuery('SELECT id, end_time FROM games WHERE tournament_id = ? AND (flags & '.GAME_FLAG_RATING.') <> 0 ORDER BY end_time, id LIMIT 1', $tournament_id);
				if ($row = $query->next())
				{
					list($game_id, $end_time) = $row;
					$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
					if ($row = $query->next())
					{
						list ($prev_game_id) = $row;
					}
				}
				
				Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $prev_game_id, $tournament_id);
				Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id IN (SELECT id FROM games WHERE tournament_id = ?)', $tournament_id);
				Db::exec(get_label('game'), 'DELETE FROM games WHERE tournament_id = ?', $tournament_id);
			}
			else
			{
				$query = new DbQuery('SELECT id, event_id, table_num, game_num, start_time FROM games WHERE tournament_id = ?', $tournament_id);
				while ($row = $query->next())
				{
					list ($game_id, $event_id, $table_num, $game_num, $g_start) = $row;
					if (!is_null($table_num) && !is_null($game_num))
					{
						$existing_games[$event_id . '-' . $table_num . '-' . $game_num] = (int)$game_id;
					}
					$game_start = max($game_start, (int)$g_start);
				}
			}
			
			// create rounds
			$ops = json_decode($ops);
			$round_ops = new stdClass();
			if (isset($ops->flags))
			{
				$round_ops->flags = $ops->flags;
			}
			$round_ops->weight = 1.2;
			$round_ops->group = 'final';
			
			$max_stage = (int)$content->games[$games_count-1]->stage;
			
			$rounds = array();
			$query = new DbQuery('SELECT id, round FROM events WHERE tournament_id = ?', $tournament_id);
			while ($row = $query->next())
			{
				list ($round_id, $round_num) = $row;
				if ($round_num == 0)
				{
					$rounds[1] = (int)$round_id;
				}
				else
				{
					$rounds[$max_stage + 1 - $round_num] = (int)$round_id;
				}
			}
			
			if ($max_stage > 1 && !array_key_exists($max_stage, $rounds))
			{
				$round_id = create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($round_ops), $tournament_id, $rules_code, 1, $tournament_flags);
				$rounds[$max_stage] = (int)$round_id;
			}
			unset($round_ops->weight);
			$round_ops->group = 'main';
			for ($i = 1; $i < $max_stage - 1; ++$i)
			{
				if (!array_key_exists($max_stage - $i, $rounds))
				{
					$round_id = create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($round_ops), $tournament_id, $rules_code, $i + 1, $tournament_flags);
					$rounds[$max_stage - $i] = (int)$round_id;
				}
			}
			if (!array_key_exists(1, $rounds))
			{
				$round_id = create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($round_ops), $tournament_id, $rules_code, 0, $tournament_flags);
				$rounds[1] = (int)$round_id;
			}
			
			// create games
			$lang = get_next_lang(LANG_NO, $langs);
			if (!is_valid_lang($lang))
			{
				$lang = LANG_RUSSIAN;
			}
			$lang = get_lang_code($lang);
			
			$game_number = 1;
			$game_stage = 1;
			$games = array();
			foreach ($content->games as $game)
			{
				if ($game->result < 1 || $game->result > 3)
				{
					continue;
				}
				
				$event_id = $rounds[$game->stage];
				if (array_key_exists($event_id . '-' . $game->table . '-' . $game->name, $existing_games))
				{
					continue;
				}
				
				if ($game_number != $game->name || $game_stage != $game->stage)
				{
					$game_start += 3600;
					$game_number = $game->name;
					$game_stage = $game->stage;
				}
				else
				{
					$game_start += 10;
				}
				
				$g = new stdClass();
				$g->clubId = (int)$club_id;
				$g->eventId = $event_id;
				$g->tableNum = (int)$game->table;
				$g->gameNum = (int)$game->name;
				$g->language = $lang;
				$g->rules = $rules_code;
				$g->features = 'ldut';
				$g->tournamentId = (int)$tournament_id;
				$g->moderator = new stdClass();
				$mapping = $players[$game->referees_id];
				$g->moderator->id = $mapping->id;
				$g->moderator->name = $mapping->name;
				$g->startTime = $game_start;
				$g->endTime = $g->startTime + 2100;
				$g->comment = $game->comment;
				switch ($game->result)
				{
				case 1:
					$g->winner = 'civ';
					break;
				case 2:
					$g->winner = 'maf';
					break;
				case 3:
					$g->winner = 'tie';
					break;
				}
				$g->players = array(
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass(), 
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass());
				foreach ($game->players as $num => $player)
				{
					$p = $g->players[$num - 1];
					if (array_key_exists($player->players_id, $players))
					{
						$mapping = $players[$player->players_id];
						$p->id = $mapping->id;
						$p->name = $mapping->name;
					}
					switch ($player->roles_id)
					{
						case 2:
							$p->role = 'sheriff';
							break;
						case 3:
							$p->role = 'maf';
							break;
						case 4:
							$p->role = 'don';
							break;
					}
					$bonus = (float)$player->points_plus + (float)$player->points_minus;
					if (abs($bonus) > 0.001)
					{
						$p->bonus = $bonus;
					}
				}
				if (isset($game->first_blood) && !is_null($game->first_blood))
				{
					$first_killed = $g->players[$game->first_blood - 1];
					$first_killed->death = new stdClass();
					$first_killed->death->type = 'night';
					$first_killed->death->round = 1;
					$legacy = array();
					if (isset($game->best_move_1) && !is_null($game->best_move_1))
					{
						$legacy[] = (int)$game->best_move_1;
					}
					if (isset($game->best_move_2) && !is_null($game->best_move_2))
					{
						$legacy[] = (int)$game->best_move_2;
					}
					if (isset($game->best_move_3) && !is_null($game->best_move_3))
					{
						$legacy[] = (int)$game->best_move_3;
					}
					if (count($legacy) > 0)
					{
						$first_killed->legacy = $legacy;
					}
				}
				$games[] = $g;
			}
			
			foreach ($games as $g)
			{
				$game = new Game($g, 'ldut');
				$game->create();
			}
		}
		else
		{
			$misc->imafia = new stdClass();
			
			$misc->imafia->players = array();
			foreach ($players as $imafia_user_id => $player)
			{
				$misc->imafia->players[] = $player;
			}
			usort($misc->imafia->players, 'compare_players');
			
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET misc = ? WHERE id = ?', json_encode($misc), $tournament_id);
			echo get_label('Please complete players/referees mapping');
		}
		Db::commit();
	}
	
	// function import_games_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// map_player
	//-------------------------------------------------------------------------------------------------------
	function map_player_op()
	{
		global $_lang;
		
		$tournament_id = (int)get_required_param('tournament_id');
		$imafia_id = (int)get_required_param('imafia_id');
		$user_id = (int)get_required_param('user_id');
		
		Db::begin();
		list ($club_id, $misc) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.misc FROM tournaments t WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if ($user_id > 0)
		{
			list ($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT n.name, u.flags FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		}
		
		$misc = json_decode($misc);
		if (isset($misc->imafia) && isset($misc->imafia->players))
		{
			foreach ($misc->imafia->players as $player)
			{
				if ($player->imafia_id == $imafia_id)
				{
					if ($user_id > 0)
					{
						$player->id = $user_id;
						$player->name = $user_name;
						$player->flags = $user_flags;
					}
					else
					{
						if (isset($player->id))
						{
							unset($player->id);
						}
						if (isset($player->name))
						{
							unset($player->name);
						}
						if (isset($player->flags))
						{
							unset($player->flags);
						}
					}
					usort($misc->imafia->players, 'compare_players');
					break;
				}
			}
		}
		$misc = json_encode($misc);
		
		Db::exec(get_label('user'), 'UPDATE users u SET imafia_id = NULL, imafia_name = "" WHERE imafia_id = ?', $imafia_id);
		if ($user_id > 0)
		{
			Db::exec(get_label('user'), 'UPDATE users u SET imafia_id = ?, imafia_name = ? WHERE id = ?', $imafia_id, $player->imafia_name, $user_id);
		}
		Db::exec(get_label('tournament'), 'UPDATE tournaments u SET misc = ? WHERE id = ?', $misc, $tournament_id);
		Db::commit();
	}
	
	// function map_player_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// cleanup
	//-------------------------------------------------------------------------------------------------------
	function cleanup_op()
	{
		global $_lang;
		
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list ($club_id, $misc) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.misc FROM tournaments t WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (!is_null($misc))
		{
			$misc = json_decode($misc);
			if (isset($misc->imafia))
			{
				unset($misc->imafia);
				Db::exec(get_label('tournament'), 'UPDATE tournaments SET misc = ? WHERE id = ?', json_encode($misc), $tournament_id);
			}
		}
		Db::commit();
	}
	
	// function cleanup_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
}

$page = new ApiPage();
$page->run('iMafia Operations', CURRENT_VERSION);

?>