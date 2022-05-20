<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_lang_code, $_page;
		
		check_permissions(PERMISSION_ADMIN);
		
		$norm = isset($_REQUEST['norm']);
		
		echo '<div class="tab">';
		echo '<button ' . ($norm ? '' : 'class="active" ') . 'onclick="goTo(\'scorings.php\')">' . get_label('Scoring systems') . '</button>';
		echo '<button ' . (!$norm ? '' : 'class="active" ') . 'onclick="goTo(\'scorings.php?norm\')">' . get_label('Scoring normalizers') . '</button>';
		echo '</div>';
			
		if ($norm)
		{
			echo '<div class="tabcontent">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td width="48" align="center"><a href="#" onclick="mr.createNormalizer(-1)" title="'.get_label('New scoring normalizer').'">';
			echo '<img src="images/create.png" border="0"></a></td>';
			
			echo '<td>' . get_label('Scoring normalizer name') . '</td></tr>';
			
			$query = new DbQuery('SELECT id, name, version FROM normalizers WHERE club_id IS NULL ORDER BY name');
			while ($row = $query->next())
			{
				list ($id, $name, $version) = $row;
				echo '<tr><td class="dark" align="center">';
				echo '<a onclick="mr.deleteNormalizer(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
				echo '<a onclick="mr.editNormalizer(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
				echo '</td><td><a href="javascript:showNormalizer(' . $id . ', ' . $version . ')">' . $name . '</a></td></tr>';
			}
			echo '</table>';
			echo '</div>';
		}
		else
		{
			echo '<div class="tabcontent">';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><td width="48" align="center"><a href="#" onclick="mr.createScoringSystem(-1)" title="'.get_label('New scoring system').'">';
			echo '<img src="images/create.png" border="0"></a></td>';
			
			echo '<td>' . get_label('Scoring system name') . '</td></tr>';
			
			$query = new DbQuery('SELECT id, name, version FROM scorings WHERE club_id IS NULL ORDER BY name');
			while ($row = $query->next())
			{
				list ($id, $name, $version) = $row;
				echo '<tr><td class="dark" align="center">';
				echo '<a onclick="mr.deleteScoringSystem(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
				echo '<a onclick="mr.editScoringSystem(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
				echo '</td><td><a href="javascript:showScoring(' . $id . ', ' . $version . ')">' . $name . '</a></td></tr>';
			}
			echo '</table>';
			echo '</div>';
		}
	}
	
	protected function js()
	{
		if (isset($_REQUEST['norm']))
		{
?>
			function showNormalizer(id, version)
			{
				dlg.infoForm("form/normalizer_show.php?id=" + id + "&version=" + version);
			}
<?php
		}
		else
		{
?>
			function showScoring(id, version)
			{
				dlg.infoForm("form/scoring_show.php?id=" + id + "&version=" + version);
			}
<?php
		}
	}
}

$page = new Page();
$page->run(get_label('Scoring systems'));

?>