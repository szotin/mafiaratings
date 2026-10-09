<?php

require_once '../../include/api.php';
require_once '../../include/scoring.php';
require_once '../../include/systems.php';
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
			$normalizer = check_json($normalizer);
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
		
		list ($current_version) = Db::record(get_label('scoring normalizer'), 'SELECT version FROM normalizers WHERE id = ?', $normalizer_id);
		
		// The version to write. An existing one is rewritten in place - that is what Save does,
		// and the editor warns first when something already runs on it. One above the highest
		// there is gets created and becomes the current version - that is the New version
		// button, which only picks the number and leaves the writing to the save.
		$versions = get_system_versions('normalizer', $normalizer_id);
		$version = (int)get_optional_param('normalizer_version', $current_version);
		$exists = in_array($version, $versions, true);
		if (!$exists && $version != max($versions) + 1)
		{
			throw new Exc(get_label('Unknown [0]', get_label('version')));
		}
		
		// What is in it now - or in the current version when the one asked for is about to be
		// created, so that a request naming only a new version gets a copy of the current one.
		list ($old_normalizer) = Db::record(get_label('scoring normalizer'),
			'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?',
			$normalizer_id, $exists ? $version : $current_version);
		
		$normalizer = get_optional_param('normalizer', $old_normalizer);
		if (!is_string($normalizer))
		{
			$normalizer = json_encode($normalizer);
		}
		else
		{
			$normalizer = check_json($normalizer);
		}
		
		// Rewriting a version with what it already holds leaves it alone. Creating one is a
		// change even when its contents are a copy.
		$changed = (!$exists || $old_normalizer != $normalizer);
		
		Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET name = ? WHERE id = ?', $name, $normalizer_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
		}
		
		if ($changed)
		{
			if ($exists)
			{
				Db::exec(get_label('scoring normalizer'), 'UPDATE normalizer_versions SET normalizer = ? WHERE normalizer_id = ? AND version = ?', $normalizer, $normalizer_id, $version);
				if (Db::affected_rows() > 0)
				{
					$log_details = new stdClass();
					$log_details->normalizer = $normalizer;
					db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
					
					// A tournament normalized by what was just rewritten has to be counted again.
					Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = flags & ~' . TOURNAMENT_FLAG_FINISHED . ' WHERE normalizer_id = ? AND normalizer_version = ?', $normalizer_id, $version);
					Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE normalizer_id = ? AND normalizer_version = ?', $normalizer_id, $version);
				}
			}
			else
			{
				$current_version = $version;
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
		
		// Which version the change landed on, and which one is current now - the same thing
		// unless an older version was rewritten in place.
		$this->response['normalizer_version'] = (int)$version;
		$this->response['current_version'] = (int)$current_version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change a scoring normalizer. Which version is written is up to the <em>normalizer_version</em> parameter: an existing one is rewritten where it is, and every tournament normalized by it is marked to be counted again; one number above the highest version there is creates that version and makes it the current one, leaving the tournaments on the older versions as they were.');
		$help->request_param('normalizer_id', 'Scoring normalizer id. If the scoring normalizer is global (shared between clubs) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'Scoring normalizer name.', 'remains the same.');
		api_normalizer_help($help->request_param('normalizer', 'Normalizer rules:', 'remain the same.'));
		$help->request_param('normalizer_version', 'The version to write. An existing version is rewritten where it is, and every tournament normalized by it is counted again. One number above the highest version there is creates that version and makes it current. Any other number is refused.', 'is the current version.');
		$help->response_param('normalizer_version', 'The version the change landed on.');
		$help->response_param('current_version', 'Current scoring normalizer version. The same as <em>normalizer_version</em> unless an older version was rewritten where it is.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_version
	//-------------------------------------------------------------------------------------------------------
	function delete_version_op()
	{
		$normalizer_id = (int)get_required_param('normalizer_id');
		$version = (int)get_required_param('normalizer_version');
		
		Db::begin();
		
		list ($club_id, $league_id, $current_version) = Db::record(get_label('scoring normalizer'), 'SELECT club_id, league_id, version FROM normalizers WHERE id = ?', $normalizer_id);
		if (is_null($club_id))
		{
			if (is_null($league_id))
			{
				check_permissions(PERMISSION_ADMIN);
			}
			else
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		else
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			if (!is_null($league_id))
			{
				check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			}
		}
		
		$versions = get_system_versions('normalizer', $normalizer_id);
		if (!in_array($version, $versions, true))
		{
			throw new Exc(get_label('Unknown [0]', get_label('version')));
		}
		
		// A system is its versions, so the last one left cannot go.
		if (count($versions) < 2)
		{
			throw new Exc(get_label('The only version of a system can not be deleted.'));
		}
		
		// Neither can one a tournament is normalized by. The page does not offer it and the foreign keys
		// would refuse it, but the answer should say what the matter is.
		$used = get_used_system_versions('normalizer', $normalizer_id, array('tournaments'));
		if (isset($used[$version]))
		{
			throw new Exc(get_label('This version is used in competitions and can not be deleted.'));
		}
		
		// The system points at its current version, so that has to move off it first.
		if ($version == $current_version)
		{
			$current_version = max(array_diff($versions, array($version)));
			Db::exec(get_label('scoring normalizer'), 'UPDATE normalizers SET version = ? WHERE id = ?', $current_version, $normalizer_id);
		}
		
		Db::exec(get_label('scoring normalizer'), 'DELETE FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $normalizer_id, $version);
		
		// Nothing is on this version, so nothing is cached for it either - except that its
		// number comes free again, and a row left behind would be read as the next one's.
		Db::exec(get_label('score'), 'DELETE FROM tournament_scores_cache WHERE normalizer_id = ? AND normalizer_version = ?', $normalizer_id, $version);
		
		$log_details = new stdClass();
		$log_details->deleted_version = $version;
		db_log(LOG_OBJECT_SCORING_NORMALIZER, 'changed', $log_details, $normalizer_id, $club_id, $league_id);
		
		Db::commit();
		
		$this->response['current_version'] = (int)$current_version;
	}
	
	function delete_version_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete one version of a scoring normalizer. Only a version no tournament is normalized by, and never the only version there is. When the version deleted is the current one, the highest of the rest takes its place.');
		$help->request_param('normalizer_id', 'Scoring normalizer id. If the scoring normalizer is global (shared between clubs) deleting requires <em>admin</em> permissions.');
		$help->request_param('normalizer_version', 'The version to delete.');
		$help->response_param('current_version', 'Current scoring normalizer version after the deletion.');
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