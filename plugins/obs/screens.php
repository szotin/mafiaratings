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
	$c->done = NULL; // the game played out at this table but not yet written down, if there is one
	$settled = -1;   // how far down the rounds we got
	foreach ($c->rounds as $index => $r)
	{
		$state = obs_table_state($r->id, $table_num);
		$next = obs_next_game_num($r->id, $table_num, $state);

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

// Opens a row of cards, and is closed by the caller with a plain </div>. How many cards are
// coming is handed to the stylesheet, which cannot count them and needs the number to work out
// how wide one of them may be - see .card in between.css.
function obs_card_row($count, $extra_class = '')
{
	echo '<div class="card-row ' . $extra_class . '" style="--cards: ' . max((int)$count, 1) . '">';
}

// One card. $player may be NULL - a seat whose player we do not know still keeps its number.
// $mark, when there is one, is the badge under the number saying how the player left the game:
// ->class is what it is and ->text what is written on it, if anything - the picture ones carry
// no text.
function obs_card($number, $player, $extra_class = '', $corner = '', $mark = NULL)
{
	echo '<div class="card ' . $extra_class . '">';
	echo '<div class="card-no">' . $number . '</div>';
	echo '<div class="card-name">' . (is_null($player) ? '' : htmlspecialchars($player->name)) . '</div>';
	if ($corner != '')
	{
		echo '<div class="card-corner">' . $corner . '</div>';
	}
	if (!is_null($mark) && $mark->class != '')
	{
		echo '<div class="card-mark ' . $mark->class . '">' . htmlspecialchars($mark->text) . '</div>';
	}

	// A player we have no picture of wears the back of a card, which the stylesheet puts there;
	// a seat with nobody on it is left plain, which none uncovers - see between.css.
	if (is_null($player))
	{
		echo '<div class="card-photo" style="background-image: none"></div>';
	}
	else if ($player->photo != '')
	{
		echo '<div class="card-photo" style="background-image: url(' . $player->photo . ')"></div>';
	}
	else
	{
		echo '<div class="card-photo"></div>';
	}
	// The club along the bottom, with the flag of the country in the corner beside it - a flag is
	// read at a glance and leaves the whole line to the club, which a second line of text did not.
	echo '<div class="card-club">';
	if (!is_null($player))
	{
		echo '<div class="card-club-name">' . htmlspecialchars($player->club) . '</div>';
		if (isset($player->flag) && $player->flag != '')
		{
			echo '<div class="card-flag" style="background-image: url(' . $player->flag . ')" title="' .
				htmlspecialchars($player->country) . '"></div>';
		}
	}
	echo '</div>';
	echo '</div>';
}

// A screen prints its heading through this so that they all look alike.
// The line under the title of a screen: which round, which table, which game. An event of its
// own has no round to name - the screen is already headed with its name - so it says only
// where it is.
function obs_screen_sub($round_name, $table_num, $game_num)
{
	$where = get_label('Table [0], Game [1]', $table_num, $game_num);
	if ($round_name == '')
	{
		return '<div class="head-sub">' . $where . '</div>';
	}
	return '<div class="head-sub">' . htmlspecialchars($round_name) . ' &middot; ' . $where . '</div>';
}

function obs_screen_head($left, $middle, $right)
{
	echo '<div class="screen-head">';
	echo '<div class="head-left">' . $left . '</div>';
	echo '<div class="head-middle">' . $middle . '</div>';
	echo '<div class="head-right">' . $right . '</div>';
	echo '</div>';
}

?>
