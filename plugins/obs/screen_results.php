<?php

require_once 'screens.php';
require_once '../../include/scoring.php'; // SCORING_FLAG_BEST_PLAYER

// How the game before this one went at this table. Not shown when the round has not had a game
// yet - the first game of a round has nothing behind it. Note that this looks only inside the
// round: the last game of the round before is somebody else's news by then.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

// The last game played at this table in the round we are in.
$query = new DbQuery(
	'SELECT id, result, game_num, moderator_id FROM games WHERE event_id = ? AND table_num = ?'.
	' ORDER BY game_num DESC LIMIT 1', $c->event_id, $c->table_num);
$row = $query->next();
if (!$row)
{
	return; // first game of the round
}
list ($game_id, $result, $game_num, $moderator_id) = $row;

$query = new DbQuery(
	'SELECT user_id, nick_name, number, role, won, flags FROM players WHERE game_id = ? ORDER BY number', $game_id);
$seats = array();
$ids = array();
while ($row = $query->next())
{
	$p = new stdClass();
	list ($p->user_id, $p->nick_name, $p->number, $p->role, $p->won, $p->flags) = $row;
	$p->user_id = (int)$p->user_id;
	$p->role = (int)$p->role;
	$p->best = ((int)$p->flags & SCORING_FLAG_BEST_PLAYER) != 0;
	$seats[] = $p;
	$ids[] = $p->user_id;
}
if (empty($seats))
{
	return;
}

$ids[] = (int)$moderator_id;
$players = obs_players($ids, $c->event_id, $c->tournament_id, $c->club_id);

switch ((int)$result)
{
case GAME_RESULT_MAFIA:
	$winner = get_label('Mafia win');
	break;
case GAME_RESULT_TOWN:
	$winner = get_label('Town win');
	break;
default:
	$winner = get_label('Tie');
	break;
}

$moderator = isset($players[(int)$moderator_id]) ? $players[(int)$moderator_id]->name : '';
obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . $winner . '</div>' .
		'<div class="head-sub">' . htmlspecialchars($c->event_name) . ' &middot; ' . get_label('Table [0], Game [1]', $c->table_num, $game_num) . '</div>',
	$moderator == '' ? '' : get_label('Referee') . ': ' . htmlspecialchars($moderator));

// The black team on its own row above the town, the way the result of a game is read.
$black = array();
$red = array();
foreach ($seats as $p)
{
	if ($p->role == ROLE_MAFIA || $p->role == ROLE_DON)
	{
		$black[] = $p;
	}
	else
	{
		$red[] = $p;
	}
}

function obs_results_row($seats, $players, $row_class)
{
	if (empty($seats))
	{
		return;
	}
	echo '<div class="card-row ' . $row_class . '">';
	foreach ($seats as $p)
	{
		switch ($p->role)
		{
		case ROLE_DON:
			$role = 'don';
			break;
		case ROLE_MAFIA:
			$role = 'maf';
			break;
		case ROLE_SHERIFF:
			$role = 'sheriff';
			break;
		default:
			$role = 'town';
			break;
		}

		$player = isset($players[$p->user_id]) ? $players[$p->user_id] : NULL;
		if (is_null($player) && $p->nick_name != '')
		{
			// A player the users table has nothing for still has the name the game recorded.
			$player = new stdClass();
			$player->name = $p->nick_name;
			$player->photo = '';
			$player->club = '';
			$player->country = '';
		}
		// The corner carries the glyph of the role; the best player of the game gets the gold edge.
		obs_card($p->number, $player, 'role-' . $role . ($p->best ? ' best' : ''), $role == 'town' ? '' : ' ');
	}
	echo '</div>';
}

echo '<div class="screen-body results">';
obs_results_row($black, $players, 'black-row');
obs_results_row($red, $players, 'red-row');
echo '</div>';

?>
