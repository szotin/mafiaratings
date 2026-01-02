<?php

require_once '../../include/api.php';
require_once '../../include/email.php';
require_once '../../include/address.php';
require_once '../../include/event.php';
require_once '../../include/url.php';
require_once '../../include/scoring.php';
require_once '../../include/gaining.php';
require_once '../../include/image.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $league_id = 0)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('league name')));
		}

		check_name($name, get_label('league name'));

		if ($league_id > 0)
		{
			$query = new DbQuery('SELECT name FROM leagues WHERE name = ? AND id <> ?', $name, $league_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM leagues WHERE name = ?', $name);
		}
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('League name'), $name));
		}
		
		if ($league_id >= 0)
		{
			$query = new DbQuery('SELECT name FROM league_requests WHERE name = ?', $name);
			if ($query->next())
			{
				throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('League name'), $name));
			}
		}
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$name = trim(get_required_param('name'));

		$url = get_optional_param('url');
		$phone = get_optional_param('phone');
		$langs = (int)get_optional_param('langs', $_profile->user_langs);
		if ($langs == 0)
		{
			throw new Exc(get_label('Please select at least one language.'));
		}
		$flags = (int)get_optional_param('flags', NEW_LEAGUE_FLAGS);
		$flags = ($flags & LEAGUE_EDITABLE_MASK) + (NEW_LEAGUE_FLAGS & ~LEAGUE_EDITABLE_MASK);
		
		$email = trim(get_optional_param('email', $_profile->user_email));
		if (!empty($email) && !is_email($email))
		{
			throw new Exc(get_label('[0] is not a valid email address.', $email));
		}
		
		Db::begin();
		$this->check_name($name);
		if ($_profile->is_admin())
		{
			// Admin does not have to send a confirmation request. The league is confirmed instantly.
			
			Db::exec(
				get_label('league'),
				'INSERT INTO leagues (name, langs, flags, web_site, email, phone, rules, default_rules, scoring_id, normalizer_id, gaining_id) VALUES (?, ?, ?, ?, ?, ?, \'{}\', ?, ?, ?, ?)',
				$name, $langs, $flags, $url, $email, $phone, DEFAULT_RULES, SCORING_DEFAULT_ID, NORMALIZER_DEFAULT_ID, GAINING_DEFAULT_ID);
			list ($league_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->flags = NEW_LEAGUE_FLAGS;
			$log_details->url = $url;
			$log_details->email = $email;
			$log_details->phone = $phone;
			db_log(LOG_OBJECT_LEAGUE, 'created', $log_details, $league_id, NULL, $league_id);
			$this->response['league_id'] = $league_id;
		}
		else
		{
			Db::exec(
				get_label('league'), 
				'INSERT INTO league_requests (user_id, name, langs, web_site, email, phone) VALUES (?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $langs, $url, $email, $phone);
				
			list ($request_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->url = $url;
			$log_details->email = $email;
			$log_details->phone = $phone;
			db_log(LOG_OBJECT_LEAGUE_REQUEST, 'created', $log_details, $request_id);
			
			// send request to admin
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE (u.flags & ' . USER_PERM_ADMIN . ') <> 0 and u.email <> \'\' AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0');
			while ($row = $query->next())
			{
				list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
				$lang = get_lang_code($admin_def_lang);
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/create_league.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_id' => new Tag($admin_id),
					'user_name' => new Tag($admin_name),
					'league_name' => new Tag($name),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($admin_email, $body, $text_body, $subj, admin_unsubscribe_url($admin_id), $admin_def_lang);
			}
			
			echo  
				'<p>' .
				get_label('Your request for creating the league "[0]" has been sent to the administration. [1] administrators will review your league information.', $name, PRODUCT_NAME) .
				'</p><p>' .
				get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
				'</p>';
		}
		Db::commit();
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Create league. If user is admin, league is just created. If not, league request is created and email is sent to admin. Admin has to accept it.');
		$help->request_param('name', 'League name.');
		$help->request_param('url', 'League web site URL.');
		$help->request_param('langs', 'Languages used in the league. A bit combination of language ids.' . valid_langs_help());
		$help->request_param('email', 'League email.', 'user email is used.');
		$help->request_param('phone', 'League phone. Just a text.', 'empty.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		list($old_name, $old_url, $old_email, $old_phone, $old_langs, $old_scoring_id, $old_normalizer_id, $old_gaining_id, $old_rules, $old_def_rules, $old_flags) = Db::record(get_label('league'),
			'SELECT name, web_site, email, phone, langs, scoring_id, normalizer_id, gaining_id, rules, default_rules, flags FROM leagues c WHERE id = ?', $league_id);
			
		$name = get_optional_param('name', $old_name);
		if ($name != $old_name)
		{
			$this->check_name($name, $league_id);
		}
		
		$url = check_url(get_optional_param('url', $old_url));
		$phone = get_optional_param('phone', $old_phone);
		$scoring_id = get_optional_param('scoring_id', $old_scoring_id);
		$normalizer_id = get_optional_param('normalizer_id', $old_normalizer_id);
		if ($normalizer_id <= 0)
		{
			$normalizer_id = NULL;
		}
		$gaining_id = get_optional_param('gaining_id', $old_gaining_id);
		$langs = (int)get_optional_param('langs', $old_langs);
		$flags = (int)get_optional_param('flags', $old_flags);
		if ($_profile->is_admin())
		{
			$flags = ($flags & LEAGUE_EDITABLE_MASK) + ($old_flags & ~LEAGUE_EDITABLE_MASK);
		}
		else
		{
			// return when there will be more editable flags
			// $flags = ($flags & (LEAGUE_EDITABLE_MASK & ~LEAGUE_FLAG_ELITE) + ($old_flags & ~(LEAGUE_EDITABLE_MASK & ~LEAGUE_FLAG_ELITE));
			$flags = $old_flags;
		}
		
		$rules = get_optional_param('rules', $old_rules);
		if (!is_string($rules))
		{
			foreach ($rules as $rule => $values)
			{
				if (is_string($values))
				{
					switch (strtolower($values))
					{
						case 'true':
							$rules[$rule] = true;
							break;
						case 'false':
							$rules[$rule] = false;
							break;
					}
				}
				else
				{
					for ($i = 0; $i < count($values); ++$i)
					{
						switch (strtolower($values[$i]))
						{
							case 'true':
								$values[$i] = true;
								break;
							case 'false':
								$values[$i] = false;
								break;
						}
					}
				}
			}
			
			$rules = json_encode($rules);
		}
		
		$def_rules = check_rules_code(get_optional_param('default_rules', $old_def_rules));
		$def_rules = correct_rules($def_rules, $rules);
		
		if ($langs == 0)
		{
			throw new Exc(get_label('Please select at least one language.'));
		}
		
		$email = get_optional_param('email', $old_email);
		if ($email != $old_email && !empty($email) && !is_email($email))
		{
			throw new Exc(get_label('[0] is not a valid email address.', $email));
		}
		
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . LEAGUE_PICS_DIR, $league_id);
			
			$icon_version = (($flags & LEAGUE_ICON_MASK) >> LEAGUE_ICON_MASK_OFFSET) + 1;
			if ($icon_version > LEAGUE_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~LEAGUE_ICON_MASK) + ($icon_version << LEAGUE_ICON_MASK_OFFSET);
		}
		
		Db::exec(
			get_label('league'), 
			'UPDATE leagues SET name = ?, web_site = ?, langs = ?, email = ?, phone = ?, scoring_id = ?, normalizer_id = ?, gaining_id = ?, rules = ?, default_rules = ?, flags = ? WHERE id = ?',
			$name, $url, $langs, $email, $phone, $scoring_id, $normalizer_id, $gaining_id, $rules, $def_rules, $flags, $league_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($url != $old_url)
			{
				$log_details->url = $url;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			if ($email != $old_email)
			{
				$log_details->email = $email;
			}
			if ($phone != $old_phone)
			{
				$log_details->phone = $phone;
			}
			if ($rules != $old_rules)
			{
				$log_details->rules = $rules;
			}
			if ($old_flags != $flags)
			{
				$log_details->flags = $flags;
				if (($old_flags & LEAGUE_ICON_MASK) != ($flags & LEAGUE_ICON_MASK))
				{
					$log_details->logo_uploaded = true;
				}
			}
			if ($scoring_id != $old_scoring_id)
			{
				$log_details->scoring_id = $scoring_id;
			}
			if ($normalizer_id != $old_normalizer_id)
			{
				$log_details->normalizer_id = $normalizer_id;
			}
			if ($gaining_id != $old_gaining_id)
			{
				$log_details->gaining_id = $gaining_id;
			}
			db_log(LOG_OBJECT_LEAGUE, 'changed', $log_details, $league_id, NULL, $league_id);
		}
		
		if ($rules != $old_rules)
		{
			$query = new DbQuery('SELECT club_id, rules FROM league_clubs WHERE league_id = ?', $league_id);
			while ($row = $query->next())
			{
				list ($club_id, $rules_code) = $row;
				$new_rules_code = correct_rules($rules_code, $rules);
				if ($new_rules_code !== $rules_code)
				{
					Db::exec(get_label('club'), 'UPDATE league_clubs SET rules = ? WHERE club_id = ? AND league_id = ?', $new_rules_code, $club_id, $league_id);
				}
			}
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change league record.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('name', 'League name.', 'remains the same.');
		$help->request_param('url', 'League web site URL.', 'remains the same.');
		$help->request_param('langs', 'Languages used in the league. A bit combination of language ids.' . valid_langs_help(), 'remains the same.');
		$help->request_param('email', 'League email.', 'remains the same.');
		$help->request_param('phone', 'League phone. Just a text.', 'remains the same.');
		api_rules_filter_help($help->request_param('rules', 'Game rules filter. Specifies what rules are allowed in the league. Contains json. Example: { "split_on_four": true, "extra_points": ["fiim", "maf-club"] } - linching 2 players on 4 must be allowed; extra points assignment is allowed in ФИИМ or maf-club styles, but no others.'));
		$help->request_param('default_rules', 'Rules code for the rules that are default for this league.', 'remains the same.');
		$help->request_param('logo', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");
		$help->request_param('scoring_id', 'Default tournament scoring system for the league. This scoring system is suggested by default to all new tournaments of the league.', 'remains the same.');
		$help->request_param('normalizer_id', 'Default tournament scoring normalizer for the league. This scoring normalizer is suggested by default to all new tournaments of the league. Send 0 if the league does need to have default normalizer.', 'remains the same.');
		$help->request_param('gaining_id', 'Default series scoring system for the league. This scoring system is suggested by default to all new tournament series of the league.', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// accept
	//-------------------------------------------------------------------------------------------------------
	function accept_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_ADMIN);
		$request_id = (int)get_required_param('request_id');
		$flags = (int)get_optional_param('flags', NEW_LEAGUE_FLAGS);
		$flags = ($flags & LEAGUE_EDITABLE_MASK) + (NEW_LEAGUE_FLAGS & ~LEAGUE_EDITABLE_MASK);
		
		Db::begin();
		list($url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone) = Db::record(
			get_label('league'),
			'SELECT l.web_site, l.langs, l.user_id, nu.name, u.email, u.def_lang, u.flags, l.email, l.phone FROM league_requests l' .
				' JOIN users u ON l.user_id = u.id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE l.id = ?',
			$request_id);
			
		if (isset($_REQUEST['name']))
		{
			$name = $_REQUEST['name'];
		}
		$this->check_name($name, -1);
		
		Db::exec(
			get_label('league'),
			'INSERT INTO leagues (name, langs, flags, web_site, email, phone, rules, scoring_id, normalizer_id, gaining_id) VALUES (?, ?, ?, ?, ?, ?, \'{}\', ?, ?, ?)',
			$name, $langs, $flags, $url, $email, $phone, SCORING_DEFAULT_ID, NORMALIZER_DEFAULT_ID, GAINING_DEFAULT_ID);
			
		list ($league_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->langs = $langs;
		$log_details->flags = NEW_LEAGUE_FLAGS;
		$log_details->url = $url;
		$log_details->email = $email;
		$log_details->phone = $phone;
		db_log(LOG_OBJECT_LEAGUE, 'created', $log_details, $league_id, NULL, $league_id);

		if (($user_flags & USER_PERM_ADMIN) == 0)
		{
			Db::exec(get_label('user'), 'INSERT INTO league_managers (league_id, user_id) VALUES (?, ?)', $league_id, $user_id);
			$log_details = new stdClass();
			$log_details->user = $user_name;
			$log_details->user_id = $user_id;
			db_log(LOG_OBJECT_USER, 'becomes league manager', $log_details, $league_id, NULL, $league_id);
		}
			
		Db::exec(get_label('league'), 'DELETE FROM league_requests WHERE id = ?', $request_id);
		db_log(LOG_OBJECT_LEAGUE_REQUEST, 'accepted', NULL, $request_id, NULL, $league_id);
		
		// send email
		$lang = get_lang_code($user_lang);
		$tags = array(
			'root' => new Tag(get_server_url()),
			'user_id' => new Tag($user_id),
			'user_name' => new Tag($user_name),
			'league_name' => new Tag($name),
			'league_id' => new Tag($league_id));
		list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/accept_league.php';
		$body = parse_tags($body, $tags);
		$text_body = parse_tags($text_body, $tags);
		// We are not checking if user is unsubscribed. They created request, we have to reply.
		send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
		
		Db::commit();
		
		$this->response['league_id'] = $league_id;
	}
	
	function accept_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Accept league. Admin accepts league request created by a user. The user becomes the a manager of the league. An email is sent to the user notifying that the league is accepted.');
		$help->request_param('request_id', 'Id of the user request');
		$help->request_param('name', 'Name of the league. If set, it is used as a new name for this league instead of the one used in request.', 'name from the request is used');
		$help->response_param('league_id', 'League id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// decline
	//-------------------------------------------------------------------------------------------------------
	function decline_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_ADMIN);
		$request_id = (int)get_required_param('request_id');
		$reason = prepare_message(get_optional_param('reason'));;
	
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang) = Db::record(
			get_label('league'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, nu.name, u.email, u.def_lang'.
			' FROM league_requests c'.
			' JOIN users u ON c.user_id = u.id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE c.id = ?',
			$request_id);
		
		Db::exec(get_label('league'), 'DELETE FROM league_requests WHERE id = ?', $request_id);
		db_log(LOG_OBJECT_LEAGUE_REQUEST, 'declined', NULL, $request_id);
		Db::commit();
		if ($reason != '')
		{
			
			$lang = get_lang_code($user_lang);
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/decline_league.php';
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_name' => new Tag($user_name),
				'reason' => new Tag($reason),
				'league_name' => new Tag($name));
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			// We are not checking if user is unsubscribed. They created request, we have to reply.
			send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
		}
	}
	
	function decline_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Decline league create request. An email is sent to the user notifying that the request is declined.');
		$help->request_param('request_id', 'Id of the user request');
		$help->request_param('reason', 'Text explaining why it is declined.', 'empty.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// close
	//-------------------------------------------------------------------------------------------------------
	function close_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		Db::exec(get_label('league'), 'UPDATE leagues SET flags = flags | ' . LEAGUE_FLAG_CLOSED . ' WHERE id = ?', $league_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_LEAGUE, 'closed', NULL, $league_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function close_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Close the existing league.');
		$help->request_param('league_id', 'League id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		Db::exec(get_label('league'), 'UPDATE leagues SET flags = flags & ~' . LEAGUE_FLAG_CLOSED . ' WHERE id = ?', $league_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_LEAGUE, 'restored', NULL, $league_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Reopen closed league.');
		$help->request_param('league_id', 'League id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_manager
	//-------------------------------------------------------------------------------------------------------
	function add_manager_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		$user_id = (int)get_required_param('user_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		Db::exec(get_label('league'), 'INSERT IGNORE INTO league_managers (league_id, user_id) VALUES (?, ?)', $league_id, $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'becomes league manager', NULL, $user_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function add_manager_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Grand league manager permission to a user.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_manager
	//-------------------------------------------------------------------------------------------------------
	function remove_manager_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		$user_id = (int)get_required_param('user_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		Db::exec(get_label('league'), 'DELETE FROM league_managers WHERE league_id = ? AND user_id = ?', $league_id, $user_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'removed from league managers', NULL, $user_id, NULL, $league_id);
		}
		Db::commit();
	}
	
	function remove_manager_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Revoke league manager permission from a user.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_club
	//-------------------------------------------------------------------------------------------------------
	function add_club_op()
	{
		global $_profile, $_lang;
		
		$league_id = (int)get_required_param('league_id');
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, $club_id, $league_id);
		
		Db::begin();
		$insert = true;
		$old_flags = LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED | LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED;
		$query = new DbQuery('SELECT flags FROM league_clubs WHERE league_id = ? AND club_id = ?', $league_id, $club_id);
		if ($row = $query->next())
		{
			list ($old_flags) = $row;
			$insert = false;
		}
		
		$flags = $old_flags;
		if (is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
		{
			$flags = $flags & ~LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED;
		}
		if (is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			$flags = $flags & ~LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED;
		}
		
		if ($flags != $old_flags)
		{
			if ($insert)
			{
				list($rules_code) = Db::record(get_label('club'), 'SELECT rules FROM clubs WHERE id = ?', $club_id);
				list($rules_filter) = Db::record(get_label('league'), 'SELECT rules FROM leagues WHERE id = ?', $league_id);
				$rules_filter = json_decode($rules_filter);
				$rules_code = correct_rules($rules_code, $rules_filter);
				Db::exec(get_label('league'), 'INSERT INTO league_clubs (league_id, club_id, rules, flags) VALUES (?, ?, ?, ?)', $league_id, $club_id, $rules_code, $flags);
			}
			else
			{
				Db::exec(get_label('league'), 'UPDATE league_clubs SET flags = ? WHERE league_id = ? AND club_id = ?', $flags, $league_id, $club_id);
			}
			
			if ($flags & LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED)
			{
				list($league_name, $league_langs) = Db::record(get_label('league'), 'SELECT name, langs FROM leagues WHERE id = ?', $league_id);
				list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
				$query = new DbQuery(
					'SELECT u.id, nu.name, u.email, u.def_lang'.
					' FROM league_managers l'.
					' JOIN users u ON u.id = l.user_id'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0 AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0'.
					' WHERE l.league_id = ?', $league_id);
				while ($row = $query->next())
				{
					list($user_id, $user_name, $user_email, $user_lang) = $row;
					if (!is_valid_lang($user_lang))
					{
						$user_lang = get_lang($league_langs);
						if (!is_valid_lang($user_lang))
						{
							$user_lang = LANG_RUSSIAN;
						}
					}
					list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/club_add_league.php';
					
					$tags = array(
						'root' => new Tag(get_server_url()),
						'user_id' => new Tag($user_id),
						'user_name' => new Tag($user_name),
						'league_id' => new Tag($league_id),
						'league_name' => new Tag($league_name),
						'club_id' => new Tag($club_id),
						'club_name' => new Tag($club_name),
						'sender' => new Tag($_profile->user_name));
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
				}
			}
			
			if ($flags & LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED)
			{
				list($league_name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
				list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
				$query = new DbQuery(
					'SELECT u.id, nu.name, u.email, u.def_lang'.
					' FROM club_regs uc'.
					' JOIN users u ON uc.user_id = u.id'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
					' WHERE uc.club_id = ? AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0 AND uc.flags & ' . USER_PERM_MANAGER, $club_id);
				while ($row = $query->next())
				{
					list($user_id, $user_name, $user_email, $user_lang) = $row;
					if (!is_valid_lang($user_lang))
					{
						$user_lang = get_lang($league_langs);
						if (!is_valid_lang($user_lang))
						{
							$user_lang = LANG_RUSSIAN;
						}
					}
					list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/league_add_club.php';
					
					$tags = array(
						'root' => new Tag(get_server_url()),
						'user_id' => new Tag($user_id),
						'user_name' => new Tag($user_name),
						'league_id' => new Tag($league_id),
						'league_name' => new Tag($league_name),
						'club_id' => new Tag($club_id),
						'club_name' => new Tag($club_name),
						'sender' => new Tag($_profile->user_name));
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
				}
			}
			$this->response['flags'] = $flags;
		}
		Db::commit();
	}
	
	function add_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, 'Adds club to a league. Both club manager and league manager have to approve it. When a club manager sends this request, all league managers are notified. One of them has to approve it. In order to do this he sends the same request as a league manager. Once both club manager and league manager send it, the club becomes a member. If user who is sending it is a club manager and a league manager at the same time, the club is just added without sending any emails.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_club
	//-------------------------------------------------------------------------------------------------------
	function remove_club_op()
	{
		global $_profile, $_lang;
		
		$league_id = (int)get_required_param('league_id');
		$club_id = (int)get_required_param('club_id');
		$message = prepare_message(get_optional_param('message'));
		check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, $club_id, $league_id);
		
		Db::begin();
		$insert = true;
		$old_flags = LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED | LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED;
		$query = new DbQuery('SELECT flags FROM league_clubs WHERE league_id = ? AND club_id = ?', $league_id, $club_id);
		if ($row = $query->next())
		{
			list ($old_flags) = $row;
		}
		else
		{
			return;
		}
		
		Db::exec(get_label('league'), 'DELETE FROM league_clubs WHERE league_id = ? AND club_id = ?', $league_id, $club_id);
		
		$flags = $old_flags;
		if (!is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
		{
			list($league_name, $league_langs) = Db::record(get_label('league'), 'SELECT name, langs FROM leagues WHERE id = ?', $league_id);
			list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM league_managers l'.
				' JOIN users u ON u.id = l.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0 AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0'.
				' WHERE l.league_id = ?', $league_id);
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_email, $user_lang) = $row;
				if (!is_valid_lang($user_lang))
				{
					$user_lang = get_lang($league_langs);
					if (!is_valid_lang($user_lang))
					{
						$user_lang = LANG_RUSSIAN;
					}
				}
				list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/league_remove_club.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_id' => new Tag($user_id),
					'user_name' => new Tag($user_name),
					'league_id' => new Tag($league_id),
					'league_name' => new Tag($league_name),
					'club_id' => new Tag($club_id),
					'club_name' => new Tag($club_name),
					'message' => new Tag($message),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
			}
		}
		
		if (!is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			list($league_name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
			list($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM club_regs uc'.
				' JOIN users u ON uc.user_id = u.id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE uc.club_id = ? AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0 AND uc.flags & ' . USER_PERM_MANAGER, $club_id);
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_email, $user_lang) = $row;
				if (!is_valid_lang($user_lang))
				{
					$user_lang = get_lang($league_langs);
					if (!is_valid_lang($user_lang))
					{
						$user_lang = LANG_RUSSIAN;
					}
				}
				list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/club_remove_league.php';
				
				if (!empty($message))
				{
					$tags = array(
						'root' => new Tag(get_server_url()),
						'user_id' => new Tag($user_id),
						'user_name' => new Tag($user_name),
						'league_id' => new Tag($league_id),
						'league_name' => new Tag($league_name),
						'club_id' => new Tag($club_id),
						'club_name' => new Tag($club_name),
						'message' => new Tag($message),
						'sender' => new Tag($_profile->user_name));
					$body = parse_tags($body, $tags);
					$text_body = parse_tags($text_body, $tags);
					send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
				}
			}
		}
		Db::commit();
	}
	
	function remove_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, 'Removes a club to a league. If this is request of a club manager, all league managers are notified. It it is a request of a league manager, all club managers are notified. If a user has both priviledges, club is just removed without any notifications.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('message', 'Message containing explanation why club was removed.', 'empty.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('League Operations', CURRENT_VERSION);

?>