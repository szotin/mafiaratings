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
// Under it, the things worth saying about the tournament as a whole. A card nobody has earned
// yet is left out and the rest share the row, so the frame is never half empty.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

$town = 0;
$maf = array(0, 0, 0, 0); // by the mafia left at the table; [0] is the games that never said
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
	$count = (int)$count;

	switch ((int)$result)
	{
	case GAME_RESULT_TOWN:
		// A game given away by a team kick out was not won at the table, but a win is a win
		// when the question is who has been winning, so it is counted for the side it was
		// handed to - it simply has no score to be counted by.
		$town += $count;
		break;
	case GAME_RESULT_MAFIA:
		// Three mafiosi is as many as there are, so anything else is a game whose counts were
		// never written down, and a game given away never drew level at all: both belong with
		// the wins of no particular kind.
		$alive = ($team_kick_out || is_null($maf_alive)) ? 0 : (int)$maf_alive;
		$maf[($alive >= 1 && $alive <= 3) ? $alive : 0] += $count;
		break;
	}
	// A tie belongs to neither side and is left out of the question altogether.
}

$mafia = array_sum($maf);
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

// Three of three: the whole mafia named by a player shot in the first night. The last one to
// do it is named under the number - it is the one the table still remembers.
$perfect = obs_stats_fact_games($scope, $scope_id, SCORING_FLAG_FIRST_LEGACY_3);
if ($perfect > 0)
{
	$who = '';
	$query = new DbQuery(
		'SELECT p.nick_name, g.table_num, g.game_num FROM players p JOIN games g ON g.id = p.game_id'.
		' WHERE g.' . $scope . ' = ? AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0 AND (p.flags & ' . SCORING_FLAG_FIRST_LEGACY_3 . ') <> 0'.
		' ORDER BY g.end_time DESC LIMIT 1', $scope_id);
	if ($row = $query->next())
	{
		list ($nick, $table_num, $game_num) = $row;
		$who = htmlspecialchars($nick) . ' &middot; ' . get_label('Table [0]', (int)$table_num) . ', ' . get_label('Game [0]', (int)$game_num);
	}

	$fact = new stdClass();
	$fact->name = get_label('Perfect best guess');
	$fact->value = $perfect;
	$fact->of = '';
	$fact->sub = $who;
	$facts[] = $fact;
}

// How often the mafia found the sheriff straight away, which is the other thing a tournament
// is talked about by.
$sheriff = obs_stats_fact_games($scope, $scope_id, SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT);
if ($sheriff > 0)
{
	$fact = new stdClass();
	$fact->name = get_label('Sheriff killed first night');
	$fact->value = $sheriff;
	$fact->of = get_label('out of [0]', $total);
	$fact->sub = get_label('[0]% of games', (int)round(100 * $sheriff / $total));
	$facts[] = $fact;
}

obs_frame_start('f4');
obs_head($c->tournament_name, get_label('Wins'),
	get_label('all tables') . ' &middot; ' . get_label('finished games'),
	'<span class="mu">' . get_label('Games [0]', $total) . '</span>');

echo '<div class="f4main glass">';
echo '<div class="sides">';
echo '<div class="side t"><div class="k">' . get_label('Town') . '</div>' .
	'<div class="pc">' . $town_share . '%</div>' .
	'<div class="ct">' . get_label('wins: [0]', $town) . '</div></div>';
echo '<div class="side m"><div class="k">' . get_label('Mafia') . '</div>' .
	'<div class="pc">' . (100 - $town_share) . '%</div>' .
	'<div class="ct">' . get_label('wins: [0]', $mafia) . '</div></div>';
echo '</div>';

// The one bar the two sides share. A kind nobody has played is left out of it altogether, and
// the mafia's kinds get darker the closer the game was.
$bar = array(
	array($town, 'c-t', get_label('Town')),
	array($maf[0], 'c-0', get_label('Mafia win')),
	array($maf[1], 'c-1', get_label('Mafia [0] on [1]', 1, 1)),
	array($maf[2], 'c-2', get_label('Mafia [0] on [1]', 2, 2)),
	array($maf[3], 'c-3', get_label('Mafia [0] on [1]', 3, 3)));

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
