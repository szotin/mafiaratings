<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/game_stats.php';

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
	
	function create_op_help()
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
		$help->request_param('rules_code', 'Rules for this event.', 'default club rules are used.');
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
		$this->response['scoring_weight'] = $event->scoring_weight;
		$this->response['planned_games'] = $event->planned_games;
		
		$base = get_server_url() . '/';
		if (($event->addr_flags & ADDR_ICON_MASK) != 0)
		{
			$this->response['addr_image'] = $base . ADDRESS_PICS_DIR . TNAILS_DIR . $event->addr_id . '.jpg';
		}
		
		$this->response['date_str'] = format_date($date_format, $event->timestamp, $event->timezone);
		$this->response['time_str'] = format_date($time_format, $event->timestamp, $event->timezone);
		
		date_default_timezone_set($event->timezone);
		$this->response['hour'] = date('G', $event->timestamp);
		$this->response['minute'] = round(date('i', $event->timestamp) / 10) * 10;
		
		if (count($event->rounds) > 0)
		{
			$this->response['rounds'] = $event->rounds;
		}
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
		$param = $help->response_param('rounds', 'Event rounds in a form of a json array. For example: [{name: "Quater final", scoring_id: 17, scoring_weight: 1, games: 10}, {name: "Semi final", scoring_id: 17, scoring_weight: 1.5, games: 5}, {name: "Final", scoring_id: 17, scoring_weight: 2, games: 2}].'); 
			$param->sub_param('name', 'Round name.');
			$param->sub_param('scoring_id', 'Scoring system id used in this round. All points from different scoring systems accumulate in final result. If a one needs to clear them, they should create a new event.');
			$param->sub_param('scoring_weight', 'Weight of the points in this round. All scores in this round are multiplied by it.');
			$param->sub_param('games', 'How many games should be played in this round. The system will automaticaly change round after this number of games is played. Send 0 for changing rounds manually.');
		
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
	// set_round
	//-------------------------------------------------------------------------------------------------------
	function set_round_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		check_permissions(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, $event->club_id);
		
		$time = time();
		if ($event->timestamp + $event->duration < $time)
		{
			throw new Exc(get_label('The event over. Please extend it first.'));
		}
		
		$round = (int)get_required_param('round');
		$finish_event = false;
		if ($round < 0 || $round > count($event->rounds))
		{
			$round = count($event->rounds);
		}
		
		Db::begin();
		Db::exec(get_label('event'), 'UPDATE events SET round_num = ? WHERE id = ?', $round, $event->id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->round = $round;
			db_log(LOG_OBJECT_EVENT, 'round changed', $log_details, $event->id, $event->club_id);
		}
		Db::commit();
	}
	
	function set_round_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, 'Change current round for the event. Note that round changes automatically when a number of games for the round exceeds round.planned_games count. However when planned_games is 0, manual change using this function is required.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('round', 'Round number. 0 for main round, and consecutive numbers for the next rounds. If round number is greater than number of rounds, the event becomes finished.');
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
		
		$some_sent = false;
		$query = new DbQuery('SELECT id, status FROM event_mailings WHERE event_id = ?', $event_id);
		while ($row = $query->next())
		{
			list ($mailing_id, $mailing_status) = $row;
			switch ($mailing_status)
			{
				case MAILING_WAITING:
					Db::exec(get_label('mailing'), 'DELET FROM event_mailings WHERE id = ?', $mailing_id);
					if (Db::affected_rows() > 0)
					{
						db_log(LOG_OBJECT_EVENT_MAILINGS, 'deleted', NULL, $mailing_id, $club_id);
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
			db_log(LOG_OBJECT_EVENT, 'restored', NULL, $event_id, $club_id);
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
		
		$query = new DbQuery('SELECT id, log FROM games WHERE event_id = ? AND result <> 0', $event_id);
		while ($row = $query->next())
		{
			list ($game_id, $game_log) = $row;
			$gs = new GameState();
			$gs->init_existing($game_id, $game_log);
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
			Db::exec(get_label('registration'), 'UPDATE registrations SET nick_name = ? WHERE user_id = ? AND event_id = ?', $new_user_id, $event_id);
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
		$help->request_param('details', 'Detailed explanation why user recieves or looses points.', 'empty.');
		
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
		$help->request_param('details', 'Detailed explanation why user recieves or looses points.', 'remains the same');
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
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email_comment_event.php';
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