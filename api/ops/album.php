<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/image.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	
	// ....................... Copied from advert.php. Not much changed. Will be implemented later.
	// function create_op()
	// {
		// global $_profile;
		
		// $club_id = (int)get_required_param('club_id');
		// check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		// $club = $_profile->clubs[$club_id];
		
		// $raw_message = get_required_param('message');
		// $message = prepare_message($raw_message);
		// $start = ApiPage::get_timestamp(get_optional_param('start', time()), $club->timezone);
		// if ($start <= 0)
		// {
			// throw new Exc(get_label('Invalid start time'));
		// }
		
		// $end = ApiPage::get_timestamp(get_required_param('end'), $club->timezone);
		// if ($end <= 0)
		// {
			// throw new Exc(get_label('Invalid end time'));
		// }
		
		// $lang = (int)get_optional_param('lang', 0);
		// if (!is_valid_lang($lang))
		// {
			// $lang = detect_lang($raw_message);
			// if (!is_valid_lang($lang))
			// {
				// $lang = $_profile->user_def_lang;
			// }
		// }
		
		// Db::begin();
		// Db::exec(
			// get_label('photo album'),
			// 'INSERT INTO news (club_id, timestamp, raw_message, message, lang, expires) VALUES (?, ?, ?, ?, ?, ?)', 
			// $club_id, $start, $raw_message, $message, $lang, $end);
		// list ($album_id) = Db::record(get_label('photo album'), 'SELECT LAST_INSERT_ID()');
		// $log_details = new stdClass();
		// $log_details->lang = $lang;
		// db_log(LOG_OBJECT_PHOTO_ALBUM, 'created', $log_details, $album_id, $club_id);
		// Db::commit();
		// $this->response['album_id'] = $album_id;
	// }
	
	// function create_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create photo album.');
		// $help->request_param('club_id', 'Club id. Required.');
		// $help->request_param('message', 'ALbum message text.');
		// $help->request_param('start', 'Time when the message will start apearing in the club main page. It is either unix timestamp or time string in format (php datetime format) "Y-m-d H:i" ("2018-06-23 17:33").', 'the message starts being showed immediatly.');
		// $help->request_param('end', 'Expiration time.  It is either unix timestamp or time string in format (php datetime format) "Y-m-d H:i" ("2018-06-23 17:33").');
		// $help->request_param('lang', 'Album language. 1 (English) or 2 (Russian). Other languages are not supported yet.', 'auto-detected by analyzing message character codes.');

		// $help->response_param('album_id', 'Newly created album id.');
		// return $help;
	// }

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$album_id = (int)get_required_param('album_id');
		list ($club_id, $user_id, $old_flags) = Db::record(get_label('photo album'), 'SELECT club_id, user_id, flags FROM photo_albums WHERE id = ?', $album_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
		
		// Only upload is implemented yet.
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . ALBUM_PICS_DIR, $album_id);
			
			$flags = $old_flags;
			$icon_version = (($flags & ALBUM_ICON_MASK) >> ALBUM_ICON_MASK_OFFSET) + 1;
			if ($icon_version > ALBUM_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~ALBUM_ICON_MASK) + ($icon_version << ALBUM_ICON_MASK_OFFSET);
		}
		
		Db::begin();
		Db::exec(get_label('photo album'), 'UPDATE photo_albums SET flags = ? WHERE id = ?', $flags, $album_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
				$log_details->logo_uploaded = true;
			}
			db_log(LOG_OBJECT_PHOTO_ALBUM, 'changed', $log_details, $album_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change photo album.');
		$help->request_param('album_id', 'Album id.');
		$help->request_param('logo', 'Png or jpeg file to be uploaded for multicast multipart/form-data.', "remains the same");
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$album_id = (int)get_required_param('album_id');
		list ($club_id, $user_id) = Db::record(get_label('photo album'), 'SELECT club_id, user_id FROM photo_albums WHERE id = ?', $album_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
		
		Db::begin();
		Db::exec(get_label('photo album'), 'DELETE FROM photo_albums WHERE id = ?', $album_id);
		db_log(LOG_OBJECT_PHOTO_ALBUM, 'deleted', NULL, $album_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Delete photo album.');
		$help->request_param('album_id', 'Album id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Photo Album Operations', CURRENT_VERSION);

?>