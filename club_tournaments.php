<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/tournament.php';
require_once 'include/checkbox_filter.php';

define("CUT_NAME",45);
define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}
		
		$condition = new SQL(
			' FROM tournaments t ' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE t.club_id = ?',
			$this->id);
		if ($future)
		{
			$condition->add(' AND t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND t.start_time < UNIX_TIMESTAMP()');
			
			if ($filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id AND g.result > 0)');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
			}
		}
			
		echo '<div class="tab">';
		echo '<button ' . ($future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';
		
		if (!$future)
		{
			echo '<p><table class="transp" width="100%"><tr><td>';
			show_checkbox_filter(array(get_label('with video'), get_label('unplayed tournaments'), get_label('canceled tournaments')), $filter, 'filterTournaments');
			echo '</td></tr></table></p>';
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		if ($future)
		{
			$order_by = ' ORDER BY t.start_time, t.id';
		}
		else
		{
			$order_by = ' ORDER BY t.start_time DESC, t.id DESC';
		}
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$series_pic = new Picture(SERIES_PICTURE, new Picture(LEAGUE_PICTURE));
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, ct.timezone, t.langs, a.id, a.address, a.flags,' .
			' (SELECT count(DISTINCT _p.user_id) FROM players _p JOIN games _g ON _g.id = _p.game_id WHERE _g.tournament_id = t.id AND _g.is_canceled = FALSE AND _g.result > 0) as players,' .
			' (SELECT count(*) FROM games _g JOIN events _e ON _e.id = _g.event_id WHERE _e.tournament_id = t.id AND _g.is_canceled = FALSE AND _g.result > 0) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$tournaments = array();
		$delim = '';
		$cs_tournaments = '';
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->languages, $tournament->addr_id, $tournament->addr, $tournament->addr_flags, 
				$tournament->players_count, $tournament->games_count, $tournament->rounds_count, $tournament->videos_count) = $row;
			$tournament->series = array();
			$tournaments[] = $tournament;
			$cs_tournaments .= $delim . $tournament->id;
			$delim = ',';
		}
		
		// Get tournaments series
		if ($cs_tournaments != '')
		{
			$query = new DbQuery(
				'SELECT st.tournament_id, st.stars, s.id, s.name, s.flags, l.id, l.name, l.flags' .
				' FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN tournaments t ON t.id = st.tournament_id' .
				' JOIN leagues l ON l.id = s.league_id' .
				' WHERE st.tournament_id IN (' . $cs_tournaments . ') ' . $order_by . ', s.id DESC');
			$current_tournament = 0;
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags) = $row;
				while ($current_tournament < count($tournaments) && $tournaments[$current_tournament]->id != $tournament_id)
				{
					++$current_tournament;
				}
				if ($current_tournament < count($tournaments))
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

		$now = time();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="3" align="center">' . get_label('Tournament') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games') . '</td>';
		echo '<td width="60" align="center">' . get_label('Rounds') . '</td></tr>';
		foreach ($tournaments as $tournament)
		{
			$playing =($now >= $tournament->time && $now < $tournament->time + $tournament->duration);
			if ($playing)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="60" class="dark" align="center" valign="center">';
			$tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
			$tournament_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td><b><a href="tournament_standings.php?bck=1&id=' . $tournament->id . '">' . $tournament->name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</b><br>' . format_date('F d, Y', $tournament->time, $tournament->timezone) . '</a></td>';
			foreach ($tournament->series as $series)
			{
				echo '<td width="50" align="center" valign="center">';
				echo '<a href="series_standings.php?bck=1&id=' . $series->id . '">';
				$series_pic->set($series->id, $series->name, $series->flags)->set($series->league_id, $series->league_name, $series->league_flags);
				$series_pic->show(ICONS_DIR, false, 40);
				echo '</a><br><font style="color:#B8860B; font-size:12px;">' . tournament_stars_str($series->stars) . '</font>';
				echo '</td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td align="center" width="60">';
			if ($tournament->videos_count > 0)
			{
				echo '<a href="tournament_videos.php?id=' . $tournament->id . '&bck=1" title="' . get_label('Videos from [0]', $tournament->name) . '"><img src="images/video.png" width="40" height="40"></a>';
			}
			echo '</td>';
			
			echo '<td align="center"><a href="tournament_standings.php?bck=1&id=' . $tournament->id . '">' . $tournament->players_count . '</a></td>';
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament->id . '">' . $tournament->games_count . '</a></td>';
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament->id . '">' . $tournament->rounds_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterTournaments()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>