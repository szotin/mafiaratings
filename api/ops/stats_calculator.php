<?php

require_once '../../include/session.php';
require_once '../../include/club.php';
require_once '../../include/city.php';
require_once '../../include/country.php';
require_once '../../include/address.php';

require_once '../../include/api.php';
require_once '../../include/user_location.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private static function check_name($name, $id = -1)
	{
		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('name')));
		}

		if ($id > 0)
		{
			$query = new DbQuery('SELECT name FROM stats_calculators WHERE name = ? AND id <> ?', $name, $id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM stats_calculators WHERE name = ?', $name);
		}
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Name'), $name));
		}
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$name = get_required_param('name');
		$description = get_optional_param('description');
		$published = (int)get_optional_param('published', 0);
		$code = get_required_param('code');
		
		Db::begin();
		ApiPage::check_name($name);

		Db::exec(get_label('stats'), 'INSERT INTO stats_calculators (name, description, code, owner_id, published) values (?, ?, ?, ?, ?)', $name, $description, $code, $_profile->user_id, $published);
		list ($id) = Db::record(get_label('stats'), 'SELECT LAST_INSERT_ID()');
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->description = $description;
		$log_details->published = $published;
		$log_details->code = $code;
		db_log(LOG_OBJECT_STATS_CALCULATOR, 'created', $log_details, $id);
		Db::commit();
		
		$this->response['id'] = $id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Create javascript stats code.');
		$help->request_param('name', 'Name of the stats.');
		$help->request_param('description', 'Description.', 'empty');
		$help->request_param('code', 'Javascript code for gathering stats. It should contain two functions:<br>proceedGame(game, num) - stats page calls this function once per game sending game object as it is described in /api/get/games.php?help<br>function complete() is called by stats page to output the results. It should return html with the calculated results.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$id = (int)get_required_param('id');
		
		Db::begin();
		list($old_name, $old_description, $old_code, $owner_id, $old_published) = Db::record(get_label('stats'), 'SELECT name, description, code, owner_id, published FROM stats_calculators WHERE id = ?', $id);
		check_permissions(PERMISSION_OWNER, $owner_id);
		
		$name = get_optional_param('name', $old_name);
		ApiPage::check_name($name, $id);
		
		$description = get_optional_param('description', $old_description);
		$code = get_optional_param('code', $old_code);
		$published = (int)get_optional_param('published', $old_published);
		
		Db::exec(
			get_label('stats'), 
			'UPDATE stats_calculators SET name = ?, description = ?, code = ?, published = ? WHERE id = ?', $name, $description, $code, $published, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($old_name != $name)
			{
				$log_details->name = $name;
			}
			if ($old_description != $description)
			{
				$log_details->description = $description;
			}
			if ($old_published != $published)
			{
				$log_details->published = $published;
			}
			if ($old_code != $code)
			{
				$log_details->code = $code;
			}
			db_log(LOG_OBJECT_STATS_CALCULATOR, 'changed', $log_details, $id);
		}
		Db::commit();
		
		$this->response['id'] = $id;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Change javascript stats code.');
		$help->request_param('id', 'Stats id.');
		$help->request_param('name', 'Name of the stats.');
		$help->request_param('description', 'Description.', 'empty');
		$help->request_param('code', 'Javascript code for gathering stats. It should contain two functions:<br>proceedGame(game, num) - stats page calls this function once per game sending game object as it is described in /api/get/games.php?help<br>function complete() is called by stats page to output the results. It should return html with the calculated results.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$id = (int)get_required_param('id');
		
		Db::begin();
		list($owner_id, $name) = Db::record(get_label('stats'), 'SELECT owner_id, name FROM stats_calculators WHERE id = ?', $id);
		check_permissions(PERMISSION_OWNER, $owner_id);
		
		Db::exec(
			get_label('stats'), 
			'DELETE FROM stats_calculators WHERE id = ?', $id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_STATS_CALCULATOR, 'deleted', NULL, $id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER, 'Delete javascript stats code.');
		$help->request_param('id', 'Stats id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Stats Calculator Operations', CURRENT_VERSION);

?>