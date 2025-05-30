
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
		global $_profile;
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		list($league_name, $league_rules, $league_langs, $league_flags, $gaining_id) = Db::record(get_label('league'), 'SELECT name, rules, langs, flags, gaining_id FROM leagues WHERE id = ?', $league_id);
		
		$gaining_id = (int)get_optional_param('gaining_id'); //, $gaining_id);
		list($gaining_version) = Db::record(get_label('gaining system'), 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
		$gaining_version = (int)get_optional_param('gaining_version', $gaining_version);
		
		$parent_series = json_decode(get_optional_param('parent_series', '[]'));
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('Series name')));
		}
		
		$fee = (int)get_optional_param('fee', -1);
		if ($fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = (int)get_optional_param('currency_id', -1);
		if ($currency_id <= 0)
		{
			$currency_id = NULL;
		}
		
		$notes = get_optional_param('notes', '');
		$flags = (int)get_optional_param('flags', NEW_SERIES_FLAGS);
		$flags = ($flags & SERIES_EDITABLE_MASK) + (NEW_SERIES_FLAGS & ~SERIES_EDITABLE_MASK);
		if (($league_flags & LEAGUE_FLAG_ELITE) == 0)
		{
			$flags &= ~SERIES_FLAG_ELITE;
		}
		
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
			'INSERT INTO series (name, league_id, start_time, duration, langs, notes, fee, currency_id, flags, rules, gaining_id, gaining_version) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $league_id, $start, $end - $start, $langs, $notes, $fee, $currency_id, $flags, $league_rules, $gaining_id, $gaining_version);
		list ($series_id) = Db::record(get_label('sеriеs'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->league_id = $league_id;
		$log_details->start = $start;
		$log_details->duration = $end - $start;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->fee = $fee;
		$log_details->currency_id = $currency_id;
		$log_details->rules_code = $league_rules;
		$log_details->flags = $flags;
		$log_details->gaining_id = $gaining_id;
		$log_details->gaining_version = $gaining_version;
		$log_details->parent_series = json_encode($parent_series);
		db_log(LOG_OBJECT_SERIES, 'created', $log_details, $series_id, NULL, $league_id);
		
		// create parent series records
		foreach ($parent_series as $s)
		{
			Db::exec(
				get_label('sеriеs'), 
				'INSERT INTO series_series (child_id, parent_id, stars) values (?, ?, ?)',
				$series_id, $s->id, $s->stars);
			// todo send notification for series too:
			// send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
		}
		
		Db::commit();
		$this->response['series_id'] = $series_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Create series.');
		$help->request_param('name', 'Series name.');
		$help->request_param('league_id', 'League id.');
		$series_help = $help->request_param('parent_series', 'Json array of series that this series belongs to. For example "[{id:2,stars:3},{id:4,stars:1}]".', 'series does not belong to any series - same as "[]".');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
		$help->request_param('start', 'Series start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Series end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('notes', 'Series notes. Just a text.', 'empty.');
		$help->request_param('fee', 'Admission rate per player-tournament. Send -1 if unknown.', '-1.');
		$help->request_param('currency_id', 'Currency id for the admission rate. Send -1 if unknown.', '-1.');
		$help->request_param('langs', 'Languages on this series. A bit combination of language ids.', 'all league languages are used.');
		$help->request_param('flags', 'Series flags. Currently not used');
		$help->request_param('gaining_id', 'Gaining system id.', 'Default gaining of the league is used.');
		$help->request_param('gaining_version', 'Gaining system version.', 'Latest gaining system version is used.');
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
		
		list ($league_id, $old_name, $old_start, $old_duration, $old_langs, $old_notes, $old_fee, $old_currency_id, $old_flags, $old_gaining_id, $old_gaining_version) = 
			Db::record(get_label('sеriеs'), 'SELECT league_id, name, start_time, duration, langs, notes, fee, currency_id, flags, gaining_id, gaining_version FROM series WHERE id = ?', $series_id);
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		list($league_name, $league_rules, $league_langs, $league_flags) = Db::record(get_label('league'), 'SELECT name, rules, langs, flags FROM leagues WHERE id = ?', $league_id);
		
		$gaining_id = (int)get_optional_param('gaining_id', $old_gaining_id);
		if ($gaining_id != $old_gaining_id)
		{
			list($old_gaining_version) = Db::record(get_label('gaining system'), 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
		}
		$gaining_version = (int)get_optional_param('gaining_version', $old_gaining_version);
		
		$name = get_optional_param('name', $old_name);
		$notes = get_optional_param('notes', $old_notes);
		$langs = get_optional_param('langs', $old_langs);
		$flags = (int)get_optional_param('flags', $old_flags);
		$flags = ($flags & SERIES_EDITABLE_MASK) + ($old_flags & ~SERIES_EDITABLE_MASK);
		if (($league_flags & LEAGUE_FLAG_ELITE) == 0)
		{
			$flags &= ~LEAGUE_FLAG_ELITE;
		}
		
		$fee = get_optional_param('fee', $old_fee);
		if (!is_null($fee) && $fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = get_optional_param('currency_id', $old_currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		
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
		
		// update parent series records
		$parent_series = json_decode(get_optional_param('parent_series', NULL));
		$parent_series_changed = false;
		if (!is_null($parent_series))
		{
			$old_parent_series = array();
			$query = new DbQuery('SELECT parent_id, stars FROM series_series WHERE child_id = ?', $series_id);
			while ($row = $query->next())
			{
				$s = new stdClass();
				list($s->id, $s->stars) = $row;
				$old_parent_series[$s->id] = $s;
			}
			
			foreach ($parent_series as $s)
			{
				if (isset($old_parent_series[$s->id]))
				{
					$os = $old_parent_series[$s->id];
					$finals = isset($s->finals) ? $s->finals : false;
					if ($os->stars != $s->stars)
					{
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series_series SET stars = ? WHERE parent_id = ? AND child_id = ?', $s->stars, $s->id, $series_id);
						// todo send notification for series too:
						// send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$parent_series_changed = true;
					}
					unset($old_parent_series[$s->id]);
				}
				else
				{
					Db::exec(
						get_label('sеriеs'), 
						'INSERT INTO series_series (child_id, parent_id, stars) values (?, ?, ?)',
						$series_id, $s->id, $s->stars);
					// todo send notification for series too:
					// send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
					$parent_series_changed = true;
				}
			}
			
			foreach ($old_parent_series as $parent_id => $s)
			{
				Db::exec(
					get_label('sеriеs'), 
					'DELETE FROM series_series WHERE child_id = ? AND parent_id = ?', $series_id, $parent_id);
				// todo send notification for series too:
				// send_series_notification('tournament_series_remove', $tournament_id, $name, $club_id, $club->name, $s);
			}
		}
		
		// Update child tournaments when elite flag changed
		if (($flags & SERIES_FLAG_ELITE) != ($old_flags & SERIES_FLAG_ELITE))
		{
			$query = new DbQuery(
				'SELECT g.id, g.end_time FROM games g'.
				' JOIN series_tournaments t ON t.tournament_id = g.tournament_id'.
				' WHERE t.series_id = ? AND t.stars > 1 AND g.is_canceled = 0 AND g.is_rating <> 0'.
				' ORDER BY g.end_time, g.id'.
				' LIMIT 1', $series_id);
			if ($row = $query->next())
			{
				list ($game_id, $game_end_time) = $row;
				$prev_game_id = NULL;
				$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $game_end_time, $game_end_time, $game_id);
				if ($row = $query->next())
				{
					list($prev_game_id) = $row;
				}
				Game::rebuild_ratings($prev_game_id, $game_end_time);
			}
			
			if ($flags & SERIES_FLAG_ELITE)
			{
				Db::exec(get_label('tournaments'), 'UPDATE series_tournaments st JOIN tournaments t ON t.id = st.tournament_id SET t.flags = t.flags | ' . TOURNAMENT_FLAG_ELITE . ' WHERE st.series_id = ? AND st.stars > 1', $series_id);
			}
			else
			{
				Db::exec(get_label('tournaments'), 
					'UPDATE series_tournaments st'.
					' JOIN tournaments t ON t.id = st.tournament_id'.
					' SET t.flags = t.flags & ~' . TOURNAMENT_FLAG_ELITE. 
					' WHERE st.series_id = ? AND t.id NOT IN ('.
						'SELECT st1.tournament_id'.
						' FROM series_tournaments st1'.
						' JOIN series s1 ON s1.id = st1.series_id'.
						' WHERE s1.id <> st.series_id AND (s1.flags & ' . SERIES_FLAG_ELITE . ') <> 0 AND st1.stars > 1'.
					')', $series_id);
			}
		}
		
		if ($gaining_id != $old_gaining_id || $gaining_version != $old_gaining_version)
		{
			$flags |= SERIES_FLAG_DIRTY;
		}
		
		if ($start + $duration > time())
		{
			$flags &= ~SERIES_FLAG_FINISHED;
		}
			
		Db::exec(
			get_label('sеriеs'), 
			'UPDATE series SET name = ?, start_time = ?, duration = ?, langs = ?, notes = ?, fee = ?, currency_id = ?, flags = ?, gaining_id = ?, gaining_version = ? WHERE id = ?',
			$name, $start, $duration, $langs, $notes, $fee, $currency_id, $flags, $gaining_id, $gaining_version, $series_id);
		if (Db::affected_rows() > 0 || $parent_series_changed)
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
			if ($fee != $old_fee)
			{
				$log_details->fee = $fee;
			}
			if ($currency_id != $old_currency_id)
			{
				$log_details->currency_id = $currency_id;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($gaining_id != $old_gaining_id)
			{
				$log_details->gaining_id = $gaining_id;
				$log_details->gaining_version = $gaining_version;
			}
			else if ($gaining_version != $old_gaining_version)
			{
				$log_details->gaining_version = $gaining_version;
			}
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			if ($parent_series_changed)
			{
				$log_details->parent_series = json_encode($parent_series);
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
		$help->request_param('fee', 'Admission rate per player-tournament. Send -1 if unknown.', 'remains the same.');
		$help->request_param('currency_id', 'Currency for admission rate. Send -1 if unknown.', 'remains the same.');
		$help->request_param('langs', 'Languages on this series. A bit combination of language ids.' . valid_langs_help(), 'remains the same.');
		$help->request_param('flags', 'Series flags. Not used yet');
		$help->request_param('gaining_id', 'Gaining system id.', 'remains the same.');
		$help->request_param('gaining_version', 'Gaining system version.', 'remains the same, or latest version of the gaining system is used if gaining system was changed.');
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
	// rebuild_places
	//-------------------------------------------------------------------------------------------------------
	function rebuild_places_op()
	{
		$series_id = (int)get_optional_param('series_id', 0);
		
		Db::begin();
		
		if ($series_id > 0)
		{
			list ($league_id) = Db::record(get_label('sеriеs'), 'SELECT league_id FROM series WHERE id = ?', $series_id);
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $series_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY);
		}
		db_log(LOG_OBJECT_SERIES, 'rebuild_places', NULL, $series_id);
		Db::commit();
	}
	
	function rebuild_places_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Schedules series places for rebuild. It is needed when in user series view the place taken is wrong.');
		$help->request_param('series_id', 'Series id to rebuild places.', 'places are rebuilt for all series.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// finish
	//-------------------------------------------------------------------------------------------------------
	function finish_op()
	{
		$series_id = (int)get_required_param('series_id');
		$now = time();
		
		Db::begin();
		list($league_id, $start_time, $duration, $flags) = Db::record(get_label('series'), 'SELECT league_id, start_time, duration, flags FROM series WHERE id = ?', $series_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $league_id, $series_id);
		if (($flags & SERIES_FLAG_FINISHED) == 0)
		{
			if ($now < $start_time)
			{
				$start_time = $now;
			}
			if ($start_time + $duration > $now)
			{
				$duration = $now - $start_time;
			}
			Db::exec(get_label('series'), 'UPDATE series SET start_time = ?, duration = ? WHERE id = ?', $start_time, $duration, $series_id);
			db_log(LOG_OBJECT_SERIES, 'finished', NULL, $series_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function finish_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Finish the series. After finishing the series within one hour players will get all parent series points for this series. Finish series functionality lets not to wait until the time expires and get the results quicker.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	// function comment_op()
	// {
		// global $_profile, $_lang;
		
		// check_permissions(PERMISSION_USER);
		// $series_id = (int)get_required_param('id');
		// $comment = prepare_message(get_required_param('comment'));
		// $lang = detect_lang($comment);
		// if ($lang == LANG_NO)
		// {
			// $lang = $_lang;
		// }
		
		// Db::exec(get_label('comment'), 'INSERT INTO series_comments (time, user_id, comment, series_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $series_id, $lang);
		
		// $timezone = get_timezone();
		// list($series_id, $series_name, $series_start_time, $series_duration) = Db::record(get_label('sеriеs'), 'SELECT id, name, start_time, duration FROM series WHERE id = ?', $series_id);
		
		// $query = new DbQuery(
			// '(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			// ' FROM users u' .
			// ' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			// ' JOIN tournament_invitations ti ON u.id = ti.user_id' .
			// ' WHERE ti.status <> ' . TOURNAMENT_INVITATION_STATUS_DECLINED . ')' .
			// ' UNION DISTINCT ' .
			// ' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			// ' FROM users u' .
			// ' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			// ' JOIN tournament_comments c ON c.user_id = u.id' .
			// ' WHERE c.tournament_id = ?)', $tournament_id, $tournament_id);
		// //echo $query->get_parsed_sql();
		// while ($row = $query->next())
		// {
			// list($user_id, $user_email, $user_flags, $user_lang) = $row;
			// if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			// {
				// continue;
			// }
		
			// $code = generate_email_code();
			// $request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			// $tags = array(
				// 'root' => new Tag(get_server_url()),
				// 'user_id' => new Tag($user_id),
				// 'user_name' => new Tag($user_name),
				// 'tournament_id' => new Tag($tournament_id),
				// 'tournament_name' => new Tag($tournament_name),
				// 'series_date' => new Tag(format_date_period($series_start_time, $series_duration, $series_timezone, false, $user_lang)),
				// 'addr' => new Tag($tournament_addr),
				// 'code' => new Tag($code),
				// 'sender' => new Tag($_profile->user_name),
				// 'message' => new Tag($comment),
				// 'url' => new Tag($request_base),
				// 'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			// list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_tournament.php';
			// $body = parse_tags($body, $tags);
			// $text_body = parse_tags($text_body, $tags);
			// send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_TOURNAMENT, $tournament_id, $code);
		// }
	// }
	
	// function comment_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the tournament.');
		// $help->request_param('id', 'Tournament id.');
		// $help->request_param('comment', 'Comment text.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// add_extra_points
	//-------------------------------------------------------------------------------------------------------
	function add_extra_points_op()
	{
		global $_lang;
		
		$series_id = (int)get_required_param('series_id');
		$user_id = (int)get_required_param('user_id');
		$reason = get_required_param('reason');
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		$details = get_optional_param('details');
		$points = (float)get_required_param('points');
		$timezone = get_timezone();
		$time = get_datetime(get_required_param('time'), $timezone)->getTimestamp();
		
		Db::begin();
		
		list($league_id) = Db::record(get_label('sеriеs'), 'SELECT league_id FROM series WHERE id = ?', $series_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::exec(get_label('points'), 'INSERT INTO series_extra_points (time, series_id, user_id, reason, details, points) VALUES (?, ?, ?, ?, ?, ?)', $time, $series_id, $user_id, $reason, $details, $points);
		list ($points_id) = Db::record(get_label('points'), 'SELECT LAST_INSERT_ID()');
		
		list($user_name) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		$log_details = new stdClass();
		$log_details->time = $time;
		$log_details->user = $user_name;
		$log_details->user_is = $user_id;
		$log_details->series_id = $series_id;
		$log_details->points = $points;
		$log_details->reason = $reason;
		if (!empty($details))
		{
			$log_details->details = $details;
		}
		db_log(LOG_OBJECT_EXTRA_POINTS, 'created', $log_details, $points_id, NULL, $league_id);
		
		Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		
		Db::commit();
		
		$this->response['points_id'] = $points_id;
	}
	
	function add_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Add extra points.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('user_id', 'User id. The user who is receiving or loosing points.');
		$help->request_param('time', 'Time when point are rewarded. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract.');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'empty.');
		
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
		list($user_id, $series_id, $league_id, $old_reason, $old_details, $old_points, $old_time) = 
			Db::record(get_label('points'), 'SELECT p.user_id, p.series_id, e.league_id, p.reason, p.details, p.points, p.time FROM series_extra_points p JOIN series e ON e.id = p.series_id WHERE p.id = ?', $points_id);
			
		list($league_id) = Db::record(get_label('sеriеs'), 'SELECT league_id FROM series WHERE id = ?', $series_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		$reason = get_optional_param('reason', $old_reason);
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		$details = get_optional_param('details', $old_details);
		$points = (float)get_optional_param('points', $old_points);
		$timezone = get_timezone();
		$time = get_datetime(get_optional_param('time', $old_time), $timezone)->getTimestamp();
		
		Db::exec(get_label('points'), 'UPDATE series_extra_points SET time = ?, reason = ?, details = ?, points = ? WHERE id = ?', $time, $reason, $details, $points, $points_id);
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
			if ($time != $old_time)
			{
				$log_details->time = $time;
			}
			db_log(LOG_OBJECT_EXTRA_POINTS, 'changed', $log_details, $points_id, NULL, $league_id);
			
			Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		}
		Db::commit();
	}
	
	function change_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		$help->request_param('time', 'Time when point are rewarded. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract.', 'remains the same');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.', 'remains the same');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'remains the same');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_extra_points
	//-------------------------------------------------------------------------------------------------------
	function delete_extra_points_op()
	{
		$points_id = (int)get_required_param('points_id');
		
		Db::begin();
		list($league_id, $series_id) = Db::record(get_label('points'), 'SELECT s.league_id, s.id FROM series_extra_points p JOIN series s ON s.id = p.series_id WHERE p.id = ?', $points_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::exec(get_label('points'), 'DELETE FROM series_extra_points WHERE id = ?', $points_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EXTRA_POINTS, 'deleted', NULL, $points_id, NULL, $league_id);
			Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		}
		Db::commit();
	}
	
	function delete_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Delete extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Series Operations', CURRENT_VERSION);

?>