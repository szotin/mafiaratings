<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

//-------------------------------------------------------------------------------------------------------
// Brings seatings.tables_score back in line with what calculateTablesScore() now returns.
//
// The tables score says how evenly each player is spread over the tables. calculateTablesScore()
// used to return 0 - its way of saying "perfect, nothing left to do" - for anything with fewer
// than three tables, so every two-table seating was stored with a score of 0 even when its
// players were spread quite unevenly. Two tables do have something to balance: the optimizer can
// swap the two tables around in some games and not others.
//
// Fixing the formula is not enough on its own, because the optimizer picks its next piece of work
// with "... AND tables_score > 0" (see seating_optimization.php). A seating stored at 0 is
// invisible to it, so the stored scores have to be refreshed once before any of this starts
// happening by itself.
//
// Only rows whose stored score disagrees with the formula are written. Besides the two-table ones
// that is the odd seating whose stored score drifted from its stored arrangement, which this
// corrects as well.
//
// Run it like any other updater script:
//     php rescore_seating_tables.php -test -time 300 -log_level 3
//     https://<site>/rescore_seating_tables.php?time=120&log_level=3
// Add dry=1 (or -dry) to report what would change without writing anything.
// It is safe to run repeatedly: a row that already agrees is skipped.
//-------------------------------------------------------------------------------------------------------
class RescoreSeatingTables extends Updater
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

	function rescore_tables_task_start()
	{
		$this->vars->last_hash = '';
		$this->vars->rescored = 0;
		$this->vars->woken = 0;
		$this->vars->skipped = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}
	}

	function rescore_tables_task($items_count)
	{
		// Paged by hash so the cursor only moves forward.
		$rows = array();
		$query = new DbQuery(
			'SELECT hash, seating, tables_score FROM seatings WHERE hash > ? ORDER BY hash LIMIT '.$items_count,
			$this->vars->last_hash);
		while ($row = $query->next())
		{
			$rows[] = $row;
		}

		$count = 0;
		foreach ($rows as $row)
		{
			list ($hash, $seating_json, $stored) = $row;
			$this->vars->last_hash = $hash;
			++$count;

			$seating = json_decode($seating_json, true);
			if (!is_array($seating) || !count($seating))
			{
				++$this->vars->skipped;
				$this->log('Seating '.$hash.' has no readable arrangement stored - left alone.');
				continue;
			}

			$def = new SeatingDef($hash);
			$score = $def->calculateTablesScore(reindex_seating($seating));
			$stored = (float)$stored;

			// tables_score is a 4-byte float and MySQL hands it back as a six-digit decimal, so
			// a value like 464/3 never reads back as what was written: 154.667 against
			// 154.666666... Compared absolutely those rows would be rewritten on every run, so
			// the tolerance is relative to the value, which is how the column's precision works.
			if (abs($score - $stored) <= max(0.0001, abs($score) * 1e-5))
			{
				continue;
			}

			++$this->vars->rescored;
			// A row going from 0 to something is one the optimizer could not see before.
			if ($stored == 0 && $score > 0)
			{
				++$this->vars->woken;
			}
			$this->log('Seating '.$hash.' tables score '.round($stored, 2).' -> '.round($score, 2).'.');

			if ($this->dryRun)
			{
				continue;
			}

			Db::exec(get_label('seating'), 'UPDATE seatings SET tables_score = ? WHERE hash = ?', $score, $hash);
		}
		return $count;
	}

	function rescore_tables_task_end()
	{
		$this->log('Seatings '.($this->dryRun ? 'that would be rescored' : 'rescored').': '.
			(int)$this->vars->rescored.', of which '.(int)$this->vars->woken.
			' were stored at 0 and so invisible to the tables optimizer. Left alone: '.(int)$this->vars->skipped.'.');
	}
}

$updater = new RescoreSeatingTables();
$updater->run();

?>
