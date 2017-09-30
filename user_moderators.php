<?php

require_once 'include/user.php';
require_once 'include/pages.php';

define("PAGE_SIZE",15);

class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] moderators', $this->name);
	}
	
	protected function show_body()
	{
		global $_page;
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM players p JOIN games g ON p.game_id = g.id WHERE g.result IN(1,2) AND p.user_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, count(g.id) as gcount, SUM(p.rating_earned) as rating, SUM(p.won) as gwon FROM players p' .
			' JOIN games g ON p.game_id = g.id' .
			' JOIN users u ON g.moderator_id = u.id' .
			' WHERE g.result IN(1,2) AND p.user_id = ?' .
			' GROUP BY u.id ORDER BY rating DESC, gcount, gwon DESC, p.user_id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Moderator') . '</td>';
		echo '<td width="80" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Rating per game').'</td></tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $games_played, $rating, $games_won) = $row;

			echo '<tr><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50"><a href="user_games.php?id=' . $id . '&moder=1&bck=1">';
			show_user_pic($id, $name, $flags, ICONS_DIR, 50, 50);
			echo '</a><td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			
			echo '<td align="center" class="dark">' . number_format($rating) . '</td>';
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
}

$page = new Page();
$page->run(get_label('Moderators'), PERM_ALL);

?>