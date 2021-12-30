<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_MODERATOR, $this->club_id);
		$can_edit = $_profile->is_club_manager($this->club_id);
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		echo '<td width="87">';
		if ($can_edit)
		{
			echo '<button class="icon" onclick="mr.addTournamentUser(' . $this->id . ')" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button>';
		}
		echo '</td>';
		echo '<td colspan="4">' . get_label('User') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';

		$query = new DbQuery(
			'SELECT u.id, u.name, u.email, u.flags, tu.flags, c.id, c.name, c.flags, cu.club_id, cu.flags' .
			' FROM tournament_users tu' .
			' JOIN users u ON tu.user_id = u.id' .
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN club_users cu ON cu.club_id = tu.tournament_id AND cu.user_id = tu.user_id' .
			' WHERE tu.tournament_id = ?' .
			' ORDER BY u.name',
			$this->id);
		while ($row = $query->next())
		{
			list($id, $name, $email, $user_flags, $user_tournament_flags, $club_id, $club_name, $club_flags, $user_club_id, $user_club_flags) = $row;
		
			echo '<tr class="light"><td class="dark">';
			if ($can_edit)
			{
				echo '<button class="icon" onclick="mr.removeTournamentUser(' . $id . ', ' . $this->id . ')" title="' . get_label('Remove [0] from club members.', $name) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editTournamentAccess(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] permissions.', $name) . '"><img src="images/access.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.tournamentUserPhoto(' . $id . ', ' . $this->id . ')" title="' . get_label('Set [0] photo for [1].', $name, $this->name) . '"><img src="images/photo.png" border="0"></button>';
			}
			else
			{
				echo '<img src="images/transp.png" height="32" border="0">';
			}
			echo '</td>';
			
			echo '<td width="60" align="center">';
			$tournament_user_pic->
				set($id, $name, $user_tournament_flags, 't' . $this->id)->
				set($id, $name, $user_club_flags, 'c' . $user_club_id)->
				set($id, $name, $user_flags);
			
			$tournament_user_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 56) . '</a></td>';
			echo '<td width="200">';
			if ($_profile->is_club_manager($club_id))
			{
				echo $email;
			}
			echo '</td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id))
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			
			echo '<td>';
			if ($user_tournament_flags & USER_PERM_PLAYER)
			{
				echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($user_tournament_flags & USER_PERM_MODER)
			{
				echo '<img src="images/moderator.png" width="32" title="' . get_label('Moderator') . '">';
			}
			else
			{
				echo '<img src="images/transp.png" width="32">';
			}
			if ($user_tournament_flags & USER_PERM_MANAGER)
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
	}
}

$page = new Page();
$page->run(get_label('Registrations'));

?>