<?php

setDir();

require_once 'include/branding.php';
require_once 'include/db.php';
require_once 'include/email.php';
require_once 'include/languages.php';
require_once 'include/constants.php';
require_once 'include/localization.php';
require_once 'include/error.php';
require_once 'include/snapshot.php';

if (PHP_SAPI == 'cli')
{
	define('EOL', "\n");
}
else
{
	define('EOL', " <br>\n");
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

define('NO_TEXT', 'Please enable http in your email client in order to read this message');
define('MAX_EMAILS', 25);
try
{
	$time = time();
	$server_name = get_server_url();
	$emails_remaining = MAX_EMAILS;
	$mailing_status = array();

	Db::begin();

	$query = new DbQuery(
		'SELECT ee.id, ee.subject, ee.body, ee.lang, ee.flags, e.id, e.name, e.start_time, e.notes, e.languages, a.id, a.address, a.map_url, i.timezone, c.id, c.name FROM event_emails ee' . 
		' JOIN events e ON e.id = ee.event_id' .
		' JOIN addresses a ON a.id = e.address_id' . 
		' JOIN clubs c ON c.id = e.club_id' .
		' JOIN cities i ON i.id = a.city_id' .
		' WHERE ee.status in (' . MAILING_WAITING . ', ' . MAILING_SENDING . ')' .
		' AND ee.send_time <= ' . $time .
		' ORDER BY ee.send_time');
	// echo $query->get_parsed_sql();
	// echo '</br>';
	$image_base = $server_name . '/' . ADDRESS_PICS_DIR . TNAILS_DIR;
	while (($row = $query->next()) && $emails_remaining > 0)
	{
		list(
			$email_id, $subj, $body, $email_lang, $email_flags,
			$event_id, $event_name, $event_start_time, $event_notes, $event_langs,
			$addr_id, $addr, $addr_url, $timezone, $club_id,
			$club_name) = $row;
		
		$count = 0;
		$to_flags = ($email_flags & MAILING_FLAG_TO_ALL);
		if ($to_flags != 0)
		{
			date_default_timezone_set($timezone);
			
			$tags = get_bbcode_tags();
			$tags['ename'] = new Tag($event_name);
			$tags['eid'] = new Tag($event_id);
			$tags['edate'] = new Tag(format_date('l, F d, Y', $event_start_time, $timezone, $email_lang));
			$tags['etime'] = new Tag(format_date('H:i', $event_start_time, $timezone, $email_lang));
			$tags['notes'] = new Tag($event_notes);
			$tags['addr'] = new Tag($addr);
			$tags['aurl'] = new Tag($addr_url);
			$tags['aid'] = new Tag($addr_id);
			$tags['aimage'] = new Tag($image_base . $addr_id . '.jpg');
			$tags['langs'] = new Tag(get_langs_str($event_langs, ', ', LOWERCASE, $email_lang));
			$tags['cname'] = new Tag($club_name);
			$tags['cid'] = new Tag($club_id);
			$tags['accept_btn'] = new Tag('<input type="submit" name="accept" value="#">');
			$tags['decline_btn'] = new Tag('<input type="submit" name="decline" value="#">');
			$tags['unsub_btn'] = new Tag('<input type="submit" name="unsub" value="#">');
			
			$body = parse_tags($body, $tags);
			$subj = parse_tags($subj, $tags);
		
			$condition = new SQL('(u.languages & ?) <> 0' . 
				' AND u.email <> \'\'' .
				' AND uc.user_id = u.id' .
				' AND uc.club_id = ?' .
				' AND (uc.flags & ' . (UC_PERM_PLAYER | UC_FLAG_BANNED | UC_FLAG_SUBSCRIBED) . ') = ' . (UC_PERM_PLAYER | UC_FLAG_SUBSCRIBED) .
				' AND u.id NOT IN (SELECT user_id FROM emails WHERE obj = ' . EMAIL_OBJ_EVENT . ' AND obj_id = ?)',
				$event_langs, $club_id, $email_id);
		
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
			
			if ($email_flags & MAILING_FLAG_LANG_TO_SET_ONLY)
			{
				$condition->add(' AND (u.languages & ?) <> 0', $email_lang);
			}
			
			if ($email_flags & MAILING_FLAG_LANG_TO_DEF_ONLY)
			{
				$condition->add(' AND u.def_lang = ?', $email_lang);
			}
		
			$query1 = new DbQuery('SELECT u.id, u.name, u.email FROM users u, user_clubs uc WHERE ', $condition);
			$query1->add(' ORDER BY u.id LIMIT ' . $emails_remaining);
			echo $query1->get_parsed_sql();
			echo '</br>';
			
			while ($row1 = $query1->next())
			{
				list($user_id, $user_name, $user_email) = $row1;
				
				$code = generate_email_code();
				$base_url = get_server_url() . '/email_request.php?uid=' . $user_id . '&code=' . $code;
				
				$tags = array(
					'uname' => new Tag($user_name),
					'uid' => new Tag($user_id),
					'email' => new Tag($user_email),
					'code' => new Tag($code),
					'accept' => new Tag('<a href="' . $base_url . '&accept=1" target="_blank">', '</a>'),
					'decline' => new Tag('<a href="' . $base_url . '&decline=1" target="_blank">', '</a>'),
					'unsub' => new Tag('<a href="' . $base_url . '&unsub=1" target="_blank">', '</a>'));
					
				$body1 = parse_tags($body, $tags);
				$subj1 = parse_tags($subj, $tags);
				if (empty($subj1))
				{
					$subj1 = PRODUCT_NAME;
				}
				
				try
				{
					send_notification($user_email, $body1, NO_TEXT, $subj1, $user_id, EMAIL_OBJ_EVENT, $email_id, $code);
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
		$mailing_status[$email_id] = $count;
	}

	foreach ($mailing_status as $email_id => $count)
	{
		if ($count == 0)
		{
			Db::exec(get_label('email'), 'UPDATE event_emails SET status = ' . MAILING_COMPLETE . ' WHERE id = ?', $email_id);
			if (Db::affected_rows() > 0)
			{
				list ($club_id) = Db::record(get_label('club'), 'SELECT e.club_id FROM event_emails m JOIN events e ON e.id = m.event_id WHERE m.id = ?', $email_id);
				db_log('event_emails', 'Sending complete', NULL, $email_id, $club_id);
			}
		}
		else
		{
			Db::exec(get_label('email'), 'UPDATE event_emails SET status = ' . MAILING_SENDING . ', send_count = send_count + ? WHERE id = ?', $count, $email_id);
		}
	}
	
	if ($emails_remaining > 0)
	{
		$query = new DbQuery(
			'SELECT e.id, e.name, e.start_time, e.duration, e.notes, e.languages, a.id, a.address, a.map_url, i.timezone, c.id, c.name FROM events e' . 
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities i ON a.city_id = i.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' WHERE (e.flags & ' . EVENT_FLAG_DONE . ') = 0 AND e.start_time + e.duration + ' . EVENT_ALIVE_TIME . ' < UNIX_TIMESTAMP() AND e.start_time + e.duration + ' . EVENT_NOT_DONE_TIME . ' >= UNIX_TIMESTAMP()');
			
		while (($row = $query->next()) && $emails_remaining > 0)
		{
			list($e_id, $e_name, $e_start_time, $e_duration, $e_notes, $e_languages, $a_id, $a_address, $a_map_url, $i_timezone, $c_id, $c_name) = $row;
			$tags = array(
				'ename' => new Tag($e_name),
				'eid' => new Tag($e_id),
				'addr' => new Tag($a_address),
				'aurl' => new Tag($a_map_url),
				'cname' => new Tag($c_name),
				'cid' => new Tag($c_id),
				'join_chk' => new Tag('<input type="checkbox" name="join" checked>'),
				'yes_btn' => new Tag('<input type="submit" name="yes" value="#">'),
				'no_btn' => new Tag('<input type="submit" name="no" value="#">'));
				
			
			$to_confirm = array();
			$query1 = new DbQuery(
				'SELECT u.id, u.name, u.def_lang, u.email, r.nick_name FROM incomer_suspects s' .
				' JOIN users u ON s.user_id = u.id' .
				' JOIN registrations r ON s.reg_id = r.id' .
				' WHERE r.event_id = ?',
				$e_id);
			while ($row1 = $query1->next())
			{
				$to_confirm[$row1[0]] = $row1;
			}
			$query1 = new DbQuery(
				'SELECT u.id, u.name, u.def_lang, u.email, r.nick_name FROM registrations r' .
				' JOIN users u ON r.user_id = u.id' .
				' WHERE r.event_id = ? AND u.id NOT IN (SELECT user_id FROM user_clubs WHERE club_id = ?)',
				$e_id, $c_id);
			while ($row1 = $query1->next())
			{
				$to_confirm[$row1[0]] = $row1;
			}
			
			foreach ($to_confirm as $row1)
			{
				list ($u_id, $u_name, $u_lang, $u_email, $u_nick) = $row1;
				$lang = get_lang_code($u_lang);
				$code = generate_email_code();
				// echo '<a href="email_request.php?uid=' . $u_id . '&code=' . $code .'&yes=" target="_balnk">' . $u_name . '</a><br><br>';
				$tags['code'] = new Tag($code);
				$tags['uid'] = new Tag($u_id);
				$tags['uname'] = new Tag($u_name);
				$tags['nick'] = new Tag($u_nick);
				$tags['edate'] = new Tag(format_date('l, F d, Y', $e_start_time, $i_timezone, $u_lang));
				$tags['etime'] = new Tag(format_date('H:i', $e_start_time, $i_timezone, $u_lang));
				list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_confirm_event.php';
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_notification($u_email, $body, $text_body, $subj, $u_id, EMAIL_OBJ_CONFIRM_EVENT, $e_id, $code);
				--$emails_remaining;
			}
			
			$unknown_players = '';
			$delim = '';
			$query1 = new DbQuery(
				'SELECT i.name FROM incomers i WHERE i.event_id = ? AND (i.flags & ' . INCOMER_FLAGS_EXISTING . ') <> 0 AND NOT EXISTS (SELECT s.user_id FROM incomer_suspects s WHERE s.incomer_id = i.id)', $e_id);
			while ($row1 = $query1->next())
			{
				$unknown_players .= $delim . $row1[0];
				$delim = ', ';
			}
			
			if ($unknown_players != '')
			{
				$query1 = new DbQuery('SELECT u.id, u.name, u.def_lang, u.email FROM user_clubs c JOIN users u ON c.user_id = u.id WHERE (c.flags & ' . UC_PERM_MANAGER . ') <> 0 AND c.club_id = ?', $c_id);
				while ($row1 = $query1->next())
				{
					list ($u_id, $u_name, $u_lang, $u_email) = $row1;
					$lang = get_lang_code($u_lang);
					$code = generate_email_code();
					$tags['incomers'] = new Tag($unknown_players);
					$tags['code'] = new Tag($code);
					$tags['uid'] = new Tag($u_id);
					$tags['uname'] = new Tag($u_name);
					$tags['url'] = new Tag(get_server_url() . '/email_request.php?uid=' . $u_id . '&code=' . $code);
					$tags['edate'] = new Tag(format_date('l, F d, Y', $e_start_time, $i_timezone, $u_lang));
					$tags['etime'] = new Tag(format_date('H:i', $e_start_time, $i_timezone, $u_lang));
						
					list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_event_no_user.php';
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_notification($u_email, $body, $text_body, $subj, $u_id, EMAIL_OBJ_EVENT_NO_USER, $e_id, $code);
					
					--$emails_remaining;
				}
			}
			
			Db::exec(get_label('event'), 'UPDATE events SET flags = (flags | ' . EVENT_FLAG_DONE . ') WHERE id = ?', $e_id);
		}
	}

	if ($emails_remaining > 0)
	{
		$query = new DbQuery('SELECT u.id, u.name, u.email, p.photo_id, u.def_lang FROM users u, user_photos p WHERE u.id = p.user_id AND p.email_sent = FALSE AND p.tag = TRUE ORDER BY u.id LIMIT ' . $emails_remaining);
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
			
			$request_base = $server_name . '/email_request.php?uid=' . $user_id . '&code=' . $code;
			
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
				'uid' => new Tag($user_id),
				'code' => new Tag($code),
				'uname' => new Tag($user_name),
				'pcount' => new Tag($count),
				'photos' => new Tag($photos),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . get_server_url() . '/email_request.php?uid=' . $user_id . '&code=' . $code . '&unsub=1" target="_blank">', '</a>'));
				
			list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_photo.php';
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
	
	if ($emails_remaining == MAX_EMAILS)
	{
		// If there is no emails to send, check if it's time to make a new snapshot
		$query = new DbQuery('SELECT time, snapshot FROM snapshots ORDER BY time DESC LIMIT 1');
		if ($row = $query->next())
		{
			list($time, $json) = $row;
			$now = time();
			if (Snapshot::snapshot_time($time) < Snapshot::snapshot_time($now))
			{
				echo 'Making snapshot<br>';
				$snapshot = new Snapshot($now);
				Db::begin();
				$snapshot->shot();
				$snapshot->save();
				Db::commit();
			}
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e, true);
	echo $e->getMessage() . EOL;
}

echo 'done' . EOL;

?>