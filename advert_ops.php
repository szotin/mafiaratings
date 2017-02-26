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
		
		$message = str_replace('\"', '"', $_POST['message']);
		$expires = $_POST['expires'];
		if (isset($_POST['lang']))
		{
			$lang = $_POST['lang'];
		}
		else
		{
			$lang = detect_lang($message);
			if ($lang == LANG_NO)
			{
				$lang = $_profile->user_def_lang;
			}
		}
		
		Db::begin();
		Db::exec(
			get_label('advert'),
			'INSERT INTO news (club_id, timestamp, message, lang, expires) VALUES (?, UNIX_TIMESTAMP(), ?, ?, ?)', 
			$club->id, $message, $lang, $expires);
		list ($advert_id) = Db::record(get_label('advert'), 'SELECT LAST_INSERT_ID()');
		$log_details = 'lang=' . $lang;
		db_log('advert', 'Created', $log_details, $advert_id, $club->id);
		Db::commit();
	}
	else
	{
		if (!isset($_POST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('advert')));
		}
		$id = $_POST['id'];
	
		list ($club_id) = Db::record(get_label('advert'), 'SELECT club_id FROM news WHERE id = ?', $id);
		$club = get_club($club_id);
		
		if (isset($_POST['update']))
		{
			$message = str_replace('\"', '"', $_POST['message']);
			$starts = $_POST['starts'];
			$expires = $_POST['expires'];
			if (isset($_POST['lang']))
			{
				$lang = $_POST['lang'];
			}
			else
			{
				$lang = detect_lang($message);
				if ($lang == LANG_NO)
				{
					$lang = $_profile->user_def_lang;
				}
			}
			
			Db::begin();
			Db::exec(get_label('advert'), 'UPDATE news SET message = ?, lang = ?, timestamp = ?, expires = ? WHERE id = ?', $message, $lang, $starts, $expires, $id);
			if (Db::affected_rows() > 0)
			{
				$log_details = 'lang=' . $lang;
				db_log('advert', 'Changed', $log_details, $id, $club->id);
			}
			Db::commit();
		}
		else if (isset($_POST['delete']))
		{
			Db::begin();
			Db::exec(get_label('advert'), 'DELETE FROM news WHERE id = ?', $id);
			db_log('advert', 'Deleted', NULL, $id, $club->id);
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