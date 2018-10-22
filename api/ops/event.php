<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/message.php';

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
		$this->check_permissions($club_id);
		$club = $_profile->clubs[$club_id];
		
		$event = new Event();
		$event->set_club($club);
	
		$event->name = get_required_param('name');
		$event->hour = get_required_param('hour');
		$event->minute = get_required_param('minute');
		$event->duration = get_required_param('duration');
		$event->price = get_optional_param('price', '');
		$event->rules_id = get_optional_param('rules_id', $club->rules_id);
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
		$help = new ApiHelp('Create event.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Event name.');
		$help->request_param('month', 'Month of the event.');
		$help->request_param('day', 'Day of the month of the event.');
		$help->request_param('year', 'Year of the event.');
		$help->request_param('hour', 'Hour when the event starts.');
		$help->request_param('minute', 'Minute when the event starts.');
		$help->request_param('duration', 'Event duration in seconds.');
		$help->request_param('price', 'Admission rate. Just a string explaing it.', 'empty.');
		$help->request_param('rules_id', 'Rules id for this event.', 'default club rules are used.');
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
	
	function create_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// attend
	//-------------------------------------------------------------------------------------------------------
	function attend_op()
	{
		global $_profile;
		
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
		$help = new ApiHelp('Tell the system about the plans to attend the upcoming event.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('odds', 'The odds of coming. An integer from 0 to 100. Sending 0 means that current user is not planning to attend the event. If odds are 100, the user gets registered for the event.', '100% is used.');
		$help->request_param('late', 'I current user can not be in time, this is how much late will he/she be in munutes.', 'user is assumed to be in time.');
		$help->request_param('friends', 'How many friends are coming with the current user.', '0 is used.');
		$help->request_param('nickname', 'Nickname for the event. If it is set and not empty, the user is registered for the event even if the odds are not 100%.', 'nickname is the same as user name.');
		return $help;
	}
	
	function attend_op_permissions()
	{
		return PERMISSION_USER;
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
		$this->check_permissions($event->club_id);
		
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
		$this->response['rules_id'] = $event->rules_id;
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
		$help = new ApiHelp('Get event details. TODO: it should be moved to <q>get</q> API.');
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
		$help->response_param('rules_id', 'Game rules id.');
		$help->response_param('scoring_id', 'Scoring system id.');
		return $help;
	}
	
	function get_op_permissions()
	{
		return PERMISSION_CLUB_MEMBER;
	}

	//-------------------------------------------------------------------------------------------------------
	// extend
	//-------------------------------------------------------------------------------------------------------
	function extend_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		$this->check_permissions($event->club_id);
		
		if ($event->timestamp + $event->duration + EVENT_ALIVE_TIME < time())
		{
			throw new Exc(get_label('The event is too old. It can not be extended.'));
		}
		
		$duration = (int)get_required_param('duration');
		Db::begin();
		Db::exec(get_label('event'), 'UPDATE events SET duration = ? WHERE id = ?', $duration, $event->id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'duration=' . $duration;
			db_log('event', 'Extended', $log_details, $event->id, $event->club_id);
		}
		Db::commit();
	}
	
	function extend_op_help()
	{
		$help = new ApiHelp('Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('duration', 'New event duration. Send 0 if you want to end event now.');
		return $help;
	}
	
	function extend_op_permissions()
	{
		return PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// set_round
	//-------------------------------------------------------------------------------------------------------
	function set_round_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		$this->check_permissions($event->club_id);
		
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
			$log_details = 'round=' . $round;
			db_log('event', 'Round changed', $log_details, $event->id, $event->club_id);
		}
		Db::commit();
	}
	
	function set_round_op_help()
	{
		$help = new ApiHelp('Change current round for the event. Note that round changes automatically when a number of games for the round exceeds round.planned_games count. However when planned_games is 0, manual change using this function is required.');
		$help->request_param('event_id', 'Event id.');
		$help->request_param('round', 'Round number. 0 for main round, and consecutive numbers for the next rounds. If round number is greater than number of rounds, the event becomes finished.');
		return $help;
	}
	
	function set_round_op_permissions()
	{
		return PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// cancel
	//-------------------------------------------------------------------------------------------------------
	function cancel_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		$this->check_permissions($event->club_id);
		
		Db::begin();
		list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags | ' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			db_log('event', 'Canceled', NULL, $event_id, $club_id);
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
						db_log('event_emails', 'Canceled', NULL, $mailing_id, $club_id);
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
		$help = new ApiHelp('Cancel event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}
	
	function cancel_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$event_id = (int)get_required_param('event_id');
		$event = new Event();
		$event->load($event_id);
		$this->check_permissions($event->club_id);
		
		Db::begin();
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $event_id);
		if (Db::affected_rows() > 0)
		{
			list($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
			db_log('event', 'Restored', NULL, $event_id, $club_id);
		}
		Db::commit();
		$this->response['question'] = get_label('The event is restored. Do you want to change event mailing?');
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp('Restore canceled event.');
		$help->request_param('event_id', 'Event id.');
		return $help;
	}
	
	function restore_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
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
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id;
			$tags = array(
				'uid' => new Tag($user_id),
				'eid' => new Tag($event_id),
				'ename' => new Tag($event_name),
				'edate' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
				'etime' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'uname' => new Tag($user_name),
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
		$help = new ApiHelp('Leave a comment on the event.');
		$help->request_param('id', 'Event id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
	
	function comment_op_permissions()
	{
		return PERMISSION_USER;
	}
}

$page = new ApiPage();
$page->run('Event Operations', CURRENT_VERSION);

?>