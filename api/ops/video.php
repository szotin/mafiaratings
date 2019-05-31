<?php

require_once '../../include/api.php';
require_once '../../include/video.php';
require_once '../../include/view_game.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		
		$club_id = 0;
		if (isset($_REQUEST['club_id']))
		{
			$club_id = (int)$_REQUEST['club_id'];
		}
		
		$event_id = NULL;
		if (isset($_REQUEST['event_id']))
		{
			$event_id = (int)$_REQUEST['event_id'];
			if ($club_id == 0)
			{
				list($club_id) = Db::record(get_label('club'), 'SELECT club_id FROM events WHERE id = ?', $event_id);
			}
		}
		
		if ($club_id == 0)
		{
			// No localization because this is an assert. The calling code must fix it.
			throw new Exc('Neither "event_id" nor "club_id" are set in ' . $this->title . ': create');
		}
		check_permissions(PERMISSION_CLUB_MEMBER, $club_id);
		
		$club = $_profile->clubs[$club_id];
		
		$post_time = time();
		if (isset($_REQUEST['time']))
		{
			date_default_timezone_set(get_timezone());
			$video_time = strtotime($_REQUEST['time']);
		}
		else
		{
			$video_time = $post_time;
		}
		
		$vtype = VIDEO_TYPE_LEARNING;
		if (isset($_REQUEST['vtype']))
		{
			$t = (int)$_REQUEST['vtype'];
			switch ($t)
			{
				case VIDEO_TYPE_LEARNING:
				case VIDEO_TYPE_GAME:
					$vtype = $t;
					break;
			}
		}
		
		get_youtube_id(get_required_param('video'), $video, $vtime);
		
		$lang = 0;
		if (isset($_REQUEST['lang']))
		{
			$lang = (int)$_REQUEST['lang'];
		}
		if (!is_valid_lang($lang, $club->langs))
		{
			$lang = $_profile->user_def_lang;
		}
		if (!is_valid_lang($lang, $club->langs))
		{
			$lang = $club->langs;
			$lang -= $lang & ($lang - 1);
		}
		
		$info = get_youtube_info($video);
		if (isset($info['title']))
		{
			$title = $info['title'];
		}
		else
		{
			$title = 'Video';
		}
		
		Db::begin();
		Db::exec(get_label('video'), 'INSERT INTO videos (name, video, type, club_id, event_id, lang, post_time, video_time, user_id, vtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$title, $video, $vtype, $club_id, $event_id, $lang, $post_time, $video_time, $_profile->user_id, $vtime);
		list ($video_id) = Db::record(get_label('video'), 'SELECT LAST_INSERT_ID()');
		
		$log_details = new stdClass();
		$log_details->video = $video;
		$log_details->type = $vtype;
		$log_details->lang = $lang;
		$log_details->time = $video_time;
		db_log(LOG_OBJECT_VIDEO, 'created', $log_details, $video_id, $club_id);
		
		Db::commit();
		
		$this->response['video_id'] = $video_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MEMBER, 'Create a new youtube video reference on ' . PRODUCT_NAME . '.');
		
		$help->request_param(
			'club_id', 
			'Id of the club this video belongs to.', 
			'<q>event_id</q> must be set. A video must belong to a club or event.');
		$help->request_param(
			'event_id', 
			'Id of the event this video belongs to.', 
			'<q>club_id</q> must be set. A video must belong to a club or event.');
		$help->request_param(
			'time', 
			'Unix timestamp of the time when this video was recorded.', 
			'current time is used.');
		$help->request_param(
			'video', 
			'Youtube URL of the video, or youtube id of the video. Youtube id can be found in the youtube URL - this is "v" parameter. For example: the id for this video <a href="https://www.youtube.com/watch?v=PtS2YqyKAwI" target="_blank">https://www.youtube.com/watch?v=PtS2YqyKAwI</a> is <q>PtS2YqyKAwI</q>.');
		$help->request_param(
			'vtype', 
			'Type of the video. Currently two values are supported: 
				<ul>
					<li>0 - learning video (default): a video containing masterclass, or a seminar, or etc.</li>
					<li>1 - game video: a video containing a game.</li>
				</ul>',
				'0 (learning video) is used.');
		$help->request_param(
			'lang', 
			'Language of the video. 1 (English) or 2 (Russian). Other languages/values are not supported yet.', 
			PRODUCT_NAME . ' tries to guess the language using languages supported by the club, and default account language.');
		
		$help->response_param(
			'video_id',
			'Id of the newly created video.');
			
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		
		$video_id = (int)get_required_param('video_id');
		
		list ($club_id, $user_id, $game_id, $old_type, $old_lang, $old_time) = Db::record(get_label('video'), 'SELECT v.club_id, v.user_id, g.id, v.type, v.lang, v.video_time FROM videos v LEFT OUTER JOIN games g ON g.video_id = v.id WHERE v.id = ?', $video_id);
		if (!$_profile->is_club_manager($club_id) && $_profile->user_id != $user_id)
		{
			throw new FatalExc(get_label('No permissions'));
		}
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $user_id);
		
		if ($game_id != NULL)
		{
			throw new Exc(get_label('This video [1] is attached to the game #[0]. It can not be edited.', $game_id, $video_id));
		}
		
		$type = $old_type;
		if (isset($_REQUEST['vtype']))
		{
			$t = (int)$_REQUEST['vtype'];
			switch ($t)
			{
				case VIDEO_TYPE_LEARNING:
				case VIDEO_TYPE_GAME:
					$type = $t;
					break;
			}
		}
		
		$lang = $old_lang;
		if (isset($_REQUEST['lang']))
		{
			$l = (int)$_REQUEST['lang'];
			if (is_valid_lang($l))
			{
				$lang = $l;
			}
		}
		
		$time = $old_time;
		if (isset($_REQUEST['time']))
		{
			date_default_timezone_set(get_timezone());
			$t = strtotime($_REQUEST['time']);
			if ($t > 0)
			{
				$time = $t;
			}
		}
		
		Db::begin();
		Db::exec(get_label('video'), 'UPDATE videos SET type = ?, lang = ?, video_time = ? WHERE id = ?', $type, $lang, $time, $video_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($time != $old_time)
			{
				$log_details->time = $time;
			}
			if ($type != $old_type)
			{
				$log_details->type = $vtype;
			}
			if ($lang != $old_lang)
			{
				$log_details->lang = $lang;
			}
			db_log(LOG_OBJECT_VIDEO, 'changed', $log_details, $video_id, $club_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, 'Change an existing youtube video reference on ' . PRODUCT_NAME . '.');
		
		$help->request_param('video_id', 'Id of the video.');
		$help->request_param('lang', 'Language of the video. 1 for English; 2 for Russian. Other languages/values are not supported.', 'remains the same');
		$help->request_param('time', 'Unix timestamp of the time when this video was recorded.', 'remains the same');
		$help->request_param('vtype', 'Type of the video.  Currently two values are supported: 
				<ul>
					<li>0 - learning video: a video containing masterclass, or a seminar, or etc.</li>
					<li>1 - game video: a video containing a game.</li>
				</ul>', 'remains the same');
		
		$help->response_param(
			'video_id',
			'Id of the newly created video.');
			
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$video_id = (int)get_required_param('video_id');
		
		Db::begin();
		list ($club_id, $user_id, $old_video, $game_id) = Db::record(get_label('video'), 'SELECT v.club_id, v.user_id, v.video, g.id FROM videos v LEFT OUTER JOIN games g ON g.video_id = v.id WHERE v.id = ?', $video_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $user_id);

		Db::exec(get_label('game'), 'UPDATE games SET video_id = NULL WHERE video_id = ?', $video_id);
		Db::exec(get_label('video'), 'DELETE FROM user_videos WHERE video_id = ?', $video_id);
		Db::exec(get_label('video'), 'DELETE FROM videos WHERE id = ?', $video_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_VIDEO, 'deleted', NULL, $video_id, $club_id);
		}
		Db::commit();
	}

	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, 'Delete youtube video reference on ' . PRODUCT_NAME . '.');
		$help->request_param('video_id', 'Id of the video.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// game_video
	//-------------------------------------------------------------------------------------------------------
	function game_video_op()
	{
		global $_profile;
		
		$game_id = (int)get_required_param('game_id');
		get_youtube_id(get_required_param('video'), $video, $vtime);
		$info = get_youtube_info($video);
		if (isset($info['title']))
		{
			$title = $info['title'];
		}
		else
		{
			$title = 'Video';
		}
		
		$post_time = time();
		
		Db::begin();
		list($club_id, $event_id, $video_time, $lang, $old_video) = Db::record(get_label('game'), 'SELECT g.club_id, g.event_id, g.start_time, g.language, v.video FROM games g LEFT OUTER JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $game_id);
		check_permissions(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, $club_id);
		
		if ($old_video != NULL)
		{
			throw new Exc(get_label('Please remove old video first'));
		}
		
		Db::exec(get_label('video'), 'INSERT INTO videos (name, video, type, club_id, event_id, lang, post_time, video_time, user_id, vtime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $title, $video, VIDEO_TYPE_GAME, $club_id, $event_id, $lang, $post_time, $video_time, $_profile->user_id, $vtime);
		list ($video_id) = Db::record(get_label('video'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('game'), 'UPDATE games SET video_id = ? WHERE id = ?', $video_id, $game_id);
		
		$log_details = new stdClass();
		$log_details->video = $video;
		$log_details->game_id = $game_id;
		$log_details->title = $title;
		db_log(LOG_OBJECT_VIDEO, 'created', $log_details, $video_id, $club_id);
		
		Db::commit();
		
		$this->response['video_id'] = $video_id;
	}
	
	function game_video_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER, 'Create and assign new video to the existing game in ' . PRODUCT_NAME . '.');
		$help->request_param('game_id', 'Game id.');
		$help->request_param('video', 'Youtube URL of the video, or youtube id of the video. Youtube id can be found in the youtube URL - this is "v" parameter. For example: the id for this video <a href="https://www.youtube.com/watch?v=PtS2YqyKAwI" target="_blank">https://www.youtube.com/watch?v=PtS2YqyKAwI</a> is <q>PtS2YqyKAwI</q>.');
		$help->response_param('video_id', 'Id of the newly created video.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// tag
	//-------------------------------------------------------------------------------------------------------
	function tag_op()
	{
		global $_profile;
		
		$video_id = (int)get_required_param('video_id');
		$user_id = (int)get_required_param('user_id');
		
		list ($club_id, $owner_id) = Db::record(get_label('video'), 'SELECT club_id, user_id FROM videos WHERE id = ?', $video_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $club_id, $owner_id);
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM user_videos WHERE video_id = ? AND user_id = ?', $video_id, $user_id);
		if ($count <= 0)
		{
			Db::begin();
			
			Db::exec(get_label('video'), 'INSERT INTO user_videos (user_id, video_id, tagged_by_id) VALUES (?, ?, ?)', $user_id, $video_id, $_profile->user_id);
			
			$log_details = new stdClass();
			$log_details->user_id = $user_id;
			db_log(LOG_OBJECT_VIDEO, 'tagged', $log_details, $video_id, $club_id);
			
			Db::commit();
		}
	}
	
	function tag_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Tag a user on the video.');
		$help->request_param('video_id', 'Id of the video.');
		$help->request_param('user_id', 'Id of the user.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// untag
	//-------------------------------------------------------------------------------------------------------
	function untag_op()
	{
		$video_id = (int)get_required_param('video_id');
		$user_id = (int)get_required_param('user_id');
		
		list ($club_id, $owner_id) = Db::record(get_label('video'), 'SELECT club_id, user_id FROM videos WHERE id = ?', $video_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $club_id, $owner_id);
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM user_videos WHERE video_id = ? AND user_id = ?', $video_id, $user_id);
		if ($count > 0)
		{
			Db::begin();
			Db::exec(get_label('video'), 'DELETE FROM user_videos WHERE user_id = ? AND video_id = ?', $user_id, $video_id);
			
			$log_details = new stdClass();
			$log_details->user_id = $user_id;
			db_log(LOG_OBJECT_VIDEO, 'untagged', $log_details, $video_id, $club_id);
			
			Db::commit();
		}
	}
	
	function untag_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Untag a user from the video.');
		$help->request_param('video_id', 'Id of the video.');
		$help->request_param('user_id', 'Id of the user.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		$id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_profile->user_def_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO video_comments (time, user_id, comment, video_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $id, $lang);
		
		list($video, $video_title) = Db::record(get_label('video'), 'SELECT video, name FROM videos WHERE id = ?' , $id);
		$query = new DbQuery(
			'(SELECT u.id, u.name, u.email, u.flags, u.def_lang FROM user_videos uv JOIN users u ON u.id = uv.user_id WHERE uv.video_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, u.name, u.email, u.flags, u.def_lang FROM video_comments c JOIN users u ON c.user_id = u.id WHERE c.video_id = ?)',
			$id, $id);
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
			$video_image = 'https://img.youtube.com/vi/' . $video . '/0.jpg';
			
			$tags = array(
				'root' => new Tag(get_server_url()),
				'code' => new Tag($code),
				'user_id' => new Tag($user_id),
				'user_name' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'video' => new Tag('<a href="' . $request_base . '"><img src="' . $video_image . '" border="0" width="' . EVENT_PHOTO_WIDTH . '" title="' . $video_title . '"></a>'),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email_comment_video.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_VIDEO, $id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Comment video.');
		$help->request_param('id', 'Id of the video.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Video Operations', CURRENT_VERSION);

?>