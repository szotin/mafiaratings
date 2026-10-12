<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/picture.php';
require_once 'include/user.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

// The people of a series: its managers and its referees, and the players it has something to say
// about - exhibition players, who are in its competitions but out of its standings.
//
// A manager of the series may do with it everything the managers of the league or the club running
// it may do, and nothing beyond it: the tournaments of the series are run by their own clubs, and a
// subseries is a series of its own with its own people. A referee of the series referees every
// tournament played in it, and no tournament of a subseries - see is_tournament_series_referee().
class Page extends SeriesPageBase
{
	protected function show_body()
	{
		global $_lang, $_page;

		$is_manager = is_series_manager($this->owner, $this->id);
		check_series_permissions($this->owner, $this->id);

		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM series_regs WHERE series_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		if ($is_manager)
		{
			echo '<td width="58">';
			echo '<button class="icon" onclick="mr.addSeriesUser(' . $this->id . ')" title="' . get_label('Add [0]', get_label('user')) . '"><img src="images/create.png" border="0"></button>';
			echo '</td>';
		}
		echo '<td colspan="3">' . get_label('User') . '</td>';
		echo '<td width="130">' . get_label('Permissions') . '</td></tr>';

		$user_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, sr.flags, c.id, c.name, c.flags, ni.name' .
			' FROM series_regs sr' .
			' JOIN users u ON u.id = sr.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' WHERE sr.series_id = ?' .
			' ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $flags, $reg_flags, $club_id, $club_name, $club_flags, $city) = $row;
			$reg_flags = (int)$reg_flags;

			echo '<tr class="light">';
			if ($is_manager)
			{
				echo '<td class="dark">';
				echo '<button class="icon" onclick="mr.removeSeriesUser(' . $this->id . ', ' . $id . ', \'' .
					get_label('Are you sure you want to remove [0] from [1]?', $name, $this->name) . '\')" title="' .
					get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editSeriesAccess(' . $id . ', ' . $this->id . ')" title="' .
					get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				echo '</td>';
			}

			echo '<td width="60" align="center">';
			$user_pic->set($id, $name, $flags);
			$user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1"><b>' . $name . '</b><br>' . $city . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$club_pic->set($club_id, $club_name, $club_flags);
				$club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';

			// The same three icons in the same order as on the club list, so a row reads the same
			// wherever permissions are shown. A permission the user does not have is a gap of the
			// same width rather than nothing, so the icons line up down the column. The exhibition
			// flag has no icon anywhere and is left to the dialog.
			echo '<td>';
			if ($reg_flags & USER_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($reg_flags & USER_PERM_REFEREE)
			{
				echo '<img src="images/referee.png" width="32" title="' . get_label('Referee') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($reg_flags & USER_PERM_MANAGER)
			{
				echo '<img src="images/manager.png" width="32" title="' . get_label('Manager') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Users'));

?>
