<?php

require_once 'include/updater.php';
require_once 'include/seating.php';

//-------------------------------------------------------------------------------------------------------
// Brings stored seatings into line with the rule-based restrictions.
//
// A seating hash used to be derived by reading the seating itself: every pair of players that
// never shared a table was recorded as a rule keeping them apart. With one table that is nobody;
// with six it is most of the field, so hashes ended up asserting dozens of separations nobody
// ever asked for, and the optimizer then worked to satisfy them. Restrictions now come from the
// tournament, its club, its leagues and the global list, and the seating is only asked to confirm
// each one.
//
// Two things stand in the way of the stored data catching up, and this script removes both:
//
//  - Tournaments carry TOURNAMENT_FLAG_SEATING_EXTRACTED, so extract_seatings never looks at them
//    again. The flag is cleared.
//  - Events whose seating was rebuilt from game records were given a version, and extract_seatings
//    skips anything with one, taking it for a seating assigned from the seatings table. That froze
//    the extractor's own output. seating_optimization.php no longer writes a version there; the
//    events written before that fix have it removed here, but only where the hash they carry
//    claims restrictions that the rules do not account for. A version that belongs to a genuine
//    assignment is left alone.
//
// Then seatings that no event refers to any more are deleted. They are not lost work worth
// keeping: a hash nothing points at is one the old derivation invented, and ensure_seating_existance
// recreates any row that turns out to be wanted again.
//
// Run it, then extract_seatings, then run it again - both tasks are idempotent, and the second
// pass removes the seatings that the extraction orphaned:
//
//     php repair_seating_rules.php -test -time 300 -log_level 3
//     php seating_optimization.php -test -task extract_seatings -time 900 -log_level 3
//     php repair_seating_rules.php -test -time 300 -log_level 3
//
//     https://<site>/repair_seating_rules.php?time=120&log_level=3
//     https://<site>/seating_optimization.php?task=extract_seatings&time=300&log_level=3
//     https://<site>/repair_seating_rules.php?time=120&log_level=3
//
// Add dry=1 (or -dry) to report what would change without writing anything. Note that a dry run
// cannot show the second pass truthfully, because the extraction it depends on has not happened.
//-------------------------------------------------------------------------------------------------------
class RepairSeatingRules extends Updater
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

	//---------------------------------------------------------------------------------------------------
	// Task 1: let extract_seatings reach everything again.
	//---------------------------------------------------------------------------------------------------

	function unfreeze_seatings_task_start()
	{
		$this->vars->last_event_id = 0;
		$this->vars->unfrozen = 0;
		$this->vars->kept = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}

		list($flagged) = Db::record(get_label('tournament'),
			'SELECT COUNT(*) FROM tournaments WHERE (flags & ?) <> 0', TOURNAMENT_FLAG_SEATING_EXTRACTED);
		$this->log('Tournaments marked as already extracted: ' . (int)$flagged . '.');
		if (!$this->dryRun)
		{
			Db::exec('tournament', 'UPDATE tournaments SET flags = flags & ~?', TOURNAMENT_FLAG_SEATING_EXTRACTED);
			$this->log('Flag cleared - extract_seatings will visit them all again.');
		}
	}

	function unfreeze_seatings_task($items_count)
	{
		$rows = array();
		$query = new DbQuery(
			'SELECT e.id, e.tournament_id, e.club_id, e.misc FROM events e'.
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
			list ($event_id, $tournament_id, $club_id, $misc_str) = $row;
			$event_id = (int)$event_id;
			$this->vars->last_event_id = $event_id;
			++$count;

			$misc = $misc_str !== null ? json_decode($misc_str) : null;
			if ($misc === null || !isset($misc->seating->hash) || !isset($misc->seating->version))
			{
				continue;
			}

			$claimed = count(seating_hash_parts($misc->seating->hash)->restrictions);
			if ($claimed == 0)
			{
				continue; // Nothing asserted, nothing to disprove.
			}

			$explained = $this->_explainable_restrictions($misc, (int)$tournament_id, (int)$club_id);
			if ($claimed <= $explained)
			{
				++$this->vars->kept;
				continue; // The rules account for every restriction - a genuine assignment.
			}

			++$this->vars->unfrozen;
			$this->log('Event '.$event_id.' (tournament '.$tournament_id.') claims '.$claimed.
				' restrictions but the rules account for '.$explained.
				' - dropping its version so extract_seatings derives the hash again.');

			if ($this->dryRun)
			{
				continue;
			}

			unset($misc->seating->version);
			Db::exec('event', 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), $event_id);
		}
		return $count;
	}

	function unfreeze_seatings_task_end()
	{
		$this->log('Events '.($this->dryRun ? 'that would be unfrozen' : 'unfrozen').': '.
			(int)$this->vars->unfrozen.'. Left alone because the rules account for their restrictions: '.
			(int)$this->vars->kept.'.');
		$this->log('Run seating_optimization.php task extract_seatings next, then this script again.');
	}

	// How many restrictions the rules can account for: the separate pairs that apply to this
	// event, plus - for a team tournament whose field does not divide into whole teams - the
	// teammate pairs, which api/ops/event.php writes out individually.
	private function _explainable_restrictions($misc, $tournament_id, $club_id)
	{
		$user_to_index = array();
		if (isset($misc->seating->mapping))
		{
			foreach ((array)$misc->seating->mapping as $slot => $user_id)
			{
				$user_to_index[(int)$user_id] = (int)$slot;
			}
		}
		else if (isset($misc->seating->rounds))
		{
			$rounds = json_decode(json_encode($misc->seating->rounds), true);
			if (!is_array($rounds) || !seating_has_valid_player_ids($rounds))
			{
				// Cannot tell who is who. Say every restriction is explained rather than risk
				// rewriting an event on a guess.
				return PHP_INT_MAX;
			}
			normalize_seating_to_indices($rounds, $user_to_index);
		}
		else
		{
			return PHP_INT_MAX;
		}

		$pairs = get_tournament_separate_pairs($tournament_id, $club_id);
		$explained = count(restrictions_in_indexes($pairs, $user_to_index));

		list($team_size, $teams_by_user) = tournament_teams($tournament_id);
		foreach (teams_in_indexes($teams_by_user, $team_size, $user_to_index) as $members)
		{
			$n = count($members);
			$explained += $n * ($n - 1) / 2;
		}
		return $explained;
	}

	//---------------------------------------------------------------------------------------------------
	// Task 2: drop seatings nothing refers to.
	//---------------------------------------------------------------------------------------------------

	function cleanup_orphan_seatings_task_start()
	{
		$this->vars->last_hash = '';
		$this->vars->deleted = 0;
		$this->vars->runs_lost = 0;
	}

	function cleanup_orphan_seatings_task($items_count)
	{
		$used = array();
		$query = new DbQuery(
			'SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(misc, \'$.seating.hash\')) FROM events'.
			' WHERE JSON_EXTRACT(misc, \'$.seating.hash\') IS NOT NULL');
		while ($row = $query->next())
		{
			$used[$row[0]] = true;
		}

		// Refuse to run on an empty answer. If no event names a seating, something is wrong with
		// the query or the data, and deleting the whole table is not the right response.
		if (empty($used))
		{
			$this->log('No event names a seating at all - refusing to delete anything.');
			return 0;
		}

		// Paged by hash rather than by a plain LIMIT, which would look at the first page over and
		// over and never reach an orphan further down. The cursor only moves forward, so deleting
		// rows behind it changes nothing.
		$orphans = array();
		$seen = 0;
		$query = new DbQuery(
			'SELECT hash, players_runs + tables_runs + numbers_runs FROM seatings'.
			' WHERE hash > ? ORDER BY hash LIMIT '.$items_count,
			$this->vars->last_hash);
		while ($row = $query->next())
		{
			++$seen;
			$this->vars->last_hash = $row[0];
			if (!isset($used[$row[0]]))
			{
				$orphans[] = array($row[0], (int)$row[1]);
			}
		}

		foreach ($orphans as $orphan)
		{
			list($hash, $runs) = $orphan;
			++$this->vars->deleted;
			$this->vars->runs_lost += $runs;
			$this->log('Seating '.$hash.' is used by no event ('.$runs.' optimizer runs) - deleting.');
			if (!$this->dryRun)
			{
				Db::exec(get_label('seating'), 'DELETE FROM seatings WHERE hash = ?', $hash);
			}
		}
		return $seen;
	}

	function cleanup_orphan_seatings_task_end()
	{
		$this->log('Orphan seatings '.($this->dryRun ? 'that would be deleted' : 'deleted').': '.
			(int)$this->vars->deleted.', carrying '.(int)$this->vars->runs_lost.' optimizer runs.');
	}
}

$updater = new RepairSeatingRules();
$updater->run();

?>
