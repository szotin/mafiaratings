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
			throw new Exc(get_label('Please enter [0].', get_label('Scoring normalizer name')));
		}

		check_name($name, get_label('Scoring normalizer name'));
		
		if (!is_null($club_id))
		{
			$query = new DbQuery('SELECT name FROM normalizers WHERE name = ? AND (club_id = ? OR club_id IS NULL)', $name, $club_id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM normalizers WHERE name = ? AND club_id IS NULL', $name);
		}
		
		if ($id > 0)
		{
			$query->add(' AND id <> ?', $id);
		}
		
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Scoring normalizer name'), $name));
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
		$normalizer = get_optional_param('normalizer', '{}');
		if (!is_string($normalizer))
		{
			$normalizer = json_encode($normalizer);
		}
		else
		{
			check_json($normalizer);
		}
		
		Db::begin();
		$this->check_name($name, $club_id, $league_id);
		
		Db::exec(get_label('scoring normalizer'), 'INSERT INTO normalizers (club_id, league_id, name, version) VALUES (?, ?, ?, NULL)', $club_id, $league_id, $name);
		list ($normalizer_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		if ($copy_id > 0)
		{
			$query = new DbQuery('SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $copy_id);
			if ($row = $query->next())
			{
				list ($normalizer) = $row;
			}
		}
		
		Db::exec(get_label('scoring normalizer'), 'INSERT INTO normalizer_versions (normalizer_id, version, normalizer) VALUES (?, 1, ?)', $normalizer_id, $normalizer);
		Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET version = 1', $club_id, $league_id, $name);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->version = 1;
		$log_details->normalizer = $normalizer;
		db_log(LOG_OBJECT_SCORING_NORMALIZER, 'created', $log_details, $normalizer_id, $club_id, $league_id);
		Db::commit();
		$this->response['normalizer_id'] = (int)$normalizer_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create scoring normalizer in the club. Or create a global scoring normalizer. "Global" means that it can be used in any club. Creating global scoring normalizer requires <em>admin</em> permissions.');
		$help->request_param('club_id', 'Club id.', 'global scoring normalizer is created.');
		$help->request_param('league_id', 'League id.', 'global scoring normalizer is created.');
		$help->request_param('name', 'Scoring normalizer name.');
		$help->request_param('copy_id', 'Id of the existing scoring normalizer to be used as an initial template.', 'parameter <q>normalizer</q> is used to create the new system.');
		api_normalizer_help($help->request_param('normalizer', 'Normalizer rules:', 'empty scoring normalizer is created.'));
		$help->response_param('normalizer_id', 'Id of the newly created scoring normalizer.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$normalizer_id = (int)get_required_param('normalizer_id');
		
		Db::begin();
		
		list ($club_id, $league_id, $old_name) = Db::record(get_label('scoring normalizer'), 'SELECT club_id, league_id, name FROM normalizers WHERE id = ?', $normalizer_id);
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
		$this->check_name($name, $club_id, $normalizer_id);
		
		$normalizer = get_optional_param('normalizer', NULL);
		if (!is_string($normalizer))
		{
			$normalizer = json_encode($normalizer);
		}
		else
		{
			check_json($normalizer);
		}
		
		$overwrite = false;
		list ($old_normalizer, $version) = Db::record(get_label('scoring normalizer'), 'SELECT v.normalizer, s.version FROM normalizers s JOIN normalizer_versions v ON v.normalizer_id = s.id AND v.version = s.version WHERE s.id = ?', $normalizer_id);
		if ($old_normalizer != $normalizer)
		{
			list ($usageCount) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments WHERE normalizer_id = ? AND normalizer_version = ? AND (flags & ' . TOURNAMENT_FLAG_FINISHED . ') <> 0', $normalizer_id, $version);
			$overwrite = ($usageCount <= 0);
		}
		else
		{
			$normalizer = NULL;
		}
		
		Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET name = ? WHERE id = ?', $name, $normalizer_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
		}
		
		if (!is_null($normalizer))
		{
			if ($overwrite)
			{
				Db::exec(get_label('scoring normalizer'), 'UPDATE normalizer_versions SET normalizer = ? WHERE normalizer_id = ? AND version = ?', $normalizer, $normalizer_id, $version);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->normalizer = $normalizer;
					db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
				}
			}
			else
			{
				++$version;
				Db::exec(get_label('scoring normalizer'), 'INSERT INTO normalizer_versions (normalizer_id, version, normalizer) VALUES (?, ?, ?)', $normalizer_id, $version, $normalizer);
				Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET version = ? WHERE id = ?', $version, $normalizer_id);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->normalizer = $normalizer;
					$log_details->version = $version;
					db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
				}
			}
		}
		Db::commit();
		
		$this->response['normalizer_version'] = (int)$version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change scoring normalizer. If some of the past events or tournaments are already using this scoring normalizer, the normalizer rules are not overwritten. We create a new version of normalizer. Old events contunie using the old version. All newly created events use the new version. Events that already exist but not finished yet will use the new version.');
		$help->request_param('normalizer_id', 'Scoring normalizer id. If the scoring normalizer is global (shared between clubs) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'Scoring normalizer name.', 'remains the same.');
		api_normalizer_help($help->request_param('normalizer', 'Normalizer rules:', 'remain the same.'));
		$help->response_param('version', 'Current scoring normalizer version.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$normalizer_id = (int)get_required_param('normalizer_id');
		
		list ($club_id, $league_id) = Db::record(get_label('Scoring normalizer'), 'SELECT club_id, league_id FROM normalizers WHERE id = ?', $normalizer_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, $club_id, $league_id);

		Db::begin();
		if (!is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
		{
			Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET league_id = NULL WHERE id = ?', $normalizer_id);
			$log_details = new stdClass();
			$log_details->league_id = NULL;
			db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
		}
		else if (!is_permitted(PERMISSION_LEAGUE_MANAGER, $league_id))
		{
			Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET club_id = NULL WHERE id = ?', $normalizer_id);
			$log_details = new stdClass();
			$log_details->club_id = NULL;
			db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
		}
		else
		{
			Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET version = NULL WHERE id = ?', $normalizer_id);
			Db::exec(get_label('scoring normalizer'), 'DELETE FROM normalizer_versions WHERE normalizer_id = ?', $normalizer_id);
			Db::exec(get_label('scoring normalizer'), 'DELETE FROM normalizers WHERE id = ?', $normalizer_id);
			db_log(LOG_OBJECT_SCORING_NORMALIZER, 'deleted', NULL, $normalizer_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete scoring normalizer.');
		$help->request_param('normalizer_id', 'Scoring normalizer id. If the scoring normalizer is global (shared between clubs) deleting requires <em>admin</em> permissions.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Scoring Normalizer Operations', CURRENT_VERSION);

?>