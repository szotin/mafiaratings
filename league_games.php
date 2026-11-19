<?php

require_once 'include/league.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/game_rows.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends LeaguePageBase
{
	private $result_filter;
	private $is_admin;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		$this->is_admin = ($_profile != NULL && $_profile->is_admin());
		$this->result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$this->result_filter = (int)$_REQUEST['results'];
		}
		
		$this->flag_filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->flag_filter = (int)$_REQUEST['filter'];
		}
	}

	protected function show_body()
	{
		global $_page, $_profile, $_lang;
		
		
		$condition = new SQL(' WHERE s.league_id = ?', $this->id);
		if ($this->result_filter > 0)
		{
			$condition->add(' AND g.result = ?', $this->result_filter);
		}
		
		if ($this->flag_filter & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($this->flag_filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($this->flag_filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		// The filter by name and the toolbar of games.php (include/game_rows.php): the name, the
		// club/city/country and the result on the first line, the dates and the three-state filters
		// on the second.
		$search = game_search_condition($condition);
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar">' . game_search_field($search) . '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '</div>' . game_result_seg($this->result_filter);
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('rating games'), get_label('canceled games')), $this->flag_filter);
		echo '</div></div>';
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.id = ? OR i.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN series_tournaments st ON st.tournament_id = g.tournament_id JOIN series s ON s.id = st.series_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		// The table of games.php: the game, the result, the club, the tournament, the referee.
		echo '<div class="sheet"><table class="tbl games">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Game') . '</th>';
		echo '<th scope="col">' . get_label('Result') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Tournament') . '</th>';
		echo '<th class="p4" scope="col">' . get_label('Event') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Referee') . '</th>';
		if ($this->is_admin)
		{
			echo '<th scope="col"></th>';
		}
		echo '</tr></thead><tbody>';
		$query = new DbQuery(
			'SELECT g.id, c.id, nc.name, c.flags, e.id, ne.name, e.flags, t.id, nt.name, t.flags, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.flags, a.id, na.name, a.flags, g.table_num, g.game_num FROM games g' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$num = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			$g = new stdClass();
			list ($g->id, $g->club_id, $g->club_name, $g->club_flags, $g->event_id, $g->event_name, $g->event_flags, $g->tournament_id, $g->tournament_name, $g->tournament_flags, $g->timezone, $g->moder_id, $g->moder_name, $g->moder_flags, $g->start, $duration, $g->result, $g->video_id, $g->flags, $address_id, $address_name, $address_flags, $g->table_num, $g->game_num) = $row;
			
			echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
			// The game opens in the context of the page, as it did.
			echo game_row_cell($g, array('time'), '&league_id=' . $this->id);
			echo game_result_cell($g) . game_club_cell($g) . game_tournament_cell($g) . game_event_cell($g, 'p4') . game_referee_cell($g);
			if ($this->is_admin)
			{
				echo game_actions_cell($g, true);
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Games list'));

?>