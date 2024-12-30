<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/datetime.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		$timezone = get_timezone();
		
		$condition = new SQL(' FROM series_places sp JOIN series s ON s.id = sp.series_id WHERE sp.user_id = ?', $this->id);
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND s.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND s.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_date_filter();
		echo '</td></tr></table></p>';
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$series_pic = new Picture(SERIES_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, s.start_time, s.duration, s.langs, sp.place, sp.score, sp.tournaments, sp.games, sp.wins',
			$condition);
		$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		$now = time();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2" align="center">' . get_label('Series') . '</td>';
		echo '<td width="60" align="center">' . get_label('Place') . '</td>';
		echo '<td width="60" align="center">' . get_label('Tournaments played') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		while ($row = $query->next())
		{
			list ($series_id, $series_name, $series_flags, $series_time, $series_duration, $languages, $place, $score, $tournaments_count, $games_count, $wins_count) = $row;

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
			echo '</b><br>' . format_date_period($series_time, $series_duration, $timezone) . '</a></td>';
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center" class="dark">';
			if (!is_null($place))
			{
				if ($place > 0 && $place < 4)
				{
					echo '<img src="images/' . $place . '-place.png" width="48" title="' . get_label('[0] place', $place) . '">';
				}
				else if ($place < 11)
				{
					echo '<b>' . $place . '</b>';
				}
				else
				{
					echo $place;
				}
			}
			echo '</td>';
			
			echo '<td align="center">' . $tournaments_count . '</td>';
			echo '<td align="center">' . $games_count . '</td>';
			echo '<td align="center">' . $wins_count . '</td>';
			if ($games_count > 0)
			{
				echo '<td align="center">' . number_format(($wins_count*100.0)/$games_count, 1) . '%</td>';
			}
			else
			{
				echo '<td></td>';
			}
			
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>