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
		$this->check_permissions($club_id);
	
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
		$log_details =
			'name=' . $note_name .
			"<br>note=<br>" . $note;
		db_log('note', 'Created', $log_details, $note_id, $club_id);
		Db::commit();
		$this->response['note_id'] = $note_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create new note.');
		$help->request_param('club_id', 'Club id.');
		$help->request_param('name', 'Name of the note.');
		$help->request_param('note', 'Note content text.');
		$help->response_param('note_id', 'New note id.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
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
		$this->check_permissions($club_id);
		
		Db::exec(get_label('note'), 'UPDATE club_info SET value = ? WHERE id = ? AND club_id = ?', $note, $note_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = "note=<br>" . $note;
			db_log('note', 'Changed', $log_details, $note_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change the existing note.');
		$help->request_param('note_id', 'Note id.');
		$help->request_param('note', 'Note content text.');
		return $help;
	}
	
	function change_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// up
	//-------------------------------------------------------------------------------------------------------
	function up_op()
	{
		$note_id = (int)get_required_param('note_id');
		
		Db::begin();
		
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $note_id);
		$this->check_permissions($club_id);
		
		list ($pos) = Db::record(get_label('note'), 'SELECT pos FROM club_info WHERE club_id = ? AND id = ?', $club_id, $note_id);
		list ($note_id1, $pos1) = Db::record(get_label('note'), 'SELECT id, pos FROM club_info WHERE club_id = ? AND pos < ? ORDER BY pos DESC LIMIT 1', $club_id, $pos);
		
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = 0 WHERE id = ?', $note_id);
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos, $note_id1);
		Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos1, $note_id);
		Db::commit();
	}
	
	function up_op_help()
	{
		$help = new ApiHelp('Move the note up in the list of notes.');
		$help->request_param('note_id', 'Note id.');
		return $help;
	}
	
	function up_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$note_id = (int)get_required_param('note_id');
		
		Db::begin();
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $note_id);
		$this->check_permissions($club_id);
		
		Db::exec(get_label('note'), 'DELETE FROM club_info WHERE club_id = ? AND id = ?', $club_id, $note_id);
		if (Db::affected_rows() > 0)
		{
			db_log('note', 'Deleted', NULL, $note_id, $club_id);
		}
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp('Delete note.');
		$help->request_param('note_id', 'Note id.');
		return $help;
	}
	
	function delete_op_permissions()
	{
		return PERMISSION_CLUB_MANAGER;
	}
}

$page = new ApiPage();
$page->run('Club Notes Operations', CURRENT_VERSION);

?>