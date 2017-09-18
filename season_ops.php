<?php

require_once 'include/session.php';
require_once 'include/club.php';
require_once 'include/email.php';

function get_club($id)
{
	global $_profile;
	if ($_profile == NULL || !$_profile->is_manager($id))
	{
		throw new Exc(get_label('No permissions'));
	}
	return $_profile->clubs[$id];
}

ob_start();
$result = array();

try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL)
	{
		throw new Exc(get_label('No permissions'));
	}
	
/*	echo '<pre>';
	print_r($_POST);
	echo '</pre>';*/
	
	if (isset($_POST['create']))
	{
		if (!isset($_POST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$id = $_POST['id'];
		$club = get_club($id);
		
		$name = $_POST['name'];
		check_season_name($name, $club->id);
		
		$start_month = $_POST['start_month'];
		$start_day = $_POST['start_day'];
		$start_year = $_POST['start_year'];
		$end_month = $_POST['end_month'];
		$end_day = $_POST['end_day'];
		$end_year = $_POST['end_year'];
		
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
	}
	else
	{
		if (!isset($_POST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('season')));
		}
		$id = $_POST['id'];
	
		list ($club_id) = Db::record(get_label('season'), 'SELECT club_id FROM seasons WHERE id = ?', $id);
		$club = get_club($club_id);
		
		if (isset($_POST['update']))
		{
			$name = $_POST['name'];
			check_season_name($name, $club->id, $id);
			
			$start_month = $_POST['start_month'];
			$start_day = $_POST['start_day'];
			$start_year = $_POST['start_year'];
			$end_month = $_POST['end_month'];
			$end_day = $_POST['end_day'];
			$end_year = $_POST['end_year'];
			
			date_default_timezone_set($club->timezone);
			$start = mktime(0, 0, 0, $start_month, $start_day, $start_year);
			$end = mktime(0, 0, 0, $end_month, $end_day, $end_year);
			
			Db::begin();
			Db::exec_with_echo(get_label('season'), 'UPDATE seasons SET name = ?, start_time = ?, end_time = ? WHERE id = ?', $name, $start, $end, $id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'name=' . $name . '; start=' . $start . '; end=' . $end;
				db_log('season', 'Changed', $log_details, $id, $club->id);
			}
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('season'), 'DELETE FROM seasons WHERE id = ?', $id);
			db_log('season', 'Deleted', NULL, $id, $club->id);
			Db::commit();
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>