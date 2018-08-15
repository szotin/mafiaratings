<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';

define("CUT_NAME",45);
define("PAGE_SIZE",15);

define('ETYPE_TOURNAMENT', 0);
define('ETYPE_WITH_GAMES', 1);
define('ETYPE_NOT_CANCELED', 2);
define('ETYPE_ALL', 3);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		$season = 0;
		if (isset($_REQUEST['season']))
		{
			$season = (int)$_REQUEST['season'];
		}
		
		$events_type = ETYPE_WITH_GAMES;
		if (isset($_REQUEST['etype']))
		{
			$events_type = (int)$_REQUEST['etype'];
		}
		
		echo '<form method="get" name="clubForm">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		$season = show_seasons_select($this->id, $season, 'document.clubForm.submit()', get_label('Show events of a specific season.'));
		echo ' <select name="etype" onchange="document.clubForm.submit()">';
		show_option(ETYPE_TOURNAMENT, $events_type, get_label('Tournaments'));
		show_option(ETYPE_WITH_GAMES, $events_type, get_label('Events'));
		show_option(ETYPE_NOT_CANCELED, $events_type, get_label('Events including empty'));
		show_option(ETYPE_ALL, $events_type, get_label('Events including canceled'));
		echo '</select>';
		echo '</td></tr></table></form>';
		
		$condition = new SQL(
			' FROM events e ' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE e.start_time < UNIX_TIMESTAMP() AND e.club_id = ?',
			$this->id);
		$condition->add(get_season_condition($season, 'e.start_time', '(e.start_time + e.duration)'));
		switch ($events_type)
		{
			case ETYPE_TOURNAMENT:
				$condition->add(' AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_TOURNAMENT) . ') = ' . EVENT_FLAG_TOURNAMENT);
				break;
			case ETYPE_NOT_CANCELED:
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0');
				break;
			case ETYPE_ALL:
				break;
			default:
				$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
				break;
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, a.id, a.address, a.flags,' .
				' (SELECT count(*) FROM games WHERE event_id = e.id AND result IN (1, 2)) as games,' .
				' (SELECT count(*) FROM registrations WHERE event_id = e.id) as users',
			$condition);
		$query->add(' ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">' . get_label('Games played') . '</td>';
		echo '<td width="60" align="center">' . get_label('Players attended') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $address_id, $address, $address_flags, $games_count, $users_count) = $row;

			if ($event_flags & EVENT_FLAG_CANCELED)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}
			
			echo '<td width="50"><a href="event_standings.php?bck=1&id=' . $event_id . '">';
			show_event_pic($event_id, $event_name, $event_flags, $address_id, $address, $address_flags, ICONS_DIR, 50);
			echo '</a></td>';
			echo '<td width="180">' . $event_name . '<br><b>' . format_date('l, F d, Y', $event_time, $timezone) . '</b></td>';
			
			echo '<td>' . $address . '</td>';
			
			echo '<td align="center"><a href="event_games.php?bck=1&id=' . $event_id . '">' . $games_count . '</a></td>';
			echo '<td align="center"><a href="event_standings.php?bck=1&id=' . $event_id . '">' . $users_count . '</a></td>';
			
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>