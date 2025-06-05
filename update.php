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
		$this->state->id = 0;
		$this->state->updated = 0;
	}

	protected function update($items_count)
	{
		$done = true;
		Db::begin();
		$query = new DbQuery('SELECT id, json FROM games WHERE id > ? LIMIT ' . $items_count, $this->state->id);
		while ($row = $query->next())
		{
			$done = false;
			list($game_id, $game) = $row;
			$game = json_decode($game);
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
			if ($change)
			{
				Db::exec('user', 'UPDATE games SET json = ? WHERE id = ?', json_encode($game), $game_id);
				++$this->state->updated;
			}
			$this->state->id = $game_id;
		}
		Db::commit();
		$this->log('Updated ' . $this->state->updated . ' games');
		if ($done)
		{
			$this->setTask(END_RUNNING);
		}
		return $items_count;
	}
}

$updater = new UpdateGames();
$updater->run();

?>