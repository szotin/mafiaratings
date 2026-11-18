<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/cards.php';

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
		$this->scoring->id = (int)$this->scoring_id; // it is needed for caching
		$this->scoring->version = (int)$this->scoring_version; // it is needed for caching
		
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
				' JOIN cities c ON c.id = u.city_id'.
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
		
		$mvp_flag = ($this->flags & EVENT_FLAG_AWARD_MVP) ? COMPETITION_MVP : 0;
		$best_red_flag = ($this->flags & EVENT_FLAG_AWARD_RED) ? (($this->flags & EVENT_FLAG_AWARD_NO_SHERIFF_IN_RED) ? COMPETITION_BEST_CIV : COMPETITION_BEST_RED) : 0;
		$best_black_flag = ($this->flags & EVENT_FLAG_AWARD_BLACK) ? (($this->flags & EVENT_FLAG_AWARD_NO_DON_IN_BLACK) ? COMPETITION_BEST_MAF : COMPETITION_BEST_BLACK) : 0;
		$best_sheriff_flag = ($this->flags & EVENT_FLAG_AWARD_SHERIFF) ? COMPETITION_BEST_SHERIFF : 0;
		$best_don_flag = ($this->flags & EVENT_FLAG_AWARD_DON) ? COMPETITION_BEST_DON : 0;
		
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, 0, 0, $this->scoring_options, ' ', 'submitScoring', SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_NORMALIZER);
		if ($this->is_manager)
		{
			echo '</td><td align="right" width="32"><button class="icon" title="'.get_label('Refresh scoring table').'" onclick="clearCache()"><img src="images/refresh.png"></button>';
		}
		echo '</td></tr></table>';
		
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
		
		$event_reg_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));
		$club_pic = new Picture(CLUB_PICTURE);
		
		// The list of the v3 design, after tournament_standings.php: the search and the count in a
		// toolbar, the table in a sheet.
		echo '<div class="toolbar"><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, 'event=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</div><span class="count">' . get_label('[0] players', number_format($players_count)) . '</span></div>';
		show_pages_navigation(PAGE_SIZE, $players_count);
		
		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Sum') . '</th>';
		echo '<th class="num p3" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_MAIN, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_LEGACY, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_EXTRA, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_PENALTY, true) . '</th>';
		if ($this->show_fk)
		{
			echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_NIGHT1, true) . '</th>';
		}
		echo '<th class="num p3" scope="col">' . get_label('Games') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Winning %') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Points per game') . '</th>';
		echo '</tr></thead><tbody>';
		
		$page_start = $_page * PAGE_SIZE;
		$page_count = min($players_count, $page_start + PAGE_SIZE);
		for ($number = $page_start; $number < $page_count; ++$number)
		{
			$player = $players[$number];
			$place = $number + 1;
			echo '<tr' . ($player->id == $this->user_id ? ' class="me"' : '') . '>';
			echo '<td class="rk"><span class="medal' . ($place <= 3 ? ' m' . $place : '') . '">' . sprintf('%02d', $place) . '</span></td>';
			
			$event_reg_pic->
				set($player->id, $player->nickname, $player->event_reg_flags, 'e' . $this->id)->
				set($player->id, $player->name, $player->tournament_reg_flags, 't' . $this->tournament_id)->
				set($player->id, $player->name, $player->club_reg_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $player->flags);
			$awards = '';
			if (isset($player->nom_flags) && $player->nom_flags)
			{
				if ($player->nom_flags & $best_red_flag)
				{
					$awards .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span></span>';
				}
				if ($player->nom_flags & $best_black_flag)
				{
					$awards .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span></span>';
				}
				if ($player->nom_flags & $best_don_flag)
				{
					$awards .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span></span>';
				}
				if ($player->nom_flags & $best_sheriff_flag)
				{
					$awards .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span></span>';
				}
				if ($player->nom_flags & $mvp_flag)
				{
					$awards .= '<span class="award"><img src="images/wreath.png" width="36"><span class="mvp">MVP</span></span>';
				}
			}
			echo '<td><a class="who" href="event_player.php?user_id=' . $player->id . $this->event_player_params . $this->show_all . '">' . card_avatar_of($event_reg_pic, $player->id, $player->name, '') .
				'<span class="who-txt"><span class="who-name">' . $player->name . '</span></span>' . ($awards == '' ? '' : '<span class="awards">' . $awards . '</span>') . '</a></td>';
			echo '<td class="p2">' . card_club($club_pic, $player->club_id > 0 ? $player->club_id : NULL, $player->club_name, $player->club_flags) . '</td>';
			echo '<td class="num strong">' . format_score($player->points) . '</td>';
			echo '<td class="num p3">' . format_score($player->main_points) . '</td>';
			echo '<td class="num p4 dim">' . format_score($player->legacy_points, false) . '</td>';
			echo '<td class="num p4 dim">' . format_score($player->extra_points, false) . '</td>';
			echo '<td class="num p4 dim">' . format_score($player->penalty_points, false) . '</td>';
			if ($this->show_fk)
			{
				echo '<td class="num p4 dim">' . format_score($player->night1_points, false) . '</td>';
			}
			echo '<td class="num p3">' . $player->games_count . '</td>';
			echo '<td class="num p3">' . $player->wins . '</td>';
			if ($player->games_count != 0)
			{
				echo '<td class="num p4 dim">' . number_format(($player->wins * 100.0) / $player->games_count, 1) . '%</td>';
				echo '<td class="num p4 dim">' . format_score($player->points / $player->games_count) . '</td>';
			}
			else
			{
				echo '<td class="num p4"></td><td class="num p4"></td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
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
	
	protected function js()
	{
		if ($this->is_manager)
		{
?>		
			function clearCache()
			{
				json.post("api/ops/event.php", { op: 'refresh_table', event_id: <?php echo $this->id; ?> }, refr);
			}
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
