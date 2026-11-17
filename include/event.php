<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/address.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/stream.php';
require_once __DIR__ . '/city.php';
require_once __DIR__ . '/country.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/currency.php';
require_once __DIR__ . '/table_hiding.php';

define('WEEK_FLAG_SUN', 1);
define('WEEK_FLAG_MON', 2);
define('WEEK_FLAG_TUE', 4);
define('WEEK_FLAG_WED', 8);
define('WEEK_FLAG_THU', 16);
define('WEEK_FLAG_FRI', 32);
define('WEEK_FLAG_SAT', 64);
define('WEEK_FLAG_ALL', 127);

define('BRIEF_ATTENDANCE', true);

// What to tell somebody who asked for an event longer than EVENT_MAX_DURATION. An event of its own
// that long is not a sitting but a season, and a season is a long-term tournament. A round has
// nowhere else to go, so it is only told the limit.
function event_too_long_message($is_round)
{
	if ($is_round)
	{
		return get_label('A round can not last longer than [0] days.', EVENT_MAX_DURATION_DAYS);
	}
	return get_label('An event can not last longer than [0] days. Create a long-term tournament for a longer one.', EVENT_MAX_DURATION_DAYS);
}

// Whether an event belonging to this tournament is a round of it. A long-term tournament has no
// rounds: what hangs on it are the events of the club's ordinary days, which are events and are
// spoken of as events. $tournament_id is NULL or 0 for an event of its own.
function is_round_of_tournament($tournament_id)
{
	if (is_null($tournament_id) || (int)$tournament_id <= 0)
	{
		return false;
	}
	list ($tournament_flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $tournament_id);
	return ($tournament_flags & TOURNAMENT_FLAG_LONG_TERM) == 0;
}

// Whether a duration somebody asked for is allowed. The limit holds for what is being set now, but
// an event that is already longer is not broken by it: it can be kept as it is or shortened, just
// never grown. $old_duration is 0 for an event that does not exist yet.
function is_event_duration_allowed($duration, $old_duration = 0)
{
	return $duration <= EVENT_MAX_DURATION || $duration <= $old_duration;
}

function show_event_buttons($id, $tournament_id, $start_time, $duration, $flags, $club_id, $club_flags, $attending, $is_manager = NULL, $is_referee = NULL, $event_page = false)
{
	global $_profile;

	$now = time();
	if ($is_manager === NULL)
	{
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $id, $tournament_id);
	}
	if ($is_referee === NULL)
	{
		$is_referee = is_permitted(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $id, $tournament_id);
	}
	
	$no_buttons = true;
	if ($_profile != NULL && $id > 0 && ($club_flags & CLUB_FLAG_CLOSED) == 0)
	{
		$can_manage = false;
		
		if (($flags & EVENT_FLAG_CANCELED) == 0 && $start_time + $duration > $now)
		{
			if ($attending)
			{
				echo '<button class="icon" onclick="mr.attendEvent(' . $id . ')" title="' . get_label('Attend/decline the event') . '"><img src="images/accept.png" border="0"></button>';
			}
			else
			{
				echo '<button class="icon" onclick="mr.attendEvent(' . $id . ')" title="' . get_label('Attend/decline the event') . '"><img src="images/empty.png" border="0"></button>';
			}
			$no_buttons = false;
		}
		
		if ($is_manager)
		{
			if ($event_page)
			{
				$back_url = get_back_page();
				if (empty($back_url))
				{
					$back_url = 'events.php';
				}
				$back_url = '\''.$back_url.'\'';
			}
			else
			{
				$back_url = 'undefined';
			}
			
			echo '<button class="icon" onclick="mr.editEvent(' . $id . ')" title="' . get_label('Edit the event') . '"><img src="images/edit.png" border="0"></button>';
			if (($flags & EVENT_FLAG_CANCELED) != 0)
			{
				echo '<button class="icon" onclick="mr.restoreEvent(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
			} 
			echo '<button class="icon" onclick="mr.deleteEvent(' . $id . ', ' . $back_url . ')" title="' . get_label('Delete the event') . '"><img src="images/delete.png" border="0"></button>';
			$no_buttons = false;
		}
		if ($is_referee && $start_time < $now && $start_time + $duration + EVENT_ALIVE_TIME >= $now)
		{
			echo '<button class="icon" onclick="mr.extendEvent(' . $id . ')" title="' . get_label('Event flow. Finish event, or extend event.') . '"><img src="images/time.png" border="0"></button>';
			if ($start_time + $duration >= $now)
			{
				echo '<button class="icon" onclick="goTo(\'game.php\', {event_id: ' . $id . ',bck:0})" title="' . get_label('Play the game') . '"><img src="images/game.png" border="0"></button>';
			}
			$no_buttons = false;
		}
	}
	echo '<button class="icon" onclick="window.open(\'event_screen.php?id=' . $id . '\' ,\'_blank\')" title="' . get_label('Open interactive standings page') . '"><img src="images/details.png" border="0"></button>';
}

function event_late_str($late)
{
	if ($late < 0)
	{
		$result .= get_label('; very late');
	}

	$hour = floor($late / 60);
	$minutes = $late % 60;
	if ($hour == 0)
	{
		return get_label('late [0] min', $minutes);
	}
	else if ($minutes == 0)
	{
		return get_label('late [0] hr', $hour);
	}
	return get_label('late [0] hr [1] min', $hour, $minutes);
}
	
function event_odds_str($odds, $bringing, $late)
{
	if ($odds <= 0)
	{
		return get_label('not coming');
	}
	
	$result = get_label('[0]%', $odds);
	if ($bringing > 1)
	{
		$result .= get_label('; plus [0] friends', $bringing);
	}
	else if ($bringing == 1)
	{
		$result .= get_label('; plus 1 friend');
	}
	if ($late != 0)
	{
		$result .= '; ' . event_late_str($late);
	}
	return $result;
}

function event_tags()
{
	return array(
		array('[accept_btn=' . get_label('Coming') . ']', get_label('Accept button')),
		array('[decline_btn=' . get_label('Not coming') . ']', get_label('Decline button')),
		array('[accept]' . get_label('Coming') . '[/accept]', get_label('Accept link')),
		array('[decline]' . get_label('Not coming') . '[/decline]', get_label('Decline link')),
		array('[event_name]', get_label('Event name')),
		array('[event_id]', get_label('Event id')),
		array('[event_date]', get_label('Event date')),
		array('[event_time]', get_label('Event time')),
		array('[address]', get_label('Event address')),
		array('[address_url]', get_label('Event address URL')),
		array('[address_id]', get_label('Event address id')),
		array('[address_image]', get_label('Event address image')),
		array('[notes]', get_label('Event notes')),
		array('[langs]', get_label('Event languages')),
		array('[user_name]', get_label('User name')),
		array('[user_id]', get_label('User id')),
		array('[email]', get_label('User email')),
		array('[score]', get_label('User score')),
		array('[club_name]', get_label('Club name')),
		array('[club_id]', get_label('Club id')),
		array('[code]', get_label('Email code')));
}

function get_current_event_id($perm_flags)
{
	global $_profile;

	$query = new DbQuery('SELECT id FROM events WHERE UNIX_TIMESTAMP() >= start_time AND UNIX_TIMESTAMP() <= start_time + duration AND club_id IN (' . $_profile->get_comma_sep_clubs($perm_flags) . ') AND (flags & ' . EVENT_FLAG_CANCELED .  ') = 0 LIMIT 1');
	if ($row = $query->next())
	{
		return $row[0];
	}
	foreach ($_profile->clubs as $club)
	{
		if (($club->flags & $perm_flags) != 0)
		{
			return -$club->id;
		}
	}
	return 0;
}

function show_event_selector($event_id, $form_name, $select_name, $perm_flags, $current_only = false)
{
	global $_profile, $_lang;

	$clubs_count = $_profile->get_clubs_count($perm_flags);

	$sql = 'SELECT e.id, ne.name, e.start_time, e.duration, ct.timezone, nc.name FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' WHERE UNIX_TIMESTAMP() <= e.start_time + e.duration AND e.club_id IN (' . $_profile->get_comma_sep_clubs($perm_flags) .
			') AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0';
			
	if ($current_only)
	{
		$sql .= ' AND UNIX_TIMESTAMP() >= e.start_time';
	}
	else
	{
		$sql .= ' AND UNIX_TIMESTAMP() + 604800 >= e.start_time'; 
	}
	$sql .= ' ORDER BY e.start_time';
	
	$query = new DbQuery($sql);
	
	echo '<select name="' . $select_name . '" onChange="document.' . $form_name . '.submit()">';
	foreach ($_profile->clubs as $club)
	{
		if (($club->flags & $perm_flags) != 0)
		{
			echo '<option value="' . (-$club->id) . '"';
			if (-$club->id == $event_id)
			{
				echo ' selected';
			}
			if ($clubs_count > 1)
			{
				echo '>' . get_label('[No event at [0]]', $club->name) . '</option>';
			}
			else
			{
				echo '>' . get_label('[No event]') . '</option>';
			}
		}
	}
	
	$now = time();
	while ($row = $query->next())
	{
		list($e_id, $event_name, $event_start_time, $event_duration, $event_timezone, $club_name) = $row;
		
		echo '<option value="' . $e_id . '"';
		if ($e_id == $event_id)
		{
			echo ' selected';
		}
		if ($clubs_count > 1)
		{
			echo '>' . get_label('[0]: [1] at [2]', $event_name, format_date($event_start_time, $event_timezone, true), $club_name) . '</option>';
		}
		else
		{
			echo '>' . get_label('[0]: [1]', $event_name, format_date($event_start_time, $event_timezone, true)) . '</option>';
		}
	}
	echo '</select>';
}

	
function get_event_reg_array($event_id)
{
	global $_lang;
	
	$conflict_exists = false;
	$by_name = array();
	$regs = array();
	$query = new DbQuery(
		'SELECT u.id, nu.name, nc.name FROM event_regs eu'.
		' JOIN users u ON u.id = eu.user_id'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' JOIN cities c ON c.id = u.city_id'.
		' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
		' WHERE eu.event_id = ?'.
		' ORDER BY nu.name', $event_id);
	while ($row = $query->next())
	{
		$reg = new stdClass();
		list ($reg->id, $reg->name, $reg->city) = $row;
		$reg->id = (int)$reg->id;
		$regs[] = $reg;
		if (isset($by_name[$reg->name]))
		{
			$reg->next = $by_name[$reg->name];
			$conflict_exists = true;
		}
		else
		{
			$reg->next = NULL;
		}
		$by_name[$reg->name] = $reg;
	}
	
	if ($conflict_exists)
	{
		foreach ($by_name as $n => $r)
		{
			if ($r->next)
			{
				do
				{
					$r->name .= ', ' . $r->city;
					$r = $r->next;
				} 
				while ($r);
			}
		}
	}
	
	foreach ($regs as $reg)
	{
		unset($reg->next);
		unset($reg->city);
	}
	return $regs;
}

// Registers on the event every player a seating names, skipping anyone already registered.
//
// A seating reaches the event as slots plus a mapping of slot to user, and the games read their
// players straight out of it. The game screen, though, builds each seat's dropdown out of the
// event's registrations and then selects the seated player in it - so a player the seating names
// but the event does not know about has no option to select, and the seat shows up empty. The
// moderator then types in a seating that was already there. It is also why it used to start
// working from the second game on: entering the first one by hand registers those players.
//
// Entries are user ids, or objects carrying an id - both forms occur in misc.
// Returns how many registrations were added.
function register_seating_players($event_id, $mapping)
{
	$event_id = (int)$event_id;
	$added = 0;
	foreach ((array)$mapping as $entry)
	{
		$user_id = is_object($entry) ? (isset($entry->id) ? (int)$entry->id : 0) : (int)$entry;
		if ($user_id <= 0)
		{
			continue;
		}

		// INSERT IGNORE instead of looking first: (event_id, user_id) is the primary key, so the
		// insert is the check, and an existing registration keeps the flags and nickname it has.
		Db::exec(get_label('registration'),
			'INSERT IGNORE INTO event_regs (event_id, user_id, flags) VALUES (?, ?, ?)',
			$event_id, $user_id, USER_PERM_PLAYER | USER_EVENT_NEW_PLAYER_FLAGS);
		$added += Db::affected_rows();
	}
	return $added;
}

// The referees of a tournament, in the order they are registered in. That order is the one the
// preparation page shows and lets a manager change with its "move up" buttons, and it is what
// decides the tables: the first referee takes table 1, the second one table 2, and so on.
function get_tournament_referees($tournament_id)
{
	global $_lang;

	$referees = array();
	$query = new DbQuery(
		'SELECT tr.user_id FROM tournament_regs tr'.
		' JOIN users u ON u.id = tr.user_id'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE tr.tournament_id = ? AND (tr.flags & ?) <> 0 AND (tr.flags & ?) = 0'.
		' ORDER BY tr.reg_order, nu.name',
		$tournament_id, USER_PERM_REFEREE, USER_TOURNAMENT_FLAG_NOT_ACCEPTED);
	while ($row = $query->next())
	{
		list ($user_id) = $row;
		$referees[] = (int)$user_id;
	}
	return $referees;
}

// A tournament keeps its referees on the tournament, not on each of its rounds, but the game
// screen builds its moderator dropdown out of the event registrations - so a referee the event
// does not know about cannot be picked and has to be typed in by hand. This brings them onto
// the event. Returns them in registration order, see get_tournament_referees().
function register_tournament_referees($event_id, $tournament_id)
{
	$event_id = (int)$event_id;
	$referees = get_tournament_referees($tournament_id);
	foreach ($referees as $user_id)
	{
		// INSERT IGNORE instead of looking first: (event_id, user_id) is the primary key, so the
		// insert is the check, and an existing registration keeps the flags and nickname it has.
		Db::exec(get_label('registration'),
			'INSERT IGNORE INTO event_regs (event_id, user_id, flags) VALUES (?, ?, ?)',
			$event_id, $user_id, USER_PERM_REFEREE);
	}
	return $referees;
}

// The other way about: somebody has been put down for a round of a tournament, so the
// tournament is told about them too. A name that appears on one round and nowhere else is a
// name the tournament never agreed to, and it would be missing from the one list anybody
// looks at.
//
// On what terms depends on who did it. Entered by a referee or a manager, they are entered on
// the tournament the same way, with the same roles - those people are already trusted to say
// who plays. Entered by themselves, they are entered as an application instead: it grants
// nothing at all, it only puts them in front of the tournament's management with the button
// to accept them, so that somebody who put their name down is not quietly lost.
//
// Somebody the tournament already knows is left exactly as they are, accepted or not: this is
// not the place to overrule terms that were settled elsewhere. And taking a name off a round
// never takes it off the tournament - a player sitting out one round is still in it.
function register_event_user_in_tournament($tournament_id, $user_id, $club_id, $flags, $accepted)
{
	$tournament_id = (int)$tournament_id;
	$user_id = (int)$user_id;
	if ($tournament_id <= 0 || $user_id <= 0)
	{
		return false;
	}

	list ($count) = Db::record(get_label('registration'),
		'SELECT count(*) FROM tournament_regs WHERE tournament_id = ? AND user_id = ?', $tournament_id, $user_id);
	if ((int)$count > 0)
	{
		return false;
	}

	$flags = (int)$flags & USER_PERM_MASK;
	if ($flags == 0)
	{
		$flags = USER_PERM_PLAYER;
	}
	if (!$accepted)
	{
		$flags |= USER_TOURNAMENT_FLAG_NOT_ACCEPTED;
	}

	list ($city_id, $rating) = Db::record(get_label('user'), 'SELECT city_id, rating FROM users WHERE id = ?', $user_id);
	list ($reg_order) = Db::record(get_label('registration'),
		'SELECT COALESCE(MAX(reg_order), 0) FROM tournament_regs WHERE tournament_id = ?', $tournament_id);
	Db::exec(get_label('registration'),
		'INSERT INTO tournament_regs (tournament_id, user_id, flags, city_id, rating, reg_order) VALUES (?, ?, ?, ?, ?, ?)',
		$tournament_id, $user_id, $flags, $city_id, $rating, (int)$reg_order + 1);

	$log_details = new stdClass();
	$log_details->tournament_id = $tournament_id;
	$log_details->through_round = true;
	$log_details->accepted = $accepted ? 1 : 0;
	db_log(LOG_OBJECT_USER, 'joined tournament', $log_details, $user_id, $club_id);
	return true;
}

class EventPageBase extends PageBase
{
	protected $event;
	protected $is_manager;
	
	protected function get_full_name()
	{
		if (!is_null($this->tournament_id))
		{
			return $this->tournament_name . ' : ' . $this->name;
		}
		return $this->name;
	}
	
	protected function prepare()
	{
		global $_profile, $_lang;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		$this->id = (int)$_REQUEST['id'];
		
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
		
		list (
			$this->name, $this->fee, $this->currency_id, $this->currency_pattern, $this->club_id, $this->club_name, $this->club_flags, $this->club_url, $this->start_time, $this->duration,
			$this->tournament_id, $this->tournament_name, $this->tournament_flags,
			$this->address_id, $this->address, $this->address_url, $this->timezone, $this->address_flags,
			$this->notes, $this->langs, $this->flags, $this->rules_code, $this->scoring_id, $this->scoring_version, $this->scoring_options, 
			$this->coming_odds, $this->city, $this->country, $this->round_num) =
				Db::record(
					get_label('event'), 
					'SELECT ne.name, e.fee, e.currency_id, cu.pattern, c.id, nc.name, c.flags, c.web_site, e.start_time, e.duration,'.
							' t.id, nt.name, t.flags,'.
							' a.id, a.address, a.map_url, i.timezone, a.flags,'.
							' e.notes, e.languages, e.flags, e.rules, e.scoring_id, e.scoring_version, e.scoring_options,'.
							' u.coming_odds, ni.name, no.name, e.round FROM events e' .
						' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
						' JOIN addresses a ON e.address_id = a.id' .
						' JOIN clubs c ON e.club_id = c.id' .
						' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
						' JOIN cities i ON a.city_id = i.id' .
						' JOIN countries o ON i.country_id = o.id' .
						' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
						' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
						' LEFT OUTER JOIN event_regs u ON u.event_id = e.id AND u.user_id = ?' .
						' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
						' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
						' LEFT OUTER JOIN currencies cu ON e.currency_id = cu.id' .
						' WHERE e.id = ?',
					$user_id, $this->id);
					
		list ($this->broadcasts) = Db::record(get_label('event'), 'SELECT count(*) FROM event_broadcasts WHERE event_id = ?', $this->id);

		// And it can be followed in words whether or not anybody is streaming it - see
		// text_broadcast.php.
		$this->text_broadcasts = stream_is_live($this->start_time,
			($this->flags & EVENT_FLAG_FINISHED) != 0, ($this->flags & EVENT_FLAG_CANCELED) != 0);
		
		date_default_timezone_set($this->timezone);
		$this->day = date('j', $this->start_time);
		$this->month = date('n', $this->start_time);
		$this->year = date('Y', $this->start_time);
		$this->hour = date('G', $this->start_time);
		$this->minute = round(date('i', $this->start_time) / 10) * 10;
		
		$this->is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id, $this->tournament_id);
		$this->is_referee = is_permitted(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id, $this->tournament_id);
		
		if (($this->is_manager || $this->is_referee) && isset($_REQUEST['show_all']))
		{
			$this->show_all = '&show_all';
			$this->tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
		}
		else
		{
			$this->show_all = '';
		}
	}
	
	protected function show_title()
	{

		$menu = array
		(
			new MenuItem('event_info.php?id=' . $this->id, get_label('Event'), get_label('General event information')),
			new MenuItem('event_standings.php?id=' . $this->id, get_label('Standings'), get_label('Event standings')),
			new MenuItem('event_games.php?id=' . $this->id, get_label('Games'), get_label('Games list of the event')),
			new MenuItem('#stats', get_label('Reports'), NULL, array
			(
				new MenuItem('event_stats.php?id=' . $this->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.')),
				new MenuItem('event_by_numbers.php?id=' . $this->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.')),
				new MenuItem('event_nominations.php?id=' . $this->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.')),
				new MenuItem('event_referees.php?id=' . $this->id, get_label('Referees'), get_label('Referees statistics of [0]', $this->name)),
				new MenuItem('event_competition.php?id=' . $this->id, get_label('Competition chart'), get_label('How players were competing on this event.')),
			)),
			new MenuItem('#resources', get_label('Resources'), NULL, array
			(
				new MenuItem('event_rules.php?id=' . $this->id, get_label('Rulebook'), get_label('Rules of the game in [0]', $this->name)),
				new MenuItem('event_albums.php?id=' . $this->id, get_label('Photos'), get_label('Event photo albums')),
				new MenuItem('event_videos.php?id=' . $this->id, get_label('Videos'), get_label('Videos from the event.')),
				// new MenuItem('event_tasks.php?id=' . $this->id, get_label('Tasks'), get_label('Learning tasks and puzzles.')),
				// new MenuItem('event_articles.php?id=' . $this->id, get_label('Articles'), get_label('Books and articles.')),
				// new MenuItem('event_links.php?id=' . $this->id, get_label('Links'), get_label('Links to custom mafia web sites.')),
			)),
		);
		// What there is to watch belongs with the rest of what there is to watch: between the
		// standings and the games, rather than away under Resources where it was.
		if ($this->broadcasts > 0 || $this->text_broadcasts)
		{
			array_splice($menu, 2, 0, array(new MenuItem('event_broadcasts.php?id=' . $this->id, get_label('Broadcasts'), get_label('Event broadcasts.'))));
		}
		if ($this->is_manager || $this->is_referee)
		{
			$manager_menu = array();
			
			$manager_menu[] = new MenuItem('event_preparation.php?id=' . $this->id, get_label('Preparation'), get_label('Getting [0] ready: how many play, who is coming, where it is streamed from and who sits where.', $this->name));
			$manager_menu[] = new MenuItem('event_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name));
			// A round of a tournament is set up through its tournament, where one set of
			// settings covers the table for every round of it - so the question asked here is
			// the one that page will ask, and a referee of this round alone is not offered a
			// page that would turn them away.
			if (is_null($this->tournament_id)
				? is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE, $this->club_id, $this->id)
				: is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->tournament_id))
			{
				$manager_menu[] = new MenuItem(null, null, null);
				// Called by what it is about rather than by what the page is called - see the
				// same entry in include/tournament.php. The page keeps its own title.
				//
				// Opened on this round and not on the tournament it belongs to, even though the
				// settings themselves belong to the tournament: that page works the owner out
				// for itself, and what it lists at the foot of a tab - the recordings a table is
				// streamed to - is then this round's rather than every round the tournament has
				// ever played. Coming from a round and being shown all of them is being shown
				// somebody else's page.
				$manager_menu[] = new MenuItem(
					'streams.php?event_id=' . $this->id,
					get_label('Broadcasts'),
					get_label('Set up everything a stream needs at each table: the addresses to point OBS Studio at, which scene goes with which phase of the game, and what is shown between games.'));
			}
			
			if ($this->is_manager && is_null($this->tournament_id))
			{
				// Turning an event into a tournament is not one of the things above it: those
				// set this event up, and this one makes it something else. A line says so.
				$manager_menu[] = new MenuItem(null, null, null);
				$manager_menu[] = new MenuItem('javascript:mr.convertEventToTournament(' . $this->id . ')', get_label('Convert to tournament'), get_label('Convert [0] to a tournament.', $this->name));
			}
			$menu[] = new MenuItem('#management', get_label('Management'), NULL, $manager_menu);
		}

		// A round of a tournament is found through its tournament, an event on its own through the
		// list of events.
		if (is_null($this->tournament_id))
		{
			$h = new EntityHeader($this->name, get_label('Events'), 'events.php');
		}
		else
		{
			$h = new EntityHeader($this->name, $this->tournament_name, 'tournament_info.php?bck=1&id=' . $this->tournament_id);
		}
		$h->off = ($this->flags & EVENT_FLAG_CANCELED) != 0;
		if ($h->off)
		{
			$h->sub = '<span class="chip off">' . get_label('canceled') . '</span>';
		}

		$event_pic = new Picture(EVENT_PICTURE, new Picture(ADDRESS_PICTURE));
		$event_pic->
			set($this->id, $this->name, $this->flags)->
			set($this->address_id, $this->address, $this->address_flags);
		$h->art = PageBase::capture(function() use ($event_pic) { $event_pic->show(TNAILS_DIR, false); });
		if ($this->address_url != '')
		{
			$h->art = '<a href="address_info.php?bck=1&id=' . $this->address_id . '">' . $h->art . '</a>';
		}
		$h->actions = PageBase::capture(function()
		{
			show_event_buttons(
				$this->id,
				$this->tournament_id,
				$this->start_time,
				$this->duration,
				$this->flags,
				$this->club_id,
				$this->club_flags,
				$this->coming_odds != NULL && $this->coming_odds > 0,
				$this->is_manager, $this->is_referee, true);
		});

		$h->fact('cal', format_date($this->start_time, $this->timezone, true));
		$h->fact('pin', '<span><a href="address_info.php?bck=1&id=' . $this->address_id . '">' . trim($this->address) . '</a>' . (trim($this->address) == $this->city ? '' : ', ' . $this->city) . '</span>');
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$h->fact('users', PageBase::entity_link($this->club_pic, $this->club_name));
		if (!is_null($this->tournament_id))
		{
			$tournament_pic = new Picture(TOURNAMENT_PICTURE);
			$tournament_pic->set($this->tournament_id, $this->tournament_name, $this->tournament_flags);
			$h->fact('trophy', PageBase::entity_link($tournament_pic, $this->tournament_name));
		}
		if (!is_null($this->currency_pattern) && !is_null($this->fee) && $this->fee > 0)
		{
			$h->fact('coin', get_label('Admission rate') . ': ' . format_currency($this->fee, $this->currency_pattern));
		}

		$this->show_entity_header($h, $menu);
	}
	
	// What the tournament hides of this round - see include/table_hiding.php. A round hidden as a
	// whole has an empty table. A round hidden from one of its tours shows the tours before it,
	// and says that is what it shows once a tour after them has been played.
	protected function show_hidden_table_message()
	{
		$result = true;
		$text = NULL;
		$table_hiding = table_hiding($this->tournament_id, $this->tournament_flags);
		$bonus_hiding = bonus_hiding($this->tournament_id, $this->tournament_flags);
		if (is_round_hidden($table_hiding, $this->round_num))
		{
			$result = false;
			$text = get_label('Tournament tables are hidden for this round until the tournament ends.');
		}
		else
		{
			$notes = array();
			if (is_event_hiding_in_effect($table_hiding, $this->id))
			{
				$notes[] = hidden_table_message($table_hiding);
			}
			if (is_round_hidden($bonus_hiding, $this->round_num))
			{
				$notes[] = get_label('Bonus points are hidden for this round until the tournament ends.');
			}
			else if (is_event_hiding_in_effect($bonus_hiding, $this->id))
			{
				$notes[] = hidden_bonus_message($bonus_hiding);
			}
			if (!empty($notes))
			{
				$text = implode('<br>', $notes);
			}
		}

		if (!is_null($text))
		{
			echo '<p><table class="transp" width="100%"><tr><td width="32">';
			if ($this->is_manager || $this->is_referee)
			{
				echo '<button onclick="goTo({show_all: null})" title="' . get_label('Show the actual scoring tables.') . '"><img src="images/attention.png"></button>';
			}
			else
			{
				echo '<img src="images/attention.png">';
			}
			echo '</td><td><h3>' . $text . '</h3></td></tr></table></p>';
		}
		return $result;
	}
}
	
?>
