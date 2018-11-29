<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private function check_name($name, $club_id, $id = -1)
	{
		global $_profile;

		if ($name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('season name')));
		}

		check_name($name, get_label('season name'));

		if ($id > 0)
		{
			$query = new DbQuery('SELECT name FROM seasons WHERE name = ? AND club_id = ? AND id <> ?', $name, $club_id, $id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM seasons WHERE name = ? AND club_id = ?', $name, $club_id);
		}
		if ($query->next())
		{
			throw new Exc(get_label('[0] "[1]" is already used. Please try another one.', get_label('Season name'), $name));
		}
	}

	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_required_param('name');
		$this->check_name($name, $club->id);
		
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
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->start = $start_year . '-' . $start_month . '-' . $start_day;
		$log_details->end = $end_year . '-' . $end_month . '-' . $end_day;
		db_log(LOG_OBJECT_SEASON, 'created', $log_details, $season_id, $club->id);
		
		Db::commit();
		
		$this->result['season_id'] = (int)$season_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create a season in the club.');
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
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$season_id = (int)get_required_param('season_id');
		list ($club_id, $old_name, $old_start, $old_end) = Db::record(get_label('season'), 'SELECT club_id, name, start_time, end_time FROM seasons WHERE id = ?', $season_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_optional_param('name', $old_name);
		$this->check_name($name, $club->id, $season_id);
		
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
			$log_details = new stdClass();
			if ($old_name != $name)
			{
				$log_details->name = $name;
			}
			if ($old_start != $start)
			{
				$log_details->start_date = $start_year . '-' . $start_month . '-' . $start_day;
			}
			if ($old_end != $end)
			{
				$log_details->end_date = $end_year . '-' . $end_month . '-' . $end_day;
			}
			db_log(LOG_OBJECT_SEASON, 'changed', $log_details, $season_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change the season.');
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

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		global $_profile;
		
		$season_id = (int)get_required_param('season_id');
	
		list ($club_id) = Db::record(get_label('season'), 'SELECT club_id FROM seasons WHERE id = ?', $season_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		Db::begin();
		Db::exec(get_label('season'), 'DELETE FROM seasons WHERE id = ?', $season_id);
		db_log(LOG_OBJECT_SEASON, 'deleted', NULL, $season_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete season.');
		$help->request_param('season_id', 'Season id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Season Operations', CURRENT_VERSION);

?>