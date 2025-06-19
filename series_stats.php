<?php

require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends SeriesPageBase
{
	private $min_games;
	private $games_count;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		$timezone = 'America/Vancouver';
		if (isset($_profile))
		{
			date_default_timezone_set(get_timezone());
		}
		
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $this->filter);
		echo '</td></tr></table></p>';
		
		$subseries_csv = get_subseries_csv($this->id);
		$condition = new SQL(' AND st.series_id IN ('.$subseries_csv.')');
		if ($this->filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($this->filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($this->filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch ($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (SELECT club_id FROM club_users WHERE user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE c.id = ? OR c.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE c.country_id = ?)', $ccc_id);
			break;
		}
		
		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN series_tournaments st ON st.tournament_id = g.tournament_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0', $condition);

		$civils_win_count = 0;
		$mafia_win_count = 0;
		$tie_count = 0;
		$query = new DbQuery('SELECT g.result, count(*) FROM games g JOIN series_tournaments st ON st.tournament_id = g.tournament_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0', $condition);
		$query->add(' GROUP BY result');
		while ($row = $query->next())
		{
			switch ($row[0])
			{
				case GAME_RESULT_TOWN:
					$civils_win_count = $row[1];
					break;
				case GAME_RESULT_MAFIA:
					$mafia_win_count = $row[1];
					break;
				case GAME_RESULT_TIE:
					$tie_count = $row[1];
					break;
			}
		}
		$games_count = $civils_win_count + $mafia_win_count + $tie_count;
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><a href="games.php?bck=1"><b>' . get_label('Stats') . '</b></a></td></tr>';
		echo '<tr><td width="200">'.get_label('Games played').':</td><td>' . $games_count . '</td></tr>';
		if ($games_count > 0)
		{
			echo '<tr><td>'.get_label('Mafia wins').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/$games_count, 1) . '%)</td></tr>';
			echo '<tr><td>'.get_label('Town wins').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/$games_count, 1) . '%)</td></tr>';
			echo '<tr><td>'.get_label('Ties').':</td><td>' . $tie_count . ' (' . number_format($tie_count*100.0/$games_count, 1) . '%)</td></tr>';

			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id JOIN series_tournaments st ON st.tournament_id = g.tournament_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0', $condition);
			echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
			
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT g.moderator_id) FROM games g JOIN series_tournaments st ON st.tournament_id = g.tournament_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0', $condition);
			echo '<tr><td>'.get_label('Referees').':</td><td>' . $counter . '</td></tr>';
			
			list ($a_game, $s_game, $l_game) = Db::record(
				get_label('game'),
				'SELECT AVG(g.end_time - g.start_time), MIN(g.end_time - g.start_time), MAX(g.end_time - g.start_time) ' .
				'FROM games g ' .
				'JOIN series_tournaments st ON st.tournament_id = g.tournament_id ' .
				'WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0 AND g.end_time > g.start_time + 900 AND g.end_time < g.start_time + 20000', 
				$condition);
			echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
		}
		echo '</table>';
		
		if ($games_count > 0)
		{
			$query = new DbQuery('SELECT p.kill_type, p.role, count(*) FROM players p JOIN games g ON p.game_id = g.id JOIN series_tournaments st ON st.tournament_id = g.tournament_id WHERE (g.flags & '.GAME_FLAG_CANCELED.') = 0', $condition);
			$query->add(' GROUP BY p.kill_type, p.role');
			$killed = array();
			while ($row = $query->next())
			{
				list ($kill_type, $role, $count) = $row;
				if (!isset($killed[$kill_type]))
				{
					$killed[$kill_type] = array();
				}
				$killed[$kill_type][$role] = $count;
			}
			
			foreach ($killed as $kill_type => $roles)
			{
				echo '<table class="bordered light" width="100%">';
				echo '<tr class="darker"><td colspan="2"><b>';
				switch ($kill_type)
				{
				case KILL_TYPE_SURVIVED:
					echo get_label('Survived');
					break;
				case KILL_TYPE_DAY:
					echo get_label('Killed in day');
					break;
				case KILL_TYPE_NIGHT:
					echo get_label('Killed in night');
					break;
				case KILL_TYPE_WARNINGS:
					echo get_label('Killed by warnings');
					break;
				case KILL_TYPE_GIVE_UP:
					echo get_label('Gave up');
					break;
				case KILL_TYPE_KICK_OUT:
					echo get_label('Kicked out');
					break;
				case KILL_TYPE_TEAM_KICK_OUT:
					echo get_label('Mod team kills');
					break;
				}
				echo ':</b></td></tr>';
				foreach ($roles as $role => $count)
				{
					echo '<tr><td width="200">';
					switch ($role)
					{
					case ROLE_CIVILIAN:
						echo get_label('Civilians');
						break;
					case ROLE_SHERIFF:
						echo get_label('Sheriffs');
						break;
					case ROLE_MAFIA:
						echo get_label('Mafiosies');
						break;
					case ROLE_DON:
						echo get_label('Dons');
						break;
					}
					echo ':</td><td>' . $count . '</td></tr>';
				}
				echo '</table>';
			}
		}
	}
}

$page = new Page();
$page->run(get_label('Statistics'));

?>