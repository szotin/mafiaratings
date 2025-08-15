<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('VIEW_NORMAL', 0);
define('VIEW_TEAM', 1);
define('VIEW_MVP', 2);

define('SORT_BY_MVP', 0);
define('SORT_BY_RED', 1);
define('SORT_BY_BLACK', 2);
define('SORT_BY_SHERIFF', 3);
define('SORT_BY_DON', 4);

function score_title($points, $raw_points, $normalization)
{
	if ($normalization != 1 && $points != 0)
	{
		return ' title="' . format_score($raw_points) . ' * ' . format_coeff($normalization) . ' = ' . format_score($points) . '"';
	}
	return '';
}

$_mvp_sorting = SORT_BY_MVP;
function compare_mvp($player1, $player2)
{
	global $_mvp_sorting;
	
	switch ($_mvp_sorting)
	{
	case SORT_BY_RED:
		$mvp_points1 = $player1->roles[ROLE_CIVILIAN]->mvp_points + $player1->roles[ROLE_SHERIFF]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_CIVILIAN]->mvp_points + $player2->roles[ROLE_SHERIFF]->mvp_points;
		break;
	case SORT_BY_BLACK:
		$mvp_points1 = $player1->roles[ROLE_MAFIA]->mvp_points + $player1->roles[ROLE_DON]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_MAFIA]->mvp_points + $player2->roles[ROLE_DON]->mvp_points;
		break;
	case SORT_BY_SHERIFF:
		$mvp_points1 = $player1->roles[ROLE_SHERIFF]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_SHERIFF]->mvp_points;
		break;
	case SORT_BY_DON:
		$mvp_points1 = $player1->roles[ROLE_DON]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_DON]->mvp_points;
		break;
	case SORT_BY_MVP:
	default:
		$mvp_points1 = $player1->mvp_points;
		$mvp_points2 = $player2->mvp_points;
		break;
	}
	
	if ($mvp_points2 > $mvp_points1 + 0.00001)
	{
		return 1;
	}
	if ($mvp_points2 < $mvp_points1 - 0.00001)
	{
		return -1;
	}
	if ($player2->points < $player1->points - 0.00001)
	{
		return 1;
	}
	if ($player2->points > $player1->points + 0.00001)
	{
		return -1;
	}
	if ($player1->id > $player2->id)
	{
		return 1;
	}
	else if ($player1->id < $player2->id)
	{
		return -1;
	}
	return 0;
}

class Page extends TournamentPageBase
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
		
		$this->tournament_player_params = '&id=' . $this->id;
		if (isset($_REQUEST['sid']))
		{
			$this->scoring_id = (int)$_REQUEST['sid'];
			$this->tournament_player_params .= '&sid=' . $this->scoring_id;
			if (isset($_REQUEST['sver']))
			{
				$this->scoring_version = (int)$_REQUEST['sver'];
				$this->tournament_player_params .= '&sver=' . $this->scoring_version;
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
		
		$this->normalizer = '{}';
		if (isset($_REQUEST['nid']))
		{
			$this->normalizer_id = (int)$_REQUEST['nid'];
			$this->tournament_player_params .= '&nid=' . $this->normalizer_id;
			if ($this->normalizer_id > 0)
			{
				if (isset($_REQUEST['nver']))
				{
					$this->normalizer_version = (int)$_REQUEST['nver'];
					$this->tournament_player_params .= '&nver=' . $this->normalizer_version;
					list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
				}
				else
				{
					list($this->normalizer, $this->normalizer_version) = Db::record(get_label('normalizer'), 'SELECT normalizer, version FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $this->normalizer_id);
				}
			}
		}
		else if (!is_null($this->normalizer_id) && $this->normalizer_id > 0)
		{
			list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
		}
		$this->normalizer = json_decode($this->normalizer);
		$this->has_normalizer = isset($this->normalizer->policies) && count($this->normalizer->policies) > 0;
		
		$this->scoring_options = json_decode($this->scoring_options);
		if (isset($_REQUEST['sops']))
		{
			$this->tournament_player_params .= '&sops=' . rawurlencode($_REQUEST['sops']);
			$ops = json_decode($_REQUEST['sops']);
			foreach($ops as $key => $value) 
			{
				$this->scoring_options->$key = $value;
			}
		}
		$this->show_fk = !isset($this->scoring_options->flags) || ($this->scoring_options->flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0;
		
		$this->tournament_player_params .= '&bck=1';
		
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
	
	private function team_view()
	{
		global $_page;
		
		$teams = tournament_scores($this->id, $this->flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_TEAMS, $this->scoring, $this->normalizer, $this->scoring_options);
		//print_json($teams, $this->scoring);
		$teams_count = count($teams);
		
		show_pages_navigation(PAGE_SIZE, $teams_count);
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td rowspan="2">'.get_label('Team').'</td>';
		echo '<td width="36" align="center" colspan="' . ($this->show_fk ? 6 : 5) . '">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
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
		if ($teams_count > $page_start + PAGE_SIZE)
		{
			$teams_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $teams_count; ++$number)
		{
			$team = $teams[$number];
			echo '<tr>';
			echo '<td align="center" class="dark">' . ($number + 1) . '</td>';
			
			echo '<td><table class="transp" width="100%"><tr><td width="' . (count($team->players) * 50) . '">';
			foreach ($team->players as $player)
			{
				echo '<a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '">';
				$tournament_user_pic->
					set($player->id, $player->name, $player->tournament_user_flags, 't' . $this->id)->
					set($player->id, $player->name, $player->club_user_flags, 'c' . $this->club_id)->
					set($player->id, $player->name, $player->flags);
				$tournament_user_pic->show(ICONS_DIR, false, 50);
				echo '</a>';
			}
			echo '</a></td><td><b>' . $team->name . '</b></td>';
			// if (isset($team->nom_flags) && $team->nom_flags)
			// {
				// echo '<td align="right">';
				// if (($team->nom_flags & COMPETITION_BEST_RED) && ($this->flags & TOURNAMENT_FLAG_AWARD_RED))
				// {
					// echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span>';
				// }
				// if (($team->nom_flags & COMPETITION_BEST_BLACK) && ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK))
				// {
					// echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span>';
				// }
				// if (($team->nom_flags & COMPETITION_BEST_DON) && ($this->flags & TOURNAMENT_FLAG_AWARD_DON))
				// {
					// echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span>';
				// }
				// if (($team->nom_flags & COMPETITION_BEST_SHERIFF) && ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF))
				// {
					// echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span>';
				// }
				// if (($team->nom_flags & COMPETITION_MVP) && ($this->flags & TOURNAMENT_FLAG_AWARD_MVP))
				// {
					// echo '<img src="images/wreath.png" width="36"><span class="mvp">MVP</span>';
				// }
				// echo '</td>';
			// }
			echo '</tr></table></td>';
			
			echo '<td align="center" class="dark"' . score_title($team->points, $team->raw_points, 1) . '>' . format_score($team->points) . '</td>';
			echo '<td align="center"' . score_title($team->main_points, $team->raw_main_points, 1) . '>' . format_score($team->main_points) . '</td>';
			echo '<td align="center"' . score_title($team->legacy_points, $team->raw_legacy_points, 1) . '>' . format_score($team->legacy_points, false) . '</td>';
			echo '<td align="center"' . score_title($team->extra_points, $team->raw_extra_points, 1) . '>' . format_score($team->extra_points, false) . '</td>';
			echo '<td align="center"' . score_title($team->penalty_points, $team->raw_penalty_points, 1) . '>' . format_score($team->penalty_points, false) . '</td>';
			if ($this->show_fk)
			{
				echo '<td align="center"' . score_title($team->night1_points, $team->raw_night1_points, 1) . '>' . format_score($team->night1_points, false) . '</td>';
			}
			echo '<td align="center">' . $team->games_count . '</td>';
			echo '<td align="center">' . $team->wins . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $teams_count);
	}
	
	private function team_view_manual_scoring()
	{
		global $_page;
		
		echo '<center><img src="images/repairs.png"><p><big><big><b>Under construction</b></big></big></p></center>';
	}
	
	private function individual_view()
	{
		global $_page;
		
		$players = tournament_scores($this->id, $this->flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_PER_ROLE, $this->scoring, $this->normalizer, $this->scoring_options);
		//print_json($players, $this->scoring);
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
		
		$series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, l.id, l.name, l.flags, st.stars, g.gaining'.
			' FROM series_tournaments st'.
			' JOIN series s ON s.id = st.series_id'.
			' JOIN leagues l ON l.id = s.league_id'.
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version'.
			' WHERE st.tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $s->league_id, $s->league_name, $s->league_flags, $s->stars, $gaining) = $row;
			$s->gaining = json_decode($gaining);
			$series[] = $s;
		}
		$series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation(PAGE_SIZE, $players_count);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'tournament=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="2" rowspan="2">'.get_label('Player').'</td>';
		echo '<td width="36" align="center" colspan="' . ($this->show_fk ? 6 : 5) . '">'.get_label('Points').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
		echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
		if ($this->has_normalizer)
		{
			echo '<td width="36" align="center" rowspan="2">'.get_label('Normalization rate').'</td>';
		}
		foreach ($series as $s)
		{
			echo '<td width="36" align="center" rowspan="2">';
			$series_pic->set($s->id, $s->name, $s->flags)->set($s->league_id, $s->league_name, $s->league_flags);
			$series_pic->show(ICONS_DIR, true, 32);
			echo '</td>';
		}
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
		$page_count = min($players_count, $page_start + PAGE_SIZE);
		for ($number = $page_start; $number < $page_count; ++$number)
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
			echo '<td align="center" class="' . $highlight . '">';
			echo $number + 1;
			echo '</td>';
			
			echo '<td><a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '">';
			echo '<table class="transp" width="100%"><tr><td width="56">';
			$tournament_user_pic->
				set($player->id, $player->name, $player->tournament_user_flags, 't' . $this->id)->
				set($player->id, $player->name, $player->club_user_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $player->flags);
			$tournament_user_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '">' . $player->name . '</a></td>';
			if (isset($player->nom_flags) && $player->nom_flags)
			{
				echo '<td align="right">';
				if (($player->nom_flags & COMPETITION_BEST_RED) && ($this->flags & TOURNAMENT_FLAG_AWARD_RED))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_BLACK) && ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_DON) && ($this->flags & TOURNAMENT_FLAG_AWARD_DON))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_SHERIFF) && ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_MVP) && ($this->flags & TOURNAMENT_FLAG_AWARD_MVP))
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
			echo '<td align="center" class="' . $highlight . '"' . score_title($player->points, $player->raw_points, $player->normalization) . '>' . format_score($player->points) . '</td>';
			echo '<td align="center"' . score_title($player->main_points, $player->raw_main_points, $player->normalization) . '>' . format_score($player->main_points) . '</td>';
			echo '<td align="center"' . score_title($player->legacy_points, $player->raw_legacy_points, $player->normalization) . '>' . format_score($player->legacy_points, false) . '</td>';
			echo '<td align="center"' . score_title($player->extra_points, $player->raw_extra_points, $player->normalization) . '>' . format_score($player->extra_points, false) . '</td>';
			echo '<td align="center"' . score_title($player->penalty_points, $player->raw_penalty_points, $player->normalization) . '>' . format_score($player->penalty_points, false) . '</td>';
			if ($this->show_fk)
			{
				echo '<td align="center"' . score_title($player->night1_points, $player->raw_night1_points, $player->normalization) . '>' . format_score($player->night1_points, false) . '</td>';
			}
			echo '<td align="center">' . $player->games_count . '</td>';
			echo '<td align="center">' . $player->wins . '</td>';
			if ($this->has_normalizer)
			{
				echo '<td align="center">' . format_coeff($player->normalization) . '</td>';
			}
			foreach ($series as $s)
			{
				if ($number < $this->num_players && $s->stars > 0)
				{
					echo '<td align="center">' . format_gain(get_gaining_points($this->id, $s->gaining, $s->stars, $number + 1, $player->points, $players_count, $this->rating_sum, $this->rating_sum_20, $this->traveling_distance, $this->guest_coeff, false)) . '</td>';
					// if ($player->id == 3536)
					// {
						// show_gaining_info($s->gaining);
					// }
				}
				else
				{
					echo '<td align="center"></td>';
				}
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $players_count);
	}
	
	private function individual_view_manual_scoring()
	{
		global $_page, $_lang;
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM tournament_places WHERE tournament_id = ?', $this->id);
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation(PAGE_SIZE, $count);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'tournament=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		if ($this->user_id > 0)
		{
			$pos_query = new DbQuery('SELECT place FROM tournament_places WHERE user_id = ? AND tournament_id = ?', $this->user_id, $this->id);
			if ($row = $pos_query->next())
			{
				list ($place) = $row;
				$_page = floor(($place - 1) / PAGE_SIZE);
			}
			else
			{
				$_page = 0;
				$this->no_user_error();
			}
		}
		
		$series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, l.id, l.name, l.flags, st.stars, g.gaining'.
			' FROM series_tournaments st'.
			' JOIN series s ON s.id = st.series_id'.
			' JOIN leagues l ON l.id = s.league_id'.
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version'.
			' WHERE st.tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $s->league_id, $s->league_name, $s->league_flags, $s->stars, $gaining) = $row;
			$s->gaining = json_decode($gaining);
			$series[] = $s;
		}
		$series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center">';
		echo '<td width="40"></td>';
		echo '<td colspan="3" align="left">'.get_label('Player').'</td>';
		echo '<td width="72">' . get_label('Sum') . '</td>';
		echo '<td width="72">' . get_label('Main') . '</td>';
		echo '<td width="72">' . get_label('Bonus') . '</td>';
		echo '<td width="72">' . get_label('FK') . '</td>';
		echo '<td width="72">' . get_label('Games played') . '</td>';
		foreach ($series as $s)
		{
			echo '<td width="72" align="center">';
			$series_pic->set($s->id, $s->name, $s->flags)->set($s->league_id, $s->league_name, $s->league_flags);
			$series_pic->show(ICONS_DIR, true, 32);
			echo '</td>';
		}
		echo '</tr>';
		
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, c.id, c.name, c.flags, p.place, p.main_points, p.bonus_points, p.shot_points, p.games_count, tu.flags, cu.flags FROM tournament_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN tournament_users tu ON tu.tournament_id = p.tournament_id AND tu.user_id = p.user_id' .
			' LEFT OUTER JOIN club_users cu ON cu.club_id = ? AND cu.user_id = p.user_id' .
			' WHERE p.tournament_id = ? ORDER BY p.place' .
			' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, $this->club_id, $this->id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_flags, $club_id, $club_name, $club_flags, $place, $main_points, $bonus_points, $shot_points, $games_count, $tournament_user_flags, $club_user_flags) = $row;
			$sum = $main_points;
			if (!is_null($bonus_points))
			{
				$sum += $bonus_points;
			}
			if (!is_null($shot_points))
			{
				$sum += $shot_points;
			}
			
			if ($user_id == $this->user_id)
			{
				echo '<tr class="darker">';
				$highlight = 'darkest';
			}
			else
			{
				echo '<tr>';
				$highlight = 'dark';
			}
			echo '<td align="center" class="' . $highlight . '">' . $place . '</td>';
			
			echo '<td width="50"><a href="user_info.php?id=' . $user_id . '&bck=1">';
			$tournament_user_pic->
				set($user_id, $user_name, $tournament_user_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			$tournament_user_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td>' . $user_name . '</td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id) && $club_id > 0)
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			
			echo '</td>';
			echo '<td align="center" class="' . $highlight . '">' . format_score($sum) . '</td>';
			echo '<td align="center">' . format_score($main_points) . '</td>';
			echo '<td align="center">' . (is_null($bonus_points) ? '' : format_score($bonus_points, false)) . '</td>';
			echo '<td align="center">' . (is_null($shot_points) ? '' : format_score($shot_points, false)) . '</td>';
			echo '<td align="center">' . (is_null($games_count) ? '' : $games_count) . '</td>';
			foreach ($series as $s)
			{
				if ($s->stars > 0)
				{
					echo '<td align="center">' . format_gain(get_gaining_points($this->id, $s->gaining, $s->stars, $place, $sum, $count, $this->rating_sum, $this->rating_sum_20, $this->traveling_distance, $this->guest_coeff, false)) . '</td>';
				}
				else
				{
					echo '<td align="center"></td>';
				}
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	private function mvp_view()
	{
		global $_page, $_mvp_sorting;
		
		$_mvp_sorting = SORT_BY_MVP;
		if (isset($_REQUEST['sort']))
		{
			$_mvp_sorting = (int)$_REQUEST['sort'];
		}
		
		$players = tournament_scores($this->id, $this->flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_PER_ROLE, $this->scoring, $this->normalizer, $this->scoring_options);
		usort($players, 'compare_mvp');
		// print_json($players, $this->scoring);
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
		
		$num_noms = 0;
		$flags = $this->flags & TOURNAMENT_AWARD_MASK;
		while ($flags)
		{
			++$num_noms;
			$flags &= $flags - 1;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_pages_navigation(PAGE_SIZE, $players_count);
		echo '</td><td align="right">';
		echo '<img src="images/find.png" class="control-icon" title="' . get_label('Find player') . '">';
		show_user_input('page', $this->user_name, 'tournament=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</td></tr></table></p>';
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="40" rowspan="2">&nbsp;</td>';
		echo '<td colspan="2" rowspan="2">'.get_label('Player').'</td>';
		echo '<td colspan="' . $num_noms . '" align="center">'.get_label('Points').'</td>';
		echo '<td width="36" rowspan="2" align="center">'.get_label('Games played').'</td>';
		echo '<td width="36" rowspan="2" align="center">'.get_label('Wins').'</td>';
		if ($this->has_normalizer)
		{
			echo '<td width="36" align="center">'.get_label('Normalization rate').'</td>';
		}
		echo '</tr>';
		
		echo '<tr class="th darker">';
		if ($this->flags & TOURNAMENT_FLAG_AWARD_MVP)
		{
			echo '<td width="50" align="center">';
			if ($_mvp_sorting != SORT_BY_MVP)
			{
				echo '<a href="#" onclick="goTo({sort:' . SORT_BY_MVP . ', page:undefined})">' . get_label('MVP') . '</a>';
			}
			else
			{
				echo get_label('MVP');
			}
			echo '</td>';
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_RED)
		{
			echo '<td width="50" align="center">';
			if ($_mvp_sorting != SORT_BY_RED)
			{
				echo '<a href="#" onclick="goTo({sort:' . SORT_BY_RED . ', page:undefined})"><img src="images/civ.png" style="opacity: 0.5;"></a>';
			}
			else
			{
				echo '<img src="images/civ.png">';
			}
			echo '</td>';
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK)
		{
			echo '<td width="50" align="center">';
			if ($_mvp_sorting != SORT_BY_BLACK)
			{
				echo '<a href="#" onclick="goTo({sort:' . SORT_BY_BLACK . ', page:undefined})"><img src="images/maf.png" style="opacity: 0.5;"></a>';
			}
			else
			{
				echo '<img src="images/maf.png">';
			}
			echo '</td>';
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF)
		{
			echo '<td width="50" align="center">';
			if ($_mvp_sorting != SORT_BY_SHERIFF)
			{
				echo '<a href="#" onclick="goTo({sort:' . SORT_BY_SHERIFF . ', page:undefined})"><img src="images/sheriff.png" style="opacity: 0.5;"></a>';
			}
			else
			{
				echo '<img src="images/sheriff.png">';
			}
			echo '</td>';
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_DON)
		{
			echo '<td width="50" align="center">';
			if ($_mvp_sorting != SORT_BY_DON)
			{
				echo '<a href="#" onclick="goTo({sort:' . SORT_BY_DON . ', page:undefined})"><img src="images/don.png" style="opacity: 0.5;"></a>';
			}
			else
			{
				echo '<img src="images/don.png">';
			}
			echo '</td>';
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
			echo '<td align="center" class="' . $highlight . '">';
			echo $number + 1;
			echo '</td>';
			
			echo '<td><a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '">';
			echo '<table class="transp" width="100%"><tr><td width="56">';
			$tournament_user_pic->
				set($player->id, $player->name, $player->tournament_user_flags, 't' . $this->id)->
				set($player->id, $player->name, $player->club_user_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $player->flags);
			$tournament_user_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '">' . $player->name . '</a></td>';
			if (isset($player->nom_flags) && $player->nom_flags)
			{
				echo '<td align="right">';
				if (($player->nom_flags & COMPETITION_BEST_RED) && ($this->flags & TOURNAMENT_FLAG_AWARD_RED))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_BLACK) && ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_DON) && ($this->flags & TOURNAMENT_FLAG_AWARD_DON))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_BEST_SHERIFF) && ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF))
				{
					echo '<img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span>';
				}
				if (($player->nom_flags & COMPETITION_MVP) && ($this->flags & TOURNAMENT_FLAG_AWARD_MVP))
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
			if ($this->flags & TOURNAMENT_FLAG_AWARD_MVP)
			{
				echo '<td align="center"' . ($_mvp_sorting == SORT_BY_MVP ? ' class="dark"' : '') . '>' . format_score($player->mvp_points) . '</td>';
			}
			if ($this->flags & TOURNAMENT_FLAG_AWARD_RED)
			{
				echo '<td align="center"' . ($_mvp_sorting == SORT_BY_RED ? ' class="dark"' : '') . '>' . format_score($player->roles[ROLE_CIVILIAN]->mvp_points + $player->roles[ROLE_SHERIFF]->mvp_points) . '</td>';
			}
			if ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK)
			{
				echo '<td align="center"' . ($_mvp_sorting == SORT_BY_BLACK ? ' class="dark"' : '') . '>' . format_score($player->roles[ROLE_MAFIA]->mvp_points + $player->roles[ROLE_DON]->mvp_points) . '</td>';
			}
			if ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF)
			{
				echo '<td align="center"' . ($_mvp_sorting == SORT_BY_SHERIFF ? ' class="dark"' : '') . '>' . format_score($player->roles[ROLE_SHERIFF]->mvp_points) . '</td>';
			}
			if ($this->flags & TOURNAMENT_FLAG_AWARD_DON)
			{
				echo '<td align="center"' . ($_mvp_sorting == SORT_BY_DON ? ' class="dark"' : '') . '>' . format_score($player->roles[ROLE_DON]->mvp_points) . '</td>';
			}
			echo '<td align="center">' . $player->games_count . '</td>';
			echo '<td align="center">' . $player->wins . '</td>';
			if ($this->has_normalizer)
			{
				echo '<td align="center">' . format_coeff($player->normalization) . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $players_count);
	}
	
	protected function show_body()
	{
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		
		$has_teams = ($this->flags & TOURNAMENT_FLAG_TEAM) != 0;
		$has_mvp = ($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0 && ($this->flags & TOURNAMENT_AWARD_MASK) != 0;
		$has_tabs = $has_teams || $has_mvp;
		$view = $has_teams ? VIEW_TEAM : VIEW_NORMAL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}

		if (($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
			if (($this->flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
			{
				$scoring_select_flags = SCORING_SELECT_FLAG_NO_OPTIONS;
			}
			else
			{
				$scoring_select_flags = SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION;
			}
			show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'submitScoring', $scoring_select_flags);
		}
		
		if ($has_tabs)
		{
			echo '<div class="tab">';
			if ($has_teams)
			{
				echo '<button ' . ($view == VIEW_TEAM ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_TEAM . ', page:undefined})">' . get_label('Team standings') . '</button>';
			}
			echo '<button ' . ($view == VIEW_NORMAL ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_NORMAL . ', page:undefined})">' . get_label('Player standings') . '</button>';
			if ($has_mvp)
			{
				echo '<button ' . ($view == VIEW_MVP ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_MVP . ', page:undefined})">' . get_label('MVP') . '</button>';
			}
			echo '</div>';
			echo '<div class="tabcontent">';
		}
		
		if ($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE)
		{
			if ($view == VIEW_TEAM)
			{
				$this->team_view_manual_scoring();
			}
			else
			{
				$this->individual_view_manual_scoring();
			}
		}
		else switch ($view)
		{
		case VIEW_TEAM:
			$this->team_view();
			break;
		case VIEW_MVP:
			$this->mvp_view();
			break;
		case VIEW_NORMAL:
		default:
			$this->individual_view();
			break;
		}
		
		if ($has_tabs)
		{
			echo '</div>';
		}

		
		echo '<table width="100%"><tr valign="top"><td>';
		echo '</td><td id="comments"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("tournament", <?php echo $this->id; ?>, 5);
			
			function submitScoring(s)
			{
				goTo({ sid: s.sId, sver: s.sVer, nid: s.nId, nver: s.nVer, sops: s.ops, page: 0 });
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
