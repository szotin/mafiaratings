<?php

require_once 'include/general_page_base.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		check_permissions(PERMISSION_ADMIN);
		
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id IS NULL AND user_id IS NULL ORDER BY id');
		
		echo '<audio id="snd" preload></audio>';
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createSound()" title="' . get_label('Create [0]', get_label('sound')) . '"><img src="images/create.png" border="0"></button></th>';
		echo '<th align="left">' . get_label('Name') . '</th></tr>';
		while ($row = $query->next())
		{
			list ($id, $name) = $row;
			if ($id == 1)
			{
				continue;
			}

			echo '<tr class="light">';
			echo '<td width="84" valign="top">';
			echo '<button class="icon" onclick="playSound(' . $id . ')" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editSound(' . $id . ')" title="' . get_label('Edit [0]', get_label('sound')) . '"><img src="images/edit.png" border="0"></button>';
			if ($id > 3)
			{
				echo '<button class="icon" onclick="mr.deleteSound(' . $id . ', \'' . get_label('Are you sure you want to delete the sound?') . '\')" title="' . get_label('Delete [0]', get_label('sound')) . '"><img src="images/delete.png" border="0"></button>';
			}
			echo '</td>';
			echo '<td>' . $name . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function playSound(id)
		{
			var sound = document.getElementById('snd').cloneNode(true);
			var time = new Date();
			sound.src = "sounds/" + id + ".mp3?" + time.getTime();
			sound.play();
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Sounds'));

?>