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
		check_permission(PERMISSION_ADMIN);
		$name_en = get_required_param('name_en');
		$name_ru = get_required_param('name_ru');
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
		
		if ($name_en == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('City name in English')));
		}
		check_name($name_en, get_label('City name in English'));
		
		if ($name_ru == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('City name in Russian')));
		}
		check_name($name_ru, get_label('City name in Russian'));
		
		Db::begin();
		if ($country_id <= 0)
		{
			$country_id = retrieve_country_id($country);
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_ru = ?', $name_ru);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('City name in Russian'), $name_ru));
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ?', $name_en);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('City name in English'), $name_en));
		}
		
		Db::exec(
			get_label('city'), 
			'INSERT INTO cities (country_id, name_en, name_ru, timezone, area_id, flags) ' .
				'VALUES (?, ?, ?, ?, ?, ?)',
			$country_id, $name_en, $name_ru, $timezone, $area_id, $flags);
		list ($city_id) = Db::record(get_label('city'), 'SELECT LAST_INSERT_ID()');
		if ($area_id == NULL)
		{
			Db::exec(get_label('city'), 'UPDATE cities SET area_id = id WHERE id = ?', $city_id);
		}
		
		Db::exec(get_label('city'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $name_en);
		if ($name_ru != $name_en)
		{
			Db::exec(get_label('city'), 'INSERT INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $name_ru);
		}
		
		$log_details =
			'country=' . $country . ' (' . $country_id .
			")<br>name_en=" . $name_en .
			"<br>name_ru=" . $name_ru .
			"<br>timezone=" . $timezone .
			"<br>flags=" . $flags;
		db_log('city', 'Created', $log_details, $city_id);
			
		Db::commit();
		$this->response['city_id'] = $city_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Create city.');
		$help->request_param('name_en', 'City name in English.');
		$help->request_param('name_ru', 'City name in Russian.');
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
		check_permission(PERMISSION_ADMIN);
		$city_id = (int)get_required_param('city_id');
		list($name_en, $name_ru, $country_id, $country, $timezone, $area_id) = Db::record(get_label('city'), 'SELECT ct.name_en, ct.name_ru, ct.country_id, cr.name_en, ct.timezone, ct.area_id FROM cities ct JOIN countries cr ON cr.id = ct.country_id WHERE ct.id = ?', $city_id);
		
		if (isset($_REQUEST['name_en']))
		{
			$name_en = $_REQUEST['name_en'];
			if ($name_en == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('City name in English')));
			}
		}
		
		if (isset($_REQUEST['name_ru']))
		{
			$name_ru = $_REQUEST['name_ru'];
			if ($name_ru == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('City name in Russian')));
			}
		}
		
		if (isset($_REQUEST['timezone']))
		{
			$timezone = $_REQUEST['timezone'];
		}
		
		if (isset($_REQUEST['area_id']))
		{
			$area_id = $_REQUEST['area_id'];
			if ($area_id <= 0)
			{
				$area_id = $city_id;
			}
		}
		
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
		Db::begin();
		check_name($name_en, get_label('City name in English'));
		check_name($name_ru, get_label('City name in Russian'));
		
		if (isset($_REQUEST['country_id']))
		{
			$country_id = $_REQUEST['country_id'];
		}
		else if (isset($_REQUEST['country']))
		{
			$country = $_REQUEST['country'];
			$country_id = retrieve_country_id($country);
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_ru = ? AND id <> ?', $name_ru, $city_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('City name in Russian'), $name_ru));
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? AND id <> ?', $name_en, $city_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('City name in English'), $name_en));
		}
		
		$op = 'Changed';
		Db::exec(get_label('city'), 'DELETE FROM city_names WHERE city_id = ? AND name = (SELECT name_en FROM cities WHERE id = ?)', $city_id, $city_id);
		Db::exec(get_label('city'), 'DELETE FROM city_names WHERE city_id = ? AND name = (SELECT name_ru FROM cities WHERE id = ?)', $city_id, $city_id);
		Db::exec(get_label('city'), 'INSERT IGNORE INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $name_en);
		if ($name_en != $name_ru)
		{
			Db::exec(get_label('city'), 'INSERT IGNORE INTO city_names (city_id, name) VALUES (?, ?)', $city_id, $name_ru);
		}
		
		$query = new DbQuery('UPDATE cities SET country_id = ?, name_en = ?, name_ru = ?, area_id = ?, timezone = ?', $country_id, $name_en, $name_ru, $area_id, $timezone);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . CITY_FLAG_NOT_CONFIRMED . ')');
			$op = 'Confirmed';
		}
		$query->add(' WHERE id = ?', $city_id);
		
		$query->exec(get_label('city'));
		if (Db::affected_rows() > 0)
		{
			$log_details = 
				'country=' . $country . ' (' . $country_id .
				")<br>name_en=" . $name_en .
				"<br>name_ru=" . $name_ru .
				"<br>timezone=" . $timezone;
			db_log('city', $op, $log_details, $city_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Change city information.');
		$help->request_param('city_id', 'City id.');
		$help->request_param('name_en', 'City name in English.', 'remains the same.');
		$help->request_param('name_ru', 'City name in Russian.', 'remains the same.');
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
		
		check_permission(PERMISSION_ADMIN);
		$city_id = (int)get_required_param('city_id');
		$repl_id = (int)get_required_param('repl_id');
		$keep_name = (isset($_REQUEST['keep_name']) && $_REQUEST['keep_name']);
		
		Db::begin();
		
		list($new_name, $new_area_id) = Db::record(get_label('city'), 'SELECT name_en, area_id FROM cities WHERE id = ?', $repl_id);
		Db::exec(get_label('club'), 'UPDATE clubs SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('club'), 'UPDATE club_requests SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('address'), 'UPDATE addresses SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('user'), 'UPDATE users SET city_id = ? WHERE city_id = ?', $repl_id, $city_id);
		Db::exec(get_label('city'), 'UPDATE cities SET area_id = ? WHERE area_id = ?', $new_area_id, $city_id);
		if ($keep_name)
		{
			Db::exec(get_label('city'), 'UPDATE city_names n1 SET n1.city_id = ? WHERE n1.city_id = ? AND n1.name NOT IN (SELECT * FROM (SELECT n2.name FROM city_names n2 WHERE n2.city_id = ?) as t)', $repl_id, $city_id, $repl_id);
		}
		Db::exec(get_label('city'), 'DELETE FROM city_names WHERE city_id = ?', $city_id);
		Db::exec(get_label('city'), 'DELETE FROM cities WHERE id = ?', $city_id);

		$log_details = 'replaced with=' . $new_name . ' (' . $repl_id . ')';
		db_log('city', 'Deleted', $log_details, $city_id);
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
$page->run('User Operations', CURRENT_VERSION);

?>