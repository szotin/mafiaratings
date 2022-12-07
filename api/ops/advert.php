<?php

require_once '../../include/api.php';
require_once '../../include/club.php';
require_once '../../include/email.php';
require_once '../../include/message.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	private static function get_timestamp($datetime, $timezone)
	{
		if (is_numeric($datetime))
		{
			return (int)$datetime;
		}
		
		date_default_timezone_set($timezone);
		return strtotime($datetime);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile, $_lang;
		
		$club_id = (int)get_required_param('club_id');
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		$raw_message = get_required_param('message');
		$message = prepare_message($raw_message);
		$start = ApiPage::get_timestamp(get_optional_param('start', time()), $club->timezone);
		if ($start <= 0)
		{
			throw new Exc(get_label('Invalid start time'));
		}
		
		$end = ApiPage::get_timestamp(get_optional_param('end', time() + 2 * 7 * 24 * 60 *60), $club->timezone);
		if ($end <= 0)
		{
			throw new Exc(get_label('Invalid end time'));
		}
		
		$lang = (int)get_optional_param('lang', 0);
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($raw_message);
			if (!is_valid_lang($lang))
			{
				$lang = $_lang;
			}
		}
		
		Db::begin();
		Db::exec(
			get_label('advert'),
			'INSERT INTO news (club_id, timestamp, raw_message, message, lang, expires) VALUES (?, ?, ?, ?, ?, ?)', 
			$club_id, $start, $raw_message, $message, $lang, $end);
		list ($advert_id) = Db::record(get_label('advert'), 'SELECT LAST_INSERT_ID()');
		$log_details = new stdClass();
		$log_details->lang = $lang;
		db_log(LOG_OBJECT_ADVERT, 'created', $log_details, $advert_id, $club_id);
		Db::commit();
		$this->response['advert_id'] = $advert_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Create advertisement.');
		$help->request_param('club_id', 'Club id. Required.');
		$help->request_param('message', 'Advertizement message text.');
		$help->request_param('start', 'Time when the message will start apearing in the club main page. It is either unix timestamp or time string in format (php datetime format) "Y-m-d H:i" ("2018-06-23 17:33").', 'the message starts being showed immediatly.');
		$help->request_param('end', 'Expiration time.  It is either unix timestamp or time string in format (php datetime format) "Y-m-d H:i" ("2018-06-23 17:33").', 'the end is in two weeks from now');
		$help->request_param('lang', 'Advertisement language.' . valid_langs_help(), 'auto-detected by analyzing message character codes.');

		$help->response_param('advert_id', 'Newly created advertisement id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile, $_lang;
		
		$advert_id = (int)get_required_param('advert_id');
		list ($club_id, $raw_message, $message, $start, $end, $lang) = Db::record(get_label('advert'), 'SELECT club_id, raw_message, message, timestamp, expires, lang FROM news WHERE id = ?', $advert_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		$club = $_profile->clubs[$club_id];
		
		if (isset($_REQUEST['message']))
		{
			$raw_message = $_REQUEST['message'];
			$message = prepare_message($raw_message);
		}
		
		$start = ApiPage::get_timestamp(get_optional_param('start', $start), $club->timezone);
		if ($start <= 0)
		{
			throw new Exc(get_label('Invalid start time'));
		}
		
		$end = ApiPage::get_timestamp(get_optional_param('end', $end), $club->timezone);
		if ($end <= 0)
		{
			throw new Exc(get_label('Invalid end time'));
		}
		
		
		$lang = get_optional_param('lang', $lang);
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($message);
			if (!is_valid_lang($lang))
			{
				$lang = $_lang;
			}
		}
		
		Db::begin();
		Db::exec(get_label('advert'), 'UPDATE news SET raw_message = ?, message = ?, lang = ?, timestamp = ?, expires = ? WHERE id = ?', $raw_message, $message, $lang, $start, $end, $advert_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->lang = $lang;
			db_log(LOG_OBJECT_ADVERT, 'changed', $log_details, $advert_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Change advertisement.');
		$help->request_param('advert_id', 'Advertisement id.');
		$help->request_param('message', 'Advertizement message text.', 'remains the same.');
		$help->request_param('start', 'Time when the message will start apearing in the club main page. It is either unix timestamp or time string in format (php style) "Y-m-d H:i" ("2018-06-23 17:33").', 'the message starts being showed immediatly.', 'remains the same.');
		$help->request_param('end', 'Expiration time.  It is either unix timestamp or time string in format (php style) "Y-m-d H:i" ("2018-06-23 17:33").', 'remains the same.');
		$help->request_param('lang', 'Advertisement language.<br><dfn>When 0:</dfn> auto-detected by analyzing message character codes.' . valid_langs_help(), 'remains the same.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$advert_id = (int)get_required_param('advert_id');
		list ($club_id) = Db::record(get_label('advert'), 'SELECT club_id FROM news WHERE id = ?', $advert_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		
		Db::begin();
		Db::exec(get_label('advert'), 'DELETE FROM news WHERE id = ?', $advert_id);
		db_log(LOG_OBJECT_ADVERT, 'deleted', NULL, $advert_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER, 'Delete advertisement.');
		$help->request_param('advert_id', 'Advertisement id.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Advertisement Operations', CURRENT_VERSION);

?>