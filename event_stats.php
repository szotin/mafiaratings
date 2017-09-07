<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/club.php';

class Page extends EventPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0] stats', $this->event->name);
	}
	
	protected function show_body()
	{
		echo '<table class="bordered" width="100%">';
		
		$playing_count = 0;
		$civils_win_count = 0;
		$mafia_win_count = 0;
		$query = new DbQuery('SELECT result, count(*) FROM games WHERE event_id = ? GROUP BY result', $this->event->id);
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
		
		echo '<tr><td width="200" class="dark">'.get_label('Games played').':</td><td class="light">' . ($civils_win_count + $mafia_win_count) . '</td></tr>';
		if ($civils_win_count + $mafia_win_count > 0)
		{
			echo '<tr><td class="dark">'.get_label('Mafia won in').':</td><td class="light">' . $mafia_win_count . ' (' . number_format($mafia_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Civilians won in').':</td><td class="light">' . $civils_win_count . ' (' . number_format($civils_win_count*100.0/($civils_win_count + $mafia_win_count), 1) . '%)</td></tr>';
		}
		if ($playing_count > 0)
		{
			echo '<tr><td class="dark">'.get_label('Still playing').'</td><td class="light">' . $playing_count . '</td></tr>';
		}
		
		if ($civils_win_count + $mafia_win_count > 0)
		{
			list($counter) = Db::record(get_label('event'), 'SELECT COUNT(DISTINCT p.user_id) FROM players p, games g WHERE p.game_id = g.id AND g.event_id = ?', $this->event->id);
			echo '<tr><td class="dark">'.get_label('People played').':</td><td class="light">' . $counter . '</td></tr>';
			
			list($counter) = Db::record(get_label('event'), 'SELECT COUNT(DISTINCT moderator_id) FROM games WHERE event_id = ?', $this->event->id);
			echo '<tr><td class="dark">'.get_label('People moderated').':</td><td class="light">' . $counter . '</td></tr>';
			
			list ($g_duration, $s_game, $l_game) =
				Db::record(
					get_label('event'), 
					'SELECT AVG(end_time - start_time), MIN(end_time - start_time), MAX(end_time - start_time) ' .
						'FROM games WHERE result > 0 AND result < 3 AND event_id = ?',
					$this->event->id);
			echo '<tr><td class="dark">'.get_label('Average game duration').':</td><td class="light">' . format_time($g_duration) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Shortest game').':</td><td class="light">' . format_time($s_game) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Longest game').':</td><td class="light">' . format_time($l_game) . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Event statistics'), PERM_ALL);

?>