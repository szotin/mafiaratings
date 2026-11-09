<?php

require_once 'screens.php';
require_once '../../include/scoring.php'; // the flags a game writes down about each player

// Frame 4 of the round: who has been winning, over the whole tournament.
//
// A game the town wins ends with no mafia left at the table; a game the mafia wins ends with
// the two sides level, so the number of mafiosi still sitting there says whether it was a 1 on
// 1, a 2 on 2 or a 3 on 3 - which is the thing players argue about. The two sides stand at
// either end of the panel with their share between them, cut into those kinds.
//
// A game handed over by a team kick out was not won at the table at all, and has no count of
// mafiosi left to say how close it was. On the mafia's side it is a kind of its own beside the
// three, which is where it belongs - the side already reads as a run of kinds, and a win nobody
// played out is the one worth telling apart. The town's share is not cut into anything, so a
// kind there would be the only one and would read as the whole of what the town's wins are made
// of; its kick outs stay in with the rest.
//
// Under it, the things worth saying about the tournament as a whole. A card nobody has earned
// yet is left out and the rest share the row, so the frame is never half empty.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

// One game, or a group of games that came out the same way, added to the counts the panel is
// drawn from.
function obs_stats_add(&$town, &$maf, &$maf_kick, $result, $maf_alive, $team_kick_out, $count)
{
	switch ((int)$result)
	{
	case GAME_RESULT_TOWN:
		// A game given away by a team kick out was not won at the table, but a win is a win
		// when the question is who has been winning, so it is counted for the side it was
		// handed to - it simply has no score to be counted by. The town's wins are not cut into
		// kinds, so this is where it ends: in with the rest of them.
		$town += $count;
		break;
	case GAME_RESULT_MAFIA:
		if ($team_kick_out)
		{
			// Handed over rather than played out, and so with no count of mafiosi to say how
			// close it was. It stands beside the three kinds as one of its own.
			$maf_kick += $count;
			break;
		}
		// Three mafiosi is as many as there are, so anything else is a game whose counts were
		// never written down: it belongs with the wins of no particular kind.
		$alive = is_null($maf_alive) ? 0 : (int)$maf_alive;
		$maf[($alive >= 1 && $alive <= 3) ? $alive : 0] += $count;
		break;
	}
	// A tie belongs to neither side and is left out of the question altogether.
}

// The games that have been played but not written down yet. The referee is on the last screen
// of the game handing out the bonus points, and until they leave it the game is nowhere in the
// games table - see GAMETIME_END, and the create op in api/ops/game.php, which is what moves it
// there and takes the row away from here, so nothing is ever counted in both places.
//
// This screen counts them. Who has been winning is settled the moment a table is done, and a
// tournament that waits for the paperwork shows the room a count it watched being overtaken.
// The standings do not count them, and must not: the bonus points are part of what a place in
// that table is made of, so a game counted before they are handed out would move players up and
// then move them down again.
//
// Each game is read the way Game::update() reads one it is writing down, and the flags the two
// facts go by are worked out by the class that works them out on the way in - the same code, so
// a game counts here for exactly what it will count for once it is written down. Constructing
// GamePlayersStats touches nothing: the writing is in its save(), which is not called.
function obs_stats_unrecorded($c)
{
	$games = array();
	$query = $c->is_tournament
		? new DbQuery('SELECT g.game FROM current_games g JOIN events e ON e.id = g.event_id'.
			' WHERE e.tournament_id = ?', $c->tournament_id)
		: new DbQuery('SELECT game FROM current_games WHERE event_id = ?', $c->event_id);
	while ($row = $query->next())
	{
		list ($json) = $row;
		$data = json_decode($json);
		// A game still being played has no result to count, and one that never reached a result
		// at all - a table that gave up on it - has none either.
		if (!is_object($data) || !isset($data->time->time) || $data->time->time != GAMETIME_END ||
			!isset($data->winner))
		{
			continue;
		}

		$g = new stdClass();
		$g->result = GAME_RESULT_TIE;
		if ($data->winner == 'maf')
		{
			$g->result = GAME_RESULT_MAFIA;
		}
		else if ($data->winner == 'civ')
		{
			$g->result = GAME_RESULT_TOWN;
		}

		// Who was still at the table when it ended, and whether it was handed over by a team
		// kick out - the same reckoning as in Game::update().
		$g->maf_alive = 0;
		$g->team_kick_out = false;
		$players = (isset($data->players) && is_array($data->players)) ? $data->players : array();
		foreach ($players as $p)
		{
			if (!is_object($p))
			{
				continue;
			}
			if (isset($p->death))
			{
				$death = $p->death;
				$death_type = is_string($death) ? $death : (isset($death->type) ? $death->type : '');
				if ($death_type == DEATH_TYPE_TEAM_KICK_OUT)
				{
					$g->team_kick_out = true;
				}
				continue;
			}
			if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
			{
				++$g->maf_alive;
			}
		}

		// What each seat earned, in the flags the facts below are counted by. A game this
		// cannot be worked out for still counts for the win it was: half of it on the screen is
		// better than none of it, and a screen between games is no place to stop over one.
		$g->flags = array();
		$g->nicks = array();
		try
		{
			$stats = new GamePlayersStats(new Game($data));
			foreach ($stats->players as $i => $p)
			{
				$g->flags[] = isset($p->scoring_flags) ? (int)$p->scoring_flags : 0;
				$g->nicks[] = (isset($players[$i]) && isset($players[$i]->name)) ? $players[$i]->name : '';
			}
		}
		catch (Throwable $e)
		{
			$g->flags = array();
			$g->nicks = array();
		}
		$games[] = $g;
	}
	return $games;
}

// How many of the unrecorded games carry a flag against any seat, and which seats those were.
function obs_stats_unrecorded_flag($unrecorded, $flag, &$nicks)
{
	$count = 0;
	foreach ($unrecorded as $g)
	{
		$hit = false;
		foreach ($g->flags as $i => $flags)
		{
			if (($flags & $flag) != 0)
			{
				$hit = true;
				$nicks[] = isset($g->nicks[$i]) ? $g->nicks[$i] : '';
			}
		}
		if ($hit)
		{
			++$count;
		}
	}
	return $count;
}

$town = 0;
$maf = array(0, 0, 0, 0); // by the mafia left at the table; [0] is the games that never said
$maf_kick = 0;            // handed to the mafia by a team kick out, never played out at the table
// The brackets round the mask are not decoration: & binds looser than <> in MySQL, so without
// them the server compares 8 with 0 first and ands the flags with the 1 that comes out.
$scope = $c->is_tournament ? 'tournament_id' : 'event_id';
$scope_id = $c->is_tournament ? $c->tournament_id : $c->event_id;
$query = new DbQuery(
	'SELECT result, maf_alive, (flags & ' . GAME_FLAG_TEAM_KICK_OUT . ') <> 0, COUNT(*) FROM games'.
	' WHERE ' . $scope . ' = ? AND (flags & ' . GAME_FLAG_CANCELED . ') = 0'.
	' GROUP BY result, maf_alive, (flags & ' . GAME_FLAG_TEAM_KICK_OUT . ') <> 0',
	$scope_id);
while ($row = $query->next())
{
	list ($result, $maf_alive, $team_kick_out, $count) = $row;
	obs_stats_add($town, $maf, $maf_kick, $result, $maf_alive, $team_kick_out, (int)$count);
}

// And the games the room has watched but the paperwork has not caught up with.
$unrecorded = obs_stats_unrecorded($c);
foreach ($unrecorded as $u)
{
	obs_stats_add($town, $maf, $maf_kick, $u->result, $u->maf_alive, $u->team_kick_out, 1);
}

$mafia = array_sum($maf) + $maf_kick;
$total = $town + $mafia;
if ($total <= 0)
{
	return; // nothing played yet, or nothing but ties
}

$town_share = (int)round(100 * $town / $total);

// The things the tournament has to show for itself. Both are written down against the player
// they happened to - see the scoring flags in include/scoring.php - so one query over the
// games of this tournament answers each of them.
function obs_stats_fact_games($scope, $scope_id, $flag)
{
	list ($count) = Db::record(get_label('game'),
		'SELECT COUNT(DISTINCT g.id) FROM players p JOIN games g ON g.id = p.game_id'.
		' WHERE g.' . $scope . ' = ? AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0 AND (p.flags & ' . $flag . ') <> 0',
		$scope_id);
	return (int)$count;
}

$facts = array();

// Three of three: the whole mafia named by a player shot in the first night. Everyone who did
// it is named under the number, latest first - it is the rarest thing on the screen, and a
// tournament that has it four times has four people who want to see their name there. Which
// game it was in is left out: by the time the screen comes round nobody is looking it up, and
// without it the whole list fits on a line.
$done = 0;
$names = array();
// The ones not written down yet stand first: they are the latest, which is the order the rest
// come in.
$fresh = array();
$done += obs_stats_unrecorded_flag($unrecorded, SCORING_FLAG_FIRST_LEGACY_3, $fresh);
foreach ($fresh as $nick)
{
	if ($nick != '')
	{
		$names[$nick] = isset($names[$nick]) ? $names[$nick] + 1 : 1;
	}
}
$query = new DbQuery(
	'SELECT p.nick_name FROM players p JOIN games g ON g.id = p.game_id'.
	' WHERE g.' . $scope . ' = ? AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0 AND (p.flags & ' . SCORING_FLAG_FIRST_LEGACY_3 . ') <> 0'.
	' ORDER BY g.end_time DESC', $scope_id);
while ($row = $query->next())
{
	list ($nick) = $row;
	++$done;
	if ($nick == '')
	{
		continue; // a record that kept no name has nobody to name
	}
	// Somebody who did it more than once is named once, with how many times after them - a
	// name written out twice reads as a mistake rather than as a feat done twice.
	if (!isset($names[$nick]))
	{
		$names[$nick] = 0;
	}
	++$names[$nick];
}

$best = array();
foreach ($names as $nick => $times)
{
	$best[] = htmlspecialchars($nick) . ($times > 1 ? ' (' . $times . ')' : '');
}

if ($done > 0)
{
	$fact = new stdClass();
	$fact->name = get_label('Perfect best guess');
	$fact->value = $done;
	$fact->of = '';
	$fact->sub = implode(', ', $best);
	$facts[] = $fact;
}

// How often the mafia found the sheriff straight away, which is the other thing a tournament
// is talked about by.
$none = array();
$sheriff = obs_stats_fact_games($scope, $scope_id, SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT) +
	obs_stats_unrecorded_flag($unrecorded, SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT, $none);
if ($sheriff > 0)
{
	$fact = new stdClass();
	$fact->name = get_label('Sheriff killed on night 1');
	$fact->value = $sheriff;
	$fact->of = get_label('of [0]', $total);
	$fact->sub = get_label('[0]% of games', (int)round(100 * $sheriff / $total));
	$facts[] = $fact;
}

obs_frame_start('f4');
obs_head($c, get_label('Wins'),
	get_label('all tables') . ' · ' . get_label('finished games'), '',
	obs_count($total, get_label('game|games|games')));
obs_foot($c->table_num, obs_judge($c));

echo '<div class="f4main glass">';
echo '<div class="sides">';
echo '<div class="side t"><div class="k">' . get_label('Town') . '</div>' .
	'<div class="pc">' . $town_share . '%</div>' .
	'<div class="ct">' . obs_count($town, get_label('win|wins|wins')) . '</div></div>';
echo '<div class="side m"><div class="k">' . get_label('Mafia') . '</div>' .
	'<div class="pc">' . (100 - $town_share) . '%</div>' .
	'<div class="ct">' . obs_count($mafia, get_label('win|wins|wins')) . '</div></div>';
echo '</div>';

// The one bar the two sides share. A kind nobody has played is left out of it altogether, and
// the mafia's kinds get darker the closer the game was.
$bar = array(
	array($town, 'c-t', get_label('Town')),
	array($maf[0], 'c-0', get_label('Mafia win')),
	array($maf[1], 'c-1', get_label('[0] vs [1]', 1, 1)),
	array($maf[2], 'c-2', get_label('[0] vs [1]', 2, 2)),
	array($maf[3], 'c-3', get_label('[0] vs [1]', 3, 3)),
	array($maf_kick, 'c-k', get_label('Mod team kills')));

echo '<div class="split">';
foreach ($bar as $part)
{
	list ($count, $colour) = $part;
	if ($count > 0)
	{
		echo '<i class="' . $colour . '" style="flex: ' . $count . ' 1 0"></i>';
	}
}
echo '</div>';

echo '<div class="lg">';
echo '<div><span class="sw c-t"></span>' . get_label('Town') . '<b>' . $town . '</b></div>';
echo '<div class="mg">';
for ($i = 1; $i < count($bar); ++$i)
{
	list ($count, $colour, $name) = $bar[$i];
	if ($count > 0)
	{
		echo '<div><span class="sw ' . $colour . '"></span>' . $name . '<b>' . $count . '</b></div>';
	}
}
echo '</div></div>';
echo '</div>';

if (!empty($facts))
{
	echo '<div class="facts" style="grid-template-columns: repeat(' . count($facts) . ', 1fr)">';
	foreach ($facts as $fact)
	{
		echo '<div class="fact glass">';
		echo '<div class="k">' . $fact->name . '</div>';
		echo '<div class="v">' . $fact->value . ($fact->of == '' ? '' : '<small>' . $fact->of . '</small>') . '</div>';
		echo '<div class="s">' . $fact->sub . '</div>';
		echo '</div>';
	}
	echo '</div>';
}
obs_frame_end();

?>
