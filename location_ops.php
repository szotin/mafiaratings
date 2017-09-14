<?php

require_once 'include/session.php';
require_once 'include/club.php';
require_once 'include/email.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/address.php';
require_once 'include/names.php';

ob_start();
$result = array();
	
try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL || !$_profile->is_admin())
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
/*	echo '<pre>';
	print_r($_POST);
	echo '</pre>';*/
	
	if (isset($_POST['edit_city']))
	{
		if (!isset($_POST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('city')));
		}
		$id = (int)$_POST['id'];
		$name_en = $_POST['name_en'];
		$name_ru = $_POST['name_ru'];
		$country = $_POST['country'];
		$timezone = $_POST['timezone'];
		$area_id = (int)$_POST['near'];
		$confirm = (isset($_POST['confirm']) && $_POST['confirm']);
		
		if ($area_id <= 0)
		{
			$area_id = $id;
		}
		
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
		$country_id = retrieve_country_id($country);
		$query = new DbQuery('SELECT id FROM cities WHERE name_ru = ? AND id <> ?', $name_ru, $id);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('City name in Russian'), $name_ru));
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ? AND id <> ?', $name_en, $id);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('City name in English'), $name_en));
		}
		
		$op = 'Changed';
		$query = new DbQuery('UPDATE cities SET country_id = ?, name_en = ?, name_ru = ?, area_id = ?, timezone = ?', $country_id, $name_en, $name_ru, $area_id, $timezone);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . CITY_FLAG_NOT_CONFIRMED . ')');
			$op = 'Confirmed';
		}
		$query->add(' WHERE id = ?', $id);
		
		$query->exec(get_label('city'));
		if (Db::affected_rows() > 0)
		{
			$log_details = 
				'country=' . $country . ' (' . $country_id .
				")<br>name_en=" . $name_en .
				"<br>name_ru=" . $name_ru .
				"<br>timezone=" . $timezone;
			db_log('city', $op, $log_details, $id);
		}
		Db::commit();
	}
	else if (isset($_POST['new_city']))
	{
		$name_en = $_POST['name_en'];
		$name_ru = $_POST['name_ru'];
		$country = $_POST['country'];
		$timezone = $_POST['timezone'];
		$confirm = (isset($_POST['confirm']) && $_POST['confirm']);
		
		$area_id = -1;
		if (isset($_POST['near']))
		{
			$area_id = $_POST['near'];
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
		$country_id = retrieve_country_id($country);
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_ru = ?', $name_ru);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('City name in Russian'), $name_ru));
		}
		
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ?', $name_en);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('City name in English'), $name_en));
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
		$log_details =
			'country=' . $country . ' (' . $country_id .
			")<br>name_en=" . $name_en .
			"<br>name_ru=" . $name_ru .
			"<br>timezone=" . $timezone .
			"<br>flags=" . $flags;
		db_log('city', 'Created', $log_details, $city_id);
			
		Db::commit();
	}
	else if (isset($_POST['delete_city']))
	{
		if (!isset($_POST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('city')));
		}
		$id = $_POST['id'];
		$repl_id = $_POST['repl'];
		
		Db::begin();
		
		list($new_name) = Db::record(get_label('city'), 'SELECT name_en FROM cities WHERE id = ?', $repl_id);
		Db::exec(get_label('club'), 'UPDATE clubs SET city_id = ? WHERE city_id = ?', $repl_id, $id);
		Db::exec(get_label('club'), 'UPDATE club_requests SET city_id = ? WHERE city_id = ?', $repl_id, $id);
		Db::exec(get_label('address'), 'UPDATE addresses SET city_id = ? WHERE city_id = ?', $repl_id, $id);
		Db::exec(get_label('user'), 'UPDATE users SET city_id = ? WHERE city_id = ?', $repl_id, $id);
		Db::exec(get_label('city'), 'DELETE FROM cities WHERE id = ?', $id);

		$log_details = 'replaced with=' . $new_name . ' (' . $repl_id . ')';
		db_log('city', 'Deleted', $log_details, $id);
		Db::commit();
		
		if ($id == $_profile->city_id)
		{
			$_profile->city_id = $repl_id;
			list ($_profile->country_id, $_profile->timezone) =
				Db::record(get_label('city'), 'SELECT country_id, timezone FROM cities WHERE id = ?', $repl_id);
		}
		$_profile->update_clubs();
	}
	else if (isset($_POST['edit_country']))
	{
		if (!isset($_POST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('country')));
		}
		$id = $_POST['id'];
		$name_en = $_POST['name_en'];
		$name_ru = $_POST['name_ru'];
		$code = $_POST['code'];
		$confirm = (isset($_POST['confirm']) && $_POST['confirm']);
		
		if ($name_en == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country name in English')));
		}
		check_name($name_en, get_label('Country name in English'));
		
		if ($name_ru == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country name in Russian')));
		}
		check_name($name_ru, get_label('Country name in Russian'));
		
		if ($code == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country code')));
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id FROM countries WHERE name_ru = ? AND id <> ?', $name_ru, $id);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country name in Russian'), $name_ru));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? AND id <> ?', $name_en, $id);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country name in English'), $name_en));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE code = ? AND id <> ?', $code, $id);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		$op = 'Changed';
		$query = new DbQuery('UPDATE countries SET name_en = ?, name_ru = ?, code = ?', $name_en, $name_ru, $code);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . COUNTRY_FLAG_NOT_CONFIRMED . ')');
			$op = 'Confirmed';
		}
		$query->add(' WHERE id = ?', $id);
		
		$query->exec(get_label('country'));
		if (Db::affected_rows() > 0)
		{
			$log_details = 
				'code=' . $code .
				"<br>name_en=" . $name_en .
				"<br>name_ru=" . $name_ru;
			db_log('country', $op, $log_details, $id);
		}	
		Db::commit();
	}
	else if (isset($_POST['new_country']))
	{
		$name_en = $_POST['name_en'];
		$name_ru = $_POST['name_ru'];
		$code = $_POST['code'];
		$confirm = (isset($_POST['confirm']) && $_POST['confirm']);
		
		$flags = $confirm ? 0 : COUNTRY_FLAG_NOT_CONFIRMED;
		
		if ($name_en == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country name in English')));
		}
		check_name($name_en, get_label('Country name in English'));
		
		if ($name_ru == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country name in Russian')));
		}
		check_name($name_ru, get_label('Country name in Russian'));
		
		if ($code == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country code')));
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id FROM cities WHERE name_ru = ?', $name_ru);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country name in Russian'), $name_ru));
		}
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ?', $name_en);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country name in English'), $name_en));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE code = ?', $code);
		if ($query->next())
		{
			throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		Db::exec(
			get_label('country'), 
			'INSERT INTO countries (name_en, name_ru, code, flags) VALUES (?, ?, ?, ?)',
			$name_en, $name_ru, $code, $flags);
		list ($country_id) = Db::record(get_label('country'), 'SELECT LAST_INSERT_ID()');
		$log_details = 
			'name_en=' . $name_en .
			"<br>name_ru=" . $name_ru .
			"<br>code=" . $code .
			"<br>flags=" . $flags;
		db_log('country', 'Created', $log_details, $country_id);
			
		Db::commit();
	}
	else if (isset($_POST['delete_country']))
	{
		if (!isset($_POST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('country')));
		}
		$id = $_POST['id'];
		$repl_id = $_POST['repl'];
		
		Db::begin();
		
		list($new_name) = Db::record(get_label('country'), 'SELECT name_en FROM countries WHERE id = ?', $repl_id);
		Db::exec(get_label('city'), 'UPDATE cities SET country_id = ? WHERE country_id = ?', $repl_id, $id);
		Db::exec(get_label('country'), 'DELETE FROM countries WHERE id = ?', $id);
		
		$log_details = 'replaced by=' . $new_name . ' (' . $repl_id . ')';
		db_log('country', 'Deleted', $log_details, $id);
		Db::commit();
		
		if ($id == $_profile->country_id)
		{
			$_profile->country_id = $repl_id;
		}
		$_profile->update_clubs();
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>