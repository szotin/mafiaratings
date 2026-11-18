<?php

require_once 'include/league.php';
require_once 'include/pages.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends LeaguePageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM league_managers WHERE league_id = ?', $this->id);
		// The list of the v3 design, after ratings.php: the count and the button that adds a user
		// in a toolbar, the table in a sheet.
		echo '<div class="toolbar"><span class="count">' . get_label('[0] users', number_format($count)) . '</span>';
		echo '<a class="btn tb-create" href="#" onclick="mr.addLeagueUser(' . $this->id . '); return false;" title="' . get_label('Add [0] to [1]', get_label('user'), $this->name) . '">' . PageBase::icon('plus') . '<span>' . get_label('Add user') . '</span></a>';
		echo '</div>';
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th scope="col"></th>';
		echo '<th scope="col">' . get_label('User') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Email') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Permissions') . '</th></tr></thead><tbody>';

		$user_pic = new Picture(USER_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, m.flags, c.id, nc.name, c.flags, ni.name' .
			' FROM league_managers m' .
			' JOIN users u ON m.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' WHERE m.league_id = ?' .
			' ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $league_flags, $club_id, $club_name, $club_flags, $city) = $row;
			$league_flags = (int)$league_flags;
		
			echo '<tr>';
			echo '<td class="tb-btns">';
			echo '<button class="icon" onclick="mr.removeLeagueUser(' . $this->id . ', ' . $id . ', \'' . get_label('Are you sure you want to remove [0] from [1]?', $name, $this->name) . '\')" title="' . get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editLeagueAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
			echo '</td>';
			
			echo '<td><a class="who" href="user_info.php?id=' . $id . '&bck=1">' . card_avatar($user_pic, $id, $name, $flags, '') .
				'<span class="who-txt"><span class="who-name">' . $name . '</span><span class="who-sub">' . $city . '</span></span></a></td>';
			echo '<td class="p2 dim">';
			if (is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
			{
				echo $email;
			}
			echo '</td>';
			echo '<td class="p3">' . card_club($club_pic, $club_id, $club_name, $club_flags) . '</td>';
						// A permission the user does not have is a gap of the same width rather than nothing, so
			// the icons line up down the column.
			echo '<td class="num perms">';
			if ($league_flags & USER_PERM_REFEREE)
			{
				echo '<img src="images/referee.png" width="32" title="' . get_label('Referee') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($league_flags & USER_PERM_MANAGER)
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