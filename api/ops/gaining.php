<?php

require_once '../../include/api.php';
require_once '../../include/gaining.php';
require_once '../../include/systems.php';
require_once '../../include/names.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	// A gaining system belongs to a league, or to a club, or to neither - a system owned by
	// nobody is global and usable everywhere. Returns the owner ids to store, after checking
	// that the caller is allowed to own a system there.
	private function check_owner(&$league_id, &$club_id)
	{
		if ($league_id > 0 && $club_id > 0)
		{
			throw new Exc(get_label('A gaining system belongs either to a league or to a club, but not to both.'));
		}

		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			$league_id = NULL;
		}
		else if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			$club_id = NULL;
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			$league_id = NULL;
			$club_id = NULL;
		}
	}

	private function check_name($name, $league_id, $club_id, $id = -1)
	{
		global $_profile;

		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('gaining system name')));
		}

		check_name($name, get_label('gaining system name'));

		// The name has to be unique among the systems this owner can see - their own plus the
		// global ones.
		$query = new DbQuery('SELECT name FROM gainings WHERE name = ? AND ((league_id IS NULL AND club_id IS NULL)', $name);
		if (!is_null($league_id))
		{
			$query->add(' OR league_id = ?', $league_id);
		}
		if (!is_null($club_id))
		{
			$query->add(' OR club_id = ?', $club_id);
		}
		$query->add(')');

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
		$club_id = (int)get_optional_param('club_id', -1);
		$this->check_owner($league_id, $club_id);

		$copy_id = (int)get_optional_param('copy_id', -1);
		$name = trim(get_required_param('name'));
		$gaining = get_optional_param('gaining', '{}');
		if (is_string($gaining))
		{
			$gaining = json_decode($gaining);
		}
		check_gaining($gaining);
		$function_flags = get_gaining_function_flags($gaining);
		$gaining = json_encode($gaining);
		
		Db::begin();
		$this->check_name($name, $league_id, $club_id);

		Db::exec(get_label('gaining system'), 'INSERT INTO gainings (league_id, club_id, name, version) VALUES (?, ?, ?, NULL)', $league_id, $club_id, $name);
		list ($gaining_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		if ($copy_id > 0)
		{
			$query = new DbQuery('SELECT gaining, functions FROM gaining_versions WHERE gaining_id = ? ORDER BY version DESC LIMIT 1', $copy_id);
			if ($row = $query->next())
			{
				list ($gaining, $function_flags) = $row;
			}
		}
		
		Db::exec(get_label('gaining system'), 'INSERT INTO gaining_versions (gaining_id, version, gaining, functions) VALUES (?, 1, ?, ?)', $gaining_id, $gaining, $function_flags);
		Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = 1 WHERE id = ?', $gaining_id);
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->version = 1;
		$log_details->gaining = $gaining;
		db_log(LOG_OBJECT_GAINING_SYSTEM, 'created', $log_details, $gaining_id, $club_id, $league_id);
		Db::commit();
		$this->response['gaining_id'] = (int)$gaining_id;
	}

	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Create gaining system in a league or in a club. Or create a global gaining system. "Global" means that it can be used anywhere. Creating global gaining system requires <em>admin</em> permissions.');
		$help->request_param('league_id', 'League id.', 'global gaining system is created.');
		$help->request_param('club_id', 'Club id. Pass either this or <q>league_id</q>, not both.', 'global gaining system is created.');
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
		
		list ($league_id, $club_id, $old_name) = Db::record(get_label('gaining system'), 'SELECT league_id, club_id, name FROM gainings WHERE id = ?', $gaining_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
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
		$this->check_name($name, $league_id, $club_id, $gaining_id);
		
		list ($current_version) = Db::record(get_label('gaining system'), 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
		
		// The version to write. An existing one is rewritten in place - that is what Save does,
		// and the editor warns first when something already runs on it. One above the highest
		// there is gets created and becomes the current version - that is the New version
		// button, which only picks the number and leaves the writing to the save.
		$versions = get_system_versions('gaining', $gaining_id);
		$version = (int)get_optional_param('gaining_version', $current_version);
		$exists = in_array($version, $versions, true);
		if (!$exists && $version != max($versions) + 1)
		{
			throw new Exc(get_label('Unknown [0]', get_label('version')));
		}
		
		// What is in it now - or in the current version when the one asked for is about to be
		// created, so that a request naming only a new version gets a copy of the current one.
		list ($old_gaining) = Db::record(get_label('gaining system'),
			'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?',
			$gaining_id, $exists ? $version : $current_version);
		$gaining = get_optional_param('gaining', $old_gaining);
		if (is_string($gaining))
		{
			$gaining = json_decode($gaining);
		}
		check_gaining($gaining);
		$function_flags = get_gaining_function_flags($gaining);
		$gaining = json_encode($gaining);
		
		// Rewriting a version with what it already holds leaves it alone. Creating one is a
		// change even when its contents are a copy. Saving only the name is neither.
		$changed = (!$exists || $old_gaining != $gaining);

		// Rewriting the version a series runs on changes what it scored, so it has to be
		// recounted. A version being created has no series on it yet.
		if ($changed && $exists)
		{
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE gaining_id = ? AND gaining_version = ?', $gaining_id, $version);
		}

		Db::exec(get_label('gaining system'), 'UPDATE gainings SET name = ? WHERE id = ?', $name, $gaining_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = $name;
			db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, $club_id, $league_id);
		}
		
		// Nothing to write when the formulas are the same: neither a rewrite of this version nor
		// a new one. This used to fall through to the insert below and add a version whose
		// formula was NULL.
		if (!$changed)
		{
			Db::commit();
			$this->response['gaining_version'] = (int)$version;
			$this->response['current_version'] = (int)$current_version;
			return;
		}

		if ($exists)
		{
			Db::exec(get_label('gaining system'), 'UPDATE gaining_versions SET gaining = ?, functions = ? WHERE gaining_id = ? AND version = ?', $gaining, $function_flags, $gaining_id, $version);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->gaining = $gaining;
				db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, $club_id, $league_id);
			}
		}
		else
		{
			$current_version = $version;
			Db::exec(get_label('gaining system'), 'INSERT INTO gaining_versions (gaining_id, version, gaining, functions) VALUES (?, ?, ?, ?)', $gaining_id, $version, $gaining, $function_flags);
			Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = ? WHERE id = ?', $version, $gaining_id);
			if (Db::affected_rows() > 0)
			{
				$log_details = new stdClass();
				$log_details->gaining = $gaining;
				$log_details->version = $version;
				db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, $club_id, $league_id);
			}
		}
		Db::commit();
		
		// Which version the change landed on, and which one is current now - the same thing
		// unless an older version was rewritten in place.
		$this->response['gaining_version'] = (int)$version;
		$this->response['current_version'] = (int)$current_version;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Change a gaining system. Which version is written is up to the <em>gaining_version</em> parameter: an existing one is rewritten where it is, and every series using it is marked to be counted again; one number above the highest version there is creates that version and makes it the current one, leaving the series on the older versions as they were.');
		$help->request_param('gaining_id', 'gaining system id. If the gaining system is global (shared between leagues) updating requires <em>admin</em> permissions.');
		$help->request_param('name', 'gaining system name.', 'remains the same.');
		api_gaining_help($help->request_param('gaining', 'gaining rules:', 'remain the same.'));
		$help->request_param('gaining_version', 'The version to write. An existing version is rewritten where it is, and every series using it is counted again. One number above the highest version there is creates that version and makes it current. Any other number is refused.', 'is the current version.');
		$help->response_param('gaining_version', 'The version the change landed on.');
		$help->response_param('current_version', 'Current gaining system version. The same as <em>gaining_version</em> unless an older version was rewritten where it is.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_version
	//-------------------------------------------------------------------------------------------------------
	function delete_version_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');
		$version = (int)get_required_param('gaining_version');
		
		Db::begin();
		
		list ($club_id, $league_id, $current_version) = Db::record(get_label('gaining system'), 'SELECT club_id, league_id, version FROM gainings WHERE id = ?', $gaining_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else if (!is_null($league_id))
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		$versions = get_system_versions('gaining', $gaining_id);
		if (!in_array($version, $versions, true))
		{
			throw new Exc(get_label('Unknown [0]', get_label('version')));
		}
		
		// A system is its versions, so the last one left cannot go.
		if (count($versions) < 2)
		{
			throw new Exc(get_label('The only version of a system can not be deleted.'));
		}
		
		// Neither can one a series uses. The page does not offer it and the foreign keys
		// would refuse it, but the answer should say what the matter is.
		$used = get_used_system_versions('gaining', $gaining_id, array('series'));
		if (isset($used[$version]))
		{
			throw new Exc(get_label('This version is used in competitions and can not be deleted.'));
		}
		
		// The system points at its current version, so that has to move off it first.
		if ($version == $current_version)
		{
			$current_version = max(array_diff($versions, array($version)));
			Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = ? WHERE id = ?', $current_version, $gaining_id);
		}
		
		Db::exec(get_label('gaining system'), 'DELETE FROM gaining_versions WHERE gaining_id = ? AND version = ?', $gaining_id, $version);
		
		$log_details = new stdClass();
		$log_details->deleted_version = $version;
		db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, $club_id, $league_id);
		
		Db::commit();
		
		$this->response['current_version'] = (int)$current_version;
	}
	
	function delete_version_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete one version of a gaining system. Only a version no series uses, and never the only version there is. When the version deleted is the current one, the highest of the rest takes its place.');
		$help->request_param('gaining_id', 'Gaining system id. If the gaining system is global (shared between clubs) deleting requires <em>admin</em> permissions.');
		$help->request_param('gaining_version', 'The version to delete.');
		$help->response_param('current_version', 'Current gaining system version after the deletion.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');
		
		list ($league_id, $club_id) = Db::record(get_label('gaining system'), 'SELECT league_id, club_id FROM gainings WHERE id = ?', $gaining_id);
		if (!is_null($club_id))
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else if (!is_null($league_id))
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}

		Db::begin();
		Db::exec(get_label('gaining system'), 'UPDATE gainings SET version = NULL WHERE id = ?', $gaining_id);
		Db::exec(get_label('gaining system'), 'DELETE FROM gaining_versions WHERE gaining_id = ?', $gaining_id);
		Db::exec(get_label('gaining system'), 'DELETE FROM gainings WHERE id = ?', $gaining_id);
		db_log(LOG_OBJECT_GAINING_SYSTEM, 'deleted', NULL, $gaining_id, $club_id, $league_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Delete gaining system.');
		$help->request_param('gaining_id', 'Gaining system id. If the gaining system is global (shared between leagues and clubs) deleting requires <em>admin</em> permissions.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Gaining Operations', CURRENT_VERSION);

?>