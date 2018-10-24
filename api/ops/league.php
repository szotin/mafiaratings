<?php

require_once '../../include/api.php';
require_once '../../include/email.php';
require_once '../../include/address.php';
require_once '../../include/game_rules.php';
require_once '../../include/event.php';
require_once '../../include/url.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $league_id = -1)
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
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$name = trim(get_required_param('name'));
		$this->check_name($name);

		$url = get_optional_param('url');
		$phone = get_optional_param('phone');
		$langs = (int)get_optional_param('langs', $_profile->user_langs);
		if ($langs == 0)
		{
			throw new Exc(get_label('Please select at least one language.'));
		}
		
		$email = trim(get_optional_param('email', $_profile->user_email));
		if (!empty($email) && !is_email($email))
		{
			throw new Exc(get_label('[0] is not a valid email address.', $email));
		}
		
		Db::begin();
		if ($_profile->is_admin())
		{
			// Admin does not have to send a confirmation request. The league is confirmed instantly.
			$rules = new GameRules();
			$rules_id = $rules->save();
			
			Db::exec(
				get_label('league'),
				'INSERT INTO leagues (name, langs, flags, web_site, email, phone, rules_id, scoring_id) VALUES (?, ?, ' . NEW_LEAGUE_FLAGS . ', ?, ?, ?, ?, ' . SCORING_DEFAULT_ID . ')',
				$name, $langs, $url, $email, $phone, $rules_id);
			list ($league_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
			
			$log_details =
				'name=' . $name .
				"<br>langs=" . $langs .
				"<br>rules=" . $rules_id .
				"<br>flags=" . NEW_LEAGUE_FLAGS .
				"<br>url=" . $url . 
				"<br>email=" . $email .
				"<br>phone=" . $phone;
			db_log('league', 'Created', $log_details, $league_id, $league_id);
			$this->response['league_id'] = $league_id;
		}
		else
		{
			Db::exec(
				get_label('league'), 
				'INSERT INTO league_requests (user_id, name, langs, web_site, email, phone) VALUES (?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $langs, $url, $email, $phone);
				
			list ($request_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
			$log_details = 
				'name=' . $name .
				"<br>langs=" . $langs .
				"<br>url=" . $url .
				"<br>email=" . $email .
				"<br>phone=" . $phone;
			db_log('league_request', 'Created', $log_details, $request_id);
			
			// send request to admin
			$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . USER_PERM_ADMIN . ') <> 0 and email <> \'\'');
			while ($row = $query->next())
			{
				list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
				$lang = get_lang_code($admin_def_lang);
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_create_league.php';
				
				$tags = array(
					'uname' => new Tag($admin_name),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($admin_email, $body, $text_body, $subj);
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
		$help->request_param('langs', 'Languages used in the league. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'user profile languages are used.');
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
		list($old_name, $old_url, $old_email, $old_phone, $old_price, $old_langs, $old_scoring_id) = Db::record(get_label('league'),
			'SELECT name, web_site, email, phone, price, langs, scoring_id FROM leagues c WHERE id = ?', $league_id);
		
		$name = get_optional_param('name', $old_name);
		if ($name != $old_name)
		{
			$this->check_name($name, $league_id);
		}
		
		$url = check_url(get_optional_param('url', $old_url));
		$phone = get_optional_param('phone', $old_phone);
		$price = get_optional_param('price', $old_price);
		$scoring_id = get_optional_param('scoring_id', $old_scoring_id);
		$langs = (int)get_optional_param('langs', $old_langs);
		if ($langs == 0)
		{
			throw new Exc(get_label('Please select at least one language.'));
		}
		
		$email = get_optional_param('email', $old_email);
		if ($email != $old_email && !empty($email) && !is_email($email))
		{
			throw new Exc(get_label('[0] is not a valid email address.', $email));
		}
		
		Db::exec(
			get_label('league'), 
			'UPDATE leagues SET name = ?, web_site = ?, langs = ?, email = ?, phone = ?, price = ?, scoring_id = ? WHERE id = ?',
			$name, $url, $langs, $email, $phone, $price, $scoring_id, $league_id);
		if (Db::affected_rows() > 0)
		{
			$log_details =
				'name=' . $name .
				"<br>web_site=" . $url .
				"<br>langs=" . $langs .
				"<br>email=" . $email .
				"<br>phone=" . $phone .
				"<br>price=" . $price;
			db_log('league', 'Changed', $log_details, $league_id, $league_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change league record.');
		$help->request_param('league_id', 'League id.');
		$help->request_param('name', 'League name.', 'remains the same.');
		$help->request_param('url', 'League web site URL.', 'remains the same.');
		$help->request_param('langs', 'Languages used in the league. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same.');
		$help->request_param('email', 'League email.', 'remains the same.');
		$help->request_param('phone', 'League phone. Just a text.', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// accept
	//-------------------------------------------------------------------------------------------------------
	function accept_op()
	{
		global $_profile, $_lang_code;
		
		check_permissions(PERMISSION_ADMIN);
		$request_id = (int)get_required_param('request_id');
		
		Db::begin();
		list($url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone, $city_id, $city_name) = Db::record(
			get_label('league'),
			'SELECT c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone, c.city_id, i.name_en FROM league_requests c' .
				' JOIN users u ON c.user_id = u.id' .
				' JOIN cities i ON c.city_id = i.id' .
				' WHERE c.id = ?',
			$request_id);
			
		if (isset($_REQUEST['name']))
		{
			$name = $_REQUEST['name'];
		}
		$this->check_name($name);
		
		$rules = new GameRules();
		$rules_id = $rules->save();
		
		list ($city_name) = Db::record(get_label('city'), 'SELECT name_' . $_lang_code . ' FROM cities WHERE id = ?', $city_id);
		
		Db::exec(
			get_label('league'),
			'INSERT INTO leagues (name, langs, rules_id, flags, web_site, email, phone, city_id, scoring_id) VALUES (?, ?, ?, ' . NEW_LEAGUE_FLAGS . ', ?, ?, ?, ?, ' . SCORING_DEFAULT_ID . ')',
			$name, $langs, $rules_id, $url, $email, $phone, $city_id);
			
		list ($league_id) = Db::record(get_label('league'), 'SELECT LAST_INSERT_ID()');
		
		$log_details =
			'name=' . $name .
			"<br>langs=" . $langs .
			"<br>rules=" . $rules_id .
			"<br>flags=" . NEW_LEAGUE_FLAGS .
			"<br>url=" . $url . 
			"<br>email=" . $email .
			"<br>phone=" . $phone .
			"<br>city=" . $city_name . ' (' . $city_id . ')';
		db_log('league', 'Created', $log_details, $league_id, $league_id);

		if (($user_flags & USER_PERM_ADMIN) == 0)
		{
			Db::exec(
				get_label('user'), 
				'INSERT INTO user_leagues (user_id, league_id, flags) VALUES (?, ?, ' . (USER_CLUB_NEW_PLAYER_FLAGS | USER_CLUB_PERM_MODER | USER_CLUB_PERM_MANAGER) . ')',
				$user_id, $league_id);
			db_log('user', 'Became a manager of the league', NULL, $user_id, $league_id);
			
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
			
		Db::exec(get_label('league'), 'DELETE FROM league_requests WHERE id = ?', $request_id);
		db_log('league_request', 'Accepted', NULL, $request_id, $league_id);
		
		$this->create_event_email_templates($league_id, $langs);
		
		// send email
		$lang = get_lang_code($user_lang);
		$code = generate_email_code();
		$tags = array(
			'uid' => new Tag($user_id),
			'code' => new Tag($code),
			'uname' => new Tag($user_name),
			'cname' => new Tag($name),
			'url' => new Tag(get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id));
		list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_accept_league.php';
		$body = parse_tags($body, $tags);
		$text_body = parse_tags($text_body, $tags);
		send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_CREATE_LEAGUE, $league_id, $code);
		
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
		global $_profile;
		
		check_permissions(PERMISSION_ADMIN);
		$request_id = (int)get_required_param('request_id');
		$reason = '';
		if (isset($_REQUEST['reason']))
		{
			$reason = $_REQUEST['reason'];
		}
	
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang) = Db::record(
			get_label('league'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang FROM league_requests c JOIN users u ON c.user_id = u.id WHERE c.id = ?',
			$request_id);
		
		Db::exec(get_label('league'), 'DELETE FROM league_requests WHERE id = ?', $request_id);
		db_log('league_request', 'Declined', NULL, $request_id);
		Db::commit();
		if ($reason != '')
		{
			$lang = get_lang_code($user_lang);
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_decline_league.php';
			$tags = array(
				'uname' => new Tag($user_name),
				'reason' => new Tag($reason),
				'league_name' => new Tag($name));
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($user_email, $body, $text_body, $subj);
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
	// retire
	//-------------------------------------------------------------------------------------------------------
	function retire_op()
	{
		global $_profile;
		
		$league_id = (int)get_required_param('league_id');
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		Db::begin();
		Db::exec(get_label('league'), 'UPDATE leagues SET flags = flags | ' . LEAGUE_FLAG_RETIRED . ' WHERE id = ?', $league_id);
		if (Db::affected_rows() > 0)
		{
			db_log('league', 'Retired', NULL, $league_id, $league_id);
		}
		Db::commit();
	}
	
	function retire_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Close/retire the existing league.');
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
		Db::exec(get_label('league'), 'UPDATE leagues SET flags = flags & ~' . LEAGUE_FLAG_RETIRED . ' WHERE id = ?', $league_id);
		if (Db::affected_rows() > 0)
		{
			db_log('league', 'Restored', NULL, $league_id, $league_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Reopen/restore closed/retired league.');
		$help->request_param('league_id', 'League id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('League Operations', CURRENT_VERSION);

?>