<?php

require_once __DIR__ . '/db.php';

define('SNAPSHOT_INTERVAL', 604800); // one week

function compare_players($player1, $player2)
{
	if (isset($player1->dst))
	{
		if (isset($player2->dst))
		{
			return $player1->dst - $player2->dst;
		}
		return -1;
	}
	else if (isset($player2->dst))
	{
		return 1;
	}
	return $player1->src - $player2->src;
}

class SnapshotPlayer
{
	public $id;
	public $rating;
	public $user_name;
	public $user_flags;
	public $club_id;
	public $club_name;
	public $club_flags;
	
	function __construct($row, $rating = NULL)
	{
		if ($rating == NULL)
		{
			list($this->id, $this->rating, $this->user_name, $this->user_flags, $this->club_id, $this->club_name, $this->club_flags) = $row;
			$this->id = (int)$this->id;
			$this->rating = (float)$this->rating;
		}
		else
		{
			$this->id = (int)$row;
			$this->rating = (float)$rating;
		}
	}
}

class Snapshot
{
	public $time;
    public $top100;
	
	function __construct($time, $json = NULL)
	{
		$this->time = (int)$time;
		if ($json != NULL)
		{
			$this->set_json($json);
		}
		else
		{
			$this->top100 = array();
		}
	}
	
	public static function snapshot_time($time)
	{
		return floor($time / SNAPSHOT_INTERVAL) * SNAPSHOT_INTERVAL;
	}
	
	public static function next_snapshot_time($time)
	{
		return Snapshot::snapshot_time($time) + SNAPSHOT_INTERVAL;
	}
	
	public function get_snapshot_time()
	{
		return Snapshot::snapshot_time($this->time);
	}
	
	public function set_json($json)
	{
		$this->top100 = array();
		$players = json_decode($json);
		// echo '<pre>';
		// print_r($players);
		// echo '</pre>';
		$list = "";
		foreach ($players as $player)
		{
			$id = $player[0];
			$rating = $player[1];
			$this->top100[] = new SnapshotPlayer($id, $rating);
		}
	}
	
	public function get_json()
	{
		$array = array();
		foreach ($this->top100 as $player)
		{
			$array[] = array($player->id, $player->rating);
		}
		return json_encode($array);
	}
	
	public function shot()
	{
		$this->top100 = array();
		$query = new DbQuery('SELECT id FROM games WHERE end_time > ? AND non_rating = 0 AND canceled = 0 LIMIT 1', $this->time);
		if ($query->next())
		{
			$query = new DbQuery(
				'SELECT p.user_id, (p.rating_before + p.rating_earned) as rating, u.name, u.flags, c.id, c.name, c.flags
					FROM players p
					JOIN users u ON u.id = p.user_id
					LEFT OUTER JOIN clubs c ON c.id = u.club_id 
					WHERE p.game_id = (
						SELECT p1.game_id 
						FROM players p1 
						WHERE p1.user_id = p.user_id AND p1.game_end_time <= ?
						ORDER BY p1.game_end_time DESC, p1.game_id DESC
						LIMIT 1)
					ORDER BY rating DESC, p.user_id DESC 
					LIMIT 100', $this->time);
		}
		else
		{
			$query = new DbQuery(
				'SELECT u.id, u.rating, u.name, u.flags, c.id, c.name, c.flags' . 
				' FROM users u' . 
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' . 
				' WHERE u.reg_time <= ?' . 
				' ORDER BY u.rating DESC, u.games_won DESC, u.games DESC, u.id' . 
				' LIMIT 100', $this->time);
		}
		
		while ($row = $query->next())
		{
			$this->top100[] = new SnapshotPlayer($row);
		}
	}
	
	public function load_user_details()
	{
		$count = count($this->top100);
		if ($count <= 0)
		{
			return;
		}
		
		$ids = '' . $this->top100[0]->id;
		for ($i = 1; $i < $count; ++$i)
		{
			$ids .= ', ' . $this->top100[$i]->id;
		}
		
		$query = new DbQuery('SELECT u.id, u.name, u.flags, c.id, c.name, c.flags FROM users u LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE u.id IN (' . $ids . ')');
		while ($row = $query->next())
		{
			list($id, $user_name, $user_flags, $club_id, $club_name, $club_flags) = $row;

			// So inefficient. But for count 100 it is ok.
			foreach ($this->top100 as $player)
			{
				if ($player->id == $id)
				{
					$player->user_name = $user_name;
					$player->user_flags = $user_flags;
					$player->club_id = $club_id;
					$player->club_name = $club_name;
					$player->club_flags = $club_flags;
					break;
				}
			}
		}
	}
	
	// // I'm not sure. Currently we are saving every snapshot even if it is not different from the previous one.
	// // This function skips similar snapshots. Possibly this is right and we will switch to it in the future. But then rebuilding snapshots should be reworked.
	// public function save()
	// {
		// $query = new DbQuery('SELECT time, snapshot FROM snapshots WHERE time <= ? ORDER BY time DESC LIMIT 1', $this->time);
		// if ($row = $query->next())
		// {
			// list($time, $json) = $row;
			// if (Snapshot::snapshot_time($this->time) > Snapshot::snapshot_time($time))
			// {
				// $latest_snapshot = new Snapshot($time, $json);
				// if ($this->is_much_different($latest_snapshot))
				// {
					// Db::exec(get_label('snapshot'), 'INSERT INTO snapshots (time, snapshot) VALUES (?, ?)', $this->time, $this->get_json());
				// }
			// }
		// }
		// else
		// {
			// Db::exec(get_label('snapshot'), 'INSERT INTO snapshots (time, snapshot) VALUES (?, ?)', $this->time, $this->get_json());
		// }
	// }
	
	public function is_much_different($snapshot)
	{
		$count = count($this->top100);
		if ($count != count($snapshot->top100))
		{
			return true;
		}
		
		for ($i = 0; $i < $count; ++$i)
		{
			$player1 = $this->top100[$i];
			$player2 = $snapshot->top100[$i];
			if ($player1->id != $player2->id)
			{
				return true;
			}
		}
		return false;
	}
	
	public function compare($snapshot)
	{
		$players = array();
		
		$count1 = count($this->top100);
		$count2 = count($snapshot->top100);
		$count = min($count1, $count2);
		for ($i = 0; $i < $count; ++$i)
		{
			$player1 = $this->top100[$i];
			$player2 = $snapshot->top100[$i];
			if ($player1->id != $player2->id)
			{
				if (isset($players[$player1->id]))
				{
					$p1 = $players[$player1->id];
					if ($p1->rating == $player1->rating)
					{
						unset($players[$player1->id]);
					}
				}
				else
				{
					$p1 = $players[$player1->id] = clone $player1;
				}
				
				if (isset($players[$player2->id]))
				{
					$p2 = $players[$player2->id];
					if ($p2->rating == $player2->rating)
					{
						unset($players[$player2->id]);
					}
				}
				else
				{
					$p2 = $players[$player2->id] = clone $player2;
				}
				
				$p1->dst = $i + 1;
				$p2->src = $i + 1;
			}
		}
		
		for (; $i < $count1; ++$i)
		{
			$player = $this->top100[$i];
			if (isset($players[$player->id]))
			{
				$p = $players[$player->id];
			}
			else
			{
				$p = $players[$player->id] = $player;
			}
			$p->dst = $i + 1;
		}
		
		for (; $i < $count2; ++$i)
		{
			$player = $snapshot->top100[$i];
			if (isset($players[$player->id]))
			{
				$p = $players[$player->id];
			}
			else
			{
				$p = $players[$player->id] = $player;
			}
			$p->src = $i + 1;
		}
		
		$result = array();
		foreach ($players as $id => $player)
		{
			$result[] = $player;
		}
		usort($result, 'compare_players');
		
		return $result;
	}
}

?>