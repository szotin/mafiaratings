<?php

require_once 'include/club.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('Scoring systems in [0]', $this->name);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		list ($count) = Db::record(get_label('scoring system'), 'SELECT count(*) FROM scorings WHERE club_id = ?', $this->id);

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52"><a href="#" onclick="mr.createScoringSystem(' . $this->id . ')" title="'.get_label('New scoring system').'">';
		echo '<img src="images/create.png" border="0"></a></td>';
		
		echo '<td>'.get_label('Scoring system name').'</td></tr>';
		
		$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			echo '<tr><td class="dark"><a href ="javascript:mr.editScoringSystem(' . $id . ')" title="' . get_label('Edit [0] in [1]', $name, $this->name) . '"><img src="images/edit.png" border="0"></a>';
			echo ' <a href="#" onclick="mr.deleteScoringSystem(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0] in [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></a></td>';
			echo '<td>' . $name . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(NULL, UC_PERM_MANAGER);

?>