<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/forum.php';

define('COLUMN_COUNT', 4);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends ClubPageBase
{
	private function show_events_list($query, $title)
	{
		$event_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $club_id, $club_name, $club_flags, $addr_id, $addr_flags, $addr, $addr_name) = $row;
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
			echo '<a href="event_info.php?bck=1&id=' . $event_id . '" title="' . get_label('View event details.') . '"><b>';
			echo format_date('l, F d, Y, H:i', $event_time, $timezone) . '</b><br>';
			show_event_pic($event_id, $event_flags, $addr_id, $addr_flags, ICONS_DIR);
			echo '</a><br>';
			if ($addr_name == $event_name)
			{
				echo $addr;
			}
			else
			{
				echo $event_name;
			}
			echo '</b></td>';
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
	
	protected function prepare()
	{
		parent::prepare();
		$this->_title = $this->name;
		
		ForumMessage::proceed_send(FORUM_OBJ_NO, 0, $this->id);
	}
	
	protected function rating_row($row, $number)
	{
		list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

		echo '<tr><td width="20" align="center">' . $number . '</td>';
		echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
		show_user_pic($id, $flags, ICONS_DIR, 50, 50);
		echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
		echo '<td width="60" align="center">' . $rating . '</td>';
		echo '</tr>';
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
	
		if ($_profile != NULL)
		{
			$is_manager = $_profile->is_manager($this->id);
		}
		
		list ($games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE club_id = ?', $this->id);
		
		if ($games_count > 0)
		{
			echo '<table width="100%"><tr><td valign="top">';
		}
		
		// your events
		if ($_profile != NULL)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, a.id, a.flags, a.address, a.name FROM event_users u' .
					' JOIN events e ON e.id = u.event_id' .
					' JOIN addresses a ON e.address_id = a.id' .
					' JOIN clubs c ON e.club_id = c.id' .
					' JOIN cities ct ON ct.id = c.city_id' .
					' WHERE u.user_id = ? AND u.coming_odds > 0 AND e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0 AND e.club_id = ?' .
					' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
				$_profile->user_id, $this->id);
			$this->show_events_list($query, get_label('Your events'));
		}
		
		// championships
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, a.id, a.flags, a.address, a.name FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN clubs c ON e.club_id = c.id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_CHAMPIONSHIP) . ') = ' . EVENT_FLAG_CHAMPIONSHIP . ' AND e.club_id = ?',
			$this->id);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$this->show_events_list($query, get_label('Upcoming championships'));
	
		// upcoming
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, a.id, a.flags, a.address, a.name FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN clubs c ON e.club_id = c.id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_CHAMPIONSHIP) . ') = 0 AND e.club_id = ?',
			$this->id);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$this->show_events_list($query, '<a href="club_upcoming.php?bck=1&id=' . $this->id . '">' . get_label('Coming soon') . '</a>');
			
		// adverts
		$query = new DbQuery(
			'SELECT ct.timezone, n.id, n.timestamp, n.message FROM news n' . 
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE n.club_id = ? AND n.expires >= UNIX_TIMESTAMP()' .
				' ORDER BY n.timestamp DESC LIMIT 5',
			$this->id);
		if ($row = $query->next())
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td><a href="club_adverts.php?bck=1&id=' . $this->id . '"><b>' . get_label('Adverts') . '</b></a></td></tr>';
			
			do
			{
				list ($timezone, $id, $timestamp, $message) = $row;
				echo '<tr>';
				echo '<td><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
			} while ($row = $query->next());
			echo '</table></p>';
		}
		
		$params = array( 'id' => $this->id);
		echo '<p>';
		ForumMessage::show_messages($params, FORUM_OBJ_NO, -1, new CCCFilter(NULL, CCCF_CLUB . $this->id));
		echo '</p>';
		ForumMessage::show_send_form($params, get_label('Send a message') . ':');
		
		if ($games_count > 0)
		{
			// ratings
			echo '</td><td width="240" valign="top">';
			
			$query = new DbQuery(
				'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags ' . 
					'FROM users u, club_ratings r WHERE u.id = r.user_id AND r.club_id = ?' .
					' AND r.role = 0 AND type_id = (SELECT id FROM rating_types WHERE def = 1 LIMIT 1) ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT 15',
				$this->id);
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><a href="club_ratings.php?bck=1&id=' . $this->id . '"><b>' . get_label('Ratings') . '</b></a></td></tr>';
			$number = 1;
			while ($row = $query->next())
			{
				$this->rating_row($row, $number++);
			}
			if ($number == 1)
			{
				$query = new DbQuery(
					'SELECT u.id, u.name, r.rating, r.games, r.games_won, u.flags ' . 
						'FROM users u, club_ratings r WHERE u.id = r.user_id AND r.club_id = ?' .
						' AND r.role = 0 AND type_id = 1 ORDER BY r.rating DESC, r.games, r.games_won DESC, r.user_id LIMIT 15',
					$this->id);
				while ($row = $query->next())
				{
					$this->rating_row($row, $number++);
				}
			}
			
			echo '</table>';
			
			echo '</td></tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Club'), PERM_ALL);

?>