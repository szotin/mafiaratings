<?php

require_once 'include/league.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

define('MAX_CLUBS', 15);

class Page extends LeaguePageBase
{
	private $tournament_pic;
	private $series_pic;
	
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
		
		echo '<tr' . $dark_class . ' style="height: 40px;"><td colspan="3" align="center"><b>' . $tournament->name . '</b></td></tr>';
		echo '<tr' . $light_class . ' style="height: 80px;"><td colspan="3" align="center">';
		echo '<a href="' . $url . '?bck=1&id=' . $tournament->id . '" title="' . get_label('View tournament details.') . '">';
		
		$this->tournament_pic->set($tournament->id, $tournament->name, $tournament->flags);
		$this->tournament_pic->show(ICONS_DIR, false, $future ? 56 : 70);
		echo '</a>';
		if ($future)
		{
			echo '<br>' . format_date_period($tournament->start_time, $tournament->duration, $tournament->timezone);
		}
		echo '</td></tr>';
		
		echo '<tr' . $dark_class . ' style="height: 40px;"><td colspan="2" align="center">' . $tournament->club_name . '</td><td width="34">';
		$this->club_pic->set($tournament->club_id, $tournament->club_name, $tournament->club_flags);
		$this->club_pic->show(ICONS_DIR, false, 30);
		echo '</td></tr>';
		
		echo '</table>';
	}
	
	private function show_series($series)
	{
		$future = ($series->start_time > time());
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
		
		echo '<tr' . $dark_class . ' style="height: 40px;"><td colspan="3" align="center"><b>' . $series->name . '</b></td></tr>';
		echo '<tr' . $light_class . ' style="height: 80px;"><td colspan="3" align="center">';
		echo '<a href="' . $url . '?bck=1&id=' . $series->id . '" title="' . get_label('View series details.') . '">';
		
		$this->series_pic->set($series->id, $series->name, $series->flags);
		$this->series_pic->show(ICONS_DIR, false, $future ? 56 : 70);
		echo '</a>';
		if ($future)
		{
			echo '<br>' . format_date_period($series->start_time, $series->duration, $tournament->timezone);
		}
		echo '</td></tr>';
		
		echo '</table>';
	}
	
	private function show_tournaments($tournaments, $series)
	{
		$tournament = new stdClass();
		$tournament_count = 0;
		$column_count = 0;
		foreach ($tournaments as $row)
		{
			list (
				$tournament->id, $tournament->name, $tournament->flags, 
				$tournament->start_time, $tournament->duration, $tournament->timezone, 
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, 
				$tournament->languages, 
				$tournament->addr_id, $tournament->addr_flags, $tournament->addr, $tournament->addr_name) = $row;
			
			if ($column_count == 0)
			{
				if ($tournament_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . get_label('Tournaments and series') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			echo '<td width="' . COLUMN_WIDTH . '%" valign="top">';
			$this->show_tournament($tournament);
			echo '</td>';
			++$column_count;
			++$tournament_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		$s = new stdClass();
		foreach ($series as $row)
		{
			list ($s->id, $s->name, $s->flags, $s->start_time, $s->duration, $s->languages) = $row;
			
			if ($column_count == 0)
			{
				if ($tournament_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td colspan="' . COLUMN_COUNT . '"><b>' . get_label('Tournaments and series') . '</b></td></tr>';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			echo '<td width="' . COLUMN_WIDTH . '%" valign="top">';
			$this->show_series($s);
			echo '</td>';
			++$column_count;
			++$tournament_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		
		if ($column_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '"></td>';
		}
		if ($tournament_count > 0)
		{
			echo '</tr></table>';
			return true;
		}
		return false;
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$this->series_pic = new Picture(SERIES_PICTURE);
		
		echo '<table width="100%"><tr><td valign="top">';
		$have_tables = false;
	
		// tournaments
		$tournaments = array();
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.flags, a.address, a.name' . 
			' FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = c.city_id' .
			' LEFT OUTER JOIN leagues l ON l.id = s.league_id' .
			' WHERE t.start_time + t.duration > UNIX_TIMESTAMP() AND s.league_id = ?' .
			' ORDER BY t.start_time + t.duration, t.name, t.id LIMIT ' . (COLUMN_COUNT * ROW_COUNT), $this->id);
		while ($row = $query->next())
		{
			$tournaments[] = $row;
		}
		
		$series = array();
		$query = new DbQuery('SELECT s.id, s.name, s.flags, s.start_time, s.duration, s.langs FROM series s' .
			' WHERE s.start_time + s.duration > UNIX_TIMESTAMP() AND s.league_id = ?' .
			' ORDER BY s.start_time + s.duration, s.name, s.id LIMIT ' . (COLUMN_COUNT * ROW_COUNT), $this->id);
		while ($row = $query->next())
		{
			$series[] = $row;
		}
		
		$have_tables = $this->show_tournaments($tournaments, $series) || $have_tables;
		
		// clubs
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, COUNT(DISTINCT g.tournament_id) as _t, COUNT(DISTINCT p.user_id) as _p FROM players p' . 
			' JOIN games g ON g.id = p.game_id' .
			' JOIN series_tournaments st ON st.tournament_id = g.tournament_id' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN clubs c ON c.id = g.club_id WHERE s.league_id = ? GROUP BY c.id ORDER BY _t DESC, _p DESC LIMIT ' . MAX_CLUBS, $this->id);
		
		$number = 1;
		if ($row = $query->next())
		{
			echo '</td><td width="320" valign="top">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td colspan="3"><b>' . get_label('Clubs') . '</b></td><td>' . get_label('Tournaments played') . '</td><td>' . get_label('Players participated') . '</td></tr>';
			
			do
			{
				list ($club_id, $club_name, $club_flags, $t_count, $p_count) = $row;

				echo '<td width="20" class="dark" align="center">' . $number . '</td>';
				echo '<td width="50" valign="top">';
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 50);
				echo '</td><td><a href="club_main.php?id=' . $club_id . '&bck=1">' . $club_name . '</a></td>';
				echo '<td width="60" align="center">' . $t_count . '</td>';
				echo '<td width="60" align="center">' . $p_count . '</td>';
				echo '</tr>';
				
				++$number;
			} while ($row = $query->next());
			
			echo '</table>';
		}
		
		echo '</td></tr></table>';
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>