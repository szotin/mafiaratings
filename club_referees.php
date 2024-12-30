<?php

require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</td></tr></table></p>';

		$condition = new SQL();
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
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND g.is_canceled <> 0');
		}
		if ($filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND g.is_canceled = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g WHERE g.club_id = ? AND is_canceled = FALSE AND result > 0', $this->id, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, SUM(IF(g.result = 1, 1, 0)), SUM(IF(g.result = 2, 1, 0)), c.id, c.name, c.flags, cu.flags FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON g.moderator_id = u.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN club_users cu ON cu.club_id = g.club_id AND cu.user_id = u.id' .
				' WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0',
			$this->id, $condition);
		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="20">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games refereed').'</td>';
		echo '<td width="100" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Mafia wins').'</td>';
		echo '</tr>';

		$club_user_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
		
		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $civil_wins, $mafia_wins, $club_id, $club_name, $club_flags, $club_user_flags) = $row;

			echo '<tr><td class="dark" align="center">' . $number . '</td>';
			echo '<td width="50">';
			$club_user_pic->set($id, $name, $club_user_flags, 'c' . $this->id)->set($id, $name, $flags);
			$club_user_pic->show(ICONS_DIR, true, 50);
			echo '<td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			$games = $civil_wins + $mafia_wins;
			
			echo '<td align="center">' . $games . '</td>';
			if ($civil_wins > 0)
			{
				echo '<td align="center">' . $civil_wins . ' (' . number_format(($civil_wins*100.0)/$games, 1) . '%)</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($mafia_wins > 0)
			{
				echo '<td align="center">' . $mafia_wins . ' (' . number_format(($mafia_wins*100.0)/$games, 1) . '%)</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
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