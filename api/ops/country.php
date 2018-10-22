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
		$name_en = get_required_param('name_en');
		$name_ru = get_required_param('name_ru');
		$code = get_required_param('code');
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
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
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country name in Russian'), $name_ru));
		}
		$query = new DbQuery('SELECT id FROM cities WHERE name_en = ?', $name_en);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country name in English'), $name_en));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE code = ?', $code);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		Db::exec(
			get_label('country'), 
			'INSERT INTO countries (name_en, name_ru, code, flags) VALUES (?, ?, ?, ?)',
			$name_en, $name_ru, $code, $flags);
		list ($country_id) = Db::record(get_label('country'), 'SELECT LAST_INSERT_ID()');
		
		Db::exec(get_label('country'), 'INSERT INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $name_en);
		if ($name_en != $name_ru)
		{
			Db::exec(get_label('country'), 'INSERT INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $name_ru);
		}
		
		$log_details = 
			'name_en=' . $name_en .
			"<br>name_ru=" . $name_ru .
			"<br>code=" . $code .
			"<br>flags=" . $flags;
		db_log('country', 'Created', $log_details, $country_id);
			
		Db::commit();
		$this->response['country_id'] = $country_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
		$help->request_param('name_en', 'Country name in English.');
		$help->request_param('name_ru', 'Country name in Russian.');
		$help->request_param('code', 'Two letter country code.');
		$help->request_param('confirm', 'If it is set and non zero, the country is marked as confirmed by admin.', 'country is not confirmed.');

		$help->response_param('country_id', 'Country id.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return PERMISSION_ADMIN;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$country_id = (int)get_required_param('country_id');
		list($name_en, $name_ru, $code) = Db::record(get_label('country'), 'SELECT name_en, name_ru, code FROM countries WHERE id = ?', $country_id);
		
		if (isset($_REQUEST['name_en']))
		{
			$name_en = $_REQUEST['name_en'];
			if ($name_en == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('Country name in English')));
			}
		}
		
		if (isset($_REQUEST['name_ru']))
		{
			$name_ru = $_REQUEST['name_ru'];
			if ($name_ru == '')
			{
				throw new Exc(get_label('Please enter [0].', get_label('Country name in Russian')));
			}
		}
		
		if (isset($_REQUEST['code']))
		{
			$code = $_REQUEST['code'];
		}
		if (strlen($code) != 2)
		{
			throw new Exc(get_label('Country code must be two letters.'));
		}
		
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
		Db::begin();
		check_name($name_en, get_label('Country name in English'));
		check_name($name_ru, get_label('Country name in Russian'));
		$query = new DbQuery('SELECT id FROM countries WHERE name_ru = ? AND id <> ?', $name_ru, $country_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country name in Russian'), $name_ru));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE name_en = ? AND id <> ?', $name_en, $country_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country name in English'), $name_en));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE code = ? AND id <> ?', $code, $country_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		Db::exec(get_label('country'), 'DELETE FROM country_names WHERE country_id = ? AND name = (SELECT name_en FROM countries WHERE id = ?)', $country_id, $country_id);
		Db::exec(get_label('country'), 'DELETE FROM country_names WHERE country_id = ? AND name = (SELECT name_ru FROM countries WHERE id = ?)', $country_id, $country_id);
		Db::exec(get_label('country'), 'INSERT INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $name_en);
		if ($name_en != $name_ru)
		{
			Db::exec(get_label('country'), 'INSERT INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $name_ru);
		}
		
		$op = 'Changed';
		$query = new DbQuery('UPDATE countries SET name_en = ?, name_ru = ?, code = ?', $name_en, $name_ru, $code);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . COUNTRY_FLAG_NOT_CONFIRMED . ')');
			$op = 'Confirmed';
		}
		$query->add(' WHERE id = ?', $country_id);
		
		$query->exec(get_label('country'));
		if (Db::affected_rows() > 0)
		{
			$log_details = 
				'code=' . $code .
				"<br>name_en=" . $name_en .
				"<br>name_ru=" . $name_ru;
			db_log('country', $op, $log_details, $country_id);
		}	
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change country.');
		
		$help->request_param('country_id', 'Country id.');
		$help->request_param('name_en', 'Country name in English.', 'remains the same.');
		$help->request_param('name_ru', 'Country name in Russian.', 'remains the same.');
		$help->request_param('code', 'Two letter country code.', 'remains the same.');
		$help->request_param('confirm', 'If it is set and non zero, the country is marked as confirmed by admin.', 'country confirmation status remains the same.');

		return $help;
	}
	
	function change_op_permissions()
	{
		return PERMISSION_ADMIN;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		$country_id = (int)get_required_param('country_id');
		$repl_id = (int)get_required_param('repl_id');
		$keep_name = (isset($_REQUEST['keep_name']) && $_REQUEST['keep_name']);
		
		Db::begin();
		
		list($new_name) = Db::record(get_label('country'), 'SELECT name_en FROM countries WHERE id = ?', $repl_id);
		Db::exec(get_label('city'), 'UPDATE cities SET country_id = ? WHERE country_id = ?', $repl_id, $country_id);
		if ($keep_name)
		{
			Db::exec(get_label('country'), 'UPDATE country_names n1 SET n1.country_id = ? WHERE n1.country_id = ? AND n1.name NOT IN (SELECT * FROM (SELECT n2.name FROM country_names n2 WHERE n2.country_id = ?) as t)', $repl_id, $country_id, $repl_id);
		}
		Db::exec(get_label('country'), 'DELETE FROM country_names WHERE country_id = ?', $country_id);
		Db::exec(get_label('country'), 'DELETE FROM countries WHERE id = ?', $country_id);
		
		$log_details = 'replaced by=' . $new_name . ' (' . $repl_id . ')';
		db_log('country', 'Deleted', $log_details, $country_id);
		Db::commit();
		
		if ($country_id == $_profile->country_id)
		{
			$_profile->country_id = $repl_id;
		}
		$_profile->update_clubs();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp('Delete country.');
		$help->request_param('country_id', 'Country id.');
		$help->request_param('repl_id', 'Country id that is used as a replacement. The country is replaced by this country everywhere it is used.');
		$help->request_param('keep_name', 'If set and non-zero, the country names are still used as alternative names to the replacement country.', 'country name is not used in search any more.');
		return $help;
	}
	
	function delete_op_permissions()
	{
		return PERMISSION_ADMIN;
	}
}

$page = new ApiPage();
$page->run('User Operations', CURRENT_VERSION);
		
?>


