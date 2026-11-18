<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

define('VIEW_NORMAL', 0);
define('VIEW_TEAM', 1);
define('VIEW_MVP', 2);

define('SORT_BY_MVP', 0);
define('SORT_BY_RED', 1);
define('SORT_BY_BLACK', 2);
define('SORT_BY_CIV', 3);
define('SORT_BY_MAF', 4);
define('SORT_BY_SHERIFF', 5);
define('SORT_BY_DON', 6);

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
	case SORT_BY_CIV:
		$mvp_points1 = $player1->roles[ROLE_CIVILIAN]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_CIVILIAN]->mvp_points;
		break;
	case SORT_BY_MAF:
		$mvp_points1 = $player1->roles[ROLE_MAFIA]->mvp_points;
		$mvp_points2 = $player2->roles[ROLE_MAFIA]->mvp_points;
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
		$this->scoring->id = (int)$this->scoring_id; // it is needed for caching
		$this->scoring->version = (int)$this->scoring_version; // it is needed for caching
		
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
		$this->normalizer->id = (int)$this->normalizer_id; // it is needed for caching
		$this->normalizer->version = (int)$this->normalizer_version; // it is needed for caching
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
			$this->scoring_options->custom = true; // this is needed for caching
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
	
	// The pieces of the tables of the v3 design, shared by the views below: see ratings.php.

	// Above a table: the search for a player and how many there are.
	private function show_toolbar($count, $count_label)
	{
		echo '<div class="toolbar"><div class="field grow">' . PageBase::icon('search');
		show_user_input('page', $this->user_name, 'tournament=' . $this->id, get_label('Go to the page where a specific player is located.'));
		echo '</div><span class="count">' . get_label($count_label, number_format($count)) . '</span></div>';
	}

	// The place: the first three in red.
	private function rank_cell($place)
	{
		return '<td class="rk"><span class="medal' . ($place <= 3 ? ' m' . $place : '') . '">' . sprintf('%02d', $place) . '</span></td>';
	}

	// The picture of a player in this tournament: their photo for it, or for its club, or their own.
	private function avatar($pic, $id, $name, $tournament_reg_flags, $club_reg_flags, $flags)
	{
		$pic->
			set($id, $name, $tournament_reg_flags, 't' . $this->id)->
			set($id, $name, $club_reg_flags, 'c' . $this->club_id)->
			set($id, $name, $flags);
		return card_avatar_of($pic, $id, $name, '');
	}

	// A player: the picture and the name, the awards after it.
	private function player_cell($pic, $player, $url, $awards = '')
	{
		return '<td><a class="who" href="' . $url . '">' . $this->avatar($pic, $player->id, $player->name, $player->tournament_reg_flags, $player->club_reg_flags, $player->flags) .
			'<span class="who-txt"><span class="who-name">' . $player->name . '</span></span>' . ($awards == '' ? '' : '<span class="awards">' . $awards . '</span>') . '</a></td>';
	}

	// The wreaths of the nominations a player won.
	private function awards($player)
	{
		if (!isset($player->nom_flags) || !$player->nom_flags)
		{
			return '';
		}
		$mvp_flag = ($this->flags & TOURNAMENT_FLAG_AWARD_MVP) ? COMPETITION_MVP : 0;
		$best_red_flag = ($this->flags & TOURNAMENT_FLAG_AWARD_RED) ? (($this->flags & TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED) ? COMPETITION_BEST_CIV : COMPETITION_BEST_RED) : 0;
		$best_black_flag = ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK) ? (($this->flags & TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK) ? COMPETITION_BEST_MAF : COMPETITION_BEST_BLACK) : 0;
		$best_sheriff_flag = ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF) ? COMPETITION_BEST_SHERIFF : 0;
		$best_don_flag = ($this->flags & TOURNAMENT_FLAG_AWARD_DON) ? COMPETITION_BEST_DON : 0;

		$html = '';
		if ($player->nom_flags & $best_red_flag)
		{
			$html .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/civ.png"></span></span>';
		}
		if ($player->nom_flags & $best_black_flag)
		{
			$html .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/maf.png"></span></span>';
		}
		if ($player->nom_flags & $best_don_flag)
		{
			$html .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/don.png"></span></span>';
		}
		if ($player->nom_flags & $best_sheriff_flag)
		{
			$html .= '<span class="award"><img src="images/wreath.png" width="36"><span class="best-in-role"><img src="images/sheriff.png"></span></span>';
		}
		if ($player->nom_flags & $mvp_flag)
		{
			$html .= '<span class="award"><img src="images/wreath.png" width="36"><span class="mvp">MVP</span></span>';
		}
		return $html;
	}

	// A number of points: the sum stands out, the parts of it are quieter; a zero part is left empty.
	private function score_cell($points, $raw_points, $normalization, $class, $show_zero = true)
	{
		return '<td class="num ' . $class . '"' . score_title($points, $raw_points, $normalization) . '>' . format_score($points, $show_zero) . '</td>';
	}

	// The headings of the parts of the points.
	private function score_headings()
	{
		echo '<th class="num" scope="col">' . get_label('Sum') . '</th>';
		echo '<th class="num p3" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_MAIN, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_LEGACY, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_EXTRA, true) . '</th>';
		echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_PENALTY, true) . '</th>';
		if ($this->show_fk)
		{
			echo '<th class="num p4" scope="col">' . get_scoring_group_label($this->scoring, SCORING_GROUP_NIGHT1, true) . '</th>';
		}
	}

	// The parts of the points of a player or a team.
	private function score_cells($p, $normalization)
	{
		echo $this->score_cell($p->points, $p->raw_points, $normalization, 'strong');
		echo $this->score_cell($p->main_points, $p->raw_main_points, $normalization, 'p3');
		echo $this->score_cell($p->legacy_points, $p->raw_legacy_points, $normalization, 'p4 dim', false);
		echo $this->score_cell($p->extra_points, $p->raw_extra_points, $normalization, 'p4 dim', false);
		echo $this->score_cell($p->penalty_points, $p->raw_penalty_points, $normalization, 'p4 dim', false);
		if ($this->show_fk)
		{
			echo $this->score_cell($p->night1_points, $p->raw_night1_points, $normalization, 'p4 dim', false);
		}
	}

	// The series the tournament is in, each with the points of its rating a place brings.
	private function load_series()
	{
		global $_lang;

		$series = array();
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, ' . series_owner_fields() . ', st.stars, g.gaining, s.start_time, s.max_stars'.
			' FROM series_tournaments st'.
			' JOIN series s ON s.id = st.series_id'.
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0'.
			series_owner_join().
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version'.
			' WHERE st.tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			$s = new stdClass();
			list($s->id, $s->name, $s->flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $s->stars, $gaining, $start_time, $max_stars) = $row;
			$s->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$s->gaining = set_gaining_series_type(json_decode($gaining), series_type($start_time, $max_stars));
			$series[] = $s;
		}
		return $series;
	}

	// The heading of the column of a series: its picture, its name in the hint.
	private function series_headings($series)
	{
		$series_pic = new SeriesPicture();
		foreach ($series as $s)
		{
			echo '<th class="num p4 th-pic" scope="col" title="' . strip_tags($s->name) . '">';
			$series_pic->set($s->id, $s->name, $s->flags, $s->owner)->show(ICONS_DIR, true, 24);
			echo '</th>';
		}
	}

	private function team_view()
	{
		global $_page;
		
		$teams = tournament_scores($this->id, $this->flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_TEAMS, $this->scoring, $this->normalizer, $this->scoring_options);
		//print_json($teams, $this->scoring);
		$teams_count = count($teams);
		
		$total = $teams_count;
		show_pages_navigation(PAGE_SIZE, $total);

		$tournament_reg_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));

		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Team') . '</th>';
		$this->score_headings();
		echo '<th class="num p3" scope="col">' . get_label('Games') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		echo '</tr></thead><tbody>';

		$page_start = $_page * PAGE_SIZE;
		if ($teams_count > $page_start + PAGE_SIZE)
		{
			$teams_count = $page_start + PAGE_SIZE;
		}
		for ($number = $page_start; $number < $teams_count; ++$number)
		{
			$team = $teams[$number];
			echo '<tr>' . $this->rank_cell($number + 1);

			// The players of the team, their pictures overlapping, over the name of the team.
			echo '<td><div class="team"><span class="team-avs">';
			foreach ($team->players as $player)
			{
				echo '<a href="tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all . '" title="' . strip_tags($player->name) . '">' .
					$this->avatar($tournament_reg_pic, $player->id, $player->name, $player->tournament_reg_flags, $player->club_reg_flags, $player->flags) . '</a>';
			}
			echo '</span><span class="who-name">' . $team->name . '</span></div></td>';

			$this->score_cells($team, 1);
			echo '<td class="num p3">' . $team->games_count . '</td>';
			echo '<td class="num p3">' . $team->wins . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $total);
	}
	
	private function team_view_manual_scoring()
	{
		global $_page;
		
		echo '<center><img src="images/repairs.png"><p><big><big><b>Under construction</b></big></big></p></center>';
	}
	
	private function individual_view()
	{
		global $_page, $_lang;
		
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
		
		$series = $this->load_series();

		$this->show_toolbar($players_count, '[0] players');
		show_pages_navigation(PAGE_SIZE, $players_count);

		$tournament_reg_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		$club_pic = new Picture(CLUB_PICTURE);

		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		$this->score_headings();
		echo '<th class="num p3" scope="col">' . get_label('Games') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		if ($this->has_normalizer)
		{
			echo '<th class="num p4" scope="col" title="' . get_label('Normalization rate') . '">&times;</th>';
		}
		$this->series_headings($series);
		echo '</tr></thead><tbody>';

		$page_start = $_page * PAGE_SIZE;
		$page_count = min($players_count, $page_start + PAGE_SIZE);
		for ($number = $page_start; $number < $page_count; ++$number)
		{
			$player = $players[$number];
			echo '<tr' . ($player->id == $this->user_id ? ' class="me"' : '') . '>' . $this->rank_cell($number + 1);
			echo $this->player_cell($tournament_reg_pic, $player, 'tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all, $this->awards($player));
			echo '<td class="p2">' . card_club($club_pic, $player->club_id > 0 ? $player->club_id : NULL, $player->club_name, $player->club_flags) . '</td>';
			$this->score_cells($player, $player->normalization);
			echo '<td class="num p3">' . $player->games_count . '</td>';
			echo '<td class="num p3">' . $player->wins . '</td>';
			if ($this->has_normalizer)
			{
				echo '<td class="num p4 dim">' . format_coeff($player->normalization) . '</td>';
			}
			foreach ($series as $s)
			{
				// As complete_competitions.php counts them: whatever stars the series gives the tournament -
				// none at all included - go into the formula, which may or may not use them.
				echo '<td class="num p4 dim">';
				if ($number < $this->num_players)
				{
					echo format_gain(get_gaining_points($this->id, $s->gaining, $s->stars, $number + 1, $player->points, $players_count, $this->rating_sum, $this->rating_sum_20, $this->traveling_distance, $this->guest_coeff, false));
				}
				echo '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $players_count);
	}
	
	private function individual_view_manual_scoring()
	{
		global $_page, $_lang;
		
		$tournament_reg_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM tournament_places WHERE tournament_id = ?', $this->id);

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
		
		$series = $this->load_series();

		$this->show_toolbar($count, '[0] players');
		show_pages_navigation(PAGE_SIZE, $count);

		$club_pic = new Picture(CLUB_PICTURE);
		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="num" scope="col">' . get_label('Sum') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Main') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('Bonus') . '</th>';
		echo '<th class="num p4" scope="col">' . get_label('FK') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Games') . '</th>';
		$this->series_headings($series);
		echo '</tr></thead><tbody>';

		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, c.id, nc.name, c.flags, p.place, p.main_points, p.bonus_points, p.shot_points, p.games_count, tu.flags, cu.flags FROM tournament_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournament_regs tu ON tu.tournament_id = p.tournament_id AND tu.user_id = p.user_id' .
			' LEFT OUTER JOIN club_regs cu ON cu.club_id = ? AND cu.user_id = p.user_id' .
			' WHERE p.tournament_id = ? ORDER BY p.place' .
			' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, $this->club_id, $this->id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_flags, $club_id, $club_name, $club_flags, $place, $main_points, $bonus_points, $shot_points, $games_count, $tournament_reg_flags, $club_reg_flags) = $row;
			$sum = $main_points;
			if (!is_null($bonus_points))
			{
				$sum += $bonus_points;
			}
			if (!is_null($shot_points))
			{
				$sum += $shot_points;
			}
			
			echo '<tr' . ($user_id == $this->user_id ? ' class="me"' : '') . '>' . $this->rank_cell($place);
			echo '<td><a class="who" href="user_info.php?id=' . $user_id . '&bck=1">' . $this->avatar($tournament_reg_pic, $user_id, $user_name, $tournament_reg_flags, $club_reg_flags, $user_flags) .
				'<span class="who-txt"><span class="who-name">' . $user_name . '</span></span></a></td>';
			echo '<td class="p2">' . card_club($club_pic, $club_id, $club_name, $club_flags) . '</td>';
			echo '<td class="num strong">' . format_score($sum) . '</td>';
			echo '<td class="num p3">' . format_score($main_points) . '</td>';
			echo '<td class="num p4 dim">' . (is_null($bonus_points) ? '' : format_score($bonus_points, false)) . '</td>';
			echo '<td class="num p4 dim">' . (is_null($shot_points) ? '' : format_score($shot_points, false)) . '</td>';
			echo '<td class="num p3">' . (is_null($games_count) ? '' : $games_count) . '</td>';
			foreach ($series as $s)
			{
				echo '<td class="num p4 dim">' . format_gain(get_gaining_points($this->id, $s->gaining, $s->stars, $place, $sum, $count, $this->rating_sum, $this->rating_sum_20, $this->traveling_distance, $this->guest_coeff, false)) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
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
		
		$this->show_toolbar($players_count, '[0] players');
		show_pages_navigation(PAGE_SIZE, $players_count);

		$tournament_reg_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		$club_pic = new Picture(CLUB_PICTURE);

		// The nominations of the tournament, each a column the table can be sorted by.
		$noms = array();
		if ($this->flags & TOURNAMENT_FLAG_AWARD_MVP)
		{
			$noms[] = array(SORT_BY_MVP, get_label('MVP'));
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_RED)
		{
			$noms[] = array(($this->flags & TOURNAMENT_FLAG_AWARD_NO_SHERIFF_IN_RED) ? SORT_BY_CIV : SORT_BY_RED, '<img src="images/civ.png">');
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_BLACK)
		{
			$noms[] = array(($this->flags & TOURNAMENT_FLAG_AWARD_NO_DON_IN_BLACK) ? SORT_BY_MAF : SORT_BY_BLACK, '<img src="images/maf.png">');
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_SHERIFF)
		{
			$noms[] = array(SORT_BY_SHERIFF, '<img src="images/sheriff.png">');
		}
		if ($this->flags & TOURNAMENT_FLAG_AWARD_DON)
		{
			$noms[] = array(SORT_BY_DON, '<img src="images/don.png">');
		}

		echo '<div class="sheet"><table class="tbl mid">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		foreach ($noms as $nom)
		{
			list($sort, $label) = $nom;
			echo '<th class="num th-pic" scope="col"' . ($_mvp_sorting == $sort ? ' aria-sort="descending"' : '') . '><a class="th-sort" href="#" onclick="goTo({sort:' . ($sort == SORT_BY_MVP ? 'undefined' : $sort) . ', page:undefined}); return false;">' . $label . '</a></th>';
		}
		echo '<th class="num p3" scope="col">' . get_label('Games') . '</th>';
		echo '<th class="num p3" scope="col">' . get_label('Wins') . '</th>';
		if ($this->has_normalizer)
		{
			echo '<th class="num p4" scope="col" title="' . get_label('Normalization rate') . '">&times;</th>';
		}
		echo '</tr></thead><tbody>';

		$page_start = $_page * PAGE_SIZE;
		$page_count = min($players_count, $page_start + PAGE_SIZE);
		for ($number = $page_start; $number < $page_count; ++$number)
		{
			$player = $players[$number];
			echo '<tr' . ($player->id == $this->user_id ? ' class="me"' : '') . '>' . $this->rank_cell($number + 1);
			echo $this->player_cell($tournament_reg_pic, $player, 'tournament_player.php?user_id=' . $player->id . $this->tournament_player_params . $this->show_all, $this->awards($player));
			echo '<td class="p2">' . card_club($club_pic, $player->club_id > 0 ? $player->club_id : NULL, $player->club_name, $player->club_flags) . '</td>';
			foreach ($noms as $nom)
			{
				list($sort, $label) = $nom;
				switch ($sort)
				{
				case SORT_BY_CIV:
					$points = $player->roles[ROLE_CIVILIAN]->mvp_points;
					break;
				case SORT_BY_RED:
					$points = $player->roles[ROLE_CIVILIAN]->mvp_points + $player->roles[ROLE_SHERIFF]->mvp_points;
					break;
				case SORT_BY_MAF:
					$points = $player->roles[ROLE_MAFIA]->mvp_points;
					break;
				case SORT_BY_BLACK:
					$points = $player->roles[ROLE_MAFIA]->mvp_points + $player->roles[ROLE_DON]->mvp_points;
					break;
				case SORT_BY_SHERIFF:
					$points = $player->roles[ROLE_SHERIFF]->mvp_points;
					break;
				case SORT_BY_DON:
					$points = $player->roles[ROLE_DON]->mvp_points;
					break;
				default:
					$points = $player->mvp_points;
					break;
				}
				echo '<td class="num ' . ($_mvp_sorting == $sort ? 'strong' : 'dim') . '">' . format_score($points) . '</td>';
			}
			echo '<td class="num p3">' . $player->games_count . '</td>';
			echo '<td class="num p3">' . $player->wins . '</td>';
			if ($this->has_normalizer)
			{
				echo '<td class="num p4 dim">' . format_coeff($player->normalization) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $players_count);
	}
	
	protected function show_body()
	{
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		
		$has_teams = $this->team_size > 1;
		$has_mvp = ($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0 && ($this->flags & TOURNAMENT_AWARD_MASK) != 0;
		$has_tabs = $has_teams || $has_mvp;
		$view = $has_teams ? VIEW_TEAM : VIEW_NORMAL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}

		echo '<table class="transp" width="100%"><tr><td>';
		if (($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
			if (($this->flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
			{
				$scoring_select_flags = SCORING_SELECT_FLAG_NO_OPTIONS | SCORING_SELECT_FLAG_NO_NORMALIZER;
			}
			else
			{
				$scoring_select_flags = SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_NORMALIZER;
			}
			show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'submitScoring', $scoring_select_flags);
		}
		echo '</td><td align="right">';
		if ($this->is_manager)
		{
			echo '<button class="icon" title="'.get_label('Refresh scoring table').'" onclick="clearCache()"><img src="images/refresh.png"></button>';
		}
		echo '</td></tr></table>';
		
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
	
	protected function js()
	{
		if ($this->is_manager)
		{
?>		
			function clearCache()
			{
				json.post("api/ops/tournament.php", { op: 'refresh_table', tournament_id: <?php echo $this->id; ?> }, refr);
			}
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('Standings'));

?>
