<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';
require_once 'include/event_cards.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);
define('ETYPE_ALL', 0);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NOT_TOURNAMENT', 0x0008);

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
		
		$condition = new SQL(
			' FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN games g ON g.event_id = e.id' .
			' JOIN players p ON p.game_id = g.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN event_places ep ON ep.event_id = e.id AND ep.user_id = p.user_id' . 
			' JOIN cities ct ON ct.id = a.city_id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' WHERE p.user_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0', $this->id);
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND e.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND e.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
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
			$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_NO_VIDEOS)
		{
			$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND e.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NOT_TOURNAMENT)
		{
			$condition->add(' AND e.tournament_id IS NULL');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND e.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND e.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$search = event_search_condition($condition);
		
		// The toolbar of a list (see new_look.css): the name and the place; then the dates and the
		// three-state filters.
		echo '<div class="toolbar">' . card_search_field($search, get_label('Game day, tournament or club'));
		echo '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('events')));
		echo '</div><div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament events')), $filter);
		echo '</div></div>';
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(DISTINCT e.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT e.id, ne.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, nt.name, t.flags, c.id, nc.name, c.flags, nct.name, ep.place, SUM(p.rating_earned), COUNT(g.id), SUM(p.won),' .
			' (SELECT count(*) FROM games WHERE event_id = e.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(distinct _p.user_id) FROM players _p JOIN games _g ON _g.id = _p.game_id WHERE _g.event_id = e.id) as users,' .
			' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		$query->add(' GROUP BY e.id ORDER BY e.start_time DESC, e.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$events = array();
		while ($row = $query->next())
		{
			$event = new stdClass();
			list ($event->id, $event->name, $event->flags, $event->time, $event->duration, $event->timezone, $event->tournament_id, $event->tournament_name, $event->tournament_flags, $event->club_id, $event->club_name, $event->club_flags, $event->city, $event->place, $event->rating, $event->games_played, $event->games_won, $event->games_count, $event->users_count, $event->videos_count) = $row;
			$event->extra = $this->result_html($event);
			$events[] = $event;
		}
		// The cards of events.php, with the result of the player: see include/event_cards.php.
		event_cards_show($events, false, get_label('No events found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	// The result of the player on the card, as user_tournaments.php shows it.
	private function result_html($event)
	{
		$html = '<div class="pc-meta pc-res">';
		if (!is_null($event->place) && $event->place > 0)
		{
			$html .= '<span class="medal' . ($event->place <= 3 ? ' m' . $event->place : '') . '" title="' . get_label('Place') . '">' . get_label('[0] place', $event->place) . '</span>';
		}
		if ($event->games_played != 0)
		{
			$html .= '<span title="' . get_label('Rating earned') . '">' . PageBase::icon('trophy') . number_format($event->rating, 2) . '</span>';
			$html .= '<a href="event_player.php?bck=1&user_id=' . $this->id . '&id=' . $event->id . '" title="' . get_label('Games played') . '">' . PageBase::icon('play') . $event->games_played . '</a>';
			$html .= '<span title="' . get_label('Winning %') . '">' . number_format(($event->games_won * 100.0) / $event->games_played, 1) . '%</span>';
			$html .= '<span title="' . get_label('Rating per game') . '">' . number_format($event->rating / $event->games_played, 2) . ' ' . get_label('per game') . '</span>';
		}
		return $html . '</div>';
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>