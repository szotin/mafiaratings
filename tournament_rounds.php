<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/event.php';
require_once 'include/address.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_page, $_lang_code, $_profile;
		
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
		$page_size = ROW_COUNT * COLUMN_COUNT;
		$event_count = 0;
		$column_count = 0;
		
		if ($is_manager)
		{
			--$page_size;
			++$event_count;
			++$column_count;
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*) FROM events WHERE tournament_id = ?', $this->id);
		show_pages_navigation($page_size, $count);
		
		if ($is_manager)
		{
			echo '<table class="bordered light" width="100%"><tr>';
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';	
			echo '<table class="transp" width="100%">';
			echo '<tr class="light"><td align="left" style="padding:2px;>';
			show_club_buttons(-1, '', 0, 0);
			echo '</td></tr><tr><td align="center"><a href="#" onclick="mr.createRound(' . $this->id . ')">' . get_label('Create [0]', get_label('round'));
			echo '<br><img src="images/create_big.png" border="0" width="' . ICON_WIDTH . '">';
			echo '</td></tr></table>';
			echo '</td>';
		}
		
		$query = new DbQuery('SELECT e.id, e.name, e.start_time, e.duration, e.flags, ct.name_' . $_lang_code . ', cr.name_' . $_lang_code . ', ct.timezone, a.id, a.flags, a.address, a.map_url, a.name');
		if ($_profile != null)
		{
			$query->add(', eu.coming_odds, eu.people_with_me, eu.late FROM events e LEFT OUTER JOIN event_users eu ON eu.event_id = e.id AND eu.user_id = ?', $_profile->user_id);
		}
		else
		{
			$query->add(', NULL, NULL, NULL FROM events e');
		}
		$query->add(
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id WHERE e.tournament_id = ?', 
			$this->id);
		$query->add(' ORDER BY e.start_time DESC, e.id DESC LIMIT ' . ($_page * $page_size) . ',' . $page_size);

		$event_pic = new Picture(EVENT_PICTURE, new Picture(ADDRESS_PICTURE));
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $duration, $flags, $city_name, $country_name, $event_timezone, $addr_id, $addr_flags, $addr, $addr_url, $addr_name, $come_odds, $bringing, $late) = $row;
			if ($name == $addr_name)
			{
				$name = $addr;
			}
			if ($column_count == 0)
			{
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			if ($_profile != NULL)
			{
				echo '<tr><td class="dark" style="padding:2px;">';
				Event::show_buttons($id, $this->id, $start_time, $duration, $flags, $this->club_id, $this->club_flags, ($come_odds != NULL && $come_odds > 0));
				echo '</td></tr>';	
			}
			
			echo '<tr><td align="center"><a href="event_info.php?bck=1&id=' . $id . '"><b>' . $name . '</b><br>';
			$event_pic->
				set($id, $name, $flags)->
				set($addr_id, $addr, $addr_flags);
			$event_pic->show(ICONS_DIR, false);
			echo '</a><br>' . format_date('l, F d, Y', $start_time, $event_timezone);
			
			if ($come_odds != NULL)
			{
				echo '<br><br><b>' . Event::odds_str($come_odds, $bringing, $late) . '</b>';
			}
			echo '</td></tr></table>';
			
			echo '</td>';
			
			++$event_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($event_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Rounds'));

?>