<?php

require_once '../../include/api.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$rules_name = trim(get_required_param('name'));
		$rules_code = upgrade_rules_code(get_required_param('rules', ''));
		if (!is_valid_rules_code($rules_code))
		{
			$rules_code = rules_code_from_object($rules_code);
		}
		
		Db::begin();
		
		$query = new DbQuery('SELECT rules FROM club_rules WHERE club_id = ? AND name = ?', $club_id, $rules_name);
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Rules name'), $rules_name));
		}
		
		$query = new DbQuery('SELECT id FROM clubs WHERE name = ?', $club_id, $rules_name);
		if ($query->next())
		{
			throw new Exc(get_label('Rules name can not be the same as club name [0].', $rules_name));
		}
		
		Db::exec(get_label('rules'), 'INSERT INTO club_rules (rules, club_id, name) VALUES (?, ?, ?)', $rules_code, $club_id, $rules_name);
		list ($rules_id) = Db::record(get_label('rules'), 'SELECT LAST_INSERT_ID()');
			
		$log_details = new stdClass();
		$log_details->id = $rules_id;
		$log_details->name = $rules_name;
		$log_details->code = $rules_code;
		db_log(LOG_OBJECT_RULES, 'created', $log_details, $rules_id, $club_id);
		Db::commit();
		
		$this->response['rules_id'] = $rules_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create custom game rules in a club.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Rules name. Must be unique in the club. Must be different from the club name.');
		
		$rules_param = $help->request_param('rules', 'Rules. Either rules code in the form "' . default_rules_code() . '" or json object of the following format.');
		api_rules_help($rules_param);
		
		$help->response_param('rules_id', 'Newly created rules id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$rules_id = (int)get_optional_param('rules_id', 0);
		$club_id = (int)get_optional_param('club_id', 0);
		$league_id = (int)get_optional_param('league_id', 0);

		$last_rule = (int)get_optional_param('last_edited_rule', -1);
		if ($last_rule >= 0)
		{
			$_SESSION['last_edited_rule'] = $last_rule;
		}
		
		Db::begin();
		
		if ($rules_id > 0)
		{
			list ($club_id, $old_rules_name, $old_rules_code) = Db::record(get_label('rules'), 'SELECT club_id, name, rules FROM club_rules WHERE id = ?', $rules_id);
			$rules_name = get_optional_param('name', $old_rules_name);
		}
		else if ($club_id <= 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		else if ($league_id > 0)
		{
			list ($old_rules_code) = Db::record(get_label('league'), 'SELECT rules FROM league_clubs WHERE club_id = ? AND league_id = ?', $club_id, $league_id);
		}
		else
		{
			list ($old_rules_code) = Db::record(get_label('club'), 'SELECT rules FROM clubs WHERE id = ?', $club_id);
		}
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$rules_code = get_optional_param('rules', $old_rules_code);
		
		if ($rules_id > 0)
		{
			Db::exec(get_label('rules'),  'UPDATE club_rules SET name = ?, rules = ? WHERE id = ?', $rules_name, $rules_code, $rules_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				if ($rules_name != $old_rules_name)
				{
					$log_details->name = $rules_name;
				}
				if ($rules_code != $old_rules_code)
				{
					$log_details->code = $rules_code;
				}
				db_log(LOG_OBJECT_RULES, 'changed', $log_details, $rules_id, $club_id);
			}
		}
		else if ($league_id > 0)
		{
			Db::exec(get_label('league'),  'UPDATE league_clubs SET rules = ? WHERE club_id = ? AND league_id = ?', $rules_code, $club_id, $league_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->code = $rules_code;
				$log_details->league_id = $league_id;
				db_log(LOG_OBJECT_CLUB, 'league rules changed', $log_details, $club_id, $club_id, $league_id);
			}
		}
		else
		{
			Db::exec(get_label('club'),  'UPDATE clubs SET rules = ? WHERE id = ?', $rules_code, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->code = $rules_code;
				db_log(LOG_OBJECT_CLUB, 'rules changed', $log_details, $club_id, $club_id);
			}
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change custom game rules in a club.');
		$help->request_param('rules_id', 'Rules id.', 'default club rules are updated, "name" is not used.');
		$help->request_param('club_id', 'Club id.', '"rules_id" must be set.');
		$help->request_param('league_id', 'League id. When both club_id and league_id are set, the default rules are set for this club in the league.', 'default rules for the club itself are set.');
		$help->request_param('name', 'New rules name.', 'remains the same.');
		$rules_param = $help->request_param('rules', 'New rules. Either rules code in the form "' . default_rules_code() . '" or json object of the following format.', 'remains the same.');
		api_rules_help($rules_param);
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$rules_id = (int)get_required_param('rules_id');
		
		Db::begin();
		
		list ($club_id) = Db::record(get_label('rules'), 'SELECT club_id FROM club_rules WHERE id = ?', $rules_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('rules'), 'DELETE FROM club_rules WHERE id = ?', $rules_id);
		db_log(LOG_OBJECT_RULES, 'deleted', NULL, $rules_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete custom game rules in a club.');
		$help->request_param('rules_id', 'Rules id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// set_last_rule
	//-------------------------------------------------------------------------------------------------------
	function set_last_rule_op()
	{
		$_SESSION['last_edited_rule'] = (int)get_required_param('rule');
	}
	
	function set_last_rule_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Set last edited rule number for this session.');
		$help->request_param('rule', 'Rule number.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Rules Operations', CURRENT_VERSION);

?>