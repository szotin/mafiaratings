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

list ($scoring, $normalizer) = Db::record(get_label('tournament'),
	'SELECT s.scoring, n.normalizer FROM tournaments t'.
	' JOIN scoring_versions s ON s.scoring_id = t.scoring_id AND s.version = t.scoring_version'.
	' LEFT OUTER JOIN normalizer_versions n ON n.normalizer_id = t.normalizer_id AND n.version = t.normalizer_version'.
	' WHERE t.id = ?', $c->tournament_id);

$scoring = json_decode($scoring);
$scoring->id = (int)$c->scoring_id;           // both are wanted for the caching inside scoring.php
$scoring->version = (int)$c->scoring_version;

$normalizer = json_decode(is_null($normalizer) ? '{}' : $normalizer);
$normalizer->id = (int)$c->normalizer_id;
$normalizer->version = (int)$c->normalizer_version;

$players = tournament_scores($c->tournament_id, $c->tournament_flags, NULL, SCORING_LOD_PER_GROUP,
	$scoring, $normalizer, json_decode($c->scoring_options));
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

obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . get_label('Standings') . '</div>',
	'');

$pic = new Picture(USER_TOURNAMENT_PICTURE, new Picture(USER_CLUB_PICTURE, new Picture(USER_PICTURE)));

// The number of rows a column holds is handed to the stylesheet so that every row is the same
// height whether its column is full or not - otherwise the last column, holding the tail of the
// table, would stretch its few rows into bars twice the size of the ones beside them.
echo '<div class="screen-body standings" style="--rows: ' . $rows . '">';
echo '<div class="standings-columns">';
$shown = 0;
for ($col = 0; $col < $columns && $shown < count($players); ++$col)
{
	echo '<div class="standings-column">';
	for ($i = 0; $i < $rows && $shown < count($players); ++$i)
	{
		$player = $players[$shown++];
		$pic->
			set($player->id, $player->name, $player->tournament_reg_flags, 't' . $c->tournament_id)->
			set($player->id, $player->name, $player->club_reg_flags, 'c' . $c->club_id)->
			set($player->id, $player->name, $player->flags);

		echo '<div class="standings-row">';
		echo '<div class="standings-place">' . $player->place . '</div>';
		echo '<div class="standings-photo" style="background-image: url(' . $pic->url(ICONS_DIR) . ')"></div>';
		echo '<div class="standings-name">' . htmlspecialchars($player->name) . '</div>';
		echo '<div class="standings-points">' . format_score($player->points) . '</div>';
		echo '</div>';
	}
	echo '</div>';
}
echo '</div>';
if ($note != '')
{
	echo '<div class="standings-note">' . $note . '</div>';
}
echo '</div>';

?>
