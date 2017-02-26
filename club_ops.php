<?php

require_once 'include/session.php';
require_once 'include/club.php';
require_once 'include/email.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/address.php';
require_once 'include/game_rules.php';
require_once 'include/event.php';
require_once 'include/url.php';

ob_start();
$result = array();
	
try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	$id = $_REQUEST['id'];
	
/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';*/
	
	if (isset($_REQUEST['decline']))
	{
		if (!$_profile->is_admin())
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$reason = $_REQUEST['reason'];
	
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang) = Db::record(
			get_label('club'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang FROM club_requests c JOIN users u ON c.user_id = u.id WHERE c.id = ?',
			$id);
		
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $id);
		db_log('club_request', 'Declined', NULL, $id);
		Db::commit();
		if ($reason != '')
		{
			$lang = get_lang_code($user_lang);
			list($subj, $body) = include 'include/languages/' . $lang . '/email_decline_club.php';
			$tags = array(
				'uname' => new Tag($user_name),
				'reason' => new Tag($reason),
				'club_name' => new Tag($name));
			$body = parse_tags($body, $tags);
			send_email($user_email, $body, $subj);
		}
	}
	else if (isset($_REQUEST['accept']))
	{
		if (!$_profile->is_admin())
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$name = $_REQUEST['name'];
		
		Db::begin();
		list($url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone, $city_id, $city_name) = Db::record(
			get_label('club'),
			'SELECT c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone, c.city_id, i.name_en FROM club_requests c' .
				' JOIN users u ON c.user_id = u.id' .
				' JOIN cities i ON c.city_id = i.id' .
				' WHERE c.id = ?',
			$id);
		
		check_club_name($name);
		
		$rules = new GameRules();
		$rules_id = $rules->save();
		
		list ($city_name) = Db::record(get_label('city'), 'SELECT name_' . $_lang_code . ' FROM cities WHERE id = ?', $city_id);
		
		Db::exec(
			get_label('club'),
			'INSERT INTO clubs (name, langs, rules_id, flags, web_site, email, phone, city_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?)',
			$name, $langs, $rules_id, $url, $email, $phone, $city_id);
			
		list ($club_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
		
		$log_details =
			'name=' . $name .
			"<br>langs=" . $langs .
			"<br>rules=" . $rules_id .
			"<br>flags=" . NEW_CLUB_FLAGS .
			"<br>url=" . $url . 
			"<br>email=" . $email .
			"<br>phone=" . $phone .
			"<br>city=" . $city_name . ' (' . $city_id . ')';
		db_log('club', 'Created', $log_details, $club_id, $club_id);

		if (($user_flags & U_PERM_ADMIN) == 0)
		{
			Db::exec(
				get_label('user'), 
				'INSERT INTO user_clubs (user_id, club_id, flags) VALUES (?, ?, ' . (UC_NEW_PLAYER_FLAGS | UC_PERM_MODER | UC_PERM_MANAGER) . ')',
				$user_id, $club_id);
			db_log('user', 'Became a manager of the club', NULL, $user_id, $club_id);
			
			Db::exec(
				get_label('user'), 
				'UPDATE users SET city_id = ? WHERE id = ?',
				$city_id, $user_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'city=' . $city_name . ' (' . $city_id . ')';
				db_log('user', 'Changed', $log_details, $user_id);
			}
		}
			
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $id);
		db_log('club_request', 'Accepted', NULL, $id, $club_id);
		
		// create default event emails
		$l = LANG_NO;
		while (($l = get_next_lang($l, $langs)) != LANG_NO)
		{
			$lang = get_lang_code($l);
			$event_emails = include 'include/languages/' . $lang . '/event_emails.php';
			foreach ($event_emails as $event_email)
			{
				list($ename, $esubj, $ebody) = $event_email;
				Db::exec(
					get_label('email'),
					'INSERT INTO email_templates (club_id, name, subject, body) VALUES (?, ?, ?, ?)',
					$club_id, $ename, $esubj, $ebody);
				list ($template_id) = Db::record(get_label('email'), 'SELECT LAST_INSERT_ID()');
				$log_details = 'name=' . $ename . "<br>subject=" . $esubj . "<br>body=<br>" . $ebody;
				db_log('email_template', 'Created', $log_details, $template_id, $club_id);
			}
		}
		
		// send email
		$lang = get_lang_code($user_lang);
		$code = generate_email_code();
		$tags = array(
			'uid' => new Tag($user_id),
			'code' => new Tag($code),
			'uname' => new Tag($user_name),
			'cname' => new Tag($name),
			'url' => new Tag('http://' . get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id));
		list($subj, $body) = include 'include/languages/' . $lang . '/email_accept_club.php';
		$body = parse_tags($body, $tags);
		send_notification($user_email, $body, $subj, $user_id, EMAIL_OBJ_CREATE_CLUB, $club_id, $code);
		
		Db::commit();
		
		if ($_profile->user_id == $user_id)
		{
			$_profile->update_clubs();
		}
	}
	else if (isset($_REQUEST['restore']))
	{
		if (!check_manager_permission($id)) // can not use $_profile->is_manager because the club is retired
		{
			throw new Exc(get_label('No permissions'));
		}
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags & ~' . CLUB_FLAG_RETIRED . ' WHERE id = ?', $id);
		if (Db::affected_rows() > 0)
		{
			db_log('club', 'Restored', NULL, $id, $id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	else
	{
		if (!isset($_profile->clubs[$id]))
		{
			throw new Exc(get_label('No permissions'));
		}
		$club = $_profile->clubs[$id];
		
		if (isset($_REQUEST['retire']))
		{
			Db::begin();
			Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_RETIRED . ' WHERE id = ?', $club->id);
			if (Db::affected_rows() > 0)
			{
				db_log('club', 'Retired', NULL, $club->id, $club->id);
			}
			Db::commit();
			$_profile->update_clubs();
		}
		else if (isset($_REQUEST['edit']))
		{
			$name = trim($_REQUEST['name']);
			$url = check_url($_REQUEST['url']);
			$email = trim($_REQUEST['email']);
			$phone = $_REQUEST['phone'];
			$price = $_REQUEST['price'];
			$langs = $_REQUEST['langs'];
			
			check_club_name($name, $club->id);
			if ($langs == 0)
			{
				throw new Exc(get_label('Please select at least one language.'));
			}
			
			if ($email != '' && !is_email($email))
			{
				throw new Exc(get_label('[0] is not a valid email address.', $email));
			}
			
			Db::begin();
			$city_id = retrieve_city_id($_REQUEST['city'], retrieve_country_id($_REQUEST['country']), $club->timezone);
			
			Db::exec(
				get_label('club'), 
				'UPDATE clubs SET name = ?, web_site = ?, langs = ?, email = ?, phone = ?, price = ?, city_id = ? WHERE id = ?',
				$name, $url, $langs, $email, $phone, $price, $city_id, $club->id);
			if (Db::affected_rows() > 0)
			{
				list($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
				$log_details =
					'name=' . $name .
					"<br>web_site=" . $url .
					"<br>langs=" . $langs .
					"<br>email=" . $email .
					"<br>phone=" . $phone .
					"<br>price=" . $price .
					"<br>city=" . $city_name . ' (' . $city_id . ')';
				db_log('club', 'Changed', $log_details, $club->id, $club->id);
			}
			Db::commit();
				
			$_profile->update_clubs();
		}
		else if (isset($_REQUEST['new_address']))
		{
			$address = $_REQUEST['address'];
			if ($address == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('address')));
			}
			
			$name = '';
			if (isset($_REQUEST['name']))
			{
				$name = $_REQUEST['name'];
			}
			if ($name == '')
			{
				$name = $address;
			}
			
			Db::begin();
			$city_id = retrieve_city_id($_REQUEST['city'], retrieve_country_id($_REQUEST['country']), $club->timezone);
			$sc_name = htmlspecialchars($name, ENT_QUOTES);
			$sc_address = htmlspecialchars($address, ENT_QUOTES);
	
			check_address_name($name, $id);
	
			Db::exec(
				get_label('address'), 
				'INSERT INTO addresses (name, club_id, address, map_url, city_id, flags) values (?, ?, ?, \'\', ?, 0)',
				$sc_name, $id, $sc_address, $city_id);
			list ($addr_id) = Db::record(get_label('address'), 'SELECT LAST_INSERT_ID()');
			list ($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
			$log_details =
				'name=' . $sc_name .
				"<br>address=" . $sc_address .
				"<br>city=" . $city_name . ' (' . $city_id . ')';
			db_log('address', 'Created', $log_details, $addr_id, $id);
	
			$warning = load_map_info($addr_id);
			if ($warning != NULL)
			{
				echo '<p>' . $warning . '</p>';
			}
	
			Db::commit();
		}
		else if (isset($_REQUEST['new_event']))
		{
			$event = new Event();
			$event->set_club($club);
		
			$event->name = $_REQUEST['name'];
			$event->hour = $_REQUEST['hour'];
			$event->minute = $_REQUEST['minute'];
			$event->duration = $_REQUEST['duration'];
			$event->price = $_REQUEST['price'];
			$event->rules_id = $_REQUEST['rules'];
			$event->system_id = $_REQUEST['system'];
			if ($event->system_id <= 0)
			{
				$event->system_id = NULL;
			}
			$event->notes = $_REQUEST['notes'];
			$event->flags = $_REQUEST['flags'];
			$event->langs = $_REQUEST['langs'];
			$event->addr_id = $_REQUEST['addr'];
			if ($event->addr_id <= 0)
			{
				$event->addr = $_REQUEST['new_addr'];
				$event->country = $_REQUEST['country'];
				$event->city = $_REQUEST['city'];
			}
			
			Db::begin();
			date_default_timezone_set($event->timezone);
			$time = mktime($event->hour, $event->minute, 0, $_REQUEST['month'], $_REQUEST['day'], $_REQUEST['year']);
			if (isset($_REQUEST['weekdays']))
			{
				$weekdays = $_REQUEST['weekdays'];
				$until = mktime($event->hour, $event->minute, 0, $_REQUEST['to_month'], $_REQUEST['to_day'], $_REQUEST['to_year']);
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
			$result['events'] = $event_ids;
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
	$result['message'] = $message;
}

echo json_encode($result);

?>