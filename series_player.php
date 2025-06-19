<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/tournament.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/pages.php';
require_once 'include/player_stats.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('VIEW_TOURNAMENTS', 0);
define('VIEW_GAMES', 1);
define('VIEW_STATS', 2);
define('VIEW_COUNT', 3);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_RATING | FLAG_FILTER_NO_CANCELED);

define('TYPE_TOURNAMENT', 0);
define('TYPE_SERIES', 1);
define('TYPE_EXTRA_POINTS', 2);

function compare_tournaments($tournament1, $tournament2)
{
	$ends1 = $tournament1->time + $tournament1->duration;
	$ends2 = $tournament2->time + $tournament2->duration;
	if ($ends1 != $ends2)
	{
		return $ends2 - $ends1;
	}
	if ($tournament1->time != $tournament2->time)
	{
		return $tournament2->time - $tournament1->time;
	}
	if ($tournament1->type != $tournament2->type)
	{
		return $tournament2->type - $tournament1->type;
	}
	return $tournament2->id - $tournament1->id;
}

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		global $_page, $_lang;
		
		parent::prepare();
		
		$this->view = VIEW_TOURNAMENTS;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < 0 || $this->view >= VIEW_COUNT)
			{
				$this->view = VIEW_TOURNAMENTS;
			}
		}
		
		if (isset($_REQUEST['gaining_id']))
		{
			$this->gaining_id = (int)$_REQUEST['gaining_id'];
			if (isset($_REQUEST['gaining_version']))
			{
				$this->gaining_version = (int)$_REQUEST['gaining_version'];
				list($this->gaining) =  Db::record(get_label('gaining system'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $this->gaining_id, $this->gaining_version);
			}
			else
			{
				list($this->gaining, $this->gaining_version) = Db::record(get_label('gaining'), 'SELECT gaining, version FROM gaining_versions WHERE gaining_id = ? ORDER BY version DESC LIMIT 1', $this->gaining_id);
			}
		}
		else
		{
			list($this->gaining) =  Db::record(get_label('gaining'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $this->gaining_id, $this->gaining_version);
		}
		$this->gaining = json_decode($this->gaining);
		
		$this->user_id = 0;
		if (!isset($_REQUEST['user_id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$this->user_id = (int)$_REQUEST['user_id'];
		list($this->user_name, $this->user_flags) = Db::record(get_label('user'), 'SELECT nu.name, u.flags FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $this->user_id);
		
		$this->player_pic = new Picture(USER_PICTURE);
		$this->player_pic->set($this->user_id, $this->user_name, $this->user_flags);
	}
	
	protected function show_body()
	{
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td align="right">';
		echo get_label('Select a player') . ': ';
		show_user_input('user_name', '', 'must&series=' . $this->id, get_label('Select a player'), 'selectPlayer');
		echo '</td></tr></table></p>';
		
		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_TOURNAMENTS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_TOURNAMENTS . ',page:undefined})">' . get_label('Tournaments') . '</button>';
		echo '<button ' . ($this->view == VIEW_GAMES ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_GAMES . ',page:undefined})">' . get_label('Games') . '</button>';
		echo '<button ' . ($this->view == VIEW_STATS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_STATS . ',page:undefined})">' . get_label('Stats') . '</button>';
		echo '</div>';

		switch ($this->view)
		{
			case VIEW_TOURNAMENTS:
				$this->show_tournaments();
				break;
			case VIEW_GAMES:
				$this->show_games();
				break;
			case VIEW_STATS:
				$this->show_stats();
				break;
		}
	}
	
	private function show_tournaments()
	{
		global $_lang;
		
		$default_timezone = get_timezone();
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td colspan="3">';
		echo '<table class="transp" width="100%"><tr><td width="72">';
		echo '<a href="user_info.php?bck=1&id=' . $this->user_id . '">';
		$this->player_pic->show(ICONS_DIR, false, 64);
		echo '</a>';
		echo '</td><td>' . $this->user_name . '</td></tr>';
		echo '</table>';
		echo '</td>';

		echo '<td width="80">Place</td>';
		echo '<td width="80">Points</td>';
		echo '<td width="80">Players participated</td>';
		echo '</tr>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		
		$t_sum_power = (int)get_gainig_sum_power($this->gaining, false);
		if ($t_sum_power > 0)
		{
			$t_score_query = 'POW(p.main_points + p.bonus_points + p.shot_points, ' . $t_sum_power . '), SUM(POW(p1.main_points + p1.bonus_points + p1.shot_points, ' . $t_sum_power . '))';
		}
		else
		{
			$t_score_query = '0, 0';
		}
		
		$s_sum_power = (int)get_gainig_sum_power($this->gaining, true);
		if ($s_sum_power > 0)
		{
			$s_score_query = 'POW(p.score, ' . $s_sum_power . '), SUM(POW(p1.score, ' . $s_sum_power . '))';
		}
		else
		{
			$s_score_query = '0, 0';
		}
		
		$delim = '';
		$cs_tournaments = '';
		$tournaments = array();
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, c.id, c.name, c.flags, s.stars, s.flags, p.place, n.name, t.start_time, t.duration, ct.timezone, COUNT(p1.user_id), '.$t_score_query.
			' FROM tournament_places p'.
			' JOIN tournaments t ON t.id = p.tournament_id'.
			' JOIN tournament_places p1 ON p1.tournament_id = t.id'.
			' JOIN series_tournaments s ON s.tournament_id = t.id AND s.series_id = ?'.
			' JOIN clubs c ON c.id = t.club_id'.
			' JOIN addresses a ON a.id = t.address_id'.
			' JOIN cities ct ON ct.id = a.city_id'.
			' JOIN names n ON n.id = ct.name_id AND (n.langs & '.$_lang.') <> 0'.
			' WHERE p.user_id = ?'.
			' GROUP BY t.id', $this->id, $this->user_id);
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list(
				$tournament->id, $tournament->name, $tournament->flags, $tournament->club_id, $tournament->club_name, $tournament->club_flags, 
				$tournament->stars, $tournament->series_tournament_flags, $tournament->place, $tournament->city_name, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->players_count, $tournament->points, $tournament->points_sum) = $row;
			$tournament->exclude = (($tournament->series_tournament_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED) != 0);
			$tournament->score = get_gaining_points(create_gaining_table($this->gaining, $tournament->stars, $tournament->players_count, $tournament->points_sum, false), $tournament->place, $tournament->points);
			$tournament->type = TYPE_TOURNAMENT;
			$tournaments[] = $tournament;
			$cs_tournaments .= $delim . $tournament->id;
			$delim = ',';
		}

		$delim = '';
		$cs_child_series = '';
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, l.id, l.name, l.flags, ss.stars, ss.flags, p.place, s.start_time, s.duration, COUNT(p1.user_id), '.$s_score_query.
			' FROM series_places p'.
			' JOIN series s ON s.id = p.series_id'.
			' JOIN series_places p1 ON p1.series_id = s.id'.
			' JOIN series_series ss ON ss.child_id = s.id AND ss.parent_id = ?'.
			' JOIN leagues l ON l.id = s.league_id'.
			' WHERE p.user_id = ? AND (s.flags & ' . SERIES_FLAG_FINISHED . ') <> 0'.
			' GROUP BY s.id', $this->id, $this->user_id);
		while ($row = $query->next())
		{
			$c_series = new stdClass();
			list(
				$c_series->id, $c_series->name, $c_series->flags, $c_series->league_id, $c_series->league_name, $c_series->league_flags, 
				$c_series->stars, $c_series->series_series_flags, $c_series->place, $c_series->time, $c_series->duration, 
				$c_series->players_count, $c_series->points, $c_series->points_sum) = $row;
			$c_series->exclude = (($c_series->series_series_flags & SERIES_SERIES_FLAG_NOT_PAYED) != 0);
			$c_series->score = get_gaining_points(create_gaining_table($this->gaining, $c_series->stars, $c_series->players_count, $c_series->points_sum, true), $c_series->place, $c_series->points);
			$c_series->type = TYPE_SERIES;
			$c_series->timezone = $default_timezone;
			$tournaments[] = $c_series;
			$cs_child_series .= $delim . $c_series->id;
			$delim = ',';
		}
		
		$query = new DbQuery(
			'SELECT time, reason, points FROM series_extra_points WHERE series_id = ? AND user_id = ?', $this->id, $this->user_id);
		while ($row = $query->next())
		{
			$c_points = new stdClass();
			list($c_points->time, $c_points->name, $c_points->score) = $row;
			$c_points->duration = '';
			$c_points->players_count = '';
			$c_points->stars = 0;
			$c_points->place = '';
			$c_points->exclude = false;
			$c_points->type = TYPE_EXTRA_POINTS;
			$c_points->timezone = $default_timezone;
			$tournaments[] = $c_points;
		}
		
		usort($tournaments, 'compare_tournaments');

		if (isset($this->gaining->maxTournaments))
		{
			if ($this->gaining->maxTournaments <= 0)
			{
				foreach ($tournaments as $tournament)
				{
					$tournament->exclude = true;
				}
				foreach ($child_series as $c_series)
				{
					$c_series->exclude = true;
				}
			}
			else if (count($tournaments) > $this->gaining->maxTournaments)
			{
				$counted = array();
				$min_score = $tournaments[0]->score;
				$min_index = 0;
				$counted[] = $tournaments[0];
				for ($i = 1; $i < count($tournaments); ++$i)
				{
					$tournament = $tournaments[$i];
					if ($tournament->exclude)
					{
						continue;
					}
					else if (count($counted) >= $this->gaining->maxTournaments)
					{
						break;
					}
					
					if ($tournament->score < $counted[$min_index]->score)
					{
						$min_score = $tournament->score;
						$min_index = $i;
					}
					$counted[] = $tournament;
				}
				
				for (; $i < count($tournaments); ++$i)
				{
					$tournament = $tournaments[$i];
					if ($tournament->exclude)
					{
						continue;
					}
					
					// $sep = '';
					// foreach ($counted as $c)
					// {
						// echo $sep . $c->score;
						// $sep = ', ';
					// }
					// echo '<br>';
					
					if ($min_index < 0)
					{
						$min_score = $counted[0]->score;
						$min_index = 0;
						for ($j = 1; $j < count($counted); ++$j)
						{
							if ($counted[$j]->score < $min_score)
							{
								$min_score = $counted[$j]->score;
								$min_index = $j;
							}
						}
					}
					
					if ($tournament->score > $min_score)
					{
						$counted[$min_index]->exclude = true;
						$counted[$min_index] = $tournament;
						$min_index = -1;
						$min_score = 0;
					}
					else
					{
						$tournament->exclude = true;
					}
				}
				// $sep = '';
				// foreach ($counted as $c)
				// {
					// echo $sep . $c->score;
					// $sep = ', ';
				// }
				// echo '<br>';
			}
		}
		
		// Get other series
		if ($cs_tournaments != '')
		{
			$query = new DbQuery(
				'SELECT st.tournament_id, st.stars, s.id, s.name, s.flags, l.id, l.name, l.flags' .
				' FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN tournaments t ON t.id = st.tournament_id' .
				' JOIN leagues l ON l.id = s.league_id' .
				' WHERE st.tournament_id IN (' . $cs_tournaments . ') ORDER BY t.start_time + t.duration DESC, t.start_time DESC, t.id DESC');
			$current_tournament = 0;
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags) = $row;
				for (; $current_tournament < count($tournaments); ++$current_tournament)
				{
					$t = $tournaments[$current_tournament];
					if ($t->type == TYPE_TOURNAMENT && $t->id == $tournament_id)
					{
						break;
					}
				}
				if ($current_tournament < count($tournaments))
				{
					if ($series_id != $this->id)
					{
						$series = new stdClass();
						$series->stars = $stars;
						$series->id = $series_id;
						$series->name = $series_name;
						$series->flags = $series_flags;
						$series->league_id = $league_id;
						$series->league_name = $league_name;
						$series->league_flags = $league_flags;
						$tournaments[$current_tournament]->series[] = $series;
					}
				}
			}
		}
		
		if ($cs_child_series != '')
		{
			$query = new DbQuery(
				'SELECT ss.child_id, ss.stars, s.id, s.name, s.flags, l.id, l.name, l.flags' .
				' FROM series_series ss' .
				' JOIN series s ON s.id = ss.parent_id' .
				' JOIN series c ON c.id = ss.child_id' .
				' JOIN leagues l ON l.id = s.league_id' .
				' WHERE ss.child_id IN (' . $cs_child_series . ') ORDER BY c.start_time + c.duration DESC, c.start_time DESC, c.id DESC');
			$current_tournament = 0;
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags) = $row;
				for (; $current_tournament < count($tournaments); ++$current_tournament)
				{
					$t = $tournaments[$current_tournament];
					if ($t->type == TYPE_SERIES && $t->id == $tournament_id)
					{
						break;
					}
				}
				if ($current_tournament < count($tournaments))
				{
					if ($series_id != $this->id)
					{
						$series = new stdClass();
						$series->stars = $stars;
						$series->id = $series_id;
						$series->name = $series_name;
						$series->flags = $series_flags;
						$series->league_id = $league_id;
						$series->league_name = $league_name;
						$series->league_flags = $league_flags;
						$tournaments[$current_tournament]->series[] = $series;
					}
				}
			}
		}
		
		$league_pic = new Picture(LEAGUE_PICTURE);
		$series_pic = new Picture(SERIES_PICTURE, $league_pic);
		$sum = 0;
		$num = 0;
		foreach ($tournaments as $tournament)
		{
			echo '<tr align="center">';
			echo '<td width="30"><b>'.++$num.'</b></td>';
			echo '<td><table width="100%" class="transp"><tr><td width="58">';
			switch ($tournament->type)
			{
				case TYPE_TOURNAMENT:
					$link_beg = '<a href="tournament_player.php?user_id=' . $this->user_id . '&id=' . $tournament->id . '&bck=1">';
					$link_end = '</a>';
					echo $link_beg;
					$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
					$tournament_pic->show(ICONS_DIR, true, 50, 50, NULL, ($tournament->series_tournament_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED) ? 'not_payed.png' : NULL);
					echo $link_end;
					break;
				case TYPE_SERIES:
					$link_beg = '<a href="series_player.php?user_id=' . $this->user_id . '&id=' . $tournament->id . '&bck=1">';
					$link_end = '</a>';
					echo $link_beg;
					$series_pic->set($tournament->id, $tournament->name, $tournament->flags)->set($tournament->league_id, $tournament->league_name, $tournament->league_flags);
					$series_pic->show(ICONS_DIR, true, 50, 50, NULL, ($tournament->series_series_flags & SERIES_SERIES_FLAG_NOT_PAYED) ? 'not_payed.png' : NULL);
					echo $link_end;
					break;
				case TYPE_EXTRA_POINTS:
					$link_beg = '';
					$link_end = '';
					echo '<img src="images/transp.png" width="50">';
					break;
			}
			echo '</td><td>' . $link_beg . $tournament->name . $link_end;
			echo '<br><font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($tournament->stars) . '</font></td>';
			if (isset($tournament->series))
			{
				foreach ($tournament->series as $series)
				{
					echo '<td width="64" align="center" valign="center">';
					echo '<font style="color:#B8860B; font-size:14px;">' . tournament_stars_str($series->stars) . '</font>';
					echo '<br><a href="series_standings.php?bck=1&id=' . $series->id . '">';
					$series_pic->set($series->id, $series->name, $series->flags)->set($series->league_id, $series->league_name, $series->league_flags);
					$series_pic->show(ICONS_DIR, false, 32);
					echo '</a></td>';
				}
			}
			echo '</tr></table></td>';
			
			echo '<td width="160" valign="center" class="dark">';
			echo '<table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			switch ($tournament->type)
			{
				case TYPE_TOURNAMENT:
					$club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
					$club_pic->show(ICONS_DIR, false, 40);
					break;
				case TYPE_SERIES:
					$league_pic->set($tournament->league_id, $tournament->league_name, $tournament->league_flags);
					$league_pic->show(ICONS_DIR, false, 40);
					break;
				case TYPE_EXTRA_POINTS:
					break;
			}
			echo '</td>';
			echo '<td>';
			if ($tournament->type == TYPE_TOURNAMENT)
			{
				echo '<b>' . $tournament->city_name  . '</b><br>';
			}
			echo format_date_period($tournament->time, $tournament->duration, $tournament->timezone) . '</td>';
			echo '</tr></table></td>';
			
			echo '<td><a href="javascript:showGaining(' . $tournament->players_count . ', ' . $tournament->stars . ', ' . $tournament->place . ')">';
			if ($tournament->place > 0 && $tournament->place < 4)
			{
				echo '<img src="images/' . $tournament->place . '-place.png" width="48" title="' . get_label('[0] place', $tournament->place) . '">';
			}
			else if ($tournament->place < 11)
			{
				if ($tournament->exclude)
				{
					echo '<b><font color="#808080">' . $tournament->place . '</font></b>';
				}
				else
				{
					echo '<b>' . $tournament->place . '</b>';
				}
			}
			else if ($tournament->exclude)
			{
				echo '<font color="#808080">' . $tournament->place . '</font>';
			}
			else
			{
				echo $tournament->place;
			}
			echo '</a></td>';
			if ($tournament->exclude)
			{
				echo '<td class="dark"><font color="#808080">' . format_score($tournament->score) . '</font></td>';
				echo '<td><font color="#808080">' . $tournament->players_count . '</font></td>';
			}
			else
			{
				echo '<td class="dark"><b>' . format_score($tournament->score) . '<b></td>';
				echo '<td>' . $tournament->players_count . '</td>';
				$sum += $tournament->score;
			}
			echo '</tr>';
		}
		echo '<tr class="darker" style="height:50px;"><td colspan="4"><b>' . get_label('Total') . ':</b></td><td align="center"><b>' . format_score($sum) . '</b></td><td></td></tr>';
		echo '</table></p>';
	}
	
	private function show_games()
	{
		global $_page, $_lang;
		
		$roles = 0;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}

		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$result_filter = 0;
		if (isset($_REQUEST['result']))
		{
			$result_filter = (int)$_REQUEST['result'];
			if ($result_filter < 0 || $result_filter > 5)
			{
				$result_filter = 0;
			}
		}
		
		$club_pic = new Picture(CLUB_PICTURE);
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		
		echo '<p><select id="result" onChange="filterChanged()">';
		show_option(0, $result_filter, get_label('All games'));
		show_option(1, $result_filter, get_label('Town wins'));
		show_option(2, $result_filter, get_label('Mafia wins'));
		show_option(3, $result_filter, get_label('Ties'));
		show_option(4, $result_filter, get_label('[0] wins', $this->user_name));
		show_option(5, $result_filter, get_label('[0] losses', $this->user_name));
		echo '</select> ';
		show_roles_select($roles, 'rolesChanged()', get_label('Games where [0] was in a specific role.', $this->user_name), ROLE_NAME_FLAG_SINGLE);
		show_checkbox_filter(array(get_label('with video'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</p>';
		
		$condition = new SQL();
		if ($filter & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}
		
		$condition->add(get_roles_condition($roles));
		switch ($result_filter)
		{
			case 1:
				$condition->add(' AND g.result = '.GAME_RESULT_TOWN);
				break;
			case 2:
				$condition->add(' AND g.result = '.GAME_RESULT_MAFIA);
				break;
			case 3:
				$condition->add(' AND g.result = '.GAME_RESULT_TIE);
				break;
			case 4:
				$condition->add(' AND p.won > 0');
				break;
			case 5:
				$condition->add(' AND p.won = 0');
				break;
		}
		
		$subseries_csv = get_subseries_csv($this->id);
		
		list ($count) = Db::record(get_label('player'), 
			'SELECT count(*) FROM players p'.
			' JOIN games g ON g.id = p.game_id'.
			' JOIN series_tournaments s ON s.tournament_id = g.tournament_id'.
			' WHERE p.user_id = ? AND s.series_id IN (' . $subseries_csv . ')', $this->user_id, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td>';
		echo '<table class="transp" width="100%"><tr><td width="72">';
		echo '<a href="user_info.php?bck=1&id=' . $this->user_id . '">';
		$this->player_pic->show(ICONS_DIR, false, 64);
		echo '</a>';
		echo '</td><td>' . $this->user_name . '</td></tr>';
		echo '</table>';
		echo '</td><td width="48">'.get_label('Club').'</td><td width="240">'.get_label('Tournament').'</td><td width="48">'.get_label('Role').'</td><td width="48">'.get_label('Result').'</td><td width="100">'.get_label('Rating').'</td></tr>';
		
		$query = new DbQuery(
			'SELECT g.id, c.id, c.name, c.flags, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.flags, p.role, p.rating_before, p.rating_earned, g.video_id, e.id, e.name, e.flags, t.id, t.name, t.flags, a.id, a.name, a.flags FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN clubs c ON c.id = g.club_id' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN tournaments t ON t.id = g.tournament_id' .
			' JOIN series_tournaments s ON s.tournament_id = g.tournament_id' .
			' JOIN addresses a ON a.id = e.address_id' .
			' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
			' LEFT OUTER JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
			' JOIN cities ct ON ct.id = a.city_id' .
			' WHERE p.user_id = ? AND s.series_id IN (' . $subseries_csv . ')', 
			$this->user_id, $condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list (
				$game_id, $club_id, $club_name, $club_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, 
				$game_result, $flags, $role, $rating_before, $rating_earned, $video_id, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $address_id, $address_name, $address_flags) = $row;
		
			echo '<tr align="center"';
			if (($flags & (GAME_FLAG_RATING | GAME_FLAG_CANCELED)) != GAME_FLAG_RATING)
			{
				echo ' class="dark"';
			}
			echo '>';
		
			echo '<td align="left" style="padding-left:12px;">';
			if ($video_id != NULL)
			{
				echo '<table class="transp" width="100%"><tr><td>';
			}
			echo '<a href="view_game.php?id=' . $game_id . '&user_id=' . $this->user_id . '&bck=1"><b>' . get_label('Game #[0]', $game_id) . '</b><br>';
			echo format_date($start, $timezone, true) . '</a>';
			if ($video_id != NULL)
			{
				echo '</td><td align="right"><a href="javascript:mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/video.png" width="40" height="40"></a>';
				echo '</td></tr></table>';
			}
			echo '</td>';
			
			echo '<td>';
			$club_pic->
				set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			
			echo '<td class="dark">';
			$tournament_pic->
				set($tournament_id, $tournament_name, $tournament_flags);
			echo '<table class="transp" width="100%"><tr><td width="56">';
			$tournament_pic->show(ICONS_DIR, true, 48);
			echo '</td><td>' . $tournament_name . ': ' . $event_name . '</td></tr></table>';
			echo '</td>';

			$win = 0;
			echo '<td>';
			switch ($role)
			{
				case 0: // civil;
					echo '<img src="images/civ.png" title="' . get_label('civil') . '" style="opacity: 0.5;">';
					$win = $game_result == GAME_RESULT_TOWN ? 1 : 2;
					break;
				case 1: // sherif;
					echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
					$win = $game_result == GAME_RESULT_TOWN ? 1 : 2;
					break;
				case 2: // mafia;
					echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
					$win = $game_result == GAME_RESULT_MAFIA ? 1 : 2;
					break;
				case 3: // don
					echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
					$win = $game_result == GAME_RESULT_MAFIA ? 1 : 2;
					break;
			}
			echo '</td>';
			echo '<td>';
			switch ($win)
			{
				case 1:
					echo '<img src="images/won.png" title="' . get_label('win') . '" style="opacity: 0.8;">';
					break;
				case 2:
					echo '<img src="images/lost.png" title="' . get_label('loss') . '" style="opacity: 0.8;">';
					break;
			}
			echo '</td>';
			if ($flags & GAME_FLAG_CANCELED)
			{
				echo '<td class="darker">' . get_label('Canceled');
				if (($flags & GAME_FLAG_RATING) == 0)
				{
					echo '<br>' . get_label('Non-rating');
				}
				echo '';
			}
			else if (($flags & GAME_FLAG_RATING) == 0)
			{
				echo '<td class="darker">' . get_label('Non-rating') . '';
			}
			else
			{
				echo '<td>';
				echo format_rating(USER_INITIAL_RATING + $rating_before);
				if ($rating_earned >= 0)
				{
					echo ' + ' . format_rating($rating_earned);
				}
				else
				{
					echo ' - ' . format_rating(-$rating_earned);
				}
				echo ' = ' . format_rating(USER_INITIAL_RATING + $rating_before + $rating_earned);
			}
			// echo '<td>' . format_rating($rating_earned);
			echo '</td></tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	private function show_stats()
	{
		if (LOCK_DATE != NULL && !is_permitted(PERMISSION_ADMIN))
		{
			$dt = new DateTime(LOCK_DATE, new DateTimeZone(get_timezone()));
			if (time() < $dt->getTimestamp())
			{
				throw new Exc(get_label('Page is temporarily inavalable until [0].', LOCK_DATE));
			}
		}
			
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_roles_select($roles, 'rolesChanged()', get_label('Use stats of a specific role.'), ROLE_NAME_FLAG_SINGLE);
		echo '</td></tr></table></p>';
		
		$subseries_csv = get_subseries_csv($this->id);
		
		$condition = new SQL(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0 AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 AND g.tournament_id IN (SELECT tournament_id FROM series_tournaments WHERE series_id IN ('.$subseries_csv.'))');
		$stats = new PlayerStats($this->user_id, $roles, $condition);
		$mafs_in_legacy = $stats->guess3maf * 3 + $stats->guess2maf * 2 + $stats->guess1maf;
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th-short darker"><td colspan="2">';
		
		echo '<table class="transp" width="100%"><tr><td width="72">';
		echo '<a href="user_info.php?bck=1&id=' . $this->user_id . '">';
		$this->player_pic->show(ICONS_DIR, false, 64);
		echo '</a>';
		echo '</td><td>' . $this->user_name . '</td></tr>';
		echo '</table>';
		
		echo '</td></tr>';
		
		echo '<tr><td class="dark" width="300">'.get_label('Games played').':</td><td>' . $stats->games_played . '</td></tr>';
		if ($stats->games_played > 0)
		{
			echo '<tr><td class="dark" width="300">'.get_label('Wins').':</td><td>' . $stats->games_won . ' (' . number_format($stats->games_won*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Earned rating').':</td><td>' . get_label('[0] ([1] per game)', number_format($stats->rating, 2), number_format($stats->rating/$stats->games_played, 3)) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best player').':</td><td>' . $stats->best_player . ' (' . number_format($stats->best_player*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best move').':</td><td>' . $stats->best_move . ' (' . number_format($stats->best_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Auto-bonus removed').':</td><td>' . $stats->worst_move . ' (' . number_format($stats->worst_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Bonus points').':</td><td>' . number_format($stats->bonus, 2) . ' (' . number_format($stats->bonus/$stats->games_played, 3) . ' ' . get_label('per game') . ')</td></tr>';
			echo '<tr><td class="dark">'.get_label('Killed first night').':</td><td>' . $stats->killed_first_night . ' (' . number_format($stats->killed_first_night*100.0/$stats->games_played, 1) . '%)</td></tr>';
			if ($stats->killed_first_night > 0)
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (' . number_format($stats->guess3maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (' . number_format($stats->guess2maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (' . number_format($stats->guess1maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (' . number_format($mafs_in_legacy*100.0/($stats->killed_first_night * 3), 1) . '%)</td></tr>';
			}
			else
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (0.0%)</td></tr>';
			}
			echo '</table>';
		
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Voting') . '</td></tr>';
			
			$count = $stats->voted_civil + $stats->voted_mafia + $stats->voted_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Voted against civilians').':</td><td>';
			if ($stats->voted_civil > 0)
			{
				echo $stats->voted_civil . ' (' . number_format($stats->voted_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against mafia').':</td><td>';
			if ($stats->voted_mafia > 0)
			{
				echo $stats->voted_mafia . ' (' . number_format($stats->voted_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against sheriff').':</td><td>';
			if ($stats->voted_sheriff > 0)
			{
				echo $stats->voted_sheriff . ' (' . number_format($stats->voted_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->voted_by_civil + $stats->voted_by_mafia + $stats->voted_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by civilians').':</td><td>';
			if ($stats->voted_by_civil > 0)
			{
				echo $stats->voted_by_civil . ' (' . number_format($stats->voted_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by mafia').':</td><td>';
			if ($stats->voted_by_mafia > 0)
			{
				echo $stats->voted_by_mafia . ' (' . number_format($stats->voted_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by sheriff').':</td><td>';
			if ($stats->voted_by_sheriff > 0)
			{
				echo $stats->voted_by_sheriff . ' (' . number_format($stats->voted_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Nominating') . '</td></tr>';
			
			$count = $stats->nominated_civil + $stats->nominated_mafia + $stats->nominated_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Nominated civilians').':</td><td>';
			if ($stats->nominated_civil > 0)
			{
				echo $stats->nominated_civil . ' (' . number_format($stats->nominated_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated mafia').':</td><td>';
			if ($stats->nominated_mafia > 0)
			{
				echo $stats->nominated_mafia . ' (' . number_format($stats->nominated_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated sheriff').':</td><td>';
			if ($stats->nominated_sheriff > 0)
			{
				echo $stats->nominated_sheriff . ' (' . number_format($stats->nominated_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->nominated_by_civil + $stats->nominated_by_mafia + $stats->nominated_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by civilians').':</td><td>';
			if ($stats->nominated_by_civil > 0)
			{
				echo $stats->nominated_by_civil . ' (' . number_format($stats->nominated_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by mafia').':</td><td>';
			if ($stats->nominated_by_mafia > 0)
			{
				echo $stats->nominated_by_mafia . ' (' . number_format($stats->nominated_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by sheriff').':</td><td>';
			if ($stats->nominated_by_sheriff > 0)
			{
				echo $stats->nominated_by_sheriff . ' (' . number_format($stats->nominated_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Surviving') . '</td></tr>';
			foreach ($stats->surviving as $surviving)
			{
				switch ($surviving->type)
				{
					case KILL_TYPE_SURVIVED:
						echo '<tr><td class="dark" width="300">'.get_label('Survived').':</td><td>';
						break;
					case KILL_TYPE_DAY:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in day').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_NIGHT:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in night').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_WARNINGS:
						echo '<tr><td class="dark" width="300">'.get_label('Killed by warnings in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_GIVE_UP:
						echo '<tr><td class="dark" width="300">'.get_label('Left the game in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Kicked out in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_TEAM_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Made the opposite team win').' ' . $surviving->round . ':</td><td>';
						break;
					default:
						echo '<tr><td class="dark" width="300">'.get_label('Round').' ' . $surviving->round . ':</td><td>';
						break;
				}
				echo $surviving->count . ' (' . number_format($surviving->count*100.0/$stats->games_played, 2) . '%)</td></tr>';
			}
			echo '</table></p>';
			
			if ($roles == POINTS_BLACK || $roles == POINTS_MAFIA || $roles == POINTS_DON)
			{
				$mafia_stats = new MafiaStats($this->user_id, $roles, $condition);
				echo '<p><table class="bordered light" width="100%">';
				echo '<tr class="th-short darker"><td colspan="2">' . get_label('Mafia shooting') . '</td></tr>';
				
				$count = $mafia_stats->shots3_ok + $mafia_stats->shots3_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('3 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots3_ok . ' '.get_label('success;').' ' . $mafia_stats->shots3_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots3_ok*100/$count, 1) . get_label('% success rate.');
					if ($mafia_stats->shots3_fail > 0)
					{
						echo $mafia_stats->shots3_fail . ' '.get_label('times guilty in misses.');
					}
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots2_ok + $mafia_stats->shots2_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('2 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots2_ok . ' '.get_label('success;').' ' . $mafia_stats->shots2_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots2_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots1_ok + $mafia_stats->shots1_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('Single shooter').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots1_ok . ' '.get_label('success;').' ' . $mafia_stats->shots1_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots1_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				echo '</table></p>';
			}
			
			if ($roles == POINTS_SHERIFF)
			{
				$sheriff_stats = new SheriffStats($this->user_id, $condition);
				$count = $sheriff_stats->civil_found + $sheriff_stats->mafia_found;
				if ($count > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Sheriff stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Red checks').':</td><td>' . $sheriff_stats->civil_found . ' (' . number_format($sheriff_stats->civil_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->civil_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Black checks').':</td><td>' . $sheriff_stats->mafia_found . ' (' . number_format($sheriff_stats->mafia_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->mafia_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '</table></p>';
				}
			}
			
			if ($roles == POINTS_DON)
			{
				$don_stats = new DonStats($this->user_id, $condition);
				if ($don_stats->games_played > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Don stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found').':</td><td>' . $don_stats->sheriff_found . ' ' . $don_stats->games_played . '(' . number_format($don_stats->sheriff_found*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff arranged').':</td><td>' . $don_stats->sheriff_arranged . ' (' . number_format($don_stats->sheriff_arranged*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found first night').':</td><td>' . $stats->sheriff_found_first_night . ' (' . number_format($stats->sheriff_found_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff killed first night').':</td><td>' . $stats->sheriff_killed_first_night . ' (' . number_format($stats->sheriff_killed_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '</table></p>';
				}
			}
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Miscellaneous') . '</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Warnings').':</td><td>' . $stats->warnings . ' (' . number_format($stats->warnings/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Arranged by mafia').':</td><td>' . $stats->arranged . ' (' . number_format($stats->arranged/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by don').':</td><td>' . $stats->checked_by_don . ' (' . number_format($stats->checked_by_don*100/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by sheriff').':</td><td>' . $stats->checked_by_sheriff . ' (' . number_format($stats->checked_by_sheriff*100/$stats->games_played, 1) . '%)</td></tr>';
		}
		echo '</table></p>';
	}
	
	protected function js()
	{
		parent::js();
?>
	
		function showGaining(players, stars, place)
		{
			dlg.infoForm('form/gaining_show.php?id=<?php echo $this->gaining_id; ?>&version=<?php echo $this->gaining_version; ?>&players=' + players + '&stars=' + stars + '&place=' + place);
		}
		
		function selectPlayer(data)
		{
			goTo({ 'user_id': data.id });
		}
		
		function submitScoring(s)
		{
			goTo({ sid: s.sId, sver: s.sVer, nid: s.nId, nver: s.nVer, sops: s.ops });
		}
		
		function changeTournamentPlayer(tournamentId, userId, nickname)
		{
			dlg.form("form/tournament_change_player.php?tournament_id=" + tournamentId + "&user_id=" + userId + "&nick=" + nickname, function(r)
			{
				goTo({ 'user_id': r.user_id });
			});
		}
		
		function filterChanged()
		{
			goTo({result: $('#result').val(), filter: checkboxFilterFlags(), page: undefined});
		}
		
		function rolesChanged()
		{
			goTo({roles: $('#roles').val(), page: undefined});
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('player details'));

?>
