<?php

require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/club.php';

define("PAGE_SIZE",15);

class Page extends AddressPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] moderators', $this->name);
	}
	
	protected function show_body()
	{
		global $_page;
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g JOIN events e ON e.id = g.event_id WHERE e.address_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, SUM(IF(g.result = 1, 1, 0)) AS civ, SUM(IF(g.result = 2, 1, 0)) AS maf, SUM(IF(g.result = 3, 1, 0)) AS term FROM users u' .
				' JOIN games g ON g.moderator_id = u.id' .
				' JOIN events e ON g.event_id = e.id' .
				' WHERE e.address_id = ?' .
				' GROUP BY u.id ORDER BY count(g.id) DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games moderated').'</td>';
		echo '<td width="100" align="center">'.get_label('Games terminated').'</td>';
		echo '<td width="100" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Mafia wins').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $civil_wins, $mafia_wins, $terminated) = $row;

			echo '<tr><td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50"><a href="user_games.php?id=' . $id . '&moder=1&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a><td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			
			$games = $civil_wins + $mafia_wins;
			
			echo '<td align="center" class="dark">' . $games . '</td>';
			if ($terminated > 0)
			{
				echo '<td align="center">' . $terminated . ' (' . number_format(($terminated*100.0)/($games + $terminated), 1) . '%)</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td>';
			}
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
$page->run(get_label('Moderators'), PERM_ALL);

?>