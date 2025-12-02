<?php 

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/game.php';
require_once 'include/updater.php';

define('COL_WIDTH', 80);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		
		echo '<table class="bordered light" width="100%">';
		$query = new DbQuery('SELECT name, filename FROM maintenance_scripts ORDER BY name');
		while ($row = $query->next())
		{
			list ($script_name, $scrtipt_filename) = $row;
			
			$script_batches = 0;
			$script_logs = false;
			$tasks = array();
			$query1 = new DbQuery('SELECT name, batches, runs, items, times, items_times, items_items FROM maintenance_tasks WHERE script_name = ? ORDER BY num', $script_name);
			while ($row1 = $query1->next())
			{
				$task = new stdClass();
				list ($task->name, $task->batches, $task->runs, $task->items, $task->times, $task->items_times, $task->items_items) = $row1;
				$task->average_item_time = Updater::averageItemTime($task->batches, $task->items, $task->times, $task->items_items, $task->items_times);
				$task->has_average_item_time = (abs($task->average_item_time) > 0.00001);
				$task->average_const_time = Updater::averageConstTime($task->batches, $task->items, $task->times, $task->items_items, $task->items_times);
				$task->has_average_const_time = (abs($task->average_const_time) > 0.00001);
				$task->log_filename = 'logs/'.$script_name.'.'.$task->name.'.log';
				$task->log_exists = file_exists($task->log_filename);
				$tasks[] = $task;
				$script_batches += $task->batches;
				$script_logs = $script_logs || $task->log_exists;
			}
			
			echo '<tr class="darker">';
			echo '<td width="112">';
			echo '<button class="icon" id="run"><img src="images/resume.png" title="Run '.$script_name.'" onclick="runScript(\''.$scrtipt_filename.'\')"></button>';
			echo '<button class="icon" onclick="deleteStats(\''.$script_name.'\')"'.($script_batches > 0 ? '' : ' disabled').'><img src="images/worst_move.png" title="Delete all statistics for '.$script_name.'"></button>';
			echo '<button class="icon" onclick="deleteLog(\''.$script_name.'\')"'.($script_logs ? '' : ' disabled').'><img src="images/delete.png" title="Delete all logs for '.$script_name.'"></button>';
			'</td>';
			echo '<td><b>'.$script_name.'</b></td>';
			echo '<td width="'.COL_WIDTH.'">Runs</td>';
			echo '<td width="'.COL_WIDTH.'">Batches</td>';
			echo '<td width="'.COL_WIDTH.'">Items</td>';
			echo '<td width="'.COL_WIDTH.'">Batches per run</td>';
			echo '<td width="'.COL_WIDTH.'">Items per batch</td>';
			echo '<td width="'.COL_WIDTH.'">1000 items execution time</td>';
			echo '<td width="'.COL_WIDTH.'">Batch overhead time</td>';
			echo '<td width="'.COL_WIDTH.'">Items per minute</td></tr>';
			foreach ($tasks as $task)
			{
				echo '<tr><td>';
				echo '<button class="icon" onclick="runScript(\''.$scrtipt_filename.'\', \''.$task->name.'\')"><img src="images/resume.png" title="Run '.$task->name.'"></button>';
				echo '<button class="icon" onclick="deleteStats(\''.$script_name.'\',\''.$task->name.'\')"'.($task->batches > 0? '' : ' disabled').'><img src="images/worst_move.png" title="Delete statistics"></button>';
				echo '<button class="icon" onclick="deleteLog(\''.$script_name.'\',\''.$task->name.'\')"'.($task->log_exists?'':' disabled').'><img src="images/delete.png" title="Delete log"></button>';
				echo '<button class="icon" onclick="viewLog(\''.$task->log_filename.'\')"'.($task->log_exists?'':' disabled').'><img src="images/log.png" title="View log"></button>';
				echo '</td>';
				
				echo '<td>'.$task->name.'</td>';
				echo '<td>'.$task->runs.'</td>';
				echo '<td>'.$task->batches.'</td>';
				echo '<td>'.$task->items.'</td>';
				echo '<td>'.($task->runs > 0 ? format_float($task->batches/$task->runs, 1) : '').'</td>';
				echo '<td>'.($task->batches > 0 ? format_float($task->items/$task->batches, 1) : '').'</td>';
				echo '<td>'.($task->has_average_item_time ? format_float($task->average_item_time * 1000, 1).'s' : '').'</td>';
				echo '<td>'.($task->has_average_const_time ? format_float($task->average_const_time, 2).'s' : '').'</td>';
				echo '<td>'.($task->has_average_item_time ? format_float(60/$task->average_item_time, 1) : '').'</td>';
				echo '</tr>';
			}
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function runScript(script, task)
		{
			let url = "form/run_script.php?script=" + script;
			if (task)
			{
				url += "&task=" + task;
			}
			dlg.form(url, refr, 400);
		}
		
		function deleteStats(script, task)
		{
			if (task)
				dlg.yesNo("Are you sure you want to delete " + script + "." + task + " statistics?", null, null, function() 
				{
					json.post("api/ops/repair.php",
					{
						op: "delete_task_stats"
						, script: script
						, task: task
					}, refr);
				});
			else
				dlg.yesNo("Are you sure you want to delete all " + script + " statistics?", null, null, function() 
				{
					json.post("api/ops/repair.php",
					{
						op: "delete_task_stats"
						, script: script
					}, refr);
				});
		}
		
		function viewLog(logFile)
		{
			window.location.assign(logFile);
		}
		
		function deleteLog(script, task)
		{
			if (task)
				dlg.yesNo("Are you sure you want to delete " + script + "." + task + " log file?", null, null, function() 
				{
					json.post("api/ops/repair.php",
					{
						op: "delete_log"
						, script: script
						, task: task
					}, refr);
				});
			else
				dlg.yesNo("Are you sure you want to delete all log files for " + script + "?", null, null, function() 
				{
					json.post("api/ops/repair.php",
					{
						op: "delete_log"
						, script: script
						, task: task
					}, refr);
				});
		}
<?php
	}
}

$page = new Page();
$page->run('Maintenance tasks');

?>
