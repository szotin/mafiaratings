<?php

require_once 'include/player_stats.php';
require_once 'include/league.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_EMPTY', 0x0001);
define('FLAG_FILTER_NOT_EMPTY', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NOT_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends LeaguePageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		$timezone = get_timezone();
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}
		
		$condition = new SQL(' FROM series s LEFT OUTER JOIN tournaments f ON f.id = s.finals_id WHERE s.league_id = ?', $this->id);
		if ($future)
		{
			$condition->add(' AND s.start_time + s.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND s.start_time < UNIX_TIMESTAMP()');
			
			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT t.id FROM series_tournaments ts JOIN tournaments t ON t.id = ts.tournament_id WHERE ts.series_id = s.id AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT t.id FROM series_tournaments ts JOIN tournaments t ON t.id = ts.tournament_id WHERE ts.series_id = s.id AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0)');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0');
			}
		}
			
		echo '<div class="tab">';
		echo '<button ' . ($future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		if (!$future)
		{
			echo '<p><table class="transp" width="100%"><tr><td>';
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $filter, 'filterSeries');
			echo '</td></tr></table></p>';
		}
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$series_pic = new Picture(SERIES_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, s.start_time, s.duration, s.langs,' .
			' (SELECT count(*) FROM series_tournaments _ts JOIN tournaments _t ON _t.id = _ts.tournament_id WHERE _ts.series_id = s.id AND (_t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0) as tournaments, f.id, f.name, f.flags',
			$condition);
		if ($future)
		{
			$query->add(' ORDER BY s.start_time, s.id');
		}
		else
		{
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		$now = time();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2" align="center">' . get_label('Sеriеs') . '</td>';
		echo '<td width="60" align="center">' . get_label('Tournaments') . '</td>';
		echo '<td width="60" align="center">' . get_label('Finals') . '</td>';
		while ($row = $query->next())
		{
			list ($series_id, $series_name, $series_flags, $series_time, $series_duration, $languages, $tournaments_count, $finals_id, $finals_name, $finals_flags) = $row;

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
			echo '<td><b><a href="series_standings.php?bck=1&id=' . $series_id . '">' . $series_name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</b><br>' . format_date('F d, Y', $series_time, $timezone) . '</a></td>';
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center"><a href="series_tournaments.php?bck=1&id=' . $series_id . '">' . $tournaments_count . '</a></td>';
			
			echo '<td align="center"><a href="tournament_standings.php?bck=1&id=' . $finals_id . '">';
			$tournament_pic->set($finals_id, $finals_name, $finals_flags);
			$tournament_pic->show(ICONS_DIR, true, 56);
			echo '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterSeries()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>