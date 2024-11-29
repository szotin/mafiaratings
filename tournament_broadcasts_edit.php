<?php

require_once 'include/tournament.php';

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id);
		
		$query = new DbQuery('SELECT id, name FROM events WHERE tournament_id = ? ORDER BY start_time, start_time + duration, id', $this->id);
		while ($row = $query->next())
		{
			list ($event_id, $event_name) = $row;
			echo '<p><h2>' . $event_name . '</h2></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="58"><button class="icon" onclick="mr.eventCreateBroadcast(' . $event_id . ')" title="' . get_label('Create broadcast for [0]', $event_name) . '"><img src="images/create.png"></button></td>';
			echo '<td width="50" align="center">' . get_label('Day') . '</td><td width="50" align="center">' . get_label('Table') . '</td><td width="50" align="center">' . get_label('Part') . '</td><td>' . get_label('URL') . '</td></tr>';
			
			$query1 = new DbQuery('SELECT day_num, table_num, part_num, url FROM event_broadcasts WHERE event_id = ? ORDER BY day_num, table_num, part_num', $event_id);
			while ($row1 = $query1->next())
			{
				list ($day, $table, $part, $url) = $row1;
				echo '<tr><td><button class="icon" onclick="mr.eventDeleteBroadcast(' . $event_id . ',' . $day . ',' . $table . ',' . $part . ', \'' . get_label('Are you sure you want to remove the broadcast?') . '\')"><img src="images/delete.png"></button>';
				echo '<button class="icon" onclick="mr.eventEditBroadcast(' . $event_id . ',' . $day . ',' . $table . ',' . $part . ')"><img src="images/edit.png"></button></td>';
				
				echo '<td align="center">' . $day . '</td>';
				echo '<td align="center">' . chr(65 + $table) . '</td>';
				echo '<td align="center">' . $part . '</td>';
				echo '<td><a href="' . $url . '" target="_blank">' . $url . '</a></td></tr>';
			}
			echo '</table></p>';
		}
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>