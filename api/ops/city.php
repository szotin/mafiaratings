<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/address.php';
require_once '../../include/names.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$timezone = get_required_param('timezone');
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
		$country_id = -1;
		if (isset($_REQUEST['country_id']))
		{
			$country_id = (int)$_REQUEST['country_id'];
		}
		if ($country_id <= 0)
		{
			$country = get_required_param('country');
		}
		
		$area_id = -1;
		if (isset($_REQUEST['area_id']))
		{
			$area_id = $_REQUEST['area_id'];
		}
		if ($area_id <= 0)
		{
			$area_id = NULL;
		}
		
		$flags = $confirm ? 0 : CITY_FLAG_NOT_CONFIRMED;
		
		Db::begin();
		if ($country_id <= 0)
		{
			$country_id = retrieve_country_id($country);
		}
		
		$names = new Names(0, get_label('city name'), 'cities');
		
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			throw new Exc(get_label('Please enter [0].', get_label('city name')));
		}
		
		Db::exec(
			get_label('city'), 
			'INSERT INTO cities (country_id, name_id, timezone, area_id, flags) ' .
				'VALUES (?, ?, ?, ?, ?)',
			$country_id, $name_id, $timezone, $area_id, $flags);
		list ($city_id) = Db::record(get_label('city'), 'SELECT LAST_INSERT_ID()');
		if ($area_id == NULL)
		{
			Db::exec(get_label('city'), 'UPDATE cities SET area_id = id WHERE id = ?', $city_id);
		}
		
		foreach ($names->names as $n)
		{
			Db::exec(get_label('city name'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $n->name);
		}
		
		$log_details = new stdClass();
		$log_details->country = $country;
		$log_details->country_id = $country_id;
		$log_details->name = $names->to_string();
		$log_details->area_id = $area_id;
		$log_details->timezone = $timezone;
		$log_details->flags = $flags;
		db_log(LOG_OBJECT_CITY, 'created', $log_details, $city_id);
			
		Db::commit();
		$this->response['city_id'] = $city_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Create city.');
		Names::help($help, 'City', false);
		$timezone_text = 'City timezone. One of: <select>';
		$zones = DateTimeZone::listIdentifiers();
		foreach ($zones as $zone)
		{
			$timezone_text .= '<option>' . $zone . '</option>';
		}
		$timezone_text .= '</select>';
		$help->request_param('timezone', $timezone_text);
		$help->request_param('country_id', 'Country id of the city.', '<q>country</q> must be set');
		$help->request_param('country', 'Country name. It is used only if <q>country_id</q> is missing.', '<q>country_id</q> must be set');
		$help->request_param('area_id', 'Id of the nearest big city. For example when we create Dolgoprudny, we set Moscow as an area_id. In this case we know that all players from Dolgoprudny can easily participate in all events near Moscow.', 'city forms an area around it by itself.');
		$help->request_param('confirm', 'If it is set and non zero, the city is marked as confirmed by admin.', 'city is not confirmed.');

		$help->response_param('city_id', 'City id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$city_id = (int)get_required_param('city_id');
		
		Db::begin();
		list($old_name_id, $old_country_id, $country, $old_timezone, $old_area_id) = 
			Db::record(get_label('city'), 
				'SELECT ct.name_id, ct.country_id, n.name, ct.timezone, ct.area_id FROM cities ct' .
				' JOIN countries cr ON cr.id = ct.country_id' .
				' JOIN names n ON n.id = cr.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0' .
				' WHERE ct.id = ?', $city_id);
		
		$timezone = get_optional_param('timezone', $old_timezone);
		
		$area_id = $old_area_id;
		if (isset($_REQUEST['area_id']))
		{
			$area_id = $_REQUEST['area_id'];
			if ($area_id <= 0)
			{
				$area_id = $city_id;
			}
		}
		
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
		$names = new Names(0, get_label('city name'), 'cities', $city_id);
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			$name_id = $old_name_id;
		}
		
		if ($name_id != $old_name_id)
		{
			Db::exec(get_label('city name'), 'DELETE FROM city_names WHERE city_id = ? AND name IN (SELECT n.name FROM cities c JOIN names n ON n.id = c.name_id WHERE c.id = ?)', $city_id, $city_id);
			foreach ($names->names as $n)
			{
				Db::exec(get_label('city name'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $n->name);
			}
		}
		
		$country_id = $old_country_id;
		if (isset($_REQUEST['country_id']))
		{
			$country_id = $_REQUEST['country_id'];
			list($country) = Db::record(get_label('country'), 'SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $country_id);
		}
		else if (isset($_REQUEST['country']))
		{
			$country = $_REQUEST['country'];
			$country_id = retrieve_country_id($country);
		}
		
		$op = 'changed';
		$query = new DbQuery('UPDATE cities SET country_id = ?, name_id = ?, area_id = ?, timezone = ?', $country_id, $name_id, $area_id, $timezone);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . CITY_FLAG_NOT_CONFIRMED . ')');
			$op = 'confirmed';
		}
		$query->add(' WHERE id = ?', $city_id);
		
		$query->exec(get_label('city'));
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($country_id != $old_country_id)
			{
				$log_details->country_id = $country_id;
			}
			if ($name_id != $old_name_id)
			{
				$log_details->name = $names->to_string();
			}
			if ($area_id != $old_area_id)
			{
				$log_details->area_id = $area_id;
			}
			if ($timezone != $old_timezone)
			{
				$log_details->timezone = $timezone;
			}
			db_log(LOG_OBJECT_CITY, $op, $log_details, $city_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Change city information.');
		$help->request_param('city_id', 'City id.');
		Names::help($help, 'City', true);
		$timezone_text = 'City timezone. One of: <select>';
		$zones = DateTimeZone::listIdentifiers();
		foreach ($zones as $zone)
		{
			$timezone_text .= '<option>' . $zone . '</option>';
		}
		$timezone_text .= '</select>';
		$help->request_param('timezone', $timezone_text, 'remains the same.');
		$help->request_param('country_id', 'Country id of the city.', 'remains the same unless <q>country</q> is set.');
		$help->request_param('country', 'Country name. It is used only if <q>country_id</q> is missing.', 'remains the same unless <q>country_id</q> is set.');
		$help->request_param('area_id', 'Id of the nearest big city. For example when we create Dolgoprudny, we set Moscow as an area_id. In this case we know that all players from Dolgoprudny can easily participate in all events near Moscow. When not set, remains the same. Pass 0 when the city does not belong to any area. (For example: Moscow, Kiev, NY, London do not - they form their area by themselves).', 'remains the same.');
		$help->request_param('confirm', 'If it is set and non zero, the city is marked as confirmed by admin.', '-');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_ADMIN);
		$city_id = (int)get_required_param('city_id');
		$repl_id = (int)get_required_param('repl_id');
		$keep_name = (isset($_REQUEST['keep_name']) && $_REQUEST['keep_name']);
		
		Db::begin();
		
		list($new_name, $new_area_id) = Db::record(get_label('city'), 'SELECT n.name, c.area_id FROM cities c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $repl_id);
		Db::exec(get_label('club'), 'UPDATE clubs SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('club'), 'UPDATE club_requests SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('address'), 'UPDATE addresses SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('user'), 'UPDATE users SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('city'), 'UPDATE cities SET area_id = ? WHERE area_id = ?', $new_area_id, $city_id);
		if ($keep_name)
		{
			Db::exec(get_label('city name'), 'UPDATE city_names n1 SET n1.city_id = ? WHERE n1.city_id = ? AND n1.name NOT IN (SELECT * FROM (SELECT n2.name FROM city_names n2 WHERE n2.city_id = ?) as t)', $repl_id, $city_id, $repl_id);
		}
		Db::exec(get_label('city name'), 'DELETE FROM city_names WHERE city_id = ?', $city_id);
		Db::exec(get_label('city'), 'DELETE FROM cities WHERE id = ?', $city_id);

		$log_details = new stdClass();
		$log_details->replaced_with = new stdClass();
		$log_details->replaced_with->city = $new_name;
		$log_details->replaced_with->city_id = $repl_id;
		db_log(LOG_OBJECT_CITY, 'deleted', $log_details, $city_id);
		Db::commit();
		
		if ($city_id == $_profile->city_id)
		{
			$_profile->city_id = $repl_id;
			list ($_profile->country_id, $_profile->timezone) =
				Db::record(get_label('city'), 'SELECT country_id, timezone FROM cities WHERE id = ?', $repl_id);
		}
		$_profile->update_clubs();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Delete city.');
		$help->request_param('city_id', 'City id.');
		$help->request_param('repl_id', 'City id that is used as a replacement. The city is replaced by this city everywhere it is used.');
		$help->request_param('keep_name', 'If set and non-zero, the city names are still used as alternative names to the replacement city.', 'city name is not used any more.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('City Operations', CURRENT_VERSION);

?>