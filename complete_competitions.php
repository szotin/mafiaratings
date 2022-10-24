<?php

define('TOURNAMENT_CREDIT_GAMES_PERCENT', 60);

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

function complete_event()
{
	$result = false;
	Db::begin();
	$query = new DbQuery('SELECT e.id, sv.scoring, e.scoring_options, e.flags FROM events e JOIN scoring_versions sv ON sv.scoring_id = e.scoring_id AND sv.version = e.scoring_version WHERE e.start_time + e.duration < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_FINISHED . ') = 0 LIMIT 1');
	if ($row = $query->next())
	{
		list($event_id, $scoring, $scoring_options, $flags) = $row;
		
		$players_count = 0;
		Db::exec(get_label('event'), 'DELETE FROM event_places WHERE event_id = ?', $event_id);
		if (($flags & EVENT_FLAG_CANCELED) == 0)
		{
			$scoring = json_decode($scoring);
			$scoring_options = json_decode($scoring_options);
			
			$players = event_scores($event_id, null, SCORING_LOD_PER_GROUP, $scoring, $scoring_options);
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
			// find out minimum player games to count tournament for a player
			$sum_games = 0;
			$player_games = array();
			$query1 = new DbQuery('SELECT p.user_id, count(g.id) FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id WHERE e.tournament_id = ? AND (e.flags & ' . EVENT_FLAG_WITH_SELECTION . ') = 0 GROUP BY p.user_id', $tournament_id);
			while ($row1 = $query1->next())
			{
				list($player_id, $games_played) = $row1;
				$sum_games += $games_played;
				++$players_count;
			}
			if ($players_count > 0)
			{
				// The tournament counts for a player only if they played more than TOURNAMENT_CREDIT_GAMES_PERCENT (60%) of average games count. 
				// We do it in a separate query because we calculate average using only main rounds - excluding finals and semi-finals.
				$min_games = $sum_games / $players_count; // average
				$min_games = $min_games * TOURNAMENT_CREDIT_GAMES_PERCENT / 100;
			}
			
			// Write down the tournament places
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
			
			Db::exec(get_label('tournament'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
			$players = tournament_scores($tournament_id, $tournament_flags, null, SCORING_LOD_PER_GROUP, $scoring, $normalizer, $scoring_options);
			$players_count = count($players);
			
			if ($players_count > 0)
			{
				foreach ($players as $player)
				{
					if ($player->games_count <= $min_games)
					{
						$player->credit = false;
					}
					else
					{
						$player->credit = true;
						++$real_count;
					}
				}
				
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
						Db::exec(get_label('player'), 'INSERT INTO tournament_places (tournament_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $tournament_id, $player->id, $place, $importance, $main_points, $bonus_points, $shot_points, $player->games_count);
						++$place;
					}
				}
			}
		}
			
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags | ' . TOURNAMENT_FLAG_FINISHED .  ' WHERE id = ?', $tournament_id);
		
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

function complete_series()
{
	return false;
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
		if (!complete_series() && !complete_tournament() && !complete_event())
		{
			break;
		}
		$spent_time = time() - $exec_start_time;
		++$count;
	}
	writeLog('It took ' . $spent_time . ' sec.');
	if ($_web && $count > 0)
	{
		echo '<script>window.location.reload();</script>';
	}
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