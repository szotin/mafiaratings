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
		
		$message = str_replace('\"', '"', get_required_param('message'));
		$starts = (int)get_optional_param('starts', time());
		$expires = (int)get_required_param('expires');
		$lang = (int)get_optional_param('lang', 0);
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($message);
			if (!is_valid_lang($lang))
			{
				$lang = $_profile->user_def_lang;
			}
		}
		
		Db::begin();
		Db::exec(
			get_label('advert'),
			'INSERT INTO news (club_id, timestamp, message, lang, expires) VALUES (?, UNIX_TIMESTAMP(), ?, ?, ?)', 
			$club_id, $message, $lang, $expires);
		list ($advert_id) = Db::record(get_label('advert'), 'SELECT LAST_INSERT_ID()');
		$log_details = 'lang=' . $lang;
		db_log('advert', 'Created', $log_details, $advert_id, $club_id);
		Db::commit();
		$this->response['advert_id'] = $advert_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp('Create advertisement.');
		$help->request_param('club_id', 'Club id. Required.');
		$help->request_param('message', 'Advertizement message text.');
		$help->request_param('starts', 'Unix timestamp for the time when the message will start apearing in the club main page.', 'the message starts being showed immediatly.');
		$help->request_param('expires', 'Unix timestamp for the expiration time.');
		$help->request_param('lang', 'Advertisement language. 1 (English) or 2 (Russian). Other languages are not supported yet.', 'auto-detected by analyzing message character codes.');

		$help->response_param('advert_id', 'Newly created advertisement id.');
		return $help;
	}
	
	function create_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$advert_id = (int)get_required_param('advert_id');
		list ($club_id, $message, $starts, $expires, $lang) = Db::record(get_label('advert'), 'SELECT club_id, message, timestamp, expires, lang FROM news WHERE id = ?', $advert_id);
		$this->check_permissions($club_id);
		
		if (isset($_REQUEST['message']))
		{
			$message = str_replace('\"', '"', $_REQUEST['message']);
		}
		$starts = get_optional_param('starts', $starts);
		$expires = get_optional_param('expires', $expires);
		$lang = get_optional_param('lang', $lang);
		if (!is_valid_lang($lang))
		{
			$lang = detect_lang($message);
			if (!is_valid_lang($lang))
			{
				$lang = $_profile->user_def_lang;
			}
		}
		
		Db::begin();
		Db::exec(get_label('advert'), 'change news SET message = ?, lang = ?, timestamp = ?, expires = ? WHERE id = ?', $message, $lang, $starts, $expires, $advert_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = 'lang=' . $lang;
			db_log('advert', 'Changed', $log_details, $advert_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp('Change advertisement.');
		$help->request_param('advert_id', 'Advertisement id.');
		$help->request_param('message', 'Advertizement message text.', 'remains the same.');
		$help->request_param('starts', 'Unix timestamp for the time when the message will start apearing in the club main page.', 'remains the same.');
		$help->request_param('expires', 'Unix timestamp for the expiration time.', 'remains the same.');
		$help->request_param('lang', 'Advertisement language. 1 (English) or 2 (Russian). Other languages are not supported yet.<br><dfn>When 0:</dfn> auto-detected by analyzing message character codes.', 'remains the same.');
		return $help;
	}
	
	function change_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$advert_id = (int)get_required_param('advert_id');
		list ($club_id) = Db::record(get_label('advert'), 'SELECT club_id FROM news WHERE id = ?', $advert_id);
		$this->check_permissions($club_id);
		
		Db::begin();
		Db::exec(get_label('advert'), 'DELETE FROM news WHERE id = ?', $advert_id);
		db_log('advert', 'Deleted', NULL, $advert_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp('Delete advertisement.');
		$help->request_param('advert_id', 'Advertisement id.');
		return $help;
	}
	
	function delete_op_permissions()
	{
		return API_PERM_FLAG_MANAGER;
	}
}

$page = new ApiPage();
$page->run('Advertisement Operations', CURRENT_VERSION);

?>