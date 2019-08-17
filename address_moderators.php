<?php

require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/club.php';

define("PAGE_SIZE",15);

class Page extends AddressPageBase
{
	protected function show_body()
	{
		global $_page;
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g JOIN events e ON e.id = g.event_id WHERE e.address_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, SUM(IF(g.result = 1, 1, 0)) AS civ, SUM(IF(g.result = 2, 1, 0)) AS maf, c.id, c.name, c.flags' .
				' FROM users u' .
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN events e ON g.event_id = e.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' WHERE e.address_id = ?' .
				' GROUP BY u.id ORDER BY count(g.id) DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games moderated').'</td>';
		echo '<td width="100" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Mafia wins').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $civil_wins, $mafia_wins, $club_id, $club_name, $club_flags) = $row;

			echo '<tr><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50"><a href="user_games.php?id=' . $id . '&moder=1&bck=1">';
			$this->user_pic->set($id, $name, $flags);
			$this->user_pic->show(ICONS_DIR, 50);
			echo '</a><td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, 40);
			}
			echo '</td>';
			
			$games = $civil_wins + $mafia_wins;
			
			echo '<td align="center" class="dark">' . $games . '</td>';
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
	}
}

$page = new Page();
$page->run(get_label('Moderators'));

?>