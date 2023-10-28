<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('FLAG_FILTER_RATING', 0x0001);
define('FLAG_FILTER_NO_RATING', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NO_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING | FLAG_FILTER_NO_CANCELED);

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<p>';
		echo '<table class="transp" width="100%"><tr><td>';
		show_checkbox_filter(array(get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</td></tr></table></p>';
		
		$condition = new SQL(' WHERE g.tournament_id = ? AND g.result > 0', $this->id);
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
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$moders = array();
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, SUM(IF(g.result = 1, 1, 0)), SUM(IF(g.result = 2, 1, 0)), c.id, c.name, c.flags, tu.flags, cu.flags' . 
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN games g ON g.moderator_id = u.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN tournament_users tu ON tu.tournament_id = g.tournament_id AND tu.user_id = u.id' .
				' LEFT OUTER JOIN club_users cu ON cu.club_id = g.club_id AND cu.user_id = u.id',
				$condition);
 		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC, u.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			$moder = new stdClass();
			list($moder->id, $moder->name, $moder->flags, $moder->red_wins, $moder->black_wins, $moder->club_id, $moder->club_name, $moder->club_flags, $moder->tournament_user_flags, $moder->club_user_flags) = $row;
			$moders[] = $moder;
		}
		
		$query = new DbQuery(
			'SELECT u.id, SUM(p.extra_points), SUM(IF((p.flags & ' . SCORING_FLAG_WORST_MOVE . ') = 0, 0, 1))' . 
				' FROM users u' .
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN players p ON p.game_id = g.id',
				$condition);
 		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC, u.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$i = 0;
		while ($row = $query->next())
		{
			$moder = $moders[$i++];
			list($id, $moder->bonus, $moder->worst_moves) = $row;
		}
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="20">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games refereed').'</td>';
		echo '<td width="100" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Mafia wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Bonus points').'</td>';
		echo '<td width="100" align="center">'.get_label('Removed auto-bonus').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		foreach ($moders as $moder)
		{
			++$number;

			echo '<tr><td align="center" width="40" class="dark">' . $number . '</td>';
			echo '<td width="50">';
			$tournament_user_pic->
				set($moder->id, $moder->name, $moder->tournament_user_flags, 't' . $this->id)->
				set($moder->id, $moder->name, $moder->club_user_flags, 'c' . $this->club_id)->
				set($moder->id, $moder->name, $moder->flags);
			$tournament_user_pic->show(ICONS_DIR, true, 50);
			echo '<td><a href="user_games.php?id=' . $moder->id . '&moder=1&bck=1">' . $moder->name . '</a></td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($moder->club_id, $moder->club_name, $moder->club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			
			$games = $moder->red_wins + $moder->black_wins;
			
			echo '<td align="center" class="dark">' . $games . '</td>';
			if ($moder->red_wins > 0)
			{
				echo '<td align="center">' . $moder->red_wins . ' (' . number_format(($moder->red_wins*100.0)/$games, 1) . '%)</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($moder->black_wins > 0)
			{
				echo '<td align="center">' . $moder->black_wins . ' (' . number_format(($moder->black_wins*100.0)/$games, 1) . '%)</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
			if ($moder->bonus > 0)
			{
				echo '<td align="center">' . number_format($moder->bonus, 1) . ' (' . number_format($moder->bonus/$games, 2) . ' per game)</td>';
			}
			else
			{
				echo '<td align="center">0</td>';
			}
			if ($moder->worst_moves > 0)
			{
				echo '<td align="center">' . $moder->worst_moves . ' (' . number_format($moder->worst_moves/$games, 2) . ' per game)</td>';
			}
			else
			{
				echo '<td align="center">0</td>';
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