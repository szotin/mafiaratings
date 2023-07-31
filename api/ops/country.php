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
	function get_currency_id()
	{
		$currency_id = get_optional_param('currency_id', 0);
		if ($currency_id > 0)
		{
			return $currency_id;
		}
		
		$names = new Names(0, get_label('currency name'), 'currencies', 0, NULL, 'currency_name');
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			return NULL;
		}
		
		$pattern = get_required_param('currency_pattern');
		if (strpos($pattern, '#') === false)
		{
			throw new Exc(get_label('Display pattern must contain \'#\' character.'));
		}
		
		Db::exec(get_label('currency'), 'INSERT INTO currencies (name_id, pattern) VALUES (?, ?)', $name_id, $pattern);
		list ($currency_id) = Db::record(get_label('currency'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $names->to_string();
		$log_details->pattern = $pattern;
		db_log(LOG_OBJECT_CURRENCY, 'created', $log_details, $currency_id);
		return $currency_id;
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$code = get_required_param('code');
		if ($code == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Country code')));
		}
		
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		$flags = $confirm ? 0 : COUNTRY_FLAG_NOT_CONFIRMED;
		
		Db::begin();
		$names = new Names(0, get_label('country name'), 'countries');
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			throw new Exc(get_label('Please enter [0].', get_label('country name')));
		}
		
		$query = new DbQuery('SELECT id FROM countries WHERE code = ?', $code);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		$currency_id = $this->get_currency_id();
		Db::exec(get_label('country'), 'INSERT INTO countries (name_id, code, flags, currency_id) VALUES (?, ?, ?, ?)', $name_id, $code, $flags, $currency_id);
		list ($country_id) = Db::record(get_label('country'), 'SELECT LAST_INSERT_ID()');
		
		foreach ($names->names as $n)
		{
			Db::exec(get_label('country name'), 'INSERT IGNORE INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $n->name);
		}
		
		$log_details = new stdClass();
		$log_details->name = $names->to_string();
		$log_details->code = $code;
		$log_details->flags = $flags;
		db_log(LOG_OBJECT_COUNTRY, 'created', $log_details, $country_id);
			
		Db::commit();
		$this->response['country_id'] = $country_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Extend the event to a longer time. Event can be extended during 8 hours after it ended.');
		Names::help($help, 'Country', false);
		$help->request_param('code', 'Two letter country code.');
		$help->request_param('currency_id', 'National currency_id.', 'currency_name and currency_pattern params are checked. If they are set, new currency is created. If not, currency is set to null.');
		Names::help($help, 'Currency', false, 'currency_name');
		$help->request_param('currency_pattern', 'Display pattern for creating currency where # stands for the sum. For example if the pattern is "$# aaa", the displayed value for 10000 is "$10,000 aaa".', 'currency_id is used. If currency_id is also not set, currency is set to null.');
		$help->request_param('confirm', 'If it is set and non zero, the country is marked as confirmed by admin.', 'country is not confirmed.');

		$help->response_param('country_id', 'Country id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$country_id = (int)get_required_param('country_id');
		$confirm = (isset($_REQUEST['confirm']) && $_REQUEST['confirm']);
		
		Db::begin();
		list($old_name_id, $old_code, $old_currency_id) = Db::record(get_label('country'), 'SELECT name_id, code, currency_id FROM countries WHERE id = ?', $country_id);
		
		$code = get_optional_param('code', $old_code);
		if (strlen($code) != 2)
		{
			throw new Exc(get_label('Country code must be two letters.'));
		}
		$query = new DbQuery('SELECT id FROM countries WHERE code = ? AND id <> ?', $code, $country_id);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Country code'), $code));
		}
		
		$names = new Names(0, get_label('country name'), 'countries', $country_id);
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			$name_id = $old_name_id;
		}
		
		if ($name_id != $old_name_id)
		{
			Db::exec(get_label('country name'), 'DELETE FROM country_names WHERE country_id = ? AND name IN (SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id WHERE c.id = ?)', $country_id, $country_id);
			foreach ($names->names as $n)
			{
				Db::exec(get_label('country name'), 'INSERT IGNORE INTO country_names (country_id, name) VALUES (?, ?)', $country_id, $n->name);
			}
		}
		
		$currency_id = $this->get_currency_id();
		$op = 'changed';
		$query = new DbQuery('UPDATE countries SET name_id = ?, code = ?, currency_id = ?', $name_id, $code, $currency_id);
		if ($confirm)
		{
			$query->add(', flags = (flags & ~' . COUNTRY_FLAG_NOT_CONFIRMED . ')');
			$op = 'confirmed';
		}
		$query->add(' WHERE id = ?', $country_id);
		
		$query->exec(get_label('country'));
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($code != $old_code)
			{
				$log_details->code = $code;
			}
			if ($name_id != $old_name_id)
			{
				$log_details->name = $names->to_string();
			}
			if ($currency_id != $old_currency_id)
			{
				$log_details->currency_id = $currency_id;
			}
			db_log(LOG_OBJECT_COUNTRY, $op, $log_details, $country_id);
		}	
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Change country.');
		
		$help->request_param('country_id', 'Country id.');
		Names::help($help, 'Country', true);
		$help->request_param('code', 'Two letter country code.', 'remains the same.');
		$help->request_param('currency_id', 'National currency_id.', 'currency_name and currency_pattern params are checked. If they are set, new currency is created. If not, currency remains the same.');
		Names::help($help, 'Currency', false, 'currency_name');
		$help->request_param('currency_pattern', 'Display pattern for creating currency where # stands for the sum. For example if the pattern is "$# aaa", the displayed value for 10000 is "$10,000 aaa".', 'currency_id is used. If currency_id is also not set, currency is set to null.');
		$help->request_param('confirm', 'If it is set and non zero, the country is marked as confirmed by admin.', 'country confirmation status remains the same.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_ADMIN);
		$country_id = (int)get_required_param('country_id');
		$repl_id = (int)get_required_param('repl_id');
		$keep_name = (isset($_REQUEST['keep_name']) && $_REQUEST['keep_name']);
		
		Db::begin();
		
		list($new_name) = Db::record(get_label('country'), 'SELECT n.name FROM countries c JOIN names n ON n.id = c.name_id AND (n.langs & ' . LANG_ENGLISH . ') <> 0 WHERE c.id = ?', $repl_id);
		Db::exec(get_label('city'), 'UPDATE cities SET country_id = ? WHERE country_id = ?', $repl_id, $country_id);
		if ($keep_name)
		{
			Db::exec(get_label('country'), 'UPDATE country_names n1 SET n1.country_id = ? WHERE n1.country_id = ? AND n1.name NOT IN (SELECT * FROM (SELECT n2.name FROM country_names n2 WHERE n2.country_id = ?) as t)', $repl_id, $country_id, $repl_id);
		}
		Db::exec(get_label('country'), 'DELETE FROM country_names WHERE country_id = ?', $country_id);
		Db::exec(get_label('country'), 'DELETE FROM countries WHERE id = ?', $country_id);
		
		$log_details = new stdClass();
		$log_details->replaced_by = new stdClass();
		$log_details->replaced_by->country = $new_name;
		$log_details->replaced_by->country_id = $repl_id;
		db_log(LOG_OBJECT_COUNTRY, 'deleted', $log_details, $country_id);
		Db::commit();
		
		if ($country_id == $_profile->country_id)
		{
			$_profile->country_id = $repl_id;
		}
		$_profile->update_clubs();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Delete country.');
		$help->request_param('country_id', 'Country id.');
		$help->request_param('repl_id', 'Country id that is used as a replacement. The country is replaced by this country everywhere it is used.');
		$help->request_param('keep_name', 'If set and non-zero, the country names are still used as alternative names to the replacement country.', 'country name is not used in search any more.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Country Operations', CURRENT_VERSION);
		
?>
