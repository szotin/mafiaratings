<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/address.php';
require_once '../../include/game_rules.php';
require_once '../../include/event.php';
require_once '../../include/url.php';
require_once '../../include/scoring.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $club_id = -1)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('club name')));
		}

		check_name($name, get_label('club name'));

		if ($club_id > 0)
		{
			$query = new DbQuery('SELECT name FROM clubs WHERE name = ? AND id <> ?', $name, $club_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM clubs WHERE name = ?', $name);
		}
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Club name'), $name));
		}
	}

	private function create_event_email_templates($club_id, $langs)
	{
		$l = LANG_NO;
		$second_lang = false;
		while (($l = get_next_lang($l, $langs)) != LANG_NO)
		{
			$lang = get_lang_code($l);
			$event_emails = include '../../include/languages/' . $lang . '/event_emails.php';
			foreach ($event_emails as $event_email)
			{
				list($ename, $esubj, $ebody, $default_for) = $event_email;
				if ($second_lang)
				{
					$default_for = 0;
				}
				Db::exec(
					get_label('email'),
					'INSERT INTO email_templates (club_id, name, subject, body, default_for) VALUES (?, ?, ?, ?, ?)',
					$club_id, $ename, $esubj, $ebody, $default_for);
				list ($template_id) = Db::record(get_label('email'), 'SELECT LAST_INSERT_ID()');
				$log_details = 'name=' . $ename . "<br>subject=" . $esubj . "<br>body=<br>" . $ebody;
				db_log('email_template', 'Created', $log_details, $template_id, $club_id);
			}
			$second_lang = true;
		}
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$name = trim(get_required_param('name'));
		$this->check_name($name);

		$url = get_optional_param('url');
		$phone = get_optional_param('phone');
		$city_id = (int)get_optional_param('city_id', -1);
		
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
		if ($city_id <= 0)
		{
			$city_id = retrieve_city_id(get_required_param('city'), retrieve_country_id(get_required_param('country')), get_timezone());
		}
		list ($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
		
		if ($_profile->is_admin())
		{
			// Admin does not have to send a confirmation request. The club is confirmed instantly.
			$rules = new GameRules();
			$rules_id = $rules->save();
			
			Db::exec(
				get_label('club'),
				'INSERT INTO clubs (name, langs, rules_id, flags, web_site, email, phone, city_id, scoring_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?, ' . SCORING_DEFAULT_ID . ')',
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

			$this->create_event_email_templates($club_id, $langs);
			$this->response['club_id'] = $club_id;
			$_profile->update_clubs();

		}
		else
		{
			Db::exec(
				get_label('club'), 
				'INSERT INTO club_requests (user_id, name, langs, web_site, email, phone, city_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $langs, $url, $email, $phone, $city_id);
				
			list ($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			list ($city_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $city_id);
			$log_details = 
				'name=' . $name .
				"<br>langs=" . $langs .
				"<br>url=" . $url .
				"<br>email=" . $email .
				"<br>phone=" . $phone .
				"<br>city=" . $city_name . ' (' . $city_id . ')';
			db_log('club_request', 'Created', $log_details, $request_id);
			
			// send request to admin
			$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . U_PERM_ADMIN . ') <> 0 and email <> \'\'');
			while ($row = $query->next())
			{
				list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
				$lang = get_lang_code($admin_def_lang);
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_create_club.php';
				
				$tags = array(
					'uname' => new Tag($admin_name),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($admin_email, $body, $text_body, $subj);
			}
			
			echo  
				'<p>' .
				get_label('Your request for creating the club has been sent to the administration. Site administrators will review your club information.') .
				'</p><p>' .
				get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
				'</p>';
		}
		Db::commit();
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create club. If user is admin, club is just created. If not, club request is created and email is sent to admin. Admin has to accept it.');
		$help->request_param('name', 'Club name.');
		$help->request_param('url', 'Club web site URL.');
		$help->request_param('langs', 'Languages used in the club. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'user profile languages are used.');
		$help->request_param('email', 'Club email.', 'user email is used.');
		$help->request_param('phone', 'Club phone. Just a text.', 'empty.');
		$help->request_param('city_id', 'City id.', '<q>city</q> and <q>country</q> must be set.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', '<q>city_id</q> must be set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', '<q>city_id</q> must be set.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return API_PERM_FLAG_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		
		Db::begin();
		list($old_name, $old_url, $old_email, $old_phone, $old_price, $old_langs, $old_scoring_id, $old_city_id, $timezone) = Db::record(get_label('club'),
			'SELECT c.name, c.web_site, c.email, c.phone, c.price, c.langs, c.scoring_id, ct.id, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
		
		$name = get_optional_param('name', $old_name);
		if ($name != $old_name)
		{
			$this->check_name($name, $club_id);
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
		
		$city_id = (int)get_optional_param('city_id', -1);
		
		if ($city_id <= 0)
		{
			$city = get_optional_param('city');
			if (!empty($city))
			{
				$city_id = retrieve_city_id($city, retrieve_country_id(get_optional_param('country')), $timezone);
			}
			if ($city_id <= 0)
			{
				$city_id = $old_city_id;
			}
		}
		
		Db::exec(
			get_label('club'), 
			'UPDATE clubs SET name = ?, web_site = ?, langs = ?, email = ?, phone = ?, price = ?, city_id = ?, scoring_id = ? WHERE id = ?',
			$name, $url, $langs, $email, $phone, $price, $city_id, $scoring_id, $club_id);
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
			db_log('club', 'Changed', $log_details, $club_id, $club_id);
		}
		Db::commit();
			
		$_profile->update_clubs();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change club record.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Club name.', 'remains the same.');
		$help->request_param('url', 'Club web site URL.', 'remains the same.');
		$help->request_param('langs', 'Languages used in the club. A bit combination of 1 (English) and 2 (Russian). Other languages are not supported yet.', 'remains the same.');
		$help->request_param('email', 'Club email.', 'remains the same.');
		$help->request_param('phone', 'Club phone. Just a text.', 'remains the same.');
		$help->request_param('city_id', 'City id.', 'remains the same unless <q>city</q> and <q>country</q> are set.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', 'city remains the same unless <q>city_id</q> is set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', 'city remains the same unless <q>city_id</q> is set.');
		return $help;
	}
	
	function change_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// accept
	//-------------------------------------------------------------------------------------------------------
	function accept_op()
	{
		global $_profile, $_lang_code;
		
		$this->check_permissions();
		$request_id = (int)get_required_param('request_id');
		
		Db::begin();
		list($url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone, $city_id, $city_name) = Db::record(
			get_label('club'),
			'SELECT c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone, c.city_id, i.name_en FROM club_requests c' .
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
			get_label('club'),
			'INSERT INTO clubs (name, langs, rules_id, flags, web_site, email, phone, city_id, scoring_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?, ' . SCORING_DEFAULT_ID . ')',
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
			
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $request_id);
		db_log('club_request', 'Accepted', NULL, $request_id, $club_id);
		
		$this->create_event_email_templates($club_id, $langs);
		
		// send email
		$lang = get_lang_code($user_lang);
		$code = generate_email_code();
		$tags = array(
			'uid' => new Tag($user_id),
			'code' => new Tag($code),
			'uname' => new Tag($user_name),
			'cname' => new Tag($name),
			'url' => new Tag(get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id));
		list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_accept_club.php';
		$body = parse_tags($body, $tags);
		$text_body = parse_tags($text_body, $tags);
		send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_CREATE_CLUB, $club_id, $code);
		
		Db::commit();
		
		$this->response['club_id'] = $club_id;
		if ($_profile->user_id == $user_id)
		{
			$_profile->update_clubs();
		}
	}
	
	function accept_op_help()
	{
		$help = new ApiHelp('Accept club. Admin accepts club request created by a user. The user becomes the a manager of the club. An email is sent to the user notifying that the club is accepted.');
		$help->request_param('request_id', 'Id of the user request');
		$help->request_param('name', 'Name of the club. If set, it is used as a new name for this club instead of the one used in request.', 'name from the request is used');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
	
	function accept_op_permissions()
	{
		return API_PERM_FLAG_ADMIN;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// decline
	//-------------------------------------------------------------------------------------------------------
	function decline_op()
	{
		global $_profile;
		
		$this->check_permissions();
		$request_id = (int)get_required_param('request_id');
		$reason = '';
		if (isset($_REQUEST['reason']))
		{
			$reason = $_REQUEST['reason'];
		}
	
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang) = Db::record(
			get_label('club'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang FROM club_requests c JOIN users u ON c.user_id = u.id WHERE c.id = ?',
			$request_id);
		
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $request_id);
		db_log('club_request', 'Declined', NULL, $request_id);
		Db::commit();
		if ($reason != '')
		{
			$lang = get_lang_code($user_lang);
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email_decline_club.php';
			$tags = array(
				'uname' => new Tag($user_name),
				'reason' => new Tag($reason),
				'club_name' => new Tag($name));
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($user_email, $body, $text_body, $subj);
		}
	}
	
	function decline_op_help()
	{
		$help = new ApiHelp('Decline club create request. An email is sent to the user notifying that the request is declined.');
		$help->request_param('request_id', 'Id of the user request');
		$help->request_param('reason', 'Text explaining why it is declined.', 'empty.');
		return $help;
	}
	
	function decline_op_permissions()
	{
		return API_PERM_FLAG_ADMIN;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// retire
	//-------------------------------------------------------------------------------------------------------
	function retire_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_RETIRED . ' WHERE id = ?', $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log('club', 'Retired', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function retire_op_help()
	{
		$help = new ApiHelp('Close/retire the existing club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function retire_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		if (!$this->is_allowed($_REQUEST['op'], $club_id))
		{
			// it is possible that the permission is missing because the club is retired
			$query = new DbQuery(
				'SELECT * FROM user_clubs WHERE user_id = ? AND club_id = ? AND (flags & ' . UC_PERM_MANAGER . ') <> 0',
				$_profile->user_id, $club_id);
			if (!$query->next())
			{
				if ($_profile == NULL)
				{
					throw new LoginExc();
				}
				throw new FatalExc(get_label('No permissions'));
			}
		}
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags & ~' . CLUB_FLAG_RETIRED . ' WHERE id = ?', $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log('club', 'Restored', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp('Reopen/restore closed/retired club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	function restore_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
}

$page = new ApiPage();
$page->run('Club Operations', CURRENT_VERSION);

?>