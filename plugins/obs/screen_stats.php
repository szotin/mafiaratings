<?php

require_once 'screens.php';

// How the tournament has been going, in one picture: who has been winning, and how close the
// mafia's wins were. A game the town wins ends with no mafia left at the table; a game the
// mafia wins ends with the two sides level, so the number of mafiosi still sitting there says
// whether it was a 1 on 1, a 2 on 2 or a 3 on 3 - which is the thing players argue about.
//
// Nothing else is on this screen on purpose. It is up for ten seconds between games, and one
// pie that can be read at a glance says more in that time than a page of numbers would.
//
// A game written down before the two counts existed has nothing to say about how it ended, and
// those - where there are any - stand as mafia wins of no particular kind rather than being
// quietly dropped, which would make the pie disagree with the number of games played.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

$town = 0;
$tie = 0;
$ppk_town = 0; // a team kick out hands the win to the other side, so each has its own count
$ppk_maf = 0;
$maf = array(0, 0, 0, 0); // by the mafia left at the table; [0] is the games that never said
// The brackets round the mask are not decoration: & binds looser than <> in MySQL, so without
// them the server compares 8 with 0 first and ands the flags with the 1 that comes out.
// Over the tournament, or over the event when it is one of its own.
$query = new DbQuery(
	'SELECT result, maf_alive, (flags & ' . GAME_FLAG_TEAM_KICK_OUT . ') <> 0, COUNT(*) FROM games'.
	' WHERE ' . ($c->is_tournament ? 'tournament_id' : 'event_id') . ' = ? AND (flags & ' . GAME_FLAG_CANCELED . ') = 0'.
	' GROUP BY result, maf_alive, (flags & ' . GAME_FLAG_TEAM_KICK_OUT . ') <> 0',
	$c->is_tournament ? $c->tournament_id : $c->event_id);
while ($row = $query->next())
{
	list ($result, $maf_alive, $team_kick_out, $count) = $row;
	$count = (int)$count;

	// A game given away by a team kick out was not won at the table, so it is counted on its
	// own rather than among the wins played out - and which side was handed it still matters,
	// so the two are kept apart. A team kick out always names a winner; anything else carrying
	// the flag falls through and is counted by its result like any other game.
	if ($team_kick_out && (int)$result == GAME_RESULT_TOWN)
	{
		$ppk_town += $count;
		continue;
	}
	if ($team_kick_out && (int)$result == GAME_RESULT_MAFIA)
	{
		$ppk_maf += $count;
		continue;
	}

	switch ((int)$result)
	{
	case GAME_RESULT_TOWN:
		$town += $count;
		break;
	case GAME_RESULT_MAFIA:
		// Three mafiosi is as many as there are, so anything else is a game whose counts were
		// never written down and belongs with the ones that did not say.
		$alive = is_null($maf_alive) ? 0 : (int)$maf_alive;
		$maf[($alive >= 1 && $alive <= 3) ? $alive : 0] += $count;
		break;
	default:
		$tie += $count;
		break;
	}
}

$total = $town + $tie + $ppk_town + $ppk_maf + array_sum($maf);
if ($total <= 1)
{
	// Nothing played yet, or one game - and one game is not a share of anything. A single
	// slice filling the circle says only what the results screen has just said at more length.
	return;
}

// Two families of colour, because whose win it was is the thing to see first and how it was
// won only second: the reds are the town, the greys the black team, and a game given away by a
// team kick out is a shade of whichever side was handed it rather than a colour of its own.
// Within the greys, the more mafiosi were left sitting there the darker the slice: a 3 on 3 is
// the blackest of the black wins. Only the tie stands outside both, because it belongs to
// neither.
// The legend is read in this order; the circle is drawn in the order of the second number,
// which is not the same thing. The list wants to read the way anyone would say it out loud,
// and the circle wants each family to be one unbroken arc with the two games nobody played
// out meeting at the top, one on each side of twelve o'clock - which is exactly what they
// are, the same accident falling to either side.
$slices = array();
function obs_stats_slice(&$slices, $count, $draw, $colour, $name)
{
	if ($count <= 0)
	{
		return;
	}
	$slice = new stdClass();
	$slice->count = $count;
	$slice->draw = $draw;
	$slice->colour = $colour;
	$slice->name = $name;
	$slices[] = $slice;
}

obs_stats_slice($slices, $town, 1, '#e03a3e', get_label('Town win'));
obs_stats_slice($slices, $ppk_town, 0, '#8c2126', get_label('Mod team kill, town win'));
obs_stats_slice($slices, $maf[1], 3, '#8b96a2', get_label('Mafia [0] on [1]', 1, 1));
obs_stats_slice($slices, $maf[2], 4, '#626d79', get_label('Mafia [0] on [1]', 2, 2));
obs_stats_slice($slices, $maf[3], 5, '#3a434d', get_label('Mafia [0] on [1]', 3, 3));
obs_stats_slice($slices, $maf[0], 6, '#b4bdc6', get_label('Mafia win'));
obs_stats_slice($slices, $ppk_maf, 7, '#1b2026', get_label('Mod team kill, mafia win'));
// The tie belongs to neither side, so it sits on the line between them.
obs_stats_slice($slices, $tie, 2, '#c9a227', get_label('Tie'));

// The circle, in its own order. A copy, so that the legend keeps the order it was built in.
$drawn = $slices;
usort($drawn, function ($a, $b) { return $a->draw - $b->draw; });

// The share of the whole, as it is written on the screen. A slice that is really there but
// rounds away to nothing is said to be under one per cent rather than being called nothing.
function obs_stats_share($count, $total)
{
	$share = round(100 * $count / $total);
	return ($share <= 0 ? '<1' : $share) . '%';
}

// A slice of the pie, drawn from the middle out. The circle starts at twelve o'clock and goes
// round clockwise, the way a clock does and the way the legend beside it is read.
function obs_stats_path($from, $to, $cx, $cy, $r)
{
	$x1 = $cx + $r * sin($from);
	$y1 = $cy - $r * cos($from);
	$x2 = $cx + $r * sin($to);
	$y2 = $cy - $r * cos($to);
	$large = ($to - $from) > M_PI ? 1 : 0;
	return sprintf('M %.3f %.3f L %.3f %.3f A %.3f %.3f 0 %d 1 %.3f %.3f Z',
		$cx, $cy, $x1, $y1, $r, $r, $large, $x2, $y2);
}

obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . get_label('Wins') . '</div>' .
		'<div class="head-sub">' . get_label('Games played') . ': ' . $total . '</div>',
	'');

echo '<div class="screen-body stats">';
echo '<div class="stats-main">';

$cx = 50;
$cy = 50;
$r = 47;
echo '<div class="stats-pie"><svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet">';
if (count($drawn) == 1)
{
	// One slice is the whole circle, and an arc that ends where it starts draws nothing at all.
	echo '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="' . $drawn[0]->colour . '"/>';
}
else
{
	$angle = 0;
	foreach ($drawn as $slice)
	{
		$sweep = 2 * M_PI * $slice->count / $total;
		echo '<path d="' . obs_stats_path($angle, $angle + $sweep, $cx, $cy, $r) . '" fill="' . $slice->colour . '"/>';
		$angle += $sweep;
	}
}

// The share written on the slice itself, so that the picture can be read without going to the
// legend for it. Only where it fits: a sliver would wear its number across its neighbours.
if (count($drawn) == 1)
{
	// Nothing to point at but the whole circle, so the share goes in the middle of it.
	echo '<text class="stats-share" x="' . $cx . '" y="' . $cy . '">' .
		obs_stats_share($drawn[0]->count, $total) . '</text>';
}
else
{
	$angle = 0;
	foreach ($drawn as $slice)
	{
		$sweep = 2 * M_PI * $slice->count / $total;
		if ($sweep >= 0.55)
		{
			$middle = $angle + $sweep / 2;
			printf('<text class="stats-share" x="%.3f" y="%.3f">%s</text>',
				$cx + 0.62 * $r * sin($middle), $cy - 0.62 * $r * cos($middle),
				obs_stats_share($slice->count, $total));
		}
		$angle += $sweep;
	}
}
echo '</svg></div>';

echo '<div class="stats-legend">';
foreach ($slices as $slice)
{
	echo '<div class="stats-row">';
	echo '<div class="stats-chip" style="background-color: ' . $slice->colour . '"></div>';
	echo '<div class="stats-name">' . $slice->name . '</div>';
	echo '<div class="stats-count">' . $slice->count . '</div>';
	echo '<div class="stats-share-num">' . obs_stats_share($slice->count, $total) . '</div>';
	echo '</div>';
}
echo '</div>';

echo '</div>'; // stats-main

// How the two sides stand against each other, which is the one thing the slices alone do not
// say outright: a team kick out counts for the side it was handed to, because a win is a win
// when the question is who has been winning. Ties stand apart and are named only where there
// have been any.
//
// The line reads the way the circle above it is laid out rather than the way the legend
// beside it is: the black team holds the left of the circle, the town the right, and the tie
// sits on the line between them at the bottom. Naming them in any other order sets the eye
// looking for the red on the left.
// These three are lettering rather than picture, and lettering has to answer to the ground it
// is written on - a grey that reads as the black team on a dark page disappears on a pale one.
// So the side is named in a class and the stylesheet picks the colour for the page in hand.
function obs_stats_total($side, $name, $count, $total)
{
	if ($count <= 0)
	{
		return;
	}
	echo '<div class="stats-total">';
	echo '<div class="stats-total-name">' . $name . '</div>';
	echo '<div class="stats-total-share ' . $side . '">' . obs_stats_share($count, $total) . '</div>';
	echo '</div>';
}

echo '<div class="stats-totals">';
obs_stats_total('mafia', get_label('Mafia'), $ppk_maf + array_sum($maf), $total);
obs_stats_total('tie', get_label('Ties'), $tie, $total);
obs_stats_total('town', get_label('Town'), $town + $ppk_town, $total);
echo '</div>';

echo '</div>';

?>
