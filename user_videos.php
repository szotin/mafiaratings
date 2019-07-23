<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/user.php';
require_once 'include/image.php';
require_once 'include/languages.php';

define('NUM_COLUMNS', 10);
define('COLUMN_COUNT', 4);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends UserPageBase
{
	private $video_type;
	
	protected function prepare()
	{
		parent::prepare();
		
		$this->video_type = VIDEO_TYPE_LEARNING;
		if (isset($_REQUEST['vtype']))
		{
			$this->video_type = (int)$_REQUEST['vtype'];
		}
		
		switch ($this->video_type)
		{
			case VIDEO_TYPE_LEARNING:
				$this->_title = get_label('Learning Videos');
				break;
			case VIDEO_TYPE_GAME:
				$this->_title = get_label('Game Videos');
				break;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$page_size = NUM_COLUMNS * COLUMN_COUNT;
		$video_count = 0;
		$column_count = 0;
		
		list ($count1) = Db::record(get_label('video'), 'SELECT count(*) FROM user_videos u JOIN videos v ON v.id = u.video_id WHERE u.user_id = ? AND v.type = ?', $this->id, $this->video_type);
		list ($count2) = Db::record(get_label('video'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id JOIN videos v ON v.id = g.video_id WHERE p.user_id = ? AND v.type = ?', $this->id, $this->video_type);
		$count = $count1 + $count2;
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation($page_size, $count);
		echo '</td><td align="right">';
		echo '</tr></table></p>';
		
		$query = new DbQuery();
		if ($this->video_type == VIDEO_TYPE_GAME)
		{
			$query->add('(');
		}
		$query->add(
			'SELECT v.id as video_id, v.video, v.name, v.lang, v.post_time as post_time, g.id, c.id, c.name, c.flags, e.id, e.name, e.flags FROM user_videos u' .
			' JOIN videos v ON u.video_id = v.id' .
			' JOIN clubs c ON c.id = v.club_id' .
			' LEFT OUTER JOIN events e ON e.id = v.event_id' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE u.user_id = ? AND v.type = ?', $this->id, $this->video_type);
		if ($this->video_type == VIDEO_TYPE_GAME)
		{
			$query->add(
				') UNION (SELECT v.id as video_id, v.video, v.name, v.lang, v.post_time as post_time, g.id, c.id, c.name, c.flags, e.id, e.name, e.flags FROM players p' .
				' JOIN games g ON p.game_id = g.id' .
				' JOIN videos v ON g.video_id = v.id' .
				' JOIN clubs c ON c.id = v.club_id' .
				' LEFT OUTER JOIN events e ON e.id = v.event_id' .
				' WHERE p.user_id = ? AND v.type = ?)', $this->id, $this->video_type);
		}
		$query->add(' ORDER BY post_time DESC, video_id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		while ($row = $query->next())
		{
			list($video_id, $video, $title, $lang, $post_time, $game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags) = $row;
			if ($column_count == 0)
			{
				if ($video_count == 0)
				{
					echo '<table class="bordered" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td valign="bottom"';
			echo ' width="' . COLUMN_WIDTH . '%" align="center" valign="center">';
			
			echo '<p><span style="position:relative;">';
			echo '<a href="video.php?bck=1&id=' . $video_id . '&user_id=' . $this->id . '&vtype=' . $this->video_type . '"><img src="https://img.youtube.com/vi/' . $video . '/0.jpg" width="200" title="' . $title . '">';
			if (!is_valid_lang($this->langs))
			{
				echo '<img src="images/' . ICONS_DIR . 'lang' . $lang . '.png" title="' . $title . '" width="24" style="position:absolute; margin-left:-28px;">';
			}
			echo '</a></span></p>';

			echo '</td>';
			
			++$video_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($video_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td class="light" colspan="' . (COLUMN_COUNT - $column_count) . '"></td>';
			}
			echo '</tr></table>';
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter()
		{
			goTo({ 'langs': mr.getLangs() });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Videos'));

?>