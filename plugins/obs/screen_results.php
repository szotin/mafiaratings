<?php

require_once 'screens.php'; // which pulls in game.php, for the DEATH_TYPE_* the marks go by

// How the game before this one went at this table. Not shown when the round has not had a game
// yet - the first game of a round has nothing behind it. Note that this looks only inside the
// round: the last game of the round before is somebody else's news by then.
//
// The cards carry the same marks they carried while the game was being played, and the strip
// under them the same three things the info bar carried. Both are read out of the log the game
// was written down with, which is the very thing the overlays were showing at the time.

obs_screen_start();

// The game a seat was in, as the rest of this page wants it: who sat where, how it ended, and
// the log it was played by. A game is written down with the very log its moderator played it
// by, so a game that is over and one that is still on the last screen read exactly the same
// way and there is one way of reading them.
function obs_results_game($log, $event_id, $event_name, $game_num)
{
	$g = new stdClass();
	$g->event_id = $event_id;
	$g->event_name = $event_name;
	$g->game_num = $game_num;
	$g->moderator_id = isset($log->moderator->id) ? (int)$log->moderator->id : 0;

	$g->result = GAME_RESULT_TIE;
	if (isset($log->winner))
	{
		if ($log->winner == 'maf')
		{
			$g->result = GAME_RESULT_MAFIA;
		}
		else if ($log->winner == 'civ')
		{
			$g->result = GAME_RESULT_TOWN;
		}
	}

	$g->log = isset($log->players) && is_array($log->players) ? $log->players : array();
	$g->seats = array();
	foreach ($g->log as $i => $p)
	{
		$seat = new stdClass();
		$seat->user_id = isset($p->id) ? (int)$p->id : 0;
		$seat->nick_name = isset($p->name) ? $p->name : '';
		$seat->number = $i + 1;
		$seat->role = ROLE_CIVILIAN;
		if (isset($p->role))
		{
			switch ($p->role)
			{
			case 'don':
				$seat->role = ROLE_DON;
				break;
			case 'maf':
				$seat->role = ROLE_MAFIA;
				break;
			case 'sheriff':
				$seat->role = ROLE_SHERIFF;
				break;
			}
		}
		// The bonus points the referee hands out at the end are in the log too, so the best
		// player gets their gold edge as soon as they are named.
		$bonus = isset($p->bonus) ? (is_array($p->bonus) ? $p->bonus : array($p->bonus)) : array();
		$seat->best = in_array('bestPlayer', $bonus);
		$g->seats[] = $seat;
	}
	return $g;
}

// The last game recorded at this table in the round the results belong to - which is the round
// the table is in, except when it has moved on to one that has not started, see obs_context().
function obs_results_recorded($c)
{
	// The last game is the one played last, not the one with the highest number: a game that has
	// been replayed is the news of the table again, whatever its number in the round.
	$query = new DbQuery(
		'SELECT result, game_num, moderator_id, json FROM games WHERE event_id = ? AND table_num = ?'.
		' ORDER BY end_time DESC, id DESC LIMIT 1', $c->results_event_id, $c->table_num);
	$row = $query->next();
	if (!$row)
	{
		return NULL; // nothing played in this round yet
	}
	list ($result, $game_num, $moderator_id, $json) = $row;

	$log = is_null($json) ? NULL : json_decode($json);
	if (is_null($log))
	{
		return NULL; // too old to have a log, or a broken one - there is nothing to draw
	}

	$g = obs_results_game($log, $c->results_event_id, $c->results_event_name, $game_num);
	// The row is what the site itself goes by, so it has the last word over the log.
	$g->result = (int)$result;
	$g->moderator_id = (int)$moderator_id;
	return $g;
}

$c = obs_context();
if (is_null($c))
{
	return;
}

// A game the table has played out is the news the moment it ends, long before the referee is
// done entering its bonus points and it reaches the games table.
$g = is_null($c->done)
	? obs_results_recorded($c)
	: obs_results_game($c->done->log, $c->done->event_id, $c->done->event_name, $c->done->game_num);
if (is_null($g) || empty($g->seats))
{
	return; // first game of the round, and nothing played out on the table either
}

$ids = array((int)$g->moderator_id);
foreach ($g->seats as $seat)
{
	$ids[] = $seat->user_id;
}
$players = obs_players($ids, $g->event_id, $c->tournament_id, $c->club_id);

switch ($g->result)
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

$moderator = isset($players[$g->moderator_id]) ? $players[$g->moderator_id]->name : '';
obs_screen_head(
	htmlspecialchars($c->tournament_name),
	'<div class="head-title">' . $winner . '</div>' .
		'<div class="head-sub">' . htmlspecialchars($g->event_name) . ' &middot; ' . get_label('Table [0], Game [1]', $c->table_num, $g->game_num) . '</div>',
	$moderator == '' ? '' : get_label('Referee') . ': ' . htmlspecialchars($moderator));

// The black team on its own row above the town, the way the result of a game is read.
$black = array();
$red = array();
foreach ($g->seats as $p)
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
	$mark->class = '';
	$mark->text = '';
	// The technical fouls are counted apart from the warnings and cost a player the game at two,
	// so they are shown apart from them too - and they stay up whichever way the player left.
	$mark->fouls = 0;
	if (isset($p->techFouls))
	{
		$mark->fouls = is_array($p->techFouls) ? count($p->techFouls) : (int)$p->techFouls;
	}

	if ($warnings > 0 && ($type == '' || $type == DEATH_TYPE_WARNINGS))
	{
		$mark->class = 'warnings-' . min($warnings, 4);
		$mark->text = str_repeat('!', $warnings);
	}
	else if ($type == DEATH_TYPE_KICK_OUT || $type == DEATH_TYPE_TECH_FOULS)
	{
		$mark->class = 'death-kickOut';
		$mark->text = 'X';
	}
	else if ($type == DEATH_TYPE_TEAM_KICK_OUT)
	{
		// Removed, and the other team won by it. Two crosses rather than one.
		$mark->class = 'death-kickOut';
		$mark->text = 'XX';
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

	return ($mark->class == '' && $mark->fouls <= 0) ? NULL : $mark;
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
foreach ($g->seats as $p)
{
	$roles[(int)$p->number] = obs_results_role($p->role);
}

$legacy = array();
$sheriff = array();
$don = array();
foreach ($g->log as $i => $p)
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
obs_results_row($black, $players, $g->log, 'black-row');
obs_results_row($red, $players, $g->log, 'red-row');
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
