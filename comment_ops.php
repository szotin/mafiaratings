<?php

require_once 'include/session.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/address.php';

ob_start();
$result = array();

try
{
	initiate_session();
	if ($_profile == NULL)
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	
	if (!isset($_REQUEST['id']) || !isset($_REQUEST['object']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('object')));
	}
	
	if (!isset($_REQUEST['comment']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('comment')));
	}
	
	$id = $_REQUEST['id'];
	$object = $_REQUEST['object'];
	$comment = prepare_message($_REQUEST['comment']);
	$lang = detect_lang($comment);
	if ($lang == LANG_NO)
	{
		$lang = $_profile->user_def_lang;
	}
	
	Db::begin();
	if ($object == 'event')
	{
		Db::exec(get_label('comment'), 'INSERT INTO event_comments (time, user_id, comment, event_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $id, $lang);
		
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('event'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM events e' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE e.id = ?', $id);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN event_users eu ON u.id = eu.user_id' .
			' WHERE eu.coming_odds > 0 AND eu.event_id = ?)' .
			' UNION DISTINCT ' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM users u' .
			' JOIN event_comments c ON c.user_id = u.id' .
			' WHERE c.event_id = ?)', $id, $id);
		// echo $query->get_parsed_sql();
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
			if ($user_id == $_profile->user_id || ($user_flags & U_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
		
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id;
			$tags = array(
				'uid' => new Tag($user_id),
				'eid' => new Tag($event_id),
				'ename' => new Tag($event_name),
				'edate' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
				'etime' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'uname' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email_comment_event.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_EVENT, $event_id, $code);
		}
	}
	else if ($object == 'photo')
	{
		Db::exec(get_label('comment'), 'INSERT INTO photo_comments (time, user_id, comment, photo_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $id, $lang);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM user_photos p JOIN users u ON u.id = p.user_id WHERE p.tag > 0 AND p.photo_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM photo_comments c JOIN users u ON c.user_id = u.id WHERE c.photo_id = ?)',
			$id, $id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
		
			if ($user_id == $_profile->user_id || ($user_flags & U_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
			
			$code = generate_email_code();
			$server = get_server_url() . '/';
			$request_base = $server . 'email_request.php?code=' . $code . '&uid=' . $user_id;
			$image_url = $server . PHOTOS_DIR . TNAILS_DIR . $id . '.jpg';
			
			$tags = array(
				'uid' => new Tag($user_id),
				'code' => new Tag($code),
				'uname' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base . '/email_request.php?code=' . $code . '&uid=' . $user_id),
				'photo' => new Tag('<a href="' . $request_base . '&pid=' . $id . '"><img src="' . $image_url . '" border="0" width="' . EVENT_PHOTO_WIDTH . '"></a>'),
				'unsub' => new Tag('<a href="' . $request_base . '/email_request.php?code=' . $code . '&uid=' . $user_id . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email_comment_photo.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_PHOTO, $id, $code);
		}
	}
	else if ($object == 'game')
	{
		Db::exec(get_label('comment'), 'INSERT INTO game_comments (time, user_id, comment, game_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $id, $lang);
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('game'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM games g' .
				' JOIN events e ON g.event_id = e.id' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE g.id = ?', $id);
		
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM players p JOIN users u ON u.id = p.user_id WHERE p.game_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM game_comments c JOIN users u ON c.user_id = u.id WHERE c.game_id = ?)',
			$id, $id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
		
			if ($user_id == $_profile->user_id || ($user_flags & U_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
			
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&uid=' . $user_id;
			$tags = array(
				'uid' => new Tag($user_id),
				'gid' => new Tag($id),
				'eid' => new Tag($event_id),
				'ename' => new Tag($event_name),
				'edate' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
				'etime' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'uname' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email_comment_game.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_GAME, $id, $code);
		}
	}
	else
	{
		throw new Exception(get_label('Unknown [0]', get_label('object')));
	}
	Db::commit();
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
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>