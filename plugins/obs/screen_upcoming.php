<?php

require_once 'screens.php';

// Who sits down next at this table. Shown only when there is a seating to read it from, which
// also settles the rest of the rules by itself: obs_context() walks the rounds in the order they
// are played and stops at the first one with a game left at this table, so after the last game
// of a round this is already the first game of the next one - and when the tournament has no
// game left anywhere, there is nothing to stop at and nothing is shown.

obs_screen_start();

$c = obs_context();
if (is_null($c) || $c->next_game_num <= 0)
{
	return;
}

$seating = obs_seating($c->event_id, $c->table_num, $c->next_game_num);
if (empty($seating))
{
	return;
}

$players = obs_players($seating, $c->event_id, $c->tournament_id, $c->club_id);

obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . get_label('Upcoming') . '</div>' .
		'<div class="head-sub">' . htmlspecialchars($c->event_name) . ' &middot; ' . get_label('Table [0], Game [1]', $c->table_num, $c->next_game_num) . '</div>',
	'');

// Five and five, the way a table is drawn. A game with fewer seats than that simply has no
// second row - an empty one would still take its share of the screen.
echo '<div class="screen-body upcoming">';
$number = 1;
foreach (array(array_slice($seating, 0, 5), array_slice($seating, 5)) as $row)
{
	if (empty($row))
	{
		continue;
	}
	obs_card_row(count($row));
	foreach ($row as $id)
	{
		obs_card($number++, isset($players[$id]) ? $players[$id] : NULL);
	}
	echo '</div>';
}
echo '</div>';

?>
