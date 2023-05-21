<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/address.php';
require_once 'include/image.php';
require_once 'include/languages.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('PICTURE_WIDTH', (CONTENT_WIDTH / COLUMN_COUNT) - 10);

class Page extends AddressPageBase
{
	private $video_type;
	
	protected function prepare()
	{
		parent::prepare();
		
		$this->video_type = -1;
		if (isset($_REQUEST['vtype']))
		{
			$this->video_type = (int)$_REQUEST['vtype'];
		}
		$this->_title = get_videos_title($this->video_type);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$langs = $this->club_langs;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		else if ($_profile != NULL)
		{
			$langs = $_profile->user_langs;
		}
		
		$condition = new SQL(' AND (v.lang & ?) <> 0', $langs);
		if ($this->video_type >= 0)
		{
			$condition->add(' AND v.type = ?', $this->video_type);
		}
		
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$video_count = 0;
		$column_count = 0;
		
		list ($count) = Db::record(get_label('video'), 'SELECT count(*) FROM videos v JOIN events e ON v.event_id = e.id WHERE e.address_id = ?', $this->id, $condition);
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_video_type_select($this->video_type, 'vtype', 'filter()');
		echo '</td><td align="right">';
		langs_checkboxes($langs, $this->club_langs, NULL, ' ', '', 'filter()');
		echo '</tr></table></p>';
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT v.id, v.video, v.name, v.lang, v.type, g.id, c.id, c.name, c.flags, e.id, e.name, e.flags FROM videos v' .
			' JOIN events e ON e.id = v.event_id' .
			' JOIN clubs c ON c.id = e.club_id' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE e.address_id = ?', $this->id, $condition);
		$query->add(' ORDER BY v.video_time DESC, v.post_time DESC, v.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		while ($row = $query->next())
		{
			list($video_id, $video, $title, $lang, $type, $game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags) = $row;
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
			
			echo '<td valign="top"';
			echo ' width="' . COLUMN_WIDTH . '%" align="center" valign="center">';
			
			echo '<table width="100%" class="transp"><tr class="darker" style="height: 30px;" align="center"><td><b>';
			if (is_null($game_id))
			{
				echo get_video_title($type);
			}
			else
			{
				echo get_label('Game [0]', $game_id);
			}
			echo '</b></td></tr>';
			
			echo '<tr><td><span style="position:relative;">';
			echo '<a href="video.php?bck=1&id=' . $video_id . '&address_id=' . $this->id . '&vtype=' . $this->video_type . '&langs=' . $langs . '"><img src="https://img.youtube.com/vi/' . $video . '/0.jpg" width="' . PICTURE_WIDTH . '" title="' . $title . '">';
			if (!is_valid_lang($this->club_langs))
			{
				echo '<span class="video-lang">' . get_short_lang_str($lang) . '</span>';
			}
			echo '</a></span></td></tr>';
			echo '<tr><td align="center">' . $title . '</td></tr>';
			echo '</table>';

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
		show_pages_navigation($page_size, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter()
		{
			goTo({ 'langs': mr.getLangs(), vtype: $('#vtype').val(), page: 0 });
		}
		
<?php	
	}
}

$page = new Page();
$page->run(get_label('Videos'));

?>