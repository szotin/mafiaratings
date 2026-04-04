<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/json.php';

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
function get_tournament_pairs($tournament_id, $club_id, $lang)
{
	$players_list = '';
	$delim = '';
	$query = new DbQuery('SELECT user_id FROM tournament_regs WHERE tournament_id = ?', $tournament_id);
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

	return array_values(array_filter($pairs_map, function($pair) { return $pair->policy != PAIR_POLICY_NOTHING; }));
}

function generate_next_restriction_group_level($players, $groups = null)
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

function find_longest_groups($players) 
{
	$groups = generate_next_restriction_group_level($players);
	if ($groups != null)
	{
		while (($next = generate_next_restriction_group_level($players, $groups)) != null)
		{
			$groups = $next;
		}
	}
	return $groups;
}

function normalize_restrictions($restrictions)
{
	$result = new stdClass();
	$result->players = array();
	for ($i = 0; $i < count($restrictions); ++$i)
	{
		for ($j = 0; $j < count($restrictions[$i]); ++$j)
		{
			for ($k = $j + 1; $k < count($restrictions[$i]); ++$k)
			{
				if ($j != $k)
				{
					$n1 = $restrictions[$i][$j];
					$n2 = $restrictions[$i][$k];
					$add = true;
					if (array_key_exists($n1, $result->players))
					{
						foreach ($result->players[$n1] as $n)
						{
							if ($n == $n2)
							{
								$add = false;
							}
						}
					}
					if ($add)
					{
						$result->players[$n1][] = $n2;
						$result->players[$n2][] = $n1;
					}
				}
			}
		}
	}
	
	$result->restrictions = array();
	$players_copy = $result->players;
	while(count($groups = find_longest_groups($players_copy)) > 0)
	{
		foreach ($groups as $group)
		{
			$result->restrictions[] = $group;
			for ($i = 0; $i < count($group); ++$i)
			{
				$n1 = $group[$i];
				for ($j = 0; $j < count($group); ++$j)
				{
					if ($i != $j)
					{
						$n2 = $group[$j];
						$key = array_search($n2, $players_copy[$n1]);
						if ($key !== false)
						{
							array_splice($players_copy[$n1], $key, 1);
						}
					}
				}
				if (count($players_copy[$n1]) == 0)
				{
					unset($players_copy[$n1]);
				}
			}
		}
	}
	usort($result->restrictions, function($a, $b) use ($result)
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
			$count = count($result->players[$i]);
			$aSum += $count;
			$aMax = max($aMax, $count);
		}
		
		$bSum = 0;
		$bMax = 0;
		foreach ($b as $i)
		{
			$count = count($result->players[$i]);
			$bSum += $count;
			$bMax = max($bMax, $count);
		}
		
		if ($aSum !== $bSum)
		{
			return $bSum - $aSum;
		}
		return $bMax - $aMax;
	});
	
	for ($i = 0; $i < count($result->restrictions); ++$i)
	{
		usort($result->restrictions[$i], function($a, $b) use ($result) { return count($result->players[$a]) - count($result->players[$b]); });
	}
	
	$result->mapping = array();
	$result->mapped_restrictions = array();
	$result->hash = '';
	$playerIndex = 0;
	foreach ($result->restrictions as $r)
	{
		$a = array();
		foreach ($r as $idx)
		{
			if (!array_key_exists($idx, $result->mapping))
			{
				$result->mapping[$idx] = $playerIndex++;
			}
			$a[] = $result->mapping[$idx];
		}
		sort($a);
		
		$i = 0;
		$aCount = count($a);
		$hash = '';
		while ($i < $aCount) 
		{
			$start = $i;
			
			// Look ahead to find the end of a consecutive sequence
			while ($i + 1 < $aCount && $a[$i + 1] === $a[$i] + 1)
			{
				++$i;
			}

			if (!empty($hash))
			{
				$hash .= ':';
			}
			$hash .= $a[$start];
			if ($i > $start)
			{
				$hash .= '-' . $a[$i];
			}
			++$i;
		}
		if (!empty($result->hash))
		{
			$result->hash .= '_';
		}
		$result->hash .= $hash;
		$result->mapped_restrictions[] = $a;
	}
	return $result;
}

// Тест: 1, 2, 3 не могут играть вместе (треугольник)
// echo generate_seating_hash(40, 10, [[4, 12], [12, 19], [4, 19]], []) . "\n";
// Результат: 40_10_1-3
// Тест: Цепочка 1-2, 2-3 (но 1 и 3 могут играть)
// echo generate_seating_hash(40, 10, [[1, 2], [2, 3]], []) . "\n";
// Результат: 40_10_1-2_2-3

?>
