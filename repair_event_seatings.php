<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

//-------------------------------------------------------------------------------------------------------
// Repairs events whose misc.seating is damaged.
//
// A seating kept in events.misc is written in user IDs. Some of them hold seats that name no
// player at all (a zero left where nobody was ever assigned) or seat the same person twice in
// one round. Those are not merely cosmetic: normalize_seating_to_indices() numbers every
// distinct value it meets, so a placeholder becomes an extra player who never shares a table
// with anyone, and SeatingDef then infers a "keep these two apart" rule between that phantom
// and every real player. That is where the impossible-looking seatings in the seatings table
// came from.
//
// The repair drops the damaged seating from misc and clears TOURNAMENT_FLAG_SEATING_EXTRACTED
// on the event's tournament. Nothing else is touched - in particular the games themselves,
// which are the real record, are left exactly as they are. The seating in misc is a cache:
// with it gone, seating_optimization.php's extract_seatings task rebuilds it from the game
// records on its next pass and writes it back with a hash and a version.
//
// Events whose seating is well formed but has players playing different numbers of games are
// reported and left alone. That is most likely what actually happened at the event rather than
// damaged data, and it is not this script's business to rewrite history.
//
// Run it like any other updater script:
//     php repair_event_seatings.php -test -time 120 -log_level 3
//     https://<site>/repair_event_seatings.php?time=60&log_level=3
// Add dry=1 (or -dry) to report what would change without writing anything.
// It is safe to run repeatedly: a repaired event no longer matches.
//-------------------------------------------------------------------------------------------------------
class RepairEventSeatings extends Updater
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

	function repair_seatings_task_start()
	{
		$this->vars->last_event_id = 0;
		$this->vars->repaired = 0;
		$this->vars->uneven = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}
	}

	function repair_seatings_task($items_count)
	{
		// Only finished tournaments, which is also the scope extract_seatings works in. An MWT
		// import lays out misc.seating as a scaffold of empty rounds that gets filled in as the
		// games are entered (see api/ops/mwt.php), so while a tournament is still running a
		// seating full of unfilled seats is live working data, not damage, and must be left
		// alone. Only once the tournament is over is an unfilled seat certainly a leftover.
		$rows = array();
		$query = new DbQuery(
			'SELECT e.id, e.tournament_id, e.misc FROM events e'.
			' JOIN tournaments t ON t.id = e.tournament_id'.
			' WHERE e.id > ? AND e.misc LIKE \'%"seating"%\' AND (t.flags & ?) <> 0'.
			' ORDER BY e.id LIMIT '.$items_count,
			$this->vars->last_event_id, TOURNAMENT_FLAG_FINISHED);
		while ($row = $query->next())
		{
			$rows[] = $row;
		}

		$count = 0;
		foreach ($rows as $row)
		{
			list ($event_id, $tournament_id, $misc_str) = $row;
			$event_id = (int)$event_id;
			$this->vars->last_event_id = $event_id;
			++$count;

			$misc = $misc_str !== null ? json_decode($misc_str) : null;
			if ($misc === null || !isset($misc->seating) || !isset($misc->seating->rounds))
			{
				continue;
			}

			// Only seatings still written in user IDs are checked here. Once a seating carries a
			// version it was assigned from the seatings table and its rounds hold 0-based player
			// numbers, where 0 is an ordinary player rather than an empty seat - judging those by
			// the user-ID rules would condemn every one of them that seats player 0.
			if (isset($misc->seating->version))
			{
				continue;
			}

			$rounds = json_decode(json_encode($misc->seating->rounds), true);
			if (!is_array($rounds) || seating_has_valid_player_ids($rounds))
			{
				// Well formed as far as the player IDs go. It may still have players playing
				// different numbers of games; say so, but leave it be.
				if (is_array($rounds))
				{
					$normalized = normalize_seating_to_indices($rounds);
					$def = new SeatingDef($normalized);
					if (!seating_is_well_formed($normalized, $def->players, $def->tables, $def->games))
					{
						++$this->vars->uneven;
						$this->log('Event '.$event_id.' (tournament '.$tournament_id.
							') has an uneven seating in misc - players play different numbers of games. Left unchanged.');
					}
				}
				continue;
			}

			++$this->vars->repaired;
			$this->log('Event '.$event_id.' (tournament '.$tournament_id.') has a damaged seating in misc'.
				' ('.$this->_describe_damage($rounds).') - dropping it so extract_seatings can rebuild it from the games.');

			if ($this->dryRun)
			{
				continue;
			}

			Db::begin();
			unset($misc->seating);
			Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
			if ($tournament_id !== null)
			{
				// Let extract_seatings visit this tournament again and rebuild from the games.
				Db::exec('tournament', 'UPDATE tournaments SET flags = flags & ~? WHERE id = ?',
					TOURNAMENT_FLAG_SEATING_EXTRACTED, (int)$tournament_id);
			}
			Db::commit();
		}
		return $count;
	}

	function repair_seatings_task_end()
	{
		$this->log('Damaged seatings '.($this->dryRun ? 'found' : 'repaired').': '.(int)$this->vars->repaired.
			'. Uneven seatings left unchanged: '.(int)$this->vars->uneven.'.');
		if (!$this->dryRun && (int)$this->vars->repaired > 0)
		{
			$this->log('Run seating_optimization.php task extract_seatings next to rebuild them from the game records.');
		}
	}

	private function _describe_damage($rounds)
	{
		$empty_seats = 0;
		$repeats = 0;
		$bad_tables = 0;
		foreach ($rounds as $round)
		{
			if (!is_array($round))
			{
				++$bad_tables;
				continue;
			}
			$seated = array();
			foreach ($round as $table)
			{
				if (!is_array($table) || count($table) != 10)
				{
					++$bad_tables;
					if (!is_array($table))
					{
						continue;
					}
				}
				foreach ($table as $seat)
				{
					$user_id = (int)$seat;
					if ($user_id <= 0)
					{
						++$empty_seats;
					}
					else if (isset($seated[$user_id]))
					{
						++$repeats;
					}
					$seated[$user_id] = true;
				}
			}
		}

		$parts = array();
		if ($empty_seats > 0)
		{
			$parts[] = $empty_seats.' seats with no player';
		}
		if ($repeats > 0)
		{
			$parts[] = $repeats.' repeated players within a round';
		}
		if ($bad_tables > 0)
		{
			$parts[] = $bad_tables.' tables not seating ten';
		}
		return empty($parts) ? 'malformed' : implode(', ', $parts);
	}
}

$updater = new RepairEventSeatings();
$updater->run();

?>
