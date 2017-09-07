<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/user.php';

define("PAGE_SIZE",15);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page, $_profile;
		
		$condition = new SQL();
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' WHERE g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' WHERE g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' WHERE g.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' WHERE g.club_id IN (SELECT c.id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(DISTINCT g.moderator_id) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, SUM(IF(g.result = 1, 1, 0)), SUM(IF(g.result = 2, 1, 0)) FROM users u ' .
				'JOIN games g ON g.moderator_id = u.id',
			$condition);
		$query->add(' GROUP BY u.id ORDER BY count(g.id) DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('User name') . '</td>';
		echo '<td width="60" align="center">'.get_label('Games moderated').'</td>';
		echo '<td width="100" align="center">'.get_label('Civil wins').'</td>';
		echo '<td width="100" align="center">'.get_label('Mafia wins').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $flags, $civil_wins, $mafia_wins) = $row;

			echo '<tr><td class="dark" align="center">' . $number . '</td>';
			echo '<td width="50"><a href="user_games.php?id=' . $id . '&moder=1&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a><td><a href="user_games.php?id=' . $id . '&moder=1&bck=1">' . cut_long_name($name, 88) . '</a></td>';
			
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
$page->run(get_label('Moderators'), PERM_ALL);

?>