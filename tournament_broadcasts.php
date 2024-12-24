<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';

function broadcast_status_str($status)
{
	switch ($status)
	{
		case BROADCAST_STATUS_NOT_STARTED:
			return get_label('Not started');
		case BROADCAST_STATUS_STARTED:
			return get_label('Running');
		case BROADCAST_STATUS_ENDED:
			return get_label('Finished');
	}
	return '';
}

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$events = array();
		$current_event = NULL;
		$current_day = NULL;
		$current_table = NULL;
		$query = new DbQuery('SELECT e.id, e.name, es.day_num, es.table_num, es.part_num, es.url, es.status FROM event_broadcasts es JOIN events e ON e.id = es.event_id WHERE e.tournament_id = ? ORDER BY e.start_time, e.start_time + e.duration, e.id, es.day_num, es.table_num, es.part_num', $this->id);
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $day, $table, $part, $url, $status) = $row;
			if (is_null($current_event) || $current_event->id != $event_id)
			{
				$current_event = new stdClass();
				$current_event->id = $event_id;
				$current_event->name = $event_name;
				$current_event->days = array();
				$current_event->broadcast_count = 0;
				$current_day = NULL;
				$current_table = NULL;
				$events[] = $current_event;
			}
			++$current_event->broadcast_count;
			
			if (is_null($current_day) || $current_day->day != $day)
			{
				$current_day = new stdClass();
				$current_day->day = $day;
				$current_day->broadcast_count = 0;
				$current_day->tables = array();
				$current_table = NULL;
				$current_event->days[] = $current_day;
			}
			++$current_day->broadcast_count;
			
			if (is_null($current_table) || $current_table->table != $table)
			{
				$current_table = new stdClass();
				$current_table->table = $table;
				$current_table->parts = array();
				$current_day->tables[] = $current_table;
			}
			
			$current_part = new stdClass();
			$current_part->part = $part;
			$current_part->url = $url;
			$current_part->status = $status;
			$current_table->parts[] = $current_part;
		}
		
		if (count($events) == 0)
		{
			return;
		}
		
		foreach ($events as $event)
		{
			echo '<p><h2>' . $event->name . '</h2></p>';
			echo '<p><table class="bordered light" width="100%">';
			if (count($event->days) > 1)
			{
				foreach ($event->days as $day)
				{
					echo '<tr><td class="darker th" colspan="2" align="center"><b>' . get_label('Day [0]', $day->day) . '</b></td></tr>';
					foreach ($day->tables as $table)
					{
						if (count($table->parts) > 1)
						{
							foreach ($table->parts as $part)
							{
								echo '<tr><td><a href="' . $part->url . '" target="_blank">' . get_label('Table [0] - part [1]', $table->table + 1, $part->part) . '</a></td><td width="80">' . broadcast_status_str($part->status) . '</td></tr>';
							}
						}
						else
						{
							echo '<tr><td><a href="' . $table->parts[0]->url . '" target="_blank">' . get_label('Table [0]', $table->table + 1) . '</a></td><td width="80">' . broadcast_status_str($table->parts[0]->status) . '</td></tr>';
						}
					}
				}
			}
			else if (count($event->days[0]->tables) > 1)
			{
				foreach ($event->days[0]->tables as $table)
				{
					if (count($table->parts) > 1)
					{
						foreach ($table->parts as $part)
						{
							echo '<tr><td><a href="' . $part->url . '" target="_blank">' . get_label('Table [0] - part [1]', $table->table + 1, $part->part) . '</a></td><td width="80">' . broadcast_status_str($part->status) . '</td></tr>';
						}
					}
					else
					{
						echo '<tr><td><a href="' . $table->parts[0]->url . '" target="_blank">' . get_label('Table [0]', $table->table + 1) . '</a></td><td width="80">' . broadcast_status_str($table->parts[0]->status) . '</td></tr>';
					}
				}
			}
			else if (count($event->days[0]->tables[0]->parts) > 1)
			{
				foreach ($event->days[0]->tables[0]->parts as $part)
				{
					echo '<tr><td><a href="' . $part->url . '" target="_blank">' . get_label('Part [0]', $part->part) . '</a></td><td width="80">' . broadcast_status_str($part->status) . '</td></tr>';
				}
			}
			else
			{
				echo '<tr><td><a href="' . $event->days[0]->tables[0]->parts[0]->url . '" target="_blank">' . get_label('Broadcast') . '</a></td><td width="80">' . broadcast_status_str($event->days[0]->tables[0]->parts[0]->status) . '</td></tr>';
			}
			echo '</table></p>';
		}
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>