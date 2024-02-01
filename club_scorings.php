<?php

require_once 'include/club.php';

define('VIEW_SCORINGS', 0);
define('VIEW_NORMALIZERS', 1);
define('VIEW_COUNT', 2);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		
		$this->view = VIEW_SCORINGS;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
		}
		if ($this->view < VIEW_SCORINGS || $this->view >= VIEW_COUNT)
		{
			$this->view = VIEW_SCORINGS;
		}
		
		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_SCORINGS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_SCORINGS . '})">' . get_label('Tournament scoring systems') . '</button>';
		echo '<button ' . ($this->view == VIEW_NORMALIZERS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_NORMALIZERS . '})">' . get_label('Tournament scoring normalizers') . '</button>';
		echo '</div>';
			
			
		switch ($this->view)
		{
			case VIEW_SCORINGS:
				echo '<div class="tabcontent">';
				echo '<table class="bordered light" width="100%">';
				echo '<tr class="darker"><td width="48"><a href="#" onclick="mr.createScoringSystem(' . $this->id . ')" title="'.get_label('New scoring system').'">';
				echo '<img src="images/create.png" border="0"></a></td>';
				
				echo '<td>' . get_label('Scoring system name') . '</td></tr>';
				
				$query = new DbQuery('SELECT id, name, version FROM scorings WHERE club_id = ? ORDER BY name', $this->id);
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
				break;
			
			case VIEW_NORMALIZERS:
				echo '<div class="tabcontent">';
				echo '<table class="bordered light" width="100%">';
				echo '<tr class="darker"><td width="48"><a href="#" onclick="mr.createNormalizer(' . $this->id . ')" title="'.get_label('New scoring normalizer').'">';
				echo '<img src="images/create.png" border="0"></a></td>';
				
				echo '<td>' . get_label('Scoring normalizer name') . '</td></tr>';
				
				$query = new DbQuery('SELECT id, name, version FROM normalizers WHERE club_id = ? ORDER BY name', $this->id);
				while ($row = $query->next())
				{
					list ($id, $name, $version) = $row;
					echo '<tr><td class="dark" align="center">';
					echo '<a onclick="mr.deleteNormalizer(' . $id . ', \'' . get_label('Are you sure you want to delete [0]?', $name) . '\')" title="' . get_label('Delete [0]', $name) . '"><img src="images/delete.png" border="0"></a>';
					echo '<a onclick="mr.editNormalizer(' . $id . ')" title="' . get_label('Edit [0]', $name) . '"><img src="images/edit.png" border="0"></a>';
					// todo: implement form/normalizer_show.php
//					echo '</td><td><a href="javascript:showNormalizer(' . $id . ', ' . $version . ')">' . $name . '</a></td></tr>';
					echo '</td><td>' . $name . '</td></tr>';
				}
				echo '</table>';
				echo '</div>';
				break;
		}
	}
	
	protected function js()
	{
		switch ($this->view)
		{
			case VIEW_SCORINGS:
?>
				function showScoring(id, version)
				{
					dlg.infoForm("form/scoring_show.php?id=" + id + "&version=" + version);
				}
<?php
				break;
			
			case VIEW_NORMALIZERS:
?>
				function showNormalizer(id, version)
				{
					dlg.infoForm("form/normalizer_show.php?id=" + id + "&version=" + version);
				}
<?php
				break;
		}
	}
}

$page = new Page();
$page->run(get_label('Scoring Systems'));

?>