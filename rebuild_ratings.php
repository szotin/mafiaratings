<?php


setDir();
require_once 'include/branding.php';
require_once 'include/localization.php';
require_once 'include/db.php';
require_once 'include/constants.php';
require_once 'include/game_ratings.php';
require_once 'include/snapshot.php';

$_web = isset($_SERVER['HTTP_HOST']);
$_filename = 'rebuild_ratings.log';
$_file = NULL;
 
if ($_web)
{
	if (isset($_REQUEST['no_log']))
	{
		$_filename = NULL;
	}
	define('MAX_EXEC_TIME', 25);
	define('NEEDED_TIME_FOR_FINAL_QUERY', 10);
}
else
{
	define('MAX_EXEC_TIME', 180); // 3 minutes
	define('NEEDED_TIME_FOR_FINAL_QUERY', 120); // 2 minutes
}
define('GAMES_IN_A_BATCH', 50); // how many games per transaction

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
			fwrite($_file, "------\n");
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

function get_rebuild_object()
{
	Db::begin();
	$obj = NULL;
	$query = new DbQuery('SELECT id, start_time, game_id, current_game_id, average_game_proceeding_time, games_proceeded FROM rebuild_ratings WHERE end_time = 0 ORDER BY start_time DESC LIMIT 1');
	if ($row = $query->next())
	{
		list($id, $start_time, $game_id, $current_game_id, $average_time, $games_proceeded) = $row;
		$obj = new stdClass();
		$obj->id = (int)$id;
		$obj->start_time = (int)$start_time;
		$obj->initial_game_id = (int)$game_id;
		if (is_null($current_game_id))
		{
			$obj->game_id = (int)$game_id;
		}
		else
		{
			$obj->game_id = (int)$current_game_id;
		}
		$obj->average_time = (double)$average_time;
		$obj->games_proceeded = (int)$games_proceeded;
		if ($obj->start_time <= 0)
		{
			$obj->start_time = time();
			Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET current_game_id = game_id, start_time = ?, batch_size = ? WHERE id = ?', $obj->start_time, GAMES_IN_A_BATCH, $obj->id);
			writeLog('Starting rebuild');
		}
	}
	Db::commit();
	return $obj;
}

function get_snapshot_time()
{
	$query = new DbQuery('SELECT time FROM snapshots ORDER BY time DESC LIMIT 1');
	if ($row = $query->next())
	{
		$time = (int)$row[0];
	}
	else
	{
		$query = new DbQuery('SELECT end_time FROM games WHERE result > 0 AND canceled = 0 AND non_rating = 0 ORDER BY end_time LIMIT 1');
		if ($row = $query->next())
		{
			$time = (int)$row[0];
		}
		else
		{
			$time = time();
		}
	}
	$time = Snapshot::next_snapshot_time($time);
	if ($time < time())
	{
		return $time;
	}
	return 0;
}

try
{
	$exec_start_time = time();
	$rebuild = get_rebuild_object();
	if ($rebuild != NULL)
	{
		Db::begin();
		$batch_count = 0;
		$batch_start_time = time();
		while (true)
		{
			if ($batch_count >= GAMES_IN_A_BATCH)
			{
				$rebuild->games_proceeded += $batch_count;
				$rebuild->average_time = ($rebuild->average_time * $rebuild->games_proceeded + time() - $batch_start_time) / $rebuild->games_proceeded;
				$batch_count = 0;
				$batch_start_time = time();
				
				Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET current_game_id = ?, games_proceeded = ?, average_game_proceeding_time = ? WHERE id = ?', $rebuild->game_id, $rebuild->games_proceeded, $rebuild->average_time, $rebuild->id);
				Db::commit();
				
				writeLog('Batch end. Average game time: ' . number_format($rebuild->average_time, 3) . ' sec; Games proceeded: ' . $rebuild->games_proceeded);
				if ($batch_start_time - $exec_start_time + $rebuild->average_time * GAMES_IN_A_BATCH > MAX_EXEC_TIME)
				{
					// No time for one more batch.
					writeLog('Time is up');
					writeLog('Elapsed: ' . ($batch_start_time - $exec_start_time) . ' sec');
					writeLog('Predicted: ' . number_format($rebuild->average_time * GAMES_IN_A_BATCH, 0) . ' sec');
					if ($_web)
					{
						echo '<script>window.location.reload();</script>';
					}
					break;
				}
				Db::begin();
			}
			
			// get next game
			if (is_null($rebuild->game_id))
			{
				$query = new DbQuery('SELECT id FROM games WHERE result > 0 AND canceled = 0 ORDER BY end_time, id LIMIT 1');
			}
			else
			{
				$query = new DbQuery('SELECT g1.id FROM games g JOIN games g1 ON g1.end_time > g.end_time OR (g1.end_time = g.end_time AND g1.id > g.id) WHERE g.id = ? AND g1.result > 0 AND g1.canceled = 0 ORDER BY g1.end_time, g1.id LIMIT 1', $rebuild->game_id);
			}
			if ($row = $query->next())
			{
				list($game_id) = $row;
				$rebuild->game_id = (int)$game_id;
				++$batch_count;
				update_game_ratings($game_id);
			}
			else
			{
				if ($batch_count > 0)
				{
					$rebuild->games_proceeded += $batch_count;
					$rebuild->average_time = ($rebuild->average_time * $rebuild->games_proceeded + time() - $batch_start_time) / $rebuild->games_proceeded;
					Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET current_game_id = ?, games_proceeded = ?, average_game_proceeding_time = ? WHERE id = ?', $rebuild->game_id, $rebuild->games_proceeded, $rebuild->average_time, $rebuild->id);
					writeLog('Batch end. Average game time: ' . number_format($rebuild->average_time, 3) . ' sec; Games proceeded: ' . $rebuild->games_proceeded);
				}
				
				// The end. Apply latest ratings from players table to users table.
				if (MAX_EXEC_TIME - NEEDED_TIME_FOR_FINAL_QUERY >= time() - $exec_start_time)
				{
					// there is enough time for final query
					$start_final_query = time();
					Db::exec('user', 'UPDATE users u JOIN players p ON u.id = p.user_id SET u.rating = p.rating_before + p.rating_earned WHERE p.game_id = (SELECT p1.game_id FROM players p1 WHERE p1.user_id = p.user_id ORDER BY p1.game_end_time DESC, p1.game_id DESC LIMIT 1)');
					list($ratings_changed) = Db::record('user', 'SELECT ROW_COUNT()');
					writeLog('Final query took ' . (time() - $start_final_query) . ' sec');
					writeLog('Ratings updated for ' . $ratings_changed . ' users');
					Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET end_time = ?, ratings_changed = ? WHERE id = ?', time(), $ratings_changed, $rebuild->id);
					if (is_null($rebuild->initial_game_id))
					{
						Db::exec('snapshot', 'DELETE FROM snapshots');
					}
					else
					{
						Db::exec('snapshot', 'DELETE FROM snapshots WHERE time > (SELECT end_time FROM games WHERE id = ?)', $rebuild->initial_game_id);
					}
					writeLog('Rebuild complete ');
				}
				Db::commit();
				if ($_web)
				{
					echo '<script>window.location.reload();</script>';
				}
				break;
			}
		}
	}
	else
	{
		// Nothing to rebuild. Let's check if all the latest snapshots are available
		// Note that there is no transaction by purpose. No other code is changing snapshots.
		$max_snapshot_create_time = 0;
		$snapshot_create_time = time();
		while (($time = get_snapshot_time()) > 0) 
		{
			$snapshot = new Snapshot($time);
			$snapshot->shot();
			Db::exec(get_label('snapshot'), 'INSERT INTO snapshots (time, snapshot) VALUES (?, ?)', $snapshot->time, $snapshot->get_json());
			$snapshot_create_time = time() - $snapshot_create_time;
			$max_snapshot_create_time = max($max_snapshot_create_time, $snapshot_create_time);
			writeLog('Snapshot created for ' . date('F d, Y', $time) . ' in ' . $snapshot_create_time . ' sec');
			$snapshot_create_time = time();
			if (MAX_EXEC_TIME - $max_snapshot_create_time < $snapshot_create_time - $exec_start_time)
			{
				writeLog('Time is up');
				if ($_web)
				{
					echo '<script>window.location.reload();</script>';
				}
				break;
			}
		}
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