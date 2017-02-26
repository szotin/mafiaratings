<?php

require_once 'include/session.php';
require_once 'include/game_rules.php';

ob_start();
$result = array();

try
{
	initiate_session();
	check_maintenance();

/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';*/
	
	if (isset($_REQUEST['create']))
	{
		if (!isset($_REQUEST['club']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$club_id = $_REQUEST['club'];
		if ($_profile == NULL || !$_profile->is_manager($club_id))
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$name = trim($_REQUEST['name']);
		$rules = new GameRules();
		$rules->init();
		
		Db::begin();
		$rules->create($club_id, $name);
		Db::commit();
		
		$result['id'] = $rules->id;
		$result['name'] = $name;
	}
	else if (isset($_REQUEST['update']))
	{
		if (!isset($_REQUEST['club']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$club = $_profile->clubs[$_REQUEST['club']];
		if ($_profile == NULL || !$_profile->is_manager($club->id))
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$rules_id = -1;
		if (isset($_REQUEST['id']))
		{
			$rules_id = $_REQUEST['id'];
		}
		
		$name = NULL;
		if (isset($_REQUEST['name']))
		{
			$name = trim($_REQUEST['name']);
		}
		
		$rules = new GameRules();
		$rules->init();
		
		Db::begin();
		$rules->update($club->id, $rules_id, $name);
		Db::commit();
	}
	else if (isset($_REQUEST['delete']))
	{
		if (!isset($_REQUEST['club']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$club = $_profile->clubs[$_REQUEST['club']];
		if ($_profile == NULL || !$_profile->is_manager($club->id))
		{
			throw new Exc(get_label('No permissions'));
		}
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('rules')));
		}
		$rules_id = $_REQUEST['id'];
		
		Db::begin();
		
		list($rules_name) = Db::record(get_label('rules'), 'SELECT name FROM club_rules WHERE club_id = ? AND rules_id = ?', $club->id, $rules_id);
		Db::exec(get_label('rules'), 'DELETE FROM club_rules WHERE club_id = ? AND rules_id = ?', $club->id, $rules_id);
		Db::exec(get_label('event'), 'UPDATE events e, clubs c SET e.rules_id = c.rules_id WHERE e.club_id = c.id AND c.id = ? AND e.rules_id = ? AND e.start_time + e.duration > UNIX_TIMESTAMP()', $club->id, $rules_id);
		
		db_log('rules', 'Deleted', $rules_name, $rules_id, $club->id);
		Db::commit();
	}
	else if (isset($_REQUEST['get']))
	{
		$rules = new GameRules();
		$rules->load($_REQUEST['get']);
		
		$result['id'] = $rules->id;
    	$result['flags'] = $rules->flags;
    	$result['st_free'] = $rules->st_free;
    	$result['spt_free'] = $rules->spt_free;
    	$result['st_reg'] = $rules->st_reg;
    	$result['spt_reg'] = $rules->spt_reg;
    	$result['st_killed'] = $rules->st_killed;
    	$result['spt_killed'] = $rules->spt_killed;
    	$result['st_def'] = $rules->st_def;
    	$result['spt_def'] = $rules->spt_def;
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