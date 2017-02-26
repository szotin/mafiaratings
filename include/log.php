<?php

require_once 'include/db.php';

if (!isset($_profile))
{
	$_profile = NULL;
}

function db_log($obj, $message, $details, $obj_id = NULL, $club_id = NULL)
{
	global $_profile;
	
	$user_id = NULL;
	if ($_profile != NULL)
	{
		$user_id = $_profile->user_id;
	}
	
	$remote_adr = '127.0.0.1';
	if (isset($_SERVER['REMOTE_ADDR']))
	{
		$remote_adr = $_SERVER['REMOTE_ADDR'];
	}

	$page = '';
	if (isset($_SERVER['REQUEST_URI']))
	{
		$page = $_SERVER['REQUEST_URI'];
	}
	
	if (isset($_REQUEST))
	{
		$request = json_encode($_REQUEST);
		if ($details == NULL)
		{
			$details = $request;
		}
		else
		{
			$details .= '<hr>' . $request;
		}
	}
	
	Db::exec('log',
		'INSERT INTO log (time, obj, obj_id, ip, message, details, page, user_id, club_id) ' .
			'VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?)',
		$obj, $obj_id, $remote_adr, $message, $details, $page, $user_id, $club_id);
}

function prepare_log_objects()
{
	return array(
		'address' => 'address_info.php?bck=1&id=',
		'album' => 'album_photos.php?bck=1&id=',
		'city' => 'city_info.php?bck=1&id=',
		'club' => 'club_main.php?bck=1&id=',
		'club_request' => NULL,
		'country' => 'country_info.php?bck=1&id=',
		'email_template' => 'email_info.php?bck=1&id=',
		'error' => NULL,
		'event' => 'event_info.php?bck=1&id=',
		'event_emails' => 'view_event_mailing.php?bck=1&id=',
		'login' => NULL,
		'advert' => 'advert_info.php?bck=1&id=',
		'note' => NULL,
		'rules' => 'rules_info.php?bck=1&id=',
		'ratings' => NULL,
		'user' => 'user_info.php?bck=1&id=');
}

?>