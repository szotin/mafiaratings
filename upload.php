<?php

if (isset($_POST['PHPSESSID']))
{
	session_id($_POST['PHPSESSID']);
}

require_once 'include/session.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

try
{
	initiate_session();
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('object')));
	}
	$id = $_REQUEST['id'];
	
	if (!isset($_REQUEST['code']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('object type')));
	}
	$code = $_REQUEST['code'];
	
	Db::begin();
	switch ($code)
	{
	case ADDRESS_PIC_CODE:
		list ($club_id, $flags) = Db::record(get_label('address'), 'SELECT club_id, flags FROM addresses WHERE id = ?', $id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		upload_picture('Filedata', ADDRESS_PICS_DIR, $id);
		$icon_version = (($flags & ADDRESS_ICON_MASK) >> ADDRESS_ICON_MASK_OFFSET) + 1;
		if ($icon_version > ADDRESS_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~ADDRESS_ICON_MASK) + ($icon_version << ADDRESS_ICON_MASK_OFFSET);
		$flags &= ~ADDRESS_FLAG_GENERATED;
		
		Db::exec(get_label('address'), 'UPDATE addresses SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			list($club_id, $flags) = Db::record(get_label('address'), 'SELECT club_id, flags FROM addresses WHERE id = ?', $id);
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_PHOTO, 'uploaded', $log_details, $id, $club_id);
		}
		break;
		
	case USER_PIC_CODE:
		list ($club_id, $flags) = Db::record(get_label('user'), 'SELECT club_id, flags FROM users WHERE id = ?', $id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $id);
	
		upload_picture('Filedata', USER_PICS_DIR, $id);
		$icon_version = (($flags & USER_ICON_MASK) >> USER_ICON_MASK_OFFSET) + 1;
		if ($icon_version > USER_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~USER_ICON_MASK) + ($icon_version << USER_ICON_MASK_OFFSET);
		Db::exec(get_label('user'), 'UPDATE users SET flags = ? WHERE id = ?', $flags, $id);
		if ($_profile->user_id == $id)
		{
			$_profile->user_flags = $flags;
		}
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_USER, 'photo uploaded', $log_details, $id);
		}
		break;
	
	case CLUB_PIC_CODE:
		check_permissions(PERMISSION_CLUB_MANAGER, $id);
		upload_picture('Filedata', CLUB_PICS_DIR, $id);
		
		list ($flags) = Db::record(get_label('club'), 'SELECT flags FROM clubs WHERE id = ?', $id);
		$icon_version = (($flags & CLUB_ICON_MASK) >> CLUB_ICON_MASK_OFFSET) + 1;
		if ($icon_version > CLUB_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~CLUB_ICON_MASK) + ($icon_version << CLUB_ICON_MASK_OFFSET);
		
		Db::exec(get_label('club'), 'UPDATE clubs SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_CLUB, 'logo uploaded', $log_details, $id, $id);
		}
		break;
	
	case LEAGUE_PIC_CODE:
		check_permissions(PERMISSION_LEAGUE_MANAGER, $id);
		upload_picture('Filedata', LEAGUE_PICS_DIR, $id);
		
		list ($flags) = Db::record(get_label('league'), 'SELECT flags FROM leagues WHERE id = ?', $id);
		$icon_version = (($flags & LEAGUE_ICON_MASK) >> LEAGUE_ICON_MASK_OFFSET) + 1;
		if ($icon_version > LEAGUE_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~LEAGUE_ICON_MASK) + ($icon_version << LEAGUE_ICON_MASK_OFFSET);
		
		Db::exec(get_label('league'), 'UPDATE leagues SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_LEAGUE, 'logo uploaded', 'flags=' . $flags, $id, $id);
		}
		break;
	
	case ALBUM_PIC_CODE:
		list ($owner_id, $club_id, $flags) = Db::record(get_label('photo album'),'SELECT user_id, club_id, flags FROM photo_albums WHERE id = ?', $id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $owner_id);
	
		upload_picture('Filedata', ALBUM_PICS_DIR, $id);
		
		$icon_version = (($flags & ALBUM_ICON_MASK) >> ALBUM_ICON_MASK_OFFSET) + 1;
		if ($icon_version > ALBUM_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~ALBUM_ICON_MASK) + ($icon_version << ALBUM_ICON_MASK_OFFSET);
		
		Db::exec(get_label('photo album'), 'UPDATE photo_albums SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_PHOTO_ALBUM, 'logo uploaded', $log_details, $id, $club_id);
		}
		break;
		
	case EVENT_PIC_CODE:
		list ($club_id, $flags) = Db::record(get_label('event'), 'SELECT club_id, flags FROM events WHERE id = ?', $id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		upload_picture('Filedata', EVENT_PICS_DIR, $id);
		
		$icon_version = (($flags & EVENT_ICON_MASK) >> EVENT_ICON_MASK_OFFSET) + 1;
		if ($icon_version > EVENT_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~EVENT_ICON_MASK) + ($icon_version << EVENT_ICON_MASK_OFFSET);
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_EVENT, 'logo uploaded', $log_details, $id, $club_id);
		}
		break;
		
	case TOURNAMENT_PIC_CODE:
		list ($club_id, $flags) = Db::record(get_label('tournament'), 'SELECT club_id, flags FROM tournaments WHERE id = ?', $id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		upload_picture('Filedata', TOURNAMENT_PICS_DIR, $id);
		
		$icon_version = (($flags & TOURNAMENT_ICON_MASK) >> TOURNAMENT_ICON_MASK_OFFSET) + 1;
		if ($icon_version > TOURNAMENT_ICON_MAX_VERSION)
		{
			$icon_version = 1;
		}
		$flags = ($flags & ~TOURNAMENT_ICON_MASK) + ($icon_version << TOURNAMENT_ICON_MASK_OFFSET);
		
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = ? WHERE id = ?', $flags, $id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->flags = $flags;
			db_log(LOG_OBJECT_TOURNAMENT, 'logo uploaded', $log_details, $id, $club_id);
		}
		break;
		
	case PHOTO_CODE:
		$album = new PhotoAlbum($id);
		if (!$album->can_add())
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		Db::exec(
			get_label('photo'), 
			'INSERT INTO photos (user_id, viewers, album_id) VALUES (?, ?, ?)', 
			$_profile->user_id, $album->viewers, $album->id);

		list ($id) = Db::record(get_label('photo'), 'SELECT LAST_INSERT_ID()');
		upload_photo('Filedata', $id);
		break;
		
	default:
		throw new FatalExc(get_label('Unknown [0]', get_label('object type')));
	}
	Db::commit();
	echo 'ok';
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e, true);
	echo $e->getMessage();
}

?>