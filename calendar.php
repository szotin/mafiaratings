<?php

require_once 'include/general_page_base.php';
require_once 'include/event.php';
require_once 'include/club.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private $week;
	private $city_id;

	protected function prepare()
	{
		parent::prepare();
	
		$this->week = 0;
		if (isset($_REQUEST['week']))
		{
			$this->week = $_REQUEST['week'];
		}
	}
	
	protected function show_body()
	{
		global $_lang, $_profile;
		
		$time = time() + 604800 * $this->week;
		$end_time = $time + 604800;

		$query = new DbQuery('SELECT e.id, e.name, e.start_time, e.duration, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags, ni.name, no.name, i.timezone, a.address, a.map_url');
		if ($_profile != null)
		{
			$query->add(', eu.coming_odds, eu.people_with_me, eu.late FROM events e LEFT OUTER JOIN event_users eu ON eu.event_id = e.id AND eu.user_id = ?', $_profile->user_id);
		}
		else
		{
			$query->add(', NULL, NULL, NULL FROM events e');
		}
		
		$query->add(
			' JOIN clubs c ON e.club_id = c.id' .
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN cities i ON a.city_id = i.id' .
			' JOIN countries o ON i.country_id = o.id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE e.start_time + e.duration >= ? AND e.start_time < ? AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_BEFORE) . ') = 0',
			$time, $end_time);
			
		echo '<p><table class="transp" width="100%">';
		echo '<tr>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('events')));
		echo ' ';
		if ($this->week > 0)
		{
			echo '<input type="submit" name="prev" value="' . get_label('Prev week') . '" class="btn norm" onclick="goTo({week: ' . ($this->week - 1) . '})">';
		}
		echo '<input type="submit" name="next" value="' . get_label('Next week') . '" class="btn norm" onclick="goTo({week: ' . ($this->week + 1) . '})">';
		echo '</td></tr></table></p>';
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND e.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND e.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$query->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND i.country_id = ?', $ccc_id);
			break;
		}
		$query->add(' ORDER BY e.start_time');
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		$date_str = '';
		$have_records = false;
		$day_counter = 0;
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $duration, $flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $city_name, $country_name, $event_timezone, $addr, $addr_url, $come_odds, $bringing, $late) = $row;
			
			$_date_str = format_date($start_time, $event_timezone);
			if ($date_str != $_date_str)
			{
				$date_str = $_date_str;
				if ($day_counter > 0 && $day_counter < COLUMN_COUNT)
				{
					echo '<td colspan="' . (COLUMN_COUNT - $day_counter) . '">&nbsp;</td></tr>';
				}
				if ($have_records)
				{
					echo '</table><br>';
				}
				$day_counter = 0;
				echo '<table class="bordered" width="100%">';
				echo '<tr><th colspan="' . COLUMN_COUNT . '" align="center" class="darkest">' . $date_str . '</th></tr>';
			}
			if ($day_counter == 0)
			{
				echo '<tr>';
			}
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top" class="light">';
			
			echo '<table class="transp" width="100%">';
			if ($_profile != NULL)
			{
				echo '<tr class="dark"><td style="padding:2px;">';
				show_event_buttons($id, $tournament_id, $start_time, $duration, $flags, $club_id, $club_flags, ($come_odds != NULL && $come_odds > 0));
				echo '</td></tr>';	
			}
			
			echo '<tr><td align="center"><a href="event_info.php?bck=1&id=' . $id . '">' . $club_name . '<br>';
			$event_pic->
				set($id, $name, $flags)->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($club_id, $club_name, $club_flags);
			$event_pic->show(ICONS_DIR, false);
			echo '</a><br><b>' . format_date($start_time + $duration, $event_timezone, true) . '</b><br>';
			
//			echo '<a href="event_info.php?attend&bck=1&id=' . $id . '" title="' . get_label('I am coming') . '"><img src="images/accept.png" border="0"></a>&nbsp;';
//			echo '<a href="?decline=' . $id . '" title="' . get_label('I am not coming') . '"><img src="images/delete.png" border="0"></a>';
			
			if ($addr_url != '')
			{
				echo '<a href="' . $addr_url . '" target="blank" title="' . get_label('View address at google maps.') . '">' . $addr . '</a>';
			}
			else
			{
				echo $addr;
			}
			
			if ($come_odds != NULL)
			{
				echo '<br><br><b>' . event_odds_str($come_odds, $bringing, $late) . '</b>';
			}
			echo '</td></tr></table>';
			
			echo '</td>';
			++$day_counter;
			if ($day_counter >= COLUMN_COUNT)
			{
				echo '</tr>';
				$day_counter = 0;
			}
			$have_records = true;
		}
		if ($have_records)
		{
			if ($day_counter > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $day_counter) . '">&nbsp;</td></tr>';
			}
			echo '</table>';
		}
		else 
		{
			$timezone = get_timezone();
			$date = format_date($time, $timezone);
			$end_date = format_date($end_time, $timezone);
		
			echo '<b>' . get_label('There is no games from [0] to [1]', $date, $end_date) . '</b>';
		}
	}
}

$page = new Page();
$page->run(get_label('Calendar'));

?>