<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_page;
		
		$year = 0;
		if (isset($_REQUEST['year']))
		{
			$year = (int)$_REQUEST['year'];
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$min_time = $max_time = 0;
		$query = new DbQuery('SELECT MIN(game_end_time), MAX(game_end_time) FROM players WHERE user_id = ?', $this->id);
		if ($row = $query->next())
		{
			list($min_time, $max_time) = $row;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_year_select($year, $min_time, $max_time, 'filterChanged()');
		echo ' ';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '</td></tr></table></p>';
		
		$condition = get_year_condition($year);
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND g.is_rating <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND g.is_rating = 0');
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, count(g.id) as gcount, SUM(p.rating_earned) as rating, SUM(p.won) as gwon FROM players p' .
			' JOIN games g ON p.game_id = g.id' .
			' JOIN users u ON g.moderator_id = u.id' .
			' WHERE p.user_id = ? AND g.is_canceled = FALSE AND g.result > 0',
			$this->id, $condition);
		$query->add(' GROUP BY u.id ORDER BY gcount DESC, rating DESC, gwon DESC, p.user_id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Moderator') . '</td>';
		echo '<td width="80" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Wins').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Rating per game').'</td></tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $rating, $games_won) = $row;

			echo '<tr><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '<td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			
			echo '<td align="center" class="dark">' . number_format($rating, 2) . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({filter: checkboxFilterFlags(), year: $('#year').val(), page: 0});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Moderators'));

?>