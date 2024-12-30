<?php

setDir();

require_once 'include/branding.php';
require_once 'include/db.php';
require_once 'include/email.php';
require_once 'include/languages.php';
require_once 'include/constants.php';
require_once 'include/localization.php';
require_once 'include/error.php';
require_once 'include/event_mailing.php';

if ($_SERVER['HTTP_HOST'])
{
	define('EOL', " <br>\n");
}
else
{
	define('EOL', "\n");
}

function setDir()
{
	// Set the current working directory to the directory of the script.
	// This script is sometimes called from the other directories - for auto sending, so we need to change the directory
	$pos = strrpos(__FILE__, '/');
	if ($pos === false)
	{
		$pos = strrpos(__FILE__, '\\');
		if ($pos === false)
		{
			return;
		}
	}
	$dir = substr(__FILE__, 0, $pos);
	chdir($dir);
}

function sendTournamentRegNotification($tournament_id, $tournament_name, $manager_id, $manager_name, $manager_email, $manager_lang, $new_regs)
{
	//echo $tournament_name . ' - ' . $new_regs . ': ' . $manager_name . EOL;
	
	$lang = get_lang_code($manager_lang);
	$tags = array(
		'root' => new Tag(get_server_url()),
		'new_regs' => new Tag($new_regs),
		'user_id' => new Tag($manager_id),
		'user_name' => new Tag($manager_name),
		'tournament_id' => new Tag($tournament_id),
		'tournament_name' => new Tag($tournament_name));
	list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email/tournament_new_reg.php';
	$body = parse_tags($body, $tags);
	$text_body = parse_tags($text_body, $tags);
	send_email($manager_email, $body, $text_body, $subj);
}

define('NO_TEXT', 'Please enable http in your email client in order to read this message');
define('MAX_EMAILS', 25);
try
{
	$server_name = get_server_url();
	$emails_remaining = MAX_EMAILS;
	$mailing_status = array();
	
	$time = time();
	date_default_timezone_set('America/Vancouver');
	$hour = date('G', $time);
	
	Db::begin();
	if ($hour == 23)
	{
		$query = new DbQuery(
			'SELECT t.id, t.name, t.club_id, count(tu.user_id)'.
			' FROM tournament_users tu'.
			' JOIN tournaments t ON t.id = tu.tournament_id'.
			' WHERE (tu.flags & '.(USER_TOURNAMENT_FLAG_NOT_ACCEPTED | USER_TOURNAMENT_MANAGER_NOTIFIED).') = '.USER_TOURNAMENT_FLAG_NOT_ACCEPTED.
			' GROUP BY t.id'.
			' ORDER BY t.id');
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $club_id, $new_regs) = $row;
			$send_to_club_managers = true;
			$query1 = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM tournament_users tu'.
				' JOIN users u ON u.id = tu.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE (tu.flags & '.USER_PERM_MANAGER.') <> 0 AND tu.tournament_id = ?', $tournament_id);
			while ($row1 = $query1->next())
			{
				list ($manager_id, $manager_name, $manager_email, $manager_lang) = $row1;
				sendTournamentRegNotification($tournament_id, $tournament_name, $manager_id, $manager_name, $manager_email, $manager_lang, $new_regs);
				--$emails_remaining;
				$send_to_club_managers = false;
			}
			
			if ($send_to_club_managers)
			{
				$query1 = new DbQuery(
					'SELECT u.id, nu.name, u.email, u.def_lang'.
					' FROM club_users cu'.
					' JOIN users u ON u.id = cu.user_id'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
					' WHERE (cu.flags & '.USER_PERM_MANAGER.') <> 0 AND cu.club_id = ?', $club_id);
				while ($row1 = $query1->next())
				{
					list ($manager_id, $manager_name, $manager_email, $manager_lang) = $row1;
					sendTournamentRegNotification($tournament_id, $tournament_name, $manager_id, $manager_name, $manager_email, $manager_lang, $new_regs);
					--$emails_remaining;
				}
			}
		}
		Db::exec(get_label('user'), 'UPDATE tournament_users SET flags = flags | ' . USER_TOURNAMENT_MANAGER_NOTIFIED);
	}

	$event_emails = array();
	$query = new DbQuery(
		'SELECT ee.id, ee.type, ee.langs, ee.flags, e.id, e.name, e.start_time, e.notes, e.languages, a.id, a.address, a.map_url, i.timezone, c.id, c.name'.
		' FROM event_mailings ee' . 
		' JOIN events e ON e.id = ee.event_id' .
		' JOIN addresses a ON a.id = e.address_id' . 
		' JOIN clubs c ON c.id = e.club_id' .
		' JOIN cities i ON i.id = a.city_id' .
		' WHERE ee.status in (' . MAILING_WAITING . ', ' . MAILING_SENDING . ')' .
		' AND e.start_time - ee.send_time <= UNIX_TIMESTAMP() ' .
		' ORDER BY ee.send_time');
	// echo $query->get_parsed_sql();
	// echo '</br>';
	$image_base = $server_name . '/' . ADDRESS_PICS_DIR . TNAILS_DIR;
	while (($row = $query->next()) && $emails_remaining > 0)
	{
		list(
			$mailing_id, $mailing_type, $mailing_langs, $mailing_flags,
			$event_id, $event_name, $event_start_time, $event_notes, $event_langs,
			$addr_id, $addr, $addr_url, $timezone, $club_id,
			$club_name) = $row;
			
		// echo '<br>$mailing_id=' . $mailing_id . '<br>$mailing_lang=' . $mailing_lang . '<br>$event_langs=' . $event_langs . '<br>$event_id=' . $event_id . '<br>$club_name=' . $club_name . '<br>';
		
		$count = 0;
		$to_flags = ($mailing_flags & MAILING_FLAG_TO_ALL);
		if ($to_flags != 0)
		{
			date_default_timezone_set($timezone);
			
			$common_tags = get_bbcode_tags();
			$common_tags['root'] = new Tag(get_server_url());
			$common_tags['event_name'] = new Tag($event_name);
			$common_tags['event_id'] = new Tag($event_id);
			$common_tags['notes'] = new Tag($event_notes);
			$common_tags['address'] = new Tag($addr);
			$common_tags['address_url'] = new Tag($addr_url);
			$common_tags['address_id'] = new Tag($addr_id);
			$common_tags['address_image'] = new Tag($image_base . $addr_id . '.jpg');
			$common_tags['club_name'] = new Tag($club_name);
			$common_tags['club_id'] = new Tag($club_id);
			$common_tags['accept_btn'] = new Tag('<input type="submit" name="accept" value="#">');
			$common_tags['decline_btn'] = new Tag('<input type="submit" name="decline" value="#">');
			$common_tags['unsub_btn'] = new Tag('<input type="submit" name="unsub" value="#">');
			
			$condition = new SQL('(u.languages & ?) <> 0' . 
				' AND u.email <> \'\'' .
				' AND uc.club_id = ?' .
				' AND (uc.flags & ' . (USER_PERM_PLAYER | USER_CLUB_FLAG_SUBSCRIBED) . ') = ' . (USER_PERM_PLAYER | USER_CLUB_FLAG_SUBSCRIBED) .
				' AND u.id NOT IN (SELECT user_id FROM emails WHERE obj = ' . EMAIL_OBJ_EVENT . ' AND obj_id = ?)',
				$mailing_langs, $club_id, $mailing_id);
		
			if ($to_flags != MAILING_FLAG_TO_ALL)
			{
				if ($to_flags & MAILING_FLAG_TO_ATTENDED)
				{
					if ($to_flags & MAILING_FLAG_TO_DECLINED)
					{
						$condition->add(' AND u.id IN (SELECT user_id FROM event_users WHERE event_id = ?)', $event_id);
					}
					else if ($to_flags & MAILING_FLAG_TO_DESIDING)
					{
						$condition->add(' AND u.id NOT IN (SELECT user_id FROM event_users WHERE event_id = ? AND coming_odds <= 0)', $event_id);
					}
					else
					{
						$condition->add(' AND u.id IN (SELECT user_id FROM event_users WHERE event_id = ? AND coming_odds > 0)', $event_id);
					}
				}
				else if ($to_flags & MAILING_FLAG_TO_DECLINED)
				{
					if ($to_flags & MAILING_FLAG_TO_DESIDING)
					{
						$condition->add(' AND u.id NOT IN (SELECT user_id FROM event_users WHERE event_id = ? AND coming_odds > 0)', $event_id);
					}
					else
					{
						$condition->add(' AND u.id IN (SELECT user_id FROM event_users WHERE event_id = ? AND coming_odds <= 0)', $event_id);
					}
				}
				else
				{
					$condition->add(' AND u.id NOT IN (SELECT user_id FROM event_users WHERE event_id = ?)', $event_id);
				}
			}
			
			$query1 = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang, u.languages'.
				' FROM club_users uc'.
				' JOIN users u ON u.id = uc.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE ', $condition);
			$query1->add(' ORDER BY u.id LIMIT ' . $emails_remaining);
			// echo $query1->get_parsed_sql();
			// echo '</br>';
			while ($row1 = $query1->next())
			{
				list($user_id, $user_name, $user_email, $user_lang, $user_langs) = $row1;
				$user_lang = get_event_email_lang($user_lang, $user_langs);
				$email_id = $mailing_type . '-' . $user_lang;
				if (!isset($event_emails[$email_id]))
				{
					$event_emails[$email_id] = get_event_email($mailing_type, $user_lang);
					$common_tags['event_date'] = new Tag(format_date($event_start_time, $timezone, false, $user_lang));
					$common_tags['event_time'] = new Tag(date('H:i', $event_start_time));
					$common_tags['langs'] = new Tag(get_langs_str($event_langs, ', ', LOWERCASE, $user_lang));
					for ($i = 0; $i < count($event_emails[$email_id]); ++$i)
					{
						$event_emails[$email_id][$i] = parse_tags($event_emails[$email_id][$i], $common_tags);
					}
				}
				list($subj, $body, $text_body) = $event_emails[$email_id];
				
				$code = generate_email_code();
				$base_url = get_server_url() . '/email_request.php?user_id=' . $user_id . '&code=' . $code;
				
				$tags = array(
					'user_name' => new Tag($user_name),
					'user_id' => new Tag($user_id),
					'email' => new Tag($user_email),
					'code' => new Tag($code),
					'accept' => new Tag('<a href="' . $base_url . '&accept=1" target="_blank">', '</a>'),
					'decline' => new Tag('<a href="' . $base_url . '&decline=1" target="_blank">', '</a>'),
					'unsub' => new Tag('<a href="' . $base_url . '&unsub=1" target="_blank">', '</a>'));
					
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				$subj = parse_tags($subj, $tags);
				if (empty($subj))
				{
					$subj = PRODUCT_NAME;
				}
				
				try
				{
					send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_EVENT, $mailing_id, $code);
					++$count;
					echo 'Email about ' . $event_name . ' at ' . date('l, F d, Y', $event_start_time) . ' has been sent to ' . $user_name . EOL;
				}
				catch (Exception $e)
				{
					Exc::log($e, true);
					echo 'Failed to send email about ' . $event_name . ' at ' . date('l, F d, Y', $event_start_time) . ' to ' . $user_name . EOL;
				}
				
				--$emails_remaining;
			}
		}
		$mailing_status[$mailing_id] = $count;
	}

	foreach ($mailing_status as $mailing_id => $count)
	{
		if ($count == 0)
		{
			Db::exec(get_label('email'), 'UPDATE event_mailings SET status = ' . MAILING_COMPLETE . ' WHERE id = ?', $mailing_id);
			if (Db::affected_rows() > 0)
			{
				list ($club_id) = Db::record(get_label('club'), 'SELECT e.club_id FROM event_mailings m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $mailing_id);
				db_log(LOG_OBJECT_EVENT_MAILINGS, 'sending complete', NULL, $mailing_id, $club_id);
			}
		}
		else
		{
			Db::exec(get_label('email'), 'UPDATE event_mailings SET status = ' . MAILING_SENDING . ', send_count = send_count + ? WHERE id = ?', $count, $mailing_id);
		}
	}
	
	if ($emails_remaining > 0)
	{
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, p.photo_id, u.def_lang'.
			' FROM user_photos p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE p.email_sent = FALSE AND p.tag = TRUE'.
			' ORDER BY u.id'.
			' LIMIT ' . $emails_remaining);
		$photos = array();
		while ($row = $query->next())
		{
			$user_id = $row[0];
			if (!isset($photos[$user_id]))
			{
				$photos[$user_id] = array();
			}
			$photos[$user_id][] = $row;
			--$emails_remaining;
		}
		
		$image_base = $server_name . '/' . PHOTOS_DIR . TNAILS_DIR;
		
		foreach ($photos as $user_id => $user_photos)
		{
			$user_photo = $user_photos[0];
			
			$user_name = $user_photo[1];
			$email = $user_photo[2];
			$lang = get_lang_code($user_photo[4]);
			$count = count($user_photos);
			$code = generate_email_code();
			
			$request_base = $server_name . '/email_request.php?user_id=' . $user_id . '&code=' . $code;
			
			$counter = 1;
			$photos = '';
			foreach ($user_photos as $user_photo)
			{
				$photo_id = $user_photo[3];
				$photos .=
					'<a href="' . $request_base . 
					'&pid=' . $photo_id . 
					'"><img src="' . $image_base . $photo_id . 
					'.jpg" border="0" alt="' . $counter .
					'" width="' . EVENT_PHOTO_WIDTH . 
					'"></a>';
				++$counter;
			}
			
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'code' => new Tag($code),
				'user_name' => new Tag($user_name),
				'pcount' => new Tag($count),
				'photos' => new Tag($photos),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . get_server_url() . '/email_request.php?user_id=' . $user_id . '&code=' . $code . '&unsub=1" target="_blank">', '</a>'));
				
			list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email/photo.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			try
			{
				send_notification($email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_PHOTO, 0, $code);
				echo 'Email about photo tagging has been sent to ' . $user_name . EOL;
			}
			catch (Exception $e)
			{
				Exc::log($e, true);
				echo 'Failed to send email about photo tagging to ' . $user_name . EOL;
			}
		}
		Db::exec(get_label('photo'), 'UPDATE user_photos SET email_sent = TRUE');
	}

	Db::commit();
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e, true);
	echo $e->getMessage() . EOL;
}

echo 'done' . EOL;

?>