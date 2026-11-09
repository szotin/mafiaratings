<?php

require_once 'screens.php';

// Frame 3 of the round: who sits down next at this table. Shown only when there is a seating to
// read it from, which also settles the rest of the rules by itself: obs_context() walks the
// rounds in the order they are played and stops at the first one with a game left at this
// table, so after the last game of a round this is already the first game of the next one - and
// when the tournament has no game left anywhere, there is nothing to stop at and nothing is
// shown.
//
// The cards carry no role: before a game nobody knows them, so all ten stand on the same
// ground with an edge that is barely there.

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

obs_frame_start('f3');
obs_head($c, get_label('Next game'), $c->event_name, '',
	obs_head_game($c->next_game_num, obs_round_games($c->event_id, $c->table_num)));
obs_foot($c, obs_judge($c));

echo '<div class="lineup">';
$seat = 1;
foreach ($seating as $id)
{
	$o = new stdClass();
	$o->neutral = true;
	obs_card($seat++, isset($players[$id]) ? $players[$id] : NULL, $o);
}
echo '</div>';
obs_frame_end();

?>
