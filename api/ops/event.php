<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';
require_once '../../include/image.php';
require_once '../../include/scoring.php';
require_once '../../include/game.php';

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
		
				$warning = load_map_info($address_id, '../../' . ADDRESS_PICS_DIR);
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
		$default_flags = EVENT_FLAG_ALL_CAN_REFEREE;
		if ($tournament_id <= 0)
		{
			$club_id = (int)get_required_param('club_id');
			$tournament_id = NULL;
			$round_num = 0;
			$tournament_start_time = $tournament_duration = 0;
		}
		else
		{
			list($club_id, $tournament_flags, $tournament_start_time, $tournament_duration) = db::record(get_label('tournament'), 'SELECT club_id, flags, start_time, duration FROM tournaments WHERE id = ?', $tournament_id);
			if (($tournament_flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
			{
				$default_flags |= EVENT_MASK_HIDDEN;
			}
			$round_num = (int)get_optional_param('round_num', 0);
		}
		
		$is_club_referee_creating = false;
		if (is_null($tournament_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $club_id);
			$is_club_referee_creating = !is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		}
		else
		{
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		}
		if (isset($_profile->clubs[$club_id]))
		{
			$club = $_profile->clubs[$club_id];
		}
		else
		{
			$club = new stdClass();
			list($club->id, $club->timezone, $club->country_id, $club->city_id, $club->rules_code, $club->scoring_id, $club->fee, $club->currency_id) = 
				Db::record(get_label('club'), 'SELECT c.id, ct.timezone, ct.id, ct.country_id, c.rules, c.scoring_id, c.fee, c.currency_id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
		}
		
		$name = get_required_param('name');
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('event name')));
		}

		$start = get_optional_param('start', time());
		$duration = (int)get_required_param('duration');
		$fee = (int)get_optional_param('fee', $club->fee);
		if (!is_null($fee) && $fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = get_optional_param('currency_id', $club->currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		$rules_code = get_optional_param('rules_code', $club->rules_code);
		$scoring_id = (int)get_optional_param('scoring_id', $club->scoring_id);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$scoring_options = get_optional_param('scoring_options', '{}');
		$notes = get_optional_param('notes', '');
		
		$flags = (int)get_optional_param('flags', $default_flags);
		$flags = ($flags & EVENT_EDITABLE_MASK) + ($default_flags & ~EVENT_EDITABLE_MASK);
		
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
		if (!is_null($fee) && !is_null($currency_id))
		{
			$log_details->fee = (int)$fee;
			$log_details->currency_id = (int)$currency_id;
		}
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
				if (
					($weekdays & $weekday) != 0 && (
					$tournament_start_time <= 0 || (
					$start_datetime->getTimestamp() >= $tournament_start_time && 
					$start_datetime->getTimestamp() + $duration <= $tournament_start_time + $tournament_duration)))
				{
					Db::exec(
						get_label('event'), 
						'INSERT INTO events (name, fee, currency_id, address_id, club_id, start_time, notes, duration, flags, languages, rules, scoring_id, scoring_version, scoring_options, tournament_id, round) ' .
						'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$name, $fee, $currency_id, $address_id, $club_id, $start_datetime->getTimestamp(), 
						$notes, $duration, $flags, $langs, $rules_code, 
						$scoring_id, $scoring_version, $scoring_options, $tournament_id, $round_num);
					list ($event_id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
					
					$log_details->start = $start_datetime->format('d/m/y H:i');
					db_log(LOG_OBJECT_EVENT, 'created', $log_details, $event_id, $club_id);
					
					$event_ids[] = $event_id;
					
					if ($is_club_referee_creating)
					{
						// Club moderator who is creating the event should have management permissions for the event
						Db::exec(
							get_label('registration'), 
							'INSERT INTO event_users (event_id, user_id, flags) VALUES (?, ?, ?)',
							$event_id, $_profile->user_id, USER_PERM_MANAGER);
					}
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
			$start_time = $start_datetime->getTimestamp();
			if ($tournament_start_time > 0)
			{
				$start_time = min($start_time, $tournament_start_time + $tournament_duration - $duration);
				$start_time = max($start_time, $tournament_start_time);
				$duration = min($duration, $tournament_start_time + $tournament_duration - $start_time);
			}
			
			Db::exec(
				get_label('event'), 
				'INSERT INTO events (name, fee, currency_id, address_id, club_id, start_time, notes, duration, flags, languages, rules, scoring_id, scoring_version, scoring_options, tournament_id, round) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$name, $fee, $currency_id, $address_id, $club_id, $start_time, 
				$notes, $duration, $flags, $langs, $rules_code, 
				$scoring_id, $scoring_version, $scoring_options, $tournament_id, $round_num);
			list ($event_id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
			
			$log_details->start = $start;
			db_log(LOG_OBJECT_EVENT, 'created', $log_details, $event_id, $club_id);
			
			$event_ids[] = $event_id;
			
			if ($is_club_referee_creating)
			{
				// Club moderator who is creating the event should have management permissions for the event
				Db::exec(
					get_label('registration'), 
					'INSERT INTO event_users (event_id, user_id, flags) VALUES (?, ?, ?)',
					$event_id, $_profile->user_id, USER_PERM_MANAGER);
			}
		}
		
		Db::commit();
		
		$this->response['events'] = $event_ids;
		$this->response['mailing'] = EVENT_EMAIL_INVITE;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Create event.');
		$help->request_param('club_id', 'Club id.', 'tournament_id must be set.');
		$help->request_param('tournament_id', 'Tournament id. When set the event becomes a tournament round.', 'club_id must be set.');
		$help->request_param('round_num', 'Round number: 0 for main round; 1 - final; 2 - semi-final; 3 - quoter-final; etc.', 'tournament_id must be set.');
		$help->request_param('name', 'Event name.');
		$help->request_param('month', 'Month of the event.');
		$help->request_param('day', 'Day of the month of the event.');
		$help->request_param('year', 'Year of the event.');
		$help->request_param('hour', 'Hour when the event starts.');
		$help->request_param('minute', 'Minute when the event starts.');
		$help->request_param('duration', 'Event duration in seconds.');
		$help->request_param('fee', 'Admission rate. Send -1 if unknown.', 'club fee is used.');
		$help->request_param('currency_id', 'Currency id for the admission rate. Send -1 if unknown.', 'club currency is used.');
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
		$help->request_param('langs', 'Languages on this event. A bit combination of language ids.' . valid_langs_help(), 'all club languages are used.');
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
		global $_profile, $_lang;
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $old_name, $old_tournament_id, $old_start_timestamp, $old_duration, $old_address_id, $old_fee, $old_currency_id, $old_rules_code, $old_scoring_id, $old_scoring_version, $old_scoring_options, $old_langs, $old_notes, $old_flags, $timezone, $old_round_num) = 
			Db::record(get_label('event'), 
				'SELECT e.club_id, e.name, e.tournament_id, e.start_time, e.duration, e.address_id, e.fee, e.currency_id, e.rules, e.scoring_id, e.scoring_version, e.scoring_options, e.languages, e.notes, e.flags, c.timezone, e.round ' .
				'FROM events e ' . 
				'JOIN addresses a ON a.id = e.address_id ' . 
				'JOIN cities c ON c.id = a.city_id ' . 
				'WHERE e.id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $old_tournament_id);
		if (isset($_profile->clubs[$club_id]))
		{
			$club = $_profile->clubs[$club_id];
		}
		else
		{
			$club = new stdClass();
			list($club->id, $club->timezone, $club->country_id, $club->city_id) = 
				Db::record(get_label('club'), 
					'SELECT c.id, ct.timezone, crn.name, ctn.name, c.rules, c.name, c.langs FROM clubs c ' .
					'JOIN cities ct ON ct.id = c.city_id ' .
					'JOIN countries cr ON cr.id = ct.country_id ' .
					'JOIN names ctn ON ctn.id = ct.name_id AND (ctn.langs & '.$_lang.') <> 0 ' .
					'JOIN names crn ON crn.id = cr.name_id AND (crn.langs & '.$_lang.') <> 0 ' .
					'WHERE c.id = ?', $club_id);
		}

		$name = get_optional_param('name', $old_name);
		$tournament_id = get_optional_param('tournament_id', $old_tournament_id);
		if ($tournament_id <= 0)
		{
			$tournament_id = NULL;
			$round_num = 0;
		}
		else
		{
			$round_num = get_optional_param('round_num', $old_round_num);
		}
		
		$start = get_optional_param('start', $old_start_timestamp);
		$duration = (int)get_optional_param('duration', $old_duration);
		$fee = get_optional_param('fee', $old_fee);
		if (!is_null($fee) && $fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = get_optional_param('currency_id', $old_currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		$scoring_id = (int)get_optional_param('scoring_id', $old_scoring_id);
		$scoring_version = (int)get_optional_param('scoring_version', -1);
		$scoring_options = get_optional_param('scoring_options', $old_scoring_options);
		$notes = get_optional_param('notes', $old_notes);
		
		
		$rules_code = get_optional_param('rules_code', $old_rules_code);
		$rules_code = check_rules_code($rules_code);
		
		$flags = (int)get_optional_param('flags', $old_flags);
		$flags = ($flags & EVENT_EDITABLE_MASK) + ($old_flags & ~EVENT_EDITABLE_MASK);
		
		$langs = get_optional_param('langs', $old_langs);
		if (($langs & LANG_ALL) == 0)
		{
			throw new Exc(get_label('No languages specified.'));
		}
		
		list($address_id, $timezone) = $this->get_address_id($club, $old_address_id);
		$start_datetime = get_datetime($start, $timezone);
		$start_timestamp = $start_datetime->getTimestamp();
		
		if ($tournament_id != $old_tournament_id)
		{
			if (is_null($tournament_id))
			{
				// Make event visible if it was not
				$flags &= ~EVENT_MASK_HIDDEN;
				
				// move event games out of the tournament
				$query = new DbQuery('SELECT id, json FROM games WHERE event_id = ? AND tournament_id = ?', $event_id, $old_tournament_id);
				while ($row = $query->next())
				{
					list ($game_id, $game) = $row;
					if (is_null($game))
					{
						continue;
					}
					$game = json_decode($game);
					if (isset($game->tournamentId))
					{
						unset($game->tournamentId);
					}
					Db::exec(get_label('game'), 'UPDATE games SET tournament_id = NULL, json = ? WHERE id = ?', json_encode($game), $game_id);
				}
			}
			else
			{
				list ($new_club_id, $scoring_id, $scoring_version, $rules_code, $tournament_flags, $tournament_start, $tournament_duration) = Db::record(get_label('tournament'), 'SELECT club_id, scoring_id, scoring_version, rules, flags, start_time, duration FROM tournaments WHERE id = ?', $tournament_id);
				if ($new_club_id != $club_id)
				{
					// Currently there is no API for changing event club. However in the future such an API might exist.
					// Potentially moving event to a tournament of another club is a valid operation but there is no need for it now.
					throw new Exc(get_label('Event can not be moved to the tournament of another club.'));
				}
				check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $new_club_id, $tournament_id);
				
				// Update tournament start time and duration to contain the event
				$new_tournament_start = min($start_timestamp, $tournament_start);
				$new_tournament_duration = max($start_timestamp + $duration, $tournament_start + $tournament_duration) - $new_tournament_start;
				Db::exec(
					get_label('tournament'), 
					'UPDATE tournaments SET start_time = ?, duration = ? WHERE id = ?',
					$new_tournament_start, $new_tournament_duration, $tournament_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->start = $new_tournament_start;
					$log_details->duration = $new_tournament_duration;
					db_log(LOG_OBJECT_TOURNAMENT, 'changed', $log_details, $tournament_id, $club_id);
				}
				
				// Add event registrations to tournament registrations
				$query = new DbQuery('SELECT user_id, flags FROM event_users WHERE event_id = ?', $event_id);
				while ($row = $query->next())
				{
					list($user_id, $user_flags) = $row;
					$user_flags &= USER_PERM_MASK;
					// Note that we are loosing user custom picture here if exists. We can fix it in the future if it is a problem.
					Db::exec(get_label('registration'), 'INSERT IGNORE INTO tournament_users (tournament_id, user_id, flags) values (?, ?, ?)', $tournament_id, $user_id, $user_flags);
				}
				
				// if tournament is not a long term tournament, make the event hidden
				if (($tournament_flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
				{
					$flags |= EVENT_MASK_HIDDEN;
				}
				else
				{
					$flags &= ~EVENT_MASK_HIDDEN;
				}
				
				// move event games that belonged to the old tournament to the new tournament
				if (is_null($old_tournament_id))
				{
					$query = new DbQuery('SELECT id, json FROM games WHERE event_id = ? AND tournament_id IS NULL', $event_id);
				}
				else
				{
					$query = new DbQuery('SELECT id, json FROM games WHERE event_id = ? AND tournament_id = ?', $event_id, $old_tournament_id);
				}
				while ($row = $query->next())
				{
					list ($game_id, $game) = $row;
					if (is_null($game))
					{
						continue;
					}
					$game = json_decode($game);
					$game->tournamentId = (int)$tournament_id;
					Db::exec(get_label('game'), 'UPDATE games SET tournament_id = ?, json = ? WHERE id = ?', $tournament_id, json_encode($game), $game_id);
				}
			}
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
		
		$logo_uploaded = false;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . EVENT_PICS_DIR, $event_id);
			
			$icon_version = (($flags & EVENT_ICON_MASK) >> EVENT_ICON_MASK_OFFSET) + 1;
			if ($icon_version > EVENT_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~EVENT_ICON_MASK) + ($icon_version << EVENT_ICON_MASK_OFFSET);
			$logo_uploaded = true;
		}
		
		// reset EVENT_FLAG_FINISHED flag if needed
		if (
			$old_tournament_id != $tournament_id ||
			$old_round_num != $round_num ||
			$old_scoring_id != $scoring_id ||
			$old_scoring_options != $scoring_options ||
			$old_scoring_version != $scoring_version)
		{
			$flags &= ~EVENT_FLAG_FINISHED;
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
		}
		
		Db::exec(
			get_label('event'), 
			'UPDATE events SET ' .
				'name = ?, tournament_id = ?, fee = ?, currency_id = ?, rules = ?, scoring_id = ?, scoring_version = ?, scoring_options = ?, ' .
				'address_id = ?, start_time = ?, notes = ?, duration = ?, flags = ?, ' .
				'languages = ?, round = ? WHERE id = ?',
			$name, $tournament_id, $fee, $currency_id, $rules_code, $scoring_id, $scoring_version, $scoring_options,
			$address_id, $start_timestamp, $notes, $duration, $flags,
			$langs, $round_num, $event_id);
		
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
			if ($round_num != $old_round_num)
			{
				$log_details->round_num = $round_num;
			}
			if ($fee != $old_fee)
			{
				$log_details->fee = $fee;
			}
			if ($currency_id != $old_currency_id)
			{
				$log_details->currency_id = $currency_id;
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
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			db_log(LOG_OBJECT_EVENT, 'changed', $log_details, $event_id, $club_id);
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
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Edit event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('tournament_id', 'Tournament id. When set the event becomes a tournament round.', 'remains the same.');
		$help->request_param('round_num', 'Round number: 0 for main round; 1 - final; 2 - semi-final; 3 - quoter-final; etc.', 'remains the same.');
		$help->request_param('name', 'Event name.', 'remains the same.');
		$help->request_param('start', 'Event start time. It is either unix timestamp or datetime in the format "yyyy-mm-dd hh:00". Timezone of the address is used.', 'remains the same.');
		$help->request_param('duration', 'Event duration in seconds.', 'remains the same.');
		$help->request_param('fee', 'Admission rate.', 'remains the same.');
		$help->request_param('currency_id', 'Currency for admission rate. If fee is unknown send null here.', 'remains the same.');
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
		$help->request_param('langs', 'Languages on this event. A bit combination of language ids.' . valid_langs_help(), 'remain the same.');
		$help->request_param('address_id', 'Address id of the event.', '<q>address</q> is used to create new address.');
		$help->request_param('address', 'When <q>address_id</q> is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', 'address remains the same');
		$help->request_param('country_id', 'When <q>address_id<q> is not set, and <q>address</q> is set - this is the country id for the new address.', '<q>country</q> parameter is used to create new country for the address.');
		$help->request_param('country', 'When <q>address_id</q> is not set, and <q>address</q> is set, and <q>country_id</q> is not set - this is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', 'club country is used for the new address.');
		$help->request_param('city_id', 'When <q>address_id<q> is not set, and <q>address</q> is set - this is the city id for the new address.', '<q>city</q> parameter is used to create new city for the address.');
		$help->request_param('city', 'When <q>address_id</q> is not set, and <q>address</q> is set, and <q>city_id</q> is not set - this is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', 'club city is used for the new address.');
		$help->request_param('logo', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// attend
	//-------------------------------------------------------------------------------------------------------
	function attend_op()
	{
		global $_profile, $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$user_id = get_optional_param('user_id', $_profile->user_id);
		list($user_name, $city_name) = Db::record(get_label('user'), 
			'SELECT nu.name, nc.name FROM users u '.
			' JOIN cities c ON c.id = u.city_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
			' WHERE u.id = ?', $user_id);
		
		if ($event_id > 0)
		{
			check_permissions(PERMISSION_USER);
			if ($user_id != $_profile->user_id)
			{
				list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
				check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
			}
			
			$odds = min(max((int)get_optional_param('odds', 100), 0), 100);
			$late = (int)get_optional_param('late', 0);
			$friends = (int)get_optional_param('friends', 0);
			$nickname = get_optional_param('nickname', '');
			if (empty($nickname))
			{
				$nickname = $user_name;
			}
			
			Db::begin();
			Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $user_id);
			Db::exec(get_label('registration'), 
				'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me, late, nickname) VALUES (?, ?, ?, ?, ?, ?)',
				$event_id, $user_id, $odds, $friends, $late, $nickname);
			Db::commit();
			
			$this->response['regs'] = get_event_reg_array($event_id);
		}
		else
		{
			// demo game
			if (!isset($_SESSION['demogame']))
			{
				throw new Exc(get_label('Unknown [0]', get_label('event')));
			}
			$data = $_SESSION['demogame'];
			
			$not_exists = true;
			$no_city = true;
			foreach ($data->regs as $r)
			{
				if ($r->id == $user_id)
				{
					$not_exists = false;
					break;
				}
				if ($no_city && $r->name == $user_name)
				{
					$user_name = $user_name . ' (' . $city_name . ')'; 
					$no_city = false;
				}
			}
			
			if ($not_exists)
			{
				$r = new stdClass();
				$r->id = (int)$user_id;
				$r->name = $user_name;
				$data->regs[] = $r;
				usort($data->regs, function($a, $b) { return strcmp($a->name, $b->name); });
			}
			$this->response['regs'] = $data->regs;
		}
		$this->response['user_id'] = $user_id;
	}
	
	function attend_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Tell the system about the plans to attend the upcoming event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('odds', 'The odds of coming. An integer from 0 to 100. Sending 0 means that current user is not planning to attend the event. If odds are 100, the user gets registered for the event.', '100% is used.');
		$help->request_param('late', 'I current user can not be in time, this is how much late will he/she be in munutes.', 'user is assumed to be in time.');
		$help->request_param('friends', 'How many friends are coming with the current user.', '0 is used.');
		$help->request_param('nickname', 'Nickname for the event. If it is set and not empty, the user is registered for the event even if the odds are not 100%.', 'nickname is the same as user name.');
		
		$help->response_param('user_id', 'Id of the newly registered user.');
		$param = $help->response_param('regs', 'Array containing players registered for the event.');
		$param->response_param('id', 'User id');
		$param->response_param('name', 'Player name');
		$param->response_param('flags', 'Permission bit-flags: 1 - player; 2 - referee; 4 - manager.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// new_player_attend
	//-------------------------------------------------------------------------------------------------------
	function new_player_attend_op()
	{
		global $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$name = get_required_param('name');
		$email = get_required_param('email');
		$gender = get_required_param('gender');
		
		if ($event_id > 0)
		{
			list($club_id, $tournament_id, $city_id) = Db::record(get_label('event'), 'SELECT e.club_id, e.tournament_id, c.city_id FROM events e JOIN clubs c ON c.id = e.club_id WHERE e.id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
			
			$flags = NEW_USER_FLAGS;
			if ($gender > 0)
			{
				$flags |= USER_FLAG_MALE;
			}
				
			Db::begin();
			$names = new Names(-1, get_label('user name'), 'users', 0, new SQL(' AND o.city_id = ?', $city_id), $name);
			$user_id = create_user($names, $email, $club_id, $city_id);
			Db::exec(get_label('registration'), 
				'INSERT INTO event_users (event_id, user_id, nickname) VALUES (?, ?, ?)',
				$event_id, $user_id, $name);
			Db::commit();
				
			$this->response['user_id'] = $user_id;
			$this->response['regs'] = get_event_reg_array($event_id);
		}
		else
		{
			// demo game
			if (!isset($_SESSION['demogame']))
			{
				throw new Exc(get_label('Unknown [0]', get_label('event')));
			}
			$data = $_SESSION['demogame'];
			
			$user_id = 0;
			foreach ($data->regs as $r)
			{
				$user_id = min($r->id, $user_id);
				if ($r->name == $name)
				{
					throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('User name'), $name));
				}
			}
			--$user_id;

			$r = new stdClass();
			$r->id = (int)$user_id;
			$r->name = $name;
			$data->regs[] = $r;
			usort($data->regs, function($a, $b) { return strcmp($a->name, $b->name); });
			
			$this->response['user_id'] = $user_id;
			$this->response['regs'] = $data->regs;
		}
	}
	
	function new_player_attend_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Create player and register them for an event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('name', 'User name.');
		$help->request_param('email', 'User email.');
		$help->request_param('gender', 'User gender - 0 for female, 1 for male.');
		
		$help->response_param('user_id', 'Id of the newly created and registered user.');
		$param = $help->response_param('regs', 'Array containing players registered for the event.');
		$param->response_param('id', 'User id');
		$param->response_param('name', 'Player name');
		$param->response_param('flags', 'Permission bit-flags: 1 - player; 2 - referee; 4 - manager.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// extend
	//-------------------------------------------------------------------------------------------------------
	function extend_op()
	{
		$event_id = (int)get_required_param('event_id');
		$duration = (int)get_required_param('duration');
		Db::begin();
		list ($club_id, $tournament_id, $timestamp, $old_duration) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, start_time, duration FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);
		
		if ($timestamp + $old_duration + EVENT_ALIVE_TIME < time())
		{
			throw new Exc(get_label('The event is too old. It can not be extended.'));
		}
		
		Db::exec(get_label('event'), 'UPDATE events SET duration = ? WHERE id = ?', $duration, $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->duration = $duration;
			db_log(LOG_OBJECT_EVENT, 'extended', $log_details, $event_id, $club_id);
		}
		Db::commit();
	}
	
	function extend_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
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
		Db::begin();
		list ($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = ((flags | ' . EVENT_FLAG_CANCELED . ') & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $event_id);
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
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Cancel event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$event_id = (int)get_required_param('event_id');

		Db::begin();
		list ($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . (EVENT_FLAG_CANCELED | EVENT_FLAG_FINISHED) . ') WHERE id = ?', $event_id);
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
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Restore canceled event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$event_id = (int)get_required_param('event_id');
		$log_details = new stdClass();
		$prev_game_id = NULL;
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		list($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE event_id = ?', $event_id);
		if ($games_count > 0)
		{
			if (!is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
			{
				throw new Exc(get_label('[0] games were played in this event. Only club managers can delete it.', $games_count));
			}
			
			$query = new DbQuery('SELECT id, end_time FROM games WHERE event_id = ? AND (flags & '.GAME_FLAG_RATING.') <> 0 ORDER BY end_time, id LIMIT 1', $event_id);
			if ($row = $query->next())
			{
				list($game_id, $end_time) = $row;
				
				$prev_game_id = NULL;
				$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
				if ($row = $query->next())
				{
					list($prev_game_id) = $row;
				}
				Game::rebuild_ratings($prev_game_id, $end_time);
				$log_details->rebuild_ratings = $prev_game_id;
			}
		}
		
		Db::exec(get_label('user'), 'DELETE FROM event_users WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->event_users = Db::affected_rows();
		}
		Db::exec(get_label('user'), 'DELETE FROM event_incomers WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->event_incomers = Db::affected_rows();
		}
		Db::exec(get_label('comment'), 'DELETE FROM event_comments WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->comments = Db::affected_rows();
		}
		Db::exec(get_label('points'), 'DELETE FROM event_extra_points WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->extra_points = Db::affected_rows();
		}
		Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->mailings = Db::affected_rows();
		}
		Db::exec(get_label('place'), 'DELETE FROM event_places WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->places = Db::affected_rows();
		}
		Db::exec(get_label('album'), 'DELETE FROM photo_albums WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->photo_albums = Db::affected_rows();
		}
		Db::exec(get_label('video'), 'DELETE FROM videos WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->videos = Db::affected_rows();
		}
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $prev_game_id, $event_id);
		Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id IN (SELECT id FROM games WHERE event_id = ?)', $event_id);
		Db::exec(get_label('game'), 'DELETE FROM games WHERE event_id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details->games = Db::affected_rows();
		}
		
		Db::exec(get_label('event'), 'DELETE FROM events WHERE id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EVENT, 'deleted', $log_details, $event_id, $club_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Cancel event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_player
	//-------------------------------------------------------------------------------------------------------
	function change_player_op()
	{
		global $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$user_id = (int)get_required_param('user_id');
		$new_user_id = (int)get_optional_param('new_user_id', 0);
		$nickname = get_optional_param('nick', NULL);
		$changed = false;
		
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		
		if ($user_id == 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		
		Db::begin();
		if ($user_id < 0)
		{
			$incomer_id = -$user_id;
			if ($new_user_id > 0)
			{
				if ($nickname == NULL)
				{
					list($nickname) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $new_user_id);
				}
				
				Db::exec(get_label('registration'), 'INSERT INTO event_users (event_id, user_id, nickname) VALUES (?, ?, ?)', $event_id, $new_user_id, $nickname);
				$changed = $changed || Db::affected_rows() > 0;
				
				Db::exec(get_label('registration'), 'DELETE FROM event_incomers WHERE id = ?', $incomer_id);
				$changed = $changed || Db::affected_rows() > 0;
			}
			else 
			{
				$new_user_id = $user_id;
				if ($nickname != NULL)
				{
					Db::exec(get_label('registration'), 'UPDATE event_incomers SET name = ? WHERE id = ?', $nickname, $incomer_id);
					$changed = $changed || Db::affected_rows() > 0;
				}
			}
		}
		else if ($new_user_id <= 0)
		{
			if ($nickname == NULL)
			{
				list($nickname, $flags) = Db::record(get_label('user'), 'SELECT nu.name, u.flags FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE id = ?', $user_id);
			}
			else
			{
				list($flags) = Db::record(get_label('user'), 'SELECT flags FROM users WHERE id = ?', $user_id);
			}
				
			Db::exec(get_label('registration'), 'REPLACE INTO event_incomers (event_id, name, flags) VALUES (?, ?, ?)', $event_id, $nickname, $flags);
			list ($incomer_id) = Db::record(get_label('registration'), 'SELECT LAST_INSERT_ID()');
			$new_user_id = -$incomer_id;
			
			Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $user_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		else if ($user_id != $new_user_id)
		{
			if ($nickname == NULL)
			{
				list($nickname) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $new_user_id);
			}
			Db::exec(get_label('registration'), 'UPDATE event_users SET user_id = ?, nickname = ? WHERE user_id = ? AND event_id = ?', $new_user_id, $nickname, $user_id, $event_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		else if ($nickname != NULL)
		{
			Db::exec(get_label('registration'), 'UPDATE event_users SET nickname = ? WHERE user_id = ? AND event_id = ?', $nickname, $user_id, $event_id);
			$changed = $changed || Db::affected_rows() > 0;
		}
		
		$query = new DbQuery('SELECT id, json, feature_flags FROM games WHERE event_id = ?', $event_id);
		while ($row = $query->next())
		{
			list ($game_id, $json, $feature_flags) = $row;
			$game = new Game($json, $feature_flags);
			if ($game->change_user($user_id, $new_user_id, $nickname))
			{
				$game->update();
				$changed = true;
			}
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
		
		$this->response['user_id'] = $new_user_id;
		$this->response['nickname'] = $nickname;
	}
	
	function change_player_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change player on the event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('user_id', 'User id of a player who played on this event. It can be negative for temporary players.');
		$help->request_param('new_user_id', 'If it is different from user_id, player is replaced in this event with the player new_user_id. If it is 0 or negative, user is replaced with a temporary player existing for this event only.', 'user_id is used.');
		$help->request_param('nick', 'Nickname for this event. If it is empty, user name is used.', 'user name is used.');
		
		$help->response_param('user_id', 'New user id.');
		$help->response_param('nickname', 'New nickname.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// add_extra_points
	//-------------------------------------------------------------------------------------------------------
	function add_extra_points_op()
	{
		global $_lang;
		
		$event_id = (int)get_required_param('event_id');
		$user_id = (int)get_required_param('user_id');
		$reason = get_required_param('reason');
		$details = get_optional_param('details');
		$points = (float)get_required_param('points');
		$mvp = (bool)get_optional_param('mvp', 0);
		
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | 
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 
			$club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('points'), 'INSERT INTO event_extra_points (time, event_id, user_id, reason, details, points, mvp) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?, ?, ?)', $event_id, $user_id, $reason, $details, $points, $mvp);
		list ($points_id) = Db::record(get_label('points'), 'SELECT LAST_INSERT_ID()');
		
		list($user_name) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		$log_details = new stdClass();
		$log_details->user = $user_name;
		$log_details->user_is = $user_id;
		$log_details->event_id = $event_id;
		$log_details->points = $points;
		$log_details->reason = $reason;
		$log_details->mvp = $mvp;
		if (!empty($details))
		{
			$log_details->details = $details;
		}
		db_log(LOG_OBJECT_EXTRA_POINTS, 'created', $log_details, $points_id, $club_id);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $event_id);
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
		
		Db::commit();
		
		$this->response['points_id'] = $points_id;
	}
	
	function add_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Add extra points.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('user_id', 'User id. The user who is receiving or loosing points.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract. Zero means: add average points per game for this event.');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.');
		$help->request_param('mvp', 'Non zero if these points should also count in MVP competition.', 'not used in MVP competition.');
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
		list($user_id, $event_id, $tournament_id, $club_id, $old_reason, $old_details, $old_points, $old_mvp) = 
			Db::record(get_label('points'), 'SELECT p.user_id, p.event_id, e.tournament_id, e.club_id, p.reason, p.details, p.points, p.mvp FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE p.id = ?', $points_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | 
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 
			$club_id, $event_id, $tournament_id);
		
		$reason = get_optional_param('reason', $old_reason);
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		$details = get_optional_param('details', $old_details);
		$points = (float)get_optional_param('points', $old_points);
		$mvp = (bool)get_optional_param('mvp', $old_mvp);
		
		Db::exec(get_label('points'), 'UPDATE event_extra_points SET reason = ?, details = ?, points = ?, mvp = ? WHERE id = ?', $reason, $details, $points, $mvp, $points_id);
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
			if ($mvp != $old_mvp)
			{
				$log_details->mvp = $mvp;
			}
			db_log(LOG_OBJECT_EXTRA_POINTS, 'changed', $log_details, $points_id, $club_id);
			
			Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $event_id);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
		}
		Db::commit();
	}
	
	function change_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Change extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract. Zero means: add average points per game for this event.', 'remains the same');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.', 'remains the same');
		$help->request_param('mvp', 'Non zero if these points should also count in MVP competition.', 'remains the same');
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
		list($club_id, $event_id, $tournament_id) = Db::record(get_label('points'), 'SELECT e.club_id, e.id, e.tournament_id FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE p.id = ?', $points_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | 
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('points'), 'DELETE FROM event_extra_points WHERE id = ?', $points_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EXTRA_POINTS, 'deleted', NULL, $points_id, $club_id);
			
			Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $event_id);
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
		}
		Db::commit();
	}
	
	function delete_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Delete extra points.');
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
			list($club_id, $tournament_id, $lgs) = Db::record(get_label('event'), 'SELECT club_id, tournament_id, languages FROM events WHERE id = ?', $event_id);
			check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
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
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, '');
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
		list($event_id, $tournament_id, $club_id, $old_time, $status, $old_flags, $old_langs, $old_type) = Db::record(get_label('mailing'), 'SELECT e.id, e.tournament_id, e.club_id, m.send_time, m.status, m.flags, m.langs, m.type FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
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
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, '');
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
		list($event_id, $tournament_id, $club_id) = Db::record(get_label('mailing'), 'SELECT e.id, e.tournament_id, e.club_id FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
		
		Db::begin();
		Db::exec(get_label('mailing'), 'DELETE FROM event_mailings WHERE id = ?', $mailing_id);
		db_log(LOG_OBJECT_EVENT_MAILINGS, 'deleted', new stdClass(), $mailing_id, $club_id);
		Db::commit();
	}
	
	function delete_mailing_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, '');
		$help->request_param('mailing_id', 'Id of the event mailing.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// to_tournament
	//-------------------------------------------------------------------------------------------------------
	function to_tournament_op()
	{
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $name, $address_id, $start_time, $duration, $langs, $notes, $fee, $currency_id, $scoring_id, $scoring_version, $scoring_options, $rules, $flags) = 
			Db::record(get_label('event'), 'SELECT club_id, name, address_id, start_time, duration, languages, notes, fee, currency_id, scoring_id, scoring_version, scoring_options, rules, flags FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$tournament_flags = 0;
		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, fee, currency_id, scoring_id, scoring_version, scoring_options, rules, flags) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $club_id, $address_id, $start_time, $duration, $langs, $notes, $fee, $currency_id, $scoring_id, $scoring_version, $scoring_options, $rules, 0);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start_time;
		$log_details->duration = $duration;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->fee = $fee;
		$log_details->currency_id = $currency_id;
		$log_details->scoring_id = $scoring_id;
		$log_details->scoring_version = $scoring_version;
		$log_details->scoring_options = $scoring_options;
		$log_details->rules_code = $rules;
		$log_details->flags = 0;
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id);
		
		$query = new DbQuery('SELECT user_id, flags FROM event_users WHERE event_id = ?', $event_id);
		while ($row = $query->next())
		{
			list($user_id, $user_flags) = $row;
			$user_flags &= USER_PERM_MASK;
			// Note that we are loosing user custom picture here if exists. We can fix it in the future if it is a problem.
			Db::exec(get_label('registration'), 'INSERT IGNORE INTO tournament_users (tournament_id, user_id, flags) values (?, ?, ?)', $tournament_id, $user_id, $user_flags);
		}
			
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
		
		$query = new DbQuery('SELECT id, json FROM games WHERE event_id = ?', $event_id);
		while ($row = $query->next())
		{
			list ($game_id, $game) = $row;
			if (is_null($game))
			{
				continue;
			}
			throw new Exc($game);
			$game = json_decode($game);
			
			$game->tournamentId = (int)$tournament_id;
			Db::exec(get_label('game'), 'UPDATE games SET tournament_id = ?, json = ? WHERE id = ?', $tournament_id, json_encode($game), $game_id);
		}
		
		Db::commit();
		
		$this->response['tournament_id'] = $tournament_id;
	}
	
	function to_tournament_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Creates a tournament with one round. Where the event is the round.');
		$help->request_param('event_id', 'Event id to convert to a tournament.');
		$help->response_param('tournament_id', 'Id of the tournament.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// rebuild_places
	//-------------------------------------------------------------------------------------------------------
	function rebuild_places_op()
	{
		$event_id = (int)get_optional_param('event_id', 0);
		
		Db::begin();
		
		if ($event_id > 0)
		{
			Db::exec(get_label('event'), 'UPDATE events SET flags = flags & ' . (~EVENT_FLAG_FINISHED) . ' WHERE id = ?', $event_id);
		}
		else
		{
			Db::exec(get_label('event'), 'UPDATE events SET flags = flags & ' . (~EVENT_FLAG_FINISHED));
		}
		db_log(LOG_OBJECT_EVENT, 'rebuild_places', NULL, $event_id);
		Db::commit();
	}
	
	function rebuild_places_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER, 'Schedules event places for rebuild. It is needed when in user events view the place taken is wrong.');
		$help->request_param('event_id', 'Event id to rebuild places.', 'places are rebuilt for all events');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$event_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO event_comments (time, user_id, comment, event_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $event_id, $lang);
		
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('event'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM events e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $event_id);
		
		$query = new DbQuery(
			'(SELECT u.id, nu.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' JOIN event_users eu ON u.id = eu.user_id' .
			' WHERE eu.coming_odds > 0 AND eu.event_id = ?)' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
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
		
			date_default_timezone_set($event_timezone);
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'event_id' => new Tag($event_id),
				'event_name' => new Tag($event_name),
				'event_date' => new Tag(format_date($event_start_time, $event_timezone, false, $user_lang)),
				'event_time' => new Tag(date('H:i', $event_start_time)),
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

	//-------------------------------------------------------------------------------------------------------
	// add_user
	//-------------------------------------------------------------------------------------------------------
	function add_user_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if ($count == 0)
		{
			Db::exec(get_label('registration'), 'INSERT INTO event_users (user_id, event_id, flags) values (?, ?, ' . USER_EVENT_NEW_PLAYER_FLAGS . ')', $user_id, $event_id);
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			db_log(LOG_OBJECT_USER, 'joined event', $log_details, $user_id, $club_id);
		}
		Db::commit();
		
		$this->response['event_id'] = $event_id;
		$this->response['user_id'] = $user_id;
	}
	
	function add_user_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Register user to an event.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('event_id', 'Event id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('event_id', 'Event id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_user
	//-------------------------------------------------------------------------------------------------------
	function remove_user_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$event_id = (int)get_required_param('event_id');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->event_id = $event_id;
			db_log(LOG_OBJECT_USER, 'left event', $log_details, $user_id, $club_id);
		}
		Db::commit();
		
		$this->response['event_id'] = $event_id;
		$this->response['user_id'] = $user_id;
	}
	
	function remove_user_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Remove user from the registrations to the event.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('event_id', 'Event id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('event_id', 'Event id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_broadcast
	//-------------------------------------------------------------------------------------------------------
	function add_broadcast_op()
	{
		global $_profile;
		
		$event_id = (int)get_required_param('event_id');
		$table = (int)get_required_param('table');
		$day = (int)get_required_param('day');
		$url = get_required_param('url');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);
		
		list ($part) = Db::record(get_label('broadcast'), 'SELECT max(part_num) FROM event_broadcasts WHERE event_id = ? AND day_num = ? AND table_num = ?', $event_id, $day, $table);
		if (is_null($part))
		{
			$part = 1;
		}
		else
		{
			++$part;
		}
		
		Db::exec(get_label('broadcast'), 'INSERT INTO event_broadcasts (event_id, day_num, table_num, part_num, url, status) VALUES (?, ?, ?, ?, ?, 0)', $event_id, $day, $table, $part, $url);
		
		$log_details = new stdClass();
		$log_details->event_id = $event_id;
		$log_details->day = $day;
		$log_details->table = $table;
		$log_details->part = $part;
		$log_details->url = $url;
		db_log(LOG_OBJECT_EVENT_BROADCAST, 'created', $log_details, $_profile->user_id, $club_id);
		
		Db::commit();
		
		$this->response['part'] = $part;
	}
	
	function add_broadcast_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Add video broadcast to the event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table', 'Table number starting from 0.'); 
		$help->request_param('day', 'Day number of the broadcast.'); 
		$help->request_param('url', 'Broadcast URL.'); 
		$help->response_param('part', 'Broadcast number in the day for a table.'); 
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change_broadcast
	//-------------------------------------------------------------------------------------------------------
	function change_broadcast_op()
	{
		global $_profile;
		
		$event_id = (int)get_required_param('event_id');
		$table = (int)get_required_param('table');
		$day = (int)get_required_param('day');
		$part = (int)get_required_param('part');
		$url = get_required_param('url');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('broadcast'), 'UPDATE event_broadcasts SET url = ? WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ?', $url, $event_id, $day, $table, $part);
		
		$log_details = new stdClass();
		$log_details->event_id = $event_id;
		$log_details->day = $day;
		$log_details->table = $table;
		$log_details->part = $part;
		$log_details->url = $url;
		db_log(LOG_OBJECT_EVENT_BROADCAST, 'changed', $log_details, $_profile->user_id, $club_id);
		
		Db::commit();
		
		$this->response['part'] = $part;
	}
	
	function change_broadcast_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change video broadcast for the event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table', 'Table number starting from 0.'); 
		$help->request_param('day', 'Day number of the broadcast.'); 
		$help->request_param('part', 'Broadcast number in the day for a table.'); 
		$help->request_param('url', 'Broadcast URL.'); 
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_broadcast
	//-------------------------------------------------------------------------------------------------------
	function remove_broadcast_op()
	{
		global $_profile;
		
		$event_id = (int)get_required_param('event_id');
		$table = (int)get_required_param('table');
		$day = (int)get_required_param('day');
		$part = (int)get_required_param('part');
		
		Db::begin();
		list($club_id, $tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $event_id);
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
			$club_id, $event_id, $tournament_id);
		
		Db::exec(get_label('broadcast'), 'DELETE FROM event_broadcasts WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ?', $event_id, $day, $table, $part);
		Db::exec(get_label('broadcast'), 'UPDATE event_broadcasts SET part_num = part_num - 1 WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num > ?', $event_id, $day, $table, $part);
		
		$log_details = new stdClass();
		$log_details->event_id = $event_id;
		$log_details->day = $day;
		$log_details->table = $table;
		$log_details->part = $part;
		db_log(LOG_OBJECT_EVENT_BROADCAST, 'removed', $log_details, $_profile->user_id, $club_id);
		
		Db::commit();
		
		$this->response['part'] = $part;
	}
	
	function remove_broadcast_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Remove video broadcast from the event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('table', 'Table number starting from 0.'); 
		$help->request_param('day', 'Day number of the broadcast.'); 
		$help->request_param('part', 'Broadcast number in the day for a table.'); 
		return $help;
	}
}

$page = new ApiPage();
$page->run('Event Operations', CURRENT_VERSION);

?>