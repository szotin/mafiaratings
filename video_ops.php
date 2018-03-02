<?php

require_once 'include/session.php';
require_once 'include/video.php';
require_once 'include/view_game.php';

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
	print_r($_REQUEST);
	echo '</pre>';*/
	
	if (isset($_REQUEST['create']))
	{
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
		
		$post_time = time();
		if (isset($_REQUEST['time']))
		{
			date_default_timezone_set($_profile->clubs[$club_id]->timezone);
			$video_time = strtotime($_REQUEST['time']);
		}
		else
		{
			$video_time = $post_time;
		}
		
		if ($club_id == 0)
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		
		if (!isset($_profile->clubs[$club_id]))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		$video = get_youtube_id($_REQUEST['video']);
		$vtype = (int)$_REQUEST['vtype'];
		$lang = (int)$_REQUEST['lang'];
		
		if ($lang <= 0)
		{
			$lang = $_profile->user_def_lang;
		}
		else
		{
			$lang -= $lang & ($lang - 1);
		}
		
		$title = get_youtube_info($video)['title'];
		
		Db::begin();
		Db::exec(get_label('video'), 'INSERT INTO videos (name, video, type, club_id, event_id, lang, post_time, video_time, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$title, $video, $vtype, $club_id, $event_id, $lang, $post_time, $video_time, $_profile->user_id);
		list ($video_id) = Db::record(get_label('video'), 'SELECT LAST_INSERT_ID()');
		db_log('video', 'created', 'video: ' . $video . '; type: ' . $vtype . '; lang: ' . $lang, $video_id, $club_id);
		Db::commit();
	}
	else if (isset($_REQUEST['edit']))
	{
		$video_id = (int)$_REQUEST['edit'];
		
		list ($club_id, $user_id, $game_id, $type, $lang, $time) = Db::record(get_label('video'), 'SELECT v.club_id, v.user_id, g.id, v.type, v.lang, v.video_time FROM videos v LEFT OUTER JOIN games g ON g.video_id = v.id WHERE v.id = ?', $video_id);
		if (!$_profile->is_manager($club_id) && $_profile->user_id != $user_id)
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		if ($game_id != NULL)
		{
			throw new Exc(get_label('This video [1] is attached to the game #[0]. It can not be edited.', $game_id, $video_id));
		}
		
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
		if (isset($_REQUEST['lang']))
		{
			$l = (int)$_REQUEST['lang'];
			if (is_valid_lang($l))
			{
				$lang = $l;
			}
		}
		if (isset($_REQUEST['time']))
		{
			date_default_timezone_set($_profile->clubs[$club_id]->timezone);
			$t = strtotime($_REQUEST['time']);
			if ($t > 0)
			{
				$time = $t;
			}
		}
		
		Db::begin();
		Db::exec(get_label('video'), 'UPDATE videos SET type = ?, lang = ?, video_time = ? WHERE id = ?', $type, $lang, $time, $video_id);
		db_log('video', 'edited', 'type: ' . $type . '; lang: ' . $lang . '; time: ' . $time, $video_id, $club_id);
		Db::commit();
	}
	else if (isset($_REQUEST['remove']))
	{
		Db::begin();
		$video_id = (int)$_REQUEST['remove'];
		list ($club_id, $user_id, $old_video, $game_id) = Db::record(get_label('video'), 'SELECT v.club_id, v.user_id, v.video, g.id FROM videos v LEFT OUTER JOIN games g ON g.video_id = v.id WHERE v.id = ?', $video_id);
		if ($user_id != $_profile->user_id && !$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		Db::exec(get_label('game'), 'UPDATE games SET video_id = NULL WHERE video_id = ?', $video_id);
		Db::exec(get_label('video'), 'DELETE FROM videos WHERE id = ?', $video_id);
		if ($game_id == NULL)
		{
			db_log('video', 'deleted', 'Old video: ' . $old_video, $video_id, $club_id);
		}
		else
		{
			db_log('video', 'deleted', 'Old video: ' . $old_video . '; game: ' . $game_id, $video_id, $club_id);
		}
		Db::commit();
		
		if ($game_id != NULL)
		{
			reset_viewed_game($game_id);
		}
	}
	else if (isset($_REQUEST['set_game_video']))
	{
		$video = get_youtube_id($_REQUEST['set_game_video']);
		$title = get_youtube_info($video)['title'];
		$post_time = time();
		
		Db::begin();
		$game_id = $_REQUEST['game_id'];
		list($club_id, $event_id, $video_time, $lang, $old_video) = Db::record(get_label('game'), 'SELECT g.club_id, g.event_id, g.start_time, g.language, v.video FROM games g LEFT OUTER JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $game_id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		if ($old_video != NULL)
		{
			throw new Exc(get_label('Please remove old video first'));
		}
		
		Db::exec(get_label('video'), 'INSERT INTO videos (name, video, type, club_id, event_id, lang, post_time, video_time, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', $title, $video, VIDEO_TYPE_GAME, $club_id, $event_id, $lang, $post_time, $video_time, $_profile->user_id);
		list ($video_id) = Db::record(get_label('video'), 'SELECT LAST_INSERT_ID()');
		Db::exec(get_label('game'), 'UPDATE games SET video_id = ? WHERE id = ?', $video_id, $game_id);
		db_log('video', 'created', 'video: ' . $video . '; game: ' . $game_id, $video_id, $club_id);
		Db::commit();
		
		reset_viewed_game($game_id);
	}
	else if (isset($_REQUEST['tag']))
	{
		$video_id = (int)$_REQUEST['tag'];
		if (!isset($_REQUEST['user_id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = (int)$_REQUEST['user_id'];
		
		list ($club_id, $owner_id) = Db::record(get_label('video'), 'SELECT club_id, user_id FROM videos WHERE id = ?', $video_id);
		if ($owner_id != $_profile->user_id && !$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM user_videos WHERE video_id = ? AND user_id = ?', $video_id, $user_id);
		if ($count <= 0)
		{
			Db::begin();
			
			Db::exec(get_label('video'), 'INSERT INTO user_videos (user_id, video_id, tagged_by_id) VALUES (?, ?, ?)', $user_id, $video_id, $_profile->user_id);
			db_log('video', 'tagged', 'user_id: ' . $user_id, $video_id, $club_id);
			Db::commit();
		}
	}
	else if (isset($_REQUEST['untag']))
	{
		$video_id = (int)$_REQUEST['tag'];
		if (!isset($_REQUEST['user_id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = (int)$_REQUEST['user_id'];
		
		list ($club_id, $owner_id) = Db::record(get_label('video'), 'SELECT club_id, user_id FROM videos WHERE id = ?', $video_id);
		if ($user_id != $_profile->user_id && $owner_id != $_profile->user_id && !$_profile->is_manager($club_id))
		{
			throw new FatalExc(get_label('No permissions'));
		}
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM user_videos WHERE video_id = ? AND user_id = ?', $video_id, $user_id);
		if ($count > 0)
		{
			Db::begin();
			Db::exec(get_label('video'), 'DELETE FROM user_videos WHERE user_id = ? AND video_id = ?', $user_id, $video_id);
			db_log('video', 'untagged', 'user_id: ' . $user_id, $video_id, $club_id);
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
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>