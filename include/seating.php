<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/json.php';

define('PAIR_POLICY_SEPARATE', 0);
define('PAIR_POLICY_AVOID', 1);
define('PAIR_POLICY_BALANCED', 2);
define('PAIR_POLICY_WELCOME', 3);

function get_pair_policy_name($policy)
{
	switch ($u2->policy)
	{
	case PAIR_POLICY_SEPARATE:
		return get_label('Separate players.');
		break;
	case PAIR_POLICY_AVOID:
		return get_label('Reduce number of games together but do not separate completely.');
		break;
	case PAIR_POLICY_BALANCED:
		return get_label('As usual. No separation.');
		break;
	case PAIR_POLICY_WELCOME:
		return get_label('Increase number of games together.');
		break;
	}
	return '';
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
