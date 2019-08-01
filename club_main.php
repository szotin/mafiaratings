<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';

define('COLUMN_COUNT', 5);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('MANAGER_COLUMNS', 5);
define('MANAGER_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);
define('SUBCLUB_COLUMNS', 5);
define('SUBCLUB_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);
define('RATING_POSITIONS', 15);

class Page extends ClubPageBase
{
	private function show_events_list($query, $title)
	{
		$event_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $tour_id, $tour_name, $tour_flags, $addr_id, $addr_flags, $addr, $addr_name) = $row;
			if ($colunm_count == 0)
			{
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . $title . '</b></td></tr>';
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
			$this->event_pic->
				set($event_id, $event_name, $event_flags)->
				set($tour_id, $tour_name, $tour_flags)->
				set($addr_id, $addr, $addr_flags);
			$this->event_pic->show(ICONS_DIR);
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
			return true;
		}
		return false;
	}
	
	private function show_tournaments_list($query, $title)
	{
		$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(ADDRESS_PICTURE));
		$tournament_count = 0;
		$colunm_count = 0;
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $addr_id, $addr_flags, $addr, $addr_name) = $row;
			if ($colunm_count == 0)
			{
				if ($tournament_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . $title . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center">';
			echo '<a href="tournament_info.php?bck=1&id=' . $tournament_id . '" title="' . get_label('View tournament details.') . '"><b>';
			echo format_date('l, F d, Y, H:i', $tournament_time, $timezone) . '</b><br>';
			$tournament_pic->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($addr_id, $addr, $addr_flags);
			$tournament_pic->show(ICONS_DIR);
			echo '</a><br>';
			if ($addr_name == $tournament_name)
			{
				echo $addr;
			}
			else
			{
				echo $tournament_name;
			}
			echo '</b></td>';
			++$colunm_count;
			++$tournament_count;
			if ($colunm_count >= COLUMN_COUNT)
			{
				$colunm_count = 0;
			}
		}
		if ($colunm_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $colunm_count) . '">&nbsp;</td>';
		}
		if ($tournament_count > 0)
		{
			echo '</tr></table>';
			return true;
		}
		return false;
	}
	
	protected function rating_row($row, $number)
	{
		list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

		echo '<tr><td width="20" align="center">' . $number . '</td>';
		echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
		$this->user_pic->set($id, $name, $flags);
		$this->user_pic->show(ICONS_DIR, 50);
		echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
		echo '<td width="60" align="center">' . number_format($rating) . '</td>';
		echo '</tr>';
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
	
		if ($_profile != NULL)
		{
			$is_manager = $_profile->is_club_manager($this->id);
		}
		
		$have_tables = false;
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? GROUP BY result', $this->id);
		while ($row = $query->next())
		{
			switch ($row[0])
			{
				case 0:
					$playing_count = $row[1];
					break;
				case 1:
					$civils_win_count = $row[1];
					break;
				case 2:
					$mafia_win_count = $row[1];
					break;
			}
		}
		$games_count = $civils_win_count + $mafia_win_count + $playing_count;
		
		if ($games_count > 0)
		{
			echo '<table width="100%"><tr><td valign="top">';
		}
		
		// adverts
		$query = new DbQuery(
			'SELECT ct.timezone, n.id, n.timestamp, n.message FROM news n' . 
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE n.club_id = ? AND n.expires >= UNIX_TIMESTAMP() AND n.timestamp <= UNIX_TIMESTAMP()' .
				' ORDER BY n.timestamp DESC LIMIT 5',
			$this->id);
		if ($row = $query->next())
		{
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td><b>' . get_label('Adverts') . '</b></td></tr>';
			
			do
			{
				list ($timezone, $id, $timestamp, $message) = $row;
				echo '<tr>';
				echo '<td><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
			} while ($row = $query->next());
			echo '</table>';
			$have_tables = true;
		}
		
		$had_tables = $have_tables;
		if ($have_tables)
		{
			echo '<p>';
		}
		// your events
		if ($_profile != NULL)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, a.id, a.flags, a.address, a.name FROM event_users u' .
					' JOIN events e ON e.id = u.event_id' .
					' JOIN addresses a ON e.address_id = a.id' .
					' JOIN clubs c ON e.club_id = c.id' .
					' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
					' JOIN cities ct ON ct.id = c.city_id' .
					' WHERE u.user_id = ? AND u.coming_odds > 0 AND e.start_time + e.duration > UNIX_TIMESTAMP() AND e.club_id = ?' .
					' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
				$_profile->user_id, $this->id);
			$have_tables = $this->show_events_list($query, get_label('Your events')) || $have_tables;
		}
		
		// future tournaments
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, c.timezone, a.id, a.flags, a.address, a.name FROM tournaments t' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities c ON c.id = a.city_id' .
				' WHERE t.start_time > UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0 AND t.club_id = ? ORDER BY t.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
			$this->id);
		$have_tables = $this->show_tournaments_list($query, get_label('Upcoming tournaments')) || $have_tables;
	
		// upcoming
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, t.id, t.name, t.flags, a.id, a.flags, a.address, a.name FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' JOIN clubs c ON e.club_id = c.id' .
				' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . (EVENT_FLAG_HIDDEN_BEFORE | EVENT_FLAG_CANCELED) . ') = 0 AND e.club_id = ?',
			$this->id);
		if ($_profile != NULL)
		{
			$query->add(' AND e.id NOT IN (SELECT event_id FROM event_users WHERE user_id = ? AND coming_odds > 0)', $_profile->user_id);
		}
		$query->add(' ORDER BY e.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		$have_tables = $this->show_events_list($query, get_label('Coming soon')) || $have_tables;
		if ($had_tables)
		{
			echo '</p>';
		}
			
		// current tournaments
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, c.timezone, a.id, a.flags, a.address, a.name FROM tournaments t' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities c ON c.id = a.city_id' .
				' WHERE t.start_time + t.duration > UNIX_TIMESTAMP() AND t.start_time <= UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0 AND t.club_id = ? ORDER BY t.start_time LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
			$this->id);
		$have_tables = $this->show_tournaments_list($query, get_label('Current tournaments')) || $have_tables;
	
		// info
		if ($have_tables)
		{
			echo '<p>';
		}
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><b>' . get_label('Information') . '</b></td></tr>';
		echo '<tr><td width="200">'.get_label('City').':</td><td>' . $this->city . ', ' . $this->country	 . '</td></tr>';
		if ($this->url != '')
		{
			echo '<tr><td>'.get_label('Web site').':</td><td><a href="' . $this->url . '" target = "blank">' . $this->url . '</a></td></tr>';
		}
		if ($this->email != '')
		{
			echo '<tr><td>'.get_label('Contact email').':</td><td><a href="mailto:' . $this->email . '">' . $this->email . '</a></td></tr>';
		}
		if ($this->phone != '')
		{
			echo '<tr><td>'.get_label('Contact phone(s)').':</td><td>' . $this->phone . '</td></tr>';
		}
		
		echo '<tr><td>'.get_label('Languages').':</td><td>' . get_langs_str($this->langs, ', ') . '</td></tr>';
		if ($this->price != '')
		{
			echo '<tr><td>'.get_label('Admission rate').':</td><td>' . $this->price . '</td></tr>';
		}
		
		$first_note = true;
		$query = new DbQuery('SELECT id, name, value FROM club_info WHERE club_id = ? ORDER BY pos', $this->id);
		while ($row = $query->next())
		{
			list($note_id, $note_name, $note_value) = $row;
			$note_name = htmlspecialchars($note_name);
			echo '<tr><td valign="top">';
			if ($is_manager)
			{
				echo '<table class="transp" width="100%"><tr><td class="dark">';
				echo '<button class="icon" onclick="mr.editNote(' . $note_id . ')" title="' . get_label('Edit note [0]', $note_name) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.deleteNote(' . $note_id . ', \'' . get_label('Are you sure you want to delete the note?') . '\')" title="' . get_label('Delete note [0]', $note_name) . '"><img src="images/delete.png" border="0"></button>';
				if (!$first_note)
				{
					echo '<button class="icon" onclick="mr.upNote(' . $note_id . ')" title="' . get_label('Move note [0] up', $note_name) . '"><img src="images/up.png" border="0"></button>';
				}
				$first_note = false;
				echo '</td></tr><tr><td>' . $note_name . ':</td></tr></table>';
			}
			else
			{
				echo $note_name . ':';
			}
			echo '</td><td>' . prepare_message($note_value) . '</td></tr>';
		}
		if ($is_manager)
		{
			echo '<tr><td valign="top">';
			echo '<table class="transp" width="100%"><tr><td class="dark">';
			echo '<button class="icon" onclick="mr.createNote(' . $this->id . ')" title="' . get_label('Create [0]', get_label('note')) . '"><img src="images/create.png" border="0"></button>';
			echo '</td></tr></table></td><td>&nbsp;</td></tr>';
		}
		echo '</table>';
		if ($have_tables)
		{
			echo '</p>';
		}
		$have_tables = true;
		
		// stats
		if ($games_count > 0)
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="2"><b>' . get_label('Stats') . '</b></td></tr>';
			echo '<tr><td width="200">'.get_label('Games played').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
			if ($civils_win_count + $mafia_win_count > 0)
			{
				echo '<tr><td>'.get_label('Mafia victories').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
				echo '<tr><td>'.get_label('Town victories').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($playing_count > 0)
			{
				echo '<tr><td>'.get_label('Still playing').'</td><td>' . $playing_count . '</td></tr>';
			}
			
			if ($civils_win_count + $mafia_win_count > 0)
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.club_id = ?', $this->id);
				echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
				
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ?', $this->id);
				echo '<tr><td>'.get_label('People moderated').':</td><td>' . $counter . '</td></tr>';
				
				list ($a_game, $s_game, $l_game) = Db::record(
					get_label('game'),
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE result > 0 AND result < 3 AND club_id = ?', 
					$this->id);
				echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
			}
			echo '</table></p>';
		}
		
		// managers
		$query = new DbQuery('SELECT u.id, u.name, u.flags FROM user_clubs c JOIN users u ON u.id = c.user_id WHERE c.club_id = ? AND (c.flags & ' . USER_CLUB_PERM_MANAGER . ') <> 0', $this->id);
		if ($row = $query->next())
		{
			$managers_count = 0;
			$columns_count = 0;
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="' . MANAGER_COLUMNS . '"><b>' . get_label('Managers') . '</b></td></tr>';
			do
			{
				list($manager_id, $manager_name, $manager_flags) = $row;
				if ($columns_count == 0)
				{
					if ($managers_count > 0)
					{
						echo '</tr>';
					}
					echo '<tr>';
				}
				echo '<td width="' . MANAGER_COLUMN_WIDTH . '%" align="center">';
				echo '<a href="user_info.php?bck=1&id=' . $manager_id . '">' . $manager_name . '<br>';
				$this->user_pic->set($manager_id, $manager_name, $manager_flags);
				$this->user_pic->show(ICONS_DIR);
				echo '</a></td>';
				
				++$columns_count;
				++$managers_count;
				if ($columns_count >= MANAGER_COLUMNS)
				{
					$columns_count = 0;
				}
				
			} while ($row = $query->next());
			
			if ($columns_count > 0)
			{
				echo '<td colspan="' . (MANAGER_COLUMNS - $columns_count) . '">&nbsp;</td>';
			}
			echo '</tr></table></p>';
		}
		
		// sub clubs
		$query = new DbQuery('SELECT id, name, flags FROM clubs WHERE parent_id = ?', $this->id);
		if ($row = $query->next())
		{
			$subclubs_count = 0;
			$columns_count = 0;
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="' . SUBCLUB_COLUMNS . '"><b>' . get_label('Descendant clubs') . '</b></td></tr>';
			do
			{
				list($subclub_id, $subclub_name, $subclub_flags) = $row;
				if ($columns_count == 0)
				{
					if ($subclubs_count > 0)
					{
						echo '</tr>';
					}
					echo '<tr>';
				}
				echo '<td width="' . SUBCLUB_COLUMN_WIDTH . '%" align="center">';
				echo '<a href="club_main.php?bck=1&id=' . $subclub_id . '">' . $subclub_name . '<br>';
				$this->club_pic->set($subclub_id, $subclub_name, $subclub_flags);
				$this->club_pic->show(ICONS_DIR);
				echo '</a></td>';
				
				++$columns_count;
				++$subclubs_count;
				if ($columns_count >= SUBCLUB_COLUMNS)
				{
					$columns_count = 0;
				}
				
			} while ($row = $query->next());
			
			if ($columns_count > 0)
			{
				echo '<td colspan="' . (SUBCLUB_COLUMNS - $columns_count) . '">&nbsp;</td>';
			}
			echo '</tr></table></p>';
		}
		
		// ratings
		if ($games_count > 0)
		{
			echo '</td><td width="280" valign="top">';
			$query = new DbQuery('SELECT id, name, rating, games, games_won, flags FROM users WHERE club_id = ? ORDER BY rating DESC, games, games_won DESC, id LIMIT ' . RATING_POSITIONS, $this->id);
					
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><b>' . get_label('Best players') . '</b></td></tr>';
			$number = 1;
			while ($row = $query->next())
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags) = $row;

				echo '<td width="20" align="center">' . $number . '</td>';
				echo '<td width="50"><a href="user_info.php?id=' . $id . '&bck=1">';
				$this->user_pic->set($id, $name, $flags);
				$this->user_pic->show(ICONS_DIR, 50);
				echo '</a></td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . number_format($rating) . '</td>';
				echo '</tr>';
				
				++$number;
			}
			echo '</table>';
			echo '</td></tr></table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>