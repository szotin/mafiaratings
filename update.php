<?php

require_once 'include/updater.php';

class UpdateGames extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	protected function initState()
	{
		$this->state->event_id = 0;
		$this->state->table_num = 0;
		$this->state->game_num = 0;
		$this->state->updated = 0;
	}
	
	private function updateGame($game)
	{
		$change = false;
		if (isset($game->table))
		{
			$game->tableNum = $game->table;
			unset($game->table);
			$change = true;
		}
		if (isset($game->round))
		{
			$game->gameNum = $game->round;
			unset($game->round);
			$change = true;
		}
		return $change;
	}

	protected function update($items_count)
	{
		$count = 0;
		Db::begin();
		$query = new DbQuery(
			'SELECT event_id, table_num, game_num, game, log'.
			' FROM current_games'.
			' WHERE event_id > ? OR (event_id = ? AND (table_num > ? OR (table_num = ? AND game_num > ?)))'.
			' LIMIT ' . $items_count, 
			$this->state->event_id, $this->state->event_id, $this->state->table_num, $this->state->table_num, $this->state->game_num);
		while ($row = $query->next())
		{
			++$count;
			list($event_id, $table_num, $game_num, $game, $log) = $row;
			$game = json_decode($game);
			$log = json_decode($log);
			$change = $this->updateGame($game);
			foreach ($log as $g)
			{
				$change = $this->updateGame($g) || $change;
			}
			if ($change)
			{
				Db::exec('user', 'UPDATE current_games SET game = ?, log = ? WHERE event_id = ? AND table_num = ? AND game_num = ?', json_encode($game), json_encode($log), $event_id, $table_num, $game_num);
				++$this->state->updated;
			}
			$this->state->event_id = $event_id;
			$this->state->table_num = $table_num;
			$this->state->game_num = $game_num;
		}
		Db::commit();
		$this->log('Updated ' . $this->state->updated . ' games');
		if ($count <= 0)
		{
			$this->setTask(END_RUNNING);
		}
		return $count;
	}
}

$updater = new UpdateGames();
$updater->run();

?>