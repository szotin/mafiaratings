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
// The pairs a tournament must keep apart, as [user1_id, user2_id] with user1_id < user2_id.
//
// Same four sources and the same priority order as get_tournament_pairs(), but without the names
// and flags that only the UI needs. Those come from a join filtered by language, which silently
// drops a pair whose players have no name in the language asked for - harmless on a page that is
// being rendered in that language, wrong here, where this runs from the updater with no language
// chosen and every rule matters.
//
// Unlike get_tournament_pairs() this is not restricted to the tournament's registrations: a
// seating extracted from a played tournament is matched against the people who actually played,
// and the caller drops any pair it cannot place.
function get_tournament_separate_pairs($tournament_id, $club_id)
{
	// [key => [policy, priority]], highest priority wins, as in get_tournament_pairs().
	$policies = array();
	$add = function($u1, $u2, $policy, $priority) use (&$policies)
	{
		$u1 = (int)$u1;
		$u2 = (int)$u2;
		if ($u1 == $u2)
		{
			return;
		}
		$key = min($u1, $u2) . '_' . max($u1, $u2);
		if (!isset($policies[$key]) || $priority > $policies[$key][1])
		{
			$policies[$key] = array((int)$policy, $priority);
		}
	};

	// Priority 0: global pairs.
	$query = new DbQuery('SELECT user1_id, user2_id, policy FROM pairs');
	while ($row = $query->next())
	{
		$add($row[0], $row[1], $row[2], 0);
	}

	// Priority 1: pairs of every league this tournament runs a series for.
	$query = new DbQuery(
		'SELECT p.user1_id, p.user2_id, p.policy FROM league_pairs p'.
		' WHERE p.league_id IN (SELECT s.league_id FROM series_tournaments st'.
		' JOIN series s ON s.id = st.series_id WHERE st.tournament_id = ?)',
		$tournament_id);
	while ($row = $query->next())
	{
		$add($row[0], $row[1], $row[2], 1);
	}

	// Priority 2: pairs of the club running it.
	if ((int)$club_id > 0)
	{
		$query = new DbQuery('SELECT user1_id, user2_id, policy FROM club_pairs WHERE club_id = ?', $club_id);
		while ($row = $query->next())
		{
			$add($row[0], $row[1], $row[2], 2);
		}
	}

	// Priority 3: the tournament's own pairs.
	$query = new DbQuery('SELECT user1_id, user2_id, policy FROM tournament_pairs WHERE tournament_id = ?', $tournament_id);
	while ($row = $query->next())
	{
		$add($row[0], $row[1], $row[2], 3);
	}

	$result = array();
	foreach ($policies as $key => $p)
	{
		if ($p[0] == PAIR_POLICY_SEPARATE)
		{
			list($u1, $u2) = explode('_', $key);
			$result[] = array((int)$u1, (int)$u2);
		}
	}
	return $result;
}

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
	// Order by both user ids. Sorting by user1_id alone left pairs that share user1_id in the
	// fill order of $pairs_map, which comes from four queries (pairs, league_pairs, club_pairs,
	// tournament_pairs) that have no ORDER BY, so it shifted whenever rows were added or edited.
	// SeatingDef::normalizeRestrictions() derives its player renumbering from this order, so an
	// unstable order produced a different seating hash for an unchanged set of rules.
	usort($result, function($a, $b)
	{
		if ($a->user1_id != $b->user1_id)
		{
			return $a->user1_id - $b->user1_id;
		}
		return $a->user2_id - $b->user2_id;
	});
	return $result;
}

// Splits a seating hash into its parts. Returns an object with players, tables, games,
// team_size and restrictions (the leftover segments, ready for format_seating_restrictions).
// Use this rather than slicing explode('_', $hash) by hand: the team size sits between the
// games and the restrictions, and older hashes written before it existed do not have it, so a
// fixed offset is right for one of the two and wrong for the other.
// The quality bar both seating pages show, with the button that optimizes the very measure the
// bar is about - the user is looking at the seat numbers, so the button improves the seat
// numbers. $hash is what gets optimized; pass null for $hash or false for $can_optimize to draw
// the bar alone. $note is shown under it, for saying things the bare percentage does not, like
// that a seating this new has not been optimized at all yet.
// $event_id is the event this seating belongs to, or null on a page looking at a seating on its
// own. It does two jobs: with $has_better_version it offers to pull the improved canonical
// seating into the event, and otherwise it lets an optimization run that finds something better
// put the result into the event straight away.
// What optimizing one measure costs the others, so it can be said before the button is pressed
// rather than discovered afterwards.
//
// Only the players optimizer takes anything away. Its move exchanges two players sitting at
// different tables, which changes both who sits at which table and who holds which seat number,
// so neither of the other two measures still describes the seating afterwards - which is why
// players_task_end resets both of them outright.
//
// The other two do not disturb each other. The tables optimizer exchanges two whole tables
// within a round, so every player keeps the seat number they had; the numbers optimizer
// exchanges two players at one table, so every player keeps the table they were at. Each clears
// the other's saved cursor, but that is only a place to resume from - the score it had earned
// still holds. Measured on a real seating: after a tables move the numbers score stayed at
// 80.0, and after a numbers move the tables score stayed at 128.0.
function seating_optimization_cost($task)
{
	if ($task == 'players')
	{
		return get_label('Optimizing the players resets the tables and numbers optimization. You will have to run those again afterwards.');
	}
	return '';
}

function show_seating_quality_bar($percent, $task, $hash, $can_optimize, $note = '', $event_id = null, $has_better_version = false)
{
	$pct = round($percent);
	echo '<p><div style="display:flex;align-items:center;gap:8px;">';
	echo '<span style="white-space:nowrap;">' . get_label('Quality') . ':</span>';
	echo '<div style="position:relative;flex:1;height:24px;line-height:24px;overflow:hidden;">';
	echo '<img id="opt-fill-' . $task . '" src="images/red_dot.png" style="position:absolute;left:0;top:0;width:' . $pct . '%;height:24px;opacity:0.6;">';
	echo '<img id="opt-rest-' . $task . '" src="images/black_dot.png" style="position:absolute;left:' . $pct . '%;top:0;width:' . (100 - $pct) . '%;height:24px;opacity:0.6;">';
	echo '<b id="opt-pct-' . $task . '" style="position:absolute;left:0;top:0;width:100%;text-align:center;color:white;">' . $pct . '%</b>';
	echo '</div>';
	if ($can_optimize && $has_better_version && !is_null($event_id))
	{
		// An improved seating is already waiting, so taking it is the thing to do here -
		// optimizing again would only redo work that is done.
		$confirm = addslashes(get_label('Apply the improved seating? Players will be seated anew.'));
		echo '<button onclick="mr.updateEventSeating(' . (int)$event_id . ', \'' . $confirm . '\')">' . get_label('Update seating') . '</button>';
	}
	else if ($can_optimize && !is_null($hash) && $pct < 100)
	{
		// The event id goes along so that a run which finds something better can put it into the
		// event straight away. Optimizing improves the canonical seating, which the event only
		// holds a copy of, so without this the tournament would go on using the old one until
		// somebody noticed and pressed a button.
		$apply_to = is_null($event_id) ? 'null' : (int)$event_id;
		echo '<button id="opt-btn-' . $task . '" onclick="mr.optimizeSeating(\'' . addslashes($task) . '\', \'' . addslashes($hash) . '\', ' . $apply_to . ')">' . get_label('Optimize') . '</button>';
	}
	echo '</div>';
	echo '<div id="opt-msg-' . $task . '" style="color:#666;font-size:90%;">' . $note . '</div>';
	echo '</p>';
}

// The texts mr.optimizeSeating() puts on the page while it works. It runs in the browser and has
// no way to reach get_label(), so the page hands them over.
function show_seating_optimizer_labels()
{
	echo '<script>mr.seatingOptLabels = {';
	// The generic title is the fallback, and it is not decoration: the warning about a similar
	// seating calls mr.optimizeSeating(null, ...), with no task to name.
	echo 'title: ' . json_encode(get_label('Optimizing seating')) . ',';
	echo 'titles: {'.
		'players: ' . json_encode(get_label('Optimizing players')) . ',' .
		'numbers: ' . json_encode(get_label('Optimizing numbers')) . ',' .
		'tables: ' . json_encode(get_label('Optimizing tables')) .
	'},';
	// What the offer to optimize says. These carry what the "a new seating was made" message used
	// to say before it was folded in here - there is no sense telling the user the same thing in
	// two dialogs in a row, and what they want to know at that moment is whether to wait for the
	// background optimizer or spend ten minutes now.
	echo 'offer: ' . json_encode(get_label('This seating has not been optimized yet. Optimize it now? It takes about ten minutes, and this page has to stay open.')) . ',';
	echo 'offers: {'.
		'new: ' . json_encode(get_label('We do not have a seating arrangement for this configuration, so we have generated a very basic one.<p>The background optimizer will improve it over the next few hours. Or we can optimize it right now: that takes about ten minutes, and this page has to stay open.</p><p>Optimize it now?</p>')) . ',' .
		'similar: ' . json_encode(get_label('We have found a similar but not exactly the same seating arrangement. It is pretty good, but we can do better.<p>The background optimizer will improve it over the next few hours. Or we can optimize it right now: that takes about ten minutes, and this page has to stay open.</p><p>Optimize it now?</p>')) .
	'},';
	echo 'done: ' . json_encode(get_label('Nothing left to improve')) . ',';
	echo 'failed: ' . json_encode(get_label('Could not optimize: the seating was not found')) . ',';
	echo 'enough: ' . json_encode(get_label('Finished')) . ',';
	echo 'found: ' . json_encode(get_label('Better seatings found: [0]')) . ',';
	echo 'stopping: ' . json_encode(get_label('Stopping after this pass...')) . ',';
	echo 'stop: ' . json_encode(get_label('Stop')) . ',';
	echo 'close: ' . json_encode(get_label('Close')) . ',';
	echo 'applying: ' . json_encode(get_label('Applying the new seating to the tournament...')) . ',';
	echo 'applied: ' . json_encode(get_label('Done. The new seating is now used by the tournament.')) . ',';
	echo 'applyFailed: ' . json_encode(get_label('The seating was improved, but applying it to the tournament failed.')) . ',';
	// Only the players optimizer costs anything; the others get nothing to say.
	echo 'costs: {players: ' . json_encode(seating_optimization_cost('players')) . '}';
	echo '};</script>';
}

// Quality of a stored seating as the percentages the pages show: how far each score is from the
// worst one still considered acceptable, as a number from 0 (no better than the worst) to 100
// (nothing left to improve). players and tables come back null where the measure says nothing -
// a ten player seating has only one way to seat everyone, and tables mean nothing below three.
// Kept in one place so seating.php, tournament_seating.php and the api all agree.
function seating_quality($hash, $players_score, $numbers_score, $tables_score)
{
	$parts = seating_hash_parts($hash);
	$percent = function($score, $worst)
	{
		if ($worst <= 0)
		{
			return 100.0;
		}
		return (1 - min(max($score / $worst, 0), 1)) * 100;
	};

	$result = new stdClass();
	$result->players = ($parts->players > 10)
		? $percent($players_score, SeatingDef::worst_acceptable_players_score($parts->players, $parts->tables, $parts->games))
		: null;
	$result->numbers = $percent($numbers_score, SeatingDef::worst_acceptable_numbers_score($parts->players, $parts->tables, $parts->games));
	$result->tables = ($parts->tables >= 3)
		? $percent($tables_score, SeatingDef::worst_acceptable_tables_score($parts->players, $parts->tables, $parts->games))
		: null;
	return $result;
}

function seating_hash_parts($hash)
{
	$parts = explode('_', $hash);
	$result = new stdClass();
	$result->players      = isset($parts[0]) ? (int)$parts[0] : 0;
	$result->tables       = isset($parts[1]) ? (int)$parts[1] : 0;
	$result->games        = isset($parts[2]) ? (int)$parts[2] : 0;
	$result->team_size    = 1;
	$first = 3;
	if (count($parts) > 3 && ctype_digit($parts[3]))
	{
		$result->team_size = max(1, (int)$parts[3]);
		$first = 4;
	}
	$result->restrictions = array_slice($parts, $first);
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

// Assigns every player a "color" describing the shape of the restriction graph around them,
// by color refinement (1-dimensional Weisfeiler-Leman): start from the number of players each
// one must be kept apart from, then repeatedly replace a color by the pair (own color, sorted
// colors of the neighbours) until the partition stops getting finer. The result depends only
// on the graph, never on the player indices, so SeatingDef::normalizeRestrictions() can use it
// to break ties in a way that survives a change of registration order.
// $restrictions_by_player maps a player index to the list of players they must not meet.
function _refine_player_colors($restrictions_by_player)
{
	$colors = array();
	foreach ($restrictions_by_player as $player => $neighbours)
	{
		$colors[$player] = count($neighbours);
	}

	$distinct_count = count(array_unique($colors));
	$rounds = count($colors);
	for ($round = 0; $round < $rounds; ++$round)
	{
		$signatures = array();
		foreach ($restrictions_by_player as $player => $neighbours)
		{
			$neighbour_colors = array();
			foreach ($neighbours as $neighbour)
			{
				$neighbour_colors[] = $colors[$neighbour];
			}
			sort($neighbour_colors, SORT_NUMERIC);
			$signatures[$player] = $colors[$player] . '|' . implode(',', $neighbour_colors);
		}

		// Renumber the signatures to small integers, ordered so that the numbering itself
		// stays reproducible from one run to the next.
		$distinct = array_unique(array_values($signatures));
		sort($distinct, SORT_STRING);
		$rank = array_flip($distinct);

		$new_colors = array();
		foreach ($signatures as $player => $signature)
		{
			$new_colors[$player] = $rank[$signature];
		}
		$colors = $new_colors;

		$new_distinct_count = count($distinct);
		if ($new_distinct_count == $distinct_count)
		{
			break;
		}
		$distinct_count = $new_distinct_count;
	}
	return $colors;
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
	// _generate_next_restriction_group_level() signals "nothing left" with null. Normalize that
	// to an empty array: the caller counts the result, and while PHP 7 silently treated
	// count(null) as 0, PHP 8 throws a TypeError, which killed the whole request.
	return is_array($groups) ? $groups : array();
}

class SeatingDef
{
	public $hash;
	public $players;
	public $tables;
	public $games;
	// Number of players per team. 1 means an individual tournament. When it is greater, the
	// players are divided into teams by position - with a team size of 3, players 0,1,2 are one
	// team, 3,4,5 the next, and so on - and teammates must never share a table. Those pairs are
	// added to $restrictions in teamRestrictions(), so everything downstream (the optimizers,
	// satisfiesRestrictions, the scores) treats them as ordinary restrictions and needs no
	// changes. The hash records the team size instead of listing the pairs, which keeps it
	// short: a 30 player team-of-3 tournament has 30 teammate pairs that would otherwise be
	// spelled out and would overflow the 255 character limit.
	public $teamSize;
	// Who is on a team with whom, in the numbering this object currently uses. Read from a hash
	// the teams are the positional blocks the hash means. Built from a caller's numbers they are
	// whatever the caller passes, because a tournament's teammates sit at whatever registration
	// positions they happen to have. normalizeRestrictions() is what moves each team onto a
	// block of its own, and that is what lets the hash record just the size.
	public $teams;
	public $restrictions;

	function __construct($hash, $tables = 0, $games = 0, $restrictions = null, $team_size = 1, $teams = null)
	{
		$this->teamSize = max(1, (int)$team_size);
		$this->teams = array();
		if (is_object($hash))
		{
			// Copy constructor: accepts a SeatingDef object and clones its values.
			$this->hash         = $hash->hash;
			$this->players      = $hash->players;
			$this->tables       = $hash->tables;
			$this->games        = $hash->games;
			$this->teamSize     = $hash->teamSize;
			$this->teams        = $hash->teams;
			$this->restrictions = $hash->restrictions;
		}
		else if (is_array($hash))
		{
			// Build from a 3D seating array [round][table][seat] with player numbers 0..(players-1).
			// Counts tables, games per player and players, and keeps whichever of the caller's
			// restrictions this seating bears out.
			$seating = $hash;

			$this->tables = 0;
			$total_seats  = 0;
			$max_player   = -1;
			foreach ($seating as $round)
			{
				if (is_null($round) || empty($round)) continue;
				if ($this->tables == 0)
				{
					$this->tables = count($round);
				}
				foreach ($round as $table)
				{
					if (is_null($table)) continue;
					$total_seats += count($table);
					foreach ($table as $p)
					{
						if ((int)$p > $max_player)
						{
							$max_player = (int)$p;
						}
					}
				}
			}
			$this->players = $max_player + 1;
			// games = games-per-player = total seats / players.
			// 0 indicates an invalid seating (unequal play, or tables × 10 > players which is
			// physically impossible since a player can occupy only one table per round).
			$this->games = (
				$this->players > 0 &&
				$total_seats % $this->players === 0 &&
				$this->tables * 10 <= $this->players
			)
				? (int)($total_seats / $this->players)
				: 0;

			// Which restrictions this seating bears out.
			//
			// A seating cannot say why two players never met - a rule kept them apart, or the
			// draw simply never put them together - so it used to be read the other way round,
			// with every pair that never met turned into a restriction. That invented rules
			// wholesale: in a 10-player single-table seating every pair meets, but at 80 players
			// over 6 tables most pairs never meet, and the hash ended up asserting thousands of
			// separations nobody ever asked for.
			//
			// The rules are now supplied by the caller, which knows them: they are the separate
			// pairs of the tournament this seating came from, plus the global, league and club
			// ones. The seating is only asked to confirm each one. A pair that did share a table
			// is dropped rather than recorded - a rule added after the tournament was played
			// cannot be a rule the seating was built under.
			$met = array();
			foreach ($seating as $round)
			{
				if (is_null($round)) continue;
				foreach ($round as $table)
				{
					if (is_null($table)) continue;
					$g = array_values((array)$table);
					$n = count($g);
					for ($a = 0; $a < $n; ++$a)
					{
						for ($b = $a + 1; $b < $n; ++$b)
						{
							$lo = min((int)$g[$a], (int)$g[$b]);
							$hi = max((int)$g[$a], (int)$g[$b]);
							$met[$lo . '_' . $hi] = true;
						}
					}
				}
			}

			$this->restrictions = array();
			if (is_array($restrictions) && $this->players >= 12)
			{
				foreach ($restrictions as $pair)
				{
					$pair = array_values((array)$pair);
					if (count($pair) != 2)
					{
						continue;
					}
					$lo = min((int)$pair[0], (int)$pair[1]);
					$hi = max((int)$pair[0], (int)$pair[1]);
					if ($lo < 0 || $hi >= $this->players || $lo == $hi)
					{
						continue;
					}
					if (!isset($met[$lo . '_' . $hi]))
					{
						$this->restrictions[] = array($lo, $hi);
					}
				}
			}

			// A seating on its own cannot say which of the pairs that never met are teammates
			// and which merely happened not to meet, so the caller passes the teams in when it
			// knows them - extraction does, because it reads them from the tournament. With them
			// the hash records the team size instead of listing every teammate pair.
			if ($this->teamSize > 1 && is_array($teams))
			{
				$usable = array();
				foreach ($teams as $members)
				{
					if (count($members) == $this->teamSize)
					{
						$usable[] = array_values($members);
					}
				}
				if (count($usable) * $this->teamSize == $this->players)
				{
					$this->teams = $usable;
					$this->addTeamRestrictions();
				}
				else
				{
					$this->teamSize = 1;
					$this->teams = array();
				}
			}
			else
			{
				$this->teamSize = 1;
				$this->teams = array();
			}

			$this->generateHash();
		}
		else if (is_numeric($hash))
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
			// Only whole teams of exactly teamSize can be placed on the hash's equal blocks, and
			// only if they fill the field exactly. Anything else is not describable that way, so
			// drop back to an individual seating and let the caller spell the pairs out.
			if ($this->teamSize > 1)
			{
				$usable = array();
				if (is_array($teams))
				{
					foreach ($teams as $members)
					{
						if (count($members) == $this->teamSize)
						{
							$usable[] = array_values($members);
						}
					}
				}
				if (empty($usable))
				{
					$this->teams = $this->positionalTeams();
				}
				else if (count($usable) * $this->teamSize == $this->players)
				{
					$this->teams = $usable;
				}
				else
				{
					$this->teamSize = 1;
					$this->teams = array();
				}
			}
			$this->addTeamRestrictions();
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
				// The fourth field is the team size. Hashes written before team sizes existed
				// have no such field, and they are still readable: a restriction segment always
				// holds at least two players and so always contains ':' or '-', while the team
				// size is a bare number, which tells the two apart with no ambiguity. A hash
				// without it describes an individual tournament, which is a team size of 1.
				$first_restriction = 3;
				if (count($parts) > 3 && ctype_digit($parts[3]))
				{
					$this->teamSize = max(1, (int)$parts[3]);
					$first_restriction = 4;
				}
				else
				{
					$this->teamSize = 1;
				}
				if ($this->players >= 12)
				{
					for ($i = $first_restriction; $i < count($parts); ++$i)
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
					// The hash leaves out the pairs the team size already implies, so put them
					// back: from here on the teams are ordinary restrictions like any other.
					$this->addTeamRestrictions();
				}
			}
		}
	}
	
	// The teams as the hash means them: consecutive blocks of teamSize, so with a size of 3
	// players 0,1,2 are one team, 3,4,5 the next. Any players past the last whole team are on
	// no team at all.
	public function positionalTeams()
	{
		$teams = array();
		if ($this->teamSize <= 1)
		{
			return $teams;
		}
		$count = (int)floor($this->players / $this->teamSize);
		for ($team = 0; $team < $count; ++$team)
		{
			$members = array();
			for ($i = $team * $this->teamSize; $i < ($team + 1) * $this->teamSize; ++$i)
			{
				$members[] = $i;
			}
			$teams[] = $members;
		}
		return $teams;
	}

	// Team of the player seated in this position, or -1 when there are no teams.
	public function teamOf($player)
	{
		if ($this->teamSize <= 1)
		{
			return -1;
		}
		return (int)floor($player / $this->teamSize);
	}

	// True when every player of the group is on one team, so the team size in the hash already
	// says they must be kept apart and spelling the group out would only repeat it.
	private function isCoveredByTeams($group)
	{
		if ($this->teamSize <= 1 || count($group) < 2)
		{
			return false;
		}
		$team = $this->teamOf($group[0]);
		foreach ($group as $player)
		{
			if ($this->teamOf($player) !== $team)
			{
				return false;
			}
		}
		return true;
	}

	// Adds the pairs the team size implies - every two players of one team - to the
	// restrictions, unless they are already there. The optimizers, satisfiesRestrictions() and
	// the scores then handle teams without knowing anything about them.
	public function addTeamRestrictions()
	{
		if ($this->teamSize <= 1 || $this->players <= 0)
		{
			$this->teams = array();
			return;
		}

		if (empty($this->teams))
		{
			$this->teams = $this->positionalTeams();
		}

		$known = array();
		foreach ($this->restrictions as $group)
		{
			$n = count($group);
			for ($i = 0; $i < $n; ++$i)
			{
				for ($j = $i + 1; $j < $n; ++$j)
				{
					$lo = min($group[$i], $group[$j]);
					$hi = max($group[$i], $group[$j]);
					$known[$lo][$hi] = true;
				}
			}
		}

		foreach ($this->teams as $members)
		{
			// One group per team rather than a pair at a time: normalizeRestrictions() keeps
			// groups whose members are all mutually restricted together, which is what a team is.
			$n = count($members);
			$missing = false;
			for ($i = 0; $i < $n && !$missing; ++$i)
			{
				for ($j = $i + 1; $j < $n; ++$j)
				{
					if (!isset($known[$members[$i]][$members[$j]]))
					{
						$missing = true;
						break;
					}
				}
			}
			if ($missing && $n > 1)
			{
				$this->restrictions[] = $members;
			}
		}
	}

	public function getNoRestrictionsHash()
	{
		return $this->players . '_' . $this->tables . '_' . $this->games . '_' . $this->teamSize;
	}
	
	private function generateHash()
	{
		$this->hash = $this->getNoRestrictionsHash();
		foreach ($this->restrictions as $idx => $r)
		{
			// Skip what the team size in the hash already says. The group stays in
			// $this->restrictions, so the optimizers still honour it; it just is not spelled
			// out again in the hash.
			if ($this->isCoveredByTeams($r))
			{
				continue;
			}
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

				// Note: use strict '' comparison, not empty(), because empty("0") is true
				// in PHP — a segment whose accumulated value is exactly "0" would otherwise
				// skip the separator and produce a malformed hash like "02" instead of "0:2".
				if ($h !== '')
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
			// seatings.hash is VARCHAR(255). Skip this restriction (and all that follow)
			// if appending would overflow. normalizeRestrictions sorts groups most-to-least
			// significant, so we keep the most meaningful ones.
			if (strlen($this->hash) + 1 + strlen($h) > 255)
			{
				// Drop the restrictions that did not fit, but keep every group the team size
				// covers: those cost the hash nothing, and dropping them would quietly let
				// teammates be seated together.
				$kept = array_slice($this->restrictions, 0, $idx);
				for ($rest = $idx; $rest < count($this->restrictions); ++$rest)
				{
					if ($this->isCoveredByTeams($this->restrictions[$rest]))
					{
						$kept[] = $this->restrictions[$rest];
					}
				}
				$this->restrictions = $kept;
				break;
			}
			$this->hash .= '_' . $h;
		}
	}
	
	// Assigns $players_list (exactly $tables * 10 entries) to $tables tables
	// while satisfying cannot_meet constraints. Members of each restriction group are
	// placed at DISTINCT tables (guaranteed when group_size <= $tables), preventing
	// the violations that the old greedy approach could produce.
	// Returns an array [$table => [player, ...]].
	private function _gisAssignPlayersToTables($players_list, $tables, $conflict_map)
	{
		// Returns table indices sorted by ascending load (full tables last), random tie-break.
		$sorted_tables = function() use (&$table_load, $tables)
		{
			$order = range(0, $tables - 1);
			shuffle($order);
			usort($order, function($a, $b) use ($table_load)
			{
				$fa = ($table_load[$a] >= 10) ? 1 : 0;
				$fb = ($table_load[$b] >= 10) ? 1 : 0;
				if ($fa !== $fb) return $fa - $fb;
				return $table_load[$a] - $table_load[$b];
			});
			return $order;
		};

		$players_set = array_flip($players_list);
		$table_load  = array_fill(0, $tables, 0);
		$tables_arr  = array_fill(0, $tables, array());
		$assigned    = array();

		// Pass 1: assign each restriction group's active members to DISTINCT tables.
		// Taking $order[0..(n-1)] guarantees all n members go to different table indices.
		foreach ($this->restrictions as $group)
		{
			$members = array();
			foreach ($group as $p)
				if (isset($players_set[$p]) && !isset($assigned[$p]))
					$members[] = $p;
			if (empty($members)) continue;

			shuffle($members);
			$order = $sorted_tables();
			$n = min(count($members), $tables);

			for ($i = 0; $i < $n; $i++)
			{
				$t = $order[$i];
				$tables_arr[$t][] = $members[$i];
				$table_load[$t]++;
				$assigned[$members[$i]] = true;
			}

			// If group is larger than table count (cannot fully separate), place overflow with minimal conflicts.
			for ($i = $n; $i < count($members); $i++)
			{
				$p = $members[$i];
				$best_t = -1;
				$best_conflicts = PHP_INT_MAX;
				foreach ($sorted_tables() as $t)
				{
					if ($table_load[$t] >= 10) continue;
					$conflicts = 0;
					foreach ($tables_arr[$t] as $seated)
						if (isset($conflict_map[$p][$seated])) $conflicts++;
					if ($conflicts < $best_conflicts)
					{
						$best_conflicts = $conflicts;
						$best_t = $t;
						if ($conflicts === 0) break;
					}
				}
				if ($best_t >= 0)
				{
					$tables_arr[$best_t][] = $p;
					$table_load[$best_t]++;
				}
				$assigned[$p] = true;
			}
		}

		// Pass 2: fill remaining slots with free (unrestricted) players, balanced by load.
		$free_players = array();
		foreach ($players_list as $p)
			if (!isset($assigned[$p]))
				$free_players[] = $p;
		shuffle($free_players);

		foreach ($free_players as $p)
		{
			foreach ($sorted_tables() as $t)
			{
				if ($table_load[$t] < 10)
				{
					$tables_arr[$t][] = $p;
					$table_load[$t]++;
					break;
				}
			}
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

		// Retry a few times: the round-player selection and table assignment are both
		// randomised, so a fresh attempt is cheap and covers any residual edge cases.
		$best_result = null;
		$best_score  = 0;
		for ($attempt = 0; $attempt < 5; $attempt++)
		{
			$round_player_lists = $this->_gisGenerateRoundPlayerLists($round_table_counts);
			$result = array();
			for ($r = 0; $r < $rounds; $r++)
				$result[$r] = $this->_gisAssignPlayersToTables(
					$round_player_lists[$r], $round_table_counts[$r], $conflict_map);
			if (empty($this->restrictions) || $this->satisfiesRestrictions($result))
				return $result;
			$result_score = $this->calculatePlayersScore($result);
			if ($best_result === null || $result_score < $best_score)
			{
				$best_result = $result;
				$best_score  = $result_score;
			}
		}

		// No attempt satisfied the restrictions. With a single table that can be impossible in
		// principle (see forcedMeetings()), so at least drive the restricted players down to the
		// fewest shared games, which happens exactly when their idle rounds do not coincide.
		return $this->minimizeSingleTableViolations($best_result);
	}

	// Hill climbs using the single-table participation move: take a player who plays in one round
	// but not in another, and a player in the opposite situation, and exchange their rounds. Both
	// keep exactly the same number of games, so the schedule stays valid.
	//
	// Used when the restrictions can not be honoured, to at least minimise how often the
	// restricted players share a table. The work is bounded by wall time rather than by a number
	// of moves, because the cost of one move grows with the number of rounds (each one rescores
	// the whole seating): the small configurations where restrictions are impossible converge in
	// a few milliseconds, while a long single-table schedule would otherwise take seconds inside
	// a web request. Whatever is left is picked up by the background optimizer afterwards.
	function minimizeSingleTableViolations($seating, $max_seconds = 0.3)
	{
		if ($this->tables > 1 || empty($this->restrictions) || !is_array($seating))
		{
			return $seating;
		}

		$deadline = microtime(true) + $max_seconds;
		$rounds   = count($seating);
		$score    = $this->calculatePlayersScore($seating);
		$improved = true;

		while ($improved)
		{
			$improved = false;
			for ($r1 = 0; $r1 < $rounds - 1; ++$r1)
			{
				for ($r2 = $r1 + 1; $r2 < $rounds; ++$r2)
				{
					for ($i = 0; $i < 10; ++$i)
					{
						for ($j = 0; $j < 10; ++$j)
						{
							if (microtime(true) >= $deadline)
							{
								return $seating;
							}

							$a = $seating[$r1][0][$i];
							$b = $seating[$r2][0][$j];
							// A moves into round 2 and B into round 1, so neither may be there yet.
							if ($a == $b ||
								in_array($a, $seating[$r2][0]) ||
								in_array($b, $seating[$r1][0]))
							{
								continue;
							}

							$seating[$r1][0][$i] = $b;
							$seating[$r2][0][$j] = $a;
							$new_score = $this->calculatePlayersScore($seating);
							if ($new_score < $score)
							{
								$score    = $new_score;
								$improved = true;
							}
							else
							{
								$seating[$r1][0][$i] = $a;
								$seating[$r2][0][$j] = $b;
							}
						}
					}
				}
			}
		}
		return $seating;
	}

	// Among the given array of hash strings, finds and returns the one whose stored seating
	// can be used for this SeatingDef (i.e., satisfies all its restrictions).
	//
	// Compatibility: every restriction group of this def must fit inside some restriction group
	// of the candidate hash. "Fit" means the candidate group is large enough to hold all the
	// players from one or more of our groups assigned to it (pure size-based bin packing,
	// since restriction-group players are abstract slots that can be freely renamed).
	//
	// Among compatible hashes the one with the lowest score wins, where:
	//   score = (total size of candidate's restriction groups) - (total size of our restrictions)
	// A score of 0 means a perfect structural match; higher scores mean more wasted constraints.
	//
	// Returns null if no hash in the array is compatible.
	function findTheMostCompatibleSeating($hashes)
	{
		$a_sizes = array();
		$a_total = 0;
		foreach ($this->restrictions as $g)
		{
			$sz = count($g);
			$a_sizes[] = $sz;
			$a_total  += $sz;
		}
		rsort($a_sizes); // largest first — helps backtracking prune early

		$best_hash  = null;
		$best_score = PHP_INT_MAX;

		foreach ($hashes as $hash)
		{
			$b = new SeatingDef($hash);
			if ($b->players !== $this->players ||
			    $b->tables  !== $this->tables  ||
			    $b->games   !== $this->games)
			{
				continue;
			}

			if (empty($this->restrictions))
			{
				// No restrictions on our side: any B is compatible.
				// Prefer the B that enforces the fewest extra constraints.
				$b_total = 0;
				foreach ($b->restrictions as $g) $b_total += count($g);
				if ($b_total < $best_score)
				{
					$best_score = $b_total;
					$best_hash  = $hash;
				}
				continue;
			}

			if (empty($b->restrictions))
			{
				continue; // B has no restrictions but we need some — incompatible.
			}

			$b_sizes = array();
			$b_total = 0;
			foreach ($b->restrictions as $g)
			{
				$sz = count($g);
				$b_sizes[] = $sz;
				$b_total  += $sz;
			}

			if ($b_total < $a_total)
			{
				continue; // B cannot absorb all our restriction players — incompatible.
			}

			rsort($b_sizes); // largest first

			if (!$this->_canPackGroups($a_sizes, $b_sizes, 0))
			{
				continue;
			}

			$score = $b_total - $a_total;
			if ($score < $best_score)
			{
				$best_score = $score;
				$best_hash  = $hash;
			}
		}

		return $best_hash;
	}

	// Backtracking feasibility check: can the A group sizes (sorted descending) be assigned
	// one per B bin (also sorted descending) without any bin being overfilled?
	// Multiple A groups can share a bin; the bin's remaining capacity shrinks each time.
	private function _canPackGroups($a_sizes, $b_caps, $a_idx)
	{
		if ($a_idx >= count($a_sizes)) return true;

		$needed = $a_sizes[$a_idx];
		$tried  = array();

		for ($j = 0; $j < count($b_caps); $j++)
		{
			$cap = $b_caps[$j];
			if ($cap < $needed) break; // b_caps is descending; no larger cap follows
			if (isset($tried[$cap])) continue; // skip symmetrically equivalent bins
			$tried[$cap] = true;

			$new_caps     = $b_caps;
			$new_caps[$j] -= $needed;
			rsort($new_caps);

			if ($this->_canPackGroups($a_sizes, $new_caps, $a_idx + 1)) return true;
		}

		return false;
	}

	// Converts the player numbers in $b_seating (which was generated for $b_hash) so that
	// they match the numbering scheme of this SeatingDef.
	//
	// How it works:
	//  1. Assigns each of this def's restriction groups to a restriction group in B
	//     (same bin-packing as findTheMostCompatibleSeating).
	//  2. Maps the B players chosen for each A restriction group to the A group's
	//     actual player indices.
	//  3. Maps all remaining (unrestricted) B players to the remaining A players.
	//
	// Returns the adapted seating array, or null if the hash is not compatible.
	function adoptSeating($b_hash, $b_seating)
	{
		$b = new SeatingDef($b_hash);

		$mapping    = array();
		$b_assigned = array();

		if (!empty($this->restrictions))
		{
			if (empty($b->restrictions)) return null;

			$assignment = $this->_findPackingAssignment($this->restrictions, $b->restrictions);
			if ($assignment === null) return null;

			foreach ($this->restrictions as $a_idx => $a_group)
			{
				$b_idx   = $assignment[$a_idx];
				$b_group = $b->restrictions[$b_idx];
				$count   = 0;
				foreach ($b_group as $b_player)
				{
					if (!isset($b_assigned[$b_player]))
					{
						$mapping[$b_player]    = $a_group[$count++];
						$b_assigned[$b_player] = true;
						if ($count >= count($a_group)) break;
					}
				}
			}
		}

		// Map remaining (unrestricted) B players to the remaining A players.
		$a_used = array();
		foreach ($mapping as $a_player) $a_used[$a_player] = true;

		$a_free = array();
		for ($p = 0; $p < $this->players; $p++)
			if (!isset($a_used[$p])) $a_free[] = $p;

		$free_idx = 0;
		for ($p = 0; $p < $b->players; $p++)
			if (!isset($mapping[$p]))
				$mapping[$p] = $a_free[$free_idx++];

		return SeatingDef::applyMapping($b_seating, $mapping);
	}

	// Returns an assignment array indexed by A group index giving the chosen B group index,
	// or null if no valid bin-packing assignment exists.
	private function _findPackingAssignment($a_groups, $b_groups)
	{
		$n_a = count($a_groups);

		$a_order = range(0, $n_a - 1);
		usort($a_order, function($i, $j) use ($a_groups)
		{
			return count($a_groups[$j]) - count($a_groups[$i]);
		});

		$b_remaining = array();
		foreach ($b_groups as $j => $g) $b_remaining[$j] = count($g);

		$assignment = array_fill(0, $n_a, -1);
		if ($this->_packBacktrack($a_groups, $a_order, 0, $b_remaining, $assignment))
			return $assignment;
		return null;
	}

	private function _packBacktrack($a_groups, $a_order, $k, &$b_remaining, &$assignment)
	{
		if ($k >= count($a_order)) return true;

		$a_idx  = $a_order[$k];
		$needed = count($a_groups[$a_idx]);
		$tried  = array();

		foreach ($b_remaining as $b_idx => $cap)
		{
			if ($cap < $needed) continue;
			if (isset($tried[$cap])) continue;
			$tried[$cap] = true;

			$b_remaining[$b_idx] -= $needed;
			$assignment[$a_idx]   = $b_idx;

			if ($this->_packBacktrack($a_groups, $a_order, $k + 1, $b_remaining, $assignment))
				return true;

			$b_remaining[$b_idx] += $needed;
			$assignment[$a_idx]   = -1;
		}

		return false;
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
	// Puts the restrictions into the canonical form the hash is built from, and returns the
	// mapping from the player numbers that came in to the ones the hash uses.
	//
	// One pass is not always enough. _normalizeRestrictionsOnce() splits the rules into groups
	// before it renumbers the players, so the split it makes depends on the numbering it was
	// given, while the numbering it produces depends on the split - feed its own output back in
	// and it can settle on a different answer. That mattered: reading a stored hash back and
	// normalizing it again could give a different hash, so a row saved under one of them could
	// never be found under the other. Repeating until the hash stops changing removes the
	// difference; measured over every hash in the database it takes two passes at most, and the
	// loop gives up after a few in case some graph refuses to settle.
	function normalizeRestrictions()
	{
		$mapping = $this->_normalizeRestrictionsOnce();
		for ($pass = 0; $pass < 4; ++$pass)
		{
			$previous_hash = $this->hash;
			// Carry on from what the hash actually says rather than from the groups still in
			// memory. The two are not the same thing: the hash leaves the teammate pairs out and
			// a reader regenerates them as one clean group per team, where the pass before may
			// have had them merged into larger groups. Comparing the in-memory forms would call
			// it settled while a reader of that hash normalizes it to something else - and then
			// nothing would ever look the stored row up again.
			$reparsed = new SeatingDef($previous_hash);
			$this->restrictions = $reparsed->restrictions;
			$this->teamSize     = $reparsed->teamSize;
			$this->teams        = $reparsed->teams;
			$next = $this->_normalizeRestrictionsOnce();
			if ($this->hash === $previous_hash)
			{
				break;
			}
			foreach ($mapping as $original => $current)
			{
				if (isset($next[$current]))
				{
					$mapping[$original] = $next[$current];
				}
			}
		}
		return $mapping;
	}

	private function _normalizeRestrictionsOnce()
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
		
		// _find_longest_groups() below walks this structure greedily, so the order the players
		// and their neighbours sit in decides which groups it carves out. Built as it is above,
		// that order is the order the pairs came in, which means the same set of rules could be
		// split into different groups - and a hash records the groups, so reading a hash back
		// and normalizing it again could produce a different hash. Ordering players and their
		// neighbour lists by index makes the split depend only on the rules themselves.
		ksort($restrictions_by_player);
		foreach ($restrictions_by_player as $player => $neighbours)
		{
			sort($neighbours, SORT_NUMERIC);
			$restrictions_by_player[$player] = $neighbours;
		}

		// Structural colors of the players, used from here on to order everything without
		// reference to the player numbers themselves. See _refine_player_colors().
		$player_colors = _refine_player_colors($restrictions_by_player);
		$compare_lists = function($a, $b)
		{
			$countA = count($a);
			$countB = count($b);
			$n = min($countA, $countB);
			for ($i = 0; $i < $n; ++$i)
			{
				if ($a[$i] !== $b[$i])
				{
					return $a[$i] < $b[$i] ? -1 : 1;
				}
			}
			return $countA - $countB;
		};
		$color_key = function($group) use ($player_colors)
		{
			$key = array();
			foreach ($group as $i)
			{
				$key[] = $player_colors[$i];
			}
			sort($key, SORT_NUMERIC);
			return $key;
		};

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
					// An earlier group of this same batch can have taken the last of this
					// player's pairs, and then the line below unset them. PHP 7 quietly treated
					// the missing entry as an empty list; PHP 8 raises a TypeError out of
					// array_search() and the whole request dies.
					if (!isset($restrictions_by_player_copy[$n1]))
					{
						continue;
					}
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
		// The player renumbering below follows the order of these two sorts, so any comparison
		// they leave tied would let the incoming order of the pairs decide the hash: the same
		// set of rules then produced a different hash (and a fresh seatings row, optimized from
		// scratch) every time the pairs happened to arrive in a different order. Both sorts
		// therefore end in a total order. The tie-breaks compare structural colors first, so
		// they also survive a change of registration order, which shifts every player index;
		// the index comparison is only a last resort for fully symmetric cases.
		usort($restrictions, function($a, $b) use ($restrictions_by_player, $color_key, $compare_lists)
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
			if ($aMax !== $bMax)
			{
				return $bMax - $aMax;
			}

			$cmp = $compare_lists($color_key($a), $color_key($b));
			if ($cmp !== 0)
			{
				return $cmp;
			}
			$aIdx = $a;
			$bIdx = $b;
			sort($aIdx, SORT_NUMERIC);
			sort($bIdx, SORT_NUMERIC);
			return $compare_lists($aIdx, $bIdx);
		});

		for ($i = 0; $i < count($restrictions); ++$i)
		{
			usort($restrictions[$i], function($a, $b) use ($restrictions_by_player, $player_colors)
			{
				$countA = count($restrictions_by_player[$a]);
				$countB = count($restrictions_by_player[$b]);
				if ($countA !== $countB)
				{
					return $countA - $countB;
				}
				if ($player_colors[$a] !== $player_colors[$b])
				{
					return $player_colors[$a] - $player_colors[$b];
				}
				return $a - $b;
			});
		}
		if ($this->teamSize > 1)
		{
			$mapping = $this->_teamAwareMapping($player_colors);
		}
		else
		{
			$mapping = array();
			$playerIndex = 0;
			foreach ($restrictions as $r)
			{
				foreach ($r as $idx)
				{
					if (!array_key_exists($idx, $mapping))
					{
						$mapping[$idx] = $playerIndex++;
					}
				}
			}
		}

		$this->restrictions = array();
		foreach ($restrictions as $r)
		{
			$a = array();
			foreach ($r as $idx)
			{
				$a[] = isset($mapping[$idx]) ? $mapping[$idx] : $idx;
			}
			sort($a);
			$this->restrictions[] = $a;
		}
		$this->generateHash();
		return $mapping;
	}

	// Renumbers the players of a team tournament, keeping every team on a block of consecutive
	// numbers - the one thing the hash relies on, since it records only the team size and lets
	// the positions say who is on a team with whom. The ordinary renumbering cannot be used
	// here: it numbers players in the order the restriction groups happen to come in, which
	// would scatter a team across the field and make the team size in the hash a lie.
	//
	// Teams are ordered by the structural colors of their members, and the members within a
	// team the same way, so that the same tournament always comes out numbered the same way
	// whatever order the teams were read in. Players outside any team (when the player count is
	// not a whole number of teams) keep to the end.
	private function _teamAwareMapping($player_colors)
	{
		$key_of = function($player) use ($player_colors)
		{
			return isset($player_colors[$player]) ? $player_colors[$player] : -1;
		};
		$compare_members = function($a, $b) use ($key_of)
		{
			$ka = $key_of($a);
			$kb = $key_of($b);
			if ($ka !== $kb)
			{
				return $ka - $kb;
			}
			return $a - $b;
		};

		// Members of each team, each team's members put in a reproducible order.
		$teams = array();
		$on_a_team = array();
		foreach ($this->teams as $members)
		{
			$members = array_values($members);
			usort($members, $compare_members);
			foreach ($members as $player)
			{
				$on_a_team[$player] = true;
			}
			$teams[] = $members;
		}

		// Then the teams themselves, by the colors of their members and the lowest number in
		// the team as the final tie-break.
		usort($teams, function($a, $b) use ($key_of)
		{
			$n = min(count($a), count($b));
			for ($i = 0; $i < $n; ++$i)
			{
				$ka = $key_of($a[$i]);
				$kb = $key_of($b[$i]);
				if ($ka !== $kb)
				{
					return $ka - $kb;
				}
			}
			return min($a) - min($b);
		});

		$mapping = array();
		$next = 0;
		foreach ($teams as $members)
		{
			foreach ($members as $player)
			{
				$mapping[$player] = $next++;
			}
		}

		// Whoever is left over - players on no team at all.
		$leftover = array();
		for ($i = 0; $i < $this->players; ++$i)
		{
			if (!isset($on_a_team[$i]))
			{
				$leftover[] = $i;
			}
		}
		usort($leftover, $compare_members);
		foreach ($leftover as $player)
		{
			$mapping[$player] = $next++;
		}

		// From here on the teams are the blocks the hash describes.
		$this->teams = $this->positionalTeams();
		return $mapping;
	}

	// Extends a partial mapping (as returned by normalizeRestrictions, which only covers the
	// players named in a restriction) to every player index, giving the players it does not
	// mention the remaining numbers in ascending order. applyMapping() can complete a mapping
	// too, but it does so from the order the players appear in the seating it is given, so two
	// seatings of the same definition would come out numbered differently. Completing the
	// mapping up front keeps one definition's seatings on one numbering.
	static function completeMapping($mapping, $players)
	{
		$taken = array_flip($mapping);
		$free = array();
		for ($i = 0; $i < $players; ++$i)
		{
			if (!isset($taken[$i]))
			{
				$free[] = $i;
			}
		}
		$next = 0;
		for ($i = 0; $i < $players; ++$i)
		{
			if (!isset($mapping[$i]))
			{
				$mapping[$i] = $free[$next++];
			}
		}
		return $mapping;
	}

	// Applies a player-index mapping to a [round][table][seat] seating array.
	// If $mapping is partial (from normalizeRestrictions), unmapped indices are assigned
	// new slots sequentially; $mapping is extended in-place to a full mapping.
	static function applyMapping($seating, &$mapping)
	{
		$next_free = count($mapping);
		$result = array();
		foreach ($seating as $r => $round)
		{
			$result[$r] = array();
			foreach ($round as $t => $table)
			{
				$result[$r][$t] = array();
				foreach ($table as $seat)
				{
					$idx = (int)$seat;
					if (!isset($mapping[$idx]))
					{
						$mapping[$idx] = $next_free++;
					}
					$result[$r][$t][] = $mapping[$idx];
				}
			}
		}
		return $result;
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
					$diff *= 10; // seating restriction should have a much stronger influence
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
	
	static function worst_players_score($players, $tables, $games)
	{
		$tens = floor($players / 10);
		$expectation = $games * 9 / ($players - 1);
		return 
			$expectation * $expectation * 50 * $tens * ($tens - 1) +
			($expectation - $games) * ($expectation - $games) * $tens * 45;
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
	
	// Only used to render the optimization percentage, never by the optimizer itself.
	// calculateNumbersScore() returns 0 when every deviation is below 1 and the full sum
	// otherwise, and that full sum can not go below roughly 12.6 per player. The previous
	// threshold of 12 per player sat under that floor, so every seating that missed the
	// "perfect" band was clamped to 0% no matter how well it was actually optimized.
	// 60 per player keeps optimized seatings in the 64-98% range (median 79%, in line with
	// the players and tables percentages) while a freshly generated one, which scores around
	// 90-114 per player, still shows 0%.
	static function worst_acceptable_numbers_score($players, $tables, $games)
	{
		return $players * 60;
	}

	static function worst_acceptable_tables_score($players, $tables, $games)
	{
		return SeatingDef::worst_tables_score($players, $tables, $games) / 5;
	}

	// With a single table everybody seated in a round meets everybody else, so two players who
	// each play $games of the $rounds rounds must share at least (2 * games - rounds) of them.
	// When that is positive, no seating can keep any two players apart, so a "separate these
	// players" rule is impossible for this configuration and the players score carries a penalty
	// no optimization can ever remove - which is why such a seating shows a permanent 0%.
	// Returns 0 when restrictions can be honoured, and always 0 for more than one table, where
	// restricted players can simply be split across tables.
	//
	// The result depends only on the configuration, not on which players are restricted, so when
	// it is positive every pair is equally impossible and no subset of the rules can survive.
	static function forced_meetings($players, $tables, $games)
	{
		if ($tables != 1 || $players <= 0 || $games <= 0)
		{
			return 0;
		}
		$rounds = $players * $games / 10;
		$forced = 2 * $games - $rounds;
		return $forced > 0 ? (int)ceil($forced) : 0;
	}

	public function forcedMeetings()
	{
		if (empty($this->restrictions))
		{
			return 0;
		}
		return SeatingDef::forced_meetings($this->players, $this->tables, $this->games);
	}

	// Returns the distribution mapping: restricted players keep their index; free players
	// are reassigned so that the one with the best meeting distribution (lowest SSQ) gets
	// the lowest free index. Returns a full mapping [old_idx => new_idx] for all players.
	function buildDistributionMapping($seating)
	{
		$restricted = array();
		foreach ($this->restrictions as $group)
		{
			foreach ($group as $p)
			{
				$restricted[$p] = true;
			}
		}

		$free_indices = array();
		for ($p = 0; $p < $this->players; ++$p)
		{
			if (!isset($restricted[$p]))
			{
				$free_indices[] = $p;
			}
		}

		$mapping = array();
		for ($p = 0; $p < $this->players; ++$p)
		{
			$mapping[$p] = $p;
		}

		if (count($free_indices) <= 1)
		{
			return $mapping;
		}

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

		$free_players = $free_indices;
		usort($free_players, function($a, $b) use ($ssq)
		{
			$diff = $ssq[$a] - $ssq[$b];
			return abs($diff) < 1e-9 ? 0 : (int)($diff < 0 ? -1 : 1);
		});

		for ($i = 0; $i < count($free_players); ++$i)
		{
			$mapping[$free_players[$i]] = $free_indices[$i];
		}

		return $mapping;
	}

	// Returns true if the seating satisfies all restrictions (no two players from the same
	// restriction group share a table), false otherwise.
	function satisfiesRestrictions($seating)
	{
		if (empty($this->restrictions))
		{
			return true;
		}

		$restrict_pairs = array();
		foreach ($this->restrictions as $group)
		{
			$n = count($group);
			for ($i = 0; $i < $n; $i++)
			{
				for ($j = $i + 1; $j < $n; $j++)
				{
					$a = $group[$i];
					$b = $group[$j];
					$restrict_pairs[$a][$b] = true;
					$restrict_pairs[$b][$a] = true;
				}
			}
		}

		foreach ($seating as $round)
		{
			foreach ($round as $table)
			{
				$seats = array_values((array)$table);
				$n = count($seats);
				for ($a = 0; $a < $n; $a++)
				{
					for ($b = $a + 1; $b < $n; $b++)
					{
						if (isset($restrict_pairs[$seats[$a]][$seats[$b]]))
						{
							return false;
						}
					}
				}
			}
		}

		return true;
	}

	// Adjusts event-specific seating to satisfy per-table restrictions such as judge/player
	// conflicts. Does NOT affect the canonical seatings table — call after the seatings table
	// insert and before assign_seating_to_event.
	//
	// $table_restrictions: array indexed by table number; each element is either null (no
	//   restriction) or an array of player slot indices forbidden at that table.
	//   Example: [[4], null, [2, 3]] means slot 4 cannot sit at table 0, slots 2 and 3
	//   cannot sit at table 2, table 1 has no restriction.
	//
	// For each round/violation the method tries (in order):
	//   1. Swap the whole offending table with another table if it introduces no new violations.
	//   2. Swap the violating player with the player at the same seat in another table,
	//      checking both $table_restrictions and $this->restrictions (pair restrictions).
	// If neither fix works the violation is left as-is.
	//
	// Returns the (possibly adjusted) seating.
	function applyTableRestrictions($seating, $table_restrictions)
	{
		if ($this->tables < 2 || empty($table_restrictions)) { return $seating; }

		// Normalise restrictions to $forbidden[$t][$slot] = true for O(1) checks.
		$forbidden = array();
		foreach ($table_restrictions as $t => $slots)
		{
			if (!empty($slots))
			{
				foreach ($slots as $slot)
				{
					$forbidden[$t][$slot] = true;
				}
			}
		}

		if (empty($forbidden)) { return $seating; }

		// Build O(1) lookup for pair restrictions (players that must never share a table).
		$restrict_pairs = array();
		foreach ($this->restrictions as $group)
		{
			$n = count($group);
			for ($i = 0; $i < $n; $i++)
			{
				for ($j = $i + 1; $j < $n; $j++)
				{
					$a = $group[$i];
					$b = $group[$j];
					$restrict_pairs[$a][$b] = true;
					$restrict_pairs[$b][$a] = true;
				}
			}
		}

		foreach ($seating as $r => $round)
		{
			$tc = count($round);
			if ($tc < 2) { continue; }

			for ($t = 0; $t < $tc; $t++)
			{
				if (empty($forbidden[$t])) { continue; }

				foreach ($forbidden[$t] as $forbidden_slot => $_)
				{
					// Find this slot in the (possibly already-modified) table $t.
					$player_seat = array_search($forbidden_slot, $seating[$r][$t]);
					if ($player_seat === false) { continue; }

					// --- 1. Try a full table swap ---
					$table_swapped = false;
					for ($t2 = 0; $t2 < $tc && !$table_swapped; $t2++)
					{
						if ($t2 == $t) { continue; }

						$ok = true;
						// Players moving into table $t must not be forbidden there.
						if (!empty($forbidden[$t]))
						{
							foreach ($seating[$r][$t2] as $slot)
							{
								if (isset($forbidden[$t][$slot])) { $ok = false; break; }
							}
						}
						// Players moving into table $t2 must not be forbidden there.
						if ($ok && !empty($forbidden[$t2]))
						{
							foreach ($seating[$r][$t] as $slot)
							{
								if (isset($forbidden[$t2][$slot])) { $ok = false; break; }
							}
						}

						if ($ok)
						{
							$tmp              = $seating[$r][$t];
							$seating[$r][$t]  = $seating[$r][$t2];
							$seating[$r][$t2] = $tmp;
							$table_swapped    = true;
						}
					}

					// A full table swap resolves all violations at $t simultaneously.
					if ($table_swapped) { break; }

					// --- 2. Try swapping the violating player at the same seat position ---
					for ($t2 = 0; $t2 < $tc; $t2++)
					{
						if ($t2 == $t) { continue; }

						$other_slot = $seating[$r][$t2][$player_seat];
						$ok = true;

						// Table restriction checks for both new positions.
						if (!empty($forbidden[$t]) && isset($forbidden[$t][$other_slot]))
						{
							$ok = false;
						}
						if ($ok && !empty($forbidden[$t2]) && isset($forbidden[$t2][$forbidden_slot]))
						{
							$ok = false;
						}

						// Pair restriction: $forbidden_slot moving into table $t2.
						if ($ok && isset($restrict_pairs[$forbidden_slot]))
						{
							foreach ($seating[$r][$t2] as $seat => $slot)
							{
								if ($seat != $player_seat && isset($restrict_pairs[$forbidden_slot][$slot]))
								{
									$ok = false;
									break;
								}
							}
						}

						// Pair restriction: $other_slot moving into table $t.
						if ($ok && isset($restrict_pairs[$other_slot]))
						{
							foreach ($seating[$r][$t] as $seat => $slot)
							{
								if ($seat != $player_seat && isset($restrict_pairs[$other_slot][$slot]))
								{
									$ok = false;
									break;
								}
							}
						}

						if ($ok)
						{
							$seating[$r][$t][$player_seat]  = $other_slot;
							$seating[$r][$t2][$player_seat] = $forbidden_slot;
							break;
						}
					}
					// If no fix was found, leave this violation as-is and continue.
				}
			}
		}

		return $seating;
	}

	// Renumbers free players (those not in any restriction group) in the seating
	// so that players with better meeting distribution (lower SSQ) get lower numbers.
	// Restricted players keep their current indices unchanged.
	// Returns a new seating with renumbered players.
	function renumberByDistribution($seating)
	{
		$mapping = $this->buildDistributionMapping($seating);
		return self::applyMapping($seating, $mapping);
	}
	
	function findSeating($create = true)
	{
		$result = new stdClass();
		$row = (new DbQuery('SELECT seating, players_runs, players_void_runs, tables_runs, tables_void_runs, numbers_runs, numbers_void_runs FROM seatings WHERE hash = ?', $this->hash))->next();
		if ($row)
		{
			$result->seating = reindex_seating(json_decode($row[0], true));
			$result->status = 'ok';
			list(, $pr, $pvr, $tr, $tvr, $nr, $nvr) = $row;
			$result->seating_version = ($pr - $pvr) . '.' . ($tr - $tvr) . '.' . ($nr - $nvr);
		}
		else
		{
			$hashes = array();
			$query = new DbQuery('SELECT hash FROM seatings WHERE hash LIKE ?', $this->getNoRestrictionsHash() . '%');
			while ($row = $query->next())
			{
				$hashes[] = $row[0];
			}
			$compatible_hash = $this->findTheMostCompatibleSeating($hashes);
			if ($compatible_hash)
			{
				list ($seating_json, $ps, $ns, $ts) = Db::record(get_label('seating'), 'SELECT seating, players_score, numbers_score, tables_score FROM seatings WHERE hash = ?', $compatible_hash);
				$result->seating = $this->adoptSeating($compatible_hash, reindex_seating(json_decode($seating_json, true)));
				$result->status = 'similar';
				$result->seating_version = '0.0.0';
				$result->warning = get_label('We have found a similar but not exactly the same seating arrangement. It is pretty good but we can do better.<p>You can wait a few hours for an improved version. Or you can <a href="#" onclick="mr.optimizeSeating(null, \'[0]\');">click here</a> and optimize it right now.</p>', $this->hash);
			}
			else
			{
				$result->seating = $this->generateInitialSeating();
				$result->seating = $this->renumberByDistribution($result->seating);
				$result->status = 'new';
				$result->seating_version = '0.0.0';
				$result->warning = get_label('We do not have a seating arrangement for this configuration. We have generated a very basic initial seating for you. Now we are improving and optimizing it.<p>You can wait a few hours for an improved version. Or you can <a href="#" onclick="mr.optimizeSeating(null, \'[0]\');">click here</a> and optimize it right now.</p>', $this->hash);
				if ($create)
				{
					$ps = $this->calculatePlayersScore($result->seating);
					$ns = $this->calculateNumbersScore($result->seating);
					$ts = $this->calculateTablesScore($result->seating);
				}
			}
			if ($create)
			{
				Db::exec(get_label('seating'),
					'INSERT INTO seatings (hash, seating, players_score, numbers_score, tables_score,' .
					' players_state, numbers_state, tables_state) VALUES (?, ?, ?, ?, ?, "", "", "")',
					$this->hash, json_encode($result->seating), $ps, $ns, $ts);
			}
		}
		return $result;
	}
}


// Reindexes a [round][table][seat] seating so every level is a dense, ascending,
// 0-based array. Seatings extracted from game records are keyed by (game_num - 1) /
// (table_num - 1), so a tournament whose numbering does not start at 1 yields
// non-contiguous keys; json_encode then serializes those as a JSON object
// ({"11":...}) instead of an array. Reloaded without assoc mode such objects become
// stdClass, and the optimizers' count()/index access then fail under PHP 8. Running
// every seating through this on creation and on load keeps the stored form a proper
// array. Accepts arrays or stdClass at any level; rounds/tables are ordered by key.
function reindex_seating($seating)
{
	$seating = (array)$seating;
	ksort($seating, SORT_NUMERIC);
	$rounds = array();
	foreach ($seating as $round)
	{
		$round = (array)$round;
		ksort($round, SORT_NUMERIC);
		$tables = array();
		foreach ($round as $table)
		{
			$tables[] = array_values((array)$table);
		}
		$rounds[] = $tables;
	}
	return $rounds;
}

// Ensures a canonical seating row exists in the seatings table for the given $seating array.
// Derives SeatingDef from the seating, normalizes restrictions to get the canonical hash,
// and inserts a new row only if that hash is absent. Returns the hash on insert, null otherwise.
// Remaps whatever values are in a [round][table][seat] seating array to a dense 0-based
// integer range. Use this before ensure_seating_existance when the seating may contain
// raw user/player IDs rather than already-compact slot indices.
// Checks a seating that is still written in user IDs, before normalize_seating_to_indices()
// turns its values into player numbers. That function numbers every distinct value it finds,
// so anything that is not a real user ID becomes a player of its own. An event was stored
// with an unfilled round written as ten zeros; the zero was numbered like everybody else,
// which invented a fourteenth player who sat alone in that round - and since he never shared
// a table with any of the real thirteen, SeatingDef inferred a "keep apart" rule between him
// and every one of them. None of it was real. A user ID is always positive, and one person
// cannot take two seats in the same round, so both of those are rejected here.
function seating_has_valid_player_ids($seating)
{
	if (!is_array($seating) || count($seating) == 0)
	{
		return false;
	}

	foreach ($seating as $round)
	{
		if (!is_array($round) || count($round) == 0)
		{
			return false;
		}

		$seated_this_round = array();
		foreach ($round as $table)
		{
			if (!is_array($table) || count($table) != 10)
			{
				return false;
			}
			foreach ($table as $seat)
			{
				$user_id = (int)$seat;
				if ($user_id <= 0 || isset($seated_this_round[$user_id]))
				{
					return false;
				}
				$seated_this_round[$user_id] = true;
			}
		}
	}
	return true;
}

// $value_to_index comes back filled with the translation this made - original value (usually a
// user id) to the 0-based player number it became - so a caller that knows something about
// those users, such as which of them are on a team, can say the same thing in player numbers.
function normalize_seating_to_indices($seating, &$value_to_index = null)
{
	$seating = reindex_seating($seating);
	$all_values = array();
	foreach ($seating as $round)
		foreach ($round as $table)
			foreach ($table as $v)
				$all_values[(int)$v] = true;
	ksort($all_values);
	$remap = array_flip(array_keys($all_values));
	$value_to_index = $remap;
	$result = array();
	foreach ($seating as $round)
	{
		$new_round = array();
		foreach ($round as $table)
		{
			$new_table = array();
			foreach ($table as $v)
				$new_table[] = $remap[(int)$v];
			$new_round[] = $new_table;
		}
		$result[] = $new_round;
	}
	return $result;
}

// Returns an object with:
//   ->hash    — canonical hash of the seating
//   ->created — true if a new row was inserted into seatings, false otherwise
// Checks that a seating really is an instance of the definition it claims to be: every table
// seats ten players, nobody sits down twice in the same round, and every player plays exactly
// $games games. The games count is part of the seating's identity - it is written into the
// hash - so a seating where players play different numbers of games is not that seating at
// all, and storing it under that hash makes the row describe something it is not.
//
// Extraction from historical game records produces such rows: real events have players who
// missed rounds, and one extracted seating had a whole round filled by a single player
// repeated ten times, which kept his total at the expected ten games while nine other players
// were simply absent from that round - so a plain per-player total is not enough to catch it.
function seating_is_well_formed($seating, $players, $tables, $games)
{
	if (!is_array($seating) || count($seating) == 0 || $players <= 0 || $tables <= 0 || $games <= 0)
	{
		return false;
	}

	$played = array_fill(0, $players, 0);
	foreach ($seating as $round)
	{
		if (!is_array($round) || count($round) < 1 || count($round) > $tables)
		{
			return false;
		}

		$seated_this_round = array();
		foreach ($round as $table)
		{
			if (!is_array($table) || count($table) != 10)
			{
				return false;
			}
			foreach ($table as $seat)
			{
				$player = (int)$seat;
				if ($player < 0 || $player >= $players || isset($seated_this_round[$player]))
				{
					return false;
				}
				$seated_this_round[$player] = true;
				++$played[$player];
			}
		}
	}

	foreach ($played as $games_played)
	{
		if ($games_played != $games)
		{
			return false;
		}
	}
	return true;
}

// Team size of a tournament and the user ids on each team. Returns a size of 1 and no teams for
// an individual tournament. A seating cannot say which of its players are teammates - a
// teammate pair and two players who merely never met look alike in it - so whoever builds one
// has to read that from the tournament.
function tournament_teams($tournament_id)
{
	list($team_size) = Db::record(get_label('tournament'), 'SELECT team_size FROM tournaments WHERE id = ?', $tournament_id);
	$team_size = (int)$team_size;
	if ($team_size <= 1)
	{
		return array(1, array());
	}

	$by_team = array();
	$query = new DbQuery(
		'SELECT user_id, team_id FROM tournament_regs'.
		' WHERE tournament_id = ? AND team_id IS NOT NULL AND (flags & ?) <> 0 AND (flags & ?) = 0'.
		' ORDER BY team_id, reg_order, user_id',
		$tournament_id, USER_PERM_PLAYER, USER_TOURNAMENT_FLAG_NOT_ACCEPTED);
	while ($row = $query->next())
	{
		$by_team[(int)$row[1]][] = (int)$row[0];
	}
	ksort($by_team);
	return array($team_size, array_values($by_team));
}

// Says the separate pairs in the player numbers of one particular seating. A pair with a player
// who did not sit in it is dropped - the rule is about two people, and with one of them absent
// the seating says nothing about it either way.
function restrictions_in_indexes($pairs_by_user, $user_to_index)
{
	$result = array();
	foreach ($pairs_by_user as $pair)
	{
		$u1 = (int)$pair[0];
		$u2 = (int)$pair[1];
		if (isset($user_to_index[$u1]) && isset($user_to_index[$u2]))
		{
			$result[] = array((int)$user_to_index[$u1], (int)$user_to_index[$u2]);
		}
	}
	return $result;
}

// Says the teams in the player numbers of one particular seating. A team whose players are not
// all in this seating is dropped: only whole teams can sit on the equal blocks the hash
// describes, and SeatingDef falls back to an individual seating when they do not add up.
function teams_in_indexes($teams_by_user, $team_size, $user_to_index)
{
	if ($team_size <= 1 || empty($teams_by_user))
	{
		return array();
	}

	$teams = array();
	foreach ($teams_by_user as $members)
	{
		$seated = array();
		foreach ($members as $user_id)
		{
			if (isset($user_to_index[(int)$user_id]))
			{
				$seated[] = (int)$user_to_index[(int)$user_id];
			}
		}
		if (count($seated) == $team_size)
		{
			$teams[] = $seated;
		}
	}
	return $teams;
}

// Drops an event's stored seating when the scheme it was built for no longer holds.
//
// A seating is made for a particular number of players at a particular number of tables over a
// particular number of games - that is what its hash says and what its rows are. Change any of
// them and what is stored describes a different round: a seating for fifteen leaves the sixteenth
// player with nowhere to sit, and nothing downstream notices, because every page reads misc and
// takes it at its word.
//
// Dropping it loses nothing that matters. It is a plan, not a record - the games hold the record -
// and seating_optimization.php rebuilds misc.seating from the game records for a round that has
// already been played. What it does lose is the arrangement the organizer may have been looking
// at, which is why this only fires when the scheme really changed rather than on every save.
//
// Returns true when a seating was dropped, so the caller can say so.
function clear_event_seating_on_scheme_change($event_id, $old_scheme, $new_scheme)
{
	// A null anywhere means "not set"; compare them as numbers so null and 0 do not look like a
	// change from one to the other.
	$normalize = function($scheme)
	{
		return array((int)$scheme[0], (int)$scheme[1], (int)$scheme[2]);
	};
	if ($normalize($old_scheme) === $normalize($new_scheme))
	{
		return false;
	}
	return clear_event_seating($event_id);
}

// Removes misc.seating from an event, leaving the rest of misc alone. Returns true when there
// was one to remove.
function clear_event_seating($event_id)
{
	list($misc_str) = Db::record(get_label('event'), 'SELECT misc FROM events WHERE id = ?', $event_id);
	if (is_null($misc_str))
	{
		return false;
	}
	$misc = json_decode($misc_str);
	if (is_null($misc) || !isset($misc->seating))
	{
		return false;
	}
	unset($misc->seating);
	Db::exec(get_label('event'), 'UPDATE events SET misc = ? WHERE id = ?', json_encode($misc), (int)$event_id);
	return true;
}

// $team_size and $teams describe a team tournament: how many players are on a team, and which
// of this seating's player numbers make up each one. Pass them whenever they are known - the
// seating itself cannot tell a teammate pair from two players who merely never met.
//
// $restrictions are the pairs that were meant to be kept apart, in this seating's player numbers
// - see teams_in_indexes() and restrictions_in_indexes() for turning user ids into those. They
// are candidates, not facts: SeatingDef keeps only the ones the seating actually bears out.
function ensure_seating_existance($seating, $team_size = 1, $teams = null, $restrictions = null)
{
	// Guarantee a dense 0-based structure so the stored JSON is an array, never an object.
	$seating = reindex_seating($seating);
	$seatingDef = new SeatingDef($seating, 0, 0, $restrictions, $team_size, $teams);

	$result = new stdClass();
	$result->hash    = null;
	$result->created = false;

	// Reject impossible configurations:
	// - players * games_per_player must be a positive multiple of 10 (10 seats per game).
	// - tables * 10 must not exceed players (a player can occupy only one table per round).
	// games == 0 from the array constructor already signals invalid input.
	$total_slots = $seatingDef->players * $seatingDef->games;
	if ($total_slots <= 0 || $total_slots % 10 !== 0 || $seatingDef->tables * 10 > $seatingDef->players)
	{
		return $result;
	}

	// The parameters promise that every player plays $games games; a seating that does not keep
	// that promise is rejected rather than stored under a hash that misdescribes it. Extracted
	// historical seatings are the ones that fail this - see seating_is_well_formed().
	if (!seating_is_well_formed($seating, $seatingDef->players, $seatingDef->tables, $seatingDef->games))
	{
		return $result;
	}

	$mapping = $seatingDef->normalizeRestrictions();
	$result->hash = $seatingDef->hash;

	$row = (new DbQuery('SELECT hash FROM seatings WHERE hash = ?', $seatingDef->hash))->next();
	if ($row)
	{
		return $result;
	}

	$normalized_seating = SeatingDef::applyMapping($seating, $mapping);
	$ps = $seatingDef->calculatePlayersScore($normalized_seating);
	$ns = $seatingDef->calculateNumbersScore($normalized_seating);
	$ts = $seatingDef->calculateTablesScore($normalized_seating);
	Db::exec(get_label('seating'),
		'INSERT INTO seatings (hash, seating, players_score, numbers_score, tables_score,' .
		' players_state, numbers_state, tables_state) VALUES (?, ?, ?, ?, ?, "", "", "")',
		$seatingDef->hash, json_encode($normalized_seating), $ps, $ns, $ts);

	$result->created = true;
	return $result;
}

?>
