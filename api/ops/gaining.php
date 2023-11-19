<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';
require_once '../../include/names.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $league_id, $id = -1)
	{
		global $_profile;

		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('gaining system name')));
		}

		check_name($name, get_label('gaining system name'));
		
		if (!is_null($league_id))
		{
			$query = new DbQuery('SELECT name FROM gainings WHERE name = ? AND (league_id = ? OR league_id IS NULL)', $name, $league_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM gainings WHERE name = ? AND league_id IS NULL', $name);
		}
		
		if ($id > 0)
		{
			$query->add(' AND id <> ?', $id);
		}
		
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Gaining system name'), $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$league_id = (int)get_optional_param('league_id', -1);
		if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			$league_id = NULL;
		}
		
		$copy_id = (int)get_optional_param('copy_id', -1);
		$name = trim(get_required_param('name'));
		$gaining = get_optional_param('gaining', '{}');
		if (!is_string($gaining))
		{
			$gaining = json_encode($gaining);
		}
		else
		{
			$gaining = check_json($gaining);
		}
		
		Db::begin();
		$this->check_name($name, $league_id);
		
		Db::exec(get_label('gaining system'), 'INSERT INTO gainings (league_id, name, version) VALUES (?, ?, NULL)', $league_id, $name);
		list ($gaining_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		if ($copy_id > 0)
		{
			$query = new DbQuery('SELECT gaining FROM gaining_versions WHERE gaining_id = ? ORDER BY version DESC LIMIT 1', $copy_id);
			if ($row = $query->next())
			{
				list ($gaining) = $row;
			}
		}
		
		Db::exec(get_label('gaining system'), 'INSERT INTO gaining_versions (gaining_id, version, gaining) VALUES (?, 1, ?)', $gaining_id, $gaining);
		Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = 1 WHERE id = ?', $gaining_id);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->version = 1;
		$log_details->gaining = $gaining;
		db_log(LOG_OBJECT_GAINING_SYSTEM, 'created', $log_details, $gaining_id, NULL, $league_id);
		Db::commit();
		$this->response['gaining_id'] = (int)$gaining_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Create gaining system in the league. Or create a global gaining system. "Global" means that it can be used in any league. Creating global gaining system requires <em>admin</em> permissions.');
		$help->request_param('league_id', 'League id.', 'global gaining system is created.');
		$help->request_param('name', 'gaining system name.');
		$help->request_param('copy_id', 'Id of the existing gaining system to be used as an initial template. If set, the latest version of gaining rules from this system are copied to the new system.', 'parameter <q>gaining</q> is used to create the new system.');
		api_gaining_help($help->request_param('gaining', 'gaining rules:', 'empty gaining system is created.'));
		$help->response_param('gaining_id', 'Id of the newly created gaining system.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');
		
		Db::begin();
		
		list ($league_id, $old_name) = Db::record(get_label('gaining system'), 'SELECT league_id, name FROM gainings WHERE id = ?', $gaining_id);
		if (!is_null($league_id))
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		$name = trim(get_optional_param('name', $old_name));
		$this->check_name($name, $league_id, $gaining_id);
		
		$gaining = get_optional_param('gaining', NULL);
		if (!is_string($gaining))
		{
			$gaining = json_encode($gaining);
		}
		else
		{
			$gaining = check_json($gaining);
		}
		
		$overwrite = false;
		list ($old_gaining, $version) = Db::record(get_label('gaining system'), 'SELECT v.gaining, s.version FROM gainings s JOIN gaining_versions v ON v.gaining_id = s.id AND v.version = s.version WHERE s.id = ?', $gaining_id);
		if ($old_gaining != $gaining)
		{
			// count only complete series
			list ($usageCount) = Db::record(get_label('tournament series'), 'SELECT count(*) FROM series WHERE gaining_id = ? AND gaining_version = ? AND start_time + duration < UNIX_TIMESTAMP()', $gaining_id, $version);
			$overwrite = ($usageCount <= 0);
		}
		else
		{
			$gaining = NULL;
		}
		
		Db::exec(get_label('gaining system'), 'UPDATE gainings SET name = ? WHERE id = ?', $name, $gaining_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, NULL, $league_id);
		}
		
		if (!is_null($gaining))
		{
			if ($overwrite)
			{
				Db::exec(get_label('gaining system'), 'UPDATE gaining_versions SET gaining = ? WHERE gaining_id = ? AND version = ?', $gaining, $gaining_id, $version);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->gaining = $gaining;
					db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, NULL, $league_id);
				}
			}
			else
			{
				++$version;
				Db::exec(get_label('gaining system'), 'INSERT INTO gaining_versions (gaining_id, version, gaining) VALUES (?, ?, ?)', $gaining_id, $version, $gaining);
				Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = ? WHERE id = ?', $version, $gaining_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->gaining = $gaining;
					$log_details->version = $version;
					db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, NULL, $league_id);
				}
			}
		}
		Db::commit();
		
		$this->response['gaining_version'] = (int)$version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Change gaining system. If some of the past events or tournaments are already using this gaining system, the gaining rules are not overwritten. We create a new version of gaining rules. Old events contunie using old version. All newly created events use the new version. Events that already exist but not finished yet will use the new version.');
		$help->request_param('gaining_id', 'gaining system id. If the gaining system is global (shared between leagues) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'gaining system name.', 'remains the same.');
		api_gaining_help($help->request_param('gaining', 'gaining rules:', 'remain the same.'));
		$help->response_param('version', 'Current gaining system version.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');
		
		list ($league_id) = Db::record(get_label('gaining system'), 'SELECT league_id FROM gainings WHERE id = ?', $gaining_id);
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);

		Db::begin();
		Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = NULL WHERE id = ?', $gaining_id);
		Db::exec(get_label('gaining system'), 'DELETE FROM gaining_versions WHERE gaining_id = ?', $gaining_id);
		Db::exec(get_label('gaining system'), 'DELETE FROM gainings WHERE id = ?', $gaining_id);
		db_log(LOG_OBJECT_GAINING_SYSTEM, 'deleted', NULL, $gaining_id, NULL, $league_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER, 'Delete gaining system.');
		$help->request_param('gaining_id', 'Gaining system id. If the gaining system is global (shared between leagues) deleting requires <em>admin</em> permissions.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Gaining Operations', CURRENT_VERSION);

?>