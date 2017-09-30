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
	if (($user_flags & UC_PERM_PLAYER) != 0)
	{
		$title .= $sep . get_label('player');
		$sep = '; ';
		$image = 'player.png';
	}
	
	if (($user_flags & UC_PERM_MODER) != 0)
	{
		$title .= $sep . get_label('moderator');
		$sep = '; ';
		$image = 'moderator.png';
	}
	
	if (($user_flags & UC_PERM_MANAGER) != 0)
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
	protected function prepare()
	{
		parent::prepare();
		$this->_title = $this->title;
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$rating_pos = -1;
		$query = new DbQuery('SELECT rating, games, games_won FROM users WHERE id = ?', $this->id);
		if ($row = $query->next())
		{
			list ($rating, $games, $won) = $row;
			list ($rating_pos) = Db::record(get_label('rating'), 
				'SELECT count(*) FROM users WHERE rating > ? AND games > 0 OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND id < ?)))))', 
				$rating, $rating, $games, $games, $won, $won, $this->id);
		}
		
		if ($rating_pos >= 0)
		{
			echo '<table width="100%"><tr><td valign="top">';
		}
		
        echo '<table class="bordered light" width="100%">';
		
		$timezone = 'America/Vancouver';
		if ($_profile != NULL)
		{
			$timezone = $_profile->timezone;
		}
		
		echo '<tr><td width="150" class="dark">'.get_label('Languages').':</td><td>' . get_langs_str($this->langs, ', ') . '</td><tr>';
		echo '<tr><td class="dark">'.get_label('Registered since').':</td><td>' . format_date('F d, Y', $this->reg_date, $timezone) . '</td></tr>';
		
        if (($this->flags & U_FLAG_MALE) != 0)
        {
            echo '<tr><td class="dark">'.get_label('Gender').':</td><td>'.get_label('male').'</td></tr>';
        }
        else
        {
            echo '<tr><td class="dark">'.get_label('Gender').':</td><td>'.get_label('female').'</td></tr>';
        }
        if (($this->flags & U_FLAG_BANNED) != 0)
        {
            echo '<tr><td class="dark">'.get_label('Banned').':</td><td>'.get_label('yes').'</td></tr>';
        }
		
		$query = new DbQuery('SELECT DISTINCT nick_name FROM registrations WHERE user_id = ? ORDER BY nick_name', $this->id);
		if ($row = $query->next())
		{
			echo '<tr><td class="dark">'.get_label('Nicks').':</td><td>' . cut_long_name($row[0], 88);
			while ($row = $query->next())
			{
				echo ', ' . cut_long_name($row[0], 88);
			}
			echo '</td></tr>';
		}
		
		if ($this->games_moderated > 0)
		{
			echo '<tr><td class="dark">'.get_label('Games moderated').':</td><td>' . $this->games_moderated . '</td></tr>';
		}
		
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
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, p.role, IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = c.scoring_id AND (o.flag & p.flags) <> 0)), 0) as points, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM clubs c' . 
				' JOIN games g ON g.club_id = c.id' .
				' JOIN user_clubs u ON u.club_id = c.id' .
				' JOIN players p ON p.user_id = u.user_id AND p.game_id = g.id' .
				' WHERE u.user_id = ? GROUP BY c.id, p.role ORDER BY c.id, p.role',
			$this->id);
			
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $points, $games, $games_won, $user_flags) = $row;
			if ($club_id != $prev_club_id)
			{
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52"><a href="club_main.php?bck=1&id=' . $club_id . '">';
				show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 48, 48);
				echo '</a></td><td>' . $club_name . '</td><td align="right">';
				show_permissions($user_flags);
				echo '</td></tr></table>';
				echo '</td><td width="100">' . get_label('Games played');
				echo ':</td><td width="100">' . get_label('Games won') . ':</td><td width="100">' . get_label('Points') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_score($points), format_score($points/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, p.role, IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = c.scoring_id AND (o.flag & p.flags) <> 0)), 0) as points, COUNT(p.game_id) as games, SUM(p.won) as won FROM clubs c' . 
				' JOIN games g ON g.club_id = c.id' .
				' JOIN players p ON p.game_id = g.id' .
				' WHERE p.user_id = ? AND c.id NOT IN (SELECT u.club_id FROM user_clubs u WHERE u.user_id = p.user_id) GROUP BY c.id, p.role ORDER BY c.id, p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $points, $games, $games_won) = $row;
			if ($club_id != $prev_club_id)
			{
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52"><a href="club_main.php?bck=1&id=' . $club_id . '">';
				show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 48, 48);
				echo '</a></td><td>' . $club_name . '</td></tr></table>';
				echo '</td><td width="100">' . get_label('Games played');
				echo ':</td><td width="100">' . get_label('Games won') . ':</td><td width="100">' . get_label('Points') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_score($points), format_score($points/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, u.flags FROM clubs c' .
			' JOIN user_clubs u ON u.club_id = c.id' .
			' WHERE u.user_id = ? AND u.club_id NOT IN (SELECT g.club_id FROM players p, games g WHERE p.game_id = g.id AND p.user_id = u.user_id) ORDER BY u.flags DESC', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $user_flags) = $row;
			echo '</table>';
			echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
			echo '<table class="transp" width="100%"><tr><td width="52"><a href="club_main.php?bck=1&id=' . $club_id . '">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 48, 48);
			echo '</a></td><td>' . $club_name . '</td><td align="right">';
			show_permissions($user_flags);
			echo '</td></tr></table>';
			echo '</td><td width="100"></td><td width="100"></td><td width="100"></td></tr>';
			$prev_club_id = $club_id;
		}
		
		echo '</table>';
		
		if ($rating_pos >= 0)
		{
			echo '</td><td width="280" valign="top">';
			$rating_page = $rating_pos - 3;
			if ($rating_page < 0)
			{
				$rating_page = 0;
			}
			$query = new DbQuery(
				'SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags ' . 
				'FROM users u WHERE u.games > 0 ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $rating_page . ',7');
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><b>' . get_label('Rating position') . '</a></td></tr>';
			$number = $rating_page;
			while ($row = $query->next())
			{
				++$number;
				list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;
				if ($id == $this->id)
				{
					echo '<tr class="lighter">';
				}
				else
				{
					echo '<tr>';
				}
				
				echo '<td width="20" align="center">' . $number . '</td>';
				echo '<td width="52"><a href="user_info.php?id=' . $id . '">';
				show_user_pic($id, $name, $flags, ICONS_DIR, 48, 48);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . number_format($rating) . '</td>';
				echo '</tr>';
			}
			echo '</table>';
			
			echo '</td></tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('[0] info', get_label('User')), PERM_ALL);

?>