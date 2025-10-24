<?php

require_once 'include/languages.php';
require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/scoring.php';

function show_permissions($user_flags)
{
	$sep = '';
	$title = '';
	$image = NULL;
	if (($user_flags & USER_PERM_PLAYER) != 0)
	{
		$title .= $sep . get_label('player');
		$sep = '; ';
		$image = 'player.png';
	}
	
	if (($user_flags & USER_PERM_REFEREE) != 0)
	{
		$title .= $sep . get_label('Referee');
		$sep = '; ';
		$image = 'referee.png';
	}
	
	if (($user_flags & USER_PERM_MANAGER) != 0)
	{
		$title .= $sep . get_label('manager');
		$sep = '; ';
		$image = 'manager.png';
	}
	
	if ($image != NULL)
	{
		echo '<img src="images/' . $image . '" title="' . $title . '">';
	}
}

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		$prev_club_id = 0;
		$role_titles = array(
			get_label('Total'),
			get_label('As a red'),
			get_label('As a black'),
			get_label('As a civilian'),
			get_label('As a sheriff'),
			get_label('As a mafiosi'),
			get_label('As a don'));
		$role_titles1 = array(
			get_label('As a civilian'),
			get_label('As a sheriff'),
			get_label('As a mafiosi'),
			get_label('As a don'));
			
		$total_rating = 0;
		$total_games = 0;
		$total_won = 0;
		echo '<table class="bordered light" width="100%"><tr class="darker"><td>';
		echo '<table class="transp" width="100%"><tr><td width="52"></td><td>' . get_label('Total') . '</td></tr></table>';
		echo '</td><td width="200">' . get_label('Games played');
		echo ':</td><td width="200">' . get_label('Wins') . ':</td><td width="200">' . get_label('Rating earned') . ':</td></tr>';
		$query = new DbQuery(
			'SELECT p.role, SUM(p.rating_earned) as rating, COUNT(p.game_id) as games, SUM(p.won) as won FROM players p' . 
				' JOIN games g ON g.id = p.game_id' .
				' WHERE p.user_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY p.role ORDER BY p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($role, $rating, $games, $games_won) = $row;
			$total_rating += $rating;
			$total_games += $games;
			$total_won += $games_won;
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($rating), format_rating($rating/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, p.role, SUM(p.rating_earned) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM clubs c' . 
				' JOIN games g ON g.club_id = c.id' .
				' JOIN club_regs u ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.user_id AND p.game_id = g.id' .
				' WHERE u.user_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY c.id, p.role ORDER BY c.id, p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $rating, $games, $games_won, $user_flags) = $row;
			if ($club_id != $prev_club_id)
			{
				if ($total_games > 0)
				{
					echo '<tr class="darker"><td>' . get_label('Total') . ':</td><td>' . $total_games . '</td><td>' . $total_won . '(' . number_format($total_won * 100 / $total_games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($total_rating), format_rating($total_rating/$total_games, 1)) . '</td></tr>';
				}
				$total_rating = 0;
				$total_games = 0;
				$total_won = 0;
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52">';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 48);
				echo '</td><td>' . $club_name . '</td><td align="right">';
				show_permissions($user_flags);
				echo '</td></tr></table>';
				echo '</td><td width="200">' . get_label('Games played');
				echo ':</td><td width="200">' . get_label('Wins') . ':</td><td width="200">' . get_label('Rating earned') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			$total_rating += $rating;
			$total_games += $games;
			$total_won += $games_won;
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($rating), format_rating($rating/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, p.role, SUM(p.rating_earned) as rating, COUNT(p.game_id) as games, SUM(p.won) as won FROM clubs c' . 
				' JOIN games g ON g.club_id = c.id' .
				' JOIN players p ON p.game_id = g.id' .
				' WHERE p.user_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 AND c.id NOT IN (SELECT u.club_id FROM club_regs u WHERE u.user_id = p.user_id) GROUP BY c.id, p.role ORDER BY c.id, p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $rating, $games, $games_won) = $row;
			if ($club_id != $prev_club_id)
			{
				if ($total_games > 0)
				{
					echo '<tr class="darker"><td>' . get_label('Total') . ':</td><td>' . $total_games . '</td><td>' . $total_won . '(' . number_format($total_won * 100 / $total_games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($total_rating), format_rating($total_rating/$total_games, 1)) . '</td></tr>';
				}
				$total_rating = 0;
				$total_games = 0;
				$total_won = 0;
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52">';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 48);
				echo '</td><td>' . $club_name . '</td></tr></table>';
				echo '</td><td width="200">' . get_label('Games played');
				echo ':</td><td width="200">' . get_label('Wins') . ':</td><td width="200">' . get_label('Rating earned') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			$total_rating += $rating;
			$total_games += $games;
			$total_won += $games_won;
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($rating), format_rating($rating/$games, 1)) . '</td></tr>';
		}
		if ($total_games > 0)
		{
			echo '<tr class="darker"><td>' . get_label('Total') . ':</td><td>' . $total_games . '</td><td>' . $total_won . '(' . number_format($total_won * 100 / $total_games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($total_rating), format_rating($total_rating/$total_games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, u.flags FROM clubs c' .
			' JOIN club_regs u ON u.club_id = c.id' .
			' WHERE u.user_id = ? AND u.club_id NOT IN (SELECT g.club_id FROM players p, games g WHERE p.game_id = g.id AND p.user_id = u.user_id) ORDER BY u.flags DESC', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $user_flags) = $row;
			echo '</table>';
			echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
			echo '<table class="transp" width="100%"><tr><td width="52">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td><td>' . $club_name . '</td><td align="right">';
			show_permissions($user_flags);
			echo '</td></tr></table>';
			echo '</td><td width="200"></td><td width="200"></td><td width="200"></td></tr>';
			$prev_club_id = $club_id;
		}
		
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Clubs'));

?>