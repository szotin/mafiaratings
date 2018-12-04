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

function show_tournament_pic($id, $name, $flags, $alt_id, $alt_name, $alt_flags, $dir, $width = 0, $height = 0, $alt_addr = true)
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
	
	$origin = TOURNAMENT_PICS_DIR . $dir . $id . '.png';
	echo '<span style="position:relative;"><img code="' . TOURNAMENT_PIC_CODE . $id . '" origin="' . $origin . '" src="';
	if ($flags & TOURNAMENT_ICON_MASK)
	{
		echo $origin . '?' . (($flags & TOURNAMENT_ICON_MASK) >> TOURNAMENT_ICON_MASK_OFFSET);
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
	if ($flags & TOURNAMENT_FLAG_CANCELED)
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

class Tournament
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
		$this->flags = 0;
		$this->langs = LANG_ALL;
		$this->rules_id = -1;
		$this->scoring_id = -1;
		
		if ($_profile != NULL)
		{
			$timezone = get_timezone();
			foreach ($_profile->clubs as $club)
			{
				if (($club->flags & USER_CLUB_PERM_MANAGER) != 0)
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
	
	function set_default_name()
	{
		list ($this->club_name, $this->price) = Db::record(get_label('club'), 'SELECT name, price FROM clubs WHERE id = ?', $this->club_id);
		$this->name = $this->club_name;
	}
	
	function set_club($club)
	{
		$this->club_id = $club->id;
		
		$query = new DbQuery(
			'SELECT a.id, a.name, i.timezone, a.address, a.map_url, a.flags FROM tournaments t' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities i ON a.city_id = i.id' .
				' WHERE t.club_id = ? ORDER BY t.start_time DESC LIMIT 1',
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
		$this->scoring_id = $club->scoring_id;
		$this->rules_id = $club->rules_id;
	}

	function create()
	{
		global $_profile;
/*		echo '<pre>';
		print_r($this);
		echo '</pre>';*/
		
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament name')));
		}
		
		if ($this->timestamp + $this->duration < time())
		{
			throw new Exc(get_label('You can not create tournament in the past. Please check the date.'));
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
			
			$log_details = new stdClass();
			$log_details->name = $sc_address;
			$log_details->address = $sc_address;
			$log_details->city = $this->city;
			$log_details->city_id = $city_id;
			db_log(LOG_OBJECT_ADDRESS, 'created', $log_details, $this->addr_id, $this->club_id);
	
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
		
		$query = new DbQuery('SELECT max(start_time) FROM tournaments WHERE start_time >= ? AND start_time < ?', $this->timestamp, $this->timestamp + 60);
		if (($row = $query->next()) && $row[0] != NULL)
		{
			$this->timestamp = $row[0] + 1;
		}
		
		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules_id, scoring_id) ' .
			'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$this->name, $this->price, $this->addr_id, $this->club_id, $this->timestamp, 
			$this->notes, $this->duration, $this->flags, $this->langs, $this->rules_id, 
			$this->scoring_id);
		list ($this->id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		list ($addr_name, $timezone) = Db::record(get_label('address'), 'SELECT a.name, c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $this->addr_id);
		
		$log_details = new stdClass();
		$log_details->name = $this->name;
		$log_details->price = $this->price;
		$log_details->address_name = $addr_name;
		$log_details->address_id = $this->addr_id;
		$log_details->start = format_date('d/m/y H:i', $this->timestamp, $timezone);
		$log_details->duration = $this->duration;
		$log_details->flags = $this->flags;
		$log_details->langs = $this->langs;
		$log_details->rules_id = $this->rules_id;
		$log_details->scoring_id = $this->scoring_id;
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $this->id, $this->club_id);
		
		Db::commit();
		
		return $this->id;
	}
	
	function update()
	{
		global $_profile;
	
		if ($this->name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament name')));
		}
		
		if ($this->addr_id <= 0)
		{
			throw new Exc(get_label('Please enter tournament address.'));
		}
		
		Db::begin();
		
		list ($old_timestamp, $old_duration) = Db::record(get_label('tournament'), 'SELECT start_time, duration FROM tournaments WHERE id = ?', $this->id);
		if ($this->timestamp + 60 > $old_timestamp && $this->timestamp <= $old_timestamp)
		{
			$this->timestamp = $old_timestamp;
		}
		else
		{
			if ($this->timestamp + $this->duration < time())
			{
				throw new Exc(get_label('You can not change tournament time to the past. Please check the date.'));
			}
		
			$query = new DbQuery('SELECT max(start_time) FROM tournaments WHERE start_time >= ? AND start_time < ?', $this->timestamp, $this->timestamp + 60);
			if (($row = $query->next()) && $row[0] != NULL)
			{
				$this->timestamp = $row[0] + 1;
			}
		}
		
		Db::exec(
			get_label('tournament'), 
			'UPDATE tournaments SET ' .
				'name = ?, price = ?, club_id = ?, rules_id = ?, scoring_id = ?, ' .
				'address_id = ?, start_time = ?, notes = ?, duration = ?, flags = ?, ' .
				'languages = ? WHERE id = ?',
			$this->name, $this->price, $this->club_id, $this->rules_id, $this->scoring_id,
			$this->addr_id, $this->timestamp, $this->notes, $this->duration, $this->flags,
			$this->langs, $this->id);
		if (Db::affected_rows() > 0)
		{
			list ($addr_name, $timezone) = Db::record(get_label('address'), 'SELECT a.name, c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $this->addr_id);
			$log_details = new stdClass();
			$log_details->name = $this->name;
			$log_details->price = $this->price;
			$log_details->address_name = $addr_name;
			$log_details->address_id = $this->addr_id;
			$log_details->start = format_date('d/m/y H:i', $this->timestamp, $timezone);
			$log_details->duration = $this->duration;
			$log_details->flags = $this->flags;
			$log_details->langs = $this->langs;
			$log_details->rules_id = $this->rules_id;
			$log_details->scoring_id = $this->scoring_id;
			db_log(LOG_OBJECT_TOURNAMENT, 'changed', $log_details, $this->id, $this->club_id);
		}
		
		if ($this->timestamp != $old_timestamp || $this->duration != $old_duration)
		{
			Db::exec(
				get_label('registration'), 
				'UPDATE registrations SET start_time = ?, duration = ? WHERE tournament_id = ?',
				$this->timestamp, $this->duration, $this->id);
		}
		Db::commit();
	}

	function parse_sample_email($email_addr, $body, $subj, $lang = LANG_NO)
	{
		global $_profile;
		$code = generate_email_code();
		$base_url = get_server_url() . '/email_request.php?user_id=' . $_profile->user_id . '&code=' . $code;
		
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($body);
			if ($lang == LANG_NO)
			{
				$lang = $_profile->user_def_lang;
			}
		}

		$tags = get_bbcode_tags();
		$tags['root'] = new Tag(get_server_url());
		$tags['tournament_name'] = new Tag($this->name);
		$tags['tournament_id'] = new Tag($this->id);
		$tags['tournament_date'] = new Tag(format_date('l, F d, Y', $this->timestamp, $this->timezone, $lang));
		$tags['tournament_time'] = new Tag(format_date('H:i', $this->timestamp, $this->timezone, $lang));
		$tags['notes'] = new Tag($this->notes);
		$tags['langs'] = new Tag(get_langs_str($this->langs, ', ', LOWERCASE, $lang));
		$tags['address'] = new Tag($this->addr);
		$tags['address_url'] = new Tag($this->addr_url);
		$tags['address_id'] = new Tag($this->addr_id);
		if ($this->id > 0)
		{
			$tags['address_image'] = new Tag('<img src="' . get_server_url() . '/' . ADDRESS_PICS_DIR . TNAILS_DIR . $this->addr_id . '.jpg">');
		}
		else
		{
			$tags['address_image'] = new Tag('<img src="images/sample_address.jpg">');
		}
		$tags['user_name'] = new Tag($_profile->user_name);
		$tags['user_id'] = new Tag($_profile->user_id);
		$tags['email'] = new Tag($email_addr);
		$tags['club_name'] = new Tag($this->club_name);
		$tags['club_id'] = new Tag($this->club_id);
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
	
	function load($tournament_id)
	{
		global $_profile, $_lang_code;
		if ($tournament_id <= 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
		}
		
		$user_id = -1;
		if ($_profile != NULL)
		{
			$user_id = $_profile->user_id;
		}
	
		$this->id = $tournament_id;
		list (
			$this->name, $this->price, $this->club_id, $this->club_name, $this->club_flags, $this->club_url, $timestamp, $this->duration,
			$this->addr_id, $this->addr, $this->addr_url, $timezone, $this->addr_flags,
			$this->notes, $this->langs, $this->flags, $this->rules_id, $this->scoring_id, $this->city, $this->country) =
				Db::record(
					get_label('tournament'), 
					'SELECT t.name, t.price, c.id, c.name, c.flags, c.web_site, t.start_time, t.duration, a.id, a.address, a.map_url, i.timezone, a.flags, t.notes, t.languages, t.flags, t.rules_id, t.scoring_id, i.name_' . $_lang_code . ', o.name_' . $_lang_code . ' FROM tournaments t' .
						' JOIN addresses a ON t.address_id = a.id' .
						' JOIN clubs c ON t.club_id = c.id' .
						' JOIN cities i ON a.city_id = i.id' .
						' JOIN countries o ON i.country_id = o.id' .
						' WHERE t.id = ?',
					$user_id, $tournament_id);
					
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
	}
	
	function get_full_name($with_club = false)
	{
		if ($with_club && $this->name != $this->club_name)
		{
			return get_label('[1] / [0]: [2]', $this->name, $this->club_name, format_date('D, M d, y', $this->timestamp, $this->timezone));
		}
		return get_label('[0]: [1]', $this->name, format_date('D, M d, y', $this->timestamp, $this->timezone));
	}
	
	static function show_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags)
	{
		global $_profile;

		$now = time();
		
		$no_buttons = true;
		if ($_profile != NULL && $id > 0 && ($club_flags & CLUB_FLAG_RETIRED) == 0)
		{
			$can_manage = false;
			
			if ($_profile->is_club_manager($club_id))
			{
				echo '<button class="icon" onclick="mr.editTournament(' . $id . ')" title="' . get_label('Edit the tournament') . '"><img src="images/edit.png" border="0"></button>';
				if ($start_time >= $now)
				{
					if (($flags & TOURNAMENT_FLAG_CANCELED) != 0)
					{
						echo '<button class="icon" onclick="mr.restoreTournament(' . $id . ')"><img src="images/undelete.png" border="0"></button>';
					}
					else
					{
						echo '<button class="icon" onclick="mr.cancelTournament(' . $id . ', \'' . get_label('Are you sure you want to cancel the tournament?') . '\')" title="' . get_label('Cancel the tournament') . '"><img src="images/delete.png" border="0"></button>';
					}
				}
				$no_buttons = false;
			}
		}
		echo '<button class="icon" onclick="window.open(\'tournament_screen.php?id=' . $id . '\' ,\'_blank\')" title="' . get_label('Open interactive standings page') . '"><img src="images/details.png" border="0"></button>';
	}
	
	function show_pic($dir, $width = 0, $height = 0, $alt_addr = true)
	{
		show_tournament_pic($this->id, $this->name, $this->flags, $this->addr_id, $this->addr, $this->addr_flags, $dir, $width, $height, $alt_addr);
	}
}

class TournamentPageBase extends PageBase
{
	protected $tournament;
	protected $is_manager;

	protected function prepare()
	{
		global $_profile;
		
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
		}
		
		$this->tournament = new Tournament();
		$this->tournament->load($_REQUEST['id']);
		$this->is_manager = ($_profile != NULL && $_profile->is_club_manager($this->tournament->club_id));
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%">';

		if ($this->tournament->timestamp < time())
		{
			$menu = array
			(
				new MenuItem('tournament_info.php?id=' . $this->tournament->id, get_label('Tournament'), get_label('General tournament information'))
				, new MenuItem('tournament_standings.php?id=' . $this->tournament->id, get_label('Standings'), get_label('Tournament standings'))
				, new MenuItem('tournament_competition.php?id=' . $this->tournament->id, get_label('Competition chart'), get_label('How players were competing on this tournament.'))
				, new MenuItem('tournament_games.php?id=' . $this->tournament->id, get_label('Games'), get_label('Games list of the tournament'))
				, new MenuItem('#stats', get_label('Stats'), NULL, array
				(
					new MenuItem('tournament_stats.php?id=' . $this->tournament->id, get_label('General stats'), get_label('General statistics. How many games played, mafia winning percentage, how many players, etc.', PRODUCT_NAME))
					, new MenuItem('tournament_by_numbers.php?id=' . $this->tournament->id, get_label('By numbers'), get_label('Statistics by table numbers. What is the most winning number, or what number is shot more often.'))
					, new MenuItem('tournament_nominations.php?id=' . $this->tournament->id, get_label('Nomination winners'), get_label('Custom nomination winners. For example who had most warnings, or who was checked by sheriff most often.'))
					, new MenuItem('tournament_moderators.php?id=' . $this->tournament->id, get_label('Moderators'), get_label('Moderators statistics of the tournament'))
				))
				, new MenuItem('#resources', get_label('Resources'), NULL, array
				(
					new MenuItem('tournament_albums.php?id=' . $this->tournament->id, get_label('Photos'), get_label('Tournament photo albums'))
					, new MenuItem('tournament_videos.php?id=' . $this->tournament->id . '&vtype=' . VIDEO_TYPE_GAME, get_label('Game videos'), get_label('Game videos from various tournaments.'))
					, new MenuItem('tournament_videos.php?id=' . $this->tournament->id . '&vtype=' . VIDEO_TYPE_LEARNING, get_label('Learning videos'), get_label('Masterclasses, lectures, seminars.'))
					// , new MenuItem('tournament_tasks.php?id=' . $this->tournament->id, get_label('Tasks'), get_label('Learning tasks and puzzles.'))
					// , new MenuItem('tournament_articles.php?id=' . $this->tournament->id, get_label('Articles'), get_label('Books and articles.'))
					// , new MenuItem('tournament_links.php?id=' . $this->tournament->id, get_label('Links'), get_label('Links to custom mafia web sites.'))
				))
			);
			echo '<tr><td colspan="4">';
			PageBase::show_menu($menu);
			echo '</td></tr>';
		}
		
		echo '<tr><td rowspan="2" valign="top" align="left" width="1">';
		echo '<table class="bordered ';
		if (($this->tournament->flags & TOURNAMENT_FLAG_CANCELED) != 0)
		{
			echo 'dark';
		}
		else
		{
			echo 'light';
		}
		echo '"><tr><td width="1" valign="top" style="padding:4px;" class="dark">';
		Tournament::show_buttons(
			$this->tournament->id,
			$this->tournament->timestamp,
			$this->tournament->duration,
			$this->tournament->flags,
			$this->tournament->club_id,
			$this->tournament->club_flags);
		echo '</td><td width="' . ICON_WIDTH . '" style="padding: 4px;">';
		if ($this->tournament->addr_url != '')
		{
			echo '<a href="address_info.php?bck=1&id=' . $this->tournament->addr_id . '">';
			$this->tournament->show_pic(TNAILS_DIR);
			echo '</a>';
		}
		else
		{
			$this->tournament->show_pic(TNAILS_DIR);
		}
		echo '</td></tr></table></td>';
		$title = get_label('Tournament [0]', $this->_title);
		
		echo '<td rowspan="2" valign="top"><h2 class="tournament">' . $title . '</h2><br><h3>' . $this->tournament->name;
		$time = time();
		echo '</h3><p class="subtitle">' . format_date('l, F d, Y, H:i', $this->tournament->timestamp, $this->tournament->timezone) . '</p></td>';
		
		echo '<td valign="top" align="right">';
		show_back_button();
		echo '</td></tr><tr><td align="right" valign="bottom"><a href="club_main.php?bck=1&id=' . $this->tournament->club_id . '" title="' . $this->tournament->club_name . '"><table><tr><td align="center">' . $this->tournament->club_name . '</td></tr><tr><td align="center">';
		show_club_pic($this->tournament->club_id, $this->tournament->club_name, $this->tournament->club_flags, ICONS_DIR);
		echo '</td></tr></table></a></td></tr>';
		
		echo '</table>';
	}
}
	
?>