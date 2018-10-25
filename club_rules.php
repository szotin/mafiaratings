<?php

require_once 'include/club.php';

define('PAGE_SIZE', 20);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		list ($count) = Db::record(get_label('rules'), 'SELECT count(*) FROM club_rules r WHERE r.club_id = ?', $this->id);
		++$count;

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52"><a href ="javascript:mr.createRules(' . $this->id . ')" title="'.get_label('New rules').'">';
		echo '<img src="images/create.png" border="0"></a></td>';
		
		echo '<td>'.get_label('Rules name').'</td></tr>';
		
		echo '<tr><td><a href="#" onclick="mr.editRules(' . $this->id . ')" title="' . get_label('Edit [0] in [1]', get_label('[default]'), $this->name) . '"><img src="images/edit.png" border="0"></a>';
		echo '</td><td>' . get_label('[default]') . '</td></tr>';

		$query = new DbQuery('SELECT rules_id, name FROM club_rules r WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			list ($rules_id, $name) = $row;
			echo '<tr><td class="dark"><a href="#" onclick="mr.editRules(' . $this->id . ', ' . $rules_id . ')" title="' . get_label('Edit [0] in [1]', $name, $this->name) . '"><img src="images/edit.png" border="0"></a>';
			echo '<a href="#" onclick="mr.deleteRules(' . $this->id . ', ' . $rules_id . ', \'' . get_label('Are you sure you want to delete rules [0]?', $name) . '\')" title="' . get_label('Delete [0] in [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></a></td>';
			echo '<td>' . $name . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>