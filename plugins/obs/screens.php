<?php

require_once '../../include/session.php';
require_once '../../include/stream.php';
require_once '../../include/picture.php';
require_once '../../include/game.php';    // GAMETIME_END
require_once '../../include/country.php'; // country_flag_url()
require_once 'obs.php'; // obs_file() - the same cache-busting the overlays use

// Shared by the screens that between.php shows one after another. Each screen is an ordinary
// page that reads what it needs out of the database and prints it - there is no api in between,
// because none of them changes while it is on the screen.
//
// A screen with nothing to say prints nothing at all. between.js takes an empty answer as "skip
// this one", which is how the rules about when a screen is shown are expressed: the screen
// itself decides, and the rotation just leaves out whatever came back empty.

define('OBS_SCREEN_DELAY', 10); // seconds a screen stays up before the next one

// Starts a screen the way obs.php starts an overlay: the language comes from the URL so that the
// browser source shows what the club wants rather than what some session once decided.
function obs_screen_start()
{
	// The language the table is set to show, which is where everything else about the table
	// comes from as well - see stream_request_lang(). Only a page that names no table falls
	// back to the locale in the address.
	initiate_session(stream_request_lang_code());
}

// What is on the table right now, as the moderator's screen has it. ->playing is the game under
// way; ->done is one that has been played out and is on the last screen, where the referee
// enters the bonus points. Either may be NULL and usually one of them is.
//
// A game reaches the games table only once the moderator is finished with it, which can be a
// good while after the table is done playing it, so this is the only place to learn what is
// going on. A game whose screen was merely opened and never started is not on the table at all
// and is left out of both.
function obs_table_state($event_id, $table_num)
{
	$state = new stdClass();
	$state->playing = NULL;
	$state->done = NULL;

	$query = new DbQuery('SELECT game_num, game FROM current_games WHERE event_id = ? AND table_num = ? ORDER BY game_num', $event_id, $table_num);
	while ($row = $query->next())
	{
		list ($num, $game) = $row;
		$log = json_decode($game);
		if (is_null($log) || !isset($log->time))
		{
			continue;
		}

		$g = new stdClass();
		$g->game_num = (int)$num;
		$g->log = $log;
		if ($log->time->time == GAMETIME_END)
		{
			$state->done = $g;
		}
		else
		{
			$state->playing = $g;
		}
	}
	return $state;
}

// The number of the game this table is coming to. The game under way is it - it is not written
// down until it is over, and the seating being shown is still its own. Once it is played out,
// though, it is behind us, and the next game is the lowest number nobody has played - which is
// not always the one after it: games are sometimes played out of order, when one has to be
// replayed or when somebody is missing.
function obs_next_game_num($event_id, $table_num, $state)
{
	if (!is_null($state->playing))
	{
		return $state->playing->game_num;
	}

	$played = array();
	$query = new DbQuery('SELECT game_num FROM games WHERE event_id = ? AND table_num = ?', $event_id, $table_num);
	while ($row = $query->next())
	{
		list ($num) = $row;
		$played[(int)$num] = true;
	}
	if (!is_null($state->done))
	{
		$played[$state->done->game_num] = true; // played out, only its bonus points are still coming
	}

	$game_num = 1;
	while (isset($played[$game_num]))
	{
		++$game_num;
	}
	return $game_num;
}

// How many games this round holds at this table, according to its seating. Zero when the round
// has no seating - then there is no telling how long it runs and nothing to show as upcoming.
function obs_seated_games($misc, $table_num)
{
	$misc = is_null($misc) ? NULL : json_decode($misc);
	if (is_null($misc) || !isset($misc->seating) || !isset($misc->seating->rounds))
	{
		return 0;
	}

	$games = 0;
	foreach ($misc->seating->rounds as $round)
	{
		if ($table_num <= count($round))
		{
			++$games;
		}
	}
	return $games;
}

// Works out what is going on at this table: which round of the tournament is being played and
// which game comes next. A tournament is walked in the order its rounds are played, and the
// round we are in is the first one that still has a game left at this table - so when a round
// runs out the answer moves on to the next one by itself, which is what makes the upcoming
// screen show the first game of the next round after the last game of this one.
//
// An event of its own - a club night rather than a round of something - is a table standing on
// its own too. There are no rounds to walk and no tournament above it, so the event is the
// whole of the context: the games played at this table are its games, the table is its table,
// and the standings are its own. ->is_tournament tells the screens which of the two they are
// looking at, since a few of them have something to say in one case and not the other.
function obs_event_context($event_id, $table_num)
{
	$c = new stdClass();
	$c->is_tournament = false;
	$c->table_num = $table_num;
	$c->tournament_id = 0;
	$c->tournament_flags = 0;
	$c->normalizer_id = 0;
	$c->normalizer_version = 0;

	list ($name, $c->club_id, $c->scoring_id, $c->scoring_version, $c->scoring_options) = Db::record(get_label('event'),
		'SELECT name, club_id, scoring_id, scoring_version, scoring_options FROM events WHERE id = ?', $event_id);

	// The event is what the screens head themselves with, and there is no round under it to
	// name a second time.
	$c->tournament_name = $name;
	$c->event_id = $event_id;
	$c->event_name = '';
	$c->round_num = 0;

	$state = obs_table_state($event_id, $table_num);
	$c->next_game_num = obs_next_game_num($event_id, $table_num, $state);
	$c->done = $state->done;
	$c->playing = $state->playing; // the game under way, whose referee the frames name
	if (!is_null($c->done))
	{
		$c->done->event_id = $event_id;
		$c->done->event_name = '';
	}

	$c->results_event_id = $event_id;
	$c->results_event_name = '';
	return $c;
}

// Returns NULL when the request names no table we can make sense of.
function obs_context()
{
	$table_num = isset($_REQUEST['table_num']) ? max((int)$_REQUEST['table_num'], 1) : 1;
	$tournament_id = isset($_REQUEST['tournament_id']) ? (int)$_REQUEST['tournament_id'] : 0;
	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;

	if ($event_id > 0 && $tournament_id <= 0)
	{
		list ($tournament_id) = Db::record(get_label('event'), 'SELECT tournament_id FROM events WHERE id = ?', $event_id);
		$tournament_id = (int)$tournament_id;
		if ($tournament_id <= 0)
		{
			return obs_event_context($event_id, $table_num);
		}
	}
	if ($tournament_id <= 0)
	{
		return NULL;
	}

	$c = new stdClass();
	$c->is_tournament = true;
	$c->table_num = $table_num;
	$c->tournament_id = $tournament_id;
	list ($c->tournament_name, $c->tournament_flags, $c->club_id, $c->scoring_id, $c->scoring_version,
		$c->normalizer_id, $c->normalizer_version, $c->scoring_options) = Db::record(get_label('tournament'),
		'SELECT name, flags, club_id, scoring_id, scoring_version, normalizer_id, normalizer_version, scoring_options'.
		' FROM tournaments WHERE id = ?', $tournament_id);
	$c->tournament_flags = (int)$c->tournament_flags;

	// The rounds in the order they are played. events.round is a stage number rather than a
	// sequence, so the clock is what puts them in order.
	$c->rounds = array();
	$query = new DbQuery('SELECT id, name, round, misc FROM events WHERE tournament_id = ? ORDER BY start_time, id', $tournament_id);
	while ($row = $query->next())
	{
		$r = new stdClass();
		list ($r->id, $r->name, $r->round_num, $misc) = $row;
		$r->id = (int)$r->id;
		$r->games = obs_seated_games($misc, $table_num);
		$c->rounds[] = $r;
	}

	$c->event_id = 0;
	$c->event_name = '';
	$c->next_game_num = 0;
	$c->done = NULL;    // the game played out at this table but not yet written down, if there is one
	$c->playing = NULL; // the game under way, whose referee the frames name
	$settled = -1;      // how far down the rounds we got
	foreach ($c->rounds as $index => $r)
	{
		$state = obs_table_state($r->id, $table_num);
		$next = obs_next_game_num($r->id, $table_num, $state);
		if (!is_null($state->playing))
		{
			$c->playing = $state->playing;
		}

		// A game that has only just been played out is the news of this table whichever round we
		// end up in, so it is picked up here and carried along - the round it was played in can
		// be the one we are about to leave behind, when it was the last game of that round.
		if (!is_null($state->done))
		{
			$state->done->event_id = $r->id;
			$state->done->event_name = $r->name;
			$c->done = $state->done;
		}

		if ($r->games > 0 && $next > $r->games)
		{
			continue; // this round is played out at this table
		}

		$c->event_id = $r->id;
		$c->event_name = $r->name;
		$c->round_num = (int)$r->round_num;
		$c->next_game_num = ($r->games > 0) ? $next : 0; // no seating, so nothing to call upcoming
		$settled = $index;
		break;
	}

	// Every round played out: the tournament is over at this table. Stay on the last round so
	// that its results still have somewhere to come from.
	if ($c->event_id <= 0 && count($c->rounds) > 0)
	{
		$settled = count($c->rounds) - 1;
		$last = $c->rounds[$settled];
		$c->event_id = $last->id;
		$c->event_name = $last->name;
		$c->round_num = (int)$last->round_num;
	}

	// Which round the game behind us comes from. Normally the one the table is in - a round that
	// is really starting has a game coming up, and then the game before it belongs to the round
	// before and is left there. But a round we only moved into because the one before was played
	// out is not yet where the table is: with nothing to play and nothing played in it, the last
	// game of the round before is still the news of this table, and stays up.
	$c->results_event_id = $c->event_id;
	$c->results_event_name = $c->event_name;
	if ($c->next_game_num <= 0 && $settled >= 0)
	{
		$played = array();
		$query = new DbQuery('SELECT DISTINCT event_id FROM games WHERE tournament_id = ? AND table_num = ?', $tournament_id, $table_num);
		while ($row = $query->next())
		{
			list ($id) = $row;
			$played[(int)$id] = true;
		}

		for ($i = $settled; $i >= 0 && !isset($played[$c->results_event_id]); --$i)
		{
			$c->results_event_id = $c->rounds[$i]->id;
			$c->results_event_name = $c->rounds[$i]->name;
		}
	}

	return $c->event_id > 0 ? $c : NULL;
}

// The seating of one game, as user ids by seat. Empty when that game has no seating.
function obs_seating($event_id, $table_num, $game_num)
{
	list ($misc) = Db::record(get_label('event'), 'SELECT misc FROM events WHERE id = ?', $event_id);
	$misc = is_null($misc) ? NULL : json_decode($misc);
	if (is_null($misc) || !isset($misc->seating->rounds) ||
		$game_num < 1 || $game_num > count($misc->seating->rounds) ||
		$table_num > count($misc->seating->rounds[$game_num - 1]))
	{
		return array();
	}

	$seats = $misc->seating->rounds[$game_num - 1][$table_num - 1];
	$mapping = isset($misc->seating->mapping) ? $misc->seating->mapping : NULL;
	$players = array();
	foreach ($seats as $seat)
	{
		$id = is_null($mapping) ? $seat : (isset($mapping[$seat]) ? $mapping[$seat] : 0);
		if (is_object($id))
		{
			$id = isset($id->id) ? $id->id : 0;
		}
		$players[] = (int)$id;
	}
	return $players;
}

// Everything a card shows about a player, for a list of user ids at once - one query instead of
// one per seat. Returns them keyed by user id; an id nobody answers for is simply missing.
function obs_players($user_ids, $event_id, $tournament_id, $club_id)
{
	global $_lang;

	$ids = array();
	foreach ($user_ids as $id)
	{
		if ($id > 0)
		{
			$ids[(int)$id] = true;
		}
	}
	if (empty($ids))
	{
		return array();
	}

	$pic =
		new Picture(USER_EVENT_PICTURE,
		new Picture(USER_TOURNAMENT_PICTURE,
		new Picture(USER_CLUB_PICTURE,
		new Picture(USER_PICTURE))));

	$players = array();
	$query = new DbQuery(
		'SELECT u.id, nu.name, u.flags, eu.flags, tu.flags, cu.flags, uc.id, uc.name, uc.flags, o.code, nn.name'.
		' FROM users u'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' LEFT OUTER JOIN clubs uc ON uc.id = u.club_id'.
		' JOIN cities i ON i.id = u.city_id'.
		' JOIN countries o ON o.id = i.country_id'.
		' JOIN names nn ON nn.id = o.name_id AND (nn.langs & '.$_lang.') <> 0'.
		' LEFT OUTER JOIN event_regs eu ON eu.user_id = u.id AND eu.event_id = ?'.
		' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = ?'.
		' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = ?'.
		' WHERE u.id IN (' . implode(',', array_keys($ids)) . ')',
		$event_id, $tournament_id, $club_id);
	while ($row = $query->next())
	{
		$p = new stdClass();
		list ($p->id, $p->name, $user_flags, $event_flags, $tournament_flags, $club_flags,
			$p->club_id, $p->club_name, $club_pic_flags, $country_code, $p->country) = $row;
		$p->id = (int)$p->id;

		// The flag of the country stands in the corner of the card instead of its name. A code
		// the set has no flag for shows nothing at all: the blank flag the site puts in its place
		// on its own pages would be an empty white box on a card.
		$p->flag = country_flag_url($country_code);

		$pic->
			set($p->id, $p->name, (int)$event_flags, 'e' . $event_id)->
			set($p->id, $p->name, (int)$tournament_flags, 't' . $tournament_id)->
			set($p->id, $p->name, (int)$club_flags, 'c' . $club_id)->
			set($p->id, $p->name, (int)$user_flags);
		// A player with a photo of their own gets it whole, to be cropped square by the card.
		// One without gets nothing: the stand-in figure the site puts in its place says no more
		// than the empty card does, and ten of them in a row say it ten times over.
		$p->photo = $pic->has_image() ? $pic->url(SOURCE_DIR) : '';
		$p->club = is_null($p->club_name) ? get_label('Independent Player') : $p->club_name;
		$players[$p->id] = $p;
	}
	return $players;
}

// The glyphs the frames are drawn with, as the designer drew them. Inline, because they are
// coloured by whatever they stand in - currentColor is what makes one star gold on a badge and
// grey in a label - and because six small shapes are not worth six requests.
function obs_icon($name)
{
	switch ($name)
	{
	case 'star': // the sheriff: six points with ball tips, which is the badge everybody draws
		return '<svg viewBox="-1 -1 26 26"><g fill="currentColor">' .
			'<path d="M12.00 2.00 L15.05 6.72 L20.66 7.00 L18.10 12.00 L20.66 17.00 L15.05 17.28 L12.00 22.00 L8.95 17.28 L3.34 17.00 L5.90 12.00 L3.34 7.00 L8.95 6.72Z"/>' .
			'<circle cx="12.00" cy="1.60" r="1.7"/><circle cx="21.01" cy="6.80" r="1.7"/><circle cx="21.01" cy="17.20" r="1.7"/>' .
			'<circle cx="12.00" cy="22.40" r="1.7"/><circle cx="2.99" cy="17.20" r="1.7"/><circle cx="2.99" cy="6.80" r="1.7"/></g></svg>';
	case 'pistol': // the mafia
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M2.5 7.2h15.2l.9-1.2h2.9v3.6h-1.8l-.5 1H13l-.8 3a1.4 1.4 0 0 1-1.35 1.05H9.2L8 19H4.1l1.9-6.5-3.5-1.7z"/></svg>';
	case 'hat': // the don
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M2 16.6c0-1.1 3.7-2.2 10-2.2s10 1.1 10 2.2-3.7 2.4-10 2.4S2 17.7 2 16.6z"/>' .
			'<path fill="currentColor" d="M6.6 14.6l.7-5.6C7.5 7.2 9.1 5.8 12 5.8s4.5 1.4 4.7 3.2l.7 5.6c-1.7-.5-3.4-.7-5.4-.7s-3.7.2-5.4.7z"/></svg>';
	case 'target': // shot in the night, and the best move that follows from it
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">' .
			'<circle cx="12" cy="12" r="6.5"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/>' .
			'<path d="M12 1.8v4.4M12 17.8v4.4M1.8 12h4.4M17.8 12h4.4"/></svg>';
	case 'thumb': // voted out - a hand put up, tilted
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M2 10.5h3.6V21H2zM7.3 21V10.3l4.6-6.9c.6-.9 2-.7 2.3.4.2.6.2 1.2 0 1.8l-1.1 3.6h6.1c1.5 0 2.6 1.4 2.2 2.9l-1.8 6.9c-.4 1.2-1.4 2-2.7 2z"/></svg>';
	case 'gavel': // the referee
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M13.2 2.6l6.2 6.2-2.1 2.1-6.2-6.2zM8.3 7.5l6.2 6.2-2.1 2.1-6.2-6.2zM11.3 5.9l4.8 4.8-1.8 1.8-4.8-4.8zM10.4 13l1.4 1.4-7.3 7.3L3 20.3zM13 20h9v2h-9z"/></svg>';
	}
	return '';
}

// The page a screen is drawn on: the glows that make the ground, under everything the screen
// puts on top of them. $class names the frame (f1 ... f4) and, on the first, which side won -
// tw or mw, which tints the glows toward them.
function obs_frame_start($class)
{
	echo '<div class="fr ' . $class . '">';
	echo '<div class="glow g1"></div><div class="glow g2"></div><div class="glow g3"></div>' .
		'<div class="glow g4"></div><div class="glow vig"></div>';
}

function obs_frame_end()
{
	echo '</div>';
}

// The line across the top of every frame: the tournament on the left, the title and what it is
// about in the middle, and on the right a pill with whatever that frame has to say there -
// usually the referee. $title and $sub are plain text; $pill is ready-made html.
function obs_head($tournament, $title, $sub, $pill = '')
{
	echo '<div class="hd">';
	echo '<div class="tn"><i></i><span>' . htmlspecialchars($tournament) . '</span></div>';
	echo '<div class="ttl"><h1>' . htmlspecialchars($title) . '</h1>' .
		($sub == '' ? '' : '<div class="sub">' . $sub . '</div>') . '</div>';
	echo ($pill == '' ? '<div></div>' : '<div class="pill">' . $pill . '</div>');
	echo '</div>';
}

// The line under the title: which round, which table, which game, in the order they are said
// out loud. An event standing on its own has no round to name - the frame is already headed
// with its name.
function obs_head_sub($round_name, $table_num, $game_num, $extra = '')
{
	$parts = array();
	if ($round_name != '')
	{
		$parts[] = htmlspecialchars($round_name);
	}
	$parts[] = get_label('Table [0]', $table_num);
	$parts[] = get_label('Game [0]', $game_num);
	if ($extra != '')
	{
		$parts[] = $extra;
	}
	return implode(' &middot; ', $parts);
}

// The referee in a pill: the gavel and the name, with no word before it. Their nicknames run
// long and the name is the only part anybody reads.
function obs_head_judge($name)
{
	return obs_icon('gavel') . '<span class="nm">' . htmlspecialchars($name) . '</span>';
}

// One player card. $player is what obs_players() returned, or NULL for a seat we know nothing
// about. $o may carry:
//   role  - 'sheriff', 'don', 'maf' or '' - the ground the card stands on and the badge in its
//           corner. Left out before a game, when nobody knows them, and the card is neutral.
//   elim  - how the player left the game: ->night or ->vote, and ->number.
//   badge - false to leave the role badge off while still colouring the card by it.
function obs_card($seat, $player, $o = NULL)
{
	$role = (is_object($o) && isset($o->role)) ? $o->role : '';
	$neutral = (is_object($o) && isset($o->neutral) && $o->neutral);
	$badge = !(is_object($o) && isset($o->badge) && !$o->badge);
	$elim = (is_object($o) && isset($o->elim)) ? $o->elim : NULL;

	$class = 'card ' . ($neutral ? 'neutral' : (($role == 'maf' || $role == 'don') ? 'maf' : 'town'));
	if (!$neutral && $role == 'sheriff')
	{
		$class .= ' sher';
	}
	if (!$neutral && $role == 'don')
	{
		$class .= ' don';
	}

	echo '<div class="' . $class . '">';

	// The photograph stands on the card's own colour, with the gradient below fading it into
	// the card. A seat with nobody on it, and a player we have no picture of, get the figure of
	// nobody in particular instead - see .ph.none in between.css.
	if (!is_null($player) && $player->photo != '')
	{
		echo '<div class="ph"><img src="' . $player->photo . '" alt=""></div>';
	}
	else
	{
		echo '<div class="ph none"></div>';
	}

	if ($badge && !$neutral)
	{
		if ($role == 'sheriff')
		{
			echo '<div class="role sher">' . obs_icon('star') . '</div>';
		}
		else if ($role == 'don')
		{
			echo '<div class="role">' . obs_icon('hat') . '</div>';
		}
		else if ($role == 'maf')
		{
			echo '<div class="role">' . obs_icon('pistol') . '</div>';
		}
	}

	if (!is_null($elim))
	{
		$night = isset($elim->night) && $elim->night;
		echo '<div class="elim ' . ($night ? 'night' : 'vote') . '">' .
			obs_icon($night ? 'target' : 'thumb') . (isset($elim->number) ? (int)$elim->number : '') . '</div>';
	}

	echo '<div class="meta">';
	echo '<span class="seat">' . $seat . '</span>';
	echo '<span class="nick">' . (is_null($player) ? '' : htmlspecialchars($player->name)) . '</span>';
	echo '<div class="l2">';
	if (!is_null($player))
	{
		echo '<span class="club">' . htmlspecialchars($player->club) . '</span>';
		if (isset($player->flag) && $player->flag != '')
		{
			echo '<img class="flag" src="' . $player->flag . '" alt="' . htmlspecialchars($player->country) . '">';
		}
	}
	echo '</div></div></div>';
}

?>
