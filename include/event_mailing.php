<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/languages.php';

function get_event_email_lang($lang, $langs)
{
	if (($lang & LANG_ALL) == 0)
	{
		$lang = get_next_lang(LANG_NO, $langs);
		if (($lang & LANG_ALL) == 0)
		{
			$lang = LANG_DEFAULT;
		}
	}
	return $lang;
}

function get_event_email($type, $lang)
{
	$filename = '/email_event_invite.php';
	switch ($type)
	{
		case EVENT_EMAIL_INVITE:
			break;
		case EVENT_EMAIL_CANCEL:
			$filename = '/email_event_cancel.php';
			break;
		case EVENT_EMAIL_CHANGE_ADDRESS:
			$filename = '/email_event_address.php';
			break;
		case EVENT_EMAIL_CHANGE_TIME:
			$filename = '/email_event_time.php';
			break;
		case EVENT_EMAIL_RESTORE:
			$filename = '/email_event_restore.php';
			break;
	}
	return include 'include/languages/' . get_lang_code($lang) . $filename;
}

function get_email_recipients($flags, $langs)
{
	$to_flags = ($flags & MAILING_FLAG_TO_ALL);
	if ($to_flags == 0)
	{
		return get_label('nobody');
	}
	
	if ($to_flags == MAILING_FLAG_TO_ALL)
	{
		if (($langs & LANG_ALL) == LANG_ALL)
		{
			return get_label('everybody', get_lang_str($lang));
		}
		return get_label('players who know [0]', get_langs_str($langs, get_label(', or ')));
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
		$row = get_label('players who have not decided');
	}

	if (($langs & LANG_ALL) == LANG_ALL)
	{
		return $row;
	}
	return $row . ' ' . get_label('and know [0]', get_langs_str($langs, get_label(', or ')));
}

?>