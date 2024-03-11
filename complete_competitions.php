<?php

define('TOURNAMENT_CREDIT_GAMES_PERCENT', 50);

setDir();
require_once 'include/security.php';
require_once 'include/scoring.php';

$_web = isset($_SERVER['HTTP_HOST']);
$_filename = 'complete_competitions.log';
$_file = NULL;
 
if ($_web)
{
	if (isset($_REQUEST['no_log']))
	{
		$_filename = NULL;
	}
	define('MAX_EXEC_TIME', 25);
}
else
{
	define('MAX_EXEC_TIME', 180); // 3 minutes
}

function writeLog($str)
{
	global $_web, $_file, $_filename;
	if ($_web)
	{
		echo $str . " <br>\n";
	}
	
	if ($_filename)
	{
		if (is_null($_file))
		{
			$_file = fopen($_filename, 'a');
			fwrite($_file, '------ ' . date('F d, Y H:i:s', time()) . "\n");
		}
		fwrite($_file, $str . "\n");
	}
}

function setDir()
{
	// Set the current working directory to the directory of the script.
	// This script is sometimes called from the other directories - for auto sending, so we need to change the directory
	$pos = strrpos(__FILE__, '/');
	if ($pos === false)
	{
		$pos = strrpos(__FILE__, '\\');
		if ($pos === false)
		{
			return;
		}
	}
	$dir = substr(__FILE__, 0, $pos);
	chdir($dir);
}

function compare_series_players($player1, $player2)
{
	if ($player1->points < $player2->points)
	{
		return 1;
	}
	if ($player1->points > $player2->points)
	{
		return -1;
	}
	if ($player1->tournaments > $player2->tournaments)
	{
		return 1;
	}
	if ($player1->tournaments < $player2->tournaments)
	{
		return -1;
	}
	if ($player1->games > 0 && $player1->games > 0)
	{
		$win_rate1 = $player1->wins / $player1->games;
		$win_rate2 = $player2->wins / $player2->games;
		if ($win_rate1 + 0.00001 < $win_rate2)
		{
			return 1;
		}
		else if ($win_rate2 + 0.00001 < $win_rate1)
		{
			return -1;
		}
	}
	return $player1->id - $player2->id;
}

function complete_event()
{
	$result = false;
	Db::begin();
	$query = new DbQuery(
		'SELECT e.id, sv.scoring, e.scoring_options, e.flags, t.flags, e.round FROM events e'.
		' JOIN scoring_versions sv ON sv.scoring_id = e.scoring_id AND sv.version = e.scoring_version'.
		' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
		' WHERE e.start_time + e.duration < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_FINISHED . ') = 0 LIMIT 1');
	if ($row = $query->next())
	{
		list($event_id, $scoring, $scoring_options, $flags, $tournament_flags, $round_num) = $row;
		$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK); // no hiding tables/bonuses any more - event is complete
		
		$players_count = 0;
		Db::exec(get_label('event'), 'DELETE FROM event_places WHERE event_id = ?', $event_id);
		if (($flags & EVENT_FLAG_CANCELED) == 0)
		{
			$scoring = json_decode($scoring);
			$scoring_options = json_decode($scoring_options);
			
			
			$players = event_scores($event_id, null, SCORING_LOD_PER_GROUP, $scoring, $scoring_options, $tournament_flags, $round_num);
			$players_count = count($players);
			if ($players_count > 0)
			{
				$coeff = log10($players_count) / $players_count;
				for ($number = 0; $number < $players_count; ++$number)
				{
					$player = $players[$number];
					$importance = ($players_count - $number) * $coeff;
					if ($number == 0)
					{
						$importance *= 10;
					}
					else if ($number <= 3)
					{
						$importance *= 5;
					}
					else if ($number <= 10)
					{
						$importance *= 2;
					}
					$main_points = $player->main_points;
					$bonus_points = $player->extra_points + $player->legacy_points + $player->penalty_points;
					$shot_points = $player->night1_points;
					Db::exec(get_label('player'), 'INSERT INTO event_places (event_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $event_id, $player->id, $number + 1, $importance, $main_points, $bonus_points, $shot_points, $player->night1_points, $player->games_count);
				}
			}
		}
		Db::exec(get_label('event'), 'UPDATE events SET flags = flags | ' . EVENT_FLAG_FINISHED .  ' WHERE id = ?', $event_id);
		writeLog('Wrote ' . $players_count . ' players to event ' . $event_id . '. Event is finished.');
		$result = true;
	}
	Db::commit();
	return $result;
}

function get_tournament_importance($stars, $place, $players_count)
{
	if ($players_count <= 0)
	{
		return 0;
	}
	$coeff = log10($players_count) * $stars / $players_count;
	$importance = ($players_count - $place + 1) * $coeff;
	if ($place == 1)
	{
		$importance *= 10;
	}
	else if ($place <= 3)
	{
		$importance *= 5;
	}
	else if ($place <= 10)
	{
		$importance *= 2;
	}
	return $importance;
}
	
function complete_tournament()
{
	$result = false;
	Db::begin();
	
	$query = new DbQuery(
		'SELECT t.id, t.flags, sv.scoring, t.scoring_options, nv.normalizer, (SELECT max(st.stars) FROM series_tournaments st WHERE st.tournament_id = t.id) as stars FROM tournaments t' . 
		' JOIN scoring_versions sv ON sv.scoring_id = t.scoring_id AND sv.version = t.scoring_version' .
		' LEFT OUTER JOIN normalizer_versions nv ON nv.normalizer_id = t.normalizer_id AND nv.version = t.normalizer_version' .
		' WHERE t.start_time + t.duration < UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_FINISHED . ') = 0 LIMIT 1');
	if ($row = $query->next())
	{
		list($tournament_id, $tournament_flags, $scoring, $scoring_options, $normalizer, $stars) = $row;
		$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK); // no hiding tables/bonuses any more - tournament is complete
		$real_count = $players_count = 0;
		$min_games = 0;
		if (($tournament_flags & TOURNAMENT_FLAG_CANCELED))
		{
			Db::exec(get_label('tournament'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
		}
		else if ($tournament_flags & TOURNAMENT_FLAG_MANUAL_SCORE)
		{
			$players = array();
			$query1 = new DbQuery('SELECT user_id, place FROM tournament_places WHERE tournament_id = ?', $tournament_id);
			while ($row1 = $query1->next())
			{
				$player = new stdClass();
				list($player->id, $player->place) = $row1;
				$players[] = $player;
			}
			$real_count = $players_count = count($players);
			
			$place = 1;
			foreach ($players as $player)
			{
				$importance = get_tournament_importance($stars, $place, $players_count);
				Db::exec(get_label('player'), 'UPDATE tournament_places SET importance = ? WHERE tournament_id = ? AND user_id = ?', $importance, $tournament_id, $player->id);
				++$place;
			}
		}
		else
		{
			if (is_null($stars))
			{
				$stars = 1;
			}
			$scoring = json_decode($scoring);
			$scoring_options = json_decode($scoring_options);
			if (!is_null($normalizer))
			{
				$normalizer = json_decode($normalizer);
			}
			
			$players = tournament_scores($tournament_id, $tournament_flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_PER_ROLE, $scoring, $normalizer, $scoring_options);
			$real_count = add_tournament_nominants($tournament_id, $players);
			$players_count = count($players);
			
			Db::exec(get_label('tournament'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
			$place = 1;
			for ($number = 0; $number < $players_count; ++$number)
			{
				$player = $players[$number];
				if ($player->credit)
				{
					$importance = get_tournament_importance($stars, $place, $real_count);
					$main_points = $player->main_points;
					$bonus_points = $player->extra_points + $player->legacy_points + $player->penalty_points;
					$shot_points = $player->night1_points;
					Db::exec(get_label('player'), 'INSERT INTO tournament_places (tournament_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count, flags, wins) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $tournament_id, $player->id, $place, $importance, $main_points, $bonus_points, $shot_points, $player->games_count, $player->nom_flags, $player->wins);
					++$place;
				}
			}
		}
			
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags | ' . TOURNAMENT_FLAG_FINISHED .  ' WHERE id = ?', $tournament_id);
		Db::exec(get_label('series'), 'UPDATE series s JOIN series_tournaments st ON st.series_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE st.tournament_id = ?', $tournament_id);
		
		$log_str = 'Wrote ' . $real_count;
		if ($real_count != $players_count)
		{
			$log_str .= ' (out of ' . $players_count . ')';
		}
		$log_str .= ' players to tournament ' . $tournament_id . '.';
		if ($min_games > 0)
		{
			$log_str .= ' Minimum games requered for a player is ' . $min_games . '.';
		}
		$log_str .= ' Tournament is finished.';
		writeLog($log_str);
		
		$result = true;
	}
	Db::commit();
	return $result;
}

function get_series_importance($place, $players_count)
{
	if ($players_count <= 0)
	{
		return 0;
	}
	$coeff = log10($players_count) * 4 / $players_count;
	$importance = ($players_count - $place + 1) * $coeff;
	if ($place == 1)
	{
		$importance *= 10;
	}
	else if ($place <= 3)
	{
		$importance *= 5;
	}
	else if ($place <= 10)
	{
		$importance *= 2;
	}
	return $importance;
}
	
function calculate_series()
{
	$result = false;
	$query = new DbQuery(
		'SELECT s.id, s.flags, g.gaining FROM series s' . 
		' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version' .
		' WHERE (s.flags & ' . SERIES_FLAG_DIRTY  . ') <> 0 LIMIT 1');
	if ($row = $query->next())
	{
		list($series_id, $series_flags, $gaining) = $row;
		$gaining = json_decode($gaining);
//		print_json($gaining);

		$tournaments = array();
		$query = new DbQuery('SELECT s.tournament_id, s.stars, count(t.user_id) FROM series_tournaments s JOIN tournament_places t ON t.tournament_id = s.tournament_id WHERE s.series_id = ? GROUP BY s.tournament_id', $series_id);
		while ($row = $query->next())
		{
			list($tournament_id, $stars, $players) = $row;
			$tournaments[$tournament_id] = create_gaining_table($gaining, $stars, $players, false);
		}
		
		$child_series = array();
		$query = new DbQuery('SELECT ss.child_id, ss.stars, count(sp.user_id) FROM series_series ss JOIN series_places sp ON sp.series_id = ss.child_id JOIN series s ON s.id = ss.child_id WHERE ss.parent_id = ? AND (s.flags & ' . (SERIES_SERIES_FLAG_NOT_PAYED | SERIES_FLAG_FINISHED) . ') = ' . SERIES_FLAG_FINISHED . ' GROUP BY ss.child_id', $series_id);
		while ($row = $query->next())
		{
			list($child_series_id, $stars, $players) = $row;
			$child_series[$child_series_id] = create_gaining_table($gaining, $stars, $players, true);
		}
		
		$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;
		$players = array();
		
		$query = new DbQuery(
			'SELECT t.tournament_id, p.user_id, p.place, p.games_count, p.wins'.
			' FROM tournament_places p'.
			' JOIN series_tournaments t ON t.tournament_id = p.tournament_id'.
			' WHERE t.series_id = ? AND (t.flags & ' . SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0', $series_id);
		while ($row = $query->next())
		{
			list($tournament_id, $player_id, $place, $games, $wins) = $row;
			if (!isset($players[$player_id]))
			{
				$player = new stdClass();
				$player->id = (int)$player_id;
				$player->tournaments = 0;
				$player->games = 0;
				$player->wins = 0;
				if ($max_tournaments > 0)
				{
					$player->p = array();
				}
				else
				{
					$player->points = 0;
				}
				$players[$player_id] = $player;
			}
			else
			{
				$player = $players[$player_id];
			}
			
			$points = get_gaining_points($tournaments[$tournament_id], $place);
			if ($max_tournaments > 0)
			{
				if (count($player->p) >= $max_tournaments)
				{
					$min_index = 0;
					for ($i = 1; $i < $max_tournaments; ++$i)
					{
						if ($player->p[$i] < $player->p[$min_index])
						{
							$min_index = $i;
						}
					}
					if ($player->p[$min_index] <= $points)
					{
						$player->p[$min_index] = $points;
					}
				}
				else
				{
					$player->p[] = $points;
				}
			}
			else
			{
				$player->points += get_gaining_points($tournaments[$tournament_id], $place);
			}
			++$player->tournaments;
			$player->games += $games;
			$player->wins += $wins;
		}
		
		$query = new DbQuery(
			'SELECT p.series_id, p.user_id, p.place, p.games, p.wins'.
			' FROM series_places p'.
			' JOIN series_series s ON s.child_id = p.series_id'.
			' WHERE s.parent_id = ? AND (s.flags & ' . (SERIES_SERIES_FLAG_NOT_PAYED | SERIES_FLAG_FINISHED) . ') = ' . SERIES_FLAG_FINISHED, $series_id);
		while ($row = $query->next())
		{
			list($child_series_id, $player_id, $place, $games, $wins) = $row;
			if (!isset($players[$player_id]))
			{
				$player = new stdClass();
				$player->id = (int)$player_id;
				$player->tournaments = 0;
				$player->games = 0;
				$player->wins = 0;
				if ($max_tournaments > 0)
				{
					$player->p = array();
				}
				else
				{
					$player->points = 0;
				}
				$players[$player_id] = $player;
			}
			else
			{
				$player = $players[$player_id];
			}
			
			$points = get_gaining_points($child_series[$child_series_id], $place);
			if ($max_tournaments > 0)
			{
				if (count($player->p) >= $max_tournaments)
				{
					$min_index = 0;
					for ($i = 1; $i < $max_tournaments; ++$i)
					{
						if ($player->p[$i] < $player->p[$min_index])
						{
							$min_index = $i;
						}
					}
					if ($player->p[$min_index] <= $points)
					{
						$player->p[$min_index] = $points;
					}
				}
				else
				{
					$player->p[] = $points;
				}
			}
			else
			{
				$player->points += get_gaining_points($child_series[$child_series_id], $place);
			}
			++$player->tournaments;
			$player->games += $games;
			$player->wins += $wins;
		}
		
		$query = new DbQuery('SELECT user_id, points FROM series_extra_points WHERE series_id = ?', $series_id);
		while ($row = $query->next())
		{
			list($player_id, $points) = $row;
			if (!isset($players[$player_id]))
			{
				$player = new stdClass();
				$player->id = (int)$player_id;
				$player->tournaments = 0;
				$player->games = 0;
				$player->wins = 0;
				if ($max_tournaments > 0)
				{
					$player->p = array();
				}
				else
				{
					$player->points = 0;
				}
				$players[$player_id] = $player;
			}
			else
			{
				$player = $players[$player_id];
			}
			
			if ($max_tournaments > 0)
			{
				if (count($player->p) >= $max_tournaments)
				{
					$min_index = 0;
					for ($i = 1; $i < $max_tournaments; ++$i)
					{
						if ($player->p[$i] < $player->p[$min_index])
						{
							$min_index = $i;
						}
					}
					if ($player->p[$min_index] <= $points)
					{
						$player->p[$min_index] = $points;
					}
				}
				else
				{
					$player->p[] = $points;
				}
			}
			else
			{
				$player->points += $points;
			}
			++$player->tournaments;
		}
		
		if ($max_tournaments > 0)
		{
			foreach ($players as $player)
			{
				$player->points = 0;
				foreach ($player->p as $p)
				{
					$player->points += $p;
				}
				unset($player->p);
			}
		}
		
		usort($players, "compare_series_players");
		
		Db::exec(get_label('series'), 'DELETE FROM series_places WHERE series_id = ?', $series_id);
		$place = 1;
		$players_count = count($players);
		foreach ($players as $player)
		{
			$importance = get_series_importance($place, $players_count);
			Db::exec(get_label('series'), 'INSERT INTO series_places (series_id, user_id, place, importance, score, tournaments, games, wins) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', 
				$series_id, $player->id, $place, $importance, $player->points, $player->tournaments, $player->games, $player->wins);
			++$place;
		}
		
		Db::exec(get_label('series'), 'UPDATE series SET flags = flags & ' . ~SERIES_FLAG_DIRTY .  ' WHERE id = ?', $series_id);
		if ($series_flags & SERIES_FLAG_FINISHED)
		{
			Db::exec(get_label('series'), 'UPDATE series s JOIN series_series ss ON ss.parent_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE ss.child_id = ?', $series_id);
		}
		
		$log_str = 'Wrote ' . $players_count;
		$log_str .= ' players to series ' . $series_id . '.';
		$log_str .= ' Series is up to date.';
		writeLog($log_str);
		return true;
	}
	return false;
}

function complete_series()
{
	$result = false;
	Db::begin();
	$query = new DbQuery(
		'SELECT s.id FROM series s WHERE s.start_time + s.duration < UNIX_TIMESTAMP() AND (s.flags & ' . SERIES_FLAG_FINISHED . ') = 0 LIMIT 1');
	if ($row = $query->next())
	{
		list($series_id) = $row;
		Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_FINISHED .  ' WHERE id = ?', $series_id);
		Db::exec(get_label('series'), 'UPDATE series s JOIN series_series ss ON ss.parent_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE ss.child_id = ?', $series_id);
		writeLog('Series ' . $series_id . ' is finished.');
		$result = true;
	}
	Db::commit();
	return $result;
}

try
{
	date_default_timezone_set('America/Vancouver');
	if ($_web)
	{
		initiate_session();
		check_permissions(PERMISSION_ADMIN);
	}
	
	$exec_start_time = time();
	$spent_time = 0;
	$count = 0;
	while ($spent_time < MAX_EXEC_TIME)
	{
		if (!complete_event() && !complete_tournament() && !calculate_series() && !complete_series())
		{
			break;
		}
		$spent_time = time() - $exec_start_time;
		++$count;
	}
	writeLog('It took ' . $spent_time . ' sec.');
	
	// Retire idle clubs
	if ($spent_time < MAX_EXEC_TIME)
	{
		$wait_time = 60 * 60 * 24 * 365 / 2; // half a year
		$retired_clubs = 0;
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs c SET c.flags = c.flags | ' . CLUB_FLAG_RETIRED . ' WHERE (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 AND c.activated < UNIX_TIMESTAMP() - ? AND NOT EXISTS (SELECT * FROM games g WHERE g.club_id = c.id AND g.start_time > UNIX_TIMESTAMP() - ?);', $wait_time, $wait_time);
		$retired_clubs = Db::affected_rows();
		Db::commit();
		$spent_time = time() - $exec_start_time;
		
		if ($retired_clubs > 0)
		{
			writeLog('Retired ' . $retired_clubs . ' clubs.');
		}
	}
	
	// if ($_web && $count > 0)
	// {
		// echo '<script>window.location.reload();</script>';
	// }
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e, true);
	writeLog($e->getMessage());
}

if (!is_null($_file))
{
	fclose($_file);
}

?>