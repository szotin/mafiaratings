<?php

require_once 'include/page_base.php';
require_once 'include/pages.php';

define('PAGE_SIZE', 20);

class Page extends PageBase
{
	protected function prepare()
	{
		parent::prepare();
	}
	
	protected function show_body()
	{
		global $_profile;
	
		$query = new DbQuery('SELECT s.id, c.name, s.submit_time FROM changelists s JOIN clubs c ON s.club_id = c.id WHERE s.user_id = ? ORDER BY s.submit_time DESC', $_profile->user_id);
	
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="28">&nbsp;</td><td width="180">'.get_label('Time').'</td><td>'.get_label('Club').'</td></tr>';
		
		while ($row = $query->next())
		{
			list($id, $club_name, $time) = $row;
			
			echo '<tr><td class="dark">';
/*			echo '<button class="icon" onclick="declineSubmit(' . $id . ', \'' . get_label('Are you sure you want to decline the submit?') . '\')" title="' . get_label('Decline submit') . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="acceptSubmit(' . $id . ')" title="' . get_label('Accept submit') . '"><img src="images/accept.png" border="0"></button>';*/
			echo '<button class="icon" onclick="mr.viewChangelist(' . $id . ')" title="' . get_label('View details') . '"><img src="images/details.png" border="0"></button>';
			echo '</td>';
			echo '<td>' . format_date('l, F d, Y', $time, $_profile->timezone) . '</td>';
			echo '<td>' . $club_name . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Changelists'), UC_PERM_MODER | UC_PERM_MANAGER);

?>