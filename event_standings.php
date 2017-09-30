<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);

class Page extends EventPageBase
{
	private $scoring_id;
	private $roles;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		$this->_title = get_label('[0]. Standings.', $this->event->name);
		
		$this->scoring_id = $this->event->scoring_id;
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
		}
		
		$this->roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$this->roles = (int)$_REQUEST['roles'];
		}
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery('SELECT u.name, u.club_id, u.city_id, c.country_id FROM users u JOIN cities c ON c.id = u.city_id WHERE u.id = ?', $this->user_id);
			if ($row = $query->next())
			{
				list($this->user_name, $this->user_club_id, $this->user_city_id, $this->user_country_id) = $row;
				$this->_title .= ' ' . get_label('Following [0].', $this->user_name);
			}
			else
			{
				$this->errorMessage(get_label('Player not found.'));
			}
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = get_roles_condition($this->roles);
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery(
				'SELECT IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)), 0) as score, COUNT(p.game_id) as games, SUM(p.won) as won FROM players p' . 
					' JOIN games g ON p.game_id = g.id' .
					' JOIN users u ON p.user_id = u.id' .
					' WHERE g.event_id = ?',
			$this->scoring_id, $this->event->id, $condition);
			$pos_query->add(' AND u.id = ? GROUP BY u.id', $this->user_id);
			
			if ($row = $pos_query->next())
			{
				list ($uscore, $ugames, $uwon) = $row;
				if ($ugames > 0)
				{
					$pos_query = new DbQuery(
						'SELECT count(*) FROM (SELECT u.id, IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)), 0) as score, SUM(p.won) as won, COUNT(p.game_id) as games' .
							' FROM players p' .
							' JOIN users u ON p.user_id = u.id' .
							' JOIN games g ON g.id = p.game_id' .
							' WHERE g.event_id = ?', $this->scoring_id, $this->event->id, $condition);
					$pos_query->add(
						' AND u.id <> ? GROUP BY u.id ' . 
						' HAVING score > ? OR (score = ? AND (won > ? OR (won = ? AND (games > ? OR (games = ? AND u.id < ?)))))'  . 
						') as upper',
						$this->user_id, $uscore, $uscore, $uwon, $uwon, $ugames, $ugames, $this->user_id);
					list($user_pos) = $pos_query->next();
					$_page = floor($user_pos / PAGE_SIZE);
				}
				else
				{
					$this->no_user_error();
				}
			}
			else
			{
				$this->no_user_error();
			}
		}
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->event->club_id, $this->scoring_id, 'viewForm', get_label('Scoring system'));
		echo ' ';
		show_roles_select($this->roles, 'document.viewForm.submit()', get_label('Use only the points earned in a specific role.'));
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', '', get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';

		list ($count) = Db::record(get_label('player'), 'SELECT count(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = ?', $this->event->id, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT p.user_id, u.name, r.nick_name, IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)), 0) as points, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags, c.id, c.name, c.flags' .
				' FROM players p' . 
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON p.user_id = u.id' .
				' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
				' LEFT OUTER JOIN registrations r ON r.event_id = g.event_id AND r.user_id = p.user_id' .
				' WHERE g.event_id = ?',
			$this->scoring_id, $this->event->id, $condition);
		$query->add(' GROUP BY p.user_id ORDER BY points DESC, won DESC, games DESC, u.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$number = $_page * PAGE_SIZE;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Points').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Points per game').'</td>';
		echo '</tr>';
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $nick, $points, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags) = $row;
				
			if (!empty($nick) && $nick != $name)
			{
				$name = $nick . ' (' . $name . ')';
			}
			
			if ($id == $this->user_id)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			echo '<td align="center" class="dark">' . $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $name, $flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . $name . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 40, 40);
			echo '</td>';
			echo '<td align="center" class="dark">';
			echo format_score($points);
			echo '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">';
				echo format_score($points/$games_played);
				echo '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	
	private function no_user_error()
	{
		if ($this->roles == POINTS_ALL)
		{
			$message = get_label('[0] played no games.', $this->user_name);
		}
		else
		{
			$message = get_label('[0] played no games as [1].', $this->user_name, get_role_name($this->roles, ROLE_NAME_FLAG_SINGLE | ROLE_NAME_FLAG_LOWERCASE));
		}
		$this->errorMessage($message);
	}
}

$page = new Page();
$page->run(get_label('Event players'), PERM_ALL);

?>