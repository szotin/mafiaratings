<?php

require_once '../../include/api.php';
require_once '../../include/tournament.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$request_league_id = (int)get_optional_param('league_id', NULL);
		if ($request_league_id <= 0)
		{
			$request_league_id = NULL;
		}
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('tournament name')));
		}
		
		$price = get_optional_param('price', '');
		$scoring_id = (int)get_optional_param('scoring_id', $club->scoring_id);
		$scoring_weight = (float)get_optional_param('scoring_weight', 1);
		$notes = get_optional_param('notes', '');
		$flags = (int)get_optional_param('flags', 0);
		$langs = get_optional_param('langs', $club->langs);
		$rules_code = get_optional_param('rules_code', NULL);
		$stars = max(min((float)get_optional_param('stars', 0), 5), 0);
		
		Db::begin();
		
		$address_id = (int)get_required_param('address_id');
		if ($address_id <= 0)
		{
			$address = htmlspecialchars(get_required_param('address'), ENT_QUOTES);
			check_address_name($address, $club_id);
			
			$city_id = (int)get_optional_param('city_id', 0);
			if ($city_id <= 0)
			{
				$city = get_required_param('city');
				$country_id = (int)get_optional_param('country_id', 0);
				if ($country_id <= 0)
				{
					$country_id = retrieve_country_id(get_required_param('country'));
				}
				$city_id = retrieve_city_id($city, $country_id, $club->timezone);
			}
			
			Db::exec(
				get_label('address'), 
				'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
				$address, $club_id, $address, $city_id);
			list ($address_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $address;
			$log_details->address = $address;
			$log_details->city_id = $city_id;
			db_log(LOG_OBJECT_ADDRESS, 'created', $log_details, $address_id, $club_id);
	
			$warning = load_map_info($address_id);
			if ($warning != NULL)
			{
				echo '<p>' . $warning . '</p>';
			}
		}
		
		list($timezone) = Db::record(get_label('address'), 'SELECT c.timezone FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.id = ?', $address_id);
		$start_datetime = get_datetime(get_required_param('start'), $timezone);
		$end_datetime = get_datetime(get_required_param('end'), $timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		if ($end <= $start)
		{
			throw new Exc(get_label('Tournament ends before or right after the start.'));
		}
		
		$league_id = $request_league_id;
		if ($request_league_id != NULL)
		{
			list($league_name, $league_langs) = Db::record(get_label('league'), 'SELECT name, langs FROM leagues WHERE id = ?', $request_league_id);
			if ($rules_code == NULL)
			{
				$query = new DbQuery('SELECT rules FROM league_clubs WHERE club_id = ? AND league_id = ?', $club_id, $request_league_id);
				if ($row = $query->next())
				{
					list($rules_code) = $row;
				}
				else
				{
					throw new Exc(get_label('[0] is not a member of [1]', $club->name, $league_name));
				}
			}
			
			if (!is_permitted(PERMISSION_LEAGUE_MANAGER, $request_league_id))
			{
				$league_id = NULL;
			}
		}
		else if ($rules_code == NULL)
		{
			$rules_code = $club->rules_code;
		}
		
		Db::exec(
			get_label('tournament'), 
			'INSERT INTO tournaments (name, league_id, request_league_id, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $league_id, $request_league_id, $club_id, $address_id, $start, $end - $start, $langs, $notes, $price, $scoring_id, $rules_code, $flags, $stars);
		list ($tournament_id) = Db::record(get_label('tournament'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->league_id = $league_id;
		$log_details->request_league_id = $request_league_id;
		$log_details->club_id = $club_id; 
		$log_details->address_id = $address_id; 
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->price = $price;
		$log_details->scoring_id = $scoring_id;
		$log_details->rules_code = $rules_code;
		$log_details->flags = $flags;
		$log_details->stars = $stars;
		db_log(LOG_OBJECT_TOURNAMENT, 'created', $log_details, $tournament_id, $club_id, $request_league_id);
		
		if (($flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
		{
			$event_name = get_label('Main round'); // todo use tournament language instead of user language
			
			Db::exec(
				get_label('round'), 
				'INSERT INTO events (name, address_id, club_id, start_time, duration, notes, flags, languages, price, scoring_id, scoring_weight, tournament_id, rules) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
				$event_name, $address_id, $club_id, $start, $end - $start, $notes, $flags, $langs, $price, $scoring_id, $tournament_id, $rules_code);
				
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->tournament_id = $tournament_id;
			$log_details->club_id = $club_id; 
			$log_details->address_id = $address_id; 
			$log_details->start = $start;
			$log_details->duration = $end - $start;
			$log_details->langs = $langs;
			$log_details->notes = $notes;
			$log_details->price = $price;
			$log_details->scoring_id = $scoring_id;
			$log_details->rules_code = $rules_code;
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_EVENT, 'round created', $log_details, $tournament_id, $club_id, $request_league_id);
		}
		
		if ($league_id != $request_league_id)
		{
			// send emails to league managers asking for approval
			$query = new DbQuery('SELECT u.id, u.name, u.email, u.def_lang FROM league_managers l JOIN users u ON u.id = l.user_id WHERE l.league_id = ?', $request_league_id);
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_email, $user_lang) = $row;
				if (!is_valid_lang($user_lang))
				{
					$user_lang = get_lang($league_langs);
					if (!is_valid_lang($user_lang))
					{
						$user_lang = LANG_RUSSIAN;
					}
				}
				list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email_tournament_approve.php';
				$stars_str = '';
				for ($i = 0; $i < floor($stars) && $i < 5; ++$i)
				{
					$stars_str .= '★';
				}
				for (; $i < $stars && $i < 5; ++$i)
				{
					$stars_str .= '✯';
				}
				for (; $i < 5; ++$i)
				{
					$stars_str .= '☆';
				}
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_id' => new Tag($user_id),
					'user_name' => new Tag($user_name),
					'league_id' => new Tag($request_league_id),
					'league_name' => new Tag($league_name),
					'tournament_id' => new Tag($tournament_id),
					'tournament_name' => new Tag($name),
					'stars' => new Tag($stars),
					'stars_str' => new Tag($stars_str),
					'club_id' => new Tag($club_id),
					'club_name' => new Tag($club->name),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($user_email, $body, $text_body, $subj);
			}
		}
		else if ($league_id != NULL && $league_id > 0)
		{
			Db::exec(
				get_label('tournament'), 
				'INSERT INTO tournament_approves (user_id, league_id, tournament_id, stars) values (?, ?, ?, ?)',
				$_profile->user_id, $league_id, $tournament_id, $stars);
				
			$log_details = new stdClass();
			$log_details->league_id = $league_id;
			$log_details->tournament_id = $tournament_id;
			$log_details->stars = $stars; 
			db_log(LOG_OBJECT_TOURNAMENT, 'approved', $log_details, $tournament_id, $club_id, $league_id);
		}
		
		Db::commit();
		$this->response['tournament_id'] = $tournament_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create tournament.');
		$help->request_param('name', 'Tournament name.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('league_id', 'League that this tournament belongs to. Set 0 or negative for internal club tournament.', 'internal club tournament is created.');
		$help->request_param('stars', 'Tournament stars. Floating point value from 0 to 5. League managers will receive emails suggesting to accept the tournament and the stars, unless the user who creates the tournament is also the league manager. Managers can accept; accept but change the stars; decline the tournament. See <i>accept_tournament</i> request.', 'it set to 0.');
		$help->request_param('start', 'Tournament start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Tournament end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'empty.');
		$help->request_param('rules_code', 'Rules for this tournament.', 'default club or club-league rules are used. Depending on the league.');
		$help->request_param('scoring_id', 'Scoring id for this tournament.', 'default club scoring system is used.');
		$help->request_param('scoring_weight', 'Weight of the points for this tournament. All scores multiplied by it.', 'is set to 1');
		$help->request_param('notes', 'Tournament notes. Just a text.', 'empty.');
		$help->request_param('langs', 'Languages on this tournament. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'all club languages are used.');
		$help->request_param('flags', 'Tournament flags. A bit combination of:', '0'); // todo
		$help->request_param('address_id', 'Address id of the tournament.', '<q>address</q>, <q>city</q>, and <q>country</q> are used to create new address.');
		$help->request_param('address', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', '<q>address_id</q> must be set');
		$help->request_param('country', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', '<q>address_id</q> must be set');
		$help->request_param('city', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', '<q>address_id</q> must be set');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$event = new Event();
		$event->set_club($club);
	
		$event->name = get_required_param('name');
		$event->hour = get_required_param('hour');
		$event->minute = get_required_param('minute');
		$event->duration = get_required_param('duration');
		$event->price = get_optional_param('price', '');
		$event->rules_code = get_optional_param('rules_code', $club->rules_code);
		$event->scoring_id = get_optional_param('scoring_id', $club->scoring_id);
		$event->scoring_weight = get_optional_param('scoring_weight', 1);
		$event->planned_games = get_optional_param('planned_games', 0);
		$event->notes = '';
		if (isset($_REQUEST['notes']))
		{
			$event->notes = $_REQUEST['notes'];
		}
		
		$event->flags = set_flag($event->flags, EVENT_FLAG_REG_ON_ATTEND, isset($_REQUEST['reg_on_attend']));
		$event->flags = set_flag($event->flags, EVENT_FLAG_PWD_REQUIRED, isset($_REQUEST['pwd_required']));
		$event->flags = set_flag($event->flags, EVENT_FLAG_PWD_REQUIRED, isset($_REQUEST['all_moderate']));
		
		$event->langs = 0;
		if (isset($_REQUEST['langs']))
		{
			$event->langs = (int)$_REQUEST['langs'];
			$event->langs &= $club->langs;
		}
		if ($event->langs == 0)
		{
			$event->langs = $club->langs;
		}
		
		if (isset($_REQUEST['rounds']))
		{
			$rounds = $_REQUEST['rounds'];
			//throw new Exc(json_encode($rounds));
			$event->clear_rounds();
			foreach ($rounds as $round)
			{
				$event->add_round($round["name"], $round["scoring_id"], $round["scoring_weight"], $round["planned_games"]);
			}
			//throw new Exc(json_encode($event->rounds));
		}
		
		$event->addr_id = (int)get_required_param('address_id');
		if ($event->addr_id <= 0)
		{
			$event->addr = get_required_param('address');
			$event->country = get_required_param('country');
			$event->city = get_required_param('city');
		}
		
		Db::begin();
		date_default_timezone_set($event->timezone);
		$time = mktime($event->hour, $event->minute, 0, get_required_param('month'), get_required_param('day'), get_required_param('year'));
		if (isset($_REQUEST['weekdays']))
		{
			$weekdays = $_REQUEST['weekdays'];
			$until = mktime($event->hour, $event->minute, 0, get_required_param('to_month'), get_required_param('to_day'), get_required_param('to_year'));
			if ($time < time())
			{
				$time += 86400; // 86400 - seconds per day
			}
			
			$event_ids = array();
			$weekday = (1 << date('w', $time));
			
			while ($time < $until)
			{
				if (($weekdays & $weekday) != 0)
				{
					$event->set_datetime($time, $event->timezone);
					$event_ids[] = $event->create();
				}
				
				$time += 86400; // 86400 - seconds per day
				$weekday <<= 1;
				if ($weekday > WEEK_FLAG_ALL)
				{
					$weekday = 1;
				}
			}
			
			if (count($event_ids) == 0)
			{
				throw new Exc(get_label('No events found between the dates you specified.'));
			}
		}
		else
		{
			$event->timestamp = $time;
			$event_ids = array($event->create());
		}
		Db::commit();
		$this->response['events'] = $event_ids;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create event.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Event name.');
		$help->request_param('month', 'Month of the event.');
		$help->request_param('day', 'Day of the month of the event.');
		$help->request_param('year', 'Year of the event.');
		$help->request_param('hour', 'Hour when the event starts.');
		$help->request_param('minute', 'Minute when the event starts.');
		$help->request_param('duration', 'Event duration in seconds.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'empty.');
		$help->request_param('rules_code', 'Rules code for this event.', 'default club rules are used.');
		$help->request_param('scoring_id', 'Scoring id for this event.', 'default club scoring system is used.');
		$help->request_param('notes', 'Event notes. Just a text.', 'empty.');
		$help->request_param('reg_on_attend', 'When set, users can register by clicking attend event. We recomend to set it.', '-');
		$help->request_param('pwd_required', 'When set, users have to enter their password to register to the event. We recomend not to set it.', '-');
		$help->request_param('all_moderate', 'When set, any registered user can moderate games.', 'only the users with moderator permission can moderate.');
		$help->request_param('langs', 'Languages on this event. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'all club languages are used.');
		$help->request_param('address_id', 'Address id of the event.', '<q>address</q>, <q>city</q>, and <q>country</q> are used to create new address.');
		$help->request_param('address', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the address line to create.', '<q>address_id</q> must be set');
		$help->request_param('country', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the country name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a country with this name, new country is created.', '<q>address_id</q> must be set');
		$help->request_param('city', 'When address_id is not set, <?php echo PRODUCT_NAME; ?> creates new address. This is the city name for the new address. If <?php echo PRODUCT_NAME; ?> can not find a city with this name, new city is created.', '<q>address_id</q> must be set');
		$help->request_param('weekdays', 'When set, multiple events are created. This is a bit combination of weekdays. When it is set, <?php echo PRODUCT_NAME; ?> creates events between the start date and end date at all weekdays that are set. The flags are:
				<ul>
					<li>1 - Sunday</li>
					<li>2 - Monday</li>
					<li>4 - Tuesday</li>
					<li>8 - Wednesday</li>
					<li>16 - Thursday</li>
					<li>32 - Friday</li>
					<li>64 - Saturday</li>
				</ul>', 'single event is created.');
		$help->request_param('to_month', 'When creating multiple events (<q>weekdays</q> is set) this is the month of the end date.', '<q>weekdays</q> must also be not set');
		$help->request_param('to_day', 'When creating multiple events (<q>weekdays</q> is set) this is the day of the month of the end date.', '<q>weekdays</q> must also be not set');
		$help->request_param('to_year', 'When creating multiple events (<q>weekdays</q> is set) this is the year of the end date.', '<q>weekdays</q> must also be not set');
		$param = $help->request_param('rounds', 'Event rounds in a form of a json array. For example: [{name: "Quater final", scoring_id: 17, scoring_weight: 1, games: 10}, {name: "Semi final", scoring_id: 17, scoring_weight: 1.5, games: 5}, {name: "Final", scoring_id: 17, scoring_weight: 2, games: 2}].', 'Event does not have rounds.'); 
			$param->sub_param('name', 'Round name.');
			$param->sub_param('scoring_id', 'Scoring system id used in this round. All points from different scoring systems accumulate in final result. If a one needs to clear them, they should create a new event.');
			$param->sub_param('scoring_weight', 'Weight of the points in this round. All scores in this round are multiplied by it.', 'is set to 1');
			$param->sub_param('games', 'How many games should be played in this round. The system will automaticaly change round after this number of games is played. Send 0 for changing rounds manually.', 'is set to 0');
		$help->response_param('events', 'Array of ids of the newly created events.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// approve
	//-------------------------------------------------------------------------------------------------------
	function approve_op()
	{
		global $_profile;
		
		$tournament_id = (int)get_required_param('tournament_id');
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		list ($request_league_id, $new_stars, $club_id) = Db::record(get_label('tournament'), 'SELECT request_league_id, stars, club_id FROM tournaments WHERE id = ?', $tournament_id);
		
		$stars = (float)get_optional_param('stars', $new_stars);
		$approve = (bool)get_optional_param('approve', true);
		
		if ($approve)
		{
			$new_stars = $stars = min(max($stars, 0), 5);
			$new_league_id = $league_id;
			
			$query = new DbQuery('SELECT u.id, u.name, a.stars FROM tournament_approves a JOIN users u ON u.id = a.user_id WHERE a.league_id = ? AND a.tournament_id = ? ORDER BY a.stars', $league_id, $tournament_id);
			while ($row = $query->next())
			{
				list ($user_id, $user_name, $user_stars) = $row;
				if ($user_id == $_profile->user_id)
				{
					continue;
				}
				
				if ($user_stars > 0)
				{
					if ($user_stars < $stars)
					{
						if ($new_league_id > 0)
						{
							echo get_label('Approved but it is still [0] stars. Because [1] did not give more.', $user_stars, $user_name);
						}
						$new_stars = $user_stars;
					}
					break;
				}
				
				echo get_label('Approved but it is still disalowed because [0] denied it.', $user_name);
				$new_league_id = NULL;
			}
		}
		else
		{
			$stars = -1;
			$new_league_id = NULL;
			$update_tournament = 2; // update league_id
		}
		
		Db::exec(get_label('approve'), 'INSERT INTO tournament_approves (user_id, league_id, tournament_id, stars) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE stars = ?', $_profile->user_id, $league_id, $tournament_id, $stars, $stars);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->user_id = $_profile->user_id;
			if ($approve)
			{
				$log_details->stars = $stars;
				db_log(LOG_OBJECT_TOURNAMENT, 'approved', $log_details, $tournament_id, $club_id, $league_id);
			}
			else
			{
				db_log(LOG_OBJECT_TOURNAMENT, 'forbade', $log_details, $tournament_id, $club_id, $league_id);
			}
		}
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET league_id = ?, stars = ? WHERE id = ?', $new_league_id, $new_stars, $tournament_id);
		Db::commit();
	}
	
	function approve_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
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
			db_log(LOG_OBJECT_TOURNAMENT, 'canceled', NULL, $event_id, $club_id);
		}
		
		$some_sent = false;
		$query = new DbQuery('SELECT id, status FROM event_emails WHERE event_id = ?', $event_id);
		while ($row = $query->next())
		{
			list ($mailing_id, $mailing_status) = $row;
			switch ($mailing_status)
			{
				case MAILING_WAITING:
					Db::exec(get_label('email'), 'UPDATE event_emails SET status = ' . MAILING_CANCELED . ' WHERE id = ?', $mailing_id);
					if (Db::affected_rows() > 0)
					{
						db_log(LOG_OBJECT_TOURNAMENT, 'canceled', NULL, $mailing_id, $club_id);
					}
					break;
				case MAILING_SENDING:
				case MAILING_COMPLETE:
					$some_sent = true;
					break;
			}
		}
		Db::commit();
		
		if ($some_sent)
		{
			$this->response['question'] = get_label('Some event emails are already sent. Do you want to send cancellation email?'); 
		}
		else
		{
			list($reg_count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE event_id = ? AND coming_odds > 0', $event_id);
			if ($reg_count > 0)
			{
				$this->response['question'] = get_label('Some users have already registered for this event. Do you want to send cancellation email?'); 
			}
		}
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
			db_log(LOG_OBJECT_TOURNAMENT, 'restored', NULL, $event_id, $club_id);
		}
		Db::commit();
		$this->response['question'] = get_label('The event is restored. Do you want to change event mailing?');
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Restore canceled event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$tournament_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_profile->user_def_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO tournament_comments (time, user_id, comment, tournament_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $tournament_id, $lang);
		
		list($tournament_id, $tournament_name, $tournament_start_time, $tournament_timezone, $tournament_addr) = 
			Db::record(get_label('tournament'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM tournaments e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $tournament_id);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN tournament_invitations ti ON u.id = ti.user_id' .
			' WHERE ti.status <> ' . TOURNAMENT_INVITATION_STATUS_DECLINED . ')' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN tournament_comments c ON c.user_id = u.id' .
			' WHERE c.tournament_id = ?)', $tournament_id, $tournament_id);
		//echo $query->get_parsed_sql();
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
				'user_name' => new Tag($user_name),
				'tournament_id' => new Tag($tournament_id),
				'tournament_name' => new Tag($tournament_name),
				'tournament_date' => new Tag(format_date('l, F d, Y', $tournament_start_time, $tournament_timezone, $user_lang)),
				'tournament_time' => new Tag(format_date('H:i', $tournament_start_time, $tournament_timezone, $user_lang)),
				'addr' => new Tag($tournament_addr),
				'code' => new Tag($code),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email_comment_tournament.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_TOURNAMENT, $tournament_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the tournament.');
		$help->request_param('id', 'Tournament id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Tournament Operations', CURRENT_VERSION);

?>