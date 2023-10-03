<?php

require_once '../../include/api.php';
require_once '../../include/message.php';
require_once '../../include/photo_album.php';
require_once '../../include/image.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$album_id = (int)get_required_param('album_id', 0);
		$album = new PhotoAlbum($album_id);
		if (!$album->can_add())
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		Db::exec(
			get_label('photo'), 
			'INSERT INTO photos (user_id, viewers, album_id) VALUES (?, ?, ?)', 
			$_profile->user_id, $album->viewers, $album->id);

		list ($id) = Db::record(get_label('photo'), 'SELECT LAST_INSERT_ID()');
		upload_photo('photo', '../../' . PHOTOS_DIR, $id);
		$this->response['photo_id'] = $id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_OWNER, 'Create a photo.');
		$help->request_param('album_id', 'Photo album this photo belongs to.');
		$help->request_param('photo', 'Photo file to be uploaded for multicast multipart/form-data.');
		$help->response_param('photo_id', 'Newly created photo id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$photo_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO photo_comments (time, user_id, comment, photo_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $photo_id, $lang);
		
		$query = new DbQuery(
			'(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM user_photos p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE p.tag > 0 AND p.photo_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM photo_comments c'.
			' JOIN users u ON c.user_id = u.id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE c.photo_id = ?)',
			$photo_id, $photo_id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
		
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
			
			$code = generate_email_code();
			$server = get_server_url() . '/';
			$request_base = $server . 'email_request.php?code=' . $code . '&user_id=' . $user_id;
			$image_url = $server . PHOTOS_DIR . TNAILS_DIR . $photo_id . '.jpg';
			
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'code' => new Tag($code),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base . '/email_request.php?code=' . $code . '&user_id=' . $user_id),
				'photo' => new Tag('<a href="' . $request_base . '&pid=' . $photo_id . '"><img src="' . $image_url . '" border="0" width="' . EVENT_PHOTO_WIDTH . '"></a>'),
				'unsub' => new Tag('<a href="' . $request_base . '/email_request.php?code=' . $code . '&user_id=' . $user_id . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_photo.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_PHOTO, $photo_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Comment photo.');
		$help->request_param('id', 'Photo id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Photo Operations', CURRENT_VERSION);

?>