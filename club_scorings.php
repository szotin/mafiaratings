<?php

require_once 'include/club.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_lang_code, $_page;
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="48" align="center"><a href="#" onclick="mr.createScoringSystem(' . $this->id . ')" title="'.get_label('New scoring system').'">';
		echo '<img src="images/create.png" border="0"></a></td>';
		
		echo '<td>' . get_label('Scoring system name') . '</td></tr>';
		
		$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			echo '<tr><td class="dark" align="center">';
			echo '<a onclick="mr.deleteScoringSystem(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
			echo '<a onclick="mr.editScoringSystem(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			echo '</td><td><a href="scoring.php?bck=1&id=' . $id . '">' . $name . '</a></td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Scoring Systems'), USER_CLUB_PERM_MANAGER);

?>