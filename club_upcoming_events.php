<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/event.php';
require_once 'include/address.php';
require_once 'include/cards.php';
require_once 'include/event_cards.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);

class Page extends ClubPageBase
{
	// Making one of the club, beside its menu "≡". See PageBase::create_button().
	protected function create_action()
	{
		return PageBase::create_button('event', 'c' . $this->id);
	}

	protected function show_body()
	{
		global $_page, $_lang, $_profile;
		
		$can_create = $this->is_manager || $this->is_referee;
		
		$condition = new SQL(' WHERE e.club_id = ? AND UNIX_TIMESTAMP() <= e.start_time + e.duration', $this->id);
		if ($can_create)
		{
			$condition->add(' + ' . EVENT_ALIVE_TIME); // managers should see the event for some time after the end to be able to extend it
		}
		$search = event_search_condition($condition);
		
		// The toolbar of a list (see new_look.css): the name, and the button that creates one.
		echo '<div class="toolbar">' . card_search_field($search, get_label('Game day, tournament or club')) . '</div>';
		
		$from = 
			' FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON e.address_id = a.id' .
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0';
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)' . $from, $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT e.id, ne.name, e.start_time, e.duration, e.flags, nct.name, ct.timezone, t.id, nt.name, t.flags,' .
			' (SELECT count(*) FROM games WHERE event_id = e.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
			' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos');
		if ($_profile != null)
		{
			$query->add(', eu.coming_odds, eu.people_with_me, eu.late' . $from . ' LEFT OUTER JOIN event_regs eu ON eu.event_id = e.id AND eu.user_id = ?', $_profile->user_id);
		}
		else
		{
			$query->add(', NULL, NULL, NULL' . $from);
		}
		$query->add($condition);
		$query->add(' ORDER BY e.start_time, e.id LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);

		$events = array();
		while ($row = $query->next())
		{
			$event = new stdClass();
			list ($event->id, $event->name, $event->time, $event->duration, $event->flags, $event->city, $event->timezone, $event->tournament_id, $event->tournament_name, $event->tournament_flags, $event->games_count, $event->users_count, $event->videos_count, $come_odds, $bringing, $late) = $row;
			$event->club_id = $this->id;
			$event->club_name = $this->name;
			$event->club_flags = $this->flags;
			// Whether the viewer is coming.
			if ($come_odds != NULL)
			{
				$event->extra = '<div class="pc-meta pc-res"><span>' . event_odds_str($come_odds, $bringing, $late) . '</span></div>';
			}
			$events[] = $event;
		}
		// The cards of events.php: see include/event_cards.php.
		event_cards_show($events, true, get_label('No events found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Upcoming Events'));

?>
