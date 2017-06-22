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
	
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] standings', $this->event->name);
		
		$this->scoring_id = $this->event->scoring_id;
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$my_id = -1;
		if ($_profile != NULL)
		{
			$my_id = $_profile->user_id;
		}
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>' . get_label('Scoring system');
		echo ': <select name="scoring" onChange="document.viewForm.submit()">';
		$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $this->event->club_id);
		while ($row = $query->next())
		{
			list ($sid, $sname) = $row;
			show_option($sid, $this->scoring_id, $sname);
		}
		echo '</select>';
		
		echo '</td></tr></table></form>';

		list ($count) = Db::record(get_label('player'), 'SELECT count(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = ?', $this->event->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT p.user_id, u.name, r.nick_name, SUM((SELECT SUM(o.points) FROM scoring_points o WHERE o.scoring_id = ? AND (o.flag & p.flags) <> 0)) as rating, COUNT(p.game_id) as games, SUM(p.won) as won, u.flags FROM players p' . 
				' JOIN games g ON p.game_id = g.id' .
				' JOIN users u ON p.user_id = u.id' .
				' JOIN registrations r ON r.event_id = g.event_id AND r.user_id = p.user_id' .
				' WHERE g.event_id = ? GROUP BY p.user_id ORDER BY rating DESC, games, won DESC, u.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE,
			$this->scoring_id, $this->event->id);
		
		$number = $_page * PAGE_SIZE;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Points').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Games won').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Points per game').'</td>';
		echo '</tr>';
		while ($row = $query->next())
		{
			++$number;
			list ($id, $name, $nick, $points, $games_played, $games_won, $flags) = $row;
			
			if ($nick != $name)
			{
				$name = $nick . ' (' . $name . ')';
			}
			
			if ($id == $my_id)
			{
				echo '<tr class="light"><td align="center">';
			}
			else
			{
				echo '<tr><td align="center" class="dark">';
			}

			echo $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
			show_user_pic($id, $flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . $name . '</a></td>';
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
}

$page = new Page();
$page->run(get_label('Event players'), PERM_ALL);

?>