<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		$this->check_permissions($club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_required_param('name');
		check_season_name($name, $club->id);
		
		$start_month = (int)get_required_param('start_month');
		$start_day = (int)get_required_param('start_day');
		$start_year = (int)get_required_param('start_year');
		$end_month = (int)get_required_param('end_month');
		$end_day = (int)get_required_param('end_day');
		$end_year = (int)get_required_param('end_year');
		
		date_default_timezone_set($club->timezone);
		$start = mktime(0, 0, 0, $start_month, $start_day, $start_year);
		$end = mktime(0, 0, 0, $end_month, $end_day, $end_year);
		
		Db::begin();
		Db::exec(
			get_label('season'),
			'INSERT INTO seasons (club_id, name, start_time, end_time) VALUES (?, ?, ?, ?)', 
			$club->id, $name, $start, $end);
		list ($season_id) = Db::record(get_label('season'), 'SELECT LAST_INSERT_ID()');
		$log_details = 'name=' . $name . '; start=' . $start . '; end=' . $end;
		db_log('season', 'Created', $log_details, $season_id, $club->id);
		Db::commit();
		
		$this->result['season_id'] = (int)$season_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create a season in the club.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Season name.');
		$help->request_param('start_month', 'The month when the season starts. Integer 1-12.');
		$help->request_param('start_day', 'The day of the month when the season starts. Integer 1-31.');
		$help->request_param('start_year', 'The year when the season starts. 4 digit integer like 2018.');
		$help->request_param('end_month', 'The month when the season ends. Integer 1-12.');
		$help->request_param('end_day', 'The day of the month when the season ends. Integer 1-31.');
		$help->request_param('end_year', 'The year when the season ends. 4 digit integer like 2018.');
		$help->response_param('season_id', 'Id of the newly created season.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$season_id = (int)get_required_param('season_id');
		list ($club_id, $name) = Db::record(get_label('season'), 'SELECT club_id, name FROM seasons WHERE id = ?', $season_id);
		$this->check_permissions($club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_optional_param('name', $name);
		check_season_name($name, $club->id, $season_id);
		
		$start_month = get_required_param('start_month');
		$start_day = get_required_param('start_day');
		$start_year = get_required_param('start_year');
		$end_month = get_required_param('end_month');
		$end_day = get_required_param('end_day');
		$end_year = get_required_param('end_year');
		
		date_default_timezone_set($club->timezone);
		$start = mktime(0, 0, 0, $start_month, $start_day, $start_year);
		$end = mktime(0, 0, 0, $end_month, $end_day, $end_year);
		
		Db::begin();
		Db::exec(get_label('season'), 'UPDATE seasons SET name = ?, start_time = ?, end_time = ? WHERE id = ?', $name, $start, $end, $season_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'name=' . $name . '; start=' . $start . '; end=' . $end;
			db_log('season', 'Changed', $log_details, $season_id, $club->id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change the season.');
		$help->request_param('season_id', 'Season id.');
		$help->request_param('name', 'Season name.', 'remains the same');
		$help->request_param('start_month', 'The month when the season starts. Integer 1-12.');
		$help->request_param('start_day', 'The day of the month when the season starts. Integer 1-31.');
		$help->request_param('start_year', 'The year when the season starts. 4 digit integer like 2018.');
		$help->request_param('end_month', 'The month when the season ends. Integer 1-12.');
		$help->request_param('end_day', 'The day of the month when the season ends. Integer 1-31.');
		$help->request_param('end_year', 'The year when the season ends. 4 digit integer like 2018.');
		$help->response_param('season_id', 'Id of the newly created season.');
		return $help;
	}
	
	function change_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		$season_id = (int)get_required_param('season_id');
	
		list ($club_id) = Db::record(get_label('season'), 'SELECT club_id FROM seasons WHERE id = ?', $season_id);
		$this->check_permissions($club_id);
		$club = $_profile->clubs[$club_id];
		
		Db::begin();
		Db::exec(get_label('season'), 'DELETE FROM seasons WHERE id = ?', $season_id);
		db_log('season', 'Deleted', NULL, $season_id, $club->id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp('Delete season.');
		$help->request_param('season_id', 'Season id.');
		return $help;
	}
	
	function delete_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
}

$page = new ApiPage();
$page->run('Season Operations', CURRENT_VERSION);

?>