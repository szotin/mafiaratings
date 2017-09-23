<?php

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';

class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] stats', $this->title);
	}
	
	protected function show_body()
	{
		$club_id = 0;
		if (isset($_REQUEST['club']))
		{
			$club_id = $_REQUEST['club'];
		}
	
		$moder_stats = true;
		if (isset($_REQUEST['filter']))
		{
			$roles = 0;
			if (isset($_REQUEST['civil']))
			{
				$roles |= ROLE_CIVIL;
			}
			if (isset($_REQUEST['sheriff']))
			{
				$roles |= ROLE_SHERIFF;
			}
			if (isset($_REQUEST['mafia']))
			{
				$roles |= ROLE_MAFIA;
			}
			if (isset($_REQUEST['don']))
			{
				$roles |= ROLE_DON;
			}
			$moder_stats = isset($_REQUEST['moder']);
		}
		else
		{
			$roles = ROLE_ANY;
			$moder_stats = ($this->games_moderated > 0);
		}
		
		echo '<form method="get" name="form" action="user_stats.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<input type="hidden" name="filter" value="">';
		
		$query = new DbQuery('SELECT DISTINCT c.id, c.name FROM players p, games g, clubs c WHERE p.game_id = g.id AND g.club_id = c.id AND p.user_id = ? ORDER BY c.name', $this->id);
		echo '<select name="club" onChange="document.form.submit()">';
		show_option(ALL_CLUBS, $club_id, get_label('All clubs'));
		while ($row = $query->next())
		{
			list($cid, $cname) = $row;
			show_option($cid, $club_id, $cname);
		}
		echo '</select>';
		
		echo '<input type="checkbox" name="civil" onClick="document.form.submit()" value=""';
		if (($roles & ROLE_CIVIL) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('civilian').' ';
		echo '<input type="checkbox" name="sheriff" onClick="document.form.submit()" value=""';
		if (($roles & ROLE_SHERIFF) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('sheriff').' ';
		echo '<input type="checkbox" name="mafia" onClick="document.form.submit()" value=""';
		if (($roles & ROLE_MAFIA) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('mafia').' ';
		echo '<input type="checkbox" name="don" onClick="document.form.submit()" value=""';
		if (($roles & ROLE_DON) != 0)
		{
			echo ' checked';
		}
		echo '> '.get_label('don').' ';
		echo '<input type="checkbox" name="moder" onClick="document.form.submit()" value=""';
		if ($moder_stats)
		{
			echo ' checked';
		}
		echo '> '.get_label('moderator'); 
		echo '</td></tr></table></form>';
		
		if (($roles & ROLE_ANY) != 0)
		{
			$stats = new PlayerStats($this->id, $club_id, $roles);
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Playing') . '</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Games played').':</td><td>' . $stats->games_played . '</td></tr>';
			if ($stats->games_played > 0)
			{
				echo '<tr><td class="dark" width="300">'.get_label('Games won').':</td><td>' . $stats->games_won . ' (' . number_format($stats->games_won*100.0/$stats->games_played, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Rating').':</td><td>' . get_label('[0] ([1] per game)', $stats->rating, number_format($stats->rating/$stats->games_played, 2)) . '</td></tr>';
				echo '<tr><td class="dark">'.get_label('Best player').':</td><td>' . $stats->best_player . '</td></tr>';
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
						case SURVIVED:
							echo '<tr><td class="dark" width="300">'.get_label('Survived').':</td><td>';
							break;
						case DAY_KILL:
							echo '<tr><td class="dark" width="300">'.get_label('Killed in day').' ' . $surviving->round . ':</td><td>';
							break;
						case NIGHT_KILL:
							echo '<tr><td class="dark" width="300">'.get_label('Killed in night').' ' . $surviving->round . ':</td><td>';
							break;
						case WARNINGS_KILL:
							echo '<tr><td class="dark" width="300">'.get_label('Killed by warnings in round').' ' . $surviving->round . ':</td><td>';
							break;
						case SUICIDE_KILL:
							echo '<tr><td class="dark" width="300">'.get_label('Left the game in round').' ' . $surviving->round . ':</td><td>';
							break;
						case KICK_OUT_KILL:
							echo '<tr><td class="dark" width="300">'.get_label('Kicked out in round').' ' . $surviving->round . ':</td><td>';
							break;
						default:
							echo '<tr><td class="dark" width="300">'.get_label('Round').' ' . $surviving->round . ':</td><td>';
							break;
					}
					echo $surviving->count . ' (' . number_format($surviving->count*100.0/$stats->games_played, 1) . '%)</td></tr>';
				}
				echo '</table></p>';
				
				if (($roles & (ROLE_MAFIA | ROLE_DON)) != 0)
				{
					$mafia_stats = new MafiaStats($this->id, $club_id, $roles);
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
				
				if (($roles & ROLE_SHERIFF) != 0)
				{
					$sheriff_stats = new SheriffStats($this->id, $club_id);
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
				
				if (($roles & ROLE_DON) != 0)
				{
					$don_stats = new DonStats($this->id, $club_id);
					if ($don_stats->games_played > 0)
					{
						echo '<p><table class="bordered light" width="100%">';
						echo '<tr class="th-short darker"><td colspan="2">' . get_label('Don stats') . '</td></tr>';
						echo '<tr><td class="dark" width="300">'.get_label('Sheriff found').':</td><td>' . $don_stats->sheriff_found . ' (' . number_format($don_stats->sheriff_found*100/$don_stats->games_played, 1) . '%)</td></tr>';
						echo '<tr><td class="dark" width="300">'.get_label('Sheriff arranged').':</td><td>' . $don_stats->sheriff_arranged . ' (' . number_format($don_stats->sheriff_arranged*100/$don_stats->games_played, 1) . '%)</td></tr>';
						echo '</table></p>';
					}
				}
				
				echo '<p><table class="bordered light" width="100%">';
				echo '<tr class="th-short darker"><td colspan="2">' . get_label('Miscellaneous') . '</td></tr>';
				echo '<tr><td class="dark" width="300">'.get_label('Warnings').':</td><td>' . $stats->warnings . ' (' . number_format($stats->warnings/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
				echo '<tr><td class="dark" width="300">'.get_label('Arranged by mafia').':</td><td>' . $stats->arranged . ' (' . number_format($stats->arranged/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
				if (($roles & (ROLE_CIVIL | ROLE_SHERIFF)) != 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('Checked by don').':</td><td>' . $stats->checked_by_don . ' (' . number_format($stats->checked_by_don/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
				}
				if ($roles != ROLE_SHERIFF)
				{
					echo '<tr><td class="dark" width="300">'.get_label('Checked by sheriff').':</td><td>' . $stats->checked_by_sheriff . ' (' . number_format($stats->checked_by_sheriff/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
				}
			}
			echo '</table></p>';
		}
		
		if ($moder_stats > 0)
		{
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Moderating') . '</td></tr>';
			
			$playing_count = 0;
			$civils_win_count = 0;
			$mafia_win_count = 0;
			if ($club_id > 0)
			{
				$query = new DbQuery('SELECT result, count(*) FROM games WHERE club_id = ? AND moderator_id = ? GROUP BY result', $club_id, $this->id);
			}
			else
			{
				$query = new DbQuery('SELECT result, count(*) FROM games WHERE moderator_id = ? GROUP BY result', $this->id);
			}
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
			
			echo '<tr><td class="dark" width="300">'.get_label('Games moderated').':</td><td>' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
			if ($civils_win_count + $mafia_win_count > 0)
			{
				echo '<tr><td class="dark">'.get_label('Mafia won in').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Civilians won in').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			}
			if ($playing_count > 0)
			{
				echo '<tr><td class="dark">'.get_label('Still playing').':</td><td>' . $playing_count . '</td></tr>';
			}
			
			if ($civils_win_count + $mafia_win_count > 0)
			{
				$query = new DbQuery('SELECT COUNT(DISTINCT p.user_id), SUM(p.warns) FROM players p, games g WHERE p.game_id = g.id AND g.moderator_id = ?', $this->id);
				if ($club_id > 0)
				{
					$query->add(' AND g.club_id = ?', $club_id);
				}
				
				list ($players_moderated, $gave_warnings) = $query->record(get_label('player'));
				echo '<tr><td class="dark">'.get_label('Moderated players').':</td><td>' . $players_moderated . '</td></tr>';
				echo '<tr><td class="dark">'.get_label('Gave warnings').':</td><td>' . get_label('[0] ([1] per game)', $gave_warnings, number_format($gave_warnings/($civils_win_count + $mafia_win_count), 2)) . '</td></tr>';
			}
			echo '</table></p>';
		}
	}
}

$page = new Page();
$page->run(get_label('[0] stats', get_label('User')), PERM_ALL);

?>