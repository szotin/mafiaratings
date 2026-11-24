<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
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

class Page extends GeneralPageBase
{
	// The button that makes a game day, beside the menu "≡" of the band. See PageBase::create_button().
	protected function title_actions()
	{
		return PageBase::create_button('event');
	}

	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$condition = new SQL(' FROM events e JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0 JOIN addresses a ON e.address_id = a.id JOIN clubs c ON e.club_id = c.id JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0 LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0 JOIN cities ct ON ct.id = a.city_id JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0');
		if ($this->future)
		{
			$condition->add(' WHERE e.start_time + e.duration >= UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_BEFORE . ') = 0');
		}
		else
		{
			$condition->add(' WHERE e.start_time < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_AFTER . ') = 0');
			
			if ($this->filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_TOURNAMENT)
			{
				$condition->add(' AND e.tournament_id IS NOT NULL');
			}
			if ($this->filter & FLAG_FILTER_NOT_TOURNAMENT)
			{
				$condition->add(' AND e.tournament_id IS NULL');
			}
			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		// The filter by name: of the event, or of the tournament it is a round of.
		$search = event_search_condition($condition);
		
		// The toolbar of a list (see new_look.css): the name, the place and the switch; then the dates
		// and the three-state filters.
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar">' . card_search_field($search, get_label('Game day, tournament or club'));
		echo '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('events')));
		echo '</div>';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$this->future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $this->future)), get_label('Events'));
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('with video'), get_label('tournament events'), get_label('unplayed'), get_label('canceled')), $this->filter);
		}
		echo '</div></div>';
		
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
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND e.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND e.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT e.id, ne.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, nt.name, t.flags, c.id, nc.name, c.flags, nct.name,' .
			' (SELECT count(*) FROM games WHERE event_id = e.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(distinct p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = e.id) as users,' .
			' (SELECT count(*) FROM videos WHERE event_id = e.id) as videos',
			$condition);
		if ($this->future)
		{
			$query->add(' ORDER BY e.start_time, e.id');
		}
		else
		{
			$query->add(' ORDER BY e.start_time DESC, e.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		$events = array();
		while ($row = $query->next())
		{
			$event = new stdClass();
			list ($event->id, $event->name, $event->flags, $event->time, $event->duration, $event->timezone, $event->tournament_id, $event->tournament_name, $event->tournament_flags, $event->club_id, $event->club_name, $event->club_flags, $event->city, $event->games_count, $event->users_count, $event->videos_count) = $row;
			$events[] = $event;
		}
		// The cards: see include/event_cards.php.
		event_cards_show($events, $this->future, get_label('No events found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>