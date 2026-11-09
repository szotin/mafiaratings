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

// The badge in the top left corner of every frame. A tournament that has a logo of its own
// wears it; one that has none wears its club's, which is what the stream is run by anyway.
// Nothing at all when neither has a picture - the name then stands where the two of them
// would, rather than beside an empty tile.
function obs_logo($pic, $id, $name, $flags, $club_id)
{
	$pic->set($id, $name, $flags);
	if ($pic->has_image())
	{
		return $pic->url(ICONS_DIR);
	}

	if ($club_id > 0)
	{
		list ($club_name, $club_flags) = Db::record(get_label('club'), 'SELECT name, flags FROM clubs WHERE id = ?', $club_id);
		$club = new Picture(CLUB_PICTURE);
		$club->set((int)$club_id, $club_name, (int)$club_flags);
		if ($club->has_image())
		{
			return $club->url(ICONS_DIR);
		}
	}
	return '';
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

	list ($name, $event_flags, $c->club_id, $c->scoring_id, $c->scoring_version, $c->scoring_options, $misc) = Db::record(get_label('event'),
		'SELECT name, flags, club_id, scoring_id, scoring_version, scoring_options, misc FROM events WHERE id = ?', $event_id);

	$c->logo = obs_logo(new Picture(EVENT_PICTURE), $event_id, $name, (int)$event_flags, $c->club_id);

	// The event is what the screens head themselves with, and there is no round under it to
	// name a second time.
	$c->tournament_name = $name;
	$c->event_id = $event_id;
	$c->event_name = '';
	$c->round_num = 0;
	$c->tables = seating_tables($misc);

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
	$c->logo = obs_logo(new Picture(TOURNAMENT_PICTURE), $tournament_id, $c->tournament_name, $c->tournament_flags, $c->club_id);

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
		$r->tables = seating_tables($misc);
		$c->rounds[] = $r;
	}

	$c->event_id = 0;
	$c->event_name = '';
	$c->round_num = 0;
	$c->tables = 0;    // nothing says how many tables there are until a round does
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
		$c->tables = $r->tables;
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
		$c->tables = $last->tables;
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
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M2 6.2h19.2v4.3h-7.4c0 2.1-1.2 3.3-3.2 3.3h-.9L8.1 19.8H3.5l1.8-9.3H2zM10.1 10.5h2.2c0 1-.6 1.6-1.6 1.6h-.6z"/></svg>';
	case 'hat': // the don - a fedora, pinched along the crown
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M1.8 16.2c0-1.4 4.2-2.6 10.2-2.6s10.2 1.2 10.2 2.6-4.2 2.6-10.2 2.6S1.8 17.6 1.8 16.2z"/>' .
			'<path fill="currentColor" d="M6.6 14l.8-5.7c.3-1.6 1.8-2.6 3-2 .6.3 1 .4 1.6.4s1-.1 1.6-.4c1.2-.6 2.7.4 3 2l.8 5.7c-1.6-.4-3.4-.6-5.4-.6s-3.8.2-5.4.6z"/>' .
			'<path d="M7 12.2c1.6-.4 3.2-.6 5-.6s3.4.2 5 .6" fill="none" stroke="rgba(0,0,0,.4)" stroke-width="1.3"/></svg>';
	case 'target': // shot in the night, and the best move that follows from it
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">' .
			'<circle cx="12" cy="12" r="6.5"/><circle cx="12" cy="12" r="1.6" fill="currentColor" stroke="none"/>' .
			'<path d="M12 1.8v4.4M12 17.8v4.4M1.8 12h4.4M17.8 12h4.4"/></svg>';
	case 'thumb': // voted out - a hand put up
		return '<svg viewBox="0 0 24 24"><path fill="currentColor" d="M3 10h3.4a.6.6 0 0 1 .6.6v9.8a.6.6 0 0 1-.6.6H3a.6.6 0 0 1-.6-.6v-9.8A.6.6 0 0 1 3 10zm6 11V10.6l3.9-6.4a1.6 1.6 0 0 1 2.9 1.2l-.9 3.6h4.6a2.2 2.2 0 0 1 2.1 2.8l-2 7.3a2.6 2.6 0 0 1-2.5 1.9z"/></svg>';
	case 'cross': // taken out of the game by the judge - the mark the overlay draws for it too
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" ' .
			'stroke-linecap="round"><path d="M5.5 5.5l13 13M18.5 5.5l-13 13"/></svg>';
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

// The line across the top of every frame. Three things stand in it and nothing else:
//   left   - the tournament, its logo on a tile and under its name the one line that says
//            which part of it this is (the round, or what the numbers are counted over);
//   centre - the title, with a second line only where the title leaves something unsaid -
//            the kind of mafia win, which is the whole of what is added to "MAFIA WIN";
//   right  - the one number the frame is about: which game of how many, which places are
//            listed, how many games the tournament has behind it.
//
// Everything else that used to be up here - the table, the referee, the game - has gone to
// the foot of the frame, where it does not have to be read before the title.
function obs_head($c, $title, $line = '', $sub = '', $num = '')
{
	echo '<div class="hd">';

	echo '<div class="blk">';
	if ($c->logo != '')
	{
		echo '<span class="tlogo"><img src="' . $c->logo . '" alt=""></span>';
	}
	echo '<div><div class="b1">' . htmlspecialchars($c->tournament_name) . '</div>' .
		'<div class="b2">' . htmlspecialchars($line) . '</div></div></div>';

	echo '<div class="ttl"><h1>' . htmlspecialchars($title) . '</h1>' .
		($sub == '' ? '' : '<div class="sub">' . htmlspecialchars($sub) . '</div>') . '</div>';

	echo '<div class="blk r">' .
		($num == '' ? '' : '<div><div class="b1">' . htmlspecialchars($num) . '</div></div>') .
		'</div>';

	echo '</div>';
}

// The referee, in the bottom right corner: the table, the gavel, the name and the face. No
// word before the name - their nicknames run long and the gavel says what it is.
// $judge is what obs_players() returned, or NULL when nobody is at the table.
//
// A round seated for one table says nothing about which table it is. There is no other table
// to tell this one from, and "Table 1" on a stream with one table in it is a word that tells a
// viewer nothing. A round whose seating is not arranged yet says it as before: nothing states
// how many tables it will turn out to need - see seating_tables().
function obs_foot($c, $judge)
{
	echo '<div class="ftr">';
	$named = false;
	if ($c->tables != 1)
	{
		echo '<span>' . get_label('Table [0]', $c->table_num) . '</span>';
		$named = true;
	}
	if (!is_null($judge))
	{
		echo ($named ? '<span class="fdv"></span>' : '') . obs_icon('gavel') .
			'<b>' . htmlspecialchars($judge->name) . '</b>';
		if ($judge->photo != '')
		{
			echo '<span class="jav"><img src="' . $judge->photo . '" alt=""></span>';
		}
		else
		{
			echo '<span class="jav none"></span>';
		}
	}
	echo '</div>';
}

// Who is running this table, for the foot of a frame. The game under way is the one with a
// referee at it; between two games the one just played out is the nearest thing to it, and
// it is the same person anyway more often than not.
function obs_judge($c)
{
	$id = 0;
	if (!is_null($c->playing) && isset($c->playing->log->moderator->id))
	{
		$id = (int)$c->playing->log->moderator->id;
	}
	else if (!is_null($c->done) && isset($c->done->log->moderator->id))
	{
		$id = (int)$c->done->log->moderator->id;
	}
	if ($id <= 0)
	{
		return NULL;
	}

	$players = obs_players(array($id), $c->event_id, $c->tournament_id, $c->club_id);
	return isset($players[$id]) ? $players[$id] : NULL;
}

// How many games this table plays in that round, for the "game 8 of 10" in the corner. Zero
// when the round has no seating, and then the corner says the game and nothing more.
function obs_round_games($event_id, $table_num)
{
	list ($misc) = Db::record(get_label('event'), 'SELECT misc FROM events WHERE id = ?', $event_id);
	return obs_seated_games($misc, $table_num);
}

// Which game of how many, as the corner of a frame says it.
function obs_head_game($game_num, $games)
{
	return $games > 0 ? get_label('Game [0] of [1]', $game_num, $games) : get_label('Game [0]', $game_num);
}

// A number with the word after it, in the form that number takes. $words holds the three
// forms separated by bars - one thing, a few of them, many of them - because that is what
// Russian and Ukrainian count in; English uses the second for everything but one.
//
// The words arrive already translated, and the caller is the one who asks for them: this
// was the only place in the whole site that looked a label up by a name held in a variable,
// and build_labels.php reads the source with the PHP tokenizer to find out which labels are
// still in use. A name it cannot see is a label it believes nobody wants, so every rebuild
// of the label files quietly took the two plural forms this function counts in out of the
// Russian and the Ukrainian - and the frame then counted in English. Asked for at the call
// site the names are plain in the source, and the tokenizer finds them there.
function obs_count($count, $words)
{
	global $_lang;

	$forms = explode('|', $words);
	if (count($forms) < 3)
	{
		return $count . ' ' . $words;
	}

	if (($_lang & (LANG_RUSSIAN | LANG_UKRAINIAN)) != 0)
	{
		$tens = $count % 100;
		$ones = $count % 10;
		$form = ($ones == 1 && $tens != 11) ? 0 :
			(($ones >= 2 && $ones <= 4 && ($tens < 12 || $tens > 14)) ? 1 : 2);
	}
	else
	{
		$form = ($count == 1) ? 0 : 1;
	}
	return $count . ' ' . $forms[$form];
}

// The colour a seat number is drawn in, which is the one thing every number on every frame
// agrees on: the town's red, the mafia's charcoal, the sheriff's gold, and the flat grey of
// a seat whose part is not known - before a game, or on a frame that does not tell.
function obs_seat_class($role, $unknown = false)
{
	if ($unknown)
	{
		return 'n';
	}
	switch ($role)
	{
	case 'maf':
	case 'don':
		return 'b';
	case 'sheriff':
		return 'g';
	}
	return 'r';
}

// One player card. $player is what obs_players() returned, or NULL for a seat we know nothing
// about. $o may carry:
//   role  - 'sheriff', 'don', 'maf' or '' - the ground the card stands on and the badge in its
//           corner. Left out before a game, when nobody knows them, and the card is neutral.
//   elims - how the player left the game, as an array of marks, each with ->kind and, where
//           there is one to name, ->number. The kinds are 'night', 'vote', 'out' for a player
//           the judge took out of the game, and 'td' where their own side lost because of them.
//           Two of them where the mafia took a player and the judge took them out afterwards.
//   badge - false to leave the role badge off while still colouring the card by it.
function obs_card($seat, $player, $o = NULL)
{
	$role = (is_object($o) && isset($o->role)) ? $o->role : '';
	$neutral = (is_object($o) && isset($o->neutral) && $o->neutral);
	$badge = !(is_object($o) && isset($o->badge) && !$o->badge);
	$elims = (is_object($o) && isset($o->elims)) ? $o->elims : array();

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

	if (!empty($elims))
	{
		echo '<div class="elims">';
		foreach ($elims as $mark)
		{
			$kind = isset($mark->kind) ? $mark->kind : '';
			if ($kind == 'td')
			{
				echo '<div class="elim td">' . get_label('TD') . '</div>';
			}
			else if ($kind == 'out')
			{
				echo '<div class="elim out">' . obs_icon('cross') . '</div>';
			}
			else
			{
				echo '<div class="elim ' . ($kind == 'night' ? 'night' : 'vote') . '">' .
					obs_icon($kind == 'night' ? 'target' : 'thumb') .
					(isset($mark->number) ? (int)$mark->number : '') . '</div>';
			}
		}
		echo '</div>';
	}

	echo '<div class="meta">';
	echo '<span class="seat ' . obs_seat_class($neutral ? '' : $role, $neutral) . '">' . $seat . '</span>';
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
