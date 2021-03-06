<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/tournament.php';
require_once 'include/image.php';
require_once 'include/languages.php';

define('NUM_COLUMNS', 10);
define('COLUMN_COUNT', 4);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends TournamentPageBase
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
		
		$langs = $this->langs;
		if (isset($_REQUEST['langs']))
		{
			$langs = (int)$_REQUEST['langs'];
		}
		else if ($_profile != NULL)
		{
			$langs = $_profile->user_langs;
		}
		
		$page_size = NUM_COLUMNS * COLUMN_COUNT;
		$video_count = 0;
		$column_count = 0;
		$can_add = $_profile != NULL && isset($_profile->clubs[$this->club_id]);
		
		if ($can_add)
		{
			--$page_size;
			++$video_count;
			++$column_count;
		}
		
		list ($count) = Db::record(get_label('video'), 'SELECT count(*) FROM videos WHERE tournament_id = ? AND type = ? AND (lang & ?) <> 0', $this->id, $this->video_type, $langs);
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation($page_size, $count);
		echo '</td><td align="right">';
		if (!is_valid_lang($this->langs))
		{
			langs_checkboxes($langs, $this->langs, NULL, ' ', '', 'filter()');
		}
		echo '</tr></table></p>';
		
		if ($can_add)
		{
			echo '<table class="bordered light" width="100%">';
			echo '<tr><td align="center" width="' . COLUMN_WIDTH . '%"><a href="#" onclick="mr.createVideo(' . $this->video_type . ', ' . $this->club_id . ', ' . $this->id , ')">';
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '" title="' . get_label('Add [0]', get_label('video')) . '">';
			echo '</td>';
		}
		
		$query = new DbQuery(
			'SELECT v.id, v.video, v.name, v.lang, g.id, c.id, c.name, c.flags, e.id, e.name, e.flags FROM videos v' .
			' JOIN clubs c ON c.id = v.club_id' .
			' LEFT OUTER JOIN tournaments e ON e.id = v.tournament_id' .
			' LEFT OUTER JOIN games g ON g.video_id = v.id' .
			' WHERE v.tournament_id = ? AND v.type = ? AND (v.lang & ?) <> 0 ORDER BY v.post_time DESC, v.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size, $this->id, $this->video_type, $langs);
		while ($row = $query->next())
		{
			list($video_id, $video, $title, $lang, $game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags) = $row;
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
			
			echo '<p><span style="position:relative;">';
			echo '<a href="video.php?bck=1&id=' . $video_id . '&tournament_id=' . $this->id . '&vtype=' . $this->video_type . '&langs=' . $langs . '"><img src="https://img.youtube.com/vi/' . $video . '/0.jpg" width="200" title="' . $title . '">';
			if (!is_valid_lang($this->langs))
			{
				echo '<img src="images/' . ICONS_DIR . 'lang' . $lang . '.png" title="' . $title . '" width="24" style="position:absolute; margin-left:-28px;">';
			}
			echo '</a></span></p><p>';
			if ($game_id != NULL)
			{
				echo get_label('Game [0]: [1]', $game_id, $title);
			}
			else
			{
				echo $title;
			}

			echo '</p></td>';
			
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