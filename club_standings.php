<?php

require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);

class Page extends ClubPageBase
{
	private $my_id;
	private $roles;
	private $season; 
	
	protected function prepare()
	{
		global $_profile;
	
		parent::prepare();
		$this->my_id = -1;
		if ($_profile != NULL)
		{
			$this->my_id = $_profile->user_id;
		}

		$this->roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$this->roles = $_REQUEST['roles'];
		}
		
		$this->season = 0;
		if (isset($_REQUEST['season']))
		{
			$this->season = (int)$_REQUEST['season'];
		}
		
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
		}
		
		$this->_title = get_label('[0] standings', $this->name);
	}
	
	protected function show_body()
	{
		global $_page, $_lang_code;
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		$this->season = show_seasons_select($this->id, $this->season, 'document.viewForm.submit()', get_label('Standings by season.'));
		echo ' ';
		show_roles_select($this->roles, 'document.viewForm.submit()', get_label('Use only the points earned in a specific role.'));
		echo '</td><td align="right">' . get_label('Scoring system') . ': ';
		show_scoring_select($this->id, $this->scoring_id, 'viewForm');		
		echo '</td></tr></table></form>';
		
		$condition = get_roles_condition($this->roles);
		$condition->add(get_season_condition($this->season, 'g.start_time', 'g.end_time'));
		list ($count) = Db::record(get_label('points'), 'SELECT count(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.club_id = ?', $this->id, $condition);
		$query = new DbQuery(
			'SELECT p.user_id, u.name, IFNULL(SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)), 0) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM players p' . 
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON p.user_id = u.id' .
				' WHERE g.club_id = ?',
			$this->scoring_id, $this->id, $condition);
		$query->add(' GROUP BY p.user_id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		show_pages_navigation(PAGE_SIZE, $count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Points').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Points per game').'</td>';
		echo '</tr>';

		$number = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $points, $games_played, $games_won, $flags) = $row;

			if ($id == $this->my_id)
			{
				echo '<tr class="lighter"><td align="center">';
			}
			else
			{
				echo '<tr class="light"><td align="center" class="dark">';
			}

			echo $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
			echo '<td align="center" class="dark">' . format_score($points) . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . format_score($points/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td align="center">&nbsp;</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Club standings'), PERM_ALL);

?>