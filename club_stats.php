<?php

require_once 'include/page_base.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends ClubPageBase
{
	private $season;
	private $min_games;
	private $games_count;

	protected function prepare()
	{
		parent::prepare();
		
		list($timezone) = Db::record(get_label('club'), 'SELECT i.timezone FROM clubs c JOIN cities i ON c.city_id = i.id WHERE c.id = ?', $this->id);
		date_default_timezone_set($timezone);
		
		$this->season = SEASON_LATEST;
		if (isset($_REQUEST['season']))
		{
			$this->season = $_REQUEST['season'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang_code;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		$this->season = show_club_seasons_select($this->id, $this->season, 'filterChanged()', get_label('Show stats of a specific season.'));
		echo ' ';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '</td></tr></table>';
		
		$condition = get_club_season_condition($this->season, 'g.start_time', 'g.end_time');
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND g.is_rating <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND g.is_rating = 0');
		}

		list($this->games_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$query = new DbQuery('SELECT g.result, count(*) FROM games g WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
		$query->add(' GROUP BY result');
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
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td colspan="2"><a href="club_games.php?bck=1&id=' . $this->id . '"><b>' . get_label('Stats') . '</b></a></td></tr>';
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
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
			echo '<tr><td>'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
			
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT g.moderator_id) FROM games g WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
			echo '<tr><td>'.get_label('Referees').':</td><td>' . $counter . '</td></tr>';
			
			list ($a_game, $s_game, $l_game) = Db::record(
				get_label('game'),
				'SELECT AVG(g.end_time - g.start_time), MIN(g.end_time - g.start_time), MAX(g.end_time - g.start_time) ' .
					'FROM games g WHERE g.is_canceled = FALSE AND g.result > 0 AND club_id = ?', 
				$this->id, $condition);
			echo '<tr><td>'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
			echo '<tr><td>'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
		}
		echo '</table>';
		
		if ($games_count > 0)
		{
			$query = new DbQuery('SELECT p.kill_type, p.role, count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE g.club_id = ? AND g.is_canceled = FALSE AND g.result > 0', $this->id, $condition);
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
				case 0:
					echo get_label('Survived');
					break;
				case 1:
					echo get_label('Killed in day');
					break;
				case 2:
					echo get_label('Killed in night');
					break;
				case 3:
					echo get_label('Killed by warnings');
					break;
				case 4:
					echo get_label('Gave up');
					break;
				case 5:
					echo get_label('Kicked out');
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
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({filter: checkboxFilterFlags(), season: $('#season').val()});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('General Stats'));

?>