<?php

require_once '../../include/api.php';
require_once '../../include/game_rules.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$name = trim($_REQUEST['name']);
		$rules = new GameRules();
		$rules->init();
		
		Db::begin();
		$rules->create($club_id, $name);
		Db::commit();
		
		$this->response['rules_id'] = $rules->id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create game rules. This part will be reworked very soon. I don\'t want to waste time commenting deprecated code.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Rules name. Should be unique in the club.');
		$help->request_param('flags', '');
		$help->request_param('st_reg', '');
		$help->request_param('spt_reg', '');
		$help->request_param('st_killed', '');
		$help->request_param('spt_killed', '');
		$help->request_param('st_def', '');
		$help->request_param('spt_def', '');
		$help->request_param('st_free', '');
		$help->request_param('spt_free', '');
		$help->response_param('rules_id', 'Newly created rules id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		$rules_id = -1;
		if (isset($_REQUEST['rules_id']))
		{
			$rules_id = $_REQUEST['rules_id'];
		}
		
		$name = NULL;
		if (isset($_REQUEST['name']))
		{
			$name = trim($_REQUEST['name']);
		}
		
		$rules = new GameRules();
		$rules->init();
		
		Db::begin();
		$rules->update($club_id, $rules_id, $name);
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change game rules. This part will be reworked very soon. I don\'t want to waste time commenting deprecated code.');
		$help->request_param('club_id', 'Club id of the club that will be using these rules.');
		$help->request_param('rules_id', 'Rules id.', 'default club rules are changed.');
		$help->request_param('name', 'Rules name. Should be unique in the club.');
		$help->request_param('flags', '');
		$help->request_param('st_reg', '');
		$help->request_param('spt_reg', '');
		$help->request_param('st_killed', '');
		$help->request_param('spt_killed', '');
		$help->request_param('st_def', '');
		$help->request_param('spt_def', '');
		$help->request_param('st_free', '');
		$help->request_param('spt_free', '');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$club_id = (int)get_required_param('club_id');
		$rules_id = (int)get_required_param('rules_id');
		
		Db::begin();
		list($rules_name) = Db::record(get_label('rules'), 'SELECT name FROM club_rules WHERE club_id = ? AND rules_id = ?', $club_id, $rules_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('rules'), 'DELETE FROM club_rules WHERE club_id = ? AND rules_id = ?', $club_id, $rules_id);
		Db::exec(get_label('event'), 'UPDATE events e, clubs c SET e.rules_id = c.rules_id WHERE e.club_id = c.id AND c.id = ? AND e.rules_id = ? AND e.start_time + e.duration > UNIX_TIMESTAMP()', $club_id, $rules_id);
		
		db_log('rules', 'Deleted', $rules_name, $rules_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete game rules.');
		$help->request_param('rules_id', 'Rules id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// get !!! todo: move it to get API !!!
	//-------------------------------------------------------------------------------------------------------
	function get_op()
	{
		check_permissions(PERMISSION_USER);
		$rules_id = (int)get_required_param('rules_id');
		
		$rules = new GameRules();
		$rules->load($rules_id);
		
		$this->response['id'] = $rules->id;
    	$this->response['flags'] = $rules->flags;
    	$this->response['st_free'] = $rules->st_free;
    	$this->response['spt_free'] = $rules->spt_free;
    	$this->response['st_reg'] = $rules->st_reg;
    	$this->response['spt_reg'] = $rules->spt_reg;
    	$this->response['st_killed'] = $rules->st_killed;
    	$this->response['spt_killed'] = $rules->spt_killed;
    	$this->response['st_def'] = $rules->st_def;
    	$this->response['spt_def'] = $rules->spt_def;
	}
	
	function get_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Get game rules. This part will be reworked very soon. I don\'t want to waste time commenting deprecated code.');
		$help->request_param('rules_id', 'Rules id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Rules Operations', CURRENT_VERSION);

?>