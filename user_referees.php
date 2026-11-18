<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/ccc_filter.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_page, $_lang, $_profile;
		
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
		
		// The filters in a toolbar, as on ratings.php; the three-way filters on a line of their own.
		echo '<div class="toolbar">';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</div>';
		
		$condition = new SQL();
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.id = ? OR i.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
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
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = ?', $this->id, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, count(g.id) as gcount, SUM(p.rating_earned) as rating, SUM(p.won) as gwon FROM players p' .
			' JOIN games g ON p.game_id = g.id' .
			' JOIN users u ON g.moderator_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE p.user_id = ?',
			$this->id, $condition);
		$query->add(' GROUP BY u.id ORDER BY gcount DESC, rating DESC, gwon DESC, p.user_id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		// The table of the v3 design, after ratings.php: the place, the referee with a round picture,
		// the numbers on the right.
		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Referee') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Rating earned') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Games played') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		echo '<th class="num p2" scope="col">' . get_label('Winning %') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Rating per game') . '</th>';
		echo '</tr></thead><tbody>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $rating, $games_won) = $row;

			echo '<tr><td class="rk"><span class="medal' . ($number <= 3 ? ' m' . $number : '') . '">' . sprintf('%02d', $number) . '</span></td>';
			echo '<td><a class="who" href="user_games.php?id=' . $id . '&moder=1&bck=1">' . card_avatar($this->user_pic, $id, $name, $flags, '') .
				'<span class="who-txt"><span class="who-name">' . cut_long_name($name, 88) . '</span></span></a></td>';
			echo '<td class="num strong">' . number_format($rating, 2) . '</td>';
			echo '<td class="num p3">' . $games_played . '</td>';
			echo '<td class="num p3">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td class="num p2">' . number_format(($games_won * 100.0) / $games_played, 1) . '%</td>';
				echo '<td class="num p4 dim">' . number_format($rating / $games_played, 2) . '</td>';
			}
			else
			{
				echo '<td class="num p2"></td><td class="num p4"></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({filter: checkboxFilterFlags(), page: 0});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Referees'));

?>