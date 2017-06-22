<?php

require_once 'include/languages.php';
require_once 'include/user.php';
require_once 'include/club.php';

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
		list ($rating_type) = Db::record(get_label('rating'), 'SELECT id FROM rating_types WHERE def = 1 LIMIT 1');
		$query = new DbQuery('SELECT rating, games, games_won FROM ratings WHERE role = 0 AND type_id = ? AND user_id = ?', $rating_type, $this->id);
		if ($row = $query->next())
		{
			list ($rating, $games, $won) = $row;
			list ($rating_pos) = Db::record(get_label('rating'), 
				'SELECT count(*) FROM ratings WHERE role = 0 AND type_id = ?' .
				' AND (rating > ? OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND user_id < ?))))))', 
				$rating_type, $rating, $rating, $games, $games, $won, $won, $this->id);
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
		
		$red_rating = 0;
		$dark_rating = 0;
		$query = new DbQuery('SELECT role, rating FROM ratings WHERE user_id = ? AND type_id = 1 AND role >= ' . POINTS_RED . ' AND role <= ' . POINTS_DARK, $this->id);
		while ($row = $query->next())
		{
			$role = $row[0];
			$rating = $row[1];
			switch ($role)
			{
				case POINTS_RED:
					$red_rating = $rating;
					break;
				case POINTS_DARK:
					$dark_rating = $rating;
					break;
			}
		}
		
		if ($this->games_moderated > 0)
		{
			echo '<tr><td class="dark">'.get_label('Games moderated').':</td><td>' . $this->games_moderated . '</td></tr>';
		}
		
		$prev_club_id = 0;
		$role_titles = array(
			get_label('Total'),
			get_label('As a red player'),
			get_label('As a dark player'),
			get_label('As a civilian'),
			get_label('As a sheriff'),
			get_label('As a mafiosy'),
			get_label('As a don'));
		// $query = new DbQuery(
			// 'SELECT c.id, c.name, c.flags, c.web_site, r.role, r.rating, r.games, r.games_won FROM club_ratings r, clubs c WHERE r.club_id = c.id AND r.type_id = 1 AND r.user_id = ? ORDER BY r.club_id, r.role', $this->id);
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, r.role, r.rating, r.games, r.games_won, u.flags FROM clubs c' .
			' JOIN user_clubs u ON u.club_id = c.id' .
			' JOIN club_ratings r ON r.club_id = c.id AND r.user_id = u.user_id' .
			' WHERE r.type_id = 1 AND u.user_id = ? ORDER BY r.club_id, r.role', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $rating, $games, $games_won, $user_flags) = $row;
			if ($club_id != $prev_club_id)
			{
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52"><a href="club_main.php?bck=1&id=' . $club_id . '">';
				show_club_pic($club_id, $club_flags, ICONS_DIR, 48, 48);
				echo '</a></td><td>' . $club_name . '</td><td align="right">';
				show_permissions($user_flags);
				echo '</td></tr></table>';
				echo '</td><td width="100">' . get_label('Games played');
				echo ':</td><td width="100">' . get_label('Games won') . ':</td><td width="100">' . get_label('Rating') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			echo '<tr><td class="dark">' . $role_titles[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '</td><td>' . get_label('[0] ([1] per game)', $rating, number_format($rating/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, r.role, r.rating, r.games, r.games_won FROM clubs c' .
			' JOIN club_ratings r ON r.club_id = c.id' .
			' WHERE r.type_id = 1 AND r.user_id = ? AND c.id NOT IN (SELECT u.club_id FROM user_clubs u WHERE u.user_id = r.user_id) ORDER BY r.club_id, r.role', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $role, $rating, $games, $games_won) = $row;
			if ($club_id != $prev_club_id)
			{
				echo '</table>';
				echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
				echo '<table class="transp" width="100%"><tr><td width="52"><a href="club_main.php?bck=1&id=' . $club_id . '">';
				show_club_pic($club_id, $club_flags, ICONS_DIR, 48, 48);
				echo '</a></td><td>' . $club_name . '</td></tr></table>';
				echo '</td><td width="100">' . get_label('Games played');
				echo ':</td><td width="100">' . get_label('Games won') . ':</td><td width="100">' . get_label('Rating') . ':</td></tr>';
				$prev_club_id = $club_id;
			}
			echo '<tr><td class="dark">' . $role_titles[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '</td><td>' . get_label('[0] ([1] per game)', $rating, number_format($rating/$games, 1)) . '</td></tr>';
		}
		
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, c.web_site, u.flags FROM clubs c' .
			' JOIN user_clubs u ON u.club_id = c.id' .
			' WHERE u.user_id = ? AND u.club_id NOT IN (SELECT club_id FROM club_ratings r WHERE r.user_id = u.user_id) ORDER BY u.flags DESC', $this->id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $club_url, $user_flags) = $row;
			echo '</table>';
			echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
			echo '<table class="transp" width="100%"><tr><td width="' . ICON_WIDTH . '"><a href="club_main.php?bck=1&id=' . $club_id . '">';
			show_club_pic($club_id, $club_flags, ICONS_DIR, 48, 48);
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
				'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags ' . 
				'FROM users u, ratings r WHERE u.id = r.user_id AND r.role = 0 AND type_id = ?' .
				' ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT ' . $rating_page . ',7',
				$rating_type);
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
				show_user_pic($id, $flags, ICONS_DIR, 48, 48);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . $rating . '</td>';
				echo '</tr>';
			}
			echo '</table>';
			
			echo '</td></tr></table>';
		}
		
/*		$role_titles = array(
			get_label('Total'),
			get_label('As a red player'),
			get_label('As a dark player'),
			get_label('As a civilian'),
			get_label('As a sheriff'),
			get_label('As a mafiosy'),
			get_label('As a don'));
		
		$query = new DbQuery('SELECT role, rating, games, games_won FROM ratings WHERE user_id = ? ORDER BY role', $this->id);
		while ($row = $query->next())
		{
			$role = $row[0];
			$rating = $row[1];
			$games = $row[2];
			$games_won = $row[3];
		
			echo '<h4>' . $role_titles[$role] . ':</h4><table class="bordered" width="100%">';
			echo '<tr><td width="150">'.get_label('Games played').':</td><td>' . $games . '</td></tr>';
			echo '<tr><td>'.get_label('Games won').':</td><td>' . $games_won . ' (' . number_format(($games_won*100.0)/$games, 1) . '%)</td></tr>';
			echo '<tr><td>'.get_label('Rating').':</td><td>' . get_label('[0] ([1] per game)', $rating, number_format($rating/$games, 1)) . '</td></tr></table>';
		}*/
	}
}

$page = new Page();
$page->run(get_label('[0] info', get_label('User')), PERM_ALL);

?>