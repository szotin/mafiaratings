<?php

require_once '../../include/api.php';
require_once '../../include/mwt.php';
require_once '../../include/mwt_game.php';
require_once '../../include/tournament.php';

define('CURRENT_VERSION', 0);

function post($url, $headers = NULL, $params = NULL)
{
	global $_mwt_site;
	
	$url = $_mwt_site . $url;
	$real_headers = array('Content-type: application/x-www-form-urlencoded');
	if (isset($_SESSION['mwt_token']))
	{
		$real_headers[] = 'Authorization: Bearer ' . $_SESSION['mwt_token'];
	}
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
		throw new Exc(get_label('Unable to connect to [0].', $_mwt_site));
	}
	
	$data = json_decode($result);
	if (is_null($result))
	{
		throw new Exc(get_label('Invalid response from [0]: [1]', $_mwt_site, $result));
	}
	return $data;
}

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// sign_on
	//-------------------------------------------------------------------------------------------------------
	function sign_on_op()
	{
		$email = get_required_param('email');
		$password = get_required_param('password');
		
		if (isset($_SESSION['mwt_token']))
		{
			unset($_SESSION['mwt_token']);
		}

		$data = post('/api/auth/login?email=' . urlencode($email) . '&password=' . urlencode($password));

		if (isset($data->error))
		{
			throw new Exc($data->error);
		}
		
		if (!isset($data->success))
		{
			throw new Exc(get_label('Invalid response from [0]: [1]', $_mwt_site, formatted_json($data)));
		}
		$data = $data->success;
		
		$_SESSION['mwt_token'] = $this->response['token'] = $data->token;
		if (isset($data->nickname))
		{
			$this->response['nickname'] = $data->nickname;
		}
		if (isset($data->id))
		{
			$this->response['id'] = $data->id;
		}
		if (isset($data->avatar))
		{
			$this->response['avatar'] = $data->avatar;
		}
	}
	
	// function sign_on_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// import_schema
	//-------------------------------------------------------------------------------------------------------
	function import_schema_op()
	{
		$tournament_id = (int)get_required_param('tournament_id');
		list ($club_id, $mwt_id) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.mwt_id FROM tournaments t WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (!isset($_SESSION['mwt_token']))
		{
			$this->response['login_needed'] = true;
			return;
		}
		
		if (is_null($mwt_id))
		{
			throw new Exc(get_label('MWT ID is not set for the tournament.'));
		}
		
		$data = post('/api/tournament/seats?tournament_id=' . $mwt_id);
		if (isset($data->error))
		{
			if ($data->error == 'Unauthenticated.')
			{
				$this->response['login_needed'] = true;
				return;
			}
			throw new Exc($data->error);
		}
		
		if (!isset($data->success))
		{
			throw new Exc(get_label('Invalid response from [0]: [1]', $_mwt_site, formatted_json($data)));
		}
		$data = $data->success;
		//throw new Exc(formatted_json($data));
		
		// Collect all parts with the same number to the array.
		// Every part is assumed to be a tournament round like main, semifinals, finals, etc.
		// An index in this array is part_number-1, a value - is an array of integers.
		// Every integer in this array is number of games in this table in this round/part.
		$parts = array();
		foreach ($data as $game)
		{
			while ($game->part_number > count($parts))
			{
				$parts[] = NULL;
			}
			
			$part = $parts[$game->part_number - 1];
			if (is_null($parts[$game->part_number - 1]))
			{
				$parts[$game->part_number - 1] = array();
			}
			
			while ($game->table_number > count($parts[$game->part_number - 1]))
			{
				$parts[$game->part_number - 1][] = 0;
			}
			++$parts[$game->part_number - 1][$game->table_number - 1];
		}
		//throw new Exc(formatted_json($parts));
		
		// Some parts/rounds have only one game
		// This code merges these parts with the previous ones making sure there is no tournament rounds with only one set.
		$rounds = array();
		for ($p = 0; $p < count($parts); ++$p)
		{
			$part = $parts[$p];
			if ($part == NULL)
			{
				continue;
			}
			
			$one_game = false;
			if (count($rounds) > 0 && count($rounds[count($rounds) - 1]) == count($part))
			{
				$one_game = true;
				foreach ($part as $table)
				{
					if ($table > 1)
					{
						$one_game = false;
						break;
					}
				}
			}
			
			if ($one_game)
			{
				for ($i = 0; $i < count($part); ++$i)
				{
					$rounds[count($rounds) - 1][$i][] = $p + 1;
				}
			}
			else
			{
				$rounds[] = array();
				foreach ($part as $table)
				{
					$merge = array();
					for ($i = 0; $i < $table; ++$i)
					{
						$merge[] = $p + 1;
					}
					$rounds[count($rounds) - 1][] = $merge;
				}
			}
		}
		//throw new Exc(formatted_json($rounds));
		
		// If after merging parts some parts still have only one round, merge them too.
		// This can happen if they have different number of tables. 
		// For example 23 players are playing 10 games each: 11 rounds with 2 tables and 1 round with 1 table.
		$current_round = 0;
		for ($i = 1; $i < count($rounds); ++$i)
		{
			$round = $rounds[$i];
			if ($round == NULL)
			{
				break;
			}
			
			$one_game = true;
			foreach ($round as $table)
			{
				if (count($table) > 1)
				{
					$one_game = false;
					break;
				}
			}
			
			if ($one_game)
			{
				for ($j = 0; $j < count($round); ++$j)
				{
					while ($j >= count($rounds[$current_round]))
					{
						$rounds[$current_round][] = array();
					}
					foreach ($table as $game)
					{
						$rounds[$current_round][$j][] = $game;
					}
				}
				for ($j = $i + 1; $j < count($rounds); ++$j)
				{
					$rounds[$j-1] = $rounds[$j];
				}
				$rounds[count($rounds)-1] = NULL;
			}
			else
			{
				++$current_round;
			}
		}
		if ($i != count($rounds))
		{
			$rounds = array_slice($rounds, 0, $i);
		}
		
		// Save them to db rounds
		Db::begin();
		$round_num = 0;
		list($address_id, $club_id, $start, $duration, $langs, $scoring_id, $scoring_version, $tournament_scoring_options, $rules_code) = Db::record(get_label('tournament'), 'SELECT address_id, club_id, start_time, duration, langs, scoring_id, scoring_version, scoring_options, rules FROM tournaments WHERE id = ?', $tournament_id);
		$tournament_scoring_options = json_decode($tournament_scoring_options);
		Db::exec(get_label('round'), 'UPDATE events SET misc = NULL WHERE tournament_id = ?', $tournament_id);
		for ($i = 0; $i < count($rounds); ++$i)
		{
			$event_misc = new stdClass();
			$event_misc->mwt_schema = $rounds[$i];
			$event_misc->seating = array();
			foreach ($event_misc->mwt_schema as $table)
			{
				$t = array();
				foreach ($table as $game)
				{
					$t[] = NULL;
				}
				$event_misc->seating[] = $t;
			}
			$event_misc = json_encode($event_misc);
			
			$query = new DbQuery('SELECT id FROM events WHERE tournament_id = ? AND round = ? ORDER BY id LIMIT 1', $tournament_id, $round_num);
			if ($row = $query->next())
			{
				list($event_id) = $row;
				Db::exec(get_label('user'), 'DELETE FROM event_users WHERE event_id = ?', $event_id);
				Db::exec(get_label('round'), 'UPDATE events SET misc = ? WHERE id = ?', $event_misc, $event_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->misc = $event_misc;
					db_log(LOG_OBJECT_EVENT, 'changed', $log_details, $event_id, $club_id);
				}
			}
			else
			{
				$event_name = get_round_name($round_num);
				$scoring_options = clone $tournament_scoring_options;
				if ($round_num == 1)
				{
					$scoring_options->weight = 1.3;
					$scoring_options->group = 'final';
				}
				else
				{
					$scoring_options->group = 'main';
				}
				$scoring_options = json_encode($scoring_options);
				
				$flags = EVENT_MASK_HIDDEN | EVENT_FLAG_ALL_CAN_REFEREE;
				Db::exec(
					get_label('round'), 
					'INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_version, scoring_options, tournament_id, rules, round, misc) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
					$event_name, $address_id, $club_id, $start, $duration, $flags, $langs, $scoring_id, $scoring_version, $scoring_options, $tournament_id, $rules_code, $round_num, $event_misc);
					
				$log_details = new stdClass();
				$log_details->name = $event_name;
				$log_details->tournament_id = $tournament_id;
				$log_details->club_id = $club_id; 
				$log_details->address_id = $address_id; 
				$log_details->start = $start;
				$log_details->duration = $duration;
				$log_details->langs = $langs;
				$log_details->scoring_id = $scoring_id;
				$log_details->scoring_version = $scoring_version;
				$log_details->scoring_options = $scoring_options;
				$log_details->rules_code = $rules_code;
				$log_details->flags = $flags;
				$log_details->round_num = $round_num;
				db_log(LOG_OBJECT_EVENT, 'round created', $log_details, $tournament_id, $club_id);
			}
			$round_num = count($rounds) - $i - 1;
		}
		Db::exec(get_label('user'), 'DELETE FROM tournament_users WHERE tournament_id = ? AND team_id IS NULL', $tournament_id);
		Db::commit();
		
		$this->response['rounds'] = $rounds;
	}
	
	// function import_schema_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// import_seating
	//-------------------------------------------------------------------------------------------------------
	function import_seating_op()
	{
		global $_lang;
		
		$tournament_id = (int)get_required_param('tournament_id');
		
		Db::begin();
		list ($tournament_id, $club_id, $mwt_id, $tournament_misc) = Db::record(get_label('tournament'), 'SELECT id, club_id, mwt_id, misc FROM tournaments WHERE id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		if (is_null($mwt_id))
		{
			throw new Exc(get_label('MWT ID is not set for the tournament.'));
		}
		if (is_null($tournament_misc))
		{
			$tournament_misc = new stdClass();
		}
		else
		{
			$tournament_misc = json_decode($tournament_misc);
		}
		if (!isset($tournament_misc->mwt_players))
		{
			$tournament_misc->mwt_players = array();
		}
		
		$round_id = 0;
		$misc = NULL;
		$langs = 0;
		$progress = 0;
		$total = 0;
		$table_num = -1;
		$game_num = -1;
		$mwt_players_changed = false;
		
		$query = new DbQuery('SELECT id, misc, languages FROM events WHERE tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list($event_id, $event_misc, $event_langs) = $row;
			if (is_null($event_misc))
			{
				continue;
			}
			
			$event_misc = json_decode($event_misc);
			if (!isset($event_misc->mwt_schema) || !isset($event_misc->seating))
			{
				continue;
			}
			
			for ($t = 0; $t < count($event_misc->seating); ++$t)
			{
				$table = $event_misc->seating[$t];
				if (is_null($table))
				{
					continue;
				}
				for ($g = 0; $g < count($table); ++$g)
				{
					$game = $table[$g];
					++$total;
					if (!is_null($game))
					{
						++$progress;
					}
					else if (is_null($misc))
					{
						$round_id = $event_id;
						$misc = $event_misc;
						$langs = $event_langs;
						$table_num = $t;
						$game_num = $g;
					}
				}
			}
		}
		
		if (!is_null($misc) && $table_num >= 0 && $progress < $total)
		{
			$part_num = $misc->mwt_schema[$table_num][$game_num];
			if ($game_num > 0 && $part_num != $misc->mwt_schema[$table_num][$game_num-1])
			{
				$session_num = 1;
			}
			else
			{
				$session_num = $game_num + 1;
			}
			$url = '/api/protocol/load?tournament_id='.$mwt_id.'&part_number='.$part_num.'&table_number='.($table_num+1).'&session_number='.$session_num;
			$data = post($url);
			if (isset($data->error))
			{
				if ($data->error == 'Unauthenticated.')
				{
					$this->response['login_needed'] = true;
					return;
				}
				throw new Exc($data->error);
			}
			
			if (!isset($data->success))
			{
				throw new Exc(get_label('Invalid response from [0]: [1]', $url, formatted_json($data)));
			}
			$data = $data->success;
			
			if (!isset($data->protocol) || !isset($data->referee) || count($data->protocol) <= 0)
			{
				throw new Exc(get_label('Invalid response from [0]: [1]', $url, formatted_json($data)));
			}
			
			$players = array();
			foreach ($data->protocol as $player)
			{
				$player_id = 0;
				if (is_null($player->user_id))
				{
					foreach ($tournament_misc->mwt_players as $mwt_player)
					{
						if ($mwt_player->id < 0 && $mwt_player->name == $player->nickname)
						{
							$player_id = $mwt_player->id;
							break;
						}
					}
				}
				else
				{
					foreach ($tournament_misc->mwt_players as $mwt_player)
					{
						if ($mwt_player->mwt_id == $player->user_id)
						{
							$player_id = $mwt_player->id;
							break;
						}
					}
				}
				
				if ($player_id == 0)
				{
					$player_id = - 1 - count($tournament_misc->mwt_players);
					if (!is_null($player->user_id))
					{
						$query = new DbQuery('SELECT id FROM users WHERE mwt_id = ?', $player->user_id);
						if ($row = $query->next())
						{
							$player_id = (int)$row[0];
						}
					}
					
					$new_player = new stdClass();
					$new_player->mwt_id = $player->user_id;
					$new_player->name = $player->nickname;
					$new_player->id = $player_id;
					$tournament_misc->mwt_players[] = $new_player;
					$mwt_players_changed = true;
				}
				
				if ($player_id > 0)
				{
					if (is_valid_lang($langs))
					{
						$lang = $langs;
					}
					else
					{
						$lang = $_lang;
					}
					
					list ($user_name, $user_mwt_name) = Db::record(get_label('user'), 
						'SELECT nu.name, u.mwt_name'.
						' FROM users u'.
						' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$lang.') <> 0'.
						' WHERE u.id = ?', $player_id);
					
					if ($user_mwt_name != $player->nickname)
					{
						Db::exec(get_label('user'), 'UPDATE users SET mwt_name = ? WHERE id = ?', $player->nickname, $player_id);
					}
					
					Db::exec(get_label('registration'), 'INSERT IGNORE INTO event_users (event_id, user_id, nickname) VALUES (?, ?, ?)', $round_id, $player_id, $user_name);
					Db::exec(get_label('registration'), 'INSERT IGNORE INTO tournament_users (tournament_id, user_id, flags) VALUES (?, ?, '.USER_TOURNAMENT_NEW_PLAYER_FLAGS.')', $tournament_id, $player_id);
				}
				$players[] = $player_id;
			}
			$misc->seating[$table_num][$game_num] = $players;
			Db::exec(get_label('round'), 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $round_id);
			++$progress;
		}
		
		if ($mwt_players_changed)
		{
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET misc = ? WHERE id = ?', json_encode($tournament_misc), $tournament_id);
		}
		Db::commit();
		
		$this->response['total'] = $total;
		$this->response['progress'] = $progress;
	}
	
	// function import_seating_op_help()
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
		
		$user_id = (int)get_required_param('user_id');
		$player_id = (int)get_required_param('player_id');
		$tournament_id = (int)get_required_param('tournament_id');
		$mwt_user_name = get_optional_param('mwt_name', NULL); 
		
		list ($club_id, $mwt_id, $tournament_misc, $lang) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.mwt_id, t.misc, t.langs FROM tournaments t WHERE t.id = ?', $tournament_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
		}
		list ($user_name, $old_mwt_user_name) = Db::record(get_label('user'), 
			'SELECT nu.name, u.mwt_name'.
			' FROM users u'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$lang.') <> 0'.
			' WHERE u.id = ?', $user_id);
				
		if (is_null($tournament_misc))
		{
			throw new Exc(get_label('Players mapping is not set for the tournament.'));
		}
		$tournament_misc = json_decode($tournament_misc);
		if (!isset($tournament_misc->mwt_players))
		{
			throw new Exc(get_label('Players mapping is not set for the tournament.'));
		}
		
		Db::begin();
		$events = array();
		$query = new DbQuery('SELECT id, misc FROM events WHERE tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list($event_id, $misc) = $row;
			if (is_null($misc))
			{
				continue;
			}
			
			$misc = json_decode($misc);
			
			$event = new stdClass();
			$event->id = $event_id;
			$event->misc = $misc;
			$events[] = $event;
		}
		
		$mwt_id = -1;
		$found = false;
		foreach ($tournament_misc->mwt_players as $player)
		{
			if ($player->id == $player_id)
			{
				$found = true;
				$mwt_id = $player->mwt_id;
				$player->id = $user_id;
				break;
			}
		}
		if (!$found)
		{
			throw new Exc(get_label('Player [0] not found in this tournament', $player_id));
		}
		
		foreach ($events as $event)
		{
			for ($i = 0; $i < count($event->misc->seating); ++$i)
			{
				$table = $event->misc->seating[$i];
				for ($j = 0; $j < count($table); ++$j)
				{
					$game = $table[$j];
					for ($k = 0; $k < count($game); ++$k)
					{
						if ($game[$k] == $player_id)
						{
							$new_game = array();
							for ($l = 0; $l < count($game); ++$l)
							{
								if ($game[$l] == $player_id)
								{
									$new_game[] = $user_id;
								}
								else
								{
									$new_game[] = $game[$l];
								}
							}
							$event->misc->seating[$i][$j] = $new_game;
							break;
						}
					}
				}
			}
			
			Db::exec(get_label('round'), 'UPDATE events SET misc = ? WHERE id = ?', json_encode($event->misc), $event->id);
			Db::exec(get_label('registration'), 'INSERT IGNORE INTO event_users (event_id, user_id, nickname) VALUES (?, ?, ?)', $event->id, $user_id, $user_name);
		}

		Db::exec(get_label('registration'), 'INSERT IGNORE INTO tournament_users (tournament_id, user_id, flags) VALUES (?, ?, ?)', $tournament_id, $user_id, USER_TOURNAMENT_NEW_PLAYER_FLAGS);
		Db::exec(get_label('round'), 'UPDATE tournaments SET misc = ? WHERE id = ?', json_encode($tournament_misc), $tournament_id);

		if (!is_null($mwt_id) && $mwt_id > 0)
		{
			Db::exec(get_label('user'), 'UPDATE users SET mwt_id = NULL WHERE mwt_id = ?', $mwt_id);
			if (!is_null($mwt_user_name) && $mwt_user_name != $old_mwt_user_name)
			{
				Db::exec(get_label('user'), 'UPDATE users SET mwt_id = ?, mwt_name = ? WHERE id = ?', $mwt_id, $mwt_user_name, $user_id);
			}
			else
			{
				Db::exec(get_label('user'), 'UPDATE users SET mwt_id = ? WHERE id = ?', $mwt_id, $user_id);
			}
		}
		Db::commit();
	}
	
	// function map_player_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// export_game
	//-------------------------------------------------------------------------------------------------------
	private function export_game($game_id)
	{
		$mwt_game = convert_game_to_mwt($game_id);
		throw new Exc(formatted_json($mwt_game));
		Db::exec(get_label('game'), 'UPDATE games SET is_fiim_exported = 1 WHERE id = ?', $game_id);
	}
	
	function export_game_op()
	{
		$game_id = (int)get_optional_param('game_id', 0);
		
		$total = 1;
		$progress = 1;
		if ($game_id > 0)
		{
			$this->export_game($game_id);
		}
		else
		{
			$tournament_id = (int)get_required_param('tournament_id');
			
			list ($total) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ? AND is_canceled = 0 AND is_rating <> 0 AND result > 0 AND game_table IS NOT NULL AND game_number IS NOT NULL', $tournament_id);
			$total = (int)$total;
			
			list ($progress) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE tournament_id = ? AND is_canceled = 0 AND is_rating <> 0 AND result > 0 AND game_table IS NOT NULL AND game_number IS NOT NULL AND is_fiim_exported <> 0', $tournament_id);
			$progress = (int)$progress;
			
			if ($progress < $total)
			{
				list ($game_id) = Db::record(get_label('game'), 'SELECT id FROM games WHERE tournament_id = ? AND is_canceled = 0 AND is_rating <> 0 AND result > 0 AND is_fiim_exported = 0 LIMIT 1', $tournament_id);
				$this->export_game($game_id);
				++$progress;
			}
		}
		
		$this->response['total'] = $total;
		$this->response['progress'] = $progress;
	}
	
	// function export_game_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Finish the tournament. After finishing the tournament within one hour players will get all series points for this tournament. Finish tournament functionality lets not to wait until the time expires and get the results quicker.');
		// return $help;
	// }
}

$page = new ApiPage();
$page->run('MWT Operations', CURRENT_VERSION);

?>