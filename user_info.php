<?php

require_once 'include/languages.php';
require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD_ON_GRAPH', 10*24*60*60);

define('RATINGS_BEFORE', 5);
define('RATINGS_AFTER', 5);

function show_permissions($user_flags)
{
	$sep = '';
	$title = '';
	$image = NULL;
	if (($user_flags & USER_CLUB_PERM_PLAYER) != 0)
	{
		$title .= $sep . get_label('player');
		$sep = '; ';
		$image = 'player.png';
	}
	
	if (($user_flags & USER_CLUB_PERM_MODER) != 0)
	{
		$title .= $sep . get_label('moderator');
		$sep = '; ';
		$image = 'moderator.png';
	}
	
	if (($user_flags & USER_CLUB_PERM_MANAGER) != 0)
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
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
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
				'SELECT count(*) FROM users WHERE games > 0 AND (rating > ? OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND id < ?))))))', 
				$rating, $rating, $games, $games, $won, $won, $this->id);
		}
		
		if ($rating_pos >= 0)
		{
			echo '<table width="100%"><tr><td valign="top" align="center">';
		}
		show_chart(640, 396); // fibonacci golden ratio 1.618:1
        echo '<table class="bordered light" width="100%">';
		
		$timezone = get_timezone();
		echo '<tr><td width="150" class="dark">'.get_label('Languages').':</td><td>' . get_langs_str($this->langs, ', ') . '</td><tr>';
		echo '<tr><td class="dark">'.get_label('Registered since').':</td><td>' . format_date('F d, Y', $this->reg_date, $timezone) . '</td></tr>';
		
        if (($this->flags & USER_FLAG_MALE) != 0)
        {
            echo '<tr><td class="dark">'.get_label('Gender').':</td><td>'.get_label('male').'</td></tr>';
        }
        else
        {
            echo '<tr><td class="dark">'.get_label('Gender').':</td><td>'.get_label('female').'</td></tr>';
        }
        if (($this->flags & USER_FLAG_BANNED) != 0)
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
		
		if (is_permitted(PERMISSION_CLUB_MANAGER, $this->club_id))
		{
			echo '<tr><td class="dark">'.get_label('Email').':</td><td>' . $this->email . '</td></tr>';
			if ($this->flags & USER_FLAG_NO_PASSWORD)
			{
				$status = get_label('not activated');
			}
			else
			{
				$status = get_label('activated');
			}
			$query = new DbQuery('SELECT flags FROM user_clubs WHERE user_id = ? AND club_id = ?', $this->id, $this->club_id);
			if ($row = $query->next())
			{
				list($club_flags) = $row;
				$status .= '; ';
				if ($club_flags & USER_CLUB_FLAG_SUBSCRIBED)
				{
					$status .= get_label('subscribed');
				}
				else
				{
					$status .= get_label('not subscribed');
				}
			}
			echo '<tr><td class="dark">'.get_label('Account status').':</td><td>' . $status . '</td></tr>';
		}
		echo '</table>';
		
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
		echo '<br><table class="bordered light" width="100%"><tr class="darker"><td>';
		echo '</td><td>' . get_label('Games played') . ':</td>';
		echo '<td>' . get_label('Victories') . ':</td>';
		echo '<td>' . get_label('Rating earned') . ':</td></tr>';
		$query = new DbQuery(
			'SELECT p.role, SUM(p.rating_earned) as rating, COUNT(p.game_id) as games, SUM(p.won) as won FROM players p' . 
				' JOIN games g ON g.id = p.game_id' .
				' WHERE p.user_id = ? GROUP BY p.role ORDER BY p.role',
			$this->id);
		while ($row = $query->next())
		{
			list ($role, $rating, $games, $games_won) = $row;
			$total_rating += $rating;
			$total_games += $games;
			$total_won += $games_won;
			echo '<tr><td class="dark">' . $role_titles1[$role] . ':</td><td>' . $games . '</td><td>' . $games_won . '(' . number_format($games_won * 100 / $games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($rating), format_rating($rating/$games, 1)) . '</td></tr>';
		}
		echo '<tr class="darker"><td>' . get_label('Total') . ':</td><td>' . $total_games . '</td><td>' . $total_won . '(' . number_format($total_won * 100 / $total_games) . '%)</td><td>' . get_label('[0] ([1] per game)', format_rating($total_rating), format_rating($total_rating/$total_games, 1)) . '</td></tr>';
		echo '</table>';
		
		if ($rating_pos >= 0)
		{
			echo '</td><td width="280" valign="top">';
			$rating_page = $rating_pos - RATINGS_BEFORE;
			if ($rating_page < 0)
			{
				$rating_page = 0;
			}
			$query = new DbQuery(
				'SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags ' . 
				'FROM users u WHERE u.games > 0 ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $rating_page . ',' . (RATINGS_BEFORE + RATINGS_AFTER + 1));
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
				$this->user_pic->set($id, $name, $flags);
				$this->user_pic->show(ICONS_DIR, 48);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . number_format($rating) . '</td>';
				echo '</tr>';
			}
			echo '</table>';
			
			echo '</td></tr></table>';
		}
	}
	
	protected function js_on_load()
	{
		parent::js();
?>
		chartParams.type = "rating";
		chartParams.name = "<?php echo get_label('[0] rating chart', $this->name); ?>";
		chartParams.players = "<?php echo $this->id; ?>";
		chartParams.charts = 1;
		initChart("<?php echo get_label('Rating'); ?>");
<?php
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>