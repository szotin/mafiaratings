<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('MANAGER_COLUMNS', 5);
define('MANAGER_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);
define('SUBCLUB_COLUMNS', 5);
define('SUBCLUB_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);
define('RATING_POSITIONS', 15);

class Page extends ClubPageBase
{
	private $tournament_pic;
	private $club_user_pic;
	
	private function show_tournament($tournament)
	{
		$future = ($tournament->start_time > time());
		if ($future)
		{
			$dark_class = ' class = "darker"';
			$light_class = ' class = "dark"';
			$url = 'tournament_info.php';
		}
		else
		{
			$dark_class = ' class = "dark"';
			$light_class = '';
			$url = 'tournament_standings.php';
		}
		
		echo '<table class="transp" width="100%">';
		
		echo '<tr' . $dark_class . ' style="height: 40px;">';
		if (!is_null($tournament->league_id))
		{
			echo '<td width="32">';
			$this->league_pic->set($tournament->league_id, $tournament->league_name, $tournament->league_flags);
			$this->league_pic->show(ICONS_DIR, false, 30);
			echo '</td><td colspan="2"';' align="center"><b>' . $tournament->name . '</b>';
		}
		else
		{
			echo '<td colspan="3"';
		}
		echo ' align="center"><b>' . $tournament->name . '</b></td></tr>';
		
		echo '<tr' . $light_class . ' style="height: 80px;"><td colspan="3" align="center">';
		echo '<a href="' . $url . '?bck=1&id=' . $tournament->id . '" title="' . get_label('View tournament details.') . '">';
		
		$this->tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
		$this->tournament_pic->show(ICONS_DIR, false, $future ? 56 : 70);
		echo '</a>';
		if ($future)
		{
			echo '<br>' . format_date('l, F d', $tournament->start_time, $tournament->timezone);
		}
		echo '</td></tr>';
		
		echo '</table>';
	}
	
	private function show_event($event)
	{
		$future = ($event->start_time > time());
		if ($future)
		{
			$dark_class = ' class = "darker"';
			$light_class = ' class = "dark"';
			$url = 'event_info.php';
		}
		else
		{
			$dark_class = ' class = "dark"';
			$light_class = '';
			$url = 'event_standings.php';
		}
		
		echo '<table class="transp" width="100%">';
		
		echo '<tr' . $dark_class . ' style="height: 40px;">';
		if (!is_null($event->tournament_id))
		{
			echo '<td width="32">';
			$this->tournament_pic->set($event->tournament_id, $event->tournament_name, $event->tournament_flags);
			$this->tournament_pic->show(ICONS_DIR, false, 30);
			echo '</td><td colspan="2"';
		}
		else
		{
			echo '<td colspan="3"';
		}
		echo 'align="center"><b>' . $event->name . '</b></td></tr>';
		
		echo '<tr' . $light_class . ' style="height: 80px;"><td colspan="3" align="center">';
		echo '<a href="' . $url . '?bck=1&id=' . $event->id . '" title="' . get_label('View event details.') . '">';
		
		$this->event_pic->set($event->id, $event->name, $event->flags);
		$this->event_pic->show(ICONS_DIR, false, $future ? 56 : 70);
		echo '</a>';
		if ($future)
		{
			echo '<br>' . format_date('l, F d', $event->start_time, $event->timezone);
		}
		echo '</td></tr>';
		
		echo '</table>';
	}
	
	private function show_happenings($events, $tournaments)
	{
		$event_index = 0;
		$events_count = count($events);
		if ($events_count > 0)
		{
			$event = new stdClass();
			list (
				$event->id, $event->name, $event->flags, 
				$event->start_time, $event->duration, $event->timezone, 
				$event->tournament_id, $event->tournament_name, $event->tournament_flags, 
				$event->addr_id, $event->addr_flags, $event->addr, $event->addr_name) = $events[0];
		}
		else
		{
			$event = NULL;
		}
		
		$tournament_index = 0;
		$tournaments_count = count($tournaments);
		if ($tournaments_count > 0)
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, 
				$tournament->start_time, $tournament->duration, $tournament->timezone, 
				$tournament->addr_id, $tournament->addr_flags, $tournament->addr, $tournament->addr_name, 
				$tournament->league_id, $tournament->league_name, $tournament->league_flags) = $tournaments[0];
		}
		else
		{
			$tournament = NULL;
		}
			
		$happening_count = 0;
		$column_count = 0;
		while (true)
		{
			if ($event != NULL)
			{
				if ($tournament != NULL)
				{
					$show_tournament = ($event->start_time > $tournament->start_time || ($event->start_time == $tournament->start_time && $event->duration > $tournament->duration));
				}
				else
				{
					$show_tournament = false;
				}
			}
			else if ($tournament != NULL)
			{
				$show_tournament = true;
			}
			else
			{
				break;
			}
			
			if ($column_count == 0)
			{
				if ($happening_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . get_label('Tournaments and events') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			echo '<td width="' . COLUMN_WIDTH . '%" valign="top">';
			if ($show_tournament)
			{
				$this->show_tournament($tournament);
				
				++$tournament_index;
				if ($tournament_index < $tournaments_count)
				{
					list (
						$tournament->id, $tournament->name, $tournament->flags, 
						$tournament->start_time, $tournament->duration, $tournament->timezone, 
						$tournament->addr_id, $tournament->addr_flags, $tournament->addr, $tournament->addr_name, 
						$tournament->league_id, $tournament->league_name, $tournament->league_flags) = $tournaments[$tournament_index];
				}
				else
				{
					$tournament = NULL;
				}
			}
			else
			{
				$this->show_event($event);
				
				++$event_index;
				if ($event_index < $events_count)
				{
					list (
						$event->id, $event->name, $event->flags, 
						$event->start_time, $event->duration, $event->timezone, 
						$event->tournament_id, $event->tournament_name, $event->tournament_flags, 
						$event->addr_id, $event->addr_flags, $event->addr, $event->addr_name) = $events[$event_index];
				}
				else
				{
					$event = NULL;
				}
			}
			
			echo '</td>';
			++$column_count;
			++$happening_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		if ($column_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '"></td>';
		}
		if ($happening_count > 0)
		{
			echo '</tr></table>';
			return true;
		}
		return false;
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$this->club_user_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
	
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER, $this->id);
		$have_tables = false;
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? AND is_canceled = FALSE GROUP BY result', $this->id);
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
		
		// tournaments and events
		$tournaments = array();
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, c.timezone, a.id, a.flags, a.address, a.name, l.id, l.name, l.flags FROM tournaments t' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities c ON c.id = a.city_id' .
				' LEFT OUTER JOIN leagues l ON l.id = t.league_id' .
				' WHERE t.start_time + t.duration > UNIX_TIMESTAMP() AND t.club_id = ? ORDER BY t.start_time + t.duration, t.name, t.id LIMIT ' . (COLUMN_COUNT * ROW_COUNT),
			$this->id);
		while ($row = $query->next())
		{
			$tournaments[] = $row;
		}
		
		$events = array();
		$events_count = (COLUMN_COUNT * ROW_COUNT) - count($tournaments);
		if ($events_count > 0)
		{
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, t.name, t.flags, a.id, a.flags, a.address, a.name FROM events e' .
					' JOIN addresses a ON e.address_id = a.id' .
					' JOIN clubs c ON e.club_id = c.id' .
					' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
					' JOIN cities ct ON ct.id = c.city_id' .
					' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_BEFORE . ') = 0 AND e.club_id = ? ORDER BY e.start_time + e.duration, e.name, e.id LIMIT ' . $events_count,
				$this->id);
			while ($row = $query->next())
			{
				$events[] = $row;
			}
		}
		$have_tables = $this->show_happenings($events, $tournaments) || $have_tables;
	
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
			list ($note_id, $note_name, $note_value) = $row;
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
				echo '<tr><td>'.get_label('Mafia wins').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
				echo '<tr><td>'.get_label('Town wins').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($playing_count > 0)
			{
				echo '<tr><td>'.get_label('Still playing').'</td><td>' . $playing_count . '</td></tr>';
			}
			
			if ($civils_win_count + $mafia_win_count > 0)
			{
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.club_id = ?', $this->id);
				echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
				
				list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE club_id = ? AND is_canceled = FALSE AND result > 0', $this->id);
				echo '<tr><td>'.get_label('People moderated').':</td><td>' . $counter . '</td></tr>';
				
				list ($a_game, $s_game, $l_game) = Db::record(
					get_label('game'),
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE is_canceled = FALSE AND result > 0 AND club_id = ?', 
					$this->id);
				echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
				echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
			}
			echo '</table></p>';
		}
		
		// managers
		$query = new DbQuery(
			'SELECT u.id, u.name, u.flags, c.flags FROM club_users c JOIN users u ON u.id = c.user_id WHERE c.club_id = ? AND (c.flags & ' . USER_PERM_MANAGER . ') <> 0', $this->id);
		if ($row = $query->next())
		{
			$managers_count = 0;
			$columns_count = 0;
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="' . MANAGER_COLUMNS . '"><b>' . get_label('Managers') . '</b></td></tr>';
			do
			{
				list ($manager_id, $manager_name, $manager_flags, $club_manager_flags) = $row;
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
				$this->club_user_pic->set($manager_id, $manager_name, $club_manager_flags, 'c' . $this->id)->set($manager_id, $manager_name, $manager_flags);
				$this->club_user_pic->show(ICONS_DIR, false);
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
				list ($subclub_id, $subclub_name, $subclub_flags) = $row;
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
				$this->club_pic->show(ICONS_DIR, false);
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
			$query = new DbQuery(
				'SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags, cu.flags' .
					' FROM users u' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = u.club_id' .
					' WHERE u.club_id = ?' .
					' ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id' .
					' LIMIT ' . RATING_POSITIONS,
					$this->id);
					
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="4"><b>' . get_label('Best players') . '</b></td></tr>';
			$number = 1;
			while ($row = $query->next())
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags, $club_user_flags) = $row;

				echo '<td width="20" align="center">' . $number . '</td>';
				echo '<td width="50">';
				$this->club_user_pic->set($id, $name, $club_user_flags, 'c' . $this->id)->set($id, $name, $flags);
				$this->club_user_pic->show(ICONS_DIR, true, 50);
				echo '</td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
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