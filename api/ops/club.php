<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/address.php';
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

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$name = trim(get_required_param('name'));
		$this->check_name($name);

		$parent_id = (int)get_optional_param('parent_id', 0);
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
		list($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
		
		$is_admin = is_permitted(PERMISSION_ADMIN);
		if ($is_admin || is_permitted(PERMISSION_CLUB_MANAGER, $parent_id))
		{
			// Admin does not have to send a confirmation request. The club is confirmed instantly.
			if ($parent_id > 0)
			{
				list($rules_code, $scoring_id, $normalizer_id) = Db::record(get_label('club'), 'SELECT rules, scoring_id, normalizer_id FROM clubs WHERE id = ?', $parent_id);
			}
			else
			{
				$rules_code = default_rules_code();
				$scoring_id = SCORING_DEFAULT_ID;
				$normalizer_id = NORMALIZER_DEFAULT_ID;
				$parent_id = NULL;
			}
			Db::exec(
				get_label('club'),
				'INSERT INTO clubs (name, langs, rules, flags, web_site, email, phone, city_id, parent_id, scoring_id, normalizer_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?, ?, ?, ?)',
				$name, $langs, $rules_code, $url, $email, $phone, $city_id, $parent_id, $scoring_id, $normalizer_id);
			list($club_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->rules_code = $rules_code;
			$log_details->flags = NEW_CLUB_FLAGS;
			$log_details->url = $url;
			$log_details->email = $email;
			$log_details->phone = $phone;
			$log_details->city = $city_name;
			$log_details->city_id = $city_id;
			$log_details->scoring_id = $scoring_id;
			$log_details->normalizer_id = $normalizer_id;
			if (!is_null($parent_id))
			{
				$log_details->parent_id = $parent_id;
			}
			db_log(LOG_OBJECT_CLUB, 'created', $log_details, $club_id, $club_id);

			$this->response['club_id'] = $club_id;
			
			if (!$is_admin)
			{
				Db::exec(
					get_label('user'), 
					'INSERT INTO club_users (user_id, club_id, flags) VALUES (?, ?, ' . (USER_CLUB_NEW_PLAYER_FLAGS | USER_PERM_REFEREE | USER_PERM_MANAGER) . ')',
					$_profile->user_id, $club_id);
				db_log(LOG_OBJECT_USER, 'becomes club manager', NULL, $_profile->user_id, $club_id);
			}
			
			$_profile->update_clubs();
		}
		else if ($parent_id > 0)
		{
			Db::exec(
				get_label('club'), 
				'INSERT INTO club_requests (user_id, name, parent_id, langs, web_site, email, phone, city_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $parent_id, $langs, $url, $email, $phone, $city_id);
			list($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->url = $url;
			$log_details->email = $email;
			$log_details->phone = $phone;
			$log_details->city = $city_name;
			$log_details->city_id = $city_id;
			db_log(LOG_OBJECT_CLUB_REQUEST, 'created', $log_details, $request_id, $parent_id);
			
			list($parent_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $parent_id);
			
			// send request to parent club managers
			$query = new DbQuery('SELECT u.id, u.name, u.email, u.def_lang FROM club_users c JOIN users u ON c.user_id = u.id WHERE (c.flags & ' . USER_PERM_MANAGER . ') <> 0 AND u.email <> \'\' AND c.club_id = ?', $parent_id);
			while ($row = $query->next())
			{
				list($manager_id, $manager_name, $manager_email, $manager_def_lang) = $row;
				$lang = get_lang_code($manager_def_lang);
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/create_subclub.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_name' => new Tag($manager_name),
					'user_id' => new Tag($manager_id),
					'club_name' => new Tag($name),
					'parent_name' => new Tag($parent_name),
					'parent_id' => new Tag($parent_id),
					'sender' => new Tag($_profile->user_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($manager_email, $body, $text_body, $subj);
			}
			
			echo  
				'<p>' .
				get_label('Your request for creating the club has been sent to [0] managers. They will review your club information.', $parent_name) .
				'</p><p>' .
				get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
				'</p>';
		}
		else
		{
			Db::exec(
				get_label('club'), 
				'INSERT INTO club_requests (user_id, name, langs, web_site, email, phone, city_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $name, $langs, $url, $email, $phone, $city_id);
				
			list($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			list($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
			
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->url = $url;
			$log_details->email = $email;
			$log_details->phone = $phone;
			$log_details->city = $city_name;
			$log_details->city_id = $city_id;
			db_log(LOG_OBJECT_CLUB_REQUEST, 'created', $log_details, $request_id);
			
			// send request to admin
			$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . USER_PERM_ADMIN . ') <> 0 and email <> \'\'');
			while ($row = $query->next())
			{
				list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
				$lang = get_lang_code($admin_def_lang);
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/create_club.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_name' => new Tag($admin_name),
					'user_id' => new Tag($admin_id),
					'club_name' => new Tag($name),
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
		$help = new ApiHelp(PERMISSION_USER, 'Create club. If user is admin, club is just created. If not, club request is created and email is sent to admin. Admin has to accept it.');
		$help->request_param('parent_id', 'Id of the parent club. 0 or negative for creating top level club.', 'top level club is created');
		$help->request_param('name', 'Club name.');
		$help->request_param('url', 'Club web site URL.');
		$help->request_param('langs', 'Languages used in the club. A bit combination of language ids.' . valid_langs_help(), 'user profile languages are used.');
		$help->request_param('email', 'Club email.', 'user email is used.');
		$help->request_param('phone', 'Club phone. Just a text.', 'empty.');
		$help->request_param('city_id', 'City id.', '<q>city</q> and <q>country</q> must be set.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', '<q>city_id</q> must be set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', '<q>city_id</q> must be set.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile, $_FILES;
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		list($old_name, $old_parent_id, $old_url, $old_email, $old_phone, $old_price, $old_langs, $old_scoring_id, $old_normalizer_id, $old_city_id, $old_flags, $timezone) = Db::record(get_label('club'),
			'SELECT c.name, c.parent_id, c.web_site, c.email, c.phone, c.price, c.langs, c.scoring_id, c.normalizer_id, ct.id, c.flags, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
			
		$name = get_optional_param('name', $old_name);
		if ($name != $old_name)
		{
			$this->check_name($name, $club_id);
		}
		
		$parent_id = (int)get_optional_param('parent_id', $old_parent_id);
		$url = check_url(get_optional_param('url', $old_url));
		$phone = get_optional_param('phone', $old_phone);
		$price = get_optional_param('price', $old_price);
		$scoring_id = get_optional_param('scoring_id', $old_scoring_id);
		$normalizer_id = get_optional_param('normalizer_id', $old_normalizer_id);
		if ($normalizer_id <= 0)
		{
			$normalizer_id = NULL;
		}
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
		
		$flags = $old_flags;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . CLUB_PICS_DIR, $club_id);
			
			$icon_version = (($flags & CLUB_ICON_MASK) >> CLUB_ICON_MASK_OFFSET) + 1;
			if ($icon_version > CLUB_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~CLUB_ICON_MASK) + ($icon_version << CLUB_ICON_MASK_OFFSET);
		}
		
		Db::exec(
			get_label('club'), 
			'UPDATE clubs SET name = ?, web_site = ?, langs = ?, email = ?, phone = ?, price = ?, city_id = ?, scoring_id = ?, normalizer_id = ?, flags = ? WHERE id = ?',
			$name, $url, $langs, $email, $phone, $price, $city_id, $scoring_id, $normalizer_id, $flags, $club_id);
		if (Db::affected_rows() > 0)
		{
			list($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
			$log_details = new stdClass();
			if ($old_name != $name)
			{
				$log_details->name = $name;
			}
			if ($old_url != $url)
			{
				$log_details->url = $url;
			}
			if ($old_langs != $langs)
			{
				$log_details->langs = $langs;
			}
			if ($old_email != $email)
			{
			$log_details->email = $email;
			}
			if ($old_price != $price)
			{
				$log_details->price = $price;
			}
			if ($old_city_id != $city_id)
			{
				$log_details->city = $city_name;
				$log_details->city_id = $city_id;
			}
			if ($old_scoring_id != $scoring_id)
			{
				$log_details->scoring_id = $scoring_id;
			}
			if ($old_normalizer_id != $normalizer_id)
			{
				$log_details->normalizer_id = $normalizer_id;
			}
			if ($old_flags != $flags)
			{
				$log_details->flags = $flags;
				$log_details->logo_uploaded = true;
			}
			db_log(LOG_OBJECT_CLUB, 'changed', $log_details, $club_id, $club_id);
		}
		
		if ($old_parent_id != $parent_id)
		{
			if ($parent_id > 0)
			{
				if ($_profile->is_club_manager($parent_id))
				{
					Db::exec(get_label('club'), 'UPDATE clubs SET parent_id = ? WHERE id = ?', $parent_id, $club_id);
					$log_details = new stdClass();
					$log_details->parent_id = $parent_id;
					$log_details->parent_name = $_profile->clubs[$parent_id]->name;
					db_log(LOG_OBJECT_CLUB, 'became subclub', $log_details, $club_id, $club_id);
					db_log(LOG_OBJECT_CLUB, 'became subclub', NULL, $club_id, $parent_id);
				}
				else
				{
					Db::exec(get_label('club'), 'INSERT INTO club_requests (user_id, club_id, parent_id) VALUES (?, ?, ?)', $_profile->user_id, $club_id, $parent_id);
					list($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
					
					list($parent_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $parent_id);
					$log_details = new stdClass();
					$log_details->parent_name = $parent_name;
					$log_details->parent_id = $parent_id;
					db_log(LOG_OBJECT_CLUB_REQUEST, 'subclub request created', $log_details, $request_id, $club_id);
					db_log(LOG_OBJECT_CLUB, 'subclub request created', NULL, $club_id, $parent_id);
					
					// send request to parent club managers
					$query = new DbQuery('SELECT u.id, u.name, u.email, u.def_lang FROM club_users c JOIN users u ON c.user_id = u.id WHERE (c.flags & ' . USER_PERM_MANAGER . ') <> 0 AND u.email <> \'\' AND c.club_id = ?', $parent_id);
					while ($row = $query->next())
					{
						list($manager_id, $manager_name, $manager_email, $manager_def_lang) = $row;
						$lang = get_lang_code($manager_def_lang);
						list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/make_subclub.php';
						
						$tags = array(
							'root' => new Tag(get_server_url()),
							'user_name' => new Tag($manager_name),
							'user_id' => new Tag($manager_id),
							'club_name' => new Tag($name),
							'club_id' => new Tag($club_id),
							'parent_name' => new Tag($parent_name),
							'parent_id' => new Tag($parent_id),
							'sender' => new Tag($_profile->user_name));
						$body = parse_tags($body, $tags);
						$text_body = parse_tags($text_body, $tags);
						send_email($manager_email, $body, $text_body, $subj);
					}
					
					echo  
						'<p>' .
						get_label('Request for adding your club "[0]" to "[1]" system has been sent to [1] managers. They will review the request.', $name, $parent_name) .
						'</p><p>' .
						get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
						'</p>';
				}
			}
			else if ($old_parent_id > 0)
			{
				if ($_profile->is_admin())
				{
					Db::exec(get_label('club'), 'UPDATE clubs SET parent_id = NULL WHERE id = ?', $club_id);
					db_log(LOG_OBJECT_CLUB, 'became a club system', NULL, $club_id, $club_id);
				}
				else
				{
					Db::exec(get_label('club'), 'INSERT INTO club_requests (user_id, club_id) VALUES (?, ?)', $_profile->user_id, $club_id);
					list($request_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
					db_log(LOG_OBJECT_CLUB_REQUEST, 'root club request created', NULL, $request_id, $club_id);
					
					// send request to admin
					$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . USER_PERM_ADMIN . ') <> 0 and email <> \'\'');
					while ($row = $query->next())
					{
						list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
						$lang = get_lang_code($admin_def_lang);
						list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/make_club.php';
						
						$tags = array(
							'root' => new Tag(get_server_url()),
							'user_name' => new Tag($admin_name),
							'user_id' => new Tag($admin_id),
							'club_name' => new Tag($name),
							'club_id' => new Tag($club_id),
							'sender' => new Tag($_profile->user_name));
						$body = parse_tags($body, $tags);
						$text_body = parse_tags($text_body, $tags);
						send_email($admin_email, $body, $text_body, $subj);
					}
					
					echo  
						'<p>' .
						get_label('Your request for making [0] a club system has been sent to the administration. Site administrators will review your club information.') .
						'</p><p>' .
						get_label('Please wait for the confirmation email. It takes from a few hours to three days depending on administrators load.') .
						'</p>';
				}
			}
		}
		
		Db::commit();
			
		$_profile->update_clubs();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change club record.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Club name.', 'remains the same.');
		$help->request_param('parent_id', 'Id of the parent club. 0 or negative for changing to top level club.', 'remains the same');
		$help->request_param('url', 'Club web site URL.', 'remains the same.');
		$help->request_param('langs', 'Languages used in the club. A bit combination of language ids.' . valid_langs_help(), 'remains the same.');
		$help->request_param('email', 'Club email.', 'remains the same.');
		$help->request_param('phone', 'Club phone. Just a text.', 'remains the same.');
		$help->request_param('city_id', 'City id.', 'remains the same unless <q>city</q> and <q>country</q> are set.');
		$help->request_param('city', 'City name. Used only when <q>city_id</q> is not set. If a city with this name is not found, new city is created.', 'city remains the same unless <q>city_id</q> is set.');
		$help->request_param('country', 'Country name. Used only when <q>city_id</q> is not set. If a country with this name is not found, new country is created.', 'city remains the same unless <q>city_id</q> is set.');
		$help->request_param('logo', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");
		$help->request_param('scoring_id', 'Default scoring system for the club. This scoring system is suggested by default to all new tournaments of the club.', 'remains the same.');
		$help->request_param('normalizer_id', 'Default scoring normalizer for the club. This scoring normalizer is suggested by default to all new tournaments of the club. Send 0 if no default normalizer needed.', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// accept
	//-------------------------------------------------------------------------------------------------------
	function accept_op()
	{
		global $_profile, $_lang;
		
		$request_id = (int)get_required_param('request_id');
		
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang, $user_flags, $email, $phone, $city_id, $city_name, $club_id, $club_name, $parent_id, $parent_name) = Db::record(
			get_label('club'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, u.flags, c.email, c.phone, c.city_id, ni.name, c.club_id, cl.name, c.parent_id, p.name FROM club_requests c' .
				' JOIN users u ON c.user_id = u.id' .
				' LEFT OUTER JOIN cities i ON c.city_id = i.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . LANG_ENGLISH . ') <> 0 ' .
				' LEFT OUTER JOIN clubs cl ON c.club_id = cl.id' .
				' LEFT OUTER JOIN clubs p ON c.parent_id = p.id' .
				' WHERE c.id = ?',
			$request_id);
			
		if ($parent_id == NULL)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		else
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $parent_id);
		}
			
		$lang = get_lang_code($user_lang);
		if ($club_id == NULL)
		{
			$name = get_optional_param('name', $name);
			$this->check_name($name);
			
			if ($parent_id != NULL)
			{
				list($rules_code, $scoring_id, $normalizer_id) = Db::record(get_label('club'), 'SELECT rules, scoring_id, normalizer_id FROM clubs WHERE id = ?', $parent_id);
			}
			else
			{
				$rules_code = default_rules_code();
				$scoring_id = SCORING_DEFAULT_ID;
				$normalizer_id = NORMALIZER_DEFAULT_ID;
			}
			
			list($city_name) = Db::record(get_label('city'), 'SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
			
			Db::exec(
				get_label('club'),
				'INSERT INTO clubs (name, langs, rules, flags, web_site, email, phone, city_id, scoring_id, normalizer_id, parent_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?, ?, ?, ?)',
				$name, $langs, $rules_code, $url, $email, $phone, $city_id, $scoring_id, $normalizer_id, $parent_id);
				
			list($club_id) = Db::record(get_label('club'), 'SELECT LAST_INSERT_ID()');
			
			$log_details = new stdClass();
			$log_details->name = $name;
			$log_details->langs = $langs;
			$log_details->rules_code = $rules_code;
			$log_details->flags = NEW_CLUB_FLAGS;
			$log_details->url = $url;
			$log_details->phone = $phone;
			$log_details->city = $city_name;
			$log_details->city_id = $city_id;
			$log_details->scoring_id = $scoring_id;
			$log_details->normalizer_id = $normalizer_id;
			if ($parent_id != NULL)
			{
				$log_details->parent_id = $parent_id;
				$log_details->parent = $parent_name;
			}
			db_log(LOG_OBJECT_CLUB, 'created', $log_details, $club_id, $club_id);

			if (($user_flags & USER_PERM_ADMIN) == 0)
			{
				Db::exec(
					get_label('user'), 
					'INSERT INTO club_users (user_id, club_id, flags) VALUES (?, ?, ' . (USER_CLUB_NEW_PLAYER_FLAGS | USER_PERM_REFEREE | USER_PERM_MANAGER) . ')',
					$user_id, $club_id);
				db_log(LOG_OBJECT_USER, 'becomes club manager', NULL, $user_id, $club_id);
			}
			
			// send email
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'club_id' => new Tag($club_id),
				'club_name' => new Tag($name));
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/accept_club.php';
		}
		else if ($parent_id == NULL)
		{
			Db::exec(get_label('club'), 'UPDATE clubs SET parent_id = NULL WHERE id = ?', $club_id);
			
			// send email
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'club_id' => new Tag($club_id),
				'club_name' => new Tag($club_name));
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/accept_root_club.php';
		}
		else
		{
			Db::exec(get_label('club'), 'UPDATE clubs SET parent_id = ? WHERE id = ?', $parent_id, $club_id);
			
			// send email
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'club_id' => new Tag($club_id),
				'club_name' => new Tag($club_name),
				'parent_id' => new Tag($parent_id),
				'parent_name' => new Tag($parent_name));
			list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/accept_subclub.php';
		}
		$subj = parse_tags($subj, $tags);
		$body = parse_tags($body, $tags);
		$text_body = parse_tags($text_body, $tags);
		send_email($user_email, $body, $text_body, $subj);
			
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $request_id);
		db_log(LOG_OBJECT_CLUB_REQUEST, 'accepted', NULL, $request_id, $club_id);
		
		Db::commit();
		
		$this->response['club_id'] = $club_id;
		if ($_profile->user_id == $user_id)
		{
			$_profile->update_clubs();
		}
	}
	
	function accept_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN | PERMISSION_CLUB_MANAGER, 'Accept club. Accepting creating a root level club or making existing club a root level (admin permission required). Or accepting creating subclub of a parent club or making existing club a subclub (manager permission for the parent club required). An email is sent to the user notifying that the club is accepted.');
		$help->request_param('request_id', 'Id of the user request');
		$help->request_param('name', 'Name of the club. If set, it is used as a new name for this club instead of the one used in request. It is used only for creating a club.', 'name from the request is used');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// decline
	//-------------------------------------------------------------------------------------------------------
	function decline_op()
	{
		global $_profile;
		
		$request_id = (int)get_required_param('request_id');
		$reason = '';
		if (isset($_REQUEST['reason']))
		{
			$reason = $_REQUEST['reason'];
		}
	
		Db::begin();
		list($name, $url, $langs, $user_id, $user_name, $user_email, $user_lang, $club_id, $parent_id) = Db::record(
			get_label('club'),
			'SELECT c.name, c.web_site, c.langs, c.user_id, u.name, u.email, u.def_lang, c.club_id, c.parent_id FROM club_requests c JOIN users u ON c.user_id = u.id WHERE c.id = ?',
			$request_id);
			
		if ($parent_id == NULL)
		{
			check_permissions(PERMISSION_ADMIN);
		}
		else
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $parent_id);
		}
		
		Db::exec(get_label('club'), 'DELETE FROM club_requests WHERE id = ?', $request_id);
		$log_details = new stdClass();
		$log_details->reason = $reason;
		db_log(LOG_OBJECT_CLUB_REQUEST, 'declined', NULL, $request_id);
		Db::commit();
		if ($reason != '')
		{
			$lang = get_lang_code($user_lang);
			if ($club_id == NULL)
			{
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/decline_create_club.php';
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_name' => new Tag($user_name),
					'reason' => new Tag($reason),
					'club_name' => new Tag($name));
			}
			else
			{
				list($subj, $body, $text_body) = include '../../include/languages/' . $lang . '/email/decline_update_club.php';
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_name' => new Tag($user_name),
					'reason' => new Tag($reason),
					'club_id' => new Tag($reason),
					'club_name' => new Tag($name));
			}
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($user_email, $body, $text_body, $subj);
		}
	}
	
	function decline_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Decline club create request. An email is sent to the user notifying that the request is declined.');
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
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_RETIRED . ' WHERE id = ?', $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_CLUB, 'retired', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function retire_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Close/retire the existing club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		if (!is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			// it is possible that the permission is missing because the club is retired
			$query = new DbQuery(
				'SELECT * FROM club_users WHERE user_id = ? AND club_id = ? AND (flags & ' . USER_PERM_MANAGER . ') <> 0',
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
			db_log(LOG_OBJECT_CLUB, 'restored', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Reopen/restore closed/retired club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Club Operations', CURRENT_VERSION);

?>