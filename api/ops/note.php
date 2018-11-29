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
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
		$note_name = trim(get_required_param('name'));
		if (empty($note_name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('note name')));
		}
		
		$note = trim(get_required_param('note'));
		if (empty($note))
		{
			throw new Exc(get_label('Please enter [0].', get_label('note')));
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id FROM club_info WHERE club_id = ? AND name = ?', $club_id, $note_name);
		if ($query->next())
		{
			throw new Exc(get_label('Note [0] already exists', $note_name));
		}
		
		$pos = 0;
		$query = new DbQuery('SELECT pos FROM club_info WHERE club_id = ? ORDER BY pos DESC LIMIT 1', $club_id);
		if ($row = $query->next())
		{
			list ($pos) = $row;
		}
		++$pos;
		
		Db::exec(get_label('note'), 'INSERT INTO club_info (club_id, name, value, pos) VALUES (?, ?, ?, ?)', $club_id, $note_name, $note, $pos);
		list ($note_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->name = $note_name;
		$log_details->note = $note;
		db_log(LOG_OBJECT_NOTE, 'created', $log_details, $note_id, $club_id);
		
		Db::commit();
		$this->response['note_id'] = $note_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create new note.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Name of the note.');
		$help->request_param('note', 'Note content text.');
		$help->response_param('note_id', 'New note id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$note_id = (int)get_required_param('note_id');
		$note = trim(get_required_param('note'));
		if (empty($note))
		{
			throw new Exc(get_label('Please enter [0].', get_label('note')));
		}
		
		Db::begin();
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $note_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('note'), 'UPDATE club_info SET value = ? WHERE id = ? AND club_id = ?', $note, $note_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass(); 
			$log_details->note = $note;
			db_log(LOG_OBJECT_NOTE, 'changed', $log_details, $note_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change the existing note.');
		$help->request_param('note_id', 'Note id.');
		$help->request_param('note', 'Note content text.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// up
	//-------------------------------------------------------------------------------------------------------
	function up_op()
	{
		$note_id = (int)get_required_param('note_id');
		
		Db::begin();
		
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $note_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		list ($pos) = Db::record(get_label('note'), 'SELECT pos FROM club_info WHERE club_id = ? AND id = ?', $club_id, $note_id);
		list ($note_id1, $pos1) = Db::record(get_label('note'), 'SELECT id, pos FROM club_info WHERE club_id = ? AND pos < ? ORDER BY pos DESC LIMIT 1', $club_id, $pos);
		
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = 0 WHERE id = ?', $note_id);
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos, $note_id1);
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos1, $note_id);
		Db::commit();
	}
	
	function up_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Move the note up in the list of notes.');
		$help->request_param('note_id', 'Note id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$note_id = (int)get_required_param('note_id');
		
		Db::begin();
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $note_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::exec(get_label('note'), 'DELETE FROM club_info WHERE club_id = ? AND id = ?', $club_id, $note_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_NOTE, 'deleted', NULL, $note_id, $club_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete note.');
		$help->request_param('note_id', 'Note id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Club Notes Operations', CURRENT_VERSION);

?>