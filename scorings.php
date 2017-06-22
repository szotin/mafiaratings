<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define("PAGE_SIZE",20);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang_code, $_page;
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52"><a href="#" onclick="mr.createScoringSystem(-1)" title="'.get_label('New scoring system').'">';
		echo '<img src="images/create.png" border="0"></a></td>';
		
		echo '<td>'.get_label('Scoring system name').'</td></tr>';
		
		$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id IS NULL ORDER BY name');
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			echo '<tr><td class="dark"><a href ="javascript:mr.editScoringSystem(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
			if (!empty($name))
			{
				echo ' <a href="#" onclick="mr.deleteScoringSystem(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
				echo '</td><td>' . $name . '</td></tr>';
			}
			else
			{
				echo '</td><td>' . get_label('[default]') . '</td></tr>';
			}
		}
		echo '</table>';
		
		// list ($count) = Db::record(get_label('city'), 'SELECT count(*) FROM cities i ', $condition);
		// show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Scoring systems'), U_PERM_ADMIN);

?>