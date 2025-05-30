<?php

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/ccc_filter.php';

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

class Page extends UserPageBase
{
	protected function show_body()
	{
		if (LOCK_DATE != NULL && !is_permitted(PERMISSION_ADMIN))
		{
			$dt = new DateTime(LOCK_DATE, new DateTimeZone(get_timezone()));
			if (time() < $dt->getTimestamp())
			{
				throw new Exc(get_label('Page is temporarily inavalable until [0].', LOCK_DATE));
			}
		}
			
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$min_time = $max_time = 0;
		$query = new DbQuery('SELECT MIN(game_end_time), MAX(game_end_time) FROM players WHERE user_id = ?', $this->id);
		if ($row = $query->next())
		{
			list($min_time, $max_time) = $row;
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '&emsp;&emsp;';
		show_roles_select($roles, 'filterChanged()', get_label('Use stats of a specific role.'), ROLE_NAME_FLAG_SINGLE);
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '</td></tr></table>';
		
		$condition = new SQL();
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
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
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.id = ? OR i.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
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
			$condition->add(' AND g.is_rating <> 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		$stats = new PlayerStats($this->id, $roles, $condition);
		$mafs_in_legacy = $stats->guess3maf * 3 + $stats->guess2maf * 2 + $stats->guess1maf;
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th-short darker"><td colspan="2">' . get_label('Playing') . '</td></tr>';
		echo '<tr><td class="dark" width="300">'.get_label('Games played').':</td><td>' . $stats->games_played . '</td></tr>';
		if ($stats->games_played > 0)
		{
			echo '<tr><td class="dark" width="300">'.get_label('Wins').':</td><td>' . $stats->games_won . ' (' . number_format($stats->games_won*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Rating').':</td><td>' . get_label('[0] ([1] per game)', number_format($stats->rating, 2), number_format($stats->rating/$stats->games_played, 3)) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best player').':</td><td>' . $stats->best_player . ' (' . number_format($stats->best_player*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best move').':</td><td>' . $stats->best_move . ' (' . number_format($stats->best_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Auto-bonus removed').':</td><td>' . $stats->worst_move . ' (' . number_format($stats->worst_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Bonus points').':</td><td>' . number_format($stats->bonus, 2) . ' (' . number_format($stats->bonus/$stats->games_played, 3) . ' ' . get_label('per game') . ')</td></tr>';
			echo '<tr><td class="dark">'.get_label('Killed first night').':</td><td>' . $stats->killed_first_night . ' (' . number_format($stats->killed_first_night*100.0/$stats->games_played, 1) . '%)</td></tr>';
			if ($stats->killed_first_night > 0)
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (' . number_format($stats->guess3maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (' . number_format($stats->guess2maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (' . number_format($stats->guess1maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (' . number_format($mafs_in_legacy*100.0/($stats->killed_first_night * 3), 1) . '%)</td></tr>';
			}
			else
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (0.0%)</td></tr>';
			}
			echo '</table>';
		
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Voting') . '</td></tr>';
			
			$count = $stats->voted_civil + $stats->voted_mafia + $stats->voted_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Voted against civilians').':</td><td>';
			if ($stats->voted_civil > 0)
			{
				echo $stats->voted_civil . ' (' . number_format($stats->voted_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against mafia').':</td><td>';
			if ($stats->voted_mafia > 0)
			{
				echo $stats->voted_mafia . ' (' . number_format($stats->voted_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against sheriff').':</td><td>';
			if ($stats->voted_sheriff > 0)
			{
				echo $stats->voted_sheriff . ' (' . number_format($stats->voted_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->voted_by_civil + $stats->voted_by_mafia + $stats->voted_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by civilians').':</td><td>';
			if ($stats->voted_by_civil > 0)
			{
				echo $stats->voted_by_civil . ' (' . number_format($stats->voted_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by mafia').':</td><td>';
			if ($stats->voted_by_mafia > 0)
			{
				echo $stats->voted_by_mafia . ' (' . number_format($stats->voted_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by sheriff').':</td><td>';
			if ($stats->voted_by_sheriff > 0)
			{
				echo $stats->voted_by_sheriff . ' (' . number_format($stats->voted_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Nominating') . '</td></tr>';
			
			$count = $stats->nominated_civil + $stats->nominated_mafia + $stats->nominated_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Nominated civilians').':</td><td>';
			if ($stats->nominated_civil > 0)
			{
				echo $stats->nominated_civil . ' (' . number_format($stats->nominated_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated mafia').':</td><td>';
			if ($stats->nominated_mafia > 0)
			{
				echo $stats->nominated_mafia . ' (' . number_format($stats->nominated_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated sheriff').':</td><td>';
			if ($stats->nominated_sheriff > 0)
			{
				echo $stats->nominated_sheriff . ' (' . number_format($stats->nominated_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->nominated_by_civil + $stats->nominated_by_mafia + $stats->nominated_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by civilians').':</td><td>';
			if ($stats->nominated_by_civil > 0)
			{
				echo $stats->nominated_by_civil . ' (' . number_format($stats->nominated_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by mafia').':</td><td>';
			if ($stats->nominated_by_mafia > 0)
			{
				echo $stats->nominated_by_mafia . ' (' . number_format($stats->nominated_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by sheriff').':</td><td>';
			if ($stats->nominated_by_sheriff > 0)
			{
				echo $stats->nominated_by_sheriff . ' (' . number_format($stats->nominated_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Surviving') . '</td></tr>';
			foreach ($stats->surviving as $surviving)
			{
				switch ($surviving->type)
				{
					case KILL_TYPE_SURVIVED:
						echo '<tr><td class="dark" width="300">'.get_label('Survived').':</td><td>';
						break;
					case KILL_TYPE_DAY:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in day').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_NIGHT:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in night').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_WARNINGS:
						echo '<tr><td class="dark" width="300">'.get_label('Killed by warnings in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_GIVE_UP:
						echo '<tr><td class="dark" width="300">'.get_label('Left the game in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Kicked out in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_TEAM_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Made the opposite team win').' ' . $surviving->round . ':</td><td>';
						break;
					default:
						echo '<tr><td class="dark" width="300">'.get_label('Round').' ' . $surviving->round . ':</td><td>';
						break;
				}
				echo $surviving->count . ' (' . number_format($surviving->count*100.0/$stats->games_played, 2) . '%)</td></tr>';
			}
			echo '</table></p>';
			
			if ($roles == POINTS_BLACK || $roles == POINTS_MAFIA || $roles == POINTS_DON)
			{
				$mafia_stats = new MafiaStats($this->id, $roles, $condition);
				echo '<p><table class="bordered light" width="100%">';
				echo '<tr class="th-short darker"><td colspan="2">' . get_label('Mafia shooting') . '</td></tr>';
				
				$count = $mafia_stats->shots3_ok + $mafia_stats->shots3_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('3 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots3_ok . ' '.get_label('success;').' ' . $mafia_stats->shots3_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots3_ok*100/$count, 1) . get_label('% success rate.');
					if ($mafia_stats->shots3_fail > 0)
					{
						echo $mafia_stats->shots3_fail . ' '.get_label('times guilty in misses.');
					}
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots2_ok + $mafia_stats->shots2_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('2 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots2_ok . ' '.get_label('success;').' ' . $mafia_stats->shots2_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots2_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots1_ok + $mafia_stats->shots1_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('Single shooter').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots1_ok . ' '.get_label('success;').' ' . $mafia_stats->shots1_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots1_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				echo '</table></p>';
			}
			
			if ($roles == POINTS_SHERIFF)
			{
				$sheriff_stats = new SheriffStats($this->id, $condition);
				$count = $sheriff_stats->civil_found + $sheriff_stats->mafia_found;
				if ($count > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Sheriff stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Red checks').':</td><td>' . $sheriff_stats->civil_found . ' (' . number_format($sheriff_stats->civil_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->civil_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Black checks').':</td><td>' . $sheriff_stats->mafia_found . ' (' . number_format($sheriff_stats->mafia_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->mafia_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '</table></p>';
				}
			}
			
			if ($roles == POINTS_DON)
			{
				$don_stats = new DonStats($this->id, $condition);
				if ($don_stats->games_played > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Don stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found').':</td><td>' . $don_stats->sheriff_found . ' ' . $don_stats->games_played . '(' . number_format($don_stats->sheriff_found*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff arranged').':</td><td>' . $don_stats->sheriff_arranged . ' (' . number_format($don_stats->sheriff_arranged*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found first night').':</td><td>' . $stats->sheriff_found_first_night . ' (' . number_format($stats->sheriff_found_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff killed first night').':</td><td>' . $stats->sheriff_killed_first_night . ' (' . number_format($stats->sheriff_killed_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '</table></p>';
				}
			}
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Miscellaneous') . '</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Warnings').':</td><td>' . $stats->warnings . ' (' . number_format($stats->warnings/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Arranged by mafia').':</td><td>' . $stats->arranged . ' (' . number_format($stats->arranged/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by don').':</td><td>' . $stats->checked_by_don . ' (' . number_format($stats->checked_by_don*100/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by sheriff').':</td><td>' . $stats->checked_by_sheriff . ' (' . number_format($stats->checked_by_sheriff*100/$stats->games_played, 1) . '%)</td></tr>';
		}
		echo '</table></p>';
		
		if ($this->games_moderated > 0)
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Refereeing') . '</td></tr>';
			
			$playing_count = 0;
			$civils_win_count = 0;
			$mafia_win_count = 0;
			$query = new DbQuery('SELECT result, count(*) FROM games WHERE moderator_id = ? AND is_canceled = FALSE GROUP BY result', $this->id);
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
			
			echo '<tr><td class="dark" width="300">'.get_label('Games refereed').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
			if ($civils_win_count + $mafia_win_count > 0)
			{
				echo '<tr><td class="dark">'.get_label('Mafia wins').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Town wins').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($playing_count > 0)
			{
				echo '<tr><td class="dark">'.get_label('Still playing').':</td><td>' . $playing_count . '</td></tr>';
			}
			
			if ($civils_win_count + $mafia_win_count > 0)
			{
				$query = new DbQuery('SELECT COUNT(DISTINCT p.user_id), SUM(p.warns) FROM players p, games g WHERE p.game_id = g.id AND g.moderator_id = ?', $this->id);
				
				list ($players_moderated, $gave_warnings) = $query->record(get_label('player'));
				echo '<tr><td class="dark">'.get_label('Refereed players').':</td><td>' . $players_moderated . '</td></tr>';
				echo '<tr><td class="dark">'.get_label('Gave warnings').':</td><td>' . get_label('[0] ([1] per game)', $gave_warnings, number_format($gave_warnings/($civils_win_count + $mafia_win_count), 2)) . '</td></tr>';
			}
			echo '</table></p>';
		}
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({filter: checkboxFilterFlags(), roles: $('#roles').val()});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('General Stats'));

?>