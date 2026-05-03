<?php

require_once 'include/updater.php';

class SeatingFormatUpdater extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}

	//-------------------------------------------------------------------------------------------------------
	// SeatingFormatUpdater.events
	// Migrate events.misc.seating.tables [table][round][seat] → seating.rounds [round][table][seat]
	//-------------------------------------------------------------------------------------------------------
	private function transposeSeating($tables)
	{
		// Transpose [table][round][seat] → [round][table][seat]
		$rounds = array();
		foreach ($tables as $t_idx => $table)
		{
			if (is_null($table)) { continue; }
			foreach ($table as $g_idx => $game)
			{
				while (count($rounds) <= $g_idx)
				{
					$rounds[] = array();
				}
				while (count($rounds[$g_idx]) <= $t_idx)
				{
					$rounds[$g_idx][] = null;
				}
				$rounds[$g_idx][$t_idx] = $game;
			}
		}
		return $rounds;
	}
	
	function events_task($items_count)
	{
		if (!isset($this->vars->event))
		{
			$this->vars->event = 0;
		}

		if (!isset($this->vars->real_count))
		{
			$this->vars->real_count = 0;
			$old_real_count = -1;
		}
		else
		{
			$old_real_count = $this->vars->real_count;
		}

		$count = 0;
		$query = new DbQuery('SELECT id, misc FROM events WHERE id > ? AND misc IS NOT NULL ORDER BY id LIMIT ' . $items_count, $this->vars->event);
		while ($row = $query->next())
		{
			++$count;
			list($event_id, $misc_str) = $row;
			$misc = json_decode($misc_str);

			if (!is_null($misc) && isset($misc->seating))
			{
				if (is_object($misc->seating) && isset($misc->seating->tables))
				{
					$misc->seating->rounds = $this->transposeSeating($misc->seating->tables);
					unset($misc->seating->tables);

					Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
					++$this->vars->real_count;
				}
				else if (is_array($misc->seating))
				{
					$seating = new stdClass();
					// MWT seating is [game][table][players] — no transposition needed
					$seating->rounds = isset($misc->mwt_schema)
						? $misc->seating
						: $this->transposeSeating($misc->seating);
					$misc->seating = $seating;
					
					Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
					++$this->vars->real_count;
				}
			}

			$this->vars->event = (int)$event_id;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}

		if ($old_real_count != $this->vars->real_count)
		{
			$this->log('Migrated ' . $this->vars->real_count . ' event seating records');
		}
		return $count;
	}
}

$updater = new SeatingFormatUpdater();
$updater->run();

?>
