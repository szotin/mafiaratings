<?php

require_once '../../include/api.php';
require_once '../../include/rules.php';

define('CURRENT_VERSION', 0);

// Named rules of a club, of a league or of the site - the table rules, see db/alter212.sql. The
// rules of a club and of the site are rules codes; the rules of a league are filters, put over
// the default rules of the club that picks them. The default rules of a club are clubs.rules
// and are changed here too; those of a league are its filter, changed by api/ops/league.php; those
// of the site are the row of the site with no name, changed here by its id like any other row.
class ApiPage extends OpsApiPageBase
{
	// Who may change the rules of this owner. The site's rules are the admin's.
	private static function check_owner($club_id, $league_id)
	{
		if ($club_id != NULL)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else if ($league_id != NULL)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
	}

	// What is stored: a filter for a league, a code for the others.
	private static function rules_value($league_id, $rules)
	{
		if ($league_id != NULL)
		{
			return rules_filter_to_json($rules);
		}
		if (is_string($rules))
		{
			$rules_code = upgrade_rules_code($rules);
			if (is_valid_rules_code($rules_code))
			{
				return $rules_code;
			}
		}
		return rules_code_from_object($rules);
	}

	// A name is needed, it is not the name of the owner (that is what its default rules are
	// called), and the owner has no other rules by that name.
	private static function check_name($club_id, $league_id, $name, $rules_id = 0)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('Rules name')));
		}

		if ($club_id != NULL)
		{
			list($owner_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);
			$query = new DbQuery('SELECT id FROM rules WHERE club_id = ? AND name = ? AND id <> ?', $club_id, $name, $rules_id);
		}
		else if ($league_id != NULL)
		{
			list($owner_name) = Db::record(get_label('league'), 'SELECT name FROM leagues WHERE id = ?', $league_id);
			$query = new DbQuery('SELECT id FROM rules WHERE league_id = ? AND name = ? AND id <> ?', $league_id, $name, $rules_id);
		}
		else
		{
			$owner_name = PRODUCT_NAME;
			$query = new DbQuery('SELECT id FROM rules WHERE club_id IS NULL AND league_id IS NULL AND name = ? AND id <> ?', $name, $rules_id);
		}

		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Rules name'), $name));
		}
		if (mb_strtolower($name, 'UTF-8') == mb_strtolower($owner_name, 'UTF-8'))
		{
			throw new Exc(get_label('Rules name can not be the same as club name [0].', $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$club_id = (int)get_optional_param('club_id', 0);
		$league_id = (int)get_optional_param('league_id', 0);
		$club_id = $club_id > 0 ? $club_id : NULL;
		$league_id = ($club_id == NULL && $league_id > 0) ? $league_id : NULL;
		self::check_owner($club_id, $league_id);

		$rules_name = trim(get_required_param('name'));
		$rules = self::rules_value($league_id, get_optional_param('rules', $league_id != NULL ? '{}' : get_site_rules()));

		Db::begin();
		self::check_name($club_id, $league_id, $rules_name);
		Db::exec(get_label('rules'), 'INSERT INTO rules (club_id, league_id, name, rules) VALUES (?, ?, ?, ?)', $club_id, $league_id, $rules_name, $rules);
		list ($rules_id) = Db::record(get_label('rules'), 'SELECT LAST_INSERT_ID()');

		$log_details = new stdClass();
		$log_details->id = $rules_id;
		$log_details->name = $rules_name;
		$log_details->code = $rules;
		db_log(LOG_OBJECT_RULES, 'created', $log_details, $rules_id, $club_id, $league_id);
		Db::commit();

		$this->response['rules_id'] = $rules_id;
	}

	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_ADMIN, 'Create named game rules of a club, of a league, or of the site.');
		$help->request_param('club_id', 'Club id. The rules are the club\'s.', 'not the club\'s.');
		$help->request_param('league_id', 'League id. The rules are the league\'s, and they are a filter rather than rules: a club that picks them plays its own default rules put through the filter. Not used when club_id is set.', 'with no club_id either, the rules are the site\'s; only an admin can create those.');
		$help->request_param('name', 'Rules name. Must be unique for its owner, and different from the owner\'s name.');

		$rules_param = $help->request_param('rules', 'Rules of a club or of the site: either a rules code in the form "' . DEFAULT_RULES . '" or json object of the following format. Rules of a league: a filter, as in api/ops/league.php.', 'the default rules of the site; an empty filter for a league.');
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

		$last_rule = (int)get_optional_param('last_edited_rule', -1);
		if ($last_rule >= 0)
		{
			$_SESSION['last_edited_rule'] = $last_rule;
		}

		Db::begin();
		if ($rules_id > 0)
		{
			list ($club_id, $league_id, $old_rules_name, $old_rules) = Db::record(get_label('rules'), 'SELECT club_id, league_id, name, rules FROM rules WHERE id = ?', $rules_id);
			self::check_owner($club_id, $league_id);

			$rules = self::rules_value($league_id, get_optional_param('rules', $old_rules));
			$rules_name = $old_rules_name;
			// The default rules of the site keep having no name.
			if ($old_rules_name != '')
			{
				$rules_name = trim(get_optional_param('name', $old_rules_name));
				if ($rules_name != $old_rules_name)
				{
					self::check_name($club_id, $league_id, $rules_name, $rules_id);
				}
			}

			Db::exec(get_label('rules'),  'UPDATE rules SET name = ?, rules = ? WHERE id = ?', $rules_name, $rules, $rules_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				if ($rules_name != $old_rules_name)
				{
					$log_details->name = $rules_name;
				}
				if ($rules != $old_rules)
				{
					$log_details->code = $rules;
				}
				db_log(LOG_OBJECT_RULES, 'changed', $log_details, $rules_id, $club_id, $league_id);
			}
		}
		else if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			list ($old_rules_code) = Db::record(get_label('club'), 'SELECT rules FROM clubs WHERE id = ?', $club_id);
			$rules_code = self::rules_value(NULL, get_optional_param('rules', $old_rules_code));
			Db::exec(get_label('club'),  'UPDATE clubs SET rules = ? WHERE id = ?', $rules_code, $club_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->code = $rules_code;
				db_log(LOG_OBJECT_CLUB, 'rules changed', $log_details, $club_id, $club_id);
			}
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', get_label('rules')));
		}
		Db::commit();
	}

	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_ADMIN, 'Change named game rules, or the default rules of a club.');
		$help->request_param('rules_id', 'Rules id. The default rules of the site are changed by their id too; they keep having no name.', '"club_id" must be set.');
		$help->request_param('club_id', 'Club id. The default rules of this club are changed; "name" is not used.', '"rules_id" must be set.');
		$help->request_param('name', 'New rules name.', 'remains the same.');
		$rules_param = $help->request_param('rules', 'New rules. Either rules code in the form "' . DEFAULT_RULES . '" or json object of the following format. A filter for the rules of a league.', 'remains the same.');
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
		list ($club_id, $league_id, $rules_name) = Db::record(get_label('rules'), 'SELECT club_id, league_id, name FROM rules WHERE id = ?', $rules_id);
		self::check_owner($club_id, $league_id);
		if ($rules_name == '')
		{
			throw new Exc(get_label('The default rules can not be deleted.'));
		}

		Db::exec(get_label('rules'), 'DELETE FROM rules WHERE id = ?', $rules_id);
		db_log(LOG_OBJECT_RULES, 'deleted', NULL, $rules_id, $club_id, $league_id);
		Db::commit();
	}

	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_ADMIN, 'Delete named game rules of a club, of a league, or of the site. The default rules of the site can not be deleted.');
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