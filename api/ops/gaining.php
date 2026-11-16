<?php

require_once '../../include/api.php';
require_once '../../include/gaining.php';
require_once '../../include/scoring.php';
require_once '../../include/systems.php';
require_once '../../include/series.php'; // SERIES_TYPE_*
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

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		$this->check_owner($league_id, $club_id);

		$copy_id = (int)get_optional_param('copy_id', -1);
		$gaining = get_optional_param('gaining', '{}');
		if (is_string($gaining))
		{
			$gaining = json_decode($gaining);
		}
		check_gaining($gaining);
		$function_flags = get_gaining_function_flags($gaining);
		$gaining = json_encode($gaining);
		
		Db::begin();
		$names = get_system_names('gainings', get_label('Gaining system name'), $club_id, $league_id);

		Db::exec(get_label('gaining system'), 'INSERT INTO gainings (league_id, club_id, name_id, version) VALUES (?, ?, ?, NULL)', $league_id, $club_id, $names->get_id());
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
		$log_details->name = $names->to_string();
		$log_details->name_id = $names->get_id();
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
		Names::help($help, 'Gaining system', false);
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
		
		list ($league_id, $club_id, $old_name_id) = Db::record(get_label('gaining system'), 'SELECT league_id, club_id, name_id FROM gainings WHERE id = ?', $gaining_id);
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

		// A default system is called by the name of its owner, so its own names stay as they are; so do
		// the names of any system when the request has none.
		$name_id = $old_name_id;
		if (!is_default_system('gainings', $gaining_id) && isset($_REQUEST['name']))
		{
			$names = get_system_names('gainings', get_label('Gaining system name'), $club_id, $league_id, $gaining_id);
			$name_id = $names->get_id();
		}
		
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

		Db::exec(get_label('gaining system'), 'UPDATE gainings SET name_id = ? WHERE id = ?', $name_id, $gaining_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->name = (new Names($name_id, get_label('Gaining system name')))->to_string();
			$log_details->name_id = $name_id;
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
		Names::help($help, 'Gaining system', true);
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
		global $_lang;

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
		// The default system of a club, a league or the site can be edited but not deleted.
		if (is_default_system('gainings', $gaining_id))
		{
			throw new Exc(get_label('The default gaining system can not be deleted.'));
		}
		// A series keeps the version of the system it is scored by, so the system goes only when no
		// series uses it. Saying which ones lets the manager move them to another system first.
		$series_names = '';
		$query = new DbQuery(
			'SELECT n.name FROM series s JOIN names n ON n.id = s.name_id AND (n.langs & '.$_lang.') <> 0' .
			' WHERE s.gaining_id = ? ORDER BY s.start_time DESC, s.id DESC', $gaining_id);
		while ($row = $query->next())
		{
			$series_names .= '<br>' . htmlspecialchars($row[0], ENT_QUOTES);
		}
		if ($series_names != '')
		{
			list($gaining_name) = Db::record(get_label('gaining system'), 'SELECT n.name FROM gainings g JOIN names n ON n.id = g.name_id AND (n.langs & '.$_lang.') <> 0 WHERE g.id = ?', $gaining_id);
			throw new Exc(get_label('Unable to delete [0] because series use it:', $gaining_name) . $series_names);
		}
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

	//-------------------------------------------------------------------------------------------------------
	// deprecate
	//-------------------------------------------------------------------------------------------------------
	function deprecate_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');
		$deprecated = (int)get_optional_param('deprecated', 1) != 0;

		Db::begin();
		set_system_deprecated('gainings', $gaining_id, $deprecated, LOG_OBJECT_GAINING_SYSTEM);
		Db::commit();
	}

	function deprecate_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Deprecate a gaining system, or bring it back. A deprecated gaining system goes on being used wherever it is used already, but it is not offered for anything new. A default gaining system can not be deprecated.');
		$help->request_param('gaining_id', 'Gaining system id. Deprecating a gaining system that is not owned by a club or a league requires <em>admin</em> permissions.');
		$help->request_param('deprecated', '1 to deprecate, 0 to bring it back.', '1.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// set_series_types
	//-------------------------------------------------------------------------------------------------------
	function set_series_types_op()
	{
		$gaining_id = (int)get_required_param('gaining_id');

		Db::begin();
		list($club_id, $league_id, $flags) = check_system_permissions('gainings', $gaining_id);
		$new_flags = $flags & ~GAINING_FLAGS_NOT_FOR_MASK;
		$log_details = new stdClass();
		$any = false;
		foreach (array('series' => SERIES_TYPE_SERIES, 'season' => SERIES_TYPE_SEASON, 'recurring' => SERIES_TYPE_RECURRING) as $param => $type)
		{
			$for = (int)get_optional_param($param, ($flags & gaining_not_for_flag($type)) ? 0 : 1) != 0;
			if ($for)
			{
				$any = true;
			}
			else
			{
				$new_flags |= gaining_not_for_flag($type);
			}
			$log_details->$param = $for ? 1 : 0;
		}
		if (!$any)
		{
			throw new Exc(get_label('A gaining system has to be meant for at least one type of series.'));
		}
		if ($new_flags != $flags)
		{
			Db::exec(get_label('gaining system'), 'UPDATE gainings SET flags = ? WHERE id = ?', $new_flags, $gaining_id);
			db_log(LOG_OBJECT_GAINING_SYSTEM, 'changed', $log_details, $gaining_id, $club_id, $league_id);
		}
		Db::commit();
	}

	function set_series_types_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Say which types of series a gaining system is meant for. A series is offered only the systems meant for its type; a series on a system that is not meant for its type any more stays on it.');
		$help->request_param('gaining_id', 'Gaining system id. One that is not owned by a club or a league requires <em>admin</em> permissions.');
		$help->request_param('season', '1 if it is for seasons, 0 if not.', 'remains the same.');
		$help->request_param('series', '1 if it is for series of tournaments, 0 if not.', 'remains the same.');
		$help->request_param('recurring', '1 if it is for recurring tournaments, 0 if not.', 'remains the same.');
		return $help;
	}

}

$page = new ApiPage();
$page->run('Gaining Operations', CURRENT_VERSION);

?>