<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/address.php';

class Page extends AddressPageBase
{
	protected function show_body()
	{
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$tie_count = 0;
		$query = new DbQuery('SELECT g.result, count(*) FROM games g JOIN events e ON g.event_id = e.id WHERE e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0 GROUP BY g.result', $this->id);
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
		
		list($events_count) = Db::record(get_label('event'), 'SELECT count(*) FROM events e WHERE (e.flags & ' . (EVENT_FLAG_CANCELED | EVENT_FLAG_HIDDEN_AFTER) . ') = 0 AND start_time < UNIX_TIMESTAMP() AND e.address_id = ?', $this->id);
	
		if ($games_count > 0)
		{
			// stats
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Stats') . '</td></tr>';
			echo '<tr><td class="dark" width="200">'.get_label('Events held').':</td><td>' . $events_count . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Games played').':</td><td>' . $games_count . '</td></tr>';
			if ($civils_win_count + $mafia_win_count > 0)
			{
				echo '<tr><td class="dark">'.get_label('Mafia wins').':</td><td>' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/$games_count, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Town wins').':</td><td>' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/$games_count, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Ties').':</td><td>' . $tie_count . ' (' . number_format($tie_count*100.0/$games_count, 1) . '%)</td></tr>';
			}
			
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p JOIN games g ON p.game_id = g.id JOIN events e ON g.event_id = e.id WHERE e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
			echo '<tr><td class="dark">'.get_label('People played').':</td><td>' . $counter . '</td></tr>';
			
			list ($counter) = Db::record(get_label('game'), 'SELECT COUNT(DISTINCT g.moderator_id) FROM games g JOIN events e ON g.event_id = e.id WHERE e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
			echo '<tr><td class="dark">'.get_label('Referees').':</td><td>' . $counter . '</td></tr>';
			
			list ($a_game, $s_game, $l_game) = Db::record(get_label('game'), 'SELECT AVG(g.end_time - g.start_time), MIN(g.end_time - g.start_time), MAX(g.end_time - g.start_time) FROM games g JOIN events e ON g.event_id = e.id WHERE g.end_time > g.start_time + 900 AND g.end_time < g.start_time + 20000 AND e.address_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
			echo '<tr><td class="dark">'.get_label('Average game duration').':</td><td>' . format_time($a_game) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Shortest game').':</td><td>' . format_time($s_game) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Longest game').':</td><td>' . format_time($l_game) . '</td></tr>';
			echo '</table></p>';
		}
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>