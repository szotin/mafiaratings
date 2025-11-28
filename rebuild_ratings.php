<?php

require_once 'include/updater.php';
require_once 'include/game_ratings.php';
require_once 'include/snapshot.php';

define('PROCEED_GAMES', 'games');
define('PROCEED_RED_RATINGS', 'red-ratings');
define('PROCEED_BLACK_RATINGS', 'black-ratings');
define('PROCEED_RATINGS', 'ratings');
define('PROCEED_SNAPSHOTS', 'snapshots');

class RebuildRatings extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RebuildRatings.games
	//-------------------------------------------------------------------------------------------------------
	function games_task_start()
	{
		Db::begin();
		$query = new DbQuery('SELECT id, start_time, game_id, current_game_id, average_game_proceeding_time, games_proceeded FROM rebuild_ratings WHERE end_time = 0 ORDER BY start_time DESC LIMIT 1');
		if ($row = $query->next())
		{
			list($this->vars->id, $start_time, $game_id, $current_game_id, $average_time, $games_proceeded) = $row;
			$this->vars->id = (int)$this->vars->id;
			if (is_null($game_id))
			{
				$this->vars->initial_game_id = null;
			}
			else
			{
				$this->vars->initial_game_id = (int)$game_id;
			}
			
			if (is_null($current_game_id))
			{
				$this->vars->game_id = $this->vars->initial_game_id;
			}
			else
			{
				$this->vars->game_id = (int)$current_game_id;
			}
			
			if ($start_time <= 0)
			{
				Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET start_time = ? WHERE id = ?', time(), $this->vars->id);
			}
		}
		Db::commit();
	}
	
	function games_task_end()
	{
		if (isset($this->vars->id))
		{
			Db::begin();
			if (is_null($this->vars->initial_game_id))
			{
				Db::exec('snapshot', 'DELETE FROM snapshots');
			}
			else
			{
				Db::exec('snapshot', 'DELETE FROM snapshots WHERE time > (SELECT end_time FROM games WHERE id = ?)', $this->vars->initial_game_id);
			}
			Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET end_time = ? WHERE id = ?', time(), $this->vars->id);
			Db::commit();
		}
	}
	
	function games_task($items_count)
	{
		if (!isset($this->vars->id))
		{
			return 0;
		}
		
		$count = 0;
		Db::begin();
		if (is_null($this->vars->game_id))
		{
			$this->debug('Rebuild all');
			$query = new DbQuery('SELECT id, end_time FROM games WHERE (flags & '.GAME_FLAG_CANCELED.') = 0 ORDER BY end_time, id LIMIT ' . $items_count);
		}
		else
		{
			$this->debug('Rebuild all after the game ' . $this->vars->game_id);
			$query = new DbQuery('SELECT g1.id, g1.end_time FROM games g JOIN games g1 ON g1.end_time > g.end_time OR (g1.end_time = g.end_time AND g1.id > g.id) WHERE g.id = ? AND (g1.flags & '.GAME_FLAG_CANCELED.') = 0 ORDER BY g1.end_time, g1.id LIMIT ' . $items_count, $this->vars->game_id);
		}
		while ($row = $query->next())
		{
			list($game_id, $time) = $row;
			++$count;
			update_game_ratings($game_id);
			$this->log('Game ' . $game_id . ': ' . date('Y-m-d H:i', $time));
			$this->vars->game_id = (int)$game_id;
			
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RebuildRatings.red_ratings
	//-------------------------------------------------------------------------------------------------------
	function red_ratings_task($items_count)
	{
		Db::begin();
		// // This is a rating descenting code that is not implemented yet. This part is not working but it gives an idea of what to do
		// Db::exec('user', 
			// 'UPDATE users u'.
			// ' JOIN (SELECT id FROM users WHERE (flags & ' . USER_FLAG_RESET_RED_RATING . ') <> 0 LIMIT ' . $items_count . ') l ON u.id = l.id'. 
			// ' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				// 'SELECT p11.game_id FROM players p11'.
				// ' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role <= ' . ROLE_SHERIFF.
				// ' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				// ' LIMIT 1)'.
			// ' JOIN players p2 ON u.id = p2.user_id AND p2.game_id = ('.
				// 'SELECT p21.game_id FROM players p21'.
				// ' WHERE p21.user_id = p2.user_id AND p21.is_rating <> 0 '.
				// ' ORDER BY p21.game_end_time DESC, p21.game_id DESC'.
				// ' LIMIT 1)'.
			// ' SET u.red_rating ='.
				// ' IF(p2.rating_lock_until >= UNIX_TIMESTAMP(),'.
					// ' p1.role_rating_before + p1.rating_earned,'.
					// ' IF(p2.rating_lock_until + ' . RATING_DESCEND_PERIOD . ' <= UNIX_TIMESTAMP(),'.
						// ' 0,'.
						// ' ((p1.role_rating_before + p1.rating_earned) * (p2.rating_lock_until + ' . RATING_DESCEND_PERIOD . ' - UNIX_TIMESTAMP())) / ' . RATING_DESCEND_PERIOD . ')), '.
			// ' u.flags = u.flags & ~' . USER_FLAG_RESET_RED_RATING);
			
		Db::exec('user', 
			'UPDATE users u'.
			' JOIN (SELECT id FROM users WHERE (flags & ' . USER_FLAG_RESET_RED_RATING . ') <> 0 LIMIT ' . $items_count . ') l ON u.id = l.id'. 
			' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				'SELECT p11.game_id FROM players p11'.
				' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role <= ' . ROLE_SHERIFF.
				' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				' LIMIT 1)'.
			' SET u.red_rating = p1.role_rating_before + p1.rating_earned, u.flags = u.flags & ~' . USER_FLAG_RESET_RED_RATING);
		list($items_count) = Db::record('user', 'SELECT ROW_COUNT()');
		Db::commit();
		return (int)$items_count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RebuildRatings.black_ratings
	//-------------------------------------------------------------------------------------------------------
	function black_ratings_task($items_count)
	{
		Db::begin();
		// // This is a rating descenting code that is not implemented yet. This part is not working but it gives an idea of what to do
		// Db::exec('user', 
			// 'UPDATE users u'.
			// ' JOIN (SELECT id FROM users WHERE (flags & ' . USER_FLAG_RESET_BLACK_RATING . ') <> 0 LIMIT ' . $items_count . ') l ON u.id = l.id'. 
			// ' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				// 'SELECT p11.game_id FROM players p11'.
				// ' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role > ' . ROLE_SHERIFF.
				// ' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				// ' LIMIT 1)'.
			// ' JOIN players p2 ON u.id = p2.user_id AND p2.game_id = ('.
				// 'SELECT p21.game_id FROM players p21'.
				// ' WHERE p21.user_id = p2.user_id AND p21.is_rating <> 0 '.
				// ' ORDER BY p21.game_end_time DESC, p21.game_id DESC'.
				// ' LIMIT 1)'.
			// ' SET u.black_rating ='.
				// ' IF(p2.rating_lock_until >= UNIX_TIMESTAMP(),'.
					// ' p1.role_rating_before + p1.rating_earned,'.
					// ' IF(p2.rating_lock_until + ' . RATING_DESCEND_PERIOD . ' <= UNIX_TIMESTAMP(),'.
						// ' 0,'.
						// ' ((p1.role_rating_before + p1.rating_earned) * (p2.rating_lock_until + ' . RATING_DESCEND_PERIOD . ' - UNIX_TIMESTAMP())) / ' . RATING_DESCEND_PERIOD . ')), '.
			// ' u.flags = u.flags & ~' . USER_FLAG_RESET_BLACK_RATING);

		Db::exec('user', 
			'UPDATE users u'.
			' JOIN (SELECT id FROM users WHERE (flags & ' . USER_FLAG_RESET_BLACK_RATING . ') <> 0 LIMIT ' . $items_count . ') l ON u.id = l.id'. 
			' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				'SELECT p11.game_id FROM players p11'.
				' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role > ' . ROLE_SHERIFF.
				' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				' LIMIT 1)'.
			' SET u.black_rating = p1.role_rating_before + p1.rating_earned, u.flags = u.flags & ~' . USER_FLAG_RESET_BLACK_RATING);
		list($items_count) = Db::record('user', 'SELECT ROW_COUNT()');
		Db::commit();
		return (int)$items_count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RebuildRatings.ratings
	//-------------------------------------------------------------------------------------------------------
	function ratings_task($items_count)
	{
		$last_user_id = 0;
		if (isset($this->vars->last_user_id))
		{
			$last_user_id = (int)$this->vars->last_user_id;
		}
		
		Db::begin();
		list ($max_user_id, $items_count) = Db::record('user', 'SELECT MAX(u.id), COUNT(u.id) FROM users u JOIN (SELECT id FROM users WHERE id > ? ORDER BY id LIMIT ' . $items_count . ') l ON u.id = l.id', $last_user_id);
		if ($items_count > 0)
		{
			Db::exec('user', 'UPDATE users SET rating = black_rating + red_rating WHERE id > ? AND id <= ?', $last_user_id, $max_user_id);
			list($count) = Db::record('user', 'SELECT ROW_COUNT()');
			$this->log($count . ' actually written');
		}
		Db::commit();
		$this->vars->last_user_id = (int)$max_user_id;
		return (int)$items_count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// RebuildRatings.snapshots
	//-------------------------------------------------------------------------------------------------------
	private function getSnapshotTime()
	{
		$query = new DbQuery('SELECT time FROM snapshots ORDER BY time DESC LIMIT 1');
		if ($row = $query->next())
		{
			$time = (int)$row[0];
		}
		else
		{
			$query = new DbQuery('SELECT end_time FROM games WHERE (flags & '.GAME_FLAG_CANCELED.') = 0 AND (flags & '.GAME_FLAG_RATING.') <> 0 ORDER BY end_time LIMIT 1');
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
	
	function snapshots_task($items_count)
	{
		$count = 0;
		Db::begin();
		for ($count = 0; $count < $items_count && $this->canDoOneMoreItem(); ++$count)
		{
			$time = $this->getSnapshotTime();
			if ($time <= 0)
			{
				break;
			}
			
			$snapshot = new Snapshot($time);
			$snapshot->shot();
			Db::exec(get_label('snapshot'), 'INSERT INTO snapshots (time, snapshot) VALUES (?, ?)', $snapshot->time, $snapshot->get_json());
			$this->log('Snapshot created for ' . date('F d, Y', $time));
		}
		Db::commit();
		return $count;
	}
}

$updater = new RebuildRatings();
$updater->run();

?>