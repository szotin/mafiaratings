<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

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
		global $_page, $_lang;
		
		parent::prepare();
		
		$this->event_player_params = '&id=' . $this->id;
		if (isset($_REQUEST['scoring_id']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring_id'];
			$this->event_player_params .= '&scoring_id=' . $this->scoring_id;
			if (isset($_REQUEST['scoring_version']))
			{
				$this->scoring_version = (int)$_REQUEST['scoring_version'];
				$this->event_player_params .= '&scoring_version=' . $this->scoring_version;
				list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
			}
			else
			{
				list($this->scoring, $this->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->scoring_id);
			}
		}
		else
		{
			list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		}
		$this->scoring = json_decode($this->scoring);
		
		$this->scoring_options = json_decode($this->scoring_options);
		if (isset($_REQUEST['scoring_ops']))
		{
			$this->event_player_params .= '&scoring_ops=' . rawurlencode($_REQUEST['scoring_ops']);
			$ops = json_decode($_REQUEST['scoring_ops']);
			foreach($ops as $key => $value) 
			{
				$this->scoring_options->$key = $value;
			}
		}
		$this->show_fk = !isset($this->scoring_options->flags) || ($this->scoring_options->flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0;
		$this->event_player_params .= '&bck=1';
		
		$this->user_id = 0;
		if ($_page < 0)
		{
			$this->user_id = -$_page;
			$_page = 0;
			$query = new DbQuery(
				'SELECT nu.name, u.club_id, u.city_id, c.country_id'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities c ON c.id = u.city_id',
				' WHERE u.id = ?', $this->user_id);
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
		
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		
		echo '<form method="get" name="viewForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, 0, 0, $this->scoring_options, ' ', 'submitScoring', SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_NORMALIZER);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'event=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></form>';
		
		$condition = new SQL(' AND g.event_id = ?', $this->id);
		
		$players = event_scores($this->id, null, SCORING_LOD_PER_GROUP | SCORING_LOD_PER_ROLE, $this->scoring, $this->scoring_options, $this->tournament_flags, $this->round_num);
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
		
		$event_user_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));
		
		show_pages_navigation(PAGE_SIZE, $players_count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="2" rowspan="2">'.get_label('Player').'</td>';
		echo '<td width="36" align="center" colspan="' . ($this->show_fk ? 6 : 5) . '">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Winning %').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Points per game').'</td>';
		echo '</tr>';
		echo '<tr class="th darker" align="center"><td width="36">' . get_label('Sum') . 
			'</td><td width="36">' . get_scoring_group_label($this->scoring, SCORING_GROUP_MAIN, true) . 
			'</td><td width="36">' . get_scoring_group_label($this->scoring, SCORING_GROUP_LEGACY, true) . 
			'</td><td width="36">' . get_scoring_group_label($this->scoring, SCORING_GROUP_EXTRA, true) . 
			'</td><td width="36">' . get_scoring_group_label($this->scoring, SCORING_GROUP_PENALTY, true) . '</td>';
		if ($this->show_fk)
		{
			echo '<td width="36">' . get_scoring_group_label($this->scoring, SCORING_GROUP_NIGHT1, true) . '</td>';
		}
		echo '</tr>';
		
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
			
			
			
			echo '<td><a href="event_player.php?user_id=' . $player->id . $this->event_player_params . $this->show_all . '">';
			echo '<table class="transp" width="100%"><tr><td width="56">';
			$event_user_pic->
				set($player->id, $player->nickname, $player->event_user_flags, 'e' . $this->id)->
				set($player->id, $player->name, $player->tournament_user_flags, 't' . $this->tournament_id)->
				set($player->id, $player->name, $player->club_user_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $player->flags);
			$event_user_pic->show(ICONS_DIR, true, 50);
			echo '</a></td><td><a href="event_player.php?user_id=' . $player->id . $this->event_player_params . $this->show_all . '">' . $player->name . '</a></td>';
			if (isset($player->nom_flags) && $player->nom_flags)
			{
				echo '<td align="right">';
				if ($player->nom_flags & COMPETITION_BEST_RED)
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span>';
				}
				if ($player->nom_flags & COMPETITION_BEST_BLACK)
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span>';
				}
				if ($player->nom_flags & COMPETITION_BEST_DON)
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span>';
				}
				if ($player->nom_flags & COMPETITION_BEST_SHERIFF)
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span>';
				}
				if ($player->nom_flags & COMPETITION_MVP)
				{
					echo '<img src="images/wreath.png" width="36"><span class="mvp">MVP</span>';
				}
				echo '</td>';
			}
			echo '</tr></table></a></td>';
			
			
			
			echo '<td width="50" align="center">';
			if (!is_null($player->club_id) && $player->club_id > 0)
			{
				$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '">' . format_score($player->points) . '</td>';
			echo '<td align="center">' . format_score($player->main_points) . '</td>';
			echo '<td align="center">' . format_score($player->legacy_points, false) . '</td>';
			echo '<td align="center">' . format_score($player->extra_points, false) . '</td>';
			echo '<td align="center">' . format_score($player->penalty_points, false) . '</td>';
			if ($this->show_fk)
			{
				echo '<td align="center">' . format_score($player->night1_points, false) . '</td>';
			}
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
		show_pages_navigation(PAGE_SIZE, $players_count);
		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("event", <?php echo $this->id; ?>, 5);
			
			function submitScoring(s)
			{
				goTo({ scoring_id: s.sId, scoring_version: s.sVer, scoring_ops: s.ops, page: 0 });
			}
		</script>
<?php
	}
	
	private function no_user_error()
	{
		$this->errorMessage(get_label('[0] did not play in [1].', $this->user_name, $this->name));
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
