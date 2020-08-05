<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/game_stats.php';
require_once '../../include/datetime.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	function get_address_id($club, $old_address_id)
	{
		$address_id = get_optional_param('address_id', -1);
		$timezone = $club->timezone;
		if ($address_id <= 0)
		{
			$address = get_optional_param('address', NULL);
			if ($address != NULL)
			{
				$city_id = get_optional_param('city_id', -1);
				if ($city_id <= 0)
				{
					$country_id = get_optional_param('country_id', NULL);
					if ($country_id <= 0)
					{
						$country = get_optional_param('country', NULL);
						if ($country != NULL)
						{
							$country_id = retrieve_country_id($country);
						}
						else
						{
							$country_id = $club->country_id;
						}
					}
					
					$city = get_optional_param('city', NULL);
					if ($city != NULL)
					{
						$city_id = retrieve_city_id($city, $country_id, $timezone);
					}
					else
					{
						$city_id = $club->city_id;
					}
				}
				
				list($timezone) = Db::record(get_label('city'), 'SELECT timezone FROM cities WHERE id = ?', $city_id);
				$sc_address = htmlspecialchars($address, ENT_QUOTES);
				check_address_name($sc_address, $club->id);
		
				Db::exec(
					get_label('address'), 
					'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
					$sc_address, $club->id, $sc_address, $city_id);
				list ($address_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
				
				$log_details = new stdClass();
				$log_details->name = $sc_address;
				$log_details->address = $sc_address;
				$log_details->city_id = $city_id;
				db_log(LOG_OBJECT_ADDRESS, 'created', $log_details, $address_id, $club->id);
		
				$warning = load_map_info($address_id);
				if ($warning != NULL)
				{
					echo '<p>' . $warning . '</p>';
				}
			}
			else
			{
				$address_id = $old_address_id;
			}
		}
		else if ($address_id != $old_address_id)
		{
			list($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON a.city_id = c.id WHERE a.id = ?', $address_id);
		}
		return array($address_id, $timezone);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		$tournament_id = get_optional_param('tournament_id', 0);
		if ($tournament_id <= 0)
		{
			$club_id = (int)get_required_param('club_id');
			$tournament_id = NULL;
		}
		else
		{
			list($club_id) = db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $tournament_id);
		}
		
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_required_param('name');
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('event name')));
		}

		$start = get_required_param('start');
		$duration = (int)get_required_param('duration');
		$price = get_optional_param('price', '');
		$rules_code = get_optional_param('rules_code', $club->rules_code);
		$scoring_id = (int)get_optional_param('scoring_id', $club->scoring_id);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$scoring_options = get_optional_param('scoring_options', '{}');
		$notes = get_optional_param('notes', '');
		
		$editable_mask = EVENT_EDITABLE_MASK;
		$flags = (int)get_optional_param('flags', EVENT_FLAG_ALL_MODERATE);// & $editable_mask;
		
		$langs = get_optional_param('langs', 0);
		if (($langs & LANG_ALL) == 0)
		{
			throw new Exc(get_label('No languages specified.'));
		}
		
		Db::begin();
		if (!is_null($tournament_id))
		{
			list ($scoring_id, $scoring_version, $rules_code) = Db::record(get_label('tournament'), 'SELECT scoring_id, scoring_version, rules FROM tournaments WHERE id = ?', $tournament_id);
		}
		else if ($scoring_version < 0)
		{
			list ($scoring_version) = Db::record(get_label('scoring'), 'SELECT version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
		}
		
		list($address_id, $timezone) = $this->get_address_id($club, -1);
		if ($address_id <= 0)
		{
			throw new Exc(get_label('Please enter [0]', get_label('address')));
		}
		
		$log_details = new stdClass();
		if (!is_null($tournament_id))
		{
			$log_details->tournament_id = $tournament_id;
		}
		$log_details->name = $name;
		$log_details->price = $price;
		$log_details->address_id = $address_id;
		$log_details->duration = $duration;
		$log_details->flags = $flags;
		$log_details->langs = $langs;
		$log_details->rules_code = $rules_code;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->scoring_options = $scoring_options;
		
		$event_ids = array();
		$start_datetime = get_datetime($start, $timezone);
		if (isset($_REQUEST['weekdays']))
		{
			$weekdays = (int)$_REQUEST['weekdays'];
			if (($weekdays & WEEK_FLAG_ALL) == 0)
			{
				throw new Exc(get_label('Please enter at least one weekday.'));
			}
			
			$event_ids = array();
			$end = get_required_param('end');
			$end_datetime = get_datetime($end, $timezone);
			$interval = new DateInterval('P1D');
			$weekday = (1 << $start_datetime->format('w'));
			while ($start_datetime->getTimestamp() < $end_datetime->getTimestamp())
			{
				if (($weekdays & $weekday) != 0)
				{
					Db::exec(
						get_label('event'), 
						'INSERT INTO events (name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules, scoring_id, scoring_version, scoring_options, tournament_id) ' .
						'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$name, $price, $address_id, $club_id, $start_datetime->getTimestamp(), 
						$notes, $duration, $flags, $langs, $rules_code, 
						$scoring_id, $scoring_version, $scoring_options, $tournament_id);
					list ($event_id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
					
					$log_details->start = $start_datetime->format('d/m/y H:i');
					db_log(LOG_OBJECT_EVENT, 'created', $log_details, $event_id, $club_id);
					
					$event_ids[] = $event_id;
				}
				$start_datetime->add($interval);
				$weekday <<= 1;
				if ($weekday > WEEK_FLAG_ALL)
				{
					$weekday = 1;
				}
			}
			
			if (count($event_ids) == 0)
			{
				throw new Exc(get_label('No events found between [0] and [1].', $start, $end));
			}
		}
		else
		{
			if ($start_datetime->getTimestamp() + $duration < time())
			{
				throw new Exc(get_label('You can not create event in the past. Please check the date.'));
			}
			
			Db::exec(
				get_label('event'), 
				'INSERT INTO events (name, price, address_id, club_id, start_time, notes, duration, flags, languages, rules, scoring_id, scoring_version, scoring_options, tournament_id) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$name, $price, $address_id, $club_id, $start_datetime->getTimestamp(), 
				$notes, $duration, $flags, $langs, $rules_code, 
				$scoring_id, $scoring_version, $scoring_options, $tournament_id);
			list ($event_id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
			
			$log_details->start = $start;
			db_log(LOG_OBJECT_EVENT, 'created', $log_details, $event_id, $club_id);
			
			$event_ids[] = $event_id;
		}
		Db::commit();
		
		$this->response['events'] = $event_ids;
		$this->response['mailing'] = EVENT_EMAIL_INVITE;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create event.');
		$help->request_param('club_id', 'Club id.', 'tournament_id must be set.');
		$help->request_param('tournament_id', 'Tournament id. When set the event becomes a tournament round.', 'club_id must be set.');
		$help->request_param('name', 'Event name.');
		$help->request_param('month', 'Month of the event.');
		$help->request_param('day', 'Day of the month of the event.');
		$help->request_param('year', 'Year of the event.');
		$help->request_param('hour', 'Hour when the event starts.');
		$help->request_param('minute', 'Minute when the event starts.');
		$help->request_param('duration', 'Event duration in seconds.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'empty.');
		$help->request_param('rules_code', 'Rules for this event.', 'default club rules are used.');
		$help->request_param('scoring_id', 'Scoring id for this event.', 'default club scoring system is used.');
		$help->request_param('scoring_version', 'Scoring version for this event.', 'the latest version of the system identified by scoring_id is used.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this event.', 'null is used. All values are assumed to be default.'));
		$help->request_param('notes', 'Event notes. Just a text.', 'empty.');
		$help->request_param('flags', 'Bit combination of the next flags.
				<ol>
					<li value="1">the event should not be shown in the list of upcoming events on the site.</li>
					<li value="2">the event should not be shown in the list of past events on the site.</li>
					<li value="4">all registered users can moderate games.</li>
				</ol>', '4.');
		$help->request_param('langs', 'Languages on this event. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'all club languages are used.');
		$help->request_param('address_id', 'Address id of the event.', '<q>address</q>, <q>city</q>, and <q>country</q> are used to create new address.');
		$help->request_param('address', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', '<q>address_id</q> must be set');
		$help->request_param('country', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', '<q>address_id</q> must be set');
		$help->request_param('city', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', '<q>address_id</q> must be set');
		$help->request_param('weekdays', 'When set, multiple events are created. This is a bit combination of weekdays. When it is set, <?php echo PRODUCT_NAME; ?> creates events between the start date and end date at all weekdays that are set. The flags are:
				<ol>
					<li value="1">Sunday</li>
					<li value="2">Monday</li>
					<li value="4">Tuesday</li>
					<li value="8">Wednesday</li>
					<li value="16">Thursday</li>
					<li value="32">Friday</li>
					<li value="64">Saturday</li>
				</ol>', 'single event is created.');
		$help->request_param('to_month', 'When creating multiple events (<q>weekdays</q> is set) this is the month of the end date.', '<q>weekdays</q> must also be not set');
		$help->request_param('to_day', 'When creating multiple events (<q>weekdays</q> is set) this is the day of the month of the end date.', '<q>weekdays</q> must also be not set');
		$help->request_param('to_year', 'When creating multiple events (<q>weekdays</q> is set) this is the year of the end date.', '<q>weekdays</q> must also be not set');
		$help->response_param('events', 'Array of ids of the newly created events.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $old_name, $old_tournament_id, $old_start_timestamp, $old_duration, $old_address_id, $old_price, $old_rules_code, $old_scoring_id, $old_scoring_version, $old_scoring_options, $old_langs, $old_notes, $old_flags, $timezone) = 
			Db::record(get_label('event'), 
				'SELECT e.club_id, e.name, e.tournament_id, e.start_time, e.duration, e.address_id, e.price, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, e.languages, e.notes, e.flags, c.timezone ' .
				'FROM events e ' . 
				'JOIN addresses a ON a.id = e.address_id ' . 
				'JOIN cities c ON c.id = a.city_id ' . 
				'WHERE e.id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];

		$name = get_optional_param('name', $old_name);
		$tournament_id = get_optional_param('tournament_id', $old_tournament_id);
		if ($tournament_id <= 0)
		{
			$tournament_id = NULL;
		}
		
		$start = get_optional_param('start', $old_start_timestamp);
		$duration = (int)get_optional_param('duration', $old_duration);
		$price = get_optional_param('price', $old_price);
		$scoring_id = (int)get_optional_param('scoring_id', $old_scoring_id);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$scoring_options = get_optional_param('scoring_options', $old_scoring_options);
		$notes = get_optional_param('notes', $old_notes);
		
		
		$rules_code = get_optional_param('rules_code', $old_rules_code);
		check_rules_code($rules_code);
		
		$editable_mask = EVENT_EDITABLE_MASK;
		$flags = (int)get_optional_param('flags', $old_flags);
		$flags = ($flags & $editable_mask) + ($old_flags & ~$editable_mask);
		
		$langs = get_optional_param('langs', $old_langs);
		if (($langs & LANG_ALL) == 0)
		{
			throw new Exc(get_label('No languages specified.'));
		}
		
		list($address_id, $timezone) = $this->get_address_id($club, $old_address_id);
		$start_datetime = get_datetime($start, $timezone);
		$start_timestamp = $start_datetime->getTimestamp();
		
		if ($tournament_id != $old_tournament_id && !is_null($tournament_id))
		{
			list ($scoring_id, $scoring_version, $rules_code) = Db::record(get_label('tournament'), 'SELECT scoring_id, scoring_version, rules FROM tournaments WHERE id = ?', $tournament_id);
		}
		else if ($scoring_version <= 0)
		{
			if ($scoring_id != $old_scoring_id)
			{
				list ($scoring_version) = Db::record(get_label('scoring'), 'SELECT version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
			}
			else
			{
				$scoring_version = $old_scoring_version;
			}
		}
		
		Db::exec(
			get_label('event'), 
			'UPDATE events SET ' .
				'name = ?, tournament_id = ?, price = ?, rules = ?, scoring_id = ?, scoring_version = ?, scoring_options = ?, ' .
				'address_id = ?, start_time = ?, notes = ?, duration = ?, flags = ?, ' .
				'languages = ? WHERE id = ?',
			$name, $tournament_id, $price, $rules_code, $scoring_id, $scoring_version, $scoring_options,
			$address_id, $start_timestamp, $notes, $duration, $flags,
			$langs, $event_id);
		
		if (Db::affected_rows() > 0)
		{
			list ($addr_name, $timezone) = Db::record(get_label('address'), 'SELECT a.name, c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $address_id);
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($tournament_id != $old_tournament_id)
			{
				$log_details->tournament_id = $tournament_id;
			}
			if ($price != $old_price)
			{
				$log_details->price = $price;
			}
			if ($address_id != $old_address_id)
			{
				$log_details->address_id = $address_id;
			}
			if ($start_timestamp != $old_start_timestamp)
			{
				$log_details->start_timestamp = $start_timestamp;
			}
			if ($duration != $old_duration)
			{
				$log_details->duration = $duration;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			if ($rules_code != $old_rules_code)
			{
				$log_details->rules_code = $rules_code;
			}
			if ($scoring_id != $old_scoring_id || $scoring_version != $old_scoring_version)
			{
				$log_details->scoring_id = $scoring_id;
				$log_details->scoring_version = $scoring_version;
			}
			if ($scoring_options != $old_scoring_options)
			{
				$log_details->scoring_options = $scoring_options;
			}
			db_log(LOG_OBJECT_EVENT, 'changed', $log_details, $event_id, $club_id);
		}
		
		if ($start_timestamp != $old_start_timestamp || $duration != $old_duration)
		{
			Db::exec(
				get_label('registration'), 
				'UPDATE registrations SET start_time = ?, duration = ? WHERE event_id = ?',
				$start_timestamp, $duration, $event_id);
		}
		Db::commit();
		
		if ($address_id != $old_address_id)
		{
			$this->response['mailing'] = EVENT_EMAIL_CHANGE_ADDRESS;
		}
		else if ($start_timestamp != $old_start_timestamp)
		{
			$this->response['mailing'] = EVENT_EMAIL_CHANGE_TIME;
		}
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('tournament_id', 'Tournament id. When set the event becomes a tournament round.', 'remains the same.');
		$help->request_param('name', 'Event name.', 'remains the same.');
		$help->request_param('start', 'Event start time. It is either unix timestamp or datetime in the format "yyyy-mm-dd hh:00". Timezone of the address is used.', 'remains the same.');
		$help->request_param('duration', 'Event duration in seconds.', 'remains the same.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'remains the same.');
		$help->request_param('rules_code', 'Rules for this event.', 'remain the same.');
		$help->request_param('scoring_id', 'Scoring id for this event.', 'remain the same.');
		$help->request_param('scoring_version', 'Scoring version for this event.', 'remain the same, or set to the latest for current scoring if scoring_id is changed.');
		api_scoring_help($help->request_param('scoring_options', 'Scoring options for this event.', 'remain the same.'));
		$help->request_param('notes', 'Event notes. Just a text.', 'empty.', 'remain the same.');
		$help->request_param('flags', 'Bit combination of the next flags.
				<ol>
					<li value="1">the event should not be shown in the list of upcoming events on the site.</li>
					<li value="2">the event should not be shown in the list of past events on the site.</li>
					<li value="4">all registered users can moderate games.</li>
				</0l>', 'remain the same.');
		$help->request_param('langs', 'Languages on this event. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remain the same.');
		$help->request_param('address_id', 'Address id of the event.', '<q>address</q> is used to create new address.');
		$help->request_param('address', 'When <q>address_id</q> is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', 'address remains the same');
		$help->request_param('country_id', 'When <q>address_id<q> is not set, and <q>address</q> is set - this is the country id for the new address.', '<q>country</q> parameter is used to create new country for the address.');
		$help->request_param('country', 'When <q>address_id</q> is not set, and <q>address</q> is set, and <q>country_id</q> is not set - this is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', 'club country is used for the new address.');
		$help->request_param('city_id', 'When <q>address_id<q> is not set, and <q>address</q> is set - this is the city id for the new address.', '<q>city</q> parameter is used to create new city for the address.');
		$help->request_param('city', 'When <q>address_id</q> is not set, and <q>address</q> is set, and <q>city_id</q> is not set - this is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', 'club city is used for the new address.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// attend
	//-------------------------------------------------------------------------------------------------------
	function attend_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$event_id = (int)get_required_param('event_id');
		
		$odds = 100;
		if (isset($_REQUEST['odds']))
		{
			$odds = min(max((int)$_REQUEST['odds'], 0), 100);
		}
		
		$late = 0;
		if (isset($_REQUEST['late']))
		{
			$late = $_REQUEST['late'];
		}
		
		$friends = 0;
		if (isset($_REQUEST['friends']))
		{
			$friends = $_REQUEST['friends'];
		}
		
		$nickname = '';
		if (isset($_REQUEST['nickname']))
		{
			$nickname = $_REQUEST['nickname'];
		}
		
		Db::begin();
		
		Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
		Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
		Db::exec(get_label('registration'), 
			'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me, late) VALUES (?, ?, ?, ?, ?)',
			$event_id, $_profile->user_id, $odds, $friends, $late);
		
		if ($odds >= 100)
		{
			if (empty($nickname))
			{
				$nickname = $_profile->user_name;
			}
		
			check_nickname($nickname, $event_id);
			Db::exec(get_label('registration'),
				'INSERT INTO registrations (club_id, user_id, nick_name, duration, start_time, event_id) ' . 
				'SELECT club_id, ?, ?, duration, start_time, id FROM events WHERE id = ?',
				$_profile->user_id, $nickname, $event_id);
		}
		Db::commit();
	}
	
	function attend_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Tell the system about the plans to attend the upcoming event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('odds', 'The odds of coming. An integer from 0 to 100. Sending 0 means that current user is not planning to attend the event. If odds are 100, the user gets registered for the event.', '100% is used.');
		$help->request_param('late', 'I current user can not be in time, this is how much late will he/she be in munutes.', 'user is assumed to be in time.');
		$help->request_param('friends', 'How many friends are coming with the current user.', '0 is used.');
		$help->request_param('nickname', 'Nickname for the event. If it is set and not empty, the user is registered for the event even if the odds are not 100%.', 'nickname is the same as user name.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// TODO: replace with the get api
	// get 
	//-------------------------------------------------------------------------------------------------------
	function get_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		check_permissions(PERMISSION_CLUB_MEMBER, $event->club_id);
		
		$date_format = '';
		if (isset($_REQUEST['date_format']))
		{
			$date_format = $_REQUEST['df'];
		}

		$time_format = '';
		if (isset($_REQUEST['time_format']))
		{
			$time_format = $_REQUEST['tf'];
		}

		$this->response['id'] = $event->id;
		$this->response['name'] = $event->name;
		$this->response['price'] = $event->price;
		$this->response['club_id'] = $event->club_id;
		$this->response['club_name'] = $event->club_name;
		$this->response['club_url'] = $event->club_url;
		$this->response['start'] = $event->timestamp;
		$this->response['duration'] = $event->duration;
		$this->response['addr_id'] = $event->addr_id;
		$this->response['addr'] = $event->addr;
		$this->response['addr_url'] = $event->addr_url;
		$this->response['timezone'] = $event->timezone;
		$this->response['city'] = $event->city;
		$this->response['country'] = $event->country;
		$this->response['notes'] = $event->notes;
		$this->response['langs'] = $event->langs;
		$this->response['flags'] = $event->flags;
		$this->response['rules_code'] = $event->rules_code;
		$this->response['scoring_id'] = $event->scoring_id;
		$this->response['scoring_version'] = $event->scoring_version;
		$this->response['scoring_options'] = $event->scoring_options;
		
		$base = get_server_url() . '/';
		if (($event->addr_flags & ADDRESS_ICON_MASK) != 0)
		{
			$this->response['addr_image'] = $base . ADDRESS_PICS_DIR . TNAILS_DIR . $event->addr_id . '.jpg';
		}
		
		$this->response['date_str'] = format_date($date_format, $event->timestamp, $event->timezone);
		$this->response['time_str'] = format_date($time_format, $event->timestamp, $event->timezone);
		
		date_default_timezone_set($event->timezone);
		$this->response['hour'] = date('G', $event->timestamp);
		$this->response['minute'] = round(date('i', $event->timestamp) / 10) * 10;
	}
	
	function get_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MEMBER, 'Get event details. TODO: it should be moved to <q>get</q> API.');
		$help->request_param('event_id', 'Event id.');
		$help->response_param('id', 'Event id.');
		$help->response_param('name', 'Event name.');
		$help->response_param('price', 'Admission rate.');
		$help->response_param('club_id', 'Club id.');
		$help->response_param('club_name', 'Club name.');
		$help->response_param('club_url', 'Club URL.');
		$help->response_param('start', 'Unix timestamp for the start time.');
		$help->response_param('duration', 'Event duration in seconds.');
		$help->response_param('addr_id', 'Address id.');
		$help->response_param('addr', 'Event address.');
		$help->response_param('addr_url', 'Address url.');
		
		$timezone_help = 'Event timezone. One of: <select>';
		$zones = DateTimeZone::listIdentifiers();
		foreach ($zones as $zone)
		{
			$timezone_help .= '<option>' . $zone . '</option>';
		}
		$timezone_help .= '</select>';
		
		$help->response_param('timezone', $timezone_help);
		$help->response_param('city', 'Event city name using default language.');
		$help->response_param('country', 'Event country name using default language.');
		$help->response_param('notes', 'Event notes.');
		$help->response_param('langs', 'Event languages. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.');
		$help->response_param('rules_code', 'Game rules code.');
		$help->response_param('scoring_id', 'Scoring system id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// extend
	//-------------------------------------------------------------------------------------------------------
	function extend_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		check_permissions(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, $event->club_id);
		
		if ($event->timestamp + $event->duration + EVENT_ALIVE_TIME < time())
		{
			throw new Exc(get_label('The event is too old. It can not be extended.'));
		}
		
		$duration = (int)get_required_param('duration');
		Db::begin();
		Db::exec(get_label('event'), 'UPDATE events SET duration = ? WHERE id = ?', $duration, $event->id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->duration = $duration;
			db_log(LOG_OBJECT_EVENT, 'extended', $log_details, $event->id, $event->club_id);
		}
		Db::commit();
	}
	
	function extend_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, 'Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('duration', 'New event duration. Send 0 if you want to end event now.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// cancel
	//-------------------------------------------------------------------------------------------------------
	function cancel_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $event->club_id);
		
		Db::begin();
		list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags | ' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EVENT, 'canceled', NULL, $event_id, $club_id);
		}
		
		$query = new DbQuery('SELECT id FROM event_mailings WHERE event_id = ? AND status = ?', $event_id, MAILING_WAITING);
		while ($row = $query->next())
		{
			list ($mailing_id) = $row;
			Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE id = ?', $mailing_id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_EVENT_MAILINGS, 'deleted', NULL, $mailing_id, $club_id);
			}
		}
		Db::commit();
	}
	
	function cancel_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Cancel event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $event->club_id);
		
		Db::begin();
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
			db_log(LOG_OBJECT_EVENT, 'restored', NULL, $event_id, $club_id);
		}
		
		$query = new DbQuery('SELECT id FROM event_mailings WHERE event_id = ? AND status = ? AND type = ?', $event_id, MAILING_WAITING, EVENT_EMAIL_CANCEL);
		while ($row = $query->next())
		{
			list ($mailing_id) = $row;
			Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE id = ?', $mailing_id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_EVENT_MAILINGS, 'deleted', NULL, $mailing_id, $club_id);
			}
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Restore canceled event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_player
	//-------------------------------------------------------------------------------------------------------
	function change_player_op()
	{
		$event_id = (int)get_required_param('event_id');
		$user_id = (int)get_required_param('user_id');
		$new_user_id = (int)get_optional_param('new_user_id', $user_id);
		$nickname = get_optional_param('nick', NULL);
		$changed = false;
		
		list($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		if ($nickname == NULL)
		{
			list($nickname) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $new_user_id);
		}
		
		$query = new DbQuery('SELECT id, log, canceled FROM games WHERE event_id = ? AND result > 0', $event_id);
		while ($row = $query->next())
		{
			list ($game_id, $game_log, $is_canceled) = $row;
			$gs = new GameState();
			$gs->init_existing($game_id, $game_log, $is_canceled);
			if ($gs->change_user($user_id, $new_user_id, $nickname))
			{
				rebuild_game_stats($gs);
				Db::exec(get_label('game'), 'INSERT INTO rebuild_stats (time, action, email_sent) VALUES (UNIX_TIMESTAMP(), ?, 0)', 'Game ' . $game_id . ' is changed');
				$changed = true;
			}
		}
		
		list($new_user_registration) = Db::record(get_label('registration'), 'SELECT count(*) FROM registrations WHERE user_id = ? AND event_id = ?', $new_user_id, $event_id);
		if ($new_user_registration > 0)
		{
			Db::exec(get_label('registration'), 'UPDATE registrations SET nick_name = ? WHERE user_id = ? AND event_id = ?', $nickname, $new_user_id, $event_id);
			if (Db::affected_rows() > 0)
			{
				$changed = true;
			}
			
			Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
			if (Db::affected_rows() > 0)
			{
				$changed = true;
			}
		}
		else
		{
			Db::exec(get_label('registration'), 'UPDATE registrations SET user_id = ?, nick_name = ? WHERE user_id = ? AND event_id = ?', $new_user_id, $nickname, $user_id, $event_id);
			if (Db::affected_rows() == 0)
			{
				Db::exec(get_label('registration'), 'INSERT INTO registrations (club_id, user_id, nick_name, event_id) VALUES (?, ?, ?, ?)', $club_id, $new_user_id, $nickname, $event_id);
			}
			$changed = true;
		}
		
		if ($changed)
		{
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			$log_details->old_user_id = $user_id;
			$log_details->nickname = $nickname;
			db_log(LOG_OBJECT_USER, 'replaced', $log_details, $new_user_id);
		}
		Db::commit();
	}
	
	function change_player_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change player on the event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('user_id', 'User id of a player who played on this event. It can be negative for temporary players.');
		$help->request_param('new_user_id', 'If it is different from user_id, player is replaced in this event with the player new_user_id. If it is 0 or negative, user is replaced with a temporary player existing for this event only.', 'user_id is used.');
		$help->request_param('nick', 'Nickname for this event. If it is empty, user name is used.', 'user name is used.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// add_extra_points
	//-------------------------------------------------------------------------------------------------------
	function add_extra_points_op()
	{
		$event_id = (int)get_required_param('event_id');
		$user_id = (int)get_required_param('user_id');
		$reason = get_required_param('reason');
		$details = get_optional_param('details');
		$points = (float)get_required_param('points');
		
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		Db::begin();
		list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('points'), 'INSERT INTO event_extra_points (time, event_id, user_id, reason, details, points) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?, ?)', $event_id, $user_id, $reason, $details, $points);
		list ($points_id) = Db::record(get_label('points'), 'SELECT LAST_INSERT_ID()');
		
		list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
		$log_details = new stdClass();
		$log_details->user = $user_name;
		$log_details->user_is = $user_id;
		$log_details->event_id = $event_id;
		$log_details->points = $points;
		$log_details->reason = $reason;
		if (!empty($details))
		{
			$log_details->details = $details;
		}
		db_log(LOG_OBJECT_EXTRA_POINTS, 'created', $log_details, $points_id, $club_id);
		Db::commit();
		
		$this->response['points_id'] = $points_id;
	}
	
	function add_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Add extra points.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('user_id', 'User id. The user who is receiving or loosing points.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract. Zero means: add average points per game for this event.');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'empty.');
		
		$help->response_param('points_id', 'Id of the created extra points object.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_extra_points
	//-------------------------------------------------------------------------------------------------------
	function change_extra_points_op()
	{
		$points_id = (int)get_required_param('points_id');
		
		Db::begin();
		list($user_id, $event_id, $club_id, $old_reason, $old_details, $old_points) = 
			Db::record(get_label('points'), 'SELECT p.user_id, p.event_id, e.club_id, p.reason, p.details, p.points FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE p.id = ?', $points_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$reason = get_optional_param('reason', $old_reason);
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		$details = get_optional_param('details', $old_details);
		$points = (float)get_optional_param('points', $old_points);
		
		Db::exec(get_label('points'), 'UPDATE event_extra_points SET reason = ?, details = ?, points = ? WHERE id = ?', $reason, $details, $points, $points_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($reason != $old_reason)
			{
				$log_details->reason = $reason;
			}
			if ($details != $old_details)
			{
				$log_details->details = $details;
			}
			if ($points != $old_points)
			{
				$log_details->points = $points;
			}
			db_log(LOG_OBJECT_EXTRA_POINTS, 'changed', $log_details, $points_id, $club_id);
		}
		Db::commit();
	}
	
	function change_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract. Zero means: add average points per game for this event.', 'remains the same');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.', 'remains the same');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'remains the same');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_extra_points
	//-------------------------------------------------------------------------------------------------------
	function delete_extra_points_op()
	{
		$points_id = (int)get_required_param('points_id');
		
		Db::begin();
		list($club_id) = Db::record(get_label('points'), 'SELECT e.club_id FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE p.id = ?', $points_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('points'), 'DELETE FROM event_extra_points WHERE id = ?', $points_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EXTRA_POINTS, 'deleted', NULL, $points_id, $club_id);
		}
		Db::commit();
	}
	
	function delete_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create_mailing
	//-------------------------------------------------------------------------------------------------------
	function create_mailing_op()
	{
		$events = get_required_param('events');
		$type = (int)get_optional_param('type', EVENT_EMAIL_INVITE);
		if ($type < 0 || $type >= EVENT_EMAIL_COUNT)
		{
			throw new Exc(get_label('Invalid email type.'));
		}
		
		$time = (int)get_required_param('time');
		if ($time <= 0)
		{
			throw new Exc(get_label('Invalid send time.'));
		}
		
		$flags = (int)get_optional_param('flags', MAILING_FLAG_TO_ATTENDED | MAILING_FLAG_TO_DESIDING);
		if ($flags <= 0)
		{
			throw new Exc(get_label('No recipients.'));
		}
		
		$langs = 0;
		$event_ids = explode(',', $events);
		if (count($event_ids) <= 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('event')));
		}
		
		$langs = 0;
		foreach ($event_ids as $event_id)
		{
			list($club_id, $lgs) = Db::record(get_label('event'), 'SELECT club_id, languages FROM events WHERE id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			$langs |= $lgs;
		}
		$langs = (int)get_optional_param('langs', $langs);
		if (($langs & LANG_ALL) == 0)
		{
			throw new Exc(get_label('No recipients.'));
		}
		
		$mailing_ids = array();
		Db::begin();
		foreach ($event_ids as $event_id)
		{
			Db::exec(get_label('mailing'), 'INSERT INTO event_mailings (event_id, send_time, status, flags, langs, type) VALUES (?, ?, ' . MAILING_WAITING . ', ?, ?, ?)', $event_id, $time, $flags, $langs, $type);
			list ($mailing_id) = Db::record(get_label('mailing'), 'SELECT LAST_INSERT_ID()');
			list ($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
					
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			$log_details->send_time = $time;
			$log_details->flags = $flags;
			$log_details->langs = $langs;
			$log_details->type = $type;
			db_log(LOG_OBJECT_EVENT_MAILINGS, 'created', $log_details, $mailing_id, $club_id);
			
			$mailing_ids[] = $mailing_id;
		}
		Db::commit();
		$this->response['mailings'] = $event_ids;
	}
	
	function create_mailing_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, '');
		$help->request_param('events', 'Coma separated array of ids of the events. Mailings will be created for all these events. Examples: "1123,1124,1125", "1123", "1123, 1124".');
		$help->request_param('type', 'Mailing type. Possible values are:<ol><li value="0">invitation email.</li><li>notifiaction that the event has been canceled.</li><li>notification that start time has been changed.</li><li>notification that address has been changed.</li></ol>', '0 (invitation) is used');
		$help->request_param('time', 'When the emails should be sent. In seconds before the event start.');
		$help->request_param('flags', 'To whom the emails should be sent. An integer containing bit combination of:<ol><li value="2">to players who are attending the event.</li><li value="4">to players who declined to attend the event.</li><li value="8">to players who are still deciding whether to attend.</li></ol>', '10 (attanded & deciding) is used');
		$help->request_param('langs', 'To whom the emails should be sent. An integer containing bit combination of:<ol><li value="1">to players who speak English.</li><li value="2">to players who speak Russian.</li></ol>', 'event languages are used');
		$help->response_param('mailings', 'Array of ids of the newly created mailings.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change_mailing
	//-------------------------------------------------------------------------------------------------------
	function change_mailing_op()
	{
		$mailing_id = (int)get_required_param('mailing_id');
		list($club_id, $old_time, $status, $old_flags, $old_langs, $old_type) = Db::record(get_label('mailing'), 'SELECT e.club_id, m.send_time, m.status, m.flags, m.langs, m.type FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		if ($status != MAILING_WAITING)
		{
			throw new Exc(get_label('Can not change mailing. Some emails are already sent.', get_label('mailing')));
		}
		
		$type = (int)get_optional_param('type', $old_type);
		if ($type < 0 || $type >= EVENT_EMAIL_COUNT)
		{
			throw new Exc(get_label('Invalid email type.'));
		}
		
		$time = (int)get_optional_param('time', $old_time);
		if ($time <= 0)
		{
			throw new Exc(get_label('Invalid send time.'));
		}
		
		$flags = (int)get_optional_param('flags', $old_flags);
		if ($flags <= 0)
		{
			throw new Exc(get_label('No recipients.'));
		}
		
		$langs = (int)get_optional_param('langs', $old_langs);
		if (($langs & LANG_ALL) == 0)
		{
			throw new Exc(get_label('No recipients.'));
		}
		
		Db::begin();
		Db::exec(get_label('mailing'), 'UPDATE event_mailings SET type = ?, send_time = ?, flags = ?, langs = ? WHERE id = ?', $type, $time, $flags, $langs, $mailing_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($type != $old_type)
			{
				$log_details->type = $type;
			}
			if ($time != $old_time)
			{
				$log_details->time = $time;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			db_log(LOG_OBJECT_EVENT_MAILINGS, 'changed', $log_details, $mailing_id, $club_id);
		}
		Db::commit();
	}
	
	function change_mailing_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, '');
		$help->request_param('mailing_id', 'Id of the event mailing.');
		$help->request_param('type', 'Mailing type. Possible values are:<ol><li value="0">invitation email.</li><li>notifiaction that the event has been canceled.</li><li>notification that start time has been changed.</li><li>notification that address has been changed.</li></ol>', 'remains the same.');
		$help->request_param('time', 'When the emails should be sent. In seconds before the event start.', 'remains the same.');
		$help->request_param('flags', 'To whom the emails should be sent. An integer containing bit combination of:<ol><li value="2">to players who are attending the event.</li><li value="4">to players who declined to attend the event.</li><li value="8">to players who are still deciding whether to attend.</li></ol>', 'remains the same.');
		$help->request_param('langs', 'To whom the emails should be sent. An integer containing bit combination of:<ol><li value="1">to players who speak English.</li><li value="2">to players who speak Russian.</li></ol>', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete_mailing
	//-------------------------------------------------------------------------------------------------------
	function delete_mailing_op()
	{
		$mailing_id = (int)get_required_param('mailing_id');
		list($club_id) = Db::record(get_label('mailing'), 'SELECT e.club_id FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE id = ?', $mailing_id);
		db_log(LOG_OBJECT_EVENT_MAILINGS, 'deleted', new stdClass(), $mailing_id, $club_id);
		Db::commit();
	}
	
	function delete_mailing_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, '');
		$help->request_param('mailing_id', 'Id of the event mailing.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// convert_to_tournament
	//-------------------------------------------------------------------------------------------------------
	function convert_to_tournament_op()
	{
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $name, $address_id, $start_time, $duration, $langs, $notes, $price, $scoring_id, $scoring_version, $scoring_options, $rules, $flags, $tournament_id) = 
			Db::record(get_label('event'), 'SELECT club_id, name, address_id, start_time, duration, languages, notes, price, scoring_id, scoring_version, scoring_options, rules, flags, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		if (!is_null($tournament_id))
		{
			throw new Exc(get_label('Event [0] is already a tournament round.', $name));
		}
		
		if (($flags & EVENT_FLAG_CANCELED) != 0)
		{
			throw new Exc(get_label('Event [0] is canceled.'));
		}
		
		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, scoring_version, scoring_options, rules, flags, stars) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $club_id, $address_id, $start_time, $duration, $langs, $notes, $price, $scoring_id, $scoring_version, $scoring_options, $rules, 0, 0);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
			
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start_time;
		$log_details->duration = $duration;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->price = $price;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->scoring_options = $scoring_options;
		$log_details->rules_code = $rules;
		$log_details->flags = 0;
		$log_details->stars = 0;
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id);
			
		$name = get_label('main round');
		$flags |= EVENT_MASK_HIDDEN;
		Db::exec(
			get_label('event'), 
			'UPDATE events SET tournament_id = ?, flags = ?, name = ? WHERE id = ?', $tournament_id, $flags, $name, $event_id);
		$log_details = new stdClass();
		$log_details->tournament_id = $tournament_id;
		$log_details->name = $name;
		$log_details->flags = $flags;
		db_log(LOG_OBJECT_EVENT, 'changed', $log_details, $event_id, $club_id);
		
		Db::exec(get_label('game'), 'UPDATE games SET tournament_id = ? WHERE event_id = ?', $tournament_id, $event_id);
		
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
	}
	
	function convert_to_tournament_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Creates a tournament with one round. Where the event is the round.');
		$help->request_param('event_id', 'Event id to convert to a tournament.');
		$help->response_param('tournament_id', 'Id of the newly created tournament.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$event_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_profile->user_def_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO event_comments (time, user_id, comment, event_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $event_id, $lang);
		
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('event'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM events e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $event_id);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN event_users eu ON u.id = eu.user_id' .
			' WHERE eu.coming_odds > 0 AND eu.event_id = ?)' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN event_comments c ON c.user_id = u.id' .
			' WHERE c.event_id = ?)', $event_id, $event_id);
		// echo $query->get_parsed_sql();
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
		
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'event_id' => new Tag($event_id),
				'event_name' => new Tag($event_name),
				'event_date' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
				'event_time' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'user_name' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_event.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_EVENT, $event_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the event.');
		$help->request_param('id', 'Event id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Event Operations', CURRENT_VERSION);

?>