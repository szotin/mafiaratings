<?php

require_once __DIR__ . '/db.php';

define('LOG_OBJECT_ERROR', 'error');
define('LOG_OBJECT_LOGIN', 'login');
define('LOG_OBJECT_USER', 'user');
define('LOG_OBJECT_COUNTRY', 'country');
define('LOG_OBJECT_CITY', 'city');
define('LOG_OBJECT_LEAGUE', 'league');
define('LOG_OBJECT_LEAGUE_REQUEST', 'league request');
define('LOG_OBJECT_CLUB', 'club');
define('LOG_OBJECT_CLUB_REQUEST', 'club request');
define('LOG_OBJECT_RULES', 'rules');
define('LOG_OBJECT_SCORING_SYSTEM', 'scoring system');
define('LOG_OBJECT_SCORING_NORMALIZER', 'scoring normalizer');
define('LOG_OBJECT_CLUB_SEASON', 'club season');
define('LOG_OBJECT_LEAGUE_SEASON', 'league season');
define('LOG_OBJECT_ADDRESS', 'address');
define('LOG_OBJECT_TOURNAMENT', 'tournament');
define('LOG_OBJECT_EVENT', 'event');
define('LOG_OBJECT_GAME', 'game');
define('LOG_OBJECT_VIDEO', 'video');
define('LOG_OBJECT_PHOTO_ALBUM', 'photo album');
define('LOG_OBJECT_PHOTO', 'photo');
define('LOG_OBJECT_NOTE', 'note');
define('LOG_OBJECT_ADVERT', 'advert');
define('LOG_OBJECT_EVENT_MAILINGS', 'event emails');
define('LOG_OBJECT_STATS_CALCULATOR', 'stats calculator');
define('LOG_OBJECT_EXTRA_POINTS', 'extra points');
define('LOG_OBJECT_OBJECTION', 'objection');
define('LOG_OBJECT_SOUND', 'sound');

function prepare_log_objects()
{
	return array(
		LOG_OBJECT_ERROR => NULL
		, LOG_OBJECT_LOGIN => NULL
		, LOG_OBJECT_USER => 'user_info.php?bck=1&id='
		, LOG_OBJECT_COUNTRY => NULL
		, LOG_OBJECT_CITY => NULL
		, LOG_OBJECT_LEAGUE => 'league_main.php?bck=1&id='
		, LOG_OBJECT_LEAGUE_REQUEST => NULL
		, LOG_OBJECT_CLUB => 'club_main.php?bck=1&id='
		, LOG_OBJECT_CLUB_REQUEST => NULL
		, LOG_OBJECT_RULES => NULL
		, LOG_OBJECT_SCORING_SYSTEM => 'scoring.php?bck=1&id='
		, LOG_OBJECT_CLUB_SEASON => NULL
		, LOG_OBJECT_LEAGUE_SEASON => NULL
		, LOG_OBJECT_ADDRESS => 'address_info.php?bck=1&id='
		, LOG_OBJECT_TOURNAMENT => 'tournament_info.php?bck=1&id='
		, LOG_OBJECT_EVENT => 'event_info.php?bck=1&id='
		, LOG_OBJECT_GAME => 'view_game.php?bck=1&id='
		, LOG_OBJECT_VIDEO => 'video.php?bck=1&id='
		, LOG_OBJECT_PHOTO_ALBUM => 'album_photos.php?bck=1&id='
		, LOG_OBJECT_PHOTO => 'photo.php?bck=1&id='
		, LOG_OBJECT_NOTE => NULL
		, LOG_OBJECT_ADVERT => NULL
		, LOG_OBJECT_OBJECTION => NULL
		, LOG_OBJECT_EVENT_MAILINGS => 'view_event_mailing.php?bck=1&id='
		, LOG_OBJECT_STATS_CALCULATOR => 'stats_calculator.php?bck=1&id='
	);
}

function db_log($obj, $message, $details = NULL, $obj_id = NULL, $club_id = NULL, $league_id = NULL)
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
	
	if ($details != NULL)
	{
		if (is_string($details))
		{
			$details_obj = new stdClass();
			$details_obj->details = $details;
			$details = $details_obj;
		}
	}
	else
	{
		$details = new stdClass();
	}
	
	if (isset($_REQUEST))
	{
		$details->request = $_REQUEST;
	}
	
	Db::exec('log',
		'INSERT INTO log (time, obj, obj_id, ip, message, details, page, user_id, club_id, league_id) ' .
			'VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?, ?)',
		$obj, $obj_id, $remote_adr, $message, json_encode($details), $page, $user_id, $club_id, $league_id);
}

function cut_string($string, $cut_on)
{
	$pos = strpos($string, $cut_on);
	if ($pos !== false)
	{
		$string = substr($string, 0, $pos);
	}
	return $string;
}

function short_log_message($message)
{
	$message = cut_string($message, '<br');
	$message = cut_string($message, '<p>');
	$message = cut_string($message, '<p ');
	$message = cut_string($message, "\n");
	return $message;
}

function print_json($object)
{
	echo '<pre>';
	echo json_encode($object, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
	echo '</pre>';
}

?>