<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id,$this->id);
		$can_edit = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id,$this->id);
		
		$is_team_tournament = (($this->flags & TOURNAMENT_FLAG_TEAM) != 0);
		if (!$is_team_tournament)
		{
			// Event if it is not a team tournament, teams can exist. Team in this case is a set of players who should not play with each other.
			list($count) = Db::record(get_label('team'), 'SELECT count(*) FROM tournament_teams WHERE tournament_id = ?', $this->id);
			$is_team_tournament = ($count > 0);
		}
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker">';
		if ($is_team_tournament)
		{
			echo '<td width="100">' . get_label('Team') . '</td>';
		}
		echo '<td width="87">';
		if ($can_edit)
		{
			echo '<button class="icon" onclick="mr.addTournamentUser(' . $this->id . ')" title="' . get_label('Add registration to [0].', $this->name) . '"><img src="images/create.png" border="0"></button>';
		}
		echo '</td>';
		echo '<td colspan="5">' . get_label('Player') . '</td><td width="130">' . get_label('Permissions') . '</td></tr>';

		
		$teams = array();
		$no_team = NULL;
		$current_team = NULL;
		$query = new DbQuery(
			'SELECT t.name, u.id, nu.name, u.email, u.flags, tu.flags, c.id, c.name, c.flags, cu.club_id, cu.flags, ni.name' .
			' FROM tournament_users tu' .
			' JOIN users u ON tu.user_id = u.id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' JOIN cities i ON i.id = u.city_id'.
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
			' LEFT OUTER JOIN club_users cu ON cu.club_id = tu.tournament_id AND cu.user_id = tu.user_id' .
			' LEFT OUTER JOIN tournament_teams t ON tu.team_id = t.id' .
			' WHERE tu.tournament_id = ?' .
			' ORDER BY t.name, nu.name',
			$this->id);
		while ($row = $query->next())
		{
			$team_name = $row[0];
			if ($team_name == NULL)
			{
				if ($no_team == NULL)
				{
					$no_team = new stdClass();
					$no_team->players = array();
				}
				$no_team->players[] = $row;
			}
			else 
			{
				if ($current_team == NULL)
				{
					$current_team = new stdClass();
					$current_team->name = $team_name;
					$current_team->players = array();
				}
				else if ($current_team->name != $team_name)
				{
					$teams[] = $current_team;
					$current_team = new stdClass();
					$current_team->name = $team_name;
					$current_team->players = array();
				}
				$current_team->players[] = $row;
			}
		}
		if ($current_team != NULL)
		{
			$teams[] = $current_team;
		}
		if ($no_team != NULL)
		{
			$teams[] = $no_team;
		}
			
		foreach ($teams as $team)
		{
			$players_count = count($team->players);
			for ($i = 0; $i < $players_count; ++$i)
			{
				$row = $team->players[$i];
				list($team_name, $id, $name, $email, $user_flags, $tournament_user_flags, $club_id, $club_name, $club_flags, $user_club_id, $club_user_flags, $city) = $row;
			
				if ($tournament_user_flags & USER_TOURNAMENT_FLAG_NOT_ACCEPTED)
				{
					echo '<tr class="dark">';
				}
				else
				{
					echo '<tr class="light">';
				}
				if ($is_team_tournament && $i == 0)
				{
					if (is_null($team_name))
					{
						echo '<td class="dark"';
						if ($players_count > 1)
						{
							echo ' rowspan="' . $players_count . '"';
						}
						echo ' align="center">' . get_label('No team') . '</td>';
					}
					else
					{
						echo '<td';
						if ($players_count > 1)
						{
							echo ' rowspan="' . $players_count . '"';
						}
						echo ' align="center">' . $team_name . '</td>';
					}
				}
				echo '<td class="dark">';
				if ($can_edit)
				{
					echo '<button class="icon" onclick="mr.removeTournamentUser(' . $this->id . ', ' . $id . ')" title="' . get_label('Remove [0] from club members.', $name) . '"><img src="images/delete.png" border="0"></button>';
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
					set($id, $name, $tournament_user_flags, 't' . $this->id)->
					set($id, $name, $club_user_flags, 'c' . $user_club_id)->
					set($id, $name, $user_flags);
				$tournament_user_pic->show(ICONS_DIR, true, 50);
				echo '</td>';
				
				if ($tournament_user_flags & USER_TOURNAMENT_FLAG_NOT_ACCEPTED)
				{
					echo '<td><a href="user_info.php?id=' . $id . '&bck=1"><b>' . $name . '</b><br>' . $city . '</a></td>';
					echo '<td width="150" align="center"><button onclick="mr.acceptTournamentUser('.$this->id.','.$id.')">'.get_label('Accept application').'</button></td>';
				}
				else
				{
					echo '<td colspan="2"><a href="user_info.php?id=' . $id . '&bck=1"><b>' . $name . '</b><br>' . $city . '</a></td>';
				}
				
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
				if ($tournament_user_flags & USER_PERM_PLAYER)
				{
					echo '<img src="images/player.png" width="32" title="' . get_label('Player') . '">';
				}
				else
				{
					echo '<img src="images/transp.png" width="32">';
				}
				if ($tournament_user_flags & USER_PERM_REFEREE)
				{
					echo '<img src="images/referee.png" width="32" title="' . get_label('Referee') . '">';
				}
				else
				{
					echo '<img src="images/transp.png" width="32">';
				}
				if ($tournament_user_flags & USER_PERM_MANAGER)
				{
					echo '<img src="images/manager.png" width="32" title="' . get_label('Manager') . '">';
				}
				else
				{
					echo '<img src="images/transp.png" width="32">';
				}
				echo '</td></tr>';
			}
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Registrations'));

?>