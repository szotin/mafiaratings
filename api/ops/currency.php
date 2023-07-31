<?php

require_once '../../include/api.php';
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
		
		Db::begin();
		$names = new Names(0, get_label('currency name'), 'currencies');
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			throw new Exc(get_label('Please enter [0].', get_label('currency name')));
		}
		$pattern = get_required_param('pattern');
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
			
		Db::commit();
		$this->response['currency_id'] = $currency_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Create currency.');
		Names::help($help, 'Currency', false);
		$help->request_param('pattern', 'Display pattern where # stands for the sum. For example if the pattern is "$# aaa", the displayed value for 10000 is "$10,000 aaa".');
		$help->response_param('currency_id', 'Currency id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		check_permissions(PERMISSION_ADMIN);
		$currency_id = (int)get_required_param('currency_id');
		
		Db::begin();
		list($old_name_id, $old_pattern) = Db::record(get_label('currency'), 'SELECT name_id, pattern FROM currencies WHERE id = ?', $currency_id);
		$names = new Names(0, get_label('currency name'), 'currencies', $currency_id);
		$name_id = $names->get_id();
		if ($name_id <= 0)
		{
			$name_id = $old_name_id;
		}
		$pattern = get_optional_param('pattern', $old_pattern);
		if (strpos($pattern, '#') === false)
		{
			throw new Exc(get_label('Display pattern must contain \'#\' character.'));
		}
		
		Db::exec(get_label('currency'), 'UPDATE currencies SET name_id = ?, pattern = ? WHERE id = ?', $name_id, $pattern, $currency_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($pattern != $old_pattern)
			{
				$log_details->pattern = $pattern;
			}
			if ($name_id != $old_name_id)
			{
				$log_details->name = $names->to_string();
			}
			db_log(LOG_OBJECT_CURRENCY, 'changed', $log_details, $currency_id);
		}	
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Change currency.');
		
		$help->request_param('currency_id', 'Currency id.');
		Names::help($help, 'Currency', true);
		$help->request_param('pattern', 'Display pattern where # stands for the sum. For example if the pattern is "$# aaa", the displayed value for 10000 is "$10,000 aaa".', 'remains the same.');

		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		// todo: concider deleting it from the objects that use it
		check_permissions(PERMISSION_ADMIN);
		$currency_id = (int)get_required_param('currency_id');
		Db::begin();
		Db::exec(get_label('currency'), 'DELETE FROM currencies WHERE id = ?', $currency_id);
		$log_details = new stdClass();
		db_log(LOG_OBJECT_CURRENCY, 'deleted', $log_details, $currency_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_ADMIN, 'Delete currency.');
		$help->request_param('currency_id', 'Currency id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Currency Operations', CURRENT_VERSION);
		
?>
