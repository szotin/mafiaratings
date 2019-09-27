<?php

require_once 'include/general_page_base.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';

define("PAGE_SIZE",15);

define('ETYPE_TOURNAMENT', 0);
define('ETYPE_WITH_GAMES', 1);
define('ETYPE_NOT_CANCELED', 2);
define('ETYPE_ALL', 3);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->ccc_title = get_label('Filter events by club, city, or country.');
	}

	protected function show_body()
	{
		global $_profile, $_page;
		
		$condition = new SQL(' FROM events e JOIN addresses a ON e.address_id = a.id JOIN clubs c ON e.club_id = c.id LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id JOIN cities ct ON ct.id = a.city_id WHERE e.start_time < UNIX_TIMESTAMP()');
		
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
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
		
		$condition->add(' AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_TOURNAMENT | EVENT_FLAG_HIDDEN_AFTER) . ') = ' . EVENT_FLAG_TOURNAMENT);
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);


		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.address, a.flags,' .
			' (SELECT count(*) FROM games WHERE event_id = e.id AND canceled = FALSE AND result > 0) as games,' .
			' (SELECT count(*) FROM registrations WHERE event_id = e.id) as users',
			$condition);
		$query->add(' ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players attended') . '</td></tr>';
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $tour_id, $tour_name, $tour_flags, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr, $addr_flags, $games_count, $users_count) = $row;
			
			echo '<tr>';
			
			echo '<td width="50" class="dark"><a href="event_standings.php?bck=1&id=' . $event_id . '">';
			$event_pic->
				set($event_id, $event_name, $event_flags)->
				set($tour_id, $tour_name, $tour_flags)->
				set($club_id, $club_name, $club_flags);
			$event_pic->show(ICONS_DIR, 50);
			echo '</a></td>';
			echo '<td width="180">' . $event_name . '<br><b>' . format_date('l, F d, Y', $event_time, $timezone) . '</b></td>';
			
			echo '<td>' . $addr . '</td>';
			
			echo '<td align="center"><a href="event_games.php?bck=1&id=' . $event_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $users_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Tournaments history'));

?>