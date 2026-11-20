<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';
require_once 'include/event_cards.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NOT_TOURNAMENT', 0x0008);
define('FLAG_FILTER_EMPTY', 0x0010);
define('FLAG_FILTER_NOT_EMPTY', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NOT_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends AddressPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$condition = new SQL(
			' FROM events e' . 
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON c.id = e.club_id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON a.id = e.address_id' . 
			' JOIN cities ct ON ct.id = a.city_id' . 
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' . 
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' WHERE e.address_id = ? AND e.start_time < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0', $this->id);
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
		if ($filter & FLAG_FILTER_EMPTY)
		{
			$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_NOT_EMPTY)
		{
			$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
		}
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') <> 0');
		}
		if ($filter & FLAG_FILTER_NOT_CANCELED)
		{
			$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
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
		
		// The toolbar of a list (see new_look.css): the name and - there is no place to filter by -
		// the dates; then the three-state filters.
		echo '<div class="toolbar">' . card_search_field($search, get_label('Game day, tournament or club'));
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div><div class="tb-line">';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament events'), get_label('unplayed'), get_label('canceled')), $filter);
		echo '</div></div>';
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, e.id, ne.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, nt.name, t.flags, ' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
				' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
				' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		$query->add(' ORDER BY e.start_time DESC, e.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$events = array();
		while ($row = $query->next())
		{
			$event = new stdClass();
			list ($event->club_id, $event->club_name, $event->club_flags, $event->id, $event->name, $event->flags, $event->time, $event->duration, $event->timezone, $event->tournament_id, $event->tournament_name, $event->tournament_flags, $event->games_count, $event->users_count, $event->videos_count) = $row;
			$event->city = $this->city_name;
			$events[] = $event;
		}
		// The cards of events.php: see include/event_cards.php.
		event_cards_show($events, false, get_label('No events found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Events history'));

?>