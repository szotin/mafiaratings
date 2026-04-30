<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/json.php';

define('SEATING_MAX_PLAYERS_OPTIMIZATIONS', 1000000);
define('SEATING_MAX_NUMBERS_OPTIMIZATIONS', 1000000);
define('SEATING_MAX_TABLES_OPTIMIZATIONS', 1000);

define('PAIR_POLICY_SEPARATE', 0);
define('PAIR_POLICY_AVOID', 1);
define('PAIR_POLICY_WELCOME', 2);
define('PAIR_POLICY_NOTHING', 3);

function get_pair_policy_name($policy)
{
	switch ($policy)
	{
	case PAIR_POLICY_SEPARATE:
		return get_label('Separate players.');
		break;
	case PAIR_POLICY_AVOID:
		return get_label('Reduce number of games together but do not separate completely.');
		break;
	case PAIR_POLICY_NOTHING:
		return get_label('As usual. No separation.');
		break;
	case PAIR_POLICY_WELCOME:
		return get_label('Increase number of games together.');
		break;
	}
	return '';
}

// Returns array of pair objects for all pairs affecting this tournament,
// applying priority rules: tournament_pairs > club_pairs > league_pairs > pairs.
// For conflicts between multiple leagues, the one with smaller abs(policy) wins.
// Each returned object has:
//   user1_id, user1_name, user1_flags, user1_tournament_flags, user1_club_flags
//   user2_id, user2_name, user2_flags, user2_tournament_flags, user2_club_flags
//   policy, source (display string)
function get_tournament_pairs($tournament_id, $club_id, $lang, $accepted_only = false)
{
	$players_list = '';
	$delim = '';
	$accepted_filter = $accepted_only ? ' AND (flags & ' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED . ') = 0' : '';
	$query = new DbQuery('SELECT user_id FROM tournament_regs WHERE tournament_id = ?' . $accepted_filter, $tournament_id);
	while ($row = $query->next())
	{
		$players_list .= $delim . (int)$row[0];
		$delim = ',';
	}

	if (empty($players_list))
	{
		return array();
	}

	// $pairs_map: key = "user1_id_user2_id" => stdClass with all pair fields + priority
	$pairs_map = array();

	$add_pair = function($row, $priority, $source) use (&$pairs_map)
	{
		list ($user1_id, $user1_name, $user1_flags, $user1_tournament_flags, $user1_club_flags,
		      $user2_id, $user2_name, $user2_flags, $user2_tournament_flags, $user2_club_flags,
		      $policy) = $row;
		$key = $user1_id . '_' . $user2_id;
		if (isset($pairs_map[$key]))
		{
			$existing = $pairs_map[$key];
			if ($priority > $existing->priority)
			{
				$existing->policy = (int)$policy;
				$existing->priority = $priority;
				$existing->source = $source;
			}
			else if ($priority == $existing->priority && abs((int)$policy) < abs($existing->policy))
			{
				// League conflict: prefer smaller absolute policy value
				$existing->policy = (int)$policy;
				$existing->source = $source;
			}
		}
		else
		{
			$pair = new stdClass();
			$pair->user1_id = (int)$user1_id;
			$pair->user1_name = $user1_name;
			$pair->user1_flags = (int)$user1_flags;
			$pair->user1_tournament_flags = isset($user1_tournament_flags) ? (int)$user1_tournament_flags : 0;
			$pair->user1_club_flags = isset($user1_club_flags) ? (int)$user1_club_flags : 0;
			$pair->user2_id = (int)$user2_id;
			$pair->user2_name = $user2_name;
			$pair->user2_flags = (int)$user2_flags;
			$pair->user2_tournament_flags = isset($user2_tournament_flags) ? (int)$user2_tournament_flags : 0;
			$pair->user2_club_flags = isset($user2_club_flags) ? (int)$user2_club_flags : 0;
			$pair->policy = (int)$policy;
			$pair->priority = $priority;
			$pair->source = $source;
			$pairs_map[$key] = $pair;
		}
	};

	// Priority 0: global pairs
	$query = new DbQuery(
		'SELECT u1.id, nu1.name, u1.flags, tu1.flags, cu1.flags,' .
		' u2.id, nu2.name, u2.flags, tu2.flags, cu2.flags, p.policy' .
		' FROM pairs p' .
		' JOIN users u1 ON u1.id = p.user1_id' .
		' JOIN users u2 ON u2.id = p.user2_id' .
		' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $lang . ') <> 0' .
		' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $lang . ') <> 0' .
		' LEFT OUTER JOIN tournament_regs tu1 ON tu1.user_id = u1.id AND tu1.tournament_id = ?' .
		' LEFT OUTER JOIN tournament_regs tu2 ON tu2.user_id = u2.id AND tu2.tournament_id = ?' .
		' LEFT OUTER JOIN club_regs cu1 ON cu1.user_id = u1.id AND cu1.club_id = ?' .
		' LEFT OUTER JOIN club_regs cu2 ON cu2.user_id = u2.id AND cu2.club_id = ?' .
		' WHERE u1.id IN (' . $players_list . ') AND u2.id IN (' . $players_list . ')',
		$tournament_id, $tournament_id, $club_id, $club_id);
	while ($row = $query->next())
	{
		$add_pair($row, 0, get_label('Global'));
	}

	// Priority 1: league pairs (multiple leagues possible; conflict resolved by min abs(policy))
	$query = new DbQuery(
		'SELECT u1.id, nu1.name, u1.flags, tu1.flags, cu1.flags,' .
		' u2.id, nu2.name, u2.flags, tu2.flags, cu2.flags, p.policy, l.name' .
		' FROM league_pairs p' .
		' JOIN leagues l ON l.id = p.league_id' .
		' JOIN users u1 ON u1.id = p.user1_id' .
		' JOIN users u2 ON u2.id = p.user2_id' .
		' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $lang . ') <> 0' .
		' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $lang . ') <> 0' .
		' LEFT OUTER JOIN tournament_regs tu1 ON tu1.user_id = u1.id AND tu1.tournament_id = ?' .
		' LEFT OUTER JOIN tournament_regs tu2 ON tu2.user_id = u2.id AND tu2.tournament_id = ?' .
		' LEFT OUTER JOIN club_regs cu1 ON cu1.user_id = u1.id AND cu1.club_id = ?' .
		' LEFT OUTER JOIN club_regs cu2 ON cu2.user_id = u2.id AND cu2.club_id = ?' .
		' WHERE u1.id IN (' . $players_list . ') AND u2.id IN (' . $players_list . ')' .
		' AND l.id IN (SELECT s.league_id FROM series_tournaments st JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?)',
		$tournament_id, $tournament_id, $club_id, $club_id, $tournament_id);
	while ($row = $query->next())
	{
		$add_pair($row, 1, $row[11]);
	}

	// Priority 2: club pairs
	$query = new DbQuery(
		'SELECT u1.id, nu1.name, u1.flags, tu1.flags, cu1.flags,' .
		' u2.id, nu2.name, u2.flags, tu2.flags, cu2.flags, p.policy, c.name' .
		' FROM club_pairs p' .
		' JOIN clubs c ON c.id = p.club_id' .
		' JOIN users u1 ON u1.id = p.user1_id' .
		' JOIN users u2 ON u2.id = p.user2_id' .
		' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $lang . ') <> 0' .
		' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $lang . ') <> 0' .
		' LEFT OUTER JOIN tournament_regs tu1 ON tu1.user_id = u1.id AND tu1.tournament_id = ?' .
		' LEFT OUTER JOIN tournament_regs tu2 ON tu2.user_id = u2.id AND tu2.tournament_id = ?' .
		' LEFT OUTER JOIN club_regs cu1 ON cu1.user_id = u1.id AND cu1.club_id = ?' .
		' LEFT OUTER JOIN club_regs cu2 ON cu2.user_id = u2.id AND cu2.club_id = ?' .
		' WHERE u1.id IN (' . $players_list . ') AND u2.id IN (' . $players_list . ')' .
		' AND c.id = ?',
		$tournament_id, $tournament_id, $club_id, $club_id, $club_id);
	while ($row = $query->next())
	{
		$add_pair($row, 2, $row[11]);
	}

	// Priority 3: tournament pairs (highest priority)
	$query = new DbQuery(
		'SELECT u1.id, nu1.name, u1.flags, tu1.flags, cu1.flags,' .
		' u2.id, nu2.name, u2.flags, tu2.flags, cu2.flags, p.policy' .
		' FROM tournament_pairs p' .
		' JOIN users u1 ON u1.id = p.user1_id' .
		' JOIN users u2 ON u2.id = p.user2_id' .
		' JOIN names nu1 ON nu1.id = u1.name_id AND (nu1.langs & ' . $lang . ') <> 0' .
		' JOIN names nu2 ON nu2.id = u2.name_id AND (nu2.langs & ' . $lang . ') <> 0' .
		' LEFT OUTER JOIN tournament_regs tu1 ON tu1.user_id = u1.id AND tu1.tournament_id = p.tournament_id' .
		' LEFT OUTER JOIN tournament_regs tu2 ON tu2.user_id = u2.id AND tu2.tournament_id = p.tournament_id' .
		' LEFT OUTER JOIN club_regs cu1 ON cu1.user_id = u1.id AND cu1.club_id = ?' .
		' LEFT OUTER JOIN club_regs cu2 ON cu2.user_id = u2.id AND cu2.club_id = ?' .
		' WHERE p.tournament_id = ?',
		$club_id, $club_id, $tournament_id);
	while ($row = $query->next())
	{
		$add_pair($row, 3, get_label('In this tournament'));
	}

	$result = array_values(array_filter($pairs_map, function($pair) { return $pair->policy != PAIR_POLICY_NOTHING; }));
	usort($result, function($a, $b) { return $a->user1_id - $b->user1_id; });
	return $result;
}

function format_seating_restrictions($parts)
{
	if (empty($parts))
	{
		return '';
	}
	$groups = array();
	foreach ($parts as $part)
	{
		$players = array();
		$tokens = explode(':', $part);
		foreach ($tokens as $token)
		{
			if (strpos($token, '-') !== false)
			{
				list($from, $to) = explode('-', $token, 2);
				for ($i = (int)$from; $i <= (int)$to; $i++)
				{
					$players[] = $i;
				}
			}
			else
			{
				$players[] = (int)$token;
			}
		}
		$groups[] = '(' . implode(', ', $players) . ')';
	}
	return implode(' &nbsp; ', $groups);
}

function _generate_next_restriction_group_level($players, $groups = null)
{
	$next = array();
	if ($groups == null)
	{
		$k = array_keys($players);
		sort($k);
		foreach ($k as $n1)
		{
			foreach ($players[$n1] as $n2)
			{
				if ($n2 > $n1)
				{
					$next[] = array($n1, $n2);
				}
			}
		}
	}
	else if (count($groups) > 0)
	{
		$last = count($groups[0]) - 1;
		foreach ($groups as $g)
		{
			$n1 = $g[$last];
			foreach ($players[$n1] as $n2)
			{
				if ($n2 > $n1)
				{
					$belongs = true;
					foreach ($g as $n3)
					{
						if (array_search($n2, $players[$n3], true) === false)
						{
							$belongs = false;
						}
					}
					if ($belongs)
					{
						$new_g = $g;
						$new_g[] = $n2;
						$next[] = $new_g;
					}
				}
			}
		}
	}
	if (count($next) == 0)
	{
		$next = null;
	}
	return $next;
}

function _find_longest_groups($players) 
{
	$groups = _generate_next_restriction_group_level($players);
	if ($groups != null)
	{
		while (($next = _generate_next_restriction_group_level($players, $groups)) != null)
		{
			$groups = $next;
		}
	}
	return $groups;
}

class SeatingDef
{
	public $hash;
	public $players;
	public $tables;
	public $games;
	public $restrictions;
	
	function __construct($hash, $tables = 0, $games = 0, $restrictions = null)
	{
		if (is_numeric($hash))
		{
			$this->players = (int)$hash;
			$this->tables = (int)$tables;
			$this->games = (int)$games;
			if ($restrictions == null || $this->players < 12)
			{
				$this->restrictions = array();
			}
			else
			{
				$this->restrictions = $restrictions;
			}
			$this->generateHash();
		}
		else
		{
			$this->hash = $hash;
			$this->restrictions = array();
			$parts = explode('_', $hash);
			if (count($parts) < 3)
			{
				$this->players = 0;
				$this->tables = 0;
				$this->games = 0;
			}
			else
			{
				$this->players      = (int)$parts[0];
				$this->tables       = (int)$parts[1];
				$this->games        = (int)$parts[2];
				if ($this->players >= 12)
				{
					for ($i = 3; $i < count($parts); ++$i)
					{
						$group = array();
						// Each segment is separated by ':'; a segment may be "a" or "a-b" (inclusive range).
						$segments = explode(':', $parts[$i]);
						foreach ($segments as $seg)
						{
							if (strpos($seg, '-') !== false)
							{
								list($from, $to) = explode('-', $seg, 2);
								for ($n = (int)$from; $n <= (int)$to; ++$n)
								{
									$group[] = $n;
								}
							}
							else
							{
								$group[] = (int)$seg;
							}
						}
						if (count($group) > 0)
						{
							$this->restrictions[] = $group;
						}
					}
				}
			}
		}
	}
	
	private function generateHash()
	{
		$this->hash = $this->players . '_' . $this->tables . '_' . $this->games;
		foreach ($this->restrictions as $r)
		{
			$i = 0;
			$rCount = count($r);
			$h = '';
			while ($i < $rCount) 
			{
				$start = $i;
				
				// Look ahead to find the end of a consecutive sequence
				while ($i + 1 < $rCount && $r[$i + 1] === $r[$i] + 1)
				{
					++$i;
				}

				if (!empty($h))
				{
					$h .= ':';
				}
				$h .= $r[$start];
				if ($i > $start)
				{
					$h .= '-' . $r[$i];
				}
				++$i;
			}
			$this->hash .= '_' . $h;
		}
	}
	
	// Assigns $players_list (exactly $this->tables * 10 entries) to $this->tables tables
	// while minimising the number of cannot_meet constraint violations.
	// Returns an array [$table => [player, ...]].
	private function _gisAssignPlayersToTables($players_list, $tables, $conflict_map)
	{
		// Shuffle first, then sort most-constrained players first so they get optimal placement.
		shuffle($players_list);
		usort($players_list, function($a, $b) use ($conflict_map)
		{
			$ca = isset($conflict_map[$a]) ? count($conflict_map[$a]) : 0;
			$cb = isset($conflict_map[$b]) ? count($conflict_map[$b]) : 0;
			return $cb - $ca;
		});

		$tables_arr = array();
		for ($t = 0; $t < $tables; $t++)
		{
			$tables_arr[$t] = array();
		}

		foreach ($players_list as $player)
		{
			$best_table = -1;
			$best_conflicts = PHP_INT_MAX;

			// Shuffle table order so that tables with equal scores are chosen randomly.
			$table_order = range(0, $tables - 1);
			shuffle($table_order);

			foreach ($table_order as $t)
			{
				if (count($tables_arr[$t]) >= 10)
				{
					continue;
				}

				$conflicts = 0;
				foreach ($tables_arr[$t] as $seated)
				{
					if (isset($conflict_map[$player][$seated]))
					{
						$conflicts++;
					}
				}

				if ($conflicts < $best_conflicts)
				{
					$best_conflicts = $conflicts;
					$best_table = $t;
					if ($conflicts === 0)
					{
						break; // Cannot do better than zero conflicts.
					}
				}
			}

			$tables_arr[$best_table][] = $player;
		}

		return $tables_arr;
	}

	// Returns an array [$round => [player, ...]] where each player appears in exactly
	// $this->games games total. $round_table_counts[$r] gives the number of active tables
	// in round $r, so each round uses $round_table_counts[$r] * 10 player slots.
	// Uses a greedy algorithm: each round picks the needed players who have the most
	// remaining games to play, with randomisation to break ties.
	private function _gisGenerateRoundPlayerLists($round_table_counts)
	{
		$remaining = array_fill(0, $this->players, $this->games);
		$round_lists = array();

		foreach ($round_table_counts as $r => $table_count)
		{
			$seats_this_round = $table_count * 10;

			$candidates = range(0, $this->players - 1);
			shuffle($candidates); // randomise to break ties randomly

			// Sort descending by remaining games needed so the most "hungry" players go first.
			usort($candidates, function($a, $b) use ($remaining)
			{
				return $remaining[$b] - $remaining[$a];
			});

			$active = array_slice($candidates, 0, $seats_this_round);
			$round_lists[$r] = $active;

			foreach ($active as $p)
			{
				$remaining[$p]--;
			}
		}

		return $round_lists;
	}
	
	// Generates an initial seating arrangement for a tournament.
	//
	// Returns a 3-dimensional array: [round][table][seat] = player_number
	// Each round has at most $this->tables tables (the last round may have fewer).
	// Throws Exception if a valid equal schedule is mathematically impossible.
	function generateInitialSeating()
	{
		if ($this->players < 10)
		{
			throw new Exception(
				"Not enough players ($this->players). Need at least 10 players."
			);
		}

		$total_slots = $this->players * $this->games;
		if ($total_slots % 10 !== 0)
		{
			throw new Exception(
				"Cannot create an equal schedule: $this->players players x $this->games games = $total_slots total slots, " .
				"which is not divisible by 10 (seats per game). " .
				"Each player cannot play the same number of games under these parameters."
			);
		}

		$total_games = $total_slots / 10;

		// Build per-round table counts: most rounds have $this->tables tables,
		// the last round may have fewer if total_games is not divisible by $this->tables.
		$full_rounds      = (int)($total_games / $this->tables);
		$remainder_tables = $total_games % $this->tables;
		$round_table_counts = array();
		for ($r = 0; $r < $full_rounds; $r++)
		{
			$round_table_counts[] = $this->tables;
		}
		if ($remainder_tables > 0)
		{
			$round_table_counts[] = $remainder_tables;
		}
		$rounds = count($round_table_counts);

		// Build conflict lookup: $conflict_map[$a][$b] = true means a and b should not share a table.
		$conflict_map = array();
		foreach ($this->restrictions as $group)
		{
			$n = count($group);
			for ($i = 0; $i < $n; $i++)
			{
				for ($j = $i + 1; $j < $n; $j++)
				{
					$a = $group[$i];
					$b = $group[$j];
					$conflict_map[$a][$b] = true;
					$conflict_map[$b][$a] = true;
				}
			}
		}

		// Determine which players are active in each round so that every player
		// participates in exactly $this->games games total.
		$round_player_lists = $this->_gisGenerateRoundPlayerLists($round_table_counts, $this->games, 10);

		// Assign the active players in each round to tables, respecting cannot_meet.
		$result = array();
		for ($r = 0; $r < $rounds; $r++)
		{
			$result[$r] = $this->_gisAssignPlayersToTables($round_player_lists[$r], $round_table_counts[$r], $conflict_map);
		}
		return $result;
	}
	
	// Moves all restriction to the lowest possibe munbers. And unites restrictions if possible.
	//
	// It also builds a unique hash for a seating configuration.
	// Format: "{players}_{tables}_{games}[_{restriction_group}...]"
	// Each restriction group is a compact range/list of mapped player indices,
	// e.g. "0-2" (players 0,1,2 must not share a table) or "0:2:5".
	// 
	// Returns a mapping between old players list and the new players list. The format is for example {"3": 0, "2": 1, "1": 2, ...} 
	// where 3 is the index in the existing players array and 0 is the index in the new normalized players array.
	//
	// Example: 
	// $seating = new SeatingDef(20, 2, 10, [[1, 2], [2, 3], [1, 3], [1, 4], [4, 5], [1, 5], [2, 9]]);
	// $seating->normalizeRestrictions();
	// returns { "3": 0, "2": 1, "1": 2, "5": 3, "4": 4, "9": 5 }
	// the hash is "20_2_10_0-2_2-4_1:5" after that
	// restrictions: [[0, 1, 2], [2, 3, 4], [1, 5]]
	function normalizeRestrictions()
	{
		$restrictions_by_player = array();
		for ($i = 0; $i < count($this->restrictions); ++$i)
		{
			for ($j = 0; $j < count($this->restrictions[$i]); ++$j)
			{
				for ($k = $j + 1; $k < count($this->restrictions[$i]); ++$k)
				{
					if ($j != $k)
					{
						$n1 = $this->restrictions[$i][$j];
						$n2 = $this->restrictions[$i][$k];
						$add = true;
						if (array_key_exists($n1, $restrictions_by_player))
						{
							foreach ($restrictions_by_player[$n1] as $n)
							{
								if ($n == $n2)
								{
									$add = false;
								}
							}
						}
						if ($add)
						{
							$restrictions_by_player[$n1][] = $n2;
							$restrictions_by_player[$n2][] = $n1;
						}
					}
				}
			}
		}
		
		$restrictions = array();
		$restrictions_by_player_copy = $restrictions_by_player;
		while(count($groups = _find_longest_groups($restrictions_by_player_copy)) > 0)
		{
			foreach ($groups as $group)
			{
				$restrictions[] = $group;
				for ($i = 0; $i < count($group); ++$i)
				{
					$n1 = $group[$i];
					for ($j = 0; $j < count($group); ++$j)
					{
						if ($i != $j)
						{
							$n2 = $group[$j];
							$key = array_search($n2, $restrictions_by_player_copy[$n1]);
							if ($key !== false)
							{
								array_splice($restrictions_by_player_copy[$n1], $key, 1);
							}
						}
					}
					if (count($restrictions_by_player_copy[$n1]) == 0)
					{
						unset($restrictions_by_player_copy[$n1]);
					}
				}
			}
		}
		usort($restrictions, function($a, $b) use ($restrictions_by_player)
		{
			$countA = count($a);
			$countB = count($b);
			if ($countA !== $countB)
			{
				return $countB - $countA;
			}
			
			$aSum = 0;
			$aMax = 0;
			foreach ($a as $i)
			{
				$count = count($restrictions_by_player[$i]);
				$aSum += $count;
				$aMax = max($aMax, $count);
			}
			
			$bSum = 0;
			$bMax = 0;
			foreach ($b as $i)
			{
				$count = count($restrictions_by_player[$i]);
				$bSum += $count;
				$bMax = max($bMax, $count);
			}
			
			if ($aSum !== $bSum)
			{
				return $bSum - $aSum;
			}
			return $bMax - $aMax;
		});
		
		for ($i = 0; $i < count($restrictions); ++$i)
		{
			usort($restrictions[$i], function($a, $b) use ($restrictions_by_player) { return count($restrictions_by_player[$a]) - count($restrictions_by_player[$b]); });
		}
		$mapping = array();
		$this->restrictions = array();
		$playerIndex = 0;
		foreach ($restrictions as $r)
		{
			$a = array();
			foreach ($r as $idx)
			{
				if (!array_key_exists($idx, $mapping))
				{
					$mapping[$idx] = $playerIndex++;
				}
				$a[] = $mapping[$idx];
			}
			sort($a);
			$this->restrictions[] = $a;
		}
		$this->generateHash();
		return $mapping;
	}
	
	private function _createPlayersExpectations($seating)
	{
		if (isset($this->playersExpectations))
		{
			return;
		}
		
		$this->playersExpectations = array();
		foreach ($this->restrictions as $group)
		{
			$n = count($group);
			for ($a = 0; $a < $n; $a++)
			{
				for ($b = $a + 1; $b < $n; $b++)
				{
					$pi = $group[$a];
					$pj = $group[$b];
					if (!array_key_exists($pi, $this->playersExpectations))
					{
						$expectation = new stdClass();
						$expectation->players = array();
						$this->playersExpectations[$pi] = $expectation;
					}
					else
					{
						$expectation = $this->playersExpectations[$pi];
					}
					$expectation->players[$pj] = 0;
					
					if (!array_key_exists($pj, $this->playersExpectations))
					{
						$expectation = new stdClass();
						$expectation->players = array();
						$this->playersExpectations[$pj] = $expectation;
					}
					else
					{
						$expectation = $this->playersExpectations[$pj];
					}
					$expectation->players[$pi] = 0;
				}
			}
		}
		
		foreach ($this->playersExpectations as $p => $expectation)
		{
			$p = $this->players - 1 - count($expectation->players);
			if ($p > 0)
			{
				$expectation->def = $this->games * 9 / $p;
			}
			else
			{
				unset($this->playersExpectations[$p]);
			}
		}
	}
	
	// returning 0 means we have found a perfect seating. There is no need to optimize it any more
	function calculatePlayersScore($seating)
	{
		$def_expectation = $this->games * 9 / ($this->players - 1);
		$this->_createPlayersExpectations($seating);
		
		// Count actual co-table meetings for each pair
		$meetings = array_fill(0, $this->players * $this->players, 0);
		foreach ($seating as $round)
		{
			foreach ($round as $table)
			{
				for ($a = 0; $a < 10; $a++)
				{
					for ($b = $a + 1; $b < 10; $b++)
					{
						$pi = $table[$a];
						$pj = $table[$b];
						$meetings[$pi * $this->players + $pj]++;
						$meetings[$pj * $this->players + $pi]++;
					}
				}
			}
		}

		// Now calculate the score
		$is_perfect = true;
		$score = 0;
		for ($p1 = 0; $p1 < $this->players; ++$p1)
		{
			if (array_key_exists($p1, $this->playersExpectations))
			{
				$expectation = $this->playersExpectations[$p1];
			}
			else
			{
				$expectation = null;
			}
			
			for ($p2 = $p1 + 1; $p2 < $this->players; ++$p2)
			{
				$diff = $meetings[$p1 * $this->players + $p2];
				if (is_null($expectation))
				{
					$diff -= $def_expectation;
				}
				else if (array_key_exists($p2, $expectation->players))
				{
					$diff -= $expectation->players[$p2];
					$diff *= 10; // seating restriction should have a stronger influence
				}
				else
				{
					$diff -= $expectation->def;
				}
				$diff *= $diff;
				if ($diff >= 1)
				{
					$is_perfect = false;
				}
				$score += $diff;
			}
		}
		if ($is_perfect)
		{
			$score = 0;
		}
		return $score;
	}
	
	public function worstPlayersScore()
	{
		return SeatingDef::worst_players_score($this->players, $this->tables, $this->games);
	}
	
	// returning 0 means we have found a perfect numbers. There is no need to optimize it any more
	public function calculateNumbersScore($seating)
	{
		$numbers = array_fill(0, $this->players * 10, 0);
		foreach ($seating as $round)
		{
			foreach ($round as $table)
			{
				for ($a = 0; $a < 10; ++$a)
				{
					++$numbers[$table[$a] * 10 + $a];
				}
			}
		}
		
		$perfect = true;
		$score = 0;
		// calculate score for single numbers
		$expected = $this->games / 10;
		$offset = 0;
		for ($i = 0; $i < $this->players; ++$i)
		{
			for ($j = 0; $j < 10; ++$j)
			{
				$diff = $expected - $numbers[$offset++];
				$diff *= $diff;
				if ($diff >= 1)
				{
					// echo 'Player: ' . $i . '; number: ' . ($j + 1) . ': diff: ' . $diff . '<br>';
					$perfect = false;
				}
				switch ($j)
				{
				case 0:
					$diff *= 4;
					break;
				case 1:
					$diff *= 3;
					break;
				case 9:
					$diff *= 2;
					break;
				}
				$score += $diff * 4; // 4 is the importance multiplier
			}
		}
		
		// calculate score for pairs of numbers
		$expected = $this->games / 5;
		$offset = 0;
		for ($i = 0; $i < $this->players; ++$i)
		{
			for ($j = 0; $j < 10; $j += 2)
			{
				$diff = $expected;
				for ($k = 0; $k < 2; ++$k)
				{
					$diff -= $numbers[$offset++];
				}
				$diff *= $diff;
				if ($diff >= 1)
				{
					// echo 'Player: ' . $i . '; number: ' . ($j + 1) . '-' . ($j + 2) . ': diff: ' . $diff . '<br>';
					$perfect = false;
				}
				$score += $diff * 2; // 2 is the importance multiplier
			}
		}
		
		// calculate score for halfs
		$expected = $this->games / 2;
		$offset = 0;
		for ($i = 0; $i < $this->players; ++$i)
		{
			for ($j = 0; $j < 10; $j += 5)
			{
				$diff = $expected;
				for ($k = 0; $k < 5; ++$k)
				{
					$diff -= $numbers[$offset++];
				}
				$diff *= $diff;
				if ($diff >= 1)
				{
					// echo 'Player: ' . $i . '; number: ' . ($j == 0 ? '1-half' : '2-half') . ': diff: ' . $diff . '<br>';
					$perfect = false;
				}
				$score += $diff;
			}
		}
		
		if ($perfect)
		{
			$score = 0;
		}
		return $score;
	}
	
	public function worstNumbersScore()
	{
		return SeatingDef::worst_numbers_score($this->players, $this->tables, $this->games);
	}
	
	// returning 0 means we have found a perfect tables. There is no need to optimize it any more
	public function calculateTablesScore($seating)
	{
		if ($this->tables < 3)
		{
			return 0;
		}
		
		$tables = array_fill(0, $this->players * $this->tables, 0);
		foreach ($seating as $round)
		{
			foreach ($round as $t => $table)
			{
				for ($a = 0; $a < 10; ++$a)
				{
					++$tables[$table[$a] * $this->tables + $t];
				}
			}
		}
		
		$perfect = true;
		$score = 0;
		$expected = $this->games / $this->tables;
		$offset = 0;
		for ($i = 0; $i < $this->players; ++$i)
		{
			for ($j = 0; $j < $this->tables; ++$j)
			{
				$diff = $expected - $tables[$offset++];
				$score += $diff * $diff;
				if ($diff >= 1)
				{
					$perfect = false;
				}
			}
		}
		
		if ($perfect)
		{
			$score = 0;
		}
		return $score;
	}
	
	public function worstTablesScore()
	{
		return SeatingDef::worst_tables_score($this->players, $this->tables, $this->games);
	}
	
	static function worst_players_score($players, $tables, $games)
	{
		$tens = floor($players / 10);
		$expectation = $games * 9 / ($players - 1);
		return 
			$expectation * $expectation * 50 * $tens * ($tens - 1) +
			($expectation - $games) * ($expectation - $games) * $tens * 45;
	}
	
	static function worst_numbers_score($players, $tables, $games)
	{
		$e1 = $games / 10; // expected per single number
		$e2 = $games / 5;  // expected per pair
		$e3 = $games / 2;  // expected per half
		// worst case: player always at position 0 (highest-weighted slot)
		// part1 single numbers: 1296*e1² (pos0) + 12 + 28 + 8 = 1344*e1²
		// part2 pairs: pair(0,1) gives 32*e2², four others give 8*e2² → 40*e2²
		// part3 halves: 2*e3²
		return $players * ($e1 * $e1 * 1344 + $e2 * $e2 * 40 + 2 * $e3 * $e3);
	}

	static function worst_tables_score($players, $tables, $games)
	{
		$expectation = $games / $tables;
		return $players * ($expectation * $expectation * ($tables - 1) + ($expectation - $games) * ($expectation - $games));
	}
	
	static function worst_acceptable_players_score($players, $tables, $games)
	{
		return SeatingDef::worst_players_score($players, $tables, $games) * 4 / ($games + 2);
	}
	
	static function worst_acceptable_numbers_score($players, $tables, $games)
	{
		return $players * 12;
	}

	static function worst_acceptable_tables_score($players, $tables, $games)
	{
		return SeatingDef::worst_tables_score($players, $tables, $games) / 5;
	}

	// Renumbers free players (those not in any restriction group) in the seating
	// so that players with better meeting distribution (lower SSQ) get lower numbers.
	// Restricted players keep their current indices unchanged.
	// Returns a new seating with renumbered players.
	function renumberByDistribution($seating)
	{
		// Collect restricted player indices
		$restricted = array();
		foreach ($this->restrictions as $group)
		{
			foreach ($group as $p)
			{
				$restricted[$p] = true;
			}
		}

		// Collect free player indices (sorted ascending)
		$free_indices = array();
		for ($p = 0; $p < $this->players; ++$p)
		{
			if (!isset($restricted[$p]))
			{
				$free_indices[] = $p;
			}
		}

		if (count($free_indices) <= 1)
		{
			$mapping = array();
			for ($p = 0; $p < $this->players; ++$p)
			{
				$mapping[$p] = $p;
			}
			return $mapping;
		}

		// Count meetings between all pairs of players across all games
		$meetings = array_fill(0, $this->players * $this->players, 0);
		foreach ($seating as $round)
		{
			foreach ($round as $table)
			{
				for ($a = 0; $a < 10; ++$a)
				{
					for ($b = $a + 1; $b < 10; ++$b)
					{
						$pi = $table[$a];
						$pj = $table[$b];
						$meetings[$pi * $this->players + $pj]++;
						$meetings[$pj * $this->players + $pi]++;
					}
				}
			}
		}

		// For each free player compute SSQ of meeting-frequency deviations from expected
		$expected = $this->games * 9 / ($this->players - 1);
		$ssq = array();
		foreach ($free_indices as $p)
		{
			$sum = 0;
			for ($q = 0; $q < $this->players; ++$q)
			{
				if ($q !== $p)
				{
					$diff = $meetings[$p * $this->players + $q] - $expected;
					$sum += $diff * $diff;
				}
			}
			$ssq[$p] = $sum;
		}

		// Sort free players by SSQ ascending: lowest SSQ → lowest index (best distribution = lowest number)
		$free_players = $free_indices;
		usort($free_players, function($a, $b) use ($ssq)
		{
			$diff = $ssq[$a] - $ssq[$b];
			return abs($diff) < 1e-9 ? 0 : (int)($diff < 0 ? -1 : 1);
		});

		// Build mapping: restricted players keep their index; free players get reassigned
		$mapping = array();
		for ($p = 0; $p < $this->players; ++$p)
		{
			$mapping[$p] = $p;
		}
		for ($i = 0; $i < count($free_players); ++$i)
		{
			$mapping[$free_players[$i]] = $free_indices[$i];
		}

		// Build and return new seating with remapped player indices
		$result = array();
		foreach ($seating as $r => $round)
		{
			$result[$r] = array();
			foreach ($round as $t => $table)
			{
				$result[$r][$t] = array();
				for ($a = 0; $a < 10; ++$a)
				{
					$result[$r][$t][$a] = $mapping[$table[$a]];
				}
			}
		}
		return $result;
	}
}

?>
