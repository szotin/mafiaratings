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
		$club_id = NULL;
		if (isset($_REQUEST['club']))
		{
			$club_id = (int)$_POST['club'];
			if ($club_id <= 0)
			{
				$club_id = NULL;
			}
		}
		
		$copy_id = 0;
		if (isset($_POST['copy']))
		{
			$copy_id = (int)$_POST['copy'];
		}
		
		if (is_null($club_id))
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
	
		$name = trim($_POST['name']);
		
		Db::begin();
		check_scoring_name($name, $club_id);
		
		if ($copy_id > 0)
		{
			Db::exec(get_label('scoring system'), 'INSERT INTO scorings (club_id, name, sorting) SELECT ?, ?, sorting FROM scorings WHERE id = ?', $club_id, $name, $copy_id);
			list ($scoring_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
			Db::exec(get_label('scoring rule'), 'INSERT INTO scoring_rules (scoring_id, category, matter, roles, policy, min_dependency, min_points, max_dependency, max_points) SELECT ?, category, matter, roles, policy, min_dependency, min_points, max_dependency, max_points FROM scoring_rules WHERE scoring_id = ?', $scoring_id, $copy_id);
		}
		else
		{
			Db::exec(get_label('scoring system'), 'INSERT INTO scorings (club_id, name, sorting) VALUES (?, ?, ?)', $club_id, $name, SCORING_DEFAULT_SORTING);
			list ($scoring_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		}
		
		if ($club_id > 0)
		{
			db_log('scoring system', 'Created', 'name=' . $name, $scoring_id, $club_id);
		}
		else
		{
			db_log('scoring system', 'Created', 'name=' . $name, $scoring_id);
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
			db_log('scoring system', 'Changed', 'name=' . $name, $scoring_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('scoring system'), 'DELETE FROM scoring_rules WHERE scoring_id = ?', $scoring_id);
			Db::exec(get_label('scoring system'), 'DELETE FROM scorings WHERE id = ?', $scoring_id);
			db_log('scoring system', 'Deleted', '', $scoring_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['create_rule']))
		{
			$matter = (int)$_POST['create_rule'];
			if ($matter < 0 || $matter >= SCORING_MATTER_COUNT)
			{
				throw new Exc(get_label('Invalid rule matter [0]', $matter));
			}
			
			$category = (int)$_POST['category'];
			if ($category < 0 || $category >= SCORING_CATEGORY_COUNT)
			{
				throw new Exc(get_label('Invalid rule category [0]', $matter));
			}
			
			$roles = (int)$_POST['roles'] & SCORING_ROLE_FLAGS_ALL;
			if ($roles == 0)
			{
				throw new Exc(get_label('Please select at least one role'));
			}
			
			$policy = (int)$_POST['policy'];
			if ($policy < 0 || $policy >= SCORING_POLICY_COUNT)
			{
				throw new Exc(get_label('Invalid policy [0]', $policy));
			}
			
			$min_dependency = (float)$_POST['min_dep'];
			$min_points = (float)$_POST['min_points'];
			if ($min_dependency < 0)
			{
				$min_dependency = 0.0;
			}
			else if ($min_dependency > 1)
			{
				$min_dependency = 1.0;
			}
			
			if ($policy == SCORING_POLICY_STATIC)
			{
				$max_dependency = $min_dependency = 0.0;
				$max_points = $min_points;
			}
			else
			{
				$max_dependency = (float)$_POST['max_dep'];
				$max_points = (float)$_POST['max_points'];
				if ($max_points == $min_points)
				{
					$policy == SCORING_POLICY_STATIC;
					$max_dependency = $min_dependency = 0.0;
				}
				else if ($max_dependency < 0)
				{
					$max_dependency = 0.0;
				}
				else if ($max_dependency > 1)
				{
					$max_dependency = 1.0;
				}
			}
			
			if ($min_points == 0 && $max_points == 0)
			{
				throw new Exc(get_label('Please enter points'));
			}
			
			Db::begin();
			Db::exec(get_label('scoring rule'), 'INSERT INTO scoring_rules (scoring_id, category, matter, roles, policy, min_dependency, min_points, max_dependency, max_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', 
				$scoring_id, $category, $matter, $roles, $policy, $min_dependency, $min_points, $max_dependency, $max_points);
			db_log('scoring system', 'Rule created', 'matter=' . $matter . '; roles=' . $roles . '; policy=' . $policy . '; min_dependency=' . $min_dependency . '; min_points=' . $min_points . '; max_dependency=' . $max_dependency . '; max_points=' . $max_points, $scoring_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['delete_rule']))
		{
			$matter = (int)$_POST['delete_rule'];
			$category = (int)$_POST['category'];
			Db::begin();
			Db::exec(get_label('scoring rule'), 'DELETE FROM scoring_rules WHERE scoring_id = ? AND matter = ? AND category = ?', $scoring_id, $matter, $category);
			db_log('scoring system', 'Rule deleted', '', $scoring_id, $club_id);
			Db::commit();
		}
		else if (isset($_POST['update_sorting']))
		{
			$sorting = $_POST['update_sorting'];
			Db::begin();
			Db::exec(get_label('scoring system'), 'UPDATE scorings SET sorting = ? WHERE id = ?', $sorting, $scoring_id);
			db_log('scoring system', 'Changed sorting', 'sorting=' . $sorting, $scoring_id, $club_id);
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
		$message = '<p>' . $result['message'] . '</p><hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>