<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';
require_once 'include/ccc_filter.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->ccc_title = get_label('Filter tournaments by club, city, or country.');
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL(
			' FROM tournaments t' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' LEFT OUTER JOIN leagues l ON l.id = t.league_id' .
			' WHERE t.start_time < UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
		
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);


		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.stars, t.start_time, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.address, a.flags, l.id, l.name, l.flags,' .
			' (SELECT count(DISTINCT _p.user_id) FROM players _p JOIN games _g ON _g.id = _p.game_id WHERE _g.tournament_id = t.id AND _g.canceled = FALSE AND _g.result > 0) as players,' .
			' (SELECT count(*) FROM games WHERE tournament_id = t.id AND canceled = FALSE AND result > 0) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add(' ORDER BY t.start_time DESC, t.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="4" align="center">' . get_label('Tournament') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games') . '</td>';
		echo '<td width="60" align="center">' . get_label('Rounds') . '</td></tr>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		$league_pic = new Picture(LEAGUE_PICTURE);
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_stars, $tournament_time, $timezone, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr, $addr_flags, $league_id, $league_name, $league_flags, $players_count, $games_count, $rounds_count, $videos_count) = $row;
			
			echo '<tr>';
			
			echo '<td width="60" class="dark" align="center" valign="center">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 60);
			echo '</td>';
			
			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false, 40);
			echo '</td><td>';
			echo '<b><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">' . $tournament_name . '</b>';
			echo '<br>' . format_date('F d, Y', $tournament_time, $timezone) . '</a></td>';
			if ($videos_count > 0)
			{
				echo '<td align="right"><a href="tournament_videos.php?id=' . $tournament_id . '&bck=1" title="' . get_label('Videos from [0]', $tournament_name) . '"><img src="images/video.png"></a></td>';
			}
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td width="64" align="center" valign="center">';
			echo '<font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($tournament_stars) . '</font>';
			if ($league_id != NULL)
			{
				echo '<br>';
				$league_pic->set($league_id, $league_name, $league_flags);
				$league_pic->show(ICONS_DIR, false, 32);
			}
			echo '</td>';
			
			echo '<td align="center"><a href="tournament_standings.php?bck=1&id=' . $tournament_id . '">' . $players_count . '</a></td>';
			echo '<td align="center"><a href="tournament_games.php?bck=1&id=' . $tournament_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="tournament_rounds.php?bck=1&id=' . $tournament_id . '">' . $rounds_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Tournaments history'));

?>