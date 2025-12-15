<?php

require_once '../../include/api.php';
require_once '../../include/tournament.php';
require_once '../../include/game.php';
require_once '../../include/api_keys.php';

define('CURRENT_VERSION', 0);
define('MAX_TABLES', 10000);

function post($url, $body)
{
	$headers = array('Content-type: application/x-www-form-urlencoded');
	
	// use key 'http' even if you send the request to https://...
	$options = [
		'http' => [
			'header' => $headers,
			'method' => 'POST',
			'content' => $body,
		],
	];
	
	$context = stream_context_create($options);
	$result = file_get_contents($url, false, $context);
	if ($result === false) 
	{
		throw new Exc(get_label('Unable to connect to [0].', 'emotion.games'));
	}
	$data = json_decode($result);
	if (is_null($result))
	{
		throw new Exc(get_label('Invalid response from [0]: [1]', 'emotion.games', $result));
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
	return strcmp($player1->emo_name, $player2->emo_name);
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
	private function get_players_mapping($content, $old_players)
	{
		global $_lang;
		
		if (!isset($content->body) || !isset($content->body->games))
		{
			return $old_players;
		}
		
		$players = array();
		$unknown_refs = array();
		foreach ($content->body->games as $game_num => $game)
		{
			if (isset($game->referee_eg_id) && $game->referee_eg_id > 0)
			{
				if (!array_key_exists($game->referee_eg_id, $players))
				{
					if (array_key_exists($game->referee_eg_id, $old_players))
					{
						$p = $old_players[$game->referee_eg_id];
						if (!isset($p->emo_name))
						{
							$p->emo_name = '';
						}
					}
					else
					{
						$p = new stdClass();
						$p->emo_id = $game->referee_eg_id;
						$p->emo_name = '';
					}
					if (isset($game->referee_nickname) && $game->referee_nickname != NULL)
					{
						$p->emo_name = $game->referee_nickname;
					}
					$players[$p->emo_id] = $p;
				}
			}
			else
			{
				$ref_id = -$game->table_number;
				if (isset($game->is_final) && $game->is_final)
				{
					$ref_id -= MAX_TABLES; 
				}
				if (!array_key_exists($ref_id, $players))
				{
					if (array_key_exists($ref_id, $old_players))
					{
						$p = $old_players[$ref_id];
					}
					else
					{
						$p = new stdClass();
						$p->emo_id = $ref_id;
						if ($ref_id <= -MAX_TABLES)
						{
							$p->emo_name = get_label('Referee of the table [0] in the finals', $game->table_number);
						}
						else
						{
							$p->emo_name = get_label('Referee of the table [0]', $game->table_number);
						}
					}
					$players[$ref_id] = $p;
					$unknown_refs[] = $p;
				}
			}
			
			foreach ($game->players as $player_num => $player)
			{
				if (isset($player->eg_id) && $player->eg_id > 0 && !array_key_exists($player->eg_id, $players))
				{
					if (array_key_exists($player->eg_id, $old_players))
					{
						$p = $old_players[$player->eg_id];
					}
					else
					{
						$p = new stdClass();
						$p->emo_id = $player->eg_id;
					}
					$p->emo_name = $player->nickname;
					$players[$p->emo_id] = $p;
				}
			}
		}
		
		foreach ($unknown_refs as $ref)
		{
			$table_num = -$ref->emo_id;
			$is_finals = false;
			if ($table_num >= MAX_TABLES)
			{
				$table_num -= MAX_TABLES;
				$is_finals = true;
			}
			
			$candidates = array();
			foreach ($content->body->games as $game_num => $game)
			{
				$is_game_finals = isset($game->is_final) && $game->is_final;
				if (
					isset($game->referee_eg_id) && $game->referee_eg_id > 0 && 
					$game->table_number == $table_num && 
					(($is_finals && $is_game_finals) || (!$is_finals && !$is_game_finals)))
				{
					if (!array_key_exists($game->referee_eg_id, $candidates))
					{
						$candidates[$game->referee_eg_id] = 0;
					}
					++$candidates[$game->referee_eg_id];
				}
			}
			
			$winner_id = 0;
			$winner_count = 0;
			foreach ($candidates as $eg_id => $counter)
			{
				if ($counter > $winner_count)
				{
					$winner_count = $counter;
					$winner_id = $eg_id;
				}
			}
			if ($winner_id > 0)
			{
				$ref->candidate_id = $winner_id;
			}
		}
		
		$players_list = '';
		$delim = '';
		foreach ($players as $emo_id => $player)
		{
			if ($emo_id > 0)
			{
				$players_list .= $delim . $emo_id;
				$delim = ',';
			}
		}
		
		$query = new DbQuery('SELECT u.emo_id, u.emo_name, u.id, n.name, u.flags FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.emo_id IN (' . $players_list . ')');
		while ($row = $query->next())
		{
			list ($emo_id, $emo_name, $id, $name, $flags) = $row;
			$players[$emo_id]->id = $id;
			$players[$emo_id]->name = $name;
			$players[$emo_id]->flags = $flags;
			$players[$emo_id]->old_emo_name = $emo_name;
		}
		return $players;
	}
	
	function import_games_op()
	{
		set_time_limit(180);
		
		$tournament_id = (int)get_required_param('tournament_id');
		$emo_id = (int)get_required_param('emo_id');
		$overwrite = (int)get_optional_param('overwrite', 0);
		if ($emo_id <= 0)
		{
			$emo_id = NULL;
		}
		
		Db::begin();
		list ($club_id, $old_emo_id, $address_id, $start, $duration, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, $ops, $rules_code, $tournament_flags, $misc) = 
			Db::record(get_label('tournament'), 'SELECT club_id, emo_id, address_id, start_time, duration, notes, langs, fee, currency_id, scoring_id, scoring_version, scoring_options, rules, flags, misc FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		$rules_code = check_rules_code($rules_code);
		if (is_null($misc))
		{
			$misc = new stdClass();
		}
		else
		{
			$misc = json_decode($misc);
		}
		
		if ($emo_id != $old_emo_id)
		{
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET emo_id = ? WHERE id = ?', $emo_id, $tournament_id);
			$log_details = new stdClass();
			$log_details->emo_id = $emo_id;
			db_log(LOG_OBJECT_TOURNAMENT, 'changed`', $log_details, $tournament_id, $club_id);
		}
		
		$old_players = array();
		if (isset($misc->emo) && isset($misc->emo->players))
		{
			foreach ($misc->emo->players as $p)
			{
				if (isset($p->emo_id))
				{
					$old_players[$p->emo_id] = $p;
				}					
			}
		}
		
		$content = post('https://api.emotion.games/aml/get/tournament-games', '{"hash":"'.EMO_API_KEY.'","tid":'.$emo_id.'}');
		$players = $this->get_players_mapping($content, $old_players);
		
		// Save plyers
		$misc->emo = new stdClass();
		$misc->emo->players = array();
		foreach ($players as $emo_user_id => $player)
		{
			$misc->emo->players[] = $player;
		}
		usort($misc->emo->players, 'compare_players');
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET misc = ? WHERE id = ?', json_encode($misc), $tournament_id);

		$ready_for_import = true;
		foreach ($players as $emo_user_id => $player)
		{
			if (!isset($player->id) && !isset($player->candidate_id))
			{
				$ready_for_import = false;
			}
			else if (!empty($player->emo_name) && isset($player->old_emo_name) && $player->emo_name != $player->old_emo_name)
			{
				Db::exec(get_label('user'), 'UPDATE users SET emo_name = ? WHERE id = ?', $player->emo_name, $player->id);
			}
		}
		
		if ($ready_for_import)
		{
			$emo_games = (array)$content->body->games;
			ksort($emo_games);
			
			$games_count = count($emo_games);
			if ($games_count <= 0)
			{
				throw new Exc(get_label('No games received from [0]', 'emotion.games'));
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
			
			$main_round_id = 0;
			$final_round_id = 0;
			$query = new DbQuery('SELECT id, round FROM events WHERE tournament_id = ?', $tournament_id);
			while ($row = $query->next())
			{
				list ($round_id, $round_num) = $row;
				if ($round_num == 0)
				{
					$main_round_id = (int)$round_id;
				}
				else if ($round_num == 1)
				{
					$final_round_id = (int)$round_id;
				}
			}
			
			if ($final_round_id <= 0)
			{
				foreach ($emo_games as $game_num => $game)
				{
					if ($game->is_final)
					{
						$round_ops = new stdClass();
						if (isset($ops->flags))
						{
							$round_ops->flags = $ops->flags;
						}
						$round_ops->weight = 1.3;
						$round_ops->group = 'final';
						$final_round_id = create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($round_ops), $tournament_id, $rules_code, 1, $tournament_flags);
						break;
					}
				}
			}
			
			if ($main_round_id <= 0)
			{
				$round_ops = new stdClass();
				if (isset($ops->flags))
				{
					$round_ops->flags = $ops->flags;
				}
				$round_ops->group = 'main';
				$main_round_id = create_tournament_round($address_id, $club_id, $start, $end, $notes, $langs, $fee, $currency_id, $scoring_id, $scoring_version, json_encode($round_ops), $tournament_id, $rules_code, 0, $tournament_flags);
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
			foreach ($emo_games as $game_num => $game)
			{
				switch ($game->result)
				{
				case 'red':
					$winner = 'civ';
					break;
				case 'black':
					$winner = 'maf';
					break;
				case 'draw':
					$winner = 'tie';
					break;
				default:
					continue;
				}
				
				$event_id = $game->is_final ? $final_round_id : $main_round_id;
				if (array_key_exists($event_id . '-' . $game->table_number . '-' . $game->session_number, $existing_games))
				{
					continue;
				}
				
				if ($game_number != $game->session_number)
				{
					$game_start += 3600;
					$game_number = $game->session_number;
				}
				else
				{
					$game_start += 10;
				}
				
				$g = new stdClass();
				$g->clubId = (int)$club_id;
				$g->eventId = $event_id;
				$g->tableNum = (int)$game->table_number;
				$g->gameNum = (int)$game->session_number;
				$g->language = $lang;
				$g->rules = $rules_code;
				$g->features = 'ldut';
				$g->comment = $game->comment;
				$g->tournamentId = (int)$tournament_id;
				
				$g->moderator = new stdClass();
				if (isset($game->referee_eg_id) && $game->referee_eg_id > 0)
				{
					$ref_emo_id = $game->referee_eg_id;
				}
				else
				{
					$ref_emo_id = -$g->tableNum;
					if ($game->is_final)
					{
						$ref_emo_id -= MAX_TABLES;
					}
					$mapping = $players[$ref_emo_id];
					if (!isset($mapping->id))
					{
						$ref_emo_id = $mapping->candidate_id;
					}
				}
				$mapping = $players[$ref_emo_id];
				$g->moderator->id = $mapping->id;
				$g->moderator->name = $mapping->name;
				
				$g->startTime = $game_start;
				$g->endTime = $g->startTime + 2100;
				$g->winner = $winner;
				
				$g->players = array(
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass(), 
					new stdClass(), new stdClass(), new stdClass(), new stdClass(), new stdClass());
				foreach ($game->players as $num => $player)
				{
					$p = $g->players[$num];
					if (array_key_exists($player->eg_id, $players))
					{
						$mapping = $players[$player->eg_id];
						$p->id = $mapping->id;
						$p->name = $mapping->name;
					}
					switch ($player->role)
					{
						case 'sheriff':
							$p->role = 'sheriff';
							break;
						case 'black':
							$p->role = 'maf';
							break;
						case 'don':
							$p->role = 'don';
							break;
					}
					$bonus = (float)$player->points_plus - (float)$player->points_minus;
					if (abs($bonus) > 0.001)
					{
						$p->bonus = $bonus;
					}
					if (isset($player->killed_first) && $player->killed_first)
					{
						$p->death = new stdClass();
						$p->death->type = 'night';
						$p->death->round = 1;
						$p->legacy = $player->best_move;
						if (isset($game->best_move_numbers))
						{
							$legacy = array();
							foreach ($game->best_move_numbers as $n => $m)
							{
								if ($m > 0 && $m <= 10)
								{
									$legacy[] = $m;
								}
							}
							if (count($legacy) > 0)
							{
								$p->legacy = $legacy;
							}
						}
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
		$emo_id = (int)get_required_param('emo_id');
		$user_id = (int)get_required_param('user_id');
		
		Db::begin();
		list ($club_id, $misc) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.misc FROM tournaments t WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if ($user_id > 0)
		{
			list ($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT n.name, u.flags FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		}
		
		$misc = json_decode($misc);
		if (isset($misc->emo) && isset($misc->emo->players))
		{
			foreach ($misc->emo->players as $player)
			{
				if ($player->emo_id == $emo_id)
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
					usort($misc->emo->players, 'compare_players');
					break;
				}
			}
		}
		$misc = json_encode($misc);
		
		Db::exec(get_label('user'), 'UPDATE users u SET emo_id = NULL, emo_name = "" WHERE emo_id = ?', $emo_id);
		if ($user_id > 0)
		{
			Db::exec(get_label('user'), 'UPDATE users u SET emo_id = ?, emo_name = ? WHERE id = ?', $emo_id, $player->emo_name, $user_id);
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
			if (isset($misc->emo))
			{
				unset($misc->emo);
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
$page->run('Emotion.games Operations', CURRENT_VERSION);

?>