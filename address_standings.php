<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);
define('ROLES_COUNT', 7);

class Page extends AddressPageBase
{
	private $roles;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;

	protected function prepare()
	{
		global $_profile, $_page;
	
		parent::prepare();

		$this->roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$this->roles = $_REQUEST['roles'];
		}
		
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = $_REQUEST['scoring'];
		}
		
		$this->_title = get_label('[0]. Standings.', $this->name);
		
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
		global $_page, $_lang_code;
		
		$condition = new SQL(' AND g.event_id IN (SELECT id FROM events WHERE address_id = ?)', $this->id);
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, 'viewForm', get_label('Scoring system'));
		echo ' ';
		show_roles_select($this->roles, 'document.viewForm.submit()', get_label('Use only the points earned in a specific role.'));
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';
		
		$scoring_system = new ScoringSystem($this->scoring_id);
		$scores = new Scores($scoring_system, $condition, get_roles_condition($this->roles));
		$players_count = count($scores->players);
		if ($this->user_id > 0)
		{
			$_page = $scores->get_user_page($this->user_id, PAGE_SIZE);
			if ($_page < 0)
			{
				$_page = 0;
				$this->no_user_error();
			}
		}

		show_pages_navigation(PAGE_SIZE, $players_count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40">&nbsp;</td>';
		echo '<td colspan="3">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Points').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '<td width="80" align="center">'.get_label('Victories').'</td>';
		echo '<td width="80" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="80" align="center">'.get_label('Points per game').'</td>';
		echo '</tr>';

		$page_start = $_page * PAGE_SIZE;
		if ($players_count > $page_start + PAGE_SIZE)
		{
			$players_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $players_count; ++$number)
		{
			$score = $scores->players[$number];
			$games_count = $score->get_count(SCORING_MATTER_PLAY);
			$wins_count = $score->get_count(SCORING_MATTER_WIN);

			if ($score->id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}
			echo '<td align="center" class="' . $highlight . '">' . $number . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $score->id . '&bck=1">';
			show_user_pic($score->id, $score->name, $score->flags, ICONS_DIR, 50, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $score->id . '&bck=1">' . cut_long_name($score->name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			show_club_pic($score->club_id, $score->club_name, $score->club_flags, ICONS_DIR, 40, 40);
			echo '</td>';
			echo '<td class="' . $highlight . '" align="center">' . $score->points_str() . '</td>';
			echo '<td align="center">' . $games_count . '</td>';
			echo '<td align="center">' . $wins_count . '</td>';
			if ($games_count != 0)
			{
				echo '<td align="center">' . number_format(($wins_count * 100.0) / $games_count, 1) . '%</td>';
				echo '<td align="center">' . $score->points_per_game_str() . '</td>';
			}
			else
			{
				echo '<td>&nbsp;</td><td>&nbsp;</td>';
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
$page->run(get_label('Address'), PERM_ALL);

?>