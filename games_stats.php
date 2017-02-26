<?php

require_once 'include/general_page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile;
	
		echo '<table class="bordered light" width="100%">';
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$terminated_count = 0;
		
		$condition = new SQL(' WHERE TRUE');
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			// ATTENTION!!! Not quite right. We select all games played in clubs of the city.
			// We should select all games played in the city. Can't do it before reorganizing games.
			// Sould add city field to a game or make all games to be played in the event. TBD.
			// Same about countries.
			$condition->add(' AND g.club_id IN (SELECT id FROM clubs WHERE city_id IN (SELECT id FROM cities WHERE id = ? OR near_id = ?))', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.club_id IN (SELECT c.id FROM clubs c JOIN cities i ON i.id = c.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		$query = new DbQuery('SELECT g.result, count(*) FROM games g', $condition);
		$query->add(' GROUP BY g.result');
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
				case 3:
					$terminated_count = $row[1];
					break;
			}
		}
		echo '<tr class="th-short darker"><td colspan="2">'.get_label('Games statistics').'</td></tr>';
		
		echo '<tr><td class="dark" width="200">'.get_label('Games played').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
		if ($civils_win_count + $mafia_win_count > 0)
		{
			echo '<tr><td class="dark">'.get_label('Mafia won in').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Civilians won in').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
		}
		if ($terminated_count > 0)
		{
			echo '<tr><td class="dark">'.get_label('Games terminated').':</td><td>' . $terminated_count . ' (' . number_format($terminated_count*100.0/($terminated_count + $civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
		}
		if ($playing_count > 0)
		{
			echo '<tr><td class="dark">'.get_label('Still playing').':</td><td>' . $playing_count . '</td></tr>';
		}
		
		if ($civils_win_count + $mafia_win_count > 0)
		{
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON g.id = p.game_id', $condition);
			echo '<tr><td class="dark">'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
			
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT g.moderator_id) FROM games g', $condition);
			echo '<tr><td class="dark">'.get_label('People moderated').':</td><td>' . $counter . '</td></tr>';
			
			$sql = new SQL('SELECT AVG(g.end_time - g.start_time), MIN(g.end_time - g.start_time), MAX(g.end_time - g.start_time) FROM games g', $condition);
			$sql->add(' AND g.result > 0 AND g.result < 3');
			list ($a_game, $s_game, $l_game) = Db::record(get_label('game'), $sql);
			echo '<tr><td class="dark">'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
		}
		echo '</table>';
	}
	}

$page = new Page();
$page->run(get_label('Games statistics'), PERM_ALL);

?>