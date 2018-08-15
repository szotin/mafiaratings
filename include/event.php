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

define('WEEK_FLAG_SUN', 1);
define('WEEK_FLAG_MON', 2);
define('WEEK_FLAG_TUE', 4);
define('WEEK_FLAG_WED', 8);
define('WEEK_FLAG_THU', 16);
define('WEEK_FLAG_FRI', 32);
define('WEEK_FLAG_SAT', 64);
define('WEEK_FLAG_ALL', 127);

define('BRIEF_ATTENDANCE', true);

function show_event_pic($id, $name, $flags, $alt_id, $alt_name, $alt_flags, $dir, $width = 0, $height = 0, $alt_addr = true)
{
	global $_lang_code;

	$w = $width;
	$h = $height;
	if ($dir == ICONS_DIR)
	{
		if ($w <= 0)
		{
			$w = ICON_WIDTH;
		}
		if ($h <= 0)
		{
			$h = ICON_HEIGHT;
		}
	}
	else if ($dir == TNAILS_DIR)
	{
		if ($w <= 0)
		{
			$w = TNAIL_WIDTH;
		}
		if ($h <= 0)
		{
			$h = TNAIL_HEIGHT;
		}
	}
	
	if ($width <= 0 && $height <= 0)
	{
		$width = $w;
		$height = $h;
	}
	
	$origin = EVENT_PICS_DIR . $dir . $id . '.png';
	echo '<span style="position:relative;"><img code="' . EVENT_PIC_CODE . $id . '" origin="' . $origin . '" src="';
	if ($flags & EVENT_ICON_MASK)
	{
		echo $origin . '?' . (($flags & EVENT_ICON_MASK) >> EVENT_ICON_MASK_OFFSET);
		$title = $name;
		if (!$alt_addr)
		{
			$title .= ' (' . $alt_name . ')';
		}
	}
	else if ($alt_addr)
	{
		if (($alt_flags & ADDR_ICON_MASK) != 0)
		{
			echo ADDRESS_PICS_DIR . $dir . $alt_id . '.png?' . (($alt_flags & ADDR_ICON_MASK) >> ADDR_ICON_MASK_OFFSET);
		}
		else
		{
			echo 'images/' . $dir . 'address.png';
		}
		$title = $name;
	}
	else 
	{
		if (($alt_flags & CLUB_ICON_MASK) != 0)
		{
			echo CLUB_PICS_DIR . $dir . $alt_id . '.png?' . (($alt_flags & CLUB_ICON_MASK) >> CLUB_ICON_MASK_OFFSET);
		}
		else
		{
			echo 'images/' . $dir . 'club.png';
		}
		$title = $alt_name;
	}
	
/*		echo '<span style="position:relative; left:0px; top:0px;">';
		show_address_pic($addr_id, $addr_flags, $dir, $width, $height);
		echo '<span style="position:absolute;right:0px;bottom:0px;">';
		show_club_pic($club_id, $club_name, $club_flags, $dir, $width / 2, $height / 2);
		echo '</span></span>';*/
	echo '" border="0" title="' . $title . '"';
	if ($width > 0)
	{
		echo ' width="' . $width . '"';
	}
	if ($height > 0)
	{
		echo ' height="' . $height . '"';
	}
	echo '>';
	if ($flags & EVENT_FLAG_CANCELED)
	{
		echo '<img src="images/' . $dir . $_lang_code . '/cancelled.png" style="position:absolute; left:50%; margin-left:-' . ($w / 2) . 'px;" title="' . $title . '"';
		if ($width > 0)
		{
			echo ' width="' . $width . '"';
		}
		if ($height > 0)
		{
			echo ' height="' . $height . '"';
		}
		echo '>';
	}
	echo '</span>';
}

class Event
{
	public $id;
	public $name;
	public $price;
	public $timestamp;
	public $timezone;
	public $duration;
	public $addr_id;
	public $addr;
	public $addr_url;
	public $addr_flags;
	public $city;
	public $country;
	public $club_id;
	public $club_name;
	public $club_flags;
	public $club_url;
	public $notes;
	public $flags;
	public $langs;
	public $rules_id;
	public $scoring_id;
	
	public $day;
	public $month;
	public $year;
	public $hour;
	public $minute;
	
	public $coming_odds;
	
	function __construct()
	{
		global $_profile;
	
		$this->id = 0;
		$this->name = '';
		$this->price = '';
		$this->duration = 6 * 3600;
		$this->addr_id = -1;
		$this->addr = '';
		$this->city = '';
		$this->country = '';
		$this->addr_url = '';
		$this->addr_flags = 0;
		$this->club_id = -1;
		$this->club_name = '';
		$this->club_flags = NEW_CLUB_FLAGS;
		$this->club_url = '';
		$this->notes = '';
		$this->flags = EVENT_FLAG_REG_ON_ATTEND | EVENT_FLAG_ALL_MODERATE;
		$this->langs = LANG_ALL;
		$this->rules_id = -1;
		$this->scoring_id = -1;
		$this->coming_odds = NULL;
		
		if ($_profile != NULL)
		{
			$timezone = get_timezone();
			foreach ($_profile->clubs as $club)
			{
				if (($club->flags & UC_PERM_MANAGER) != 0)
				{
					$this->club_id = $club->id;
					$timezone = $club->timezone;
					$this->rules_id = $club->rules_id;
					$this->scoring_id = $club->scoring_id;
					$this->langs = $club->langs;
					break;
				}
			}
			$this->set_datetime(time(), $timezone);
		}
	}
	
	function set_datetime($timestamp, $timezone)
	{
		date_default_timezone_set($timezone);
		$this->timestamp = $timestamp;
		$this->timezone = $timezone;
		$this->day = date('j', $timestamp);
		$this->month = date('n', $timestamp);
		$this->year = date('Y', $timestamp);
		$this->hour = date('G', $timestamp);
		$this->minute = round(date('i', $timestamp) / 10) * 10;
	}
	
	function set_time($timestamp, $timezone)
	{
		date_default_timezone_set($timezone);
		$this->hour = date('G', $timestamp);
		$this->minute = round(date('i', $timestamp) / 10) * 10;
		$timestamp = mktime($this->hour, $this->minute, 0, $this->month, $this->day, $this->year);
		$this->timestamp = $timestamp;
		$this->timezone = $timezone;
	}
	
	function set_default_name()
	{
		list ($this->club_name, $this->price) = Db::record(get_label('club'), 'SELECT name, price FROM clubs WHERE id = ?', $this->club_id);
		$this->name = $this->club_name;
	}
	
	static function late_str($late)
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
	
	static function odds_str($odds, $bringing, $late)
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
			$result .= '; ' . Event::late_str($late);
		}
		return $result;
	}
	
	function set_club($club)
	{
		$this->club_id = $club->id;
		
		$query = new DbQuery(
			'SELECT a.id, a.name, i.timezone, a.address, a.map_url, a.flags FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities i ON a.city_id = i.id' .
				' WHERE e.club_id = ? ORDER BY e.start_time DESC LIMIT 1',
			$this->club_id);
		$row = $query->next();
		if (!$row)
		{
			$query = new DbQuery(
				'SELECT a.id, a.name, i.timezone, a.address, a.map_url, a.flags FROM addresses a' . 
					' JOIN cities i ON a.city_id = i.id' .
					' WHERE a.club_id = ? LIMIT 1',
				$this->club_id);
			$row = $query->next();
		}
		
		if ($row)
		{
			list($this->addr_id, $this->name, $timezone, $this->addr, $this->addr_url, $this->addr_flags) = $row;
			$this->set_datetime($this->timestamp, $timezone);
		}
		else
		{
			$this->addr_id = -1;
			$this->name = '';
			$this->addr = '';
			$this->addr_url = '';
			$this->addr_flags = 0;
			
			$this->set_datetime(time(), $club->timezone);
		}
		
		$this->langs = $club->langs;
		$this->price = $club->price;
		$this->city = $club->city;
		$this->country = $club->country;
	}

	function create()
	{
		global $_profile;
/*		echo '<pre>';
		print_r($this);
		echo '</pre>';*/
		
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('event name')));
		}
		
		if ($this->timestamp + $this->duration < time())
		{
			throw new Exc(get_label('You can not create event in the past. Please check the date.'));
		}
		
		$club = $_profile->clubs[$this->club_id];
		
		Db::begin();
		
		if ($this->addr_id <= 0)
		{
			$city_id = retrieve_city_id($this->city, retrieve_country_id($this->country), $club->timezone);

			if ($this->addr == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('address')));
			}
			$sc_address = htmlspecialchars($this->addr, ENT_QUOTES);
	
			check_address_name($sc_address, $this->club_id);
	
			Db::exec(
				get_label('address'), 
				'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
				$sc_address, $this->club_id, $sc_address, $city_id);
			list ($this->addr_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
			$log_details =
				'name=' . $sc_address .
				"<br>address=" . $sc_address .
				"<br>city=" . $this->city . ' (' . $city_id . ')';
			db_log('address', 'Created', $log_details, $this->addr_id, $this->club_id);
	
			$warning = load_map_info($this->addr_id);
			if ($warning != NULL)
			{
				echo '<p>' . $warning . '</p>';
			}

			$this->timezone = $club->timezone;
		}
		else
		{
			list($this->timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $this->addr_id);
		}
		
		$query = new DbQuery('SELECT max(start_time) FROM events WHERE start_time >= ? AND start_time < ?', $this->timestamp, $this->timestamp + 60);
		if (($row = $query->next()) && $row[0] != NULL)
		{
			$this->timestamp = $row[0] + 1;
		}
		
		Db::exec(
			get_label('event'), 
			'INSERT INTO events (name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$this->name, $this->price, $this->addr_id, $this->club_id, $this->timestamp, 
			$this->notes, $this->duration, $this->flags, $this->langs, $this->rules_id, 
			$this->scoring_id);
		list ($this->id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
		list ($addr_name, $timezone) = Db::record(get_label('address'), 'SELECT a.name, c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $this->addr_id);
		$log_details = 
			'name=' . $this->name .
			"<br>price=" . $this->price .
			"<br>address=" . $addr_name . ' (' . $this->addr_id .
			")<br>start=" . format_date('d/m/y H:i', $this->timestamp, $timezone) . ' (' . $timezone .
			")<br>duration=" . $this->duration .
			"<br>flags=" . $this->flags .
			"<br>langs=" . $this->langs .
			"<br>rules=" . $this->rules_id .
			"<br>scoring=" . $this->scoring_id;
		db_log('event', 'Created', $log_details, $this->id, $this->club_id);
		
		Db::commit();
		
		return $this->id;
	}
	
	function update()
	{
		global $_profile;
	
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('event name')));
		}
		
		if ($this->addr_id <= 0)
		{
			throw new Exc(get_label('Please enter event address.'));
		}
		
		Db::begin();
		
		list ($old_timestamp, $old_duration) = Db::record(get_label('event'), 'SELECT start_time, duration FROM events WHERE id = ?', $this->id);
		if ($this->timestamp + 60 > $old_timestamp && $this->timestamp <= $old_timestamp)
		{
			$this->timestamp = $old_timestamp;
		}
		else
		{
			if ($this->timestamp + $this->duration < time())
			{
				throw new Exc(get_label('You can not change event time to the past. Please check the date.'));
			}
		
			$query = new DbQuery('SELECT max(start_time) FROM events WHERE start_time >= ? AND start_time < ?', $this->timestamp, $this->timestamp + 60);
			if (($row = $query->next()) && $row[0] != NULL)
			{
				$this->timestamp = $row[0] + 1;
			}
		}
		
		Db::exec(
			get_label('event'), 
			'UPDATE events SET ' .
				'name = ?, price = ?, club_id = ?, rules_id = ?, scoring_id = ?, ' .
				'address_id = ?, start_time = ?, notes = ?, duration = ?, flags = ?, ' .
				'languages = ? WHERE id = ?',
			$this->name, $this->price, $this->club_id, $this->rules_id, $this->scoring_id, 
			$this->addr_id, $this->timestamp, $this->notes, $this->duration, $this->flags, 
			$this->langs, $this->id);
		if (Db::affected_rows() > 0)
		{
			list ($addr_name, $timezone) = Db::record(get_label('address'), 'SELECT a.name, c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $this->addr_id);
			$log_details =
				'name=' . $this->name .
				"<br>price=" . $this->price .
				"<br>address=" . $addr_name . ' (' . $this->addr_id .
				")<br>start=" . format_date('d/m/y H:i', $this->timestamp, $timezone) . ' (' . $timezone .
				")<br>duration=" . $this->duration .
				"<br>flags=" . $this->flags .
				"<br>langs=" . $this->langs .
				"<br>rules=" . $this->rules_id .
				"<br>scoring=" . $this->scoring_id;
			db_log('event', 'Changed', $log_details, $this->id, $this->club_id);
		}
		
		if ($this->timestamp != $old_timestamp || $this->duration != $old_duration)
		{
			Db::exec(
				get_label('registration'), 
				'UPDATE registrations SET start_time = ?, duration = ? WHERE event_id = ?',
				$this->timestamp, $this->duration, $this->id);
		}
		Db::commit();
	}

	function parse_sample_email($email_addr, $body, $subj, $lang = LANG_NO)
	{
		global $_profile;
		$code = generate_email_code();
		$base_url = get_server_url() . '/email_request.php?uid=' . $_profile->user_id . '&code=' . $code;
		
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($body);
			if ($lang == LANG_NO)
			{
				$lang = $_profile->user_def_lang;
			}
		}

		$tags = get_bbcode_tags();
		$tags['ename'] = new Tag($this->name);
		$tags['eid'] = new Tag($this->id);
		$tags['edate'] = new Tag(format_date('l, F d, Y', $this->timestamp, $this->timezone, $lang));
		$tags['etime'] = new Tag(format_date('H:i', $this->timestamp, $this->timezone, $lang));
		$tags['notes'] = new Tag($this->notes);
		$tags['langs'] = new Tag(get_langs_str($this->langs, ', ', LOWERCASE, $lang));
		$tags['addr'] = new Tag($this->addr);
		$tags['aurl'] = new Tag($this->addr_url);
		$tags['aid'] = new Tag($this->addr_id);
		if ($this->id > 0)
		{
			$tags['aimage'] = new Tag('<img src="' . get_server_url() . '/' . ADDRESS_PICS_DIR . TNAILS_DIR . $this->addr_id . '.jpg">');
		}
		else
		{
			$tags['aimage'] = new Tag('<img src="images/sample_address.jpg">');
		}
		$tags['uname'] = new Tag($_profile->user_name);
		$tags['uid'] = new Tag($_profile->user_id);
		$tags['email'] = new Tag($email_addr);
		$tags['cname'] = new Tag($this->club_name);
		$tags['cid'] = new Tag($this->club_id);
		$tags['code'] = new Tag($code);
		$tags['accept'] = new Tag('<a href="' . $base_url . '&accept=1" target="_blank">', '</a>');
		$tags['decline'] = new Tag('<a href="' . $base_url . '&decline=1" target="_blank">', '</a>');
		$tags['unsub'] = new Tag('<a href="' . $base_url . '&unsub=1" target="_blank">', '</a>');
		$tags['accept_btn'] = new Tag('<input type="submit" name="accept" value="#">');
		$tags['decline_btn'] = new Tag('<input type="submit" name="decline" value="#">');
		$tags['unsub_btn'] = new Tag('<input type="submit" name="unsub" value="#">');
	
		return array(
			parse_tags($body, $tags),
			parse_tags($subj, $tags),
			$lang);
	}
	
	function load($event_id)
	{
		global $_profile, $_lang_code;
		if ($event_id <= 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
	
		$this->id = $event_id;
		list (
			$this->name, $this->price, $this->club_id, $this->club_name, $this->club_flags, $this->club_url, $timestamp, $this->duration,
			$this->addr_id, $this->addr, $this->addr_url, $timezone, $this->addr_flags,
			$this->notes, $this->langs, $this->flags, $this->rules_id, $this->scoring_id, $this->coming_odds, $this->city, $this->country) =
				Db::record(
					get_label('event'), 
					'SELECT e.name, e.price, c.id, c.name, c.flags, c.web_site, e.start_time, e.duration, a.id, a.address, a.map_url, i.timezone, a.flags, e.notes, e.languages, e.flags, e.rules_id, e.scoring_id, u.coming_odds, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ' FROM events e' .
						' JOIN addresses a ON e.address_id = a.id' .
						' JOIN clubs c ON e.club_id = c.id' .
						' JOIN cities i ON a.city_id = i.id' .
						' JOIN countries o ON i.country_id = o.id' .
						' LEFT OUTER JOIN event_users u ON u.event_id = e.id AND u.user_id = ?' .
						' WHERE e.id = ?',
					$user_id, $event_id);
			
		$this->set_datetime($timestamp, $timezone);
	}
	
	function show_details($show_attendance = true, $show_details = true)
	{
		if ($show_details)
		{
			echo '<table class="bordered" width="100%"><tr>';
			echo '<td align="center" class="dark"><p>' . format_date('l, F d, Y, H:i', $this->timestamp, $this->timezone) . '<br>';
			if ($this->addr_url == '')
			{
				echo get_label('At [0]', addr_label($this->addr, $this->city, $this->country));
			}
			else
			{
				echo get_label('At [0]', '<a href="' . $this->addr_url . '" target="_blank">' . addr_label($this->addr, $this->city, $this->country) . '</a>');
			}
			if ($this->notes != '')
			{
				echo '<br>';
				echo $this->notes;
			}
			echo '</p>';
			if ($this->langs != LANG_RUSSIAN)
			{
				echo '<p>' . get_label('Language') . ': ' . get_langs_str($this->langs, ', ') . '</p>';
			}
			if ($this->price != '')
			{
				echo '<p>' . get_label('Admission rate') . ': ' . $this->price . '</p>';
			}
			echo '</td></tr></table>';
		}
		
		if ($show_attendance)
		{
			$attendance = array();
			$coming = 0;
			$min_coming = 0;
			$max_coming = 0;
			$declined = 0;
			$query = new DbQuery('SELECT u.id, u.name, a.coming_odds, a.people_with_me, u.flags, a.late FROM event_users a JOIN users u ON a.user_id = u.id WHERE a.event_id = ? ORDER BY a.coming_odds DESC, a.late, a.people_with_me DESC, u.name', $this->id);
			while ($row = $query->next())
			{
				$attendance[] = $row;
				$odds = $row[2];
				$bringing = $row[3];
				if ($odds >= 100)
				{
					$min_coming += 1 + $bringing;
					$max_coming += 1 + $bringing;
					$coming += 1 + $bringing;
				}
				else if ($odds > 0)
				{
					$max_coming += 1 + $bringing;
					$coming += (1 + $bringing) * $odds / 100;
				}
				else
				{
					++$declined;
				}
			}
			
			if ($this->flags & EVENT_FLAG_TOURNAMENT)
			{
				$found = false;
				$col = 0;
				foreach ($attendance as $a)
				{
					list($user_id, $name, $odds, $bringing, $user_flags, $late) = $a;
					if ($odds >= 100)
					{
						if ($col == 0)
						{
							if (!$found)
							{
								$found = true;
								echo '<table class="bordered" width="100%">';
								echo '<tr class="darker"><td colspan="6" align="center"><b>' . get_label('Accepted') . ':</b></td>';
							}
							echo '</tr><tr>';
						}
						
						echo '<td width="16.66%" class="lighter" align="center"><a href="user_info.php?id=' . $user_id . '&bck=1">';
						show_user_pic($user_id, $name, $user_flags, ICONS_DIR, 50, 50);
						echo '</a><br>' . $name . '</td>';
						++$col;
						if ($col == 6)
						{
							$col = 0;
						}
					}
				}
				if ($found)
				{
					if ($col > 0)
					{
						echo '<td class="lighter" colspan="' . (6 - $col) . '"></td>';
					}
					echo '</tr></table>';
				}
				
				$found = false;
				$col = 0;
				foreach ($attendance as $a)
				{
					list($user_id, $name, $odds, $bringing, $user_flags, $late) = $a;
					if ($odds < 100)
					{
						if ($col == 0)
						{
							if (!$found)
							{
								$found = true;
								echo '<table class="bordered" width="100%">';
								echo '<tr class="darker"><td colspan="6" align="center"><b>' . get_label('Declined') . ':</b></td>';
							}
							echo '</tr><tr>';
						}
						
						echo '<td width="16.66%" align="center"><a href="user_info.php?id=' . $user_id . '&bck=1">';
						show_user_pic($user_id, $name, $user_flags, ICONS_DIR, 50, 50);
						echo '</a><br>' . $name . '</td>';
						++$col;
						if ($col == 6)
						{
							$col = 0;
						}
					}
				}
				if ($found)
				{
					if ($col > 0)
					{
						echo '<td colspan="' . (6 - $col) . '"></td>';
					}
					echo '</tr></table>';
				}
			}
			else if (BRIEF_ATTENDANCE)
			{
				$found = false;
				$col = 0;
				foreach ($attendance as $a)
				{
					list($user_id, $name, $odds, $bringing, $user_flags, $late) = $a;
					if ($odds > 0)
					{
						if ($col == 0)
						{
							if (!$found)
							{
								$found = true;
								echo '<table class="bordered" width="100%">';
								echo '<tr class="darker"><td colspan="6" align="left"><b>';
								if ($max_coming == 0)
								{
									echo get_label('No players attended yet.');
								}
								else if ($max_coming != $min_coming)
								{
									echo get_label('Players coming: [0]-[1]. Most likely: [2].', $min_coming, $max_coming, number_format($coming,0));
								}
								else
								{
									echo get_label('Players coming: [0].', $min_coming, $max_coming, number_format($coming,0));
								}
								echo '</b></td>';
							}
							echo '</tr><tr>';
						}
						
						echo '<td width="16.66%" ';
						if ($odds < 50)
						{
							echo 'class="dark"';
						}
						else if ($odds < 100)
						{
							echo 'class="light"';
						}
						else
						{
							echo 'class="lighter"';
						}
						echo 'align="center"><a href="user_info.php?id=' . $user_id . '&bck=1">';
						show_user_pic($user_id, $name, $user_flags, ICONS_DIR, 50, 50);
						echo '</a><br>' . $name;
						if ($bringing > 0)
						{
							echo ' + ' . $bringing; 
						}
						if ($odds < 100)
						{
							echo ' (' . $odds . '%)';
						}
						if ($late != 0)
						{
							echo '<br>' . Event::late_str($late);
						}
						echo '</td>';
						++$col;
						if ($col == 6)
						{
							$col = 0;
						}
					}
				}
				if ($found)
				{
					if ($col > 0)
					{
						echo '<td class="dark" colspan="' . (6 - $col) . '"></td>';
					}
					echo '</tr></table>';
				}
				
				$found = false;
				$col = 0;
				foreach ($attendance as $a)
				{
					list($user_id, $name, $odds, $bringing, $user_flags, $late) = $a;
					if ($odds <= 0)
					{
						if ($col == 0)
						{
							if (!$found)
							{
								$found = true;
								echo '<table class="bordered" width="100%">';
								echo '<tr class="darker"><td><b>' . get_label('[0] declined', $declined) . ':</b></td></tr></table><table width="100%" class="bordered"><tr>';
							}
							else
							{
								echo '</tr><tr>';
							}
						}
						
						echo '<td width="16.66%" align="center"><a href="user_info.php?id=' . $user_id . '&bck=1">';
						show_user_pic($user_id, $name, $user_flags, ICONS_DIR, 50, 50);
						echo '</a><br>' . $name . '</td>';
						++$col;
						if ($col == 6)
						{
							$col = 0;
						}
					}
				}
				if ($found)
				{
					if ($col > 0)
					{
						echo '<td colspan="' . (6 - $col) . '"></td>';
					}
					echo '</tr></table>';
				}
			}
			else
			{
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td colspan="3" align="center"><b>';
				if ($max_coming == 0)
				{
					echo get_label('No players attended yet.');
				}
				else if ($max_coming != $min_coming)
				{
					echo get_label('Players coming: [0]-[1]. Most likely: [2].', $min_coming, $max_coming, number_format($coming,0));
				}
				else
				{
					echo get_label('Players coming: [0].', $min_coming, $max_coming, number_format($coming,0));
				}
				echo '</b></td></tr>';

				foreach ($attendance as $a)
				{
					list($user_id, $name, $odds, $bringing, $user_flags, $late) = $a;
					if ($odds > 50)
					{
						echo '<tr class="lighter">';
					}
					else if ($odds > 0)
					{
						echo '<tr class="light">';
					}
					else
					{
						echo '<tr>';
					}
					
					echo '<td width="50"><a href="user_info.php?id=' . $user_id . '&bck=1">';
					show_user_pic($user_id, $name, $user_flags, ICONS_DIR, 50, 50);
					echo '</a></td><td><a href="user_info.php?id=' . $user_id . '&bck=1">' . cut_long_name($name, 80) . '</a></td><td width="280" align="center"><b>';
					echo Event::odds_str($odds, $bringing, $late) . '</b></td></tr>';
				}
				
				echo '</table>';
			}
		}
	}
	
	function get_full_name($with_club = false)
	{
		if ($with_club && $this->name != $this->club_name)
		{
			return get_label('[1] / [0]: [2]', $this->name, $this->club_name, format_date('D, M d, y', $this->timestamp, $this->timezone));
		}
		return get_label('[0]: [1]', $this->name, format_date('D, M d, y', $this->timestamp, $this->timezone));
	}
	
	static function show_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags, $attending)
	{
		global $_profile;

		$now = time();
		
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
			
			if ($_profile->is_manager($club_id))
			{
				echo '<button class="icon" onclick="mr.eventMailing(' . $id . ')" title="' . get_label('Manage event emails') . '"><img src="images/email.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editEvent(' . $id . ')" title="' . get_label('Edit the event') . '"><img src="images/edit.png" border="0"></button>';
				if ($start_time >= $now)
				{
					if (($flags & EVENT_FLAG_CANCELED) != 0)
					{
						echo '<button class="icon" onclick="mr.restoreEvent(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
					}
					else
					{
						echo '<button class="icon" onclick="mr.cancelEvent(' . $id . ', \'' . get_label('Are you sure you want to cancel the event?') . '\')" title="' . get_label('Cancel the event') . '"><img src="images/delete.png" border="0"></button>';
					}
				}
				else if ($start_time + $duration + EVENT_ALIVE_TIME >= $now)
				{
					echo '<button class="icon" onclick="mr.extendEvent(' . $id . ')" title="' . get_label('Extend the event') . '"><img src="images/time.png" border="0"></button>';
				}
				$no_buttons = false;
			}
			
			if ($_profile->is_moder($club_id) && $start_time < $now && $start_time + $duration >= $now)
			{
				echo '<button class="icon" onclick="mr.playEvent(' . $id . ')" title="' . get_label('Play the game') . '"><img src="images/game.png" border="0"></button>';
				$no_buttons = false;
			}
		}
		echo '<button class="icon" onclick="window.open(\'event_screen.php?id=' . $id . '\' ,\'_blank\')" title="' . get_label('Open interactive standings page') . '"><img src="images/details.png" border="0"></button>';
	}
	
	function show_pic($dir, $width = 0, $height = 0, $alt_addr = true)
	{
		show_event_pic($this->id, $this->name, $this->flags, $this->addr_id, $this->addr, $this->addr_flags, $dir, $width, $height, $alt_addr);
	}
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
		array('[ename]', get_label('Event name')),
		array('[eid]', get_label('Event id')),
		array('[edate]', get_label('Event date')),
		array('[etime]', get_label('Event time')),
		array('[addr]', get_label('Event address')),
		array('[aurl]', get_label('Event address URL')),
		array('[aid]', get_label('Event address id')),
		array('[aimage]', get_label('Event address image')),
		array('[notes]', get_label('Event notes')),
		array('[langs]', get_label('Event languages')),
		array('[uname]', get_label('User name')),
		array('[uid]', get_label('User id')),
		array('[email]', get_label('User email')),
		array('[score]', get_label('User score')),
		array('[cname]', get_label('Club name')),
		array('[cid]', get_label('Club id')),
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
		list($eid, $event_name, $event_start_time, $event_duration, $event_timezone, $club_name) = $row;
		
		echo '<option value="' . $eid . '"';
		if ($eid == $event_id)
		{
			echo ' selected';
		}
		if ($clubs_count > 1)
		{
			echo '>' . get_label('[0]: [1] at [2]', $event_name, format_date('D F d H:i', $event_start_time, $event_timezone), $club_name) . '</option>';
		}
		else
		{
			echo '>' . get_label('[0]: [1]', $event_name, format_date('D F d H:i', $event_start_time, $event_timezone)) . '</option>';
		}
	}
	echo '</select>';
}

function show_date_controls($day, $month, $year, $name_base = '')
{
	echo '<select id="' . $name_base . 'day" name="' . $name_base . 'day">';
	for ($i = 1; $i <= 31; ++$i)
	{
		echo '<option value="' . $i . '"';
		if ($day == $i)
		{
			echo ' selected';
		}
		echo '>' . $i . '</option>';
	}
	echo '</select>';
	$month_names = array(get_label('January'), get_label('February'), get_label('March'), get_label('April'), get_label('May'), get_label('June'), get_label('July'), get_label('August'), get_label('September'), get_label('October'), get_label('November'), get_label('December'));
	echo '<select id="' . $name_base . 'month" name="' . $name_base . 'month">';
	for ($i = 1; $i <= 12; ++$i)
	{
		echo '<option value="' . $i . '"';
		if ($month == $i)
		{
			echo ' selected';
		}
		echo '>' . $month_names[$i-1] . '</option>';
	}
	echo '</select>';
	echo '<select id="' . $name_base . 'year" name="' . $name_base . 'year">';
	$current_year = (int)date("Y");
	for ($i = $current_year - 1; $i <= $current_year + 5; ++$i)
	{
		echo '<option value="' . $i . '"';
		if ($year == $i)
		{
			echo ' selected';
		}
		echo '>' . $i . '</option>';
	}
	echo '</select>';
}

function show_time_controls($hour, $minute, $prefix = '')
{
	echo '<select id="' . $prefix . 'hour" name="' . $prefix . 'hour">';
	for ($i = 0; $i < 24; ++$i)
	{
		echo '<option value="' . $i . '"';
		if ($hour == $i)
		{
			echo ' selected';
		}
		echo '>' . sprintf('%02s', $i) . '</option>';
	}
	echo '</select>';
	echo '<select id="' . $prefix . 'minute" name="' . $prefix . 'minute">';
	for ($i = 0; $i < 60; $i += 10)
	{
		echo '<option value="' . $i . '"';
		if ($minute < $i + 10 && $minute >= $i)
		{
			echo ' selected';
		}
		echo '>' . sprintf('%02s', $i) . '</option>';
	}
	echo '</select>';
}

class EventPageBase extends PageBase
{
	protected $event;
	protected $is_manager;

	protected function prepare()
	{
		global $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$this->event = new Event();
		$this->event->load($_REQUEST['id']);
		$this->is_manager = ($_profile != NULL && $_profile->is_manager($this->event->club_id));
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%">';

		if ($this->event->timestamp < time())
		{
			$menu = array
			(
				new MenuItem('event_info.php?id=' . $this->event->id, get_label('Event'), get_label('General event information'))
				, new MenuItem('event_standings.php?id=' . $this->event->id, get_label('Standings'), get_label('Event standings'))
				, new MenuItem('event_competition.php?id=' . $this->event->id, get_label('Competition chart'), get_label('How players were competing on this event.'))
				, new MenuItem('event_games.php?id=' . $this->event->id, get_label('Games'), get_label('Games list of the event'))
				, new MenuItem('#stats', get_label('Stats'), NULL, array
				(
					new MenuItem('event_stats.php?id=' . $this->event->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME))
					, new MenuItem('event_by_numbers.php?id=' . $this->event->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.'))
					, new MenuItem('event_nominations.php?id=' . $this->event->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.'))
					, new MenuItem('event_moderators.php?id=' . $this->event->id, get_label('Moderators'), get_label('Moderators statistics of the event'))
				))
				, new MenuItem('#resources', get_label('Resources'), NULL, array
				(
					new MenuItem('event_albums.php?id=' . $this->event->id, get_label('Photos'), get_label('Event photo albums'))
					, new MenuItem('event_videos.php?id=' . $this->event->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.'))
					, new MenuItem('event_videos.php?id=' . $this->event->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.'))
					// , new MenuItem('event_tasks.php?id=' . $this->event->id, get_label('Tasks'), get_label('Learning tasks and puzzles.'))
					// , new MenuItem('event_articles.php?id=' . $this->event->id, get_label('Articles'), get_label('Books and articles.'))
					// , new MenuItem('event_links.php?id=' . $this->event->id, get_label('Links'), get_label('Links to custom mafia web sites.'))
				))
			);
			echo '<tr><td colspan="4">';
			PageBase::show_menu($menu);
			echo '</td></tr>';
		}
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->event->flags & EVENT_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		Event::show_buttons(
			$this->event->id,
			$this->event->timestamp,
			$this->event->duration,
			$this->event->flags,
			$this->event->club_id,
			$this->event->club_flags,
			$this->event->coming_odds != NULL && $this->event->coming_odds > 0);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		if ($this->event->addr_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->event->addr_id . '">';
			$this->event->show_pic(TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			$this->event->show_pic(TNAILS_DIR);
		}
		echo '</td></tr></table></td>';
		
		if ($this->event->flags & EVENT_FLAG_TOURNAMENT)
		{
			$title = get_label('Tournament [0]', $this->_title);
		}
		else
		{
			$title = get_label('Event [0]', $this->_title);
		}
		
		echo '<td rowspan="2" valign="top"><h2 class="event">' . $title . '</h2><br><h3>' . $this->event->name . '</h3><p class="subtitle">' . format_date('l, F d, Y, H:i', $this->event->timestamp, $this->event->timezone) . '</p></td>';
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><a href="club_main.php?bck=1&id=' . $this->event->club_id . '" title="' . $this->event->club_name . '"><table><tr><td align="center">' . $this->event->club_name . '</td></tr><tr><td align="center">';
		show_club_pic($this->event->club_id, $this->event->club_name, $this->event->club_flags, ICONS_DIR);
		echo '</td></tr></table></a></td></tr>';
		
		echo '</table>';
	}
}
	
?>