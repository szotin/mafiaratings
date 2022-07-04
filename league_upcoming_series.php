<?php

require_once 'include/league.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/series.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends LeaguePageBase
{
	protected function show_body()
	{
		global $_page, $_lang_code, $_profile;
		
		$is_manager = is_permitted(PERMISSION_LEAGUE_MANAGER, $this->id);
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$series_count = 0;
		$column_count = 0;
		
		$condition = new SQL('s.league_id = ? AND UNIX_TIMESTAMP() <= s.start_time + s.duration', $this->id);
		if ($is_manager)
		{
			--$page_size;
			++$series_count;
			++$column_count;
		}
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*) FROM series s WHERE ', $condition);
		show_pages_navigation($page_size, $count);
		
		if ($is_manager)
		{
			echo '<table class="bordered light" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr class="light"></tr><tr><td align="center"><a href="#" onclick="mr.createSeries(' . $this->id . ')">' . get_label('Create [0]', get_label('sеriеs'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		
		$query = new DbQuery('SELECT s.id, s.name, s.start_time, s.duration, s.flags FROM series s WHERE ', $condition);
		$query->add(' ORDER BY s.start_time LIMIT ' . ($_page * $page_size) . ',' . $page_size);

		$timezone = get_timezone();
		$series_pic = new Picture(SERIES_PICTURE);
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $duration, $flags) = $row;
			if ($column_count == 0)
			{
				if ($series_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			if ($_profile != NULL)
			{
				echo '<tr><td class="dark" style="padding:2px;">';
				show_series_buttons($id, $start_time, $duration, $flags, $this->id, $this->flags, $this->id);
				echo '</td></tr>';	
			}
			
			echo '<tr><td align="center"><a href="series_info.php?bck=1&id=' . $id . '">' . format_date('F d, Y', $start_time, $timezone) . ' - ' . format_date('F d, Y', $start_time + $duration, $timezone) . '<br>';
			$series_pic->set($id, $name, $flags);
			$series_pic->show(ICONS_DIR, false);
			echo '</a><br><b>' . $name;
			echo '</b></td></tr></table>';
			
			echo '</td>';
			
			++$series_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($series_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Upcoming series'));

?>