<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);

class Page extends EventPageBase
{
	private $scoring;
	
	private $user_id;
	private $user_name;
	private $user_club_id;
	private $user_city_id;
	private $user_country_id;
	
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		
		$this->event_player_params = '&id=' . $this->event->id;
		if (isset($_REQUEST['scoring_id']))
		{
			$this->event->scoring_id = (int)$_REQUEST['scoring_id'];
			$this->event_player_params .= '&scoring_id=' . $this->event->scoring_id;
			if (isset($_REQUEST['scoring_version']))
			{
				$this->event->scoring_version = (int)$_REQUEST['scoring_version'];
				$this->event_player_params .= '&scoring_version=' . $this->event->scoring_version;
				list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->event->scoring_id, $this->event->scoring_version);
			}
			else
			{
				list($this->scoring, $this->event->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->event->scoring_id);
			}
		}
		else
		{
			list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->event->scoring_id, $this->event->scoring_version);
		}
		$this->scoring = json_decode($this->scoring);
		
		$this->scoring_options = json_decode($this->event->scoring_options);
		if (isset($_REQUEST['scoring_ops']))
		{
			$this->event_player_params .= '&scoring_ops=' . rawurlencode($_REQUEST['scoring_ops']);
			$ops = json_decode($_REQUEST['scoring_ops']);
			foreach($ops as $key => $value) 
			{
				$this->scoring_options->$key = $value;
			}
		}
		
		$this->event_player_params .= '&bck=1';
		
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
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->event->club_id, $this->event->scoring_id, $this->event->scoring_version, 0, 0, $this->scoring_options, ' ', 'submitScoring', SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_NORMALIZER);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'event=' . $this->event->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';
		
		$condition = new SQL(' AND g.event_id = ?', $this->event->id);
		
		$players = event_scores($this->event->id, null, SCORING_LOD_PER_GROUP, $this->scoring, $this->scoring_options);
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
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
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
			echo '<td width="50"><a href="event_player_games.php?user_id=' . $player->id . $this->event_player_params . '">';
			$this->user_pic->set($player->id, $player->name, $player->flags);
			$this->user_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="event_player_games.php?user_id=' . $player->id . $this->event_player_params . '">' . $player->name . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($player->club_id) && $player->club_id > 0)
			{
				$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '">' . format_score($player->points) . '</td>';
			echo '<td align="center">' . format_score($player->main_points) . '</td>';
			echo '<td align="center">' . format_score($player->legacy_points) . '</td>';
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
		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("event", <?php echo $this->event->id; ?>, 5);
			
			function submitScoring(s)
			{
				goTo({ scoring_id: s.sId, scoring_version: s.sVer, scoring_ops: s.ops });
			}
		</script>
<?php
	}
	
	
	private function no_user_error()
	{
		$this->errorMessage(get_label('[0] did not play in [1].', $this->user_name, $this->event->name));
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
