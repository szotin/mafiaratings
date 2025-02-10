<?php

require_once 'include/security.php';
require_once 'include/scoring.php';

define('AUTO_REFRESH', true);
define('MAX_EXEC_TIME', 25);
define('BATCH_SIZE', 100);
define('COMPLETION_FLAG', 0x400000); 

define('STAGE_GAMES', 0); 
define('STAGE_ISSUES', 1); 
define('STAGE_COMPLETE', 2);

$_state = new stdClass();
$_state->stage = STAGE_GAMES;
$_state->games_count = 0;
$_state->issues_count = 0;
$_state->games_change_count = 0;
$_state->issues_change_count = 0;
$_state->log_change_count = 0;
$_state->spent_time = 0;

function writeLog($str)
{
	echo $str . " <br>\n";
}

function convert_game($json)
{
	if (is_null($json))
	{
		return NULL;
	}
	$changed = false;
	$game = json_decode($json);
	if (isset($game->players))
	{
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $game->players[$i];
			if (isset($player->warnings) && is_array($player->warnings))
			{
				for ($j = 0; $j < count($player->warnings); ++$j)
				{
					$t = $player->warnings[$j];
					if ($t->time == 'voting' && isset($t->speaker) && isset($t->votingRound))
					{
						++$t->votingRound;
						$changed = true;
					}
				}
			}
			if (isset($player->death) && isset($player->death->time))
			{
				$t = $player->death->time;
				if ($t->time == 'voting' && isset($t->speaker) && isset($t->votingRound))
				{
					++$t->votingRound;
					$changed = true;
				}
			}
		}
	}
	if ($changed)
	{
		$json = json_encode($game);
	}
	
	$new_json = str_replace('nominant', 'nominee', $json);
	$changed = $changed || ($new_json !== $json);
	
	if (!$changed)
	{
		return NULL;
	}
	return $new_json;
}

function convert_log($log)
{
	if (is_null($log))
	{
		return NULL;
	}
	$new_log = str_replace('nominant', 'nominee', $log);
	if ($new_log === $log)
	{
		return NULL;
	}
	return $new_log;
}

function convert_next_batch()
{
	global $_state;
	
	switch ($_state->stage)
	{
	case STAGE_GAMES:
		$hits = false;
		Db::begin();
		$query = new DbQuery('SELECT id, log, json FROM games WHERE (feature_flags & ' . COMPLETION_FLAG . ') <> 0 LIMIT ' . BATCH_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $log, $json) = $row;
			++$_state->games_count;
			$json = convert_game($json);
			$log = convert_log($log);
			if (is_null($json))
			{
				if (is_null($log))
				{
					Db::exec('game', 'UPDATE games SET feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE id = ?', $game_id);
				}
				else
				{
					Db::exec('game', 'UPDATE games SET log = ?, feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE id = ?', $log, $game_id);
					++$_state->log_change_count;
				}
			}
			else if (is_null($log))
			{
				Db::exec('game', 'UPDATE games SET json = ?, feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE id = ?', $json, $game_id);
				++$_state->games_change_count;
			}
			else
			{
				Db::exec('game', 'UPDATE games SET json = ?, log = ?, feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE id = ?', $json, $log, $game_id);
				++$_state->games_change_count;
				++$_state->log_change_count;
			}
			$hits = true;
		}
		Db::commit();
		if (!$hits)
		{
			$_state->stage = STAGE_ISSUES;
		}
		break;
		
	case STAGE_ISSUES:
		$hits = false;
		Db::begin();
		$query = new DbQuery('SELECT game_id, json FROM game_issues WHERE (feature_flags & ' . COMPLETION_FLAG . ') <> 0 LIMIT ' . BATCH_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $json) = $row;
			++$_state->issues_count;
			$json = convert_game($json);
			if (is_null($json))
			{
				Db::exec('game', 'UPDATE game_issues SET feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE game_id = ?', $game_id);
			}
			else
			{
				Db::exec('game', 'UPDATE game_issues SET json = ? AND feature_flags = feature_flags & ~' . COMPLETION_FLAG . ' WHERE game_id = ?', $json, $game_id);
				++$_state->issues_change_count;
			}
			$hits = true;
		}
		Db::commit();
		if (!$hits)
		{
			$_state->stage = STAGE_COMPLETE;
		}
		break;
	}
}

try
{
	date_default_timezone_set('America/Vancouver');
	initiate_session();
	check_permissions(PERMISSION_ADMIN);
	
	if (isset($_REQUEST['reset']))
	{
		Db::exec('game', 'UPDATE games SET feature_flags = feature_flags | ' . COMPLETION_FLAG);
		Db::exec('game', 'UPDATE game_issues SET feature_flags = feature_flags | ' . COMPLETION_FLAG);
		writeLog('Reset done.');
		if (AUTO_REFRESH)
		{
			echo '<script src="js/common.js"></script>';
			echo '<script>goTo({ reset: undefined});</script>';
		}
	}
	else
	{
		$exec_start_time = time();
		while ($_state->spent_time < MAX_EXEC_TIME && $_state->stage != STAGE_COMPLETE)
		{
			convert_next_batch();
			$_state->spent_time = time() - $exec_start_time;
		}
		
		if (!isset($_SESSION['convert_games_state']))
		{
			$state = $_SESSION['convert_games_state'] = $_state;
		}
		else
		{
			$state = $_SESSION['convert_games_state'];
			$state->stage += $_state->stage;
			$state->games_count += $_state->games_count;
			$state->issues_count += $_state->issues_count;
			$state->games_change_count += $_state->games_change_count;
			$state->issues_change_count += $_state->issues_change_count;
			$state->log_change_count += $_state->log_change_count;
			$state->spent_time += $_state->spent_time;
		}
		
		writeLog('<h3>This itteration</h3>');
		writeLog('&nbsp;&nbsp;Games count: ' . $_state->games_count);
		writeLog('&nbsp;&nbsp;Games changed: ' . $_state->games_change_count);
		writeLog('&nbsp;&nbsp;Game logs changed: ' . $_state->log_change_count);
		writeLog('&nbsp;&nbsp;Issues count: ' . $_state->issues_count);
		writeLog('&nbsp;&nbsp;Game issues changed: ' . $_state->issues_change_count);
		writeLog('&nbsp;&nbsp;It took ' . $_state->spent_time . ' sec.');
		writeLog('<h3>Total</h3>');
		writeLog('&nbsp;&nbsp;Games count: ' . $state->games_count);
		writeLog('&nbsp;&nbsp;Games changed: ' . $state->games_change_count);
		writeLog('&nbsp;&nbsp;Game logs changed: ' . $state->log_change_count);
		writeLog('&nbsp;&nbsp;Issues count: ' . $state->issues_count);
		writeLog('&nbsp;&nbsp;Game issues changed: ' . $state->issues_change_count);
		writeLog('&nbsp;&nbsp;It took ' . $state->spent_time . ' sec.');
		
		if ($_state->stage == STAGE_COMPLETE)
		{
			unset($_SESSION['convert_games_state']);
			writeLog('<h3>Complete</h3>');
		}
		else if (AUTO_REFRESH)
		{
			echo '<script>window.location.reload();</script>';
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e, true);
	writeLog($e->getMessage());
}

?>