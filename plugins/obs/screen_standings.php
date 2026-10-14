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

obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . get_label('Standings') . '</div>',
	'');

$pic = new Picture(USER_TOURNAMENT_PICTURE, new Picture(USER_CLUB_PICTURE, new Picture(USER_PICTURE)));

// The number of rows a column holds is handed to the stylesheet so that every row is the same
// height whether its column is full or not - otherwise the last column, holding the tail of the
// table, would stretch its few rows into bars twice the size of the ones beside them.
echo '<div class="screen-body standings" style="--rows: ' . $rows . '">';
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

?>
