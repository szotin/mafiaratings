<?php

require_once '../../include/session.php';
require_once '../../include/picture.php';
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
	if (isset($_REQUEST['locale']))
	{
		initiate_session(correct_lang($_REQUEST['locale']));
	}
	else
	{
		initiate_session();
	}
}

// The lowest game number at this table that has neither been played nor is being played right
// now. This is next_game.php's reckoning, kept because it is the right one: a game that is on
// the table at this moment is not the next one, and a gap in the played numbers is.
function obs_next_game_num($event_id, $table_num)
{
	$playing = array();
	$query = new DbQuery('SELECT game_num, game FROM current_games WHERE event_id = ? AND table_num = ?', $event_id, $table_num);
	while ($row = $query->next())
	{
		list ($num, $game) = $row;
		$game = json_decode($game);
		if (isset($game->time)) // started, so the table is busy with it
		{
			$playing[(int)$num] = true;
		}
	}

	$game_num = 1;
	while (isset($playing[$game_num]))
	{
		++$game_num;
	}

	$query = new DbQuery('SELECT game_num FROM games WHERE event_id = ? AND table_num = ? ORDER BY game_num', $event_id, $table_num);
	while ($row = $query->next())
	{
		list ($num) = $row;
		if ($game_num != $num)
		{
			break;
		}
		do
		{
			++$game_num;
		}
		while (isset($playing[$game_num]));
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
	}
	if ($tournament_id <= 0)
	{
		return NULL;
	}

	$c = new stdClass();
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
	foreach ($c->rounds as $r)
	{
		$next = obs_next_game_num($r->id, $table_num);
		if ($r->games > 0 && $next > $r->games)
		{
			continue; // this round is played out at this table
		}

		$c->event_id = $r->id;
		$c->event_name = $r->name;
		$c->round_num = (int)$r->round_num;
		$c->next_game_num = ($r->games > 0) ? $next : 0; // no seating, so nothing to call upcoming
		break;
	}

	// Every round played out: the tournament is over at this table. Stay on the last round so
	// that its results still have somewhere to come from.
	if ($c->event_id <= 0 && count($c->rounds) > 0)
	{
		$last = $c->rounds[count($c->rounds) - 1];
		$c->event_id = $last->id;
		$c->event_name = $last->name;
		$c->round_num = (int)$last->round_num;
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
		'SELECT u.id, nu.name, u.flags, eu.flags, tu.flags, cu.flags, uc.id, uc.name, uc.flags, nn.name'.
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
			$p->club_id, $p->club_name, $club_pic_flags, $p->country) = $row;
		$p->id = (int)$p->id;

		$pic->
			set($p->id, $p->name, (int)$event_flags, 'e' . $event_id)->
			set($p->id, $p->name, (int)$tournament_flags, 't' . $tournament_id)->
			set($p->id, $p->name, (int)$club_flags, 'c' . $club_id)->
			set($p->id, $p->name, (int)$user_flags);
		// A player with a photo of their own gets it whole, to be cropped square by the card. One
		// without gets the site's default icon instead of the default thumbnail: the thumbnail is
		// a wide picture with the figure lost in the middle of it, which comes out tiny once the
		// card has cropped it. The card knows to lay the icon out differently - see .card.no-photo.
		$p->has_photo = $pic->has_image();
		$p->photo = $pic->url($p->has_photo ? SOURCE_DIR : ICONS_DIR);
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
// $mark, when there is one, is the badge under the number: ->class says what it is and ->text
// what is written on it, if anything - the picture ones carry nothing.
function obs_card($number, $player, $extra_class = '', $corner = '', $mark = NULL)
{
	if (!is_null($player) && isset($player->has_photo) && !$player->has_photo)
	{
		$extra_class .= ' no-photo';
	}
	echo '<div class="card ' . $extra_class . '">';
	echo '<div class="card-no">' . $number . '</div>';
	echo '<div class="card-name">' . (is_null($player) ? '' : htmlspecialchars($player->name)) . '</div>';
	if ($corner != '')
	{
		echo '<div class="card-corner">' . $corner . '</div>';
	}
	if (!is_null($mark))
	{
		echo '<div class="card-mark ' . $mark->class . '">' . htmlspecialchars($mark->text) . '</div>';
	}
	echo '<div class="card-photo"' . (is_null($player) ? '' : ' style="background-image: url(' . $player->photo . ')"') . '></div>';
	echo '<div class="card-club">';
	if (!is_null($player))
	{
		echo '<div class="card-club-name">' . htmlspecialchars($player->club) . '</div>';
		echo '<div class="card-country">' . htmlspecialchars($player->country) . '</div>';
	}
	echo '</div>';
	echo '</div>';
}

// A screen prints its heading through this so that they all look alike.
function obs_screen_head($left, $middle, $right)
{
	echo '<div class="screen-head">';
	echo '<div class="head-left">' . $left . '</div>';
	echo '<div class="head-middle">' . $middle . '</div>';
	echo '<div class="head-right">' . $right . '</div>';
	echo '</div>';
}

?>
