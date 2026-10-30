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

// The referee, where there is one to name: the game under way is run by them, and that is the
// game this frame is showing. A table between games has nobody at it yet.
$judge = '';
if (!is_null($c->playing) && isset($c->playing->log->moderator->id))
{
	$moderator = obs_players(array((int)$c->playing->log->moderator->id), $c->event_id, $c->tournament_id, $c->club_id);
	$moderator = reset($moderator);
	if ($moderator !== false)
	{
		$judge = '<span class="dv"></span>' . obs_head_judge($moderator->name);
	}
}

obs_frame_start('f3');
obs_head($c->tournament_name, get_label('Next game'),
	obs_head_sub($c->event_name, $c->table_num, $c->next_game_num),
	'<span class="mu">' . get_label('Table') . '</span>' . $c->table_num . $judge);

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
