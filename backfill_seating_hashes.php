<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

//-------------------------------------------------------------------------------------------------------
// Writes misc.seating.hash on events that carry a seating but never had one recorded.
//
// seating_optimization.php's extract_seatings task has two paths. When it rebuilds a seating from
// the game records it writes the resulting hash back onto the event. When the seating was already
// sitting in misc it only had to be identified, and until now it recorded nothing - so the link
// between the event and its row in the seatings table existed but was written down nowhere. This
// script fills that in for the events extracted before the omission was fixed; from now on the
// task itself keeps it current.
//
// Only the hash is written. The rounds stay as they are - they are the event's own, in user IDs -
// and no version is written, because a version asserts the seating was taken from the seatings
// table at a particular point in its optimization history, which for these events is not true.
// Nothing reads a hash without a mapping, so no existing behaviour changes; what does change is
// that the seatings page can now say which tournaments use a seating, and a seating with no
// events behind it is genuinely unused rather than merely unrecorded.
//
// An event is only touched when the hash it resolves to is already a row in the seatings table.
// A seating that resolves to no known row is left alone and reported: creating the missing row is
// extract_seatings' job, not this script's.
//
// Run it like any other updater script:
//     php backfill_seating_hashes.php -test -time 120 -log_level 3
//     https://<site>/backfill_seating_hashes.php?time=60&log_level=3
// Add dry=1 (or -dry) to report what would change without writing anything.
// It is safe to run repeatedly: an event that already names the right seating is skipped.
//-------------------------------------------------------------------------------------------------------
class BackfillSeatingHashes extends Updater
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

	function backfill_hashes_task_start()
	{
		$this->vars->last_event_id = 0;
		$this->vars->written = 0;
		$this->vars->unknown = 0;
		$this->vars->damaged = 0;
		$this->vars->uneven = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}
	}

	function backfill_hashes_task($items_count)
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
			if (isset($misc->seating->hash))
			{
				continue; // Already recorded.
			}

			$rounds = json_decode(json_encode($misc->seating->rounds), true);
			if (!is_array($rounds))
			{
				continue;
			}

			// Check the IDs before normalising them: afterwards a placeholder value is
			// indistinguishable from a player number. See seating_has_valid_player_ids().
			if (!seating_has_valid_player_ids($rounds))
			{
				++$this->vars->damaged;
				$this->log('Event '.$event_id.' (tournament '.$tournament_id.') has a damaged seating in misc'.
					' - skipped. Run repair_event_seatings.php for those.');
				continue;
			}

			$hash = $this->_hash_of($tournament_id, $rounds);
			if ($hash === null)
			{
				++$this->vars->uneven;
				$this->log('Event '.$event_id.' (tournament '.$tournament_id.') has an uneven seating in misc'.
					' - players play different numbers of games. Left unchanged.');
				continue;
			}

			$known = (new DbQuery('SELECT hash FROM seatings WHERE hash = ?', $hash))->next();
			if (!$known)
			{
				++$this->vars->unknown;
				$this->log('Event '.$event_id.' (tournament '.$tournament_id.') resolves to '.$hash.
					', which is not in the seatings table - left unchanged. Run seating_optimization.php'.
					' task extract_seatings to create it.');
				continue;
			}

			++$this->vars->written;
			$this->log('Event '.$event_id.' (tournament '.$tournament_id.') uses seating '.$hash.'.');

			if ($this->dryRun)
			{
				continue;
			}

			$misc->seating->hash = $hash;
			Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
		}
		return $count;
	}

	function backfill_hashes_task_end()
	{
		$this->log('Events '.($this->dryRun ? 'that would be linked' : 'linked to their seating').': '.
			(int)$this->vars->written.'.');
		$this->log('Left unchanged: '.(int)$this->vars->unknown.' naming a seating that is not in the table, '.
			(int)$this->vars->damaged.' damaged, '.(int)$this->vars->uneven.' uneven.');
	}

	// The hash an event's seating resolves to, or null when the seating does not describe a valid
	// configuration. This is deliberately the same sequence extract_seatings uses, so an event
	// lands on exactly the row that task would have created for it.
	private function _hash_of($tournament_id, $rounds)
	{
		list($team_size, $teams_by_user) = tournament_teams($tournament_id);
		$user_to_index = array();
		$normalized = reindex_seating(normalize_seating_to_indices($rounds, $user_to_index));
		$teams = teams_in_indexes($teams_by_user, $team_size, $user_to_index);

		$def = new SeatingDef($normalized, 0, 0, null, $team_size, $teams);
		$total_slots = $def->players * $def->games;
		if ($total_slots <= 0 || $total_slots % 10 !== 0 || $def->tables * 10 > $def->players)
		{
			return null;
		}
		if (!seating_is_well_formed($normalized, $def->players, $def->tables, $def->games))
		{
			return null;
		}
		$def->normalizeRestrictions();
		return $def->hash;
	}
}

$updater = new BackfillSeatingHashes();
$updater->run();

?>
