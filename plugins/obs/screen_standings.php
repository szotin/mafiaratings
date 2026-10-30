<?php

require_once 'screens.php';
require_once '../../include/scoring.php';

// The tournament table. Nothing is decided here about what may be seen: tournament_scores()
// already refuses to add up a tournament whose table is hidden, and leaves the bonus points out
// of the total when those are hidden - see is_hiding_bonus_needed() and _event_scores() in
// include/scoring.php. So an empty answer here means the site would not show the table either,
// and the screen simply does not come up.
//
//   rows, columns - how much of the table to fit on the screen, 10 by two by default.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

$rows = isset($_REQUEST['rows']) ? max((int)$_REQUEST['rows'], 1) : 10;
$columns = isset($_REQUEST['columns']) ? max((int)$_REQUEST['columns'], 1) : 2;

// An event of its own is added up on its own, by the scoring it is played with. A round of a
// tournament is not: what is worth putting on the screen there is the tournament table, which
// is what the table beside it is playing for.
list ($scoring_json) = Db::record(get_label('score'),
	'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $c->scoring_id, $c->scoring_version);
$scoring = json_decode($scoring_json);
$scoring->id = (int)$c->scoring_id;           // both are wanted for the caching inside scoring.php
$scoring->version = (int)$c->scoring_version;

if ($c->is_tournament)
{
	list ($normalizer) = Db::record(get_label('tournament'),
		'SELECT n.normalizer FROM tournaments t'.
		' LEFT OUTER JOIN normalizer_versions n ON n.normalizer_id = t.normalizer_id AND n.version = t.normalizer_version'.
		' WHERE t.id = ?', $c->tournament_id);
	$normalizer = json_decode(is_null($normalizer) ? '{}' : $normalizer);
	$normalizer->id = (int)$c->normalizer_id;
	$normalizer->version = (int)$c->normalizer_version;

	$players = tournament_scores($c->tournament_id, $c->tournament_flags, NULL, SCORING_LOD_PER_GROUP,
		$scoring, $normalizer, json_decode($c->scoring_options));
}
else
{
	$players = event_scores($c->event_id, NULL, SCORING_LOD_PER_GROUP,
		$scoring, json_decode($c->scoring_options), 0, 0);
}
if (empty($players))
{
	return; // hidden, or nothing played yet
}

$place = 0;
foreach ($players as $player)
{
	$player->place = ++$place;
}

// What this table is not telling. The site says the same thing in the same words above its own
// table - see show_hidden_table_message() in include/tournament.php - and the rounds it leaves
// out and the points it holds back are already gone from what we were given, so the table on
// the screen would otherwise quietly disagree with the one people can add up for themselves.
// A tournament that hides its table altogether never gets this far: the scores come back empty
// and the screen does not come up at all.
function obs_standings_note($c)
{
	if (!$c->is_tournament)
	{
		return ''; // an event of its own holds nothing back: there is one table and it is all here
	}
	if ($c->tournament_flags & TOURNAMENT_FLAG_FINISHED)
	{
		return ''; // it is all out in the open once the tournament is over
	}

	// Worth saying only once something has been played in the rounds being held back.
	$played = array();
	$query = new DbQuery(
		'SELECT DISTINCT e.round FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ?', $c->tournament_id);
	while ($row = $query->next())
	{
		list ($round) = $row;
		$played[(int)$round] = true;
	}

	$notes = array();
	switch (($c->tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
	{
	case 2:
		if (isset($played[1]))
		{
			$notes[] = get_label('Tournament tables of the finals are hidden for this tournament until it ends.');
		}
		break;
	case 3:
		if (isset($played[1]) || isset($played[2]))
		{
			$notes[] = get_label('Tournament tables of the finals and semi-finals are hidden for this tournament until it ends.');
		}
		break;
	}

	switch (($c->tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
	{
	case 1:
		$notes[] = get_label('Bonus points are hidden for this tournament until it ends.');
		break;
	case 2:
		if (isset($played[1]))
		{
			$notes[] = get_label('Bonus points of the finals are hidden for this tournament until it ends.');
		}
		break;
	case 3:
		if (isset($played[1]) || isset($played[2]))
		{
			$notes[] = get_label('Bonus points of the finals and semi-finals are hidden for this tournament until it ends.');
		}
		break;
	}
	return implode('<br>', $notes);
}

$note = obs_standings_note($c);

// Who is at a table right now. Their points are the points of the games already written down,
// so a line that is about to move is marked rather than left to look settled - see the note
// along the bottom.
function obs_standings_live($c)
{
	$live = array();
	$events = array();
	if ($c->is_tournament)
	{
		foreach ($c->rounds as $r)
		{
			$events[] = (int)$r->id;
		}
	}
	else
	{
		$events[] = (int)$c->event_id;
	}
	if (empty($events))
	{
		return $live;
	}

	$query = new DbQuery('SELECT game FROM current_games WHERE event_id IN (' . implode(',', $events) . ')');
	while ($row = $query->next())
	{
		list ($json) = $row;
		$log = json_decode($json);
		// A game on its last screen is played out: its points are already counted and the
		// players are up from the table.
		if (is_null($log) || !isset($log->time) || $log->time->time == GAMETIME_END || !isset($log->players))
		{
			continue;
		}
		foreach ($log->players as $p)
		{
			if (isset($p->id) && (int)$p->id > 0)
			{
				$live[(int)$p->id] = true;
			}
		}
	}
	return $live;
}

$live = obs_standings_live($c);
$shown_count = min(count($players), $rows * $columns);

obs_frame_start('f2');
obs_head($c->tournament_name, get_label('Standings'),
	get_label('all tables') . ' &middot; ' . get_label('finished games'),
	'<span class="mu">' . get_label('Places [0]-[1]', 1, $shown_count) . '</span>');

$pic = new Picture(USER_TOURNAMENT_PICTURE, new Picture(USER_CLUB_PICTURE, new Picture(USER_PICTURE)));

// The head of a column, which every column carries: the table is sorted by points, so that
// one is bright and the rest are not.
function obs_standings_head()
{
	echo '<div class="sth"><div></div><div></div><div></div>';
	echo '<div class="on">' . get_label('Points') . ' &#9662;</div>';
	echo '<div>' . get_label('Games') . '</div>';
	echo '<div>' . get_label('Wins') . '</div>';
	echo '<div>' . get_label('Avg.') . '</div></div>';
}

echo '<div class="st">';
$shown = 0;
for ($col = 0; $col < $columns && $shown < count($players); ++$col)
{
	echo '<div class="col">';
	obs_standings_head();
	for ($i = 0; $i < $rows && $shown < count($players); ++$i)
	{
		$player = $players[$shown++];
		$pic->
			set($player->id, $player->name, $player->tournament_reg_flags, 't' . $c->tournament_id)->
			set($player->id, $player->name, $player->club_reg_flags, 'c' . $c->club_id)->
			set($player->id, $player->name, $player->flags);

		$games = (int)$player->games_count;
		echo '<div class="str glass">';
		// The first three places are gold, silver and bronze; the rest are told by their number.
		echo '<div class="rk' . ($player->place <= 3 ? ' p' . $player->place : '') . '">' . $player->place . '</div>';
		// One ground for every face, so that twenty photographs read as one list.
		echo '<div class="av' . ($pic->has_image() ? '' : ' none') . '">' .
			($pic->has_image() ? '<img src="' . $pic->url(TNAILS_DIR) . '" alt="">' : '') . '</div>';
		echo '<div class="nm"><b>' . htmlspecialchars($player->name) . '</b>' .
			(isset($live[$player->id]) ? '<i class="live"></i>' : '') . '</div>';
		echo '<div class="pts">' . format_score($player->points) . '</div>';
		echo '<div class="n">' . $games . '</div>';
		echo '<div class="n">' . (int)$player->wins . '</div>';
		echo '<div class="avg">' . ($games > 0 ? number_format($player->points / $games, 2) : '&mdash;') . '</div>';
		echo '</div>';
	}
	echo '</div>';
}
echo '</div>';

// Along the bottom: that somebody is still playing, and whatever the tournament is holding
// back until it ends.
$notes = array();
if (!empty($live))
{
	$notes[] = '<i class="live"></i>' . get_label('The game is still on - the points will change when it ends');
}
if ($note != '')
{
	$notes[] = $note;
}
if (!empty($notes))
{
	echo '<div class="note">' . implode(' &middot; ', $notes) . '</div>';
}
obs_frame_end();

?>
