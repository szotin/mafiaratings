<?php

require_once 'include/session.php';
require_once 'include/event.php';
require_once 'include/email.php';

ob_start();
$result = array();

try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL)
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$id = $_REQUEST['id'];
	
	if (isset($_REQUEST['attend']))
	{
		$odds = $_REQUEST['odds'];
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
		
		Db::begin();
		
		Db::exec(get_label('registration'), 'DELETE FROM event_users WHERE event_id = ? AND user_id = ?', $id, $_profile->user_id);
		Db::exec(get_label('registration'), 'DELETE FROM registrations WHERE event_id = ? AND user_id = ?', $id, $_profile->user_id);
		Db::exec(get_label('registration'), 
			'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me, late) VALUES (?, ?, ?, ?, ?)',
			$id, $_profile->user_id, $odds, $friends, $late);
		
		if ($odds >= 100)
		{
			$nick = '';
			if (isset($_REQUEST['nick']))
			{
				$nick = $_REQUEST['nick'];
			}
			if ($nick == '')
			{
				$nick = $_profile->user_name;
			}
		
			check_nickname($nick, $id);
			Db::exec(get_label('registration'),
				'INSERT INTO registrations (club_id, user_id, nick_name, duration, start_time, event_id) ' . 
				'SELECT club_id, ?, ?, duration, start_time, id FROM events WHERE id = ?',
				$_profile->user_id, $nick, $id);
		}
		Db::commit();
	}
	else
	{
		$event = new Event();
		$event->load($id);
		if (!$_profile->is_manager($event->club_id))
		{
			throw new Exc(get_label('Unknown [0]', get_label('event')));
		}
		
		if (isset($_REQUEST['get']))
		{
			$date_format = '';
			if (isset($_REQUEST['df']))
			{
				$date_format = $_REQUEST['df'];
			}

			$time_format = '';
			if (isset($_REQUEST['tf']))
			{
				$time_format = $_REQUEST['tf'];
			}

			$e = new stdClass();
			$e->id = $event->id;
			$e->name = $event->name;
			$e->price = $event->price;
			$e->club_id = $event->club_id;
			$e->club_name = $event->club_name;
			$e->club_url = $event->club_url;
			$e->start = $event->timestamp;
			$e->duration = $event->duration;
			$e->addr_id = $event->addr_id;
			$e->addr = $event->addr;
			$e->addr_url = $event->addr_url;
			$e->timezone = $event->timezone;
			$e->city = $event->city;
			$e->country = $event->country;
			$e->notes = $event->notes;
			$e->langs = $event->langs;
			$e->flags = $event->flags;
			$e->rules_id = $event->rules_id;
			$e->scoring_id = $event->scoring_id;
			
			$base = get_server_url() . '/';
			$e->addr_image = '';
			if (($event->addr_flags & ADDR_ICON_MASK) != 0)
			{
				$e->addr_image = $base . ADDRESS_PICS_DIR . TNAILS_DIR . $e->addr_id . '.jpg';
			}
			
			$e->date_str = format_date($date_format, $e->start, $e->timezone);
			$e->time_str = format_date($time_format, $e->start, $e->timezone);
			
			date_default_timezone_set($e->timezone);
			$e->hour = date('G', $e->start);
			$e->minute = round(date('i', $e->start) / 10) * 10;
			
			$result = $e;
		}
		else if (isset($_REQUEST['extend']))
		{
			if ($event->timestamp + $event->duration + EVENT_ALIVE_TIME < time())
			{
				throw new Exc(get_label('The event is too old. It can not be extended.'));
			}
			
			$duration = $_REQUEST['duration'];
			Db::begin();
			Db::exec(get_label('event'), 'UPDATE events SET duration = ? WHERE id = ?', $duration, $event->id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'duration=' . $duration;
				db_log('event', 'Extended', $log_details, $event->id, $event->club_id);
			}
			Db::commit();
		}
		else if (isset($_REQUEST['cancel']))
		{
			Db::begin();
			list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM events WHERE id = ?', $id);
			
			Db::exec(get_label('event'), 'UPDATE events SET flags = (flags | ' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $id);
			if (Db::affected_rows() > 0)
			{
				db_log('event', 'Canceled', NULL, $id, $club_id);
			}
			
			$some_sent = false;
			$query = new DbQuery('SELECT id, status FROM event_emails WHERE event_id = ?', $id);
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
				$result['question'] = get_label('Some event emails are already sent. Do you want to send cancellation email?'); 
			}
			else
			{
				list($reg_count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE event_id = ? AND coming_odds > 0', $id);
				if ($reg_count > 0)
				{
					$result['question'] = get_label('Some users have already registered for this event. Do you want to send cancellation email?'); 
				}
			}
		}
		else if (isset($_REQUEST['restore']))
		{
			Db::begin();
			Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_CANCELED . ') WHERE id = ?', $id);
			if (Db::affected_rows() > 0)
			{
				list($club_id) = Db::record(get_label('event'), 'SELECT club_id FROM events WHERE id = ?', $id);
				db_log('event', 'Restored', NULL, $id, $club_id);
			}
			Db::commit();
			$result['question'] = get_label('The event is restored. Do you want to change event mailing?');
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>