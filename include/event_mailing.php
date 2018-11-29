<?php

require_once __DIR__ . '/session.php';

function create_event_mailing($events, $body, $subj, $send_time, $lang, $flags)
{
	global $_profile;
	Db::begin();
	foreach ($events as $id)
	{
		list ($start_time, $club_id) = Db::record(get_label('event'), 'SELECT start_time, club_id FROM events WHERE id = ?', $id);
		if ($_profile == NULL || !$_profile->is_club_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		if ($send_time <= 0)
		{
			$time = time();
		}
		else
		{
			$time = $start_time - $send_time * 86400;
		}
	
		Db::exec(
			get_label('email'), 
			'INSERT INTO event_emails (event_id, subject, body, send_time, send_count, status, flags, lang) ' .
				'VALUES (?, ?, ?, ?, 0, ' . MAILING_WAITING . ', ?, ?)',
			$id, $subj, $body, $time, $flags, $lang);
		list ($email_id) = Db::record(get_label('email'), 'SELECT LAST_INSERT_ID()');
		list ($club_id, $event_name, $event_start, $timezone) =
			Db::record(get_label('event'),
				'SELECT e.club_id, e.name, e.start_time, ct.timezone FROM events e' .
					' JOIN addresses a ON a.id = e.address_id' .
					' JOIN cities ct ON ct.id = a.city_id' .
					' WHERE e.id = ?',
				$id);
				
		$log_details = new stdClass();
		$log_details->event_id = $id;
		$log_details->subj = $subj;
		$log_details->send_time = format_date('d/m/y H:i', $time, $timezone);
		$log_details->flags = $flags;
		$log_details->lang = $lang;
		$log_details->body = $body;
		db_log(LOG_OBJECT_EVENT_EMAILS, 'created', $log_details, $email_id, $club_id);
	}
	Db::commit();
}

function update_event_mailing($id, $body, $subj, $send_time, $lang, $flags)
{
	Db::begin();
	Db::exec(
		get_label('email'), 
		'UPDATE event_emails SET subject = ?, body = ?, send_time = ?, lang = ?, flags = ? WHERE id = ?',
		$subj, $body, $send_time, $lang, $flags, $id);
	if (Db::affected_rows() > 0)
	{
		list($club_id, $timezone) = Db::record(get_label('email'), 'SELECT e.club_id, c.timezone FROM event_emails m JOIN events e ON e.id = m.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE m.id = ?', $id);
		$log_details = new stdClass();
		$log_details->send_time = format_date('d/m/y H:i', $send_time, $timezone);
		$log_details->lang = $lang;
		$log_details->flags = $flags;
		$log_details->subj = $subj;
		$log_details->body = $body;
		db_log(LOG_OBJECT_EVENT_EMAILS, 'changed', $log_details, $id, $club_id);
	}
	Db::commit();
}

function get_email_recipients($flags, $lang)
{
	$to_flags = ($flags & MAILING_FLAG_TO_ALL);
	if ($to_flags == 0)
	{
		return get_label('nobody');
	}
	
	if ($to_flags == MAILING_FLAG_TO_ALL)
	{
		if ($flags & MAILING_FLAG_LANG_TO_SET_ONLY)
		{
			if ($flags & MAILING_FLAG_LANG_TO_DEF_ONLY)
			{
				return get_label('players who know and prefer [0]', get_lang_str($lang));
			}
			return get_label('players who know [0]', get_lang_str($lang));
		}
		else if ($flags & MAILING_FLAG_LANG_TO_DEF_ONLY)
		{
			return get_label('players who prefer [0]', get_lang_str($lang));
		}
		return get_label('everybody', get_lang_str($lang));
	}
	
	if ($to_flags & MAILING_FLAG_TO_ATTENDED)
	{
		if ($to_flags & MAILING_FLAG_TO_DECLINED)
		{
			$row = get_label('players who attended/declined the event');
		}
		else if ($to_flags & MAILING_FLAG_TO_DESIDING)
		{
			$row = get_label('players who did not decline the event');
		}
		else
		{
			$row = get_label('players who attended the event');
		}
	}
	else if ($to_flags & MAILING_FLAG_TO_DECLINED)
	{
		if ($to_flags & MAILING_FLAG_TO_DESIDING)
		{
			$row = get_label('players who did not attend the event');
		}
		else
		{
			$row = get_label('players who declined the event');
		}
	}
	else
	{
		$row = get_label('players who did not attend/decline the event');
	}

	if ($flags & MAILING_FLAG_LANG_TO_SET_ONLY)
	{
		if ($flags & MAILING_FLAG_LANG_TO_DEF_ONLY)
		{
			return $row . ' ' . get_label('and know and prefer [0]', get_lang_str($lang));
		}
		return $row . ' ' . get_label('and know [0]', get_lang_str($lang));
	}
	else if ($flags & MAILING_FLAG_LANG_TO_DEF_ONLY)
	{
		return $row . ' ' . get_label('and prefer [0]', get_lang_str($lang));
	}
	return $row;
}

?>