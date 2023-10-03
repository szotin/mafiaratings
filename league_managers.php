<?php

require_once 'include/league.php';
require_once 'include/pages.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends LeaguePageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
		
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM league_managers WHERE league_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="29">';
		echo '<button class="icon" onclick="mr.addLeagueManager(' . $this->id . ')" title="' . get_label('Add league manager') . '"><img src="images/create.png" border="0"></button>';
		echo '</td>';
		echo '<td colspan="4">' . get_label('Manager') . '</td></tr>';

		$user_pic = new Picture(USER_PICTURE);
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.email, u.flags, c.id, c.name, c.flags' .
			' FROM league_managers m' .
			' JOIN users u ON m.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' WHERE m.league_id = ?' .
			' ORDER BY nu.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $flags, $club_id, $club_name, $club_flags) = $row;
		
			echo '<tr class="light">';
			echo '<td class="dark">';
			echo '<button class="icon" onclick="mr.removeLeagueManager(' . $this->id . ', ' . $id . ', \'' . get_label('Are you sure you want to remove [0] from the league managers?', $name) . '\')" title="' . get_label('Remove [0] from the league managers', $name) . '"><img src="images/delete.png" border="0"></button>';
			echo '</td>';
			
			echo '<td width="60" align="center">';
			$user_pic->set($id, $name, $flags);
			$user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="200">';
			if (is_permitted(PERMISSION_CLUB_MANAGER, $club_id))
			{
				echo $email;
			}
			echo '</td>';
			echo '<td width="50" align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 40);
			echo '</td></tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Managers'));

?>