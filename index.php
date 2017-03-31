<?php

require_once 'include/general_page_base.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/forum.php';
require_once 'include/user_location.php';
require_once 'include/club.php';

define('COLUMN_COUNT', 4);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private function show_events_list($query, $title)
	{
		$event_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $club_id, $club_name, $club_flags, $languages, $addr_id, $addr_flags, $addr, $addr_name) = $row;
			if ($event_name == $addr_name)
			{
				$event_name = $addr;
			}
			if ($colunm_count == 0)
			{
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . $title . ':</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center">';
			echo $club_name . '<br>';
			echo '<a href="event_info.php?bck=1&id=' . $event_id . '" title="' . get_label('View event details.') . '"><b>';
			echo format_date('l, F d, Y, H:i', $event_time, $timezone) . '</b><br>';
			show_event_pic($event_id, $event_flags, $club_id, $club_flags, ICONS_DIR, 0, 0, false);
			echo '</a><br>' . $event_name . '</b></td>';
			++$colunm_count;
			++$event_count;
			if ($colunm_count >= COLUMN_COUNT)
			{
				$colunm_count = 0;
			}
		}
		if ($colunm_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $colunm_count) . '">&nbsp;</td>';
		}
		if ($event_count > 0)
		{
			echo '</tr></table>';
		}
	}

	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$condition = new SQL();
		$ccc_id = $this->ccc_filter->get_id();
		$ccc_code = $this->ccc_filter->get_code();
		$ccc_type = $this->ccc_filter->get_type();
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND c.id IN (SELECT club_id FROM user_clubs WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND c.id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR near_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND c.id IN (SELECT l.id FROM clubs l JOIN cities i ON i.id = l.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		echo '<table width="100%"><tr><td valign="top">';
	
		// my events
		if ($_profile != NULL)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM event_users u' .
					' JOIN events e ON e.id = u.event_id' .
					' JOIN addresses a ON e.address_id = a.id' .
					' JOIN clubs c ON e.club_id = c.id' .
					' JOIN cities ct ON ct.id = c.city_id' .
					' WHERE u.user_id = ? AND u.coming_odds > 0 AND e.start_time + e.duration > UNIX_TIMESTAMP()' .
					' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
				$_profile->user_id);
			$this->show_events_list($query, get_label('Your events'));
		}
		
		// championships
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_CHAMPIONSHIP . ') = ' . EVENT_FLAG_CHAMPIONSHIP);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$this->show_events_list($query, get_label('Upcoming championships'));
		
		// upcoming
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM events e' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_CHAMPIONSHIP . ') = 0', $condition);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$this->show_events_list($query, '<a href="calendar.php?bck=1">' . get_label('Coming soon') . '</a>');

		// adverts
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, ct.timezone, n.id, n.timestamp, n.message FROM news n' . 
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id WHERE n.expires >= UNIX_TIMESTAMP()', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT 3');
		
		if ($row = $query->next())
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="2"><a href="adverts.php?bck=1&ccc=' . $ccc_code . '"><b>' . get_label('More adverts...') . '</b></a></td></tr>';
			
			do
			{
				list ($club_id, $club_name, $club_flags, $timezone, $id, $timestamp, $message) = $row;
				echo '<tr>';
				echo '<td width="100" class="dark" align="center" valign="top"><a href="club_main.php?id=' . $club_id . '&bck=1">' . $club_name . '<br>';
				show_club_pic($club_id, $club_flags, ICONS_DIR);
				echo '</a></td><td valign="top"><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
				
				
			} while ($row = $query->next());
			echo '</table></p>';
		}
		
		echo '<p>';
		ForumMessage::show_messages(array('ccc' => $this->ccc_filter->get_code()), FORUM_OBJ_NO, -1, $this->ccc_filter);
		echo '</p>';
		
		// ratings
		$query = new DbQuery(
			'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags FROM ratings r' .
			' JOIN users u ON u.id = r.user_id' .
			' WHERE r.role = 0 AND type_id = (SELECT id FROM rating_types WHERE def = 1 LIMIT 1)');
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM user_clubs WHERE club_id IN (' . $_profile->get_comma_sep_clubs() . '))');
			}
			break;
		case CCCF_CITY:
			$query->add(' AND u.city_id = ?', $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		$query->add(' ORDER BY r.rating DESC, r.games, r.games_won, r.user_id DESC LIMIT 15');
		
		$number = 1;
		if ($row = $query->next())
		{
			echo '</td><td width="240" valign="top">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><a href="ratings.php?bck=1&ccc=' . $ccc_code . '"><b>' . get_label('More ratings...') . '</b></a></td></tr>';
			
			do
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

				echo '<td width="20" class="dark" align="center">' . $number . '</td>';
				echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
				show_user_pic($id, $flags, ICONS_DIR, 50, 50);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . $rating . '</td>';
				echo '</tr>';
				
				++$number;
			} while ($row = $query->next());
			
			echo '</table>';
		}
		
		echo '</td></tr></table>';
	}
}

$page = new Page();
$page->run(get_label('Home'), PERM_ALL);

?>