<?php

require_once 'include/general_page_base.php';
require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/video.php';

define('FILTER_NONE', 0);
define('FILTER_USER', 1);
define('FILTER_EVENT', 2);
define('FILTER_ADDRESS', 3);
define('FILTER_CLUB', 4);

class Page extends PageBase
{
	private $video_id;
	private $video;
	private $vtime;
	private $title;
	private $type;
	private $post_time;
	private $video_time;
	private $club_id;
	private $club_name;
	private $club_flags;
	private $event_id;
	private $event_name;
	private $event_flags;
	private $tour_id;
	private $tour_name;
	private $tour_flags;
	private $game_id;
	private $lang;
	private $user_id;
	
	protected function prepare()
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		
		$this->video_id = (int)$_REQUEST['id'];
		list(
			$this->video, $this->title, $this->type, $this->post_time, $this->video_time, $this->club_id, $this->club_name, $this->club_flags, 
			$this->event_id, $this->event_name, $this->event_flags, $this->tour_id, $this->tour_name, $this->tour_flags, $this->game_id, $this->lang, $this->user_id, $this->vtime) = 
			Db::record(get_label('video'), 
				'SELECT v.video, v.name, type, v.post_time, v.video_time, c.id, c.name, c.flags, e.id, e.name, e.flags, t.id, t.name, t.flags, g.id, v.lang, v.user_id, v.vtime FROM videos v ' .
				' JOIN clubs c ON c.id = v.club_id' .
				' LEFT OUTER JOIN events e ON e.id = v.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' LEFT OUTER JOIN games g ON g.video_id = v.id' .
				' WHERE v.id = ?', $this->video_id);
		
		if ($this->game_id != NULL)
		{
			$this->_title = get_label('Game [0]: [1] ([2])', $this->game_id, $this->title, format_date('j M Y', $this->video_time, get_timezone()));
		}
		else
		{
			$this->_title = get_label('[0] ([1])', $this->title, format_date('j M Y', $this->video_time, get_timezone()));
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr><td width="40">';
		show_language_picture($this->lang, ICONS_DIR, 24, 24);
		echo '</td><td>';
		echo $this->standard_title() . '</td><td align="right" valign="top">';
		show_back_button();
		echo '</td></tr></table>';	
	}
	
	private function get_video_url($video_id)
	{
		$url = get_page_url();
		$beg = strpos($url, 'id=');
		if ($beg === false)
		{
			return NULL;
		}
		$beg += 3;
		
		$end = strpos($url, '&', $beg);
		if ($end === false)
		{
			return substr($url, 0, $beg) . $video_id;
		}
		return substr($url, 0, $beg) . $video_id . substr($url, $end);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$type = $this->type;
		if (isset($_REQUEST['vtype']))
		{
			$type = (int)$_REQUEST['vtype'];
		}
		$condition = new SQL(' AND type = ?', $type);

		if (isset($_REQUEST['langs']))
		{
			$condition->add(' AND (lang & ?) <> 0', (int)$_REQUEST['langs']);
		}
		else if ($_profile != NULL)
		{
			$condition->add(' AND (lang & ?) <> 0', $_profile->user_langs);
		}
		
		if (isset($_REQUEST['user_id']))
		{
			$user_id = (int)$_REQUEST['user_id'];
			if ($type == VIDEO_TYPE_GAME)
			{
				$condition->add(' AND (id IN (SELECT video_id FROM user_videos WHERE user_id = ?) OR id IN (SELECT g.video_id FROM players p JOIN games g ON g.id = p.game_id WHERE g.video_id IS NOT NULL AND p.user_id = ?))', $user_id, $user_id);
			}
			else
			{
				$condition->add(' AND id IN (SELECT video_id FROM user_videos WHERE user_id = ?)', $user_id);
			}
		}
		
		if (isset($_REQUEST['tournament_id']))
		{
			$condition->add(' AND tournament_id = ?', (int)$_REQUEST['tournament_id']);
		}
		else if (isset($_REQUEST['event_id']))
		{
			$condition->add(' AND event_id = ?', (int)$_REQUEST['event_id']);
		}
		else if (isset($_REQUEST['address_id']))
		{
			$condition->add(' AND event_id IN (SELECT id FROM events WHERE address_id = ?)', (int)$_REQUEST['address_id']);
		}
		else if (isset($_REQUEST['club_id']))
		{
			$condition->add(' AND club_id = ?', (int)$_REQUEST['club_id']);
		}
		
		$prev_id = 0;
		$query = new DbQuery('SELECT id, video, name FROM videos WHERE id <> ? AND post_time >= ?', $this->video_id, $this->post_time, $condition);
		$query->add(' ORDER BY post_time, id LIMIT 1');
		if ($row = $query->next())
		{
			list($prev_id, $prev_video, $prev_title) = $row;
		}
		
		$next_id = 0;
		$query = new DbQuery('SELECT id, video, name FROM videos WHERE id <> ? AND post_time <= ?', $this->video_id, $this->post_time, $condition);
		$query->add(' ORDER BY post_time DESC, id DESC LIMIT 1');
		if ($row = $query->next())
		{
			list($next_id, $next_video, $next_title) = $row;
		}
		
		echo '<table class="bordered light" width="100%"><tr><td>';
		echo '<table class="transp" width="100%"><tr height="80"><td align="center">';
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		if ($this->event_id != NULL)
		{
			echo '<a href="event_info.php?bck=1&id=' . $this->event_id . '">';
			$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, $this->club_pic));
			$event_pic->
				set($this->event_id, $this->event_name, $this->event_flags)->
				set($this->tour_id, $this->tour_name, $this->tour_flags);
			$event_pic->show(ICONS_DIR, 64);
			echo '</a>';
		}
		else
		{
			echo '<a href="club_main.php?bck=1&id=' . $this->club_id . '">';
			$this->club_pic->show(ICONS_DIR, 64);
			echo '</a>';
		}
		echo '</td><td align="center" rowspan="2">';
		echo '<p><iframe title="' . $this->title . '" width="720" height="405" src="' . get_embed_video_url($this->video, $this->vtime) . '" frameborder="0" allowfullscreen></iframe></p>';
		echo '</td><td valign="top">';
		$game_icon = ($this->game_id != NULL);
		$can_manage = ($_profile != NULL && ($_profile->user_id == $this->user_id || $_profile->is_club_manager($this->club_id)));
		if ($game_icon || $can_manage)
		{
			echo '<table class="bordered darker" width="100%"><tr><td>';
			if ($can_manage)
			{
				if ($next_id > 0)
				{
					$url = $this->get_video_url($next_id);
				}
				else if ($prev_id > 0)
				{
					$url = $this->get_video_url($prev_id);
				}
				else
				{
					$url = get_back_page();
				}
				echo '<a onclick="mr.deleteVideo(' . $this->video_id . ', \'' . get_label('Are you sure you want to delete video?') . '\', \'' . $url . '\')"><img src="images/delete.png" style="margin:3px 3px" title="' . get_label("Delete video") . '"></a>';
			}
			if ($game_icon)
			{
				echo '<a href="view_game.php?bck=1&id=' . $this->game_id . '"><img src="images/game.png" style="margin:3px 3px" title="' . get_label("View game [0] log and stats on [1].", $this->game_id, PRODUCT_NAME) . '"></a>';
			}
			else if ($can_manage)
			{
				echo '<a onclick="mr.editVideo(' . $this->video_id . ')"><img src="images/edit.png" style="margin:3px 3px" title="' . get_label("Edit video") . '"></a>';
			}
			echo '</td></tr></table>';
		}
		echo '</td><tr>';
		echo '<td width="100" align="center">';
		if ($prev_id > 0)
		{
			$title = get_label('Previous video: [0]', $prev_title);
			echo '<span style="position:relative;"><a href="' . $this->get_video_url($prev_id) . '">';
			echo '<img src="https://img.youtube.com/vi/' . $prev_video . '/0.jpg" width="100" height="70" title="' . $title . '">';
			echo '<img src="images/prev.png" width="30" height="70" style="position:absolute; margin-left:-100px;">';
			echo '</a><span>';
		}
		echo '</td><td width="100" align="center">';
		if ($next_id > 0)
		{
			$title = get_label('Next video: [0]', $next_title);
			echo '<span style="position:relative;"><a href="' . $this->get_video_url($next_id) . '">';
			echo '<img src="https://img.youtube.com/vi/' . $next_video . '/0.jpg" width="100" height="70" title="' . $title . '">';
			echo '<img src="images/next.png" width="30" height="70" style="position:absolute; margin-left:-30px;">';
			echo '</a><span>';
		}
		echo '</td></tr></table></td><tr></table>';
		
		// placeholder for tagged users
		echo '<span id="tagged"></span>';
		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
	}
	

	protected function js_on_load()
	{
		echo 'mr.showVideoUsers(' . $this->video_id . ");\n";
		if ($this->game_id != NULL)
		{
			echo 'mr.showComments("game", ' . $this->game_id . ", 20, false, 'wide_comment')\n";
		}
		else
		{
			echo 'mr.showComments("video", ' . $this->video_id . ", 20, false, 'wide_comment')\n";
		}
	}
}

$page = new Page();
$page->run('Video');

?>