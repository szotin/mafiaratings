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
	// Who was still at the table at the end, and whether the game was given away by a team kick
	// out. The same reckoning the game itself is saved by, see Game::update() - a player with a
	// death of any kind has left the table, and a team kick out ends the game on the spot.
	$g->maf_alive = 0;
	$g->civ_alive = 0;
	$g->team_kick_out = false;
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

		if (isset($p->death))
		{
			$death_type = is_string($p->death) ? $p->death : (isset($p->death->type) ? $p->death->type : '');
			if ($death_type == DEATH_TYPE_TEAM_KICK_OUT)
			{
				$g->team_kick_out = true;
			}
		}
		else if ($seat->role == ROLE_MAFIA || $seat->role == ROLE_DON)
		{
			++$g->maf_alive;
		}
		else
		{
			++$g->civ_alive;
		}
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

// The title says who won, and the line under it how close it was: a win for the mafia is said
// with the score it came at - the 1 on 1, 2 on 2 or 3 on 3 everyone at the table will be
// talking about anyway. A game given away by a team kick out is named for that instead,
// whichever side it handed the win to: it stopped mid-play, so the mafia never drew level and
// the numbers left on the table would say a score that was never played.
$extra = '';
switch ($g->result)
{
case GAME_RESULT_MAFIA:
	$title = get_label('Mafia win');
	$tint = 'mw';
	if ($g->maf_alive > 0 && !$g->team_kick_out)
	{
		$extra = get_label('Mafia [0] on [1]', $g->maf_alive, $g->civ_alive);
	}
	break;
case GAME_RESULT_TOWN:
	$title = get_label('Town win');
	$tint = 'tw';
	break;
default:
	$title = get_label('Tie');
	$tint = '';
	break;
}
if ($g->team_kick_out)
{
	$extra = get_label('Mod team kills');
}

$moderator = isset($players[$g->moderator_id]) ? $players[$g->moderator_id]->name : '';

// What each seat has to say for itself: the role it played, and how it left the game.
//
// A night belongs to the round it is written down in, a day to the round after it - the game
// counts its rounds from zero and the table counts its nights and its days from one, which is
// also what the overlays showed while the game was being played.
function obs_results_elim($p)
{
	if (is_null($p) || !isset($p->death))
	{
		return NULL;
	}
	$death = $p->death;
	$type = is_string($death) ? $death : (isset($death->type) ? $death->type : '');
	$round = (is_object($death) && isset($death->round)) ? (int)$death->round : 0;

	$elim = new stdClass();
	if ($type == DEATH_TYPE_NIGHT)
	{
		$elim->night = true;
		$elim->number = max($round, 1);
		return $elim;
	}
	if ($type == DEATH_TYPE_DAY)
	{
		$elim->night = false;
		$elim->number = $round + 1;
		return $elim;
	}
	return NULL; // sent away by the referee, or gave up: the cards say nothing about it
}

// The role as the cards and the chips spell it.
function obs_results_role($role)
{
	switch ($role)
	{
	case ROLE_DON:
		return 'don';
	case ROLE_MAFIA:
		return 'maf';
	case ROLE_SHERIFF:
		return 'sheriff';
	}
	return '';
}

// A team to a row. The winners go on top, which is the one thing that says who won besides the
// title and the colour of the page.
$black = array();
$red = array();
$roles = array();
foreach ($g->seats as $p)
{
	$roles[(int)$p->number] = obs_results_role($p->role);
	if ($p->role == ROLE_MAFIA || $p->role == ROLE_DON)
	{
		$black[] = $p;
	}
	else
	{
		$red[] = $p;
	}
}

// The three things the info bar carried through the game, which are the three things the table
// argues about afterwards: the best move of the player shot in the first night, and the checks
// of the sheriff and of the don, each in the order they were made.
$best_by = 0;
$best = array();
$sheriff = array();
$don = array();
foreach ($g->log as $i => $p)
{
	if (isset($p->legacy) && is_array($p->legacy) && !empty($p->legacy))
	{
		$best_by = $i + 1;
		$best = $p->legacy;
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

$sheriff_seat = 0;
$don_seat = 0;
foreach ($roles as $number => $role)
{
	if ($role == 'sheriff')
	{
		$sheriff_seat = $number;
	}
	else if ($role == 'don')
	{
		$don_seat = $number;
	}
}

// One chip: the number of a seat on the ground of its real role, so that a pick or a check can
// be read as right or wrong without going back to the cards for it. A don who found the
// sheriff gets gold - that is the hit the whole table remembers.
function obs_results_chip($number, $roles, $gold_sheriff = false)
{
	$role = isset($roles[$number]) ? $roles[$number] : '';
	$black = ($role == 'maf' || $role == 'don');
	$class = $black ? 'b' : 'r';
	if ($gold_sheriff && $role == 'sheriff')
	{
		$class = 'g';
	}
	echo '<div class="chip ' . $class . '">' . $number . '</div>';
}

// One column of the panel: what it is, who it belongs to, and the seats they named.
function obs_results_moves_column($icon, $class, $label, $seat, $players_by_seat, $roles, $numbers, $gold_sheriff, $perfect)
{
	echo '<div class="mv">';
	echo '<div class="tx">';
	echo '<div class="lb ' . $class . '">' . obs_icon($icon) . $label . '</div>';
	echo '<div class="who">';
	if ($seat > 0)
	{
		echo '<span class="seat">' . $seat . '</span><b>' .
			(isset($players_by_seat[$seat]) ? htmlspecialchars($players_by_seat[$seat]) : '') . '</b>';
	}
	echo '</div>';
	echo '</div>';
	echo '<div class="chips"><div class="cr">';
	foreach ($numbers as $number)
	{
		obs_results_chip((int)$number, $roles, $gold_sheriff);
	}
	echo '</div>';
	if ($perfect)
	{
		echo '<div class="perfect">' . get_label('Perfect best guess') . '</div>';
	}
	echo '</div>';
	echo '</div>';
}

// Who sits where, by seat, for the panel - which names people by their seat rather than by
// their id.
$by_seat = array();
foreach ($g->seats as $p)
{
	$by_seat[(int)$p->number] = isset($players[$p->user_id]) ? $players[$p->user_id]->name : $p->nick_name;
}

// Three of three: the whole mafia named by a player shot in the first night. Gold letters and
// nothing else - a ring around the chips was tried and taken off again.
$perfect = !empty($best);
foreach ($best as $number)
{
	$role = isset($roles[(int)$number]) ? $roles[(int)$number] : '';
	if ($role != 'maf' && $role != 'don')
	{
		$perfect = false;
	}
}

// Who sat in this seat. Usually somebody the site knows, and then the card carries their
// photograph, their club and their flag. A seat the referee never picked a player for is
// written down with a name and nothing else - the game was played by somebody, and a card
// with a bare number on it says less than their name does.
function obs_results_player($p, $players)
{
	if (isset($players[$p->user_id]))
	{
		return $players[$p->user_id];
	}
	if ($p->nick_name == '')
	{
		return NULL;
	}

	$stub = new stdClass();
	$stub->name = $p->nick_name;
	$stub->club = '';
	$stub->photo = '';
	$stub->flag = '';
	$stub->country = '';
	return $stub;
}

// A row of cards, and the row that carries the panel beside the three black ones.
function obs_results_cards($seats, $players, $log, $roles)
{
	foreach ($seats as $p)
	{
		$o = new stdClass();
		$o->role = isset($roles[(int)$p->number]) ? $roles[(int)$p->number] : '';
		$o->elim = obs_results_elim(isset($log[$p->number - 1]) ? $log[$p->number - 1] : NULL);
		obs_card($p->number, obs_results_player($p, $players), $o);
	}
}

obs_frame_start('f1 ' . $tint);
obs_head($c->tournament_name, $title, obs_head_sub($g->event_name, $c->table_num, $g->game_num, $extra),
	$moderator == '' ? '' : obs_head_judge($moderator));

echo '<div class="f1rows">';
for ($row = 0; $row < 2; ++$row)
{
	// The winners first, and with them the row of seven; the three and the panel come after.
	$town_row = (($g->result == GAME_RESULT_TOWN) == ($row == 0));
	if ($town_row)
	{
		echo '<div class="r7">';
		obs_results_cards($red, $players, $g->log, $roles);
		echo '</div>';
		continue;
	}

	echo '<div class="r3"><div class="three">';
	obs_results_cards($black, $players, $g->log, $roles);
	echo '</div>';
	echo '<div class="moves"><div class="mvl">';
	obs_results_moves_column('target', 't', get_label('Best guess'), $best_by, $by_seat, $roles, $best, false, $perfect);
	obs_results_moves_column('star', 's', get_label('Sheriff checks'), $sheriff_seat, $by_seat, $roles, array_keys($sheriff), false, false);
	obs_results_moves_column('hat', 'd', get_label('Don checks'), $don_seat, $by_seat, $roles, array_keys($don), true, false);
	echo '</div></div>';
	echo '</div>';
}
echo '</div>';
obs_frame_end();

?>
