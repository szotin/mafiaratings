<?php

require_once 'screens.php';

// The fallback between.js puts up when every other screen had nothing to say - before the first
// game of a round that has no seating, after the last game of a tournament, and so on. It says
// where we are, so that the source can be placed in OBS and can be seen to be alive rather than
// leaving a blank rectangle that looks exactly like something broken.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return; // the address names no tournament, which is a different sort of nothing
}

echo '<div class="screen-body title">';
echo '<div class="title-tournament">' . htmlspecialchars($c->tournament_name) . '</div>';
if ($c->event_name != '')
{
	echo '<div class="title-round">' . htmlspecialchars($c->event_name) . '</div>';
}
echo '<div class="title-table">' . get_label('Table [0]', $c->table_num) . '</div>';
echo '</div>';

?>
