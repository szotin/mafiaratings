<?php

require_once 'include/event.php';
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

class Page extends EventPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$days = array();
		$current_day = NULL;
		$current_table = NULL;
		$query = new DbQuery('SELECT day_num, table_num, part_num, url, status FROM event_broadcasts WHERE event_id = ? ORDER BY day_num, table_num, part_num', $this->id);
		while ($row = $query->next())
		{
			list ($day, $table, $part, $url, $status) = $row;
			// echo $day . ' ' . $table . ' ' . $part . '<br>';
			if (is_null($current_day) || $current_day->day != $day)
			{
				$current_day = new stdClass();
				$current_day->day = $day;
				$current_day->broadcast_count = 0;
				$current_day->tables = array();
				$current_table = NULL;
				$days[] = $current_day;
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
		
		if (count($days) == 0)
		{
			return;
		}
		
		echo '<table class="bordered light" width="100%">';
		if (count($days) > 1)
		{
			foreach ($days as $day)
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
		else if (count($days[0]->tables) > 1)
		{
			foreach ($days[0]->tables as $table)
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
		else if (count($days[0]->tables[0]->parts) > 1)
		{
			foreach ($days[0]->tables[0]->parts as $part)
			{
				echo '<tr><td><a href="' . $part->url . '" target="_blank">' . get_label('Part [0]', $part->part) . '</a></td><td width="80">' . broadcast_status_str($part->status) . '</td></tr>';
			}
		}
		else
		{
			echo '<tr><td><a href="' . $days[0]->tables[0]->parts[0]->url . '" target="_blank">' . get_label('Broadcast') . '</a></td><td width="80">' . broadcast_status_str($days[0]->tables[0]->parts[0]->status) . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>