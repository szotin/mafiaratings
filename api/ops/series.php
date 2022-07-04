<?php

require_once '../../include/api.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';
require_once '../../include/scoring.php';
require_once '../../include/image.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang_code;
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		list($league_name, $league_rules, $league_langs) = Db::record(get_label('league'), 'SELECT name, rules, langs FROM leagues WHERE id = ?', $league_id);
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('Series name')));
		}
		
		$notes = get_optional_param('notes', '');
		$flags = (int)get_optional_param('flags', 0) & SERIES_EDITABLE_MASK;
		$langs = get_optional_param('langs', $league_langs);
		$timezone = get_timezone();
		
		Db::begin();
		
		$start_datetime = get_datetime(get_required_param('start'), $timezone);
		$end_datetime = get_datetime(get_required_param('end'), $timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		if ($end <= $start)
		{
			throw new Exc(get_label('Series end before or right after the start.'));
		}
		
		Db::exec(
			get_label('sеriеs'), 
			'INSERT INTO series (name, league_id, start_time, duration, langs, notes, flags, rules) values (?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $league_id, $start, $end - $start, $langs, $notes, $flags, $league_rules);
		list ($series_id) = Db::record(get_label('sеriеs'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->league_id = $league_id;
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->rules_code = $league_rules;
		$log_details->flags = $flags;
		db_log(LOG_OBJECT_SERIES, 'created', $log_details, $series_id, NULL, $league_id);
		
		Db::commit();
		$this->response['series_id'] = $series_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Create series.');
		$help->request_param('name', 'Series name.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('start', 'Series start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Series end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('notes', 'Series notes. Just a text.', 'empty.');
		$help->request_param('langs', 'Languages on this series. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'all league languages are used.');
		$help->request_param('flags', 'Series flags. Currently not used');
/*		
									'A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'<li value="128">Tournament rounds must use this tournament game rules.</li>' .
									'<li value="256">Tournament rounds must use this tournament scoring system.</li>' .
									'</ol>', '384 (=128+256) is used, which is a short term tournament enforcing rules and scoring system.');*/
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		$series_id = (int)get_required_param('series_id');
		$timezone = get_timezone();
		Db::begin();
		
		list ($league_id, $old_name, $old_start, $old_duration, $old_langs, $old_notes, $old_flags) = 
			Db::record(get_label('sеriеs'), 'SELECT league_id, name, start_time, duration, langs, notes, flags FROM series WHERE id = ?', $series_id);
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		list($league_name, $league_rules, $league_langs) = Db::record(get_label('league'), 'SELECT name, rules, langs FROM leagues WHERE id = ?', $league_id);
		
		$name = get_optional_param('name', $old_name);
		$notes = get_optional_param('notes', $old_notes);
		$langs = get_optional_param('langs', $old_langs);
		$flags = (int)get_optional_param('flags', $old_flags);
		$flags = ($flags & SERIES_EDITABLE_MASK) + ($old_flags & ~SERIES_EDITABLE_MASK);
		
		$old_start_datetime = get_datetime($old_start, $timezone);
		$old_end_datetime = get_datetime($old_start + $old_duration, $timezone);
		$start_datetime = get_datetime(get_optional_param('start', datetime_to_string($old_start_datetime)), $timezone);
		$end_datetime = get_datetime(get_optional_param('end', datetime_to_string($old_end_datetime)), $timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		$duration = $end - $start;
		if ($duration <= 0)
		{
			throw new Exc(get_label('Series end before or right after the start.'));
		}
		
		$logo_uploaded = false;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . SERIES_PICS_DIR, $series_id);
			
			$icon_version = (($flags & SERIES_ICON_MASK) >> SERIES_ICON_MASK_OFFSET) + 1;
			if ($icon_version > SERIES_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~SERIES_ICON_MASK) + ($icon_version << SERIES_ICON_MASK_OFFSET);
			$logo_uploaded = true;
		}
		
		Db::exec(
			get_label('sеriеs'), 
			'UPDATE series SET name = ?, start_time = ?, duration = ?, langs = ?, notes = ?, flags = ? WHERE id = ?',
			$name, $start, $duration, $langs, $notes, $flags, $series_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($start != $old_start)
			{
				$log_details->start = $start;
			}
			if ($duration != $old_duration)
			{
				$log_details->duration = $duration;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			if ($notes != $old_notes)
			{
				$log_details->notes = $notes;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			db_log(LOG_OBJECT_SERIES, 'changed', $log_details, $series_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change series.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('name', 'Series name.', 'remains the same.');
		$help->request_param('start', 'Series start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('end', 'Series end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('notes', 'Series notes. Just a text.', 'remains the same.');
		$help->request_param('langs', 'Languages on this series. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same.');
		$help->request_param('flags', 'Series flags. Not used yet');
/*									'A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'</ol>', 'remain the same.');*/
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// cancel
	//-------------------------------------------------------------------------------------------------------
	function cancel_op()
	{
		$series_id = (int)get_required_param('series_id');
		
		Db::begin();
		list($league_id) = Db::record(get_label('sеriеs'), 'SELECT league_id FROM series WHERE id = ?', $series_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::exec(get_label('sеriеs'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_CANCELED . ') WHERE id = ?', $series_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_SERIES, 'canceled', NULL, $series_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function cancel_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Cancel series.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$series_id = (int)get_required_param('series_id');
		
		Db::begin();
		list($league_id) = Db::record(get_label('sеriеs'), 'SELECT league_id FROM series WHERE id = ?', $series_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::exec(get_label('sеriеs'), 'UPDATE series SET flags = (flags & ~' . SERIES_FLAG_CANCELED . ') WHERE id = ?', $series_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_SERIES, 'restored', NULL, $series_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Restore canceled series.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$series_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_profile->user_def_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO series_comments (time, user_id, comment, series_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $series_id, $lang);
		
		$timezone = get_timezone();
		list($series_id, $series_name, $series_start_time) = Db::record(get_label('sеriеs'), 'SELECT id, name, start_time FROM series WHERE id = ?', $series_id);
		
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
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_tournament.php';
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
$page->run('Series Operations', CURRENT_VERSION);

?>