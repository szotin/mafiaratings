<?php

require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);

class Page extends ClubPageBase
{
	private $season; 
	private $scoring;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_page, $_profile;
	
		parent::prepare();

		$this->season = SEASON_LATEST;
		if (isset($_REQUEST['season']))
		{
			$this->season = (int)$_REQUEST['season'];
		}
		
		$this->scoring = NULL;
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
			list($this->scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->scoring_id);
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
		global $_page, $_lang_code;
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		if ($this->user_id > 0)
		{
			echo '<input type="hidden" name="page" value="-' . $this->user_id . '">';
		}
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->id, $this->scoring_id, 'document.viewForm.submit()', get_label('Scoring system'));		
		echo ' ';
		$this->season = show_club_seasons_select($this->id, $this->season, 'document.viewForm.submit()', get_label('Standings by season.'));	
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'club=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';
		
		$start_time = $end_time = 0;
		if ($this->season > SEASON_LATEST)
		{
			list($start_time, $end_time) = Db::record(get_label('season'), 'SELECT start_time, end_time FROM club_seasons WHERE id = ?', $this->season);
		}
		else if ($this->season < SEASON_ALL_TIME)
		{
			date_default_timezone_set($this->timezone);
			$start_time = mktime(0, 0, 0, 1, 1, -$this->season);
			$end_time = mktime(0, 0, 0, 1, 1, 1 - $this->season);
		}
		
		$players = club_scores($this->id, $start_time, $end_time, NULL, SCORING_LOD_PER_GROUP, $this->scoring);
		$players_count = count($players);
		if ($this->user_id > 0)
		{
			$_page = get_user_page($players, $this->user_id, PAGE_SIZE);
			if ($_page < 0)
			{
				$_page = 0;
				$this->no_user_error();
			}
		}
		
		show_pages_navigation(PAGE_SIZE, $players_count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="3" rowspan="2">'.get_label('Player').'</td>';
		echo '<td width="36" align="center" colspan="6">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Winning %').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Points per game').'</td>';
		echo '</tr>';
		echo '<tr class="th darker" align="center"><td width="36">' . get_label('Sum') . '</td><td width="36">' . get_label('Main') . '</td><td width="36">' . get_label('Guess') . '</td><td width="36">' . get_label('Extra') . '</td><td width="36">' . get_label('Penlt') . '</td><td width="36">' . get_label('FK') . '</td></tr>';

		$page_start = $_page * PAGE_SIZE;
		if ($players_count > $page_start + PAGE_SIZE)
		{
			$players_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $players_count; ++$number)
		{
			$player = $players[$number];
			if ($player->id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darker';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}
			echo '<td align="center" class="' . $highlight . '">' . ($number + 1) . '</td>';
			echo '<td width="50"><a href="user_info.php?id=' . $player->id . '&bck=1">';
			$this->user_pic->set($player->id, $player->name, $player->flags);
			$this->user_pic->show(ICONS_DIR, 50);
			echo '</a></td><td><a href="user_info.php?id=' . $player->id . '&bck=1">' . cut_long_name($player->name, 45) . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($player->club_id) && $player->club_id > 0)
			{
				$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$this->club_pic->show(ICONS_DIR, 40);
			}
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '">' . format_score($player->points) . '</td>';
			echo '<td align="center">' . format_score($player->main_points) . '</td>';
			echo '<td align="center">' . format_score($player->prima_nocta_points) . '</td>';
			echo '<td align="center">' . format_score($player->extra_points) . '</td>';
			echo '<td align="center">' . format_score($player->penalty_points) . '</td>';
			echo '<td align="center">' . format_score($player->night1_points) . '</td>';
			echo '<td align="center">' . $player->games_count . '</td>';
			echo '<td align="center">' . $player->wins . '</td>';
			if ($player->games_count != 0)
			{
				echo '<td align="center">' . number_format(($player->wins * 100.0) / $player->games_count, 1) . '%</td>';
				echo '<td align="center">';
				echo format_score($player->points / $player->games_count);
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
		$this->errorMessage(get_label('[0] played no games in [1].', $this->user_name, $this->name));
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>