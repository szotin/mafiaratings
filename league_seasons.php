<?php

require_once 'include/pages.php';
require_once 'include/league.php';
require_once 'include/languages.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends LeaguePageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
		
		list ($count) = Db::record(get_label('season'), 'SELECT count(*) FROM league_seasons WHERE league_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery('SELECT id, name, start_time, end_time FROM league_seasons WHERE league_id = ? ORDER BY start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, $this->id);
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createLeagueSeason(' . $this->id . ')" title="' . get_label('Create [0]', get_label('season')) . '"><img src="images/create.png" border="0"></button></th>';
		echo '<th>' . get_label('Name') . '</th><th width="150">' . get_label('Start') . '</th><th width="150">' . get_label('End') . '</th></tr>';
		while ($row = $query->next())
		{
			list ($id, $name, $start_time, $end_time) = $row;
			echo '<tr class="light">';
			if ($this->is_manager)
			{
				echo '<td width="56" valign="top" align="center">';
				echo '<button class="icon" onclick="mr.editLeagueSeason(' . $id . ')" title="' . get_label('Edit [0]', get_label('season')) . '"><img src="images/edit.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.deleteLeagueSeason(' . $id . ', \'' . get_label('Are you sure you want to delete the season?') . '\')" title="' . get_label('Delete [0]', get_label('season')) . '"><img src="images/delete.png" border="0"></button>';
				echo '</td>';
			}
			echo '<td>' . $name . '</td>';
			echo '<td>' . format_date('F d, Y', $start_time, $_profile->timezone) . '</td>';
			echo '<td>' . format_date('F d, Y', $end_time, $_profile->timezone) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Seasons'));

?>