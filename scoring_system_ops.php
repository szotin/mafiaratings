<?php

require_once 'include/session.php';
require_once 'include/scoring_system.php';
require_once 'include/names.php';

ob_start();
$result = array();

try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
/*	echo '<pre>';
	print_r($_POST);
	echo '</pre>';*/
	
	if (isset($_POST['create']))
	{
		if (!isset($_REQUEST['club']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$club_id = $_POST['club'];
		
		if (!$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
	
		$name = trim($_POST['name']);
		$digits = $_POST['digits'];
		
		Db::begin();
		check_scoring_system_name($name, $club_id);
		
		Db::exec(get_label('scoring system'), 'INSERT INTO scoring_systems (club_id, name, digits) VALUES (?, ?, ?)', $club_id, $name, $digits);
		list ($system_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		$log_details =
			'name=' . $name .
			'<br>digits=' . $digits;
		for ($flag = 1; $flag < SCORING_FIRST_AVAILABLE_FLAG; $flag <<= 1)
		{
			$points = 0;
			if (isset($_POST[$flag]))
			{
				$points = $_POST[$flag];
			}
			if ($points != 0)
			{
				$log_details .= '<br>flag-' . $flag . '=' . $points;
				Db::exec(get_label('scoring system'), 'INSERT INTO scoring_points (system_id, flag, points) VALUES (?, ?, ?)', $system_id, $flag, $points);
			}
		}
		db_log('scoring system', 'Created', $log_details, $system_id, $club_id);
		Db::commit();
	}
	else
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
		}
		$system_id = $_REQUEST['id'];
		
		list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scoring_systems WHERE id = ?', $system_id);
		if (!$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}

		if (isset($_POST['update']))
		{
			Db::begin();
			
			$name = trim($_POST['name']);
			check_scoring_system_name($name, $club_id, $system_id);
			
			$digits = $_POST['digits'];
			
			Db::exec(get_label('scoring system'), 'UPDATE scoring_systems SET name = ?, digits = ? WHERE id = ?', $name, $digits, $system_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_points WHERE system_id = ?', $system_id);
			
			$log_details =
				'name=' . $name .
				'<br>digits=' . $digits;
			for ($flag = 1; $flag < SCORING_FIRST_AVAILABLE_FLAG; $flag <<= 1)
			{
				$points = 0;
				if (isset($_POST[$flag]))
				{
					$points = $_POST[$flag];
				}
				if ($points != 0)
				{
					$log_details .= '<br>flag-' . $flag . '=' . $points;
					Db::exec(get_label('scoring system'), 'INSERT INTO scoring_points (system_id, flag, points) VALUES (?, ?, ?)', $system_id, $flag, $points);
				}
			}
			db_log('scoring system', 'Changed', $log_details, $system_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_points WHERE system_id = ?', $system_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_systems WHERE id = ?', $system_id);
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
	$result['message'] = $message;
}

echo json_encode($result);

?>