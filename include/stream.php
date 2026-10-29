<?php

require_once 'db.php';
require_once 'constants.php';
require_once 'languages.php';
require_once 'localization.php'; // get_label
// By full path on purpose: a bare 'game.php' is resolved against the working directory first,
// and the working directory is the site root, where game.php is the page the game is played on.
require_once __DIR__ . '/game.php'; // the phases of a game and the order they come in

// How the between-games screens are set up at one table. The browser source in OBS is pointed
// at a table and nothing else; everything else is read from here, which is what lets a club
// manager change it from the site while the source is running.
//
// A table belongs to one of two things, and the settings follow it there. A tournament keeps
// its tables from round to round, so the settings are the tournament's and every round reads
// the same ones. An event played on its own has tables of its own and nothing above it. The
// two are told apart by a stream owner, which is what the rest of this file is written in
// terms of: the pair of tables holds the same columns and differs only in what it is keyed by.
//
// A table with no row of its own runs on the defaults. That is the usual case rather than an
// odd one: a row is written the first time somebody sets something for that table.

define('STREAM_DEFAULT_DELAY', 10); // seconds a screen stays up
define('STREAM_MIN_DELAY', 1);
define('STREAM_MAX_DELAY', 600);

// Whether this thing can be followed in words right now: it has begun, and nobody has marked
// it over or called it off. A text broadcast of a tournament that is finished would show the
// standings and nothing else, which the tournament's own pages say better, and one of a
// tournament that has not begun would show an empty table.
//
// The two answers are asked of the caller rather than read here, because a tournament and an
// event keep them under different bits.
function stream_is_live($start_time, $finished, $canceled)
{
	return $start_time <= time() && !$finished && !$canceled;
}

// The tables a text broadcast is offered for, by number.
//
// The seating says both how many tables there are and how many games each of them plays, so
// where there is one it settles the question twice over: a table that has played all the games
// it was laid out for is left out, because there is nothing more to read there. Tables do not
// finish together - the numbers rarely divide evenly, and one table always runs late - so this
// is a table at a time rather than the whole thing at once.
//
// Where there is no seating nothing says how many games a table has coming, so what has been
// played is all there is to go by: a table somebody has played at is a table, and the first
// table is offered whatever happens, since a tournament that has begun is being played
// somewhere.
//
// This is not stream_table_count(). That one counts the tables a stream can be set up for,
// which takes in tables nobody has reached yet, because the setting up is done beforehand.
// Reading is done while it happens, so here only what is being played at counts.
function stream_text_tables($owner)
{
	// How many games the seating lays out at each table. It is kept game by game -
	// rounds[game][table][seat] - so a table is in it once for every game it plays.
	$seated = array();
	$query = $owner->is_tournament
		? new DbQuery('SELECT misc FROM events WHERE tournament_id = ?', $owner->id)
		: new DbQuery('SELECT misc FROM events WHERE id = ?', $owner->id);
	while ($row = $query->next())
	{
		list ($misc) = $row;
		$misc = is_null($misc) ? NULL : json_decode($misc);
		if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
		{
			continue;
		}
		foreach ($misc->seating->rounds as $round)
		{
			if (!is_array($round))
			{
				continue;
			}
			for ($table_num = 1; $table_num <= count($round); ++$table_num)
			{
				$seated[$table_num] = isset($seated[$table_num]) ? $seated[$table_num] + 1 : 1;
			}
		}
	}

	$where = $owner->is_tournament ? 'tournament_id' : 'event_id';
	$played = array();
	$query = new DbQuery('SELECT table_num, COUNT(*) FROM games WHERE ' . $where . ' = ? GROUP BY table_num', $owner->id);
	while ($row = $query->next())
	{
		list ($table_num, $count) = $row;
		$played[(int)$table_num] = (int)$count;
	}

	// A game being played right now is not in games yet, and it is the very game somebody
	// would be reading - so a table playing one is offered whatever the counting says.
	$playing = array();
	$query = $owner->is_tournament
		? new DbQuery('SELECT DISTINCT g.table_num FROM current_games g JOIN events e ON e.id = g.event_id WHERE e.tournament_id = ?', $owner->id)
		: new DbQuery('SELECT DISTINCT table_num FROM current_games WHERE event_id = ?', $owner->id);
	while ($row = $query->next())
	{
		$playing[(int)$row[0]] = true;
	}

	$tables = array();
	if (!empty($seated))
	{
		ksort($seated);
		foreach ($seated as $table_num => $games)
		{
			$done = isset($played[$table_num]) ? $played[$table_num] : 0;
			if (isset($playing[$table_num]) || $done < $games)
			{
				$tables[] = $table_num;
			}
		}
		return $tables;
	}

	$count = 1;
	foreach (array_keys($played) as $table_num)
	{
		$count = max($count, $table_num);
	}
	foreach (array_keys($playing) as $table_num)
	{
		$count = max($count, $table_num);
	}
	for ($table_num = 1; $table_num <= $count; ++$table_num)
	{
		$tables[] = $table_num;
	}
	return $tables;
}

// The language a page standing at a table is to be shown in, worked out from the address it
// was opened by: the address names the table, and what the table is set to show is on the
// site - see stream_settings(). That is the whole point of it being there. A source set up in
// OBS months ago follows a language changed on the site this morning, and nobody has to go
// round the hall retyping addresses.
//
// Where the address names no table there is nothing to read, and the locale it carries is
// used instead; failing that, whatever the visitor's browser asks for.
//
// Called before the session is started, which is safe: nothing here wants a language of its
// own, and get_label answers in English until one is chosen.
function stream_request_lang()
{
	$tournament_id = isset($_REQUEST['tournament_id']) ? (int)$_REQUEST['tournament_id'] : 0;
	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	$owner = stream_owner($tournament_id, $event_id);
	if (is_null($owner))
	{
		return 0;
	}

	$table_num = isset($_REQUEST['table_num']) ? max((int)$_REQUEST['table_num'], 1) : 1;
	$settings = stream_settings($owner, $table_num);
	return (int)$settings->lang_resolved;
}

// The same, as the code a session is started with, or NULL where the address settles it
// instead - see stream_request_lang().
function stream_request_lang_code()
{
	$lang = stream_request_lang();
	if ($lang > 0)
	{
		return get_lang_code($lang);
	}
	return isset($_REQUEST['locale']) ? correct_lang($_REQUEST['locale']) : NULL;
}

// Which of the two a table hangs under. A round of a tournament is the tournament's: pass the
// tournament, not the round.
function stream_tournament($tournament_id)
{
	$owner = new stdClass();
	$owner->is_tournament = true;
	$owner->id = (int)$tournament_id;
	$owner->table = 'tournament_streams';
	$owner->column = 'tournament_id';
	return $owner;
}

function stream_event($event_id)
{
	$owner = new stdClass();
	$owner->is_tournament = false;
	$owner->id = (int)$event_id;
	$owner->table = 'event_streams';
	$owner->column = 'event_id';
	return $owner;
}

// What the request named, as an owner. A round of a tournament resolves to its tournament, an
// event of its own to itself, and anything we cannot make sense of to NULL.
function stream_owner($tournament_id, $event_id)
{
	$tournament_id = (int)$tournament_id;
	$event_id = (int)$event_id;
	if ($tournament_id > 0)
	{
		return stream_tournament($tournament_id);
	}
	if ($event_id > 0)
	{
		list ($tournament_id) = Db::record(get_label('event'), 'SELECT tournament_id FROM events WHERE id = ?', $event_id);
		return ((int)$tournament_id > 0) ? stream_tournament($tournament_id) : stream_event($event_id);
	}
	return NULL;
}

// The screens between.js can be told to show, in the order they are shown when nobody has said
// otherwise. The short name is what is stored and what appears in the settings; the file is
// where between.js fetches the screen from.
function stream_screens()
{
	return array(
		'results' => 'screen_results.php',
		'standings' => 'screen_standings.php',
		'upcoming' => 'screen_upcoming.php',
		'stats' => 'screen_stats.php');
}

// Every screen, in the order they stand in for this table: the ones being shown first, in the
// order they are shown, and then the ones that are switched off, in the order they come in by
// default. A screen that is switched off still has to stand somewhere, and where it stands is
// where it comes back when it is switched on again.
function stream_screen_order($shown)
{
	$order = $shown;
	foreach (array_keys(stream_screens()) as $name)
	{
		if (!in_array($name, $order))
		{
			$order[] = $name;
		}
	}
	return $order;
}

// What a screen is called where somebody is choosing between them. Spelled out one by one
// rather than built from the short name, so that the labels can be found by searching for them
// the way every other label in the site can.
function stream_screen_label($name)
{
	switch ($name)
	{
	case 'results':
		return get_label('Previous game');
	case 'upcoming':
		return get_label('Next game');
	case 'standings':
		return get_label('Standings');
	case 'stats':
		return get_label('Wins');
	}
	return $name;
}

// The language the games are played in, as far as anything says so. langs is a mask of every
// language the thing is run in; the screens need one, so the lowest of them is taken, and
// English when nothing says anything.
function stream_default_lang($owner)
{
	if ($owner->is_tournament)
	{
		list ($langs, $club_langs) = Db::record(get_label('tournament'),
			'SELECT t.langs, c.langs FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $owner->id);
	}
	else
	{
		list ($langs, $club_langs) = Db::record(get_label('event'),
			'SELECT e.languages, c.langs FROM events e JOIN clubs c ON c.id = e.club_id WHERE e.id = ?', $owner->id);
	}

	$langs = (int)$langs;
	if ($langs == 0)
	{
		$langs = (int)$club_langs;
	}
	for ($lang = 1; $lang > 0 && $lang < LANG_ALL; $lang <<= 1)
	{
		if ($langs & $lang)
		{
			return $lang;
		}
	}
	return LANG_DEFAULT;
}

// How many tables the seating is laid for - the widest round of it. Zero when there is no
// seating, which is the only case where nothing says for certain how many tables there are:
// a seating is a statement, while games played and tables already set up are only evidence
// that there are at least that many.
function stream_seated_tables($owner)
{
	$tables = 0;
	$query = $owner->is_tournament
		? new DbQuery('SELECT misc FROM events WHERE tournament_id = ?', $owner->id)
		: new DbQuery('SELECT misc FROM events WHERE id = ?', $owner->id);
	while ($row = $query->next())
	{
		list ($misc) = $row;
		$misc = is_null($misc) ? NULL : json_decode($misc);
		if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
		{
			continue;
		}
		foreach ($misc->seating->rounds as $round)
		{
			if (is_array($round))
			{
				$tables = max($tables, count($round));
			}
		}
	}
	return $tables;
}

// How many tables are played at, which is what the settings page has a tab for each of.
//
// The scheme is what says so, where it has been filled in: that is somebody stating how many
// tables there are. A table that is actually seated, or that a game has been written down at,
// counts whatever the scheme says - there is real work behind it and hiding it would hide the
// work - but a table nobody has settled on is not conjured up out of a row of settings.
//
// That last part is why the scheme is asked at all. Settings outlive the scheme that prompted
// them: cut a tournament from three tables to two and the third one's settings stay behind,
// and counting them would keep the third table on the page for ever. They are still counted
// while there is no scheme to go by, so that a table can be set up before anybody has said
// how many there will be. At least one in every case.
function stream_table_count($owner)
{
	$where = $owner->is_tournament ? 'tournament_id' : 'event_id';
	list ($played) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM games WHERE ' . $where . ' = ?', $owner->id);
	$count = max(1, stream_seated_tables($owner), (int)$played);

	list ($scheme) = Db::record(get_label('event'), $owner->is_tournament
		? 'SELECT MAX(tables) FROM events WHERE tournament_id = ?'
		: 'SELECT MAX(tables) FROM events WHERE id = ?', $owner->id);
	if ((int)$scheme > 0)
	{
		return max($count, (int)$scheme);
	}

	list ($set_up) = Db::record(get_label('event'), 'SELECT MAX(table_num) FROM ' . $owner->table . ' WHERE ' . $owner->column . ' = ?', $owner->id);
	return max($count, (int)$set_up);
}

// What the screens at this table are to do. Returns an object whatever the table is, so that
// the callers never have to tell a table that has been set up from one that has not.
//
// lang is kept as it is stored, where 0 means "whatever the games are played in" rather than a
// language of its own. What that works out to is lang_resolved, which is what the screens are
// actually shown in. Anything writing the settings back has to use the first, or a table left
// on the default would quietly be nailed to one language.
function stream_settings($owner, $table_num)
{
	$all = stream_screens();

	$settings = new stdClass();
	$settings->table_num = (int)$table_num;
	$settings->lang = 0;
	$settings->delay = STREAM_DEFAULT_DELAY;
	$settings->screens = array_keys($all);
	$settings->flags = 0;
	$settings->scene = '';          // the standing order from a person, if there is one
	$settings->scene_time = 0;
	$settings->scenes = array();    // what the source reported about the OBS it runs in
	$settings->control_level = -1;  // and how much it is allowed to do there
	$settings->scene_events = array(); // the scene the game asks for in each of its phases
	$settings->time_shift = 0;      // how long the stream of this table is delayed, once known

	$query = new DbQuery('SELECT lang, delay, screens, flags, scene, scene_time, scenes, scene_events, control_level, time_shift FROM ' . $owner->table .
		' WHERE ' . $owner->column . ' = ? AND table_num = ?', $owner->id, $table_num);
	if ($row = $query->next())
	{
		list ($lang, $delay, $screens, $flags, $scene, $scene_time, $scene_names, $scene_events, $control_level, $time_shift) = $row;
		$settings->lang = (int)$lang;
		$settings->delay = (int)$delay;
		$settings->flags = (int)$flags;
		$settings->scene = $scene;
		$settings->scene_time = (int)$scene_time;
		$settings->control_level = (int)$control_level;
		$settings->time_shift = (int)$time_shift;

		$scene_names = is_null($scene_names) ? NULL : json_decode($scene_names);
		if (is_array($scene_names))
		{
			$settings->scenes = $scene_names;
		}

		$settings->scene_events = stream_parse_scene_events($scene_events);

		// An empty list is every screen rather than none: a source showing nothing at all would
		// look like one that is broken, and there is no reason anybody would ask for it.
		$wanted = stream_parse_screens($screens);
		if (!empty($wanted))
		{
			$settings->screens = $wanted;
		}
	}

	$settings->lang_resolved = ($settings->lang > 0) ? $settings->lang : stream_default_lang($owner);
	$settings->delay = min(max($settings->delay, STREAM_MIN_DELAY), STREAM_MAX_DELAY);
	return $settings;
}

// Level of control a browser source needs before it may change a scene. The numbers are
// obs-browser's own; 4 is ADVANCED, which is the Page permissions setting of the same name.
define('STREAM_CONTROL_ADVANCED', 4);

// The scene the game being played at this table is asking for. The game says what is happening
// at the table and nothing else - whose speech it is, whether it is night, whether the roles
// are being handed out - and which scene that comes to is worked out here, against what the
// table was set up with. The moderator's screen used to do it and carry the answer in the game;
// doing it here means a change to the switching reaches a game already under way instead of
// waiting for that screen to be reloaded, and that the one place the sources ask is the one
// place that decides.
//
// Empty when the table has been told not to switch by itself, when nothing is set up for it,
// when no game is being played at it, or when nothing is set up for the phase that game is in.
function stream_played_scene($owner, $table_num, $settings)
{
	$scene_events = $settings->scene_events;
	if (($settings->flags & STREAM_FLAG_NO_AUTO_SCENE) != 0 || empty($scene_events))
	{
		return '';
	}

	$where = $owner->is_tournament ? 'e.tournament_id = ?' : 'e.id = ?';
	$query = new DbQuery(
		'SELECT g.game FROM current_games g JOIN events e ON e.id = g.event_id'.
		' WHERE ' . $where . ' AND g.table_num = ?', $owner->id, $table_num);
	$data = NULL;
	while ($row = $query->next())
	{
		list ($game) = $row;
		$game = json_decode($game);
		if (is_object($game) && isset($game->time) && isset($game->time->time))
		{
			$data = $game;
			break;
		}
	}
	// And NULL where there is no game at that table, which is a thing the scenes have an
	// answer for rather than a reason to leave the scene where it is - see below.
	return stream_game_scene($scene_events, $data);
}

// Which of the scenes a table should be showing. $game is the game being played at it, or NULL
// between games.
//
// A scene stands against a phase, and it is shown from that phase until the next phase that
// has a scene of its own. It used to be shown during that phase and no longer, which left
// every phase nobody had named showing whatever happened to be up - and worse, left the end of
// a game showing the game. The moderator can be through the last screen and the game saved
// inside the two seconds between one ask and the next, and then the end was never seen at all
// and the break scene never came on. That is what this is.
//
// The phases are put in the order the game runs through them - Game::gametime_to_int(), the
// same order the game itself is kept in - and the scene of the latest one the game has reached
// is the scene to show. The last of them covers both ends of the round: the phases before the
// first scene was asked for, and everything after the last, which is also where a table with
// no game at all sits. So a scene against the end of a game is the scene between games, which
// is what anybody setting one there means by it.
//
// A seat that is speaking still comes first. That is not a point on the timeline but something
// shown while it lasts, and the timeline takes over again when the speech is over.
function stream_game_scene($scene_events, $game)
{
	// A seat with no scene of its own carries on with the scene of the seat before it, round
	// the table, so that one scene can cover a run of seats without being named against each.
	$seats = array_fill(0, 10, '');
	$phases = array();
	foreach ($scene_events as $pair)
	{
		$seat = (int)$pair->event;
		if ((string)$seat === $pair->event && $seat >= 1 && $seat <= 10)
		{
			$seats[$seat - 1] = $pair->scene;
		}
		else
		{
			$order = stream_event_order($pair->event);
			if ($order >= 0)
			{
				$phases[$order] = $pair->scene; // one row to a phase - see stream_parse_scene_events
			}
		}
	}

	$carry = '';
	for ($i = 0; $i < 10; ++$i)
	{
		if ($seats[$i] == '')
		{
			$seats[$i] = $carry;
		}
		else
		{
			$carry = $seats[$i];
		}
	}
	for ($i = 0; $carry != '' && $i < 10 && $seats[$i] == ''; ++$i)
	{
		$seats[$i] = $carry; // the seats before the first one named carry on round from the last
	}

	if (!is_null($game))
	{
		$speaker = isset($game->time->speaker) ? (int)$game->time->speaker : 0;
		if ($speaker >= 1 && $speaker <= 10 && $seats[$speaker - 1] != '')
		{
			return $seats[$speaker - 1];
		}
	}

	if (empty($phases))
	{
		return ''; // nothing but seats is set up, and nobody is speaking
	}

	ksort($phases);
	$scene = end($phases); // the one that covers the far end of the round, and so both ends
	if (is_null($game))
	{
		return $scene;
	}

	$now = Game::gametime_to_int($game->time->time);
	foreach ($phases as $order => $named)
	{
		if ($order > $now)
		{
			break;
		}
		$scene = $named;
	}
	return $scene;
}

// Where a phase a scene can be set against stands in the order the game runs through them, or
// -1 for the ones that are not a point on that timeline at all - a seat, or a row nobody has
// chosen a phase for yet.
//
// The two of them that are not phases of the game itself are the plain day and night, and they
// stand where the day and the night begin. The roles being dealt and what goes with it come
// before the first night of all, so they belong to whatever covers the turn of the game -
// unless a scene is set against them by name, which is what that row is for.
//
// Voting stands at the moment the table is sent to the vote rather than at the counting of
// hands, so that the screen the moderator is looking at while the hands go up is already the
// one the viewers are.
function stream_event_order($event)
{
	switch ($event)
	{
		case 'start':
			return Game::gametime_to_int(GAMETIME_START);
		case 'arrangement':
			return Game::gametime_to_int(GAMETIME_ARRANGEMENT);
		case 'relaxed sitting':
			return Game::gametime_to_int(GAMETIME_RELAXED_SITTING);
		case 'night':
			return Game::gametime_to_int(GAMETIME_NIGHT_START);
		case 'shooting':
			return Game::gametime_to_int(GAMETIME_SHOOTING);
		case 'don':
			return Game::gametime_to_int(GAMETIME_DON);
		case 'sheriff':
			return Game::gametime_to_int(GAMETIME_SHERIFF);
		case 'day':
			return Game::gametime_to_int(GAMETIME_DAY_START);
		case 'voting':
			return Game::gametime_to_int(GAMETIME_VOTING_START);
		case 'end':
			return Game::gametime_to_int(GAMETIME_END);
	}
	return -1;
}

// The phases of a game a scene can be asked for in, in the order they are offered, as the keys
// stream_game_scene() matches a game against. The first is "no phase", which is what a scene
// that has not been given one yet stands at. The ten numbers are the seats: a scene against a
// seat is shown while that seat is speaking.
function stream_game_events()
{
	$events = array(
		'' => '',
		'day' => get_label('In the daytime'),
		'night' => get_label('In the nighttime'),
		'start' => get_label('During roles distribution'),
		'end' => get_label('On game end'),
		'arrangement' => get_label('During arrangement'),
		'relaxed sitting' => get_label('During relaxed sitting'));
	for ($i = 1; $i <= 10; ++$i)
	{
		$events[(string)$i] = get_label('On player [0] speach', $i);
	}
	$events['voting'] = get_label('During voting');
	$events['shooting'] = get_label('During shooting');
	$events['don'] = get_label('During don\'s check');
	$events['sheriff'] = get_label('During sheriff\'s check');
	return $events;
}

// What is stored against the phases, read back as a list of scene and event pairs. Anything
// that is not a pair of strings, or names a phase we do not know, is dropped rather than
// handed on; a table that was set up when we offered something else should not be able to
// confuse what reads it.
//
// A phase belongs to one row and no more. Two rows naming the same one would be two answers
// to the same question with nothing to say which is meant, so the second is dropped here as
// well as being kept off the page. A row with no phase yet is in nobody's way.
function stream_parse_scene_events($json)
{
	$events = array();
	$stored = is_null($json) ? NULL : json_decode($json);
	if (!is_array($stored))
	{
		return $events;
	}

	$known = stream_game_events();
	$taken = array();
	foreach ($stored as $pair)
	{
		if (!is_object($pair) || !isset($pair->scene) || !isset($pair->event))
		{
			continue;
		}
		if (!is_string($pair->scene) || !is_string($pair->event) || !isset($known[$pair->event]))
		{
			continue;
		}
		if ($pair->event != '')
		{
			if (isset($taken[$pair->event]))
			{
				continue;
			}
			$taken[$pair->event] = true;
		}

		$event = new stdClass();
		$event->scene = $pair->scene;
		$event->event = $pair->event;
		$events[] = $event;
	}
	return $events;
}

// The stored list, as names we know. Anything else in it is dropped rather than taken for a
// screen, and a name repeated is kept once.
function stream_parse_screens($screens)
{
	$all = stream_screens();
	$wanted = array();
	foreach (preg_split('/[\s,;]+/', strtolower(trim($screens))) as $name)
	{
		if (isset($all[$name]) && !in_array($name, $wanted))
		{
			$wanted[] = $name;
		}
	}
	return $wanted;
}

?>
