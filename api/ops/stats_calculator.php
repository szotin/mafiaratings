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
		
		$name = get_required_param('name');
		$description = get_optional_param('description');
		$published = (int)get_optional_param('published', 0);
		$code = get_required_param('code');
		
		Db::begin();
		ApiPage::check_name($name);

		Db::exec(get_label('stats'), 'INSERT INTO stats_calculators (name, description, code, owner_id, published) values (?, ?, ?, ?, ?)', $name, $description, $code, $_profile->user_id, $published);
		list ($id) = Db::record(get_label('stats'), 'SELECT LAST_INSERT_ID()');
		$log_details = 'name=' . $name . '<br>description=' . $description . '<br>published=' . $published . '<br>code=' . $code;
		db_log('address', 'Created', $log_details, $id);
		Db::commit();
		
		$this->response['id'] = $id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create javascript stats code.');
		$help->request_param('name', 'Name of the stats.');
		$help->request_param('description', 'Description.', 'empty');
		$help->request_param('code', 'Javascript code for gathering stats. It should contain two functions:<br>proceedGame(game, num) - stats page calls this function once per game sending game object as it is described in /api/get/games.php?help<br>function complete() is called by stats page to output the results. It should return html with the calculated results.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return PERMISSION_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$id = (int)get_required_param('id');
		
		Db::begin();
		list($name, $description, $code, $owner_id, $published) = Db::record(get_label('stats'), 'SELECT name, description, code, owner_id, published FROM stats_calculators WHERE id = ?', $id);
		$this->check_permissions(0, $owner_id);
		
		$name = get_optional_param('name', $name);
		ApiPage::check_name($name, $id);
		
		$description = get_optional_param('description', $description);
		$code = get_optional_param('code', $code);
		$published = (int)get_optional_param('published', $published);
		
		Db::exec(
			get_label('stats'), 
			'UPDATE stats_calculators SET name = ?, description = ?, code = ?, published = ? WHERE id = ?', $name, $description, $code, $published, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'name=' . $name . '<br>description=' . $description . '<br>published=' . $published . '<br>code=' . $code;
			db_log('stats', 'Changed', $log_details, $id);
		}
		Db::commit();
		
		$this->response['id'] = $id;
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change javascript stats code.');
		$help->request_param('id', 'Stats id.');
		$help->request_param('name', 'Name of the stats.');
		$help->request_param('description', 'Description.', 'empty');
		$help->request_param('code', 'Javascript code for gathering stats. It should contain two functions:<br>proceedGame(game, num) - stats page calls this function once per game sending game object as it is described in /api/get/games.php?help<br>function complete() is called by stats page to output the results. It should return html with the calculated results.');
		return $help;
	}
	
	function change_op_permissions()
	{
		return PERMISSION_USER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$id = (int)get_required_param('id');
		
		Db::begin();
		list($owner_id, $name) = Db::record(get_label('stats'), 'SELECT owner_id, name FROM stats_calculators WHERE id = ?', $id);
		$this->check_permissions(0, $owner_id);
		
		Db::exec(
			get_label('stats'), 
			'DELETE FROM stats_calculators WHERE id = ?', $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'name=' . $name;
			db_log('stats', 'Deleted', $log_details, $id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp('Delete javascript stats code.');
		$help->request_param('id', 'Stats id.');
		return $help;
	}
	
	function delete_op_permissions()
	{
		return PERMISSION_USER;
	}
}

$page = new ApiPage();
$page->run('Stats Calculator Operations', CURRENT_VERSION);

?>