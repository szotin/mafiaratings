<?php

require_once 'include/event.php';
require_once 'include/pages.php';

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id, $this->tournament_id);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="58"><button class="icon" onclick="mr.eventCreateBroadcast(' . $this->id . ')" title="' . get_label('Create broadcast for [0]', $this->name) . '"><img src="images/create.png"></button></td>';
		echo '<td width="50" align="center">' . get_label('Day') . '</td><td width="50" align="center">' . get_label('Table') . '</td><td width="50" align="center">' . get_label('Part') . '</td><td>' . get_label('URL') . '</td></tr>';
		
		$query = new DbQuery('SELECT day_num, table_num, part_num, url FROM event_broadcasts WHERE event_id = ? ORDER BY day_num, table_num, part_num', $this->id);
		while ($row = $query->next())
		{
			list ($day, $table, $part, $url) = $row;
			echo '<tr><td><button class="icon" onclick="mr.eventDeleteBroadcast(' . $this->id . ',' . $day . ',' . $table . ',' . $part . ', \'' . get_label('Are you sure you want to remove the broadcast?') . '\')"><img src="images/delete.png"></button>';
			echo '<button class="icon" onclick="mr.eventEditBroadcast(' . $this->id . ',' . $day . ',' . $table . ',' . $part . ')"><img src="images/edit.png"></button></td>';
			
			echo '<td align="center">' . $day . '</td>';
			echo '<td align="center">' . ($table + 1) . '</td>';
			echo '<td align="center">' . $part . '</td>';
			echo '<td><a href="' . $url . '" target="_blank">' . $url . '</a></td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Edit broadcasts'));

?>