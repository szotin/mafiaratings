<?php

require_once 'screens.php';
require_once '../../include/scoring.php'; // SCORING_FLAG_BEST_PLAYER
require_once '../../include/game.php';    // DEATH_TYPE_*

// How the game before this one went at this table. Not shown when the round has not had a game
// yet - the first game of a round has nothing behind it. Note that this looks only inside the
// round: the last game of the round before is somebody else's news by then.
//
// The cards carry the same marks they carried while the game was being played, and the strip
// under them the same three things the info bar carried. Both are read out of the log the game
// was written down with, which is the very thing the overlays were showing at the time.

obs_screen_start();

$c = obs_context();
if (is_null($c))
{
	return;
}

// The last game played at this table in the round we are in.
$query = new DbQuery(
	'SELECT id, result, game_num, moderator_id, json FROM games WHERE event_id = ? AND table_num = ?'.
	' ORDER BY game_num DESC LIMIT 1', $c->event_id, $c->table_num);
$row = $query->next();
if (!$row)
{
	return; // first game of the round
}
list ($game_id, $result, $game_num, $moderator_id, $json) = $row;

// The log of the game, by seat. Everything the overlays showed while it was played is in here.
$log = is_null($json) ? NULL : json_decode($json);
$log = (!is_null($log) && isset($log->players) && is_array($log->players)) ? $log->players : array();

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

// The role as the stylesheet spells it - the ground a card sits on and the ground a number in
// the strip sits on are the same three.
function obs_results_role($role)
{
	switch ((int)$role)
	{
	case ROLE_DON:
		return 'don';
	case ROLE_MAFIA:
		return 'maf';
	case ROLE_SHERIFF:
		return 'sheriff';
	}
	return 'town';
}

// What the overlay had in the corner of this player's card when the game ended: how many
// warnings they were carrying, or the way they died. The rule is showPlayerMark() in obs.js, kept
// the same here so that a game looks the way it looked while it was played - one mark, and a
// death that has a picture of its own takes the place of the warnings.
function obs_results_mark($p)
{
	if (is_null($p))
	{
		return NULL;
	}

	$warnings = 0;
	if (isset($p->warnings))
	{
		$warnings = is_array($p->warnings) ? count($p->warnings) : (int)$p->warnings;
	}
	$death = isset($p->death) ? $p->death : NULL;
	$type = (is_null($death) || !isset($death->type)) ? '' : $death->type;

	$mark = new stdClass();
	$mark->text = '';
	if ($warnings > 0 && ($type == '' || $type == DEATH_TYPE_WARNINGS))
	{
		$mark->class = 'warnings-' . min($warnings, 4);
		$mark->text = str_repeat('!', $warnings);
	}
	else if ($type == DEATH_TYPE_KICK_OUT)
	{
		$mark->class = 'death-kickOut';
		$mark->text = 'X';
	}
	else if ($type == DEATH_TYPE_DAY)
	{
		$mark->class = 'death-voting';
	}
	else if ($type == DEATH_TYPE_NIGHT)
	{
		// Which night it was is painted on the picture itself, and a night belongs to the round
		// before the one the death is written down in - the same reckoning current_game.php does.
		$mark->class = 'death-shooting round-' . max((int)$death->round - 1, 0);
	}
	else
	{
		return NULL; // gave up, or left with their team - the overlay showed nothing for those
	}
	return $mark;
}

function obs_results_row($seats, $players, $log, $row_class)
{
	if (empty($seats))
	{
		return;
	}
	obs_card_row(count($seats), $row_class);
	foreach ($seats as $p)
	{
		$role = obs_results_role($p->role);
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
		$seat = isset($log[$p->number - 1]) ? $log[$p->number - 1] : NULL;
		obs_card($p->number, $player, 'role-' . $role . ($p->best ? ' best' : ''),
			$role == 'town' ? '' : ' ', obs_results_mark($seat));
	}
	echo '</div>';
}

// One part of the strip: the numbers of the players it is about, each on the ground of its role,
// the way the info bar showed them. Nothing at all when there is nobody to name.
function obs_results_section($class, $numbers, $roles)
{
	if (empty($numbers))
	{
		return;
	}
	echo '<div class="info-section ' . $class . '"><div class="info-label"></div><div class="info-items">';
	foreach ($numbers as $number)
	{
		$role = isset($roles[$number]) ? $roles[$number] : 'town';
		echo '<div class="info-item role-' . $role . '">' . (int)$number . '</div>';
	}
	echo '</div></div>';
}

// The three things the info bar carried through the game: the best move of the player shot in
// the first night, and the two sets of checks, each in the order they were made.
$roles = array();
foreach ($seats as $p)
{
	$roles[(int)$p->number] = obs_results_role($p->role);
}

$legacy = array();
$sheriff = array();
$don = array();
foreach ($log as $i => $p)
{
	if (isset($p->legacy) && is_array($p->legacy))
	{
		$legacy = $p->legacy;
	}
	if (isset($p->sheriff))
	{
		$sheriff[$i + 1] = (int)$p->sheriff;
	}
	if (isset($p->don))
	{
		$don[$i + 1] = (int)$p->don;
	}
}
asort($sheriff);
asort($don);

$info = !empty($legacy) || !empty($sheriff) || !empty($don);

echo '<div class="screen-body results' . ($info ? ' with-info' : '') . '">';
obs_results_row($black, $players, $log, 'black-row');
obs_results_row($red, $players, $log, 'red-row');
if ($info)
{
	echo '<div class="info">';
	obs_results_section('legacy', $legacy, $roles);
	obs_results_section('sheriff-check', array_keys($sheriff), $roles);
	obs_results_section('don-check', array_keys($don), $roles);
	echo '</div>';
}
echo '</div>';

?>
