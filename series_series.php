<?php

require_once 'include/general_page_base.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

define('FLAG_FILTER_EMPTY', 0x0001);
define('FLAG_FILTER_NOT_EMPTY', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NOT_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $this->filter);
		}
		echo '</td></tr></table></p>';
		
		$condition = new SQL(
			' FROM series_series ss'.
			' JOIN series s ON s.id = ss.child_id' .
			' JOIN leagues l ON l.id = s.league_id'.
			' WHERE ss.parent_id = ?', $this->id);
		if ($this->future)
		{
			$condition->add(' AND s.start_time + s.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND s.start_time < UNIX_TIMESTAMP()');
			
			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT t.tournament_id FROM series_tournaments t WHERE t.series_id = s.id)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT t.tournament_id FROM series_tournaments t WHERE t.series_id = s.id)');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0');
			}
		}
		
		echo '<div class="tab">';
		echo '<button ' . ($this->future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$this->future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		list ($count) = Db::record(get_label('sеriеs'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, s.start_time, s.duration, l.id, l.name, l.flags,' .
			' (SELECT count(*) FROM series_tournaments WHERE series_id = s.id) as tournaments',
			$condition);
		if ($this->future)
		{
			$query->add(' ORDER BY s.start_time, s.id');
		}
		else
		{
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2" align="center">' . get_label('Sеriеs') . '</td>';
		echo '<td width="60" align="center">' . get_label('Tournaments') . '</td></tr>';
		
		$timezone = get_timezone();
		$now = time();
		$series_pic = new Picture(SERIES_PICTURE);
		$league_pic = new Picture(LEAGUE_PICTURE);
		while ($row = $query->next())
		{
			list ($series_id, $series_name, $series_flags, $series_time, $series_duration, $league_id, $league_name, $league_flags, $tournaments_count) = $row;
			$playing =($now >= $series_time && $now < $series_time + $series_duration);
			if ($playing)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="60" class="dark" align="center" valign="center">';
			$series_pic->set($series_id, $series_name, $series_flags);
			$series_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			$league_pic->set($league_id, $league_name, $league_flags);
			$league_pic->show(ICONS_DIR, false, 40);
			echo '</td><td>';
			echo '<b><a href="series_standings.php?bck=1&id=' . $series_id . '">' . $series_name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</b><br>' . format_date('F d, Y', $series_time, $timezone) . '</a></td>';
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center"><a href="series_tournaments.php?bck=1&id=' . $series_id . '">' . $tournaments_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Subseries'));

?>