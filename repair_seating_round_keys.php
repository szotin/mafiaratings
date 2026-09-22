<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

//-------------------------------------------------------------------------------------------------------
// Re-keys event seatings whose rounds are an object rather than a list.
//
// A seating rebuilt from game records is indexed by game number minus one. A canceled or unrated
// game leaves a gap, and a gapped PHP array is serialized by json_encode as an object - so misc
// ends up holding {"0":[...],"2":[...]} where every reader expects [[...],[...]]. Code that walks
// the rounds by index then gets an stdClass and dies: "Cannot use object of type stdClass as array".
//
// seating_optimization.php stopped producing these in c8716bd7, which put reindex_seating() on the
// extraction path, but the rows written before that were never repaired. They cannot repair
// themselves either: extract_seatings skips any event whose misc already carries a version, and
// all of these do.
//
// The repair is a pure re-keying. The seats, the players and the order of the rounds are untouched
// - only the keys become dense and 0-based, which is what the seatings table has held for these
// events all along. The script verifies that per event before writing: the re-keyed rounds must
// still hash to the hash already stored in misc, and an event that fails is reported and skipped.
//
// Run it like any other updater script:
//     php repair_seating_round_keys.php -test -time 120 -log_level 3
//     https://<site>/repair_seating_round_keys.php?time=60&log_level=3
// Add dry=1 (or -dry) to report what would change without writing anything.
// It is safe to run repeatedly: a repaired event no longer matches.
//-------------------------------------------------------------------------------------------------------
class RepairSeatingRoundKeys extends Updater
{
	private $dryRun = false;

	function __construct()
	{
		parent::__construct(__FILE__);

		if (isset($_REQUEST['dry']))
		{
			$this->dryRun = (bool)$_REQUEST['dry'];
		}
		if (isset($_SERVER['argv']))
		{
			foreach ($_SERVER['argv'] as $arg)
			{
				if ($arg == '-dry')
				{
					$this->dryRun = true;
				}
			}
		}
	}

	function repair_round_keys_task_start()
	{
		$this->vars->last_event_id = 0;
		$this->vars->repaired = 0;
		$this->vars->refused = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}
	}

	function repair_round_keys_task($items_count)
	{
		$rows = array();
		$query = new DbQuery(
			'SELECT e.id, e.tournament_id, e.misc FROM events e'.
			' WHERE e.id > ? AND e.misc LIKE \'%"seating"%\''.
			' ORDER BY e.id LIMIT '.$items_count,
			$this->vars->last_event_id);
		while ($row = $query->next())
		{
			$rows[] = $row;
		}

		$count = 0;
		foreach ($rows as $row)
		{
			list ($event_id, $tournament_id, $misc_str) = $row;
			$event_id = (int)$event_id;
			$tournament_id = (int)$tournament_id;
			$this->vars->last_event_id = $event_id;
			++$count;

			$misc = $misc_str !== null ? json_decode($misc_str) : null;
			if ($misc === null || !isset($misc->seating) || !isset($misc->seating->rounds))
			{
				continue;
			}

			// A list decodes to an array and needs nothing done to it.
			if (!is_object($misc->seating->rounds))
			{
				continue;
			}

			$dense = reindex_seating(json_decode(json_encode($misc->seating->rounds), true));

			// Prove the re-keying changed nothing but the keys. These rounds hold 0-based player
			// numbers, so the hash comes straight off them and must match what misc already says.
			$stored_hash = isset($misc->seating->hash) ? $misc->seating->hash : null;
			if (!is_null($stored_hash))
			{
				$def = new SeatingDef($dense);
				$def->normalizeRestrictions();
				if ($def->hash !== $stored_hash)
				{
					++$this->vars->refused;
					$this->log('Event '.$event_id.' (tournament '.$tournament_id.') re-keys to '.$def->hash.
						' but misc says '.$stored_hash.' - left unchanged, it needs looking at by hand.');
					continue;
				}
			}

			++$this->vars->repaired;
			$this->log('Event '.$event_id.' (tournament '.$tournament_id.') rounds were keyed '.
				implode(',', array_keys((array)$misc->seating->rounds)).' - re-keyed to a list of '.
				count($dense).'.');

			if ($this->dryRun)
			{
				continue;
			}

			$misc->seating->rounds = $dense;
			Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
		}
		return $count;
	}

	function repair_round_keys_task_end()
	{
		$this->log('Seatings '.($this->dryRun ? 'that would be re-keyed' : 're-keyed').': '.
			(int)$this->vars->repaired.'. Refused because the hash would change: '.(int)$this->vars->refused.'.');
	}
}

$updater = new RepairSeatingRoundKeys();
$updater->run();

?>
