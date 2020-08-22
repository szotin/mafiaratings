<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/datetime.php';

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
			$query = new DbQuery('SELECT name FROM club_seasons WHERE name = ? AND club_id = ? AND id <> ?', $name, $club_id, $id);
		}
		else
		{
			$query = new DbQuery('SELECT name FROM club_seasons WHERE name = ? AND club_id = ?', $name, $club_id);
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

		$start = get_datetime(get_required_param('start'), $club->timezone);
		$end = get_datetime(get_required_param('end'), $club->timezone);
		if ($start >= $end)
		{
			throw new Exc(get_label('Season ends before or right after the start.'));
		}
		
		Db::begin();
		Db::exec(
			get_label('season'),
			'INSERT INTO club_seasons (club_id, name, start_time, end_time) VALUES (?, ?, ?, ?)', 
			$club->id, $name, $start->getTimestamp(), $end->getTimestamp());
		list ($season_id) = Db::record(get_label('season'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->start = $start->format(DEF_DATETIME_FORMAT_NO_TIME);
		$log_details->end = $end->format(DEF_DATETIME_FORMAT_NO_TIME);
		db_log(LOG_OBJECT_CLUB_SEASON, 'created', $log_details, $season_id, $club->id);
		
		Db::commit();
		
		$this->response['season_id'] = (int)$season_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create a season in the club.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Season name.');
		$help->request_param('start', 'Season start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('end', 'Season end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
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
		list ($club_id, $old_name, $old_start, $old_end) = Db::record(get_label('season'), 'SELECT club_id, name, start_time, end_time FROM club_seasons WHERE id = ?', $season_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$name = get_optional_param('name', $old_name);
		$this->check_name($name, $club->id, $season_id);
		
		$start_datetime = get_datetime(get_optional_param('start', $old_start), $club->timezone);
		$end_datetime = get_datetime(get_optional_param('end', $old_end), $club->timezone);
		$start = $start_datetime->getTimestamp();
		$end = $end_datetime->getTimestamp();
		if ($start >= $end)
		{
			throw new Exc(get_label('Season ends before or right after the start.'));
		}
		
		Db::begin();
		Db::exec(get_label('season'), 'UPDATE club_seasons SET name = ?, start_time = ?, end_time = ? WHERE id = ?', $name, $start, $end, $season_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($old_name != $name)
			{
				$log_details->name = $name;
			}
			if ($old_start != $start)
			{
				$log_details->start = $start_datetime->format(DEF_DATETIME_FORMAT_NO_TIME);
			}
			if ($old_end != $end)
			{
				$log_details->end = $end_datetime->format(DEF_DATETIME_FORMAT_NO_TIME);
			}
			db_log(LOG_OBJECT_CLUB_SEASON, 'changed', $log_details, $season_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change the season.');
		$help->request_param('season_id', 'Season id.');
		$help->request_param('name', 'Season name.', 'remains the same');
		$help->request_param('start', 'Season start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('end', 'Season end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
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
	
		list ($club_id) = Db::record(get_label('season'), 'SELECT club_id FROM club_seasons WHERE id = ?', $season_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		Db::begin();
		Db::exec(get_label('season'), 'DELETE FROM club_seasons WHERE id = ?', $season_id);
		db_log(LOG_OBJECT_CLUB_SEASON, 'deleted', NULL, $season_id, $club_id);
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
$page->run('Club Season Operations', CURRENT_VERSION);

?>