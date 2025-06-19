<?php

require_once 'include/updater.php';
require_once 'include/game_ratings.php';
require_once 'include/snapshot.php';

define('PROCEED_GAMES', 'games');
define('PROCEED_RED_RATINGS', 'red-ratings');
define('PROCEED_BLACK_RATINGS', 'black-ratings');
define('PROCEED_RATINGS', 'ratings');
define('PROCEED_SNAPSHOTS', 'snapshots');

class RatingsBuilder extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	protected function initState()
	{
		Db::begin();
		$query = new DbQuery('SELECT id, start_time, game_id, current_game_id, average_game_proceeding_time, games_proceeded FROM rebuild_ratings WHERE end_time = 0 ORDER BY start_time DESC LIMIT 1');
		if ($row = $query->next())
		{
			list($id, $start_time, $game_id, $current_game_id, $average_time, $games_proceeded) = $row;
			$this->setTask(PROCEED_GAMES);
			$this->state->id = (int)$id;
			if (is_null($game_id))
			{
				$this->state->initial_game_id = NULL;
			}
			else
			{
				$this->state->initial_game_id = (int)$game_id;
			}
			
			if (is_null($current_game_id))
			{
				$this->state->game_id = $this->state->initial_game_id;
			}
			else
			{
				$this->state->game_id = (int)$current_game_id;
			}
			
			if ($start_time <= 0)
			{
				Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET start_time = ? WHERE id = ?', time(), $this->state->id);
			}
		}
		else
		{
			$this->setTask(PROCEED_SNAPSHOTS);
		}
		Db::commit();
	}
	
	private function proceedGames($items_count)
	{
		$count = 0;
		Db::begin();
		if (is_null($this->state->game_id))
		{
			$query = new DbQuery('SELECT id FROM games WHERE (flags & '.GAME_FLAG_CANCELED.') = 0 ORDER BY end_time, id LIMIT ' . $items_count);
		}
		else
		{
			$query = new DbQuery('SELECT g1.id FROM games g JOIN games g1 ON g1.end_time > g.end_time OR (g1.end_time = g.end_time AND g1.id > g.id) WHERE g.id = ? AND (g1.flags & '.GAME_FLAG_CANCELED.') = 0 ORDER BY g1.end_time, g1.id LIMIT ' . $items_count, $this->state->game_id);
		}
		while ($row = $query->next())
		{
			list($game_id) = $row;
			$this->state->game_id = (int)$game_id;
			++$count;
			update_game_ratings($game_id);
			
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		
		if ($count == 0)
		{
			$this->state->av_game_time = $this->getAverageItemTime();
			$this->setTask(PROCEED_RED_RATINGS);
		}
		return $count;
	}
	
	private function proceedRedRatings($items_count)
	{
		Db::begin();
		Db::exec('user', 'UPDATE users SET flags = flags | ' . USER_FLAG_RESET_TMP_RATING . ' WHERE (flags & ' . USER_FLAG_RESET_RED_RATING . ') <> 0 ORDER BY id LIMIT ' . $items_count);
		list($items_count) = Db::record('user', 'SELECT ROW_COUNT()');
		
		// // This is a rating descenting code that is not implemented yet. This part is not working but it gives an idea of what to do
		// Db::exec('user', 
			// 'UPDATE users u'.
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
			// ' u.flags = u.flags & ~' . (USER_FLAG_RESET_RED_RATING | USER_FLAG_RESET_TMP_RATING).
			// ' WHERE (u.flags & ' . USER_FLAG_RESET_TMP_RATING . ') <> 0');

		Db::exec('user', 
			'UPDATE users u'.
			' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				'SELECT p11.game_id FROM players p11'.
				' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role <= ' . ROLE_SHERIFF.
				' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				' LIMIT 1)'.
			' SET u.red_rating = p1.role_rating_before + p1.rating_earned, u.flags = u.flags & ~' . (USER_FLAG_RESET_RED_RATING | USER_FLAG_RESET_TMP_RATING).
			' WHERE (u.flags & ' . USER_FLAG_RESET_TMP_RATING . ') <> 0');
		Db::commit();
		
		if ($items_count <= 0)
		{
			$this->setTask(PROCEED_BLACK_RATINGS);
		}
		return $items_count;
	}
	
	private function proceedBlackRatings($items_count)
	{
		//$items_count = (int)($items_count * 0.5);
		Db::begin();
		Db::exec('user', 'UPDATE users SET flags = flags | ' . USER_FLAG_RESET_TMP_RATING . ' WHERE (flags & ' . USER_FLAG_RESET_BLACK_RATING . ') <> 0 ORDER BY id LIMIT ' . $items_count);
		list($items_count) = Db::record('user', 'SELECT ROW_COUNT()');
		
		// // This is a rating descenting code that is not implemented yet. This part is not working but it gives an idea of what to do
		// Db::exec('user', 
			// 'UPDATE users u'.
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
			// ' u.flags = u.flags & ~' . (USER_FLAG_RESET_BLACK_RATING | USER_FLAG_RESET_TMP_RATING).
			// ' WHERE (u.flags & ' . USER_FLAG_RESET_TMP_RATING . ') <> 0');

		Db::exec('user', 
			'UPDATE users u'.
			' JOIN players p1 ON u.id = p1.user_id AND p1.game_id = ('.
				'SELECT p11.game_id FROM players p11'.
				' WHERE p11.user_id = p1.user_id AND p11.is_rating <> 0 AND p11.role > ' . ROLE_SHERIFF.
				' ORDER BY p11.game_end_time DESC, p11.game_id DESC'.
				' LIMIT 1)'.
			' SET u.black_rating = p1.role_rating_before + p1.rating_earned, u.flags = u.flags & ~' . (USER_FLAG_RESET_BLACK_RATING | USER_FLAG_RESET_TMP_RATING).
			' WHERE (u.flags & ' . USER_FLAG_RESET_TMP_RATING . ') <> 0');
		Db::commit();
		
		if ($items_count <= 0)
		{
			$this->setTask(PROCEED_RATINGS);
		}
		return $items_count;
	}
	
	private function proceedRatings($items_count)
	{
		Db::begin();
		Db::exec('user', 'UPDATE users SET rating = black_rating + red_rating');
		list($items_count) = Db::record('user', 'SELECT ROW_COUNT()');
		
		if (is_null($this->state->initial_game_id))
		{
			Db::exec('snapshot', 'DELETE FROM snapshots');
		}
		else
		{
			Db::exec('snapshot', 'DELETE FROM snapshots WHERE time > (SELECT end_time FROM games WHERE id = ?)', $this->state->initial_game_id);
		}
		Db::exec('rebuild plan', 'UPDATE rebuild_ratings SET end_time = ?, average_game_proceeding_time = ? WHERE id = ?', time(), $this->state->av_game_time, $this->state->id);
		Db::commit();
		$this->log('Rebuilding ratings complete');
		$this->setTask(END_RUNNING);
		return $items_count;
	}
	
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
	
	private function proceedSnapshots($items_count)
	{
		$count = 0;
		Db::begin();
		for ($i = 0; $i < $items_count; ++$i)
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
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		
		if ($count == 0)
		{
			$this->setTask(END_RUNNING);
		}
		return $count;
	}
	
	protected function update($items_count)
	{
		switch ($this->state->task)
		{
		case PROCEED_GAMES:
			return $this->proceedGames($items_count);
		case PROCEED_RED_RATINGS:
			return $this->proceedRedRatings($items_count);
		case PROCEED_BLACK_RATINGS:
			return $this->proceedBlackRatings($items_count);
		case PROCEED_RATINGS:
			return $this->proceedRatings($items_count);
		case PROCEED_SNAPSHOTS:
		default:
			return $this->proceedSnapshots($items_count);
		}
		return 0;
	}
}

$updater = new RatingsBuilder();
$updater->run();

?>