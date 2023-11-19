<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';
require_once '../../include/names.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $club_id, $id = -1)
	{
		global $_profile;

		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('scoring system name')));
		}

		check_name($name, get_label('scoring system name'));
		
		if (!is_null($club_id))
		{
			$query = new DbQuery('SELECT name FROM scorings WHERE name = ? AND (club_id = ? OR club_id IS NULL)', $name, $club_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM scorings WHERE name = ? AND club_id IS NULL', $name);
		}
		
		if ($id > 0)
		{
			$query->add(' AND id <> ?', $id);
		}
		
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Scoring system name'), $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if ($league_id > 0)
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
			else
			{
				$league_id = NULL;
			}
		}
		else if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			$club_id = NULL;
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			$club_id = NULL;
			$league_id = NULL;
		}
		
		$copy_id = (int)get_optional_param('copy_id', -1);
		$name = trim(get_required_param('name'));
		$scoring = get_optional_param('scoring', '{}');
		if (!is_string($scoring))
		{
			$scoring = json_encode($scoring);
		}
		else
		{
			$scoring = check_json($scoring);
		}
		
		Db::begin();
		$this->check_name($name, $club_id);
		
		Db::exec(get_label('scoring system'), 'INSERT INTO scorings (club_id, league_id, name, version) VALUES (?, ?, ?, NULL)', $club_id, $league_id, $name);
		list ($scoring_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		if ($copy_id > 0)
		{
			$query = new DbQuery('SELECT scoring FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $copy_id);
			if ($row = $query->next())
			{
				list ($scoring) = $row;
			}
		}
		
		Db::exec(get_label('scoring system'), 'INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (?, 1, ?)', $scoring_id, $scoring);
		Db::exec(get_label('scoring system'), 'UPDATE scorings SET version = 1 WHERE id = ?', $scoring_id);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->version = 1;
		$log_details->scoring = $scoring;
		db_log(LOG_OBJECT_SCORING_SYSTEM, 'created', $log_details, $scoring_id, $club_id, $league_id);
		Db::commit();
		$this->response['scoring_id'] = (int)$scoring_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create scoring system in the club. Or create a global scoring system. "Global" means that it can be used in any club. Creating global scoring system requires <em>admin</em> permissions.');
		$help->request_param('club_id', 'Club id.', 'global scoring system is created.');
		$help->request_param('league_id', 'League id.', 'global scoring system is created.');
		$help->request_param('name', 'Scoring system name.');
		$help->request_param('copy_id', 'Id of the existing scoring system to be used as an initial template. If set, the latest version of scoring rules from this system are copied to the new system.', 'parameter <q>scoring</q> is used to create the new system.');
		api_scoring_help($help->request_param('scoring', 'Scoring rules:', 'empty scoring system is created.'));
		$help->response_param('scoring_id', 'Id of the newly created scoring system.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$scoring_id = (int)get_required_param('scoring_id');
		
		Db::begin();
		
		list ($club_id, $league_id, $old_name) = Db::record(get_label('scoring system'), 'SELECT club_id, league_id, name FROM scorings WHERE id = ?', $scoring_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if (!is_null($league_id))
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		else if (!is_null($league_id))
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		$name = trim(get_optional_param('name', $old_name));
		$this->check_name($name, $club_id, $scoring_id);
		
		$scoring = get_optional_param('scoring', NULL);
		if (!is_string($scoring))
		{
			$scoring = json_encode($scoring);
		}
		else
		{
			$scoring = check_json($scoring);
		}
		
		$overwrite = false;
		list ($old_scoring, $version) = Db::record(get_label('scoring system'), 'SELECT v.scoring, s.version FROM scorings s JOIN scoring_versions v ON v.scoring_id = s.id AND v.version = s.version WHERE s.id = ?', $scoring_id);
		if ($old_scoring != $scoring)
		{
			list ($usageCount) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . EVENT_FLAG_FINISHED . ') <> 0', $scoring_id, $version);
			if ($usageCount <= 0)
			{
				list ($usageCount) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments WHERE scoring_id = ? AND scoring_version = ? AND (flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0', $scoring_id, $version);
			}
			$overwrite = ($usageCount <= 0);
		}
		else
		{
			$scoring = NULL;
		}
		
		Db::exec(get_label('scoring system'), 'UPDATE scorings SET name = ? WHERE id = ?', $name, $scoring_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id, $league_id);
		}
		
		if (!is_null($scoring))
		{
			if ($overwrite)
			{
				Db::exec(get_label('scoring system'), 'UPDATE scoring_versions SET scoring = ? WHERE scoring_id = ? AND version = ?', $scoring, $scoring_id, $version);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->scoring = $scoring;
					db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id, $league_id);
				}
			}
			else
			{
				++$version;
				Db::exec(get_label('scoring system'), 'INSERT INTO scoring_versions (scoring_id, version, scoring) VALUES (?, ?, ?)', $scoring_id, $version, $scoring);
				Db::exec(get_label('scoring system'), 'UPDATE scorings SET version = ? WHERE id = ?', $version, $scoring_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->scoring = $scoring;
					$log_details->version = $version;
					db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id, $league_id);
				}
			}
		}
		Db::commit();
		
		$this->response['scoring_version'] = (int)$version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change scoring system. If some of the past events or tournaments are already using this scoring system, the scoring rules are not overwritten. We create a new version of scoring rules. Old events contunie using old version. All newly created events use the new version. Events that already exist but not finished yet will use the new version.');
		$help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'Scoring system name.', 'remains the same.');
		api_scoring_help($help->request_param('scoring', 'Scoring rules:', 'remain the same.'));
		$help->response_param('version', 'Current scoring system version.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$scoring_id = (int)get_required_param('scoring_id');
		
		list ($club_id, $league_id) = Db::record(get_label('scoring system'), 'SELECT club_id, league_id FROM scorings WHERE id = ?', $scoring_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, $club_id, $league_id);

		Db::begin();
		if (!is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			Db::exec(get_label('scoring system'), 'UPDATE scorings SET league_id = NULL WHERE id = ?', $scoring_id);
			$log_details = new stdClass();
			$log_details->league_id = NULL;
			db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id, $league_id);
		}
		else if (!is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
		{
			Db::exec(get_label('scoring system'), 'UPDATE scorings SET club_id = NULL WHERE id = ?', $scoring_id);
			$log_details = new stdClass();
			$log_details->club_id = NULL;
			db_log(LOG_OBJECT_SCORING_SYSTEM, 'changed', $log_details, $scoring_id, $club_id, $league_id);
		}
		else
		{
			Db::exec(get_label('scoring system'), 'UPDATE scorings SET version = NULL WHERE id = ?', $scoring_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_versions WHERE scoring_id = ?', $scoring_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scorings WHERE id = ?', $scoring_id);
			db_log(LOG_OBJECT_SCORING_SYSTEM, 'deleted', NULL, $scoring_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete scoring system.');
		$help->request_param('scoring_id', 'Scoring system id. If the scoring system is global (shared between clubs) deleting requires <em>admin</em> permissions.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Scoring Operations', CURRENT_VERSION);

?>