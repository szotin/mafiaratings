<?php

require_once 'include/updater.php';
require_once 'include/scoring.php';

//-------------------------------------------------------------------------------------------------------
// Teaches the stored scorings about SCORING_FLAG_TECH_FOULS.
//
// Leaving the game over two technical fouls used to raise the same scoring flag as leaving it
// over four warnings, so a rule written for one applied to the other without anybody saying so.
// The two are told apart now, which means a scoring that only mentions the warnings would quietly
// stop penalising a removal for technical fouls. This script closes that gap by saying out loud
// what the scorings used to say by accident: wherever a scoring asks about the warnings, it now
// asks about the technical fouls as well.
//
// There are two ways a scoring can ask, and both are handled:
//
//  - A policy carries a "matter" mask, and the mask must match in full - every bit of it has to
//    be in the player's flags. Adding the technical fouls bit to an existing mask would demand
//    both at once, so the policy is copied instead: the copy is the same rule with the warnings
//    bit swapped for the technical fouls one, and it goes right after the original.
//  - A "points" or "mvp" expression calls matter(12). matter() takes any number of codes and is
//    true when any one of them happened, so the call becomes matter(12, 27) and reads the same
//    way round whichever foul took the player out of the game.
//
// Nothing is rescored. A game played before the split carries the warnings flag whatever took the
// player out, and keeps the points it was given; the difference shows from the next game written
// down. Scorings that never mention the warnings are left exactly as they are.
//
// Running it twice changes nothing the second time: a policy whose copy is already there is left
// alone, and an expression that already names matter(27) is not rewritten again.
//
//     php split_tech_fouls_scoring.php -test -time 300 -log_level 3
//     https://<site>/split_tech_fouls_scoring.php?time=120&log_level=3
//
// Add dry=1 (or -dry) to report what would change without writing anything.
//-------------------------------------------------------------------------------------------------------
class SplitTechFoulsScoring extends Updater
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

	function split_scorings_task_start()
	{
		$this->vars->last_scoring_id = 0;
		$this->vars->last_version = 0;
		$this->vars->scorings_changed = 0;
		$this->vars->policies_added = 0;
		$this->vars->expressions_rewritten = 0;
		$this->vars->broken = 0;

		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}

		list($total) = Db::record(get_label('score'), 'SELECT COUNT(*) FROM scoring_versions');
		$this->log('Scoring versions stored: ' . (int)$total . '.');
		$this->log('The warnings matter is ' . $this->_matter_num(SCORING_FLAG_WARNINGS_4) .
			', the technical fouls one is ' . $this->_matter_num(SCORING_FLAG_TECH_FOULS) . '.');
	}

	function split_scorings_task($items_count)
	{
		// Read the batch out before touching anything: the update below would otherwise run
		// while this query is still being walked.
		$rows = array();
		$query = new DbQuery(
			'SELECT scoring_id, version, scoring FROM scoring_versions'.
			' WHERE scoring_id > ? OR (scoring_id = ? AND version > ?)'.
			' ORDER BY scoring_id, version LIMIT ' . $items_count,
			$this->vars->last_scoring_id, $this->vars->last_scoring_id, $this->vars->last_version);
		while ($row = $query->next())
		{
			$rows[] = $row;
		}

		$count = 0;
		foreach ($rows as $row)
		{
			list ($scoring_id, $version, $scoring_str) = $row;
			$scoring_id = (int)$scoring_id;
			$version = (int)$version;
			$this->vars->last_scoring_id = $scoring_id;
			$this->vars->last_version = $version;
			++$count;

			$scoring = json_decode($scoring_str);
			if (is_null($scoring) || !is_object($scoring))
			{
				++$this->vars->broken;
				$this->log('Scoring ' . $scoring_id . ' version ' . $version . ' is not readable json - skipped.',
					LOG_ALL, LOG_LEVEL_WARNING);
				continue;
			}

			list ($added, $rewritten) = $this->_split($scoring);
			if ($added <= 0 && $rewritten <= 0)
			{
				continue;
			}

			++$this->vars->scorings_changed;
			$this->vars->policies_added += $added;
			$this->vars->expressions_rewritten += $rewritten;
			$this->log('Scoring ' . $scoring_id . ' version ' . $version . ': ' .
				$added . ' policies copied, ' . $rewritten . ' expressions rewritten.');

			if (!$this->dryRun)
			{
				Db::exec(get_label('score'),
					'UPDATE scoring_versions SET scoring = ? WHERE scoring_id = ? AND version = ?',
					json_encode($scoring), $scoring_id, $version);
				// The standings that were worked out with the old wording are no longer what
				// this scoring says. Thrown away the same way api/ops/scoring.php throws them
				// away when somebody edits a version by hand.
				Db::exec(get_label('score'),
					'DELETE FROM event_scores_cache WHERE scoring_id = ? AND scoring_version = ?', $scoring_id, $version);
				Db::exec(get_label('score'),
					'DELETE FROM tournament_scores_cache WHERE scoring_id = ? AND scoring_version = ?', $scoring_id, $version);
			}
		}
		return $count;
	}

	function split_scorings_task_end()
	{
		$this->log('Scorings ' . ($this->dryRun ? 'that would be changed' : 'changed') . ': ' .
			(int)$this->vars->scorings_changed . '; policies copied: ' . (int)$this->vars->policies_added .
			'; expressions rewritten: ' . (int)$this->vars->expressions_rewritten . '.');
		if ((int)$this->vars->broken > 0)
		{
			$this->log('Unreadable scorings skipped: ' . (int)$this->vars->broken . '.', LOG_ALL, LOG_LEVEL_WARNING);
		}
	}

	//---------------------------------------------------------------------------------------------------
	// The scoring itself
	//---------------------------------------------------------------------------------------------------

	// Every group of a scoring is an array of policies - main, extra, legacy, penalty, night1 and
	// counters. They are found by shape rather than by name, so a group nobody has thought of yet
	// is treated like the rest.
	private function _split($scoring)
	{
		$added = 0;
		$rewritten = 0;
		foreach ($scoring as $group_name => $group)
		{
			if (!is_array($group))
			{
				continue;
			}

			$new_group = array();
			foreach ($group as $policy)
			{
				if (!is_object($policy))
				{
					$new_group[] = $policy;
					continue;
				}

				// The expressions are rewritten before the copy is taken, so the copy carries the
				// rewritten ones too.
				foreach (array('points', 'mvp') as $field)
				{
					if (isset($policy->$field) && is_string($policy->$field))
					{
						$before = $policy->$field;
						$policy->$field = $this->_rewrite($before);
						if ($policy->$field !== $before)
						{
							++$rewritten;
						}
					}
				}
				$new_group[] = $policy;

				if (!isset($policy->matter) || ((int)$policy->matter & SCORING_FLAG_WARNINGS_4) == 0)
				{
					continue;
				}

				$twin = json_decode(json_encode($policy)); // a copy of its own, not a second name for it
				$twin->matter = ((int)$policy->matter & ~SCORING_FLAG_WARNINGS_4) | SCORING_FLAG_TECH_FOULS;
				if ($this->_contains($group, $twin))
				{
					continue; // already done on an earlier run
				}
				$new_group[] = $twin;
				++$added;
			}
			$scoring->$group_name = $new_group;
		}
		return array($added, $rewritten);
	}

	// matter() asks whether something happened to the player, and takes any number of codes -
	// any one of them is enough. So a call that asks about the four warnings is given the
	// technical fouls to ask about as well, in the same call.
	//
	// Every call is taken apart rather than matched as text: the codes are written with whatever
	// spacing the author of the scoring used, and a call that already names the technical fouls
	// is left alone, which is what makes a second run of this script change nothing.
	private function _rewrite($expression)
	{
		$warnings = (string)$this->_matter_num(SCORING_FLAG_WARNINGS_4);
		$tech_fouls = (string)$this->_matter_num(SCORING_FLAG_TECH_FOULS);

		$expression = preg_replace_callback('/matter\s*\(([^()]*)\)/',
			function ($match) use ($warnings, $tech_fouls)
			{
				$args = array_map('trim', explode(',', $match[1]));
				if (!in_array($warnings, $args) || in_array($tech_fouls, $args))
				{
					return $match[0];
				}
				$args[] = $tech_fouls;
				return 'matter(' . implode(', ', $args) . ')';
			},
			$expression);

		return $this->_merge_calls($expression);
	}

	// matter(12) || matter(13) says the same thing as matter(12, 13), so the run is folded into
	// one call while we are in here anyway.
	//
	// Only where it means the same, though. || binds loosest of the operators in these
	// expressions, so folding a run is safe only when nothing tighter reaches into either end of
	// it: a && before the run would own its first call, and a && after it would own the last one,
	// and in both cases the fold would change what the expression says. So a run is left alone
	// unless what stands on each side of it is the start or the end of the expression, a bracket,
	// or one of the characters that separate the parts of a ?: - which all bind looser still.
	private function _merge_calls($expression)
	{
		if (!preg_match_all('/matter\s*\([^()]*\)(?:\s*\|\|\s*matter\s*\([^()]*\))+/',
			$expression, $runs, PREG_OFFSET_CAPTURE))
		{
			return $expression;
		}

		// Back to front, so that replacing a run leaves the offsets of the ones before it alone.
		foreach (array_reverse($runs[0]) as $run)
		{
			list ($text, $offset) = $run;
			$before = rtrim(substr($expression, 0, $offset));
			$after = ltrim(substr($expression, $offset + strlen($text)));
			if ($before !== '' && !preg_match('/[(?:,]$/', $before))
			{
				continue;
			}
			if ($after !== '' && !preg_match('/^[)?:,]/', $after))
			{
				continue;
			}

			$args = array();
			preg_match_all('/matter\s*\(([^()]*)\)/', $text, $calls);
			foreach ($calls[1] as $call_args)
			{
				foreach (array_map('trim', explode(',', $call_args)) as $arg)
				{
					if ($arg !== '' && !in_array($arg, $args))
					{
						$args[] = $arg;
					}
				}
			}
			if (empty($args))
			{
				continue; // matter() || matter() - nothing to merge, and nothing we understand
			}

			$expression = substr_replace($expression, 'matter(' . implode(', ', $args) . ')',
				$offset, strlen($text));
		}
		return $expression;
	}

	private function _contains($group, $policy)
	{
		$wanted = json_encode($policy);
		foreach ($group as $existing)
		{
			if (json_encode($existing) === $wanted)
			{
				return true;
			}
		}
		return false;
	}

	// Which matter a flag is: the flags are single bits and a matter is the number of the bit.
	private function _matter_num($flag)
	{
		$num = 0;
		while ($flag > 1)
		{
			$flag >>= 1;
			++$num;
		}
		return $num;
	}
}

$updater = new SplitTechFoulsScoring();
$updater->run();

?>
