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
		
		$rules_code = get_optional_param('rules_code', DEFAULT_RULES);
		$scoring_id = get_optional_param('scoring_id', SCORING_DEFAULT_ID);
		$normalizer_id = NORMALIZER_DEFAULT_ID;
		
		Db::begin();
		if ($city_id <= 0)
		{
			$city_id = retrieve_city_id(get_required_param('city'), retrieve_country_id(get_required_param('country')), get_timezone());
		}
		list($city_name, $currency_id, $country_id) = Db::record(get_label('city'), 'SELECT n.name, co.currency_id, co.id FROM cities c JOIN countries co ON co.id = c.country_id JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $city_id);
		if (is_sanctioned($country_id))
		{
			throw new Exc(get_label('Due to international sanctions against Russia, the creation of [0] in this country is currently unavailable.', get_label('clubs')));
		}
		
		$is_admin = is_permitted(PERMISSION_ADMIN);
		if ($parent_id > 0)
		{
			list($rules_code, $scoring_id, $normalizer_id) = Db::record(get_label('club'), 'SELECT rules, scoring_id, normalizer_id FROM clubs WHERE id = ?', $parent_id);
			$rules_code = upgrade_rules_code($rules_code);
		}
		else
		{
			$parent_id = NULL;
		}
		Db::exec(
			get_label('club'),
			'INSERT INTO clubs (name, langs, rules, flags, web_site, email, phone, city_id, parent_id, scoring_id, normalizer_id, activated, currency_id) VALUES (?, ?, ?, ' . NEW_CLUB_FLAGS . ', ?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?)',
			$name, $langs, $rules_code, $url, $email, $phone, $city_id, $parent_id, $scoring_id, $normalizer_id, $currency_id);
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
		$log_details->currency_id = $currency_id;
		$log_details->scoring_id = $scoring_id;
		$log_details->normalizer_id = $normalizer_id;
		if (!is_null($parent_id))
		{
			$log_details->parent_id = $parent_id;
		}
		db_log(LOG_OBJECT_CLUB, 'created', $log_details, $club_id, $club_id);

		if (!$is_admin)
		{
			Db::exec(
				get_label('user'), 
				'INSERT INTO club_regs (user_id, club_id, flags) VALUES (?, ?, ' . (USER_CLUB_NEW_PLAYER_FLAGS | USER_PERM_PLAYER | USER_PERM_REFEREE | USER_PERM_MANAGER) . ')',
				$_profile->user_id, $club_id);
			db_log(LOG_OBJECT_USER, 'becomes club manager', NULL, $_profile->user_id, $club_id);
			if ($_profile->user_club_id == NULL)
			{
				Db::exec(get_label('user'), 'UPDATE users SET club_id = ? WHERE id = ?', $club_id, $_profile->user_id);
				$_profile->user_club_id = $club_id;
			}
		}
		$_profile->update_clubs();
		Db::commit();
		
		$this->response['club_id'] = $club_id;
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
		list($old_name, $old_parent_id, $old_url, $old_email, $old_phone, $old_fee, $old_currency_id, $old_langs, $old_scoring_id, $old_normalizer_id, $old_city_id, $old_flags, $old_rules_code, $timezone) = Db::record(get_label('club'),
			'SELECT c.name, c.parent_id, c.web_site, c.email, c.phone, c.fee, c.currency_id, c.langs, c.scoring_id, c.normalizer_id, ct.id, c.flags, c.rules, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
			
		$name = get_optional_param('name', $old_name);
		if ($name != $old_name)
		{
			$this->check_name($name, $club_id);
		}
		
		$old_parent_id = (int)$old_parent_id;
		$parent_id = (int)get_optional_param('parent_id', $old_parent_id);
		$url = check_url(get_optional_param('url', $old_url));
		$phone = get_optional_param('phone', $old_phone);
		$fee = (int)get_optional_param('fee', $old_fee);
		if ($fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = get_optional_param('currency_id', $old_currency_id);
		if ($currency_id <= 0)
		{
			$currency_id = NULL;
		}
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
		
		$rules_code = get_optional_param('rules_code', $old_rules_code);
		
		Db::exec(
			get_label('club'), 
			'UPDATE clubs SET activated = UNIX_TIMESTAMP(), name = ?, web_site = ?, langs = ?, email = ?, phone = ?, fee = ?, currency_id = ?, city_id = ?, scoring_id = ?, normalizer_id = ?, flags = ?, rules = ? WHERE id = ?',
			$name, $url, $langs, $email, $phone, $fee, $currency_id, $city_id, $scoring_id, $normalizer_id, $flags, $rules_code, $club_id);
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
			if ($old_fee != $fee)
			{
				$log_details->fee = $fee;
			}
			if ($old_currency_id != $currency_id)
			{
				$log_details->currency_id = $currency_id;
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
			if ($old_parent_id > 0)
			{
				check_permissions(PERMISSION_CLUB_MANAGER, $old_parent_id);
			}
			if ($parent_id > 0)
			{
				check_permissions(PERMISSION_CLUB_MANAGER, $parent_id);
			}
			else
			{
				$parent_id = NULL;
			}
			
			Db::exec(get_label('club'), 'UPDATE clubs SET activated = UNIX_TIMESTAMP(), parent_id = ? WHERE id = ?', $parent_id, $club_id);
			$log_details = new stdClass();
			$log_details->parent_id = $parent_id;
			if (!is_null($parent_id) && $parent_id > 0)
			{
				$log_details->parent_name = $_profile->clubs[$parent_id]->name;
			}
			db_log(LOG_OBJECT_CLUB, 'became subclub', $log_details, $club_id, $club_id);
			db_log(LOG_OBJECT_CLUB, 'became subclub', NULL, $club_id, $parent_id);
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
		$help->request_param('fee', 'Admition rate for a common event.', 'remains the same.');
		$help->request_param('currency_id', 'Currency used in the club.', 'remains the same.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// close
	//-------------------------------------------------------------------------------------------------------
	function close_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_CLOSED . ' WHERE id = ?', $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_CLUB, 'closed', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function close_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Close the existing club.');
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
			// it is possible that the permission is missing because the club is closed
			$query = new DbQuery(
				'SELECT * FROM club_regs WHERE user_id = ? AND club_id = ? AND (flags & ' . USER_PERM_MANAGER . ') <> 0',
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
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags & ~' . CLUB_FLAG_CLOSED . ', activated = UNIX_TIMESTAMP() WHERE id = ?', $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_CLUB, 'restored', NULL, $club_id, $club_id);
		}
		Db::commit();
		$_profile->update_clubs();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Reopen closed club.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_member
	//-------------------------------------------------------------------------------------------------------
	function add_member_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$club_id = (int)get_required_param('club_id');
		
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);

		$flags = (int)get_optional_param('access_flags', USER_PERM_PLAYER) & USER_PERM_MASK;
		if ($flags == 0)
		{
			throw new Exc(get_label('Please choose at least one role for the user.'));
		}
		$flags += USER_CLUB_NEW_PLAYER_FLAGS;
		
		Db::begin();
		list ($count) = Db::record(get_label('membership'), 'SELECT count(*) FROM club_regs WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if ($count == 0)
		{
			Db::exec(get_label('membership'), 'INSERT INTO club_regs (user_id, club_id, flags) values (?, ?, ?)', $user_id, $club_id, $flags);
			db_log(LOG_OBJECT_USER, 'joined club', NULL, $user_id, $club_id);
			if ($user_id == $owner_id)
			{
				$_profile->update_clubs();
			}
		}
		Db::commit();
		
		$this->response['club_id'] = $club_id;
		$this->response['user_id'] = $user_id;
	}
	
	function add_member_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Make user a club member.');
		$help->request_param('user_id', 'User id. If the user is a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('club_id', 'Club id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_member
	//-------------------------------------------------------------------------------------------------------
	function remove_member_op()
	{
		global $_profile;
		
		$owner_id = 0;
		if ($_profile != NULL)
		{
			$owner_id = $_profile->user_id;
		}
		
		$user_id = (int)get_optional_param('user_id', $owner_id);
		$club_id = (int)get_required_param('club_id');
		
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
		
		Db::begin();
		Db::exec(get_label('membership'), 'DELETE FROM club_regs WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_USER, 'left club', NULL, $user_id, $club_id);
		}
		Db::commit();
		if ($user_id == $owner_id)
		{
			$_profile->update_clubs();
		}
		
		$this->response['club_id'] = $club_id;
		$this->response['user_id'] = $user_id;
	}
	
	function remove_member_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Remove user from the members of the club.');
		$help->request_param('user_id', 'User id. If the user is not a member already success is returned anyway.', 'the one who is making request is used.');
		$help->request_param('club_id', 'Club id.');
		$help->response_param('user_id', 'User id.');
		$help->response_param('club_id', 'Club id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Club Operations', CURRENT_VERSION);

?>