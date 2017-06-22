<?php

require_once 'include/session.php';
require_once 'include/scoring.php';
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
		
		if ($club_id <= 0 && !$_profile->is_admin())
		{
			throw new FatalExc(get_label('No permissions'));
		}
	
		$name = trim($_POST['name']);
		
		Db::begin();
		check_scoring_name($name, $club_id);
		
		if ($club_id > 0)
		{
			Db::exec(get_label('scoring system'), 'INSERT INTO scorings (club_id, name) VALUES (?, ?)', $club_id, $name);
		}
		else
		{
			Db::exec(get_label('scoring system'), 'INSERT INTO scorings (name) VALUES (?)', $name);
		}
		list ($scoring_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		$log_details =
			'name=' . $name;
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
				Db::exec(get_label('scoring system'), 'INSERT INTO scoring_points (scoring_id, flag, points) VALUES (?, ?, ?)', $scoring_id, $flag, $points);
			}
		}
		
		if ($club_id > 0)
		{
			db_log('scoring system', 'Created', $log_details, $scoring_id, $club_id);
		}
		else
		{
			db_log('scoring system', 'Created', $log_details, $scoring_id);
		}
		Db::commit();
	}
	else
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
		}
		$scoring_id = $_REQUEST['id'];
		
		list ($club_id) = Db::record(get_label('scoring system'), 'SELECT club_id FROM scorings WHERE id = ?', $scoring_id);
		if ($club_id == NULL)
		{
			if (!$_profile->is_admin())
			{
				throw new FatalExc(get_label('No permissions'));
			}
		}
		else if (!$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}

		if (isset($_POST['update']))
		{
			Db::begin();
			
			$name = trim($_POST['name']);
			check_scoring_name($name, $club_id, $scoring_id);
			
			Db::exec(get_label('scoring system'), 'UPDATE scorings SET name = ? WHERE id = ?', $name, $scoring_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_points WHERE scoring_id = ?', $scoring_id);
			
			$log_details =
				'name=' . $name;
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
					Db::exec(get_label('scoring system'), 'INSERT INTO scoring_points (scoring_id, flag, points) VALUES (?, ?, ?)', $scoring_id, $flag, $points);
				}
			}
			db_log('scoring system', 'Changed', $log_details, $scoring_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_points WHERE scoring_id = ?', $scoring_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scorings WHERE id = ?', $scoring_id);
			Db::commit();
			db_log('scoring system', 'Deleted', '', $scoring_id, $club_id);
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