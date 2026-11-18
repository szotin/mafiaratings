<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';
require_once 'include/tournament.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/tournament_cards.php';

define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		// The toolbar of tournaments.php: the filters of the page in a line.
		echo '<div class="toolbar"><div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('tournaments')));
		echo '</div><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video')), $filter, 'filterTournaments');
		echo '</div>';
				$condition = new SQL(
			' FROM tournament_places tp' .
			' JOIN tournaments t ON tp.tournament_id = t.id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN games g ON g.tournament_id = t.id AND (g.flags & '.GAME_FLAG_CANCELED.') = 0' .
			' LEFT OUTER JOIN players p ON p.game_id = g.id AND p.user_id = tp.user_id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' . 
			' WHERE tp.user_id = ? AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0', $this->id);
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
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
		if ($filter & FLAG_FILTER_VIDEOS)
		{
			$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
		}
		if ($filter & FLAG_FILTER_NO_VIDEOS)
		{
			$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND t.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND t.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(DISTINCT t.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$order_by = ' ORDER BY t.start_time DESC, t.id DESC';
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, c.flags, t.langs, a.id, a.address, a.flags, tp.place, SUM(p.rating_earned), COUNT(DISTINCT p.game_id), SUM(p.won), nct.name, t.num_players, ' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add(' GROUP BY t.id ' . $order_by . ' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$tournaments = array();
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, $tournament->languages, 
				$tournament->addr_id, $tournament->addr, $tournament->addr_flags, $tournament->place,
				$tournament->rating, $tournament->games_played, $tournament->games_won, $tournament->city, $tournament->num_players, $tournament->videos_count) = $row;
			// The games on the card are this player's, not all the games of the tournament.
			$tournament->games_count = 0;
			$tournament->extra = $this->result_html($tournament);
			$tournaments[] = $tournament;
		}
		
		// The cards of tournaments.php (include/tournament_cards.php), each with the result of this
		// player in it.
		tournament_cards_load($tournaments, false);
		tournament_cards_show($tournaments, false, get_label('No tournaments found.'));
				show_pages_navigation(PAGE_SIZE, $count);
	}
	
	// The result of the player in a tournament, a line of the card under its place and club: the
	// place, the rating earned, the games (to the player's page of the tournament), the wins and the
	// rating per game.
	private function result_html($tournament)
	{
		$html = '<div class="pc-meta pc-res">';
		if (!is_null($tournament->place) && $tournament->place > 0)
		{
			$html .= '<span class="medal' . ($tournament->place <= 3 ? ' m' . $tournament->place : '') . '" title="' . get_label('Place') . '">' . get_label('[0] place', $tournament->place) . '</span>';
		}
		if ($tournament->games_played != 0)
		{
			$html .= '<span title="' . get_label('Rating earned') . '">' . PageBase::icon('trophy') . number_format($tournament->rating, 2) . '</span>';
			$html .= '<a href="tournament_player.php?bck=1&user_id=' . $this->id . '&id=' . $tournament->id . '" title="' . get_label('Games played') . '">' . PageBase::icon('play') . $tournament->games_played . '</a>';
			$html .= '<span title="' . get_label('Winning %') . '">' . number_format(($tournament->games_won * 100.0) / $tournament->games_played, 1) . '%</span>';
			$html .= '<span title="' . get_label('Rating per game') . '">' . number_format($tournament->rating / $tournament->games_played, 2) . ' ' . get_label('per game') . '</span>';
		}
		return $html . '</div>';
	}
	
	function js()
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