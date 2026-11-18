<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/picture.php';
require_once 'include/user.php';
require_once 'include/cards.php';

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
		// The list of the v3 design, after ratings.php: the count and the button that adds a user
		// in a toolbar, the table in a sheet.
		echo '<div class="toolbar"><span class="count">' . get_label('[0] users', number_format($count)) . '</span>';
		if ($is_manager)
		{
			echo '<a class="btn tb-create" href="#" onclick="mr.addSeriesUser(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add user') . '</span></a>';
		}
		echo '</div>';
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr>';
		if ($is_manager)
		{
			echo '<th scope="col"></th>';
		}
		echo '<th scope="col">' . get_label('User') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Permissions') . '</th></tr></thead><tbody>';

		$user_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, sr.flags, c.id, nc.name, c.flags, ni.name' .
			' FROM series_regs sr' .
			' JOIN users u ON u.id = sr.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
			' JOIN cities i ON i.id = u.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & ' . $_lang . ') <> 0' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
			' WHERE sr.series_id = ?' .
			' ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $flags, $reg_flags, $club_id, $club_name, $club_flags, $city) = $row;
			$reg_flags = (int)$reg_flags;

			echo '<tr>';
			if ($is_manager)
			{
				echo '<td class="tb-btns">';
				echo '<button class="icon" onclick="mr.removeSeriesUser(' . $this->id . ', ' . $id . ', \'' .
					get_label('Are you sure you want to remove [0] from [1]?', $name, $this->name) . '\')" title="' .
					get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editSeriesAccess(' . $id . ', ' . $this->id . ')" title="' .
					get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				echo '</td>';
			}

			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar($user_pic, $id, $name, $flags, '') .
				'<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . '</span></span></a></td>';
			echo '<td class="p3">' . card_club($club_pic, $club_id, $club_name, $club_flags) . '</td>';
			// The same three icons in the same order as on the club list, so a row reads the same
			// wherever permissions are shown. A permission the user does not have is a gap of the
			// same width rather than nothing, so the icons line up down the column. The exhibition
			// flag has no icon anywhere and is left to the dialog.
			echo '<td class="num perms">';
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
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Users'));

?>
