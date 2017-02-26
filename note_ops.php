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
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	
	if (!isset($_POST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$id = $_POST['id'];
	
/*	echo '<pre>';
	print_r($_POST);
	echo '</pre>';*/
	
	if (isset($_POST['create']))
	{
		$club = get_club($id);
	
		$note_name = trim($_POST['name']);
		$note = trim($_POST['note']);
		
		if ($note_name == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('note name')));
		}
		
		if ($note == '')
		{
			throw new Exc(get_label('Please enter [0].', get_label('note')));
		}
		
		Db::begin();
		$query = new DbQuery('SELECT id FROM club_info WHERE club_id = ? AND name = ?', $club->id, $note_name);
		if ($query->next())
		{
			throw new Exc(get_label('Note [0] already exists', $note_name));
		}
		
		$pos = 0;
		$query = new DbQuery('SELECT pos FROM club_info WHERE club_id = ? ORDER BY pos DESC LIMIT 1', $club->id);
		if ($row = $query->next())
		{
			list ($pos) = $row;
		}
		++$pos;
		
		Db::exec(get_label('note'), 'INSERT INTO club_info (club_id, name, value, pos) VALUES (?, ?, ?, ?)', $club->id, $note_name, $note, $pos);
		list ($note_id) = Db::record(get_label('note'), 'SELECT LAST_INSERT_ID()');
		$log_details =
			'name=' . $note_name .
			"<br>note=<br>" . $note;
		db_log('note', 'Created', $log_details, $note_id, $club->id);
		Db::commit();
	}
	else
	{
		list ($club_id) = Db::record(get_label('note'), 'SELECT club_id FROM club_info WHERE id = ?', $id);
		$club = get_club($club_id);
		
		if (isset($_POST['update']))
		{
			$note = $_POST['note'];
			
			Db::begin();
			Db::exec(get_label('note'), 'UPDATE club_info SET value = ? WHERE id = ? AND club_id = ?', $note, $id, $club->id);
			if (Db::affected_rows() > 0)
			{
				$log_details = "note=<br>" . $note;
				db_log('note', 'Changed', $log_details, $id, $club->id);
			}
			Db::commit();
		}
		else if (isset($_POST['up']))
		{
			Db::begin();
			
			list ($pos) = Db::record(get_label('note'), 'SELECT pos FROM club_info WHERE club_id = ? AND id = ?', $club->id, $id);
			list ($id1, $pos1) = Db::record(get_label('note'), 'SELECT id, pos FROM club_info WHERE club_id = ? AND pos < ? ORDER BY pos DESC LIMIT 1', $club->id, $pos);
			
			Db::exec(get_label('note'), 'UPDATE club_info SET pos = 0 WHERE id = ?', $id);
			Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos, $id1);
			Db::exec(get_label('note'), 'UPDATE club_info SET pos = ? WHERE id = ?', $pos1, $id);
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('note'), 'DELETE FROM club_info WHERE club_id = ? AND id = ?', $club->id, $id);
			if (Db::affected_rows() > 0)
			{
				db_log('note', 'Deleted', NULL, $id, $club->id);
			}
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