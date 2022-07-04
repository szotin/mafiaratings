<?php

require_once 'include/general_page_base.php';
require_once 'include/user.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/user_location.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/snapshot.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('ROW_COUNT', 3);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends GeneralPageBase
{
	private $event_pic;
	private $tournament_pic;
	private $series_pic;
	private $league_pic;
	
	private function show_series($row)
	{
		list (
			$series_id, $series_name, $series_flags, 
			$series_start_time, $series_duration, 
			$series_languages, 
			$series_league_id, $series_league_name, $series_league_flags) = $row;
		
		$future = ($series_start_time > time());
		if ($future)
		{
			$dark_class = ' class = "darker"';
			$light_class = ' class = "dark"';
			$url = 'series_info.php';
		}
		else
		{
			$dark_class = ' class = "dark"';
			$light_class = '';
			$url = 'series_standings.php';
		}
		
		echo '<table class="transp" width="100%">';
		
		echo '<tr' . $dark_class . ' style="height: 40px;">';
		echo '<td colspan="3"';
		echo ' align="center"><b>' . $series_name . '</b></td></tr>';
		
		echo '<tr' . $light_class . ' style="height: 80px;"><td colspan="3" align="center">';
		echo '<a href="' . $url . '?bck=1&id=' . $series_id . '" title="' . get_label('View series details.') . '">';
		$this->series_pic->set($series_id, $series_name, $series_flags);
		$this->series_pic->show(ICONS_DIR, false, $future ? 56 : 70);
		echo '</a>';
		if ($future)
		{
			echo '<br>' . format_date('l, F d', $series_start_time, get_timezone());
		}
		echo '</td></tr>';
		
		echo '<tr' . $dark_class . ' style="height: 40px;"><td colspan="2" align="center">' . $series_league_name . '</td><td width="34">';
		$this->league_pic->set($series_league_id, $series_league_name, $series_league_flags);
		$this->league_pic->show(ICONS_DIR, false, 30);
		echo '</td></tr>';
		
		echo '</table>';
	}
	
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
		echo '<td colspan="3"';
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
		
		echo '<tr' . $dark_class . ' style="height: 40px;"><td colspan="2" align="center">' . $tournament->club_name . '</td><td width="34">';
		$this->club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
		$this->club_pic->show(ICONS_DIR, false, 30);
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
		
		echo '<tr' . $dark_class . ' class="dark" style="height: 40px;"><td colspan="2" align="center">' . $event->club_name . '</td><td width="34">';
		$this->club_pic->set($event->club_id, $event->club_name, $event->club_flags);
		$this->club_pic->show(ICONS_DIR, false, 30);
		echo '</td></tr>';
		
		echo '</table>';
	}
	
	private function show_happenings($events, $tournaments, $series)
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
				$event->club_id, $event->club_name, $event->club_flags, 
				$event->languages, 
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
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, 
				$tournament->languages, 
				$tournament->addr_id, $tournament->addr_flags, $tournament->addr, $tournament->addr_name) = $tournaments[0];
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
						$tournament->club_id, $tournament->club_name, $tournament->club_flags, 
						$tournament->languages, 
						$tournament->addr_id, $tournament->addr_flags, $tournament->addr, $tournament->addr_name) = $tournaments[$tournament_index];
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
						$event->club_id, $event->club_name, $event->club_flags, 
						$event->languages, 
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
		
		foreach ($series as $row)
		{
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
			$this->show_series($row);
			
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
	
	private function show_changes($rows, $columns)
	{
		global $_profile;
		
		$snapshot = new Snapshot(time());
		$snapshot->shot();
		$query = new DbQuery('SELECT time, snapshot FROM snapshots ORDER BY time DESC LIMIT 10'); // probably limit is not needed at all
		while ($row = $query->next())
		{
			list ($prev_time, $json) = $row;
			$prev_snapshot = new Snapshot($prev_time, $json);
			$prev_snapshot->load_user_details();
			$diff = $snapshot->compare($prev_snapshot);
			if (count($diff) > 0)
			{
				break;
			}
		}
		if (!isset($diff) || count($diff) == 0)
		{
			return;
		}
		
		$timezone = get_timezone();
		$firstDark = false;
		$dark = false;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="' . $columns . '"><b>' . get_label('Latest changes in the rating (since [0])', format_date('j M Y', $prev_time, $timezone)) . '</b></a></td></tr><tr>';
		for ($i = 0; $i < $columns; ++$i)
		{
			$dark = $firstDark;
			$firstDark = !$firstDark;
			
			$count = 0;
			$row_count = 0;
			echo '<td width="' . floor(100 / $columns) . '%" valign="top"><table class="transp" width="100%">';
			foreach ($diff as $player)
			{
				if ($count++ % $columns != $i)
				{
					continue;
				}
				
				if ($row_count++ >= $rows)
				{
					break;
				}
				
				if ($dark)
				{
					echo '<tr class="dark">';
				}
				else
				{
					echo '<tr>';
				}
				$dark = !$dark;
				echo '<td width="48" align="center">';
				$this->user_pic->set($player->id, $player->user_name, $player->user_flags);
				$this->user_pic->show(ICONS_DIR, true, 36);
				echo '</td><td width="48"><a href="club_main.php?id=' . $player->club_id . '&bck=1">';
				if (!is_null($player->club_id) && $player->club_id > 0)
				{
					$this->club_pic->set($player->club_id, $player->club_name, $player->club_flags);
					$this->club_pic->show(ICONS_DIR, true, 36);
				}
				echo '</a></td><td width="30">';
				if (isset($player->src))
				{
					if (isset($player->dst))
					{
						if ($player->src > $player->dst)
						{
							echo '<img src="images/up.png">';
							if ($player->user_flags & USER_FLAG_MALE)
							{
								echo '</td><td>' . get_label('[0] moved up from [1] to [2] place.', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
							else
							{
								// the space in the end of a string means female gender for the languages where it matters
								echo '</td><td>' . get_label('[0] moved up from [1] to [2] place. ', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
						}
						else if ($player->src < $player->dst)
						{
							echo '<img src="images/down_red.png">';
							if ($player->user_flags & USER_FLAG_MALE)
							{
								echo '</td><td>' . get_label('[0] moved down from [1] to [2] place.', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
							else
							{
								// the space in the end of a string means female gender for the languages where it matters
								echo '</td><td>' . get_label('[0] moved down from [1] to [2] place. ', '<b>' . $player->user_name . '</b>', $player->src, $player->dst);
							}
						}
					}
					else
					{
						echo '<img src="images/down_red.png"></td><td>';
						if ($player->user_flags & USER_FLAG_MALE)
						{
							echo get_label('[0] left top 100.', '<b>' . $player->user_name . '</b>', $player->src);
						}
						else
						{
							// the space in the end of a string means female gender for the languages where it matters
							echo get_label('[0] left top 100. ', '<b>' . $player->user_name . '</b>', $player->src);
						}
					}
				}
				else
				{
					echo '<img src="images/up.png"></td><td>';
					if ($player->user_flags & USER_FLAG_MALE)
					{
						echo get_label('[0] entered top 100 and gained [1] place.', '<b>' . $player->user_name . '</b>', $player->dst);
					}
					else
					{
						// the space in the end of a string means female gender for the languages where it matters
						echo get_label('[0] entered top 100 and gained [1] place. ', '<b>' . $player->user_name . '</b>', $player->dst);
					}
				}
			}
			echo '</table></td>';
		}
		echo '</tr></table>';
	}

	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$this->league_pic = new Picture(LEAGUE_PICTURE);
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$this->series_pic = new Picture(SERIES_PICTURE);
		$this->event_pic = new Picture(EVENT_PICTURE);
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', ''));
		echo '</td></tr></table></p>';
		
		$condition = new SQL();
		$ccc_id = $ccc_filter->get_id();
		$ccc_code = $ccc_filter->get_code();
		$ccc_type = $ccc_filter->get_type();
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND c.id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND c.id IN (SELECT club_id FROM club_users WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND c.id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND c.id IN (SELECT l.id FROM clubs l JOIN cities i ON i.id = l.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		echo '<table width="100%"><tr><td valign="top">';
		$have_tables = false;
	
		// adverts
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, ct.timezone, n.id, n.timestamp, n.message FROM news n' . 
				' JOIN clubs c ON c.id = n.club_id' .
				' JOIN cities ct ON ct.id = c.city_id WHERE n.expires >= UNIX_TIMESTAMP() AND n.timestamp <= UNIX_TIMESTAMP()', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT 3');
		
		if ($row = $query->next())
		{
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="2"><b>' . get_label('Adverts') . '</b></a></td></tr>';
			
			do
			{
				list ($club_id, $club_name, $club_flags, $timezone, $id, $timestamp, $message) = $row;
				echo '<tr>';
				echo '<td width="100" class="dark" align="center" valign="top"><a href="club_main.php?id=' . $club_id . '&bck=1">' . $club_name . '<br>';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, false, 60);
				echo '</a></td><td valign="top"><b>' . format_date('l, F d, Y', $timestamp, $timezone) . ':</b><br>' . $message . '</td></tr>';
				
				
			} while ($row = $query->next());
			echo '</table>';
			$have_tables = true;
		}
		
		$had_tables = $have_tables;
		if ($have_tables)
		{
			echo '<p>';
		}
		
		// tournaments, events and series
		$series = array();
		$query = new DbQuery(
			'SELECT s.id, s.name, s.flags, s.start_time, s.duration, s.langs, l.id, l.name, l.flags FROM series s' .
			' JOIN leagues l ON l.id = s.league_id' .
			' WHERE s.start_time + s.duration > UNIX_TIMESTAMP()', $condition);
		$query->add(' ORDER BY s.start_time + s.duration, s.name, s.id LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		while ($row = $query->next())
		{
			$series[] = $row;
		}
		
		$tournaments = array();
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.flags, a.address, a.name FROM tournaments t' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE t.start_time + t.duration > UNIX_TIMESTAMP()', $condition);
		$query->add(' ORDER BY t.start_time + t.duration, t.name, t.id LIMIT ' . (COLUMN_COUNT * ROW_COUNT));
		while ($row = $query->next())
		{
			$tournaments[] = $row;
		}
		
		$events = array();
		$events_count = (COLUMN_COUNT * ROW_COUNT) - count($tournaments);
		if ($events_count > 0)
		{
			$clubs = array();
			$query = new DbQuery(
				'SELECT e.id, e.name, e.flags, e.start_time, e.duration, ct.timezone, t.id, t.name, t.flags, c.id, c.name, c.flags, e.languages, a.id, a.flags, a.address, a.name FROM events e' .
				' JOIN addresses a ON e.address_id = a.id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' JOIN clubs c ON e.club_id = c.id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_HIDDEN_BEFORE . ') = 0', $condition);
			$query->add(' ORDER BY e.start_time + e.duration, e.name, e.id LIMIT ' . $events_count);
			while ($row = $query->next())
			{
				if (!isset($clubs[$row[9]]))
				{
					$events[] = $row;
					$clubs[$row[9]] = true;
				}
			}
		}
		$have_tables = $this->show_happenings($events, $tournaments, $series) || $have_tables;
		
		if ($had_tables)
		{
			echo '</p>';
		}

		$this->show_changes(15, 1);
		
		// ratings
		$query = new DbQuery('SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags FROM users u LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE u.games > 0');
		switch($ccc_type)
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM club_users WHERE club_id = ?)', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND u.id IN (SELECT user_id FROM club_users WHERE club_id IN (' . $_profile->get_comma_sep_clubs() . '))');
			}
			break;
		case CCCF_CITY:
			$query->add(' AND u.city_id = ?', $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won, u.id LIMIT 15');
		
		$number = 1;
		if ($row = $query->next())
		{
			echo '</td><td width="320" valign="top">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="5"><b>' . get_label('Best players') . '</b></td></tr>';
			
			do
			{
				list ($id, $name, $rating, $games_played, $games_won, $flags, $club_id, $club_name, $club_flags) = $row;

				echo '<td width="20" class="dark" align="center">' . $number . '</td>';
				echo '<td width="50" valign="top">';
				$this->user_pic->set($id, $name, $flags);
				$this->user_pic->show(ICONS_DIR, true, 50);
				echo '</td><td width="36">';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, false, 36);
				echo '</td><td><a href="user_info.php?id=' . $id . '&bck=1">' . cut_long_name($name, 45) . '</a></td>';
				echo '<td width="60" align="center">' . number_format($rating) . '</td>';
				echo '</tr>';
				
				++$number;
			} while ($row = $query->next());
			
			echo '</table>';
		}
		
		echo '</td></tr></table>';
	}
}

$page = new Page();
$page->run(get_label('Home'));

?>