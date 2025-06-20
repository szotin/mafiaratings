<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/address.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/city.php';
require_once __DIR__ . '/country.php';
require_once __DIR__ . '/user.php';
require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/currency.php';

define('WEEK_FLAG_SUN', 1);
define('WEEK_FLAG_MON', 2);
define('WEEK_FLAG_TUE', 4);
define('WEEK_FLAG_WED', 8);
define('WEEK_FLAG_THU', 16);
define('WEEK_FLAG_FRI', 32);
define('WEEK_FLAG_SAT', 64);
define('WEEK_FLAG_ALL', 127);

define('BRIEF_ATTENDANCE', true);

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
	if ($_profile != NULL && $id > 0 && ($club_flags & CLUB_FLAG_RETIRED) == 0)
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
		array('[unsub_btn=' . get_label('Unsubscribe') . ']', get_label('Unsubscribe button')),
		array('[accept]' . get_label('Coming') . '[/accept]', get_label('Accept link')),
		array('[decline]' . get_label('Not coming') . '[/decline]', get_label('Decline link')),
		array('[unsub]' . get_label('Unsubscribe') . '[/unsub]', get_label('Unsubscribe link')),
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
	global $_profile;
	
	$clubs_count = $_profile->get_clubs_count($perm_flags);

	$sql = 'SELECT e.id, e.name, e.start_time, e.duration, ct.timezone, c.name FROM events e' . 
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
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
		'SELECT u.id, nu.name, nc.name FROM event_users eu'.
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
					'SELECT e.name, e.fee, e.currency_id, cu.pattern, c.id, c.name, c.flags, c.web_site, e.start_time, e.duration,'.
							' t.id, t.name, t.flags,'.
							' a.id, a.address, a.map_url, i.timezone, a.flags,'.
							' e.notes, e.languages, e.flags, e.rules, e.scoring_id, e.scoring_version, e.scoring_options,'.
							' u.coming_odds, ni.name, no.name, e.round FROM events e' .
						' JOIN addresses a ON e.address_id = a.id' .
						' JOIN clubs c ON e.club_id = c.id' .
						' JOIN cities i ON a.city_id = i.id' .
						' JOIN countries o ON i.country_id = o.id' .
						' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
						' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
						' LEFT OUTER JOIN event_users u ON u.event_id = e.id AND u.user_id = ?' .
						' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
						' LEFT OUTER JOIN currencies cu ON e.currency_id = cu.id' .
						' WHERE e.id = ?',
					$user_id, $this->id);
					
		list ($this->broadcasts) = Db::record(get_label('event'), 'SELECT count(*) FROM event_broadcasts WHERE event_id = ?', $this->id);
		
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
		echo '<table class="head" width="100%">';

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
		if ($this->broadcasts > 0)
		{
			$menu[count($menu)-1]->submenu[] = new MenuItem('event_broadcasts.php?id=' . $this->id, get_label('Broadcasts'), get_label('Event broadcasts.'));
		}
		if ($this->is_manager || $this->is_referee)
		{
			$manager_menu = array();
			
			$manager_menu[] = new MenuItem('event_users.php?id=' . $this->id, get_label('Registrations'), get_label('Manage registrations for [0]', $this->name));
			if ($this->is_manager)
			{
				$manager_menu[] = new MenuItem('event_mailings.php?id=' . $this->id, get_label('Mailing'), get_label('Manage sending emails for [0]', $this->name));
			}
			$manager_menu[] = new MenuItem('event_extra_points.php?id=' . $this->id, get_label('Extra points'), get_label('Add/remove extra points for players of [0]', $this->name));
			$manager_menu[] = new MenuItem('game_obs.php?bck=1&user_id=0&event_id=' . $this->id, get_label('OBS Studio integration'), get_label('Instructions how to add game informaton to OBS Studio.'));
			$manager_menu[] = new MenuItem('event_broadcasts_edit.php?id=' . $this->id, get_label('Broadcasts'), get_label('Add/remove youtube/twitch broadcasts'));
			
			if ($this->is_manager && is_null($this->tournament_id))
			{
				$manager_menu[] = new MenuItem('javascript:mr.convertEventToTournament(' . $this->id . ', \'' . get_label('Are you sure you want to convert [0] to a tournament?', $this->name) . '\')', get_label('Convert to tournament'), get_label('Convert [0] to a tournament.', $this->name));				
			}
			$menu[] = new MenuItem('#management', get_label('Management'), NULL, $manager_menu);
		}
		
		echo '<tr><td colspan="4">';
		PageBase::show_menu($menu);
		echo '</td></tr>';
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->flags & EVENT_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
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
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(ADDRESS_PICTURE));
		$event_pic->
			set($this->id, $this->name, $this->flags)->
			set($this->address_id, $this->address, $this->address_flags);
		if ($this->address_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->address_id . '">';
			$event_pic->show(TNAILS_DIR, false);
			echo '</a>';
		}
		else
		{
			$event_pic->show(TNAILS_DIR, false);
		}
		echo '</td></tr></table></td>';
		
		echo '<td valign="top"><h2 class="event">' . $this->get_full_name() . '</h2><br><h3>' . $this->_title;
		$time = time();
		echo '</h3><p class="subtitle">' . format_date($this->start_time, $this->timezone, true) . '</p>';
		if (!is_null($this->currency_pattern) && !is_null($this->fee))
		{
			echo '<p class="subtitle"><b>'.get_label('Admission rate').': '.format_currency($this->fee, $this->currency_pattern).'</b></p>';
		}
		echo '</td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td style="padding: 0px 0px 0px 20px;">';
		if ($this->broadcasts > 0)
		{
			echo '<a href="event_broadcasts.php?id=' . $this->id . '&bck=1" title="' . get_label('[0] broadcasts', $this->name) . '"><img src="images/broadcast.png" width="48"></a>';
		}
		echo '</td><td align="right" valign="bottom"><table><tr><td align="center">';
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$this->club_pic->show(ICONS_DIR, true, 48);
		if (!is_null($this->tournament_id))
		{
			$tournament_pic = new Picture(TOURNAMENT_PICTURE);
			$tournament_pic->set($this->tournament_id, $this->tournament_name, $this->tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 48);
		}
		echo '</td></tr></table></td></tr>';
		
		echo '</table>';
	}
	
	protected function should_hide_table()
	{
		if ($this->tournament_flags & TOURNAMENT_FLAG_FINISHED)
		{
			return false;
		}

		switch (($this->tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
		{
			case 1:
				return true;
			case 2:
				return $this->round_num == 1;
			case 3:
				return $this->round_num == 1 || $this->round_num == 2;
		}
		return false;
	}
	
	protected function should_hide_bonus()
	{
		if ($this->tournament_flags & TOURNAMENT_FLAG_FINISHED)
		{
			return false;
		}

		switch (($this->tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
		{
			case 1:
				return true;
			case 2:
				return $this->round_num == 1;
			case 3:
				return $this->round_num == 1 || $this->round_num == 2;
		}
		return false;
	}
	
	protected function show_hidden_table_message()
	{
		$result = true;
		$text = NULL;
		if ($this->should_hide_table())
		{
			$result = false;
			$text = get_label('Tournament tables are hidden for this round until the tournament ends.');
		}
		else if ($this->should_hide_bonus())
		{
			$text = get_label('Bonus points are hidden for this round until the tournament ends.');
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