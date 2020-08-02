<?php

require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/languages.php';

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		
		$sounds = array();
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id = ? ORDER BY name', $this->id);
		while ($row = $query->next())
		{
			$sounds[] = $row;
		}
		
		$global_sounds = array();
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id IS NULL AND user_id IS NULL ORDER BY id');
		while ($row = $query->next())
		{
			$global_sounds[] = $row;
		}
		
		list($def_prompt_sound, $def_end_sound) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $this->id);
		if (is_null($def_prompt_sound))
		{
			$def_prompt_sound = 2;
		}
		if (is_null($def_end_sound))
		{
			$def_end_sound = 3;
		}
		
		echo '<p>';
		echo get_label('Default 10 sec prompt sound') . ': <select id="def-prompt" onchange="promptSoundChanged()">';
		foreach ($global_sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_prompt_sound, $name);
		}
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_prompt_sound, $name);
		}
		echo '</select>   ';
		
		echo get_label('Default end of speech sound') . ': <select id="def-end" onchange="endSoundChanged()">';
		foreach ($global_sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_end_sound, $name);
		}
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;
			show_option($id, $def_end_sound, $name);
		}
		echo '</select></p>';
		
		echo '<audio id="snd" preload></audio>';
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createSound(' . $this->id . ')" title="' . get_label('Create [0]', get_label('sound')) . '"><img src="images/create.png" border="0"></button></th>';
		echo '<th align="left">' . get_label('Name') . '</th></tr>';
		foreach ($sounds as $row)
		{
			list ($id, $name) = $row;
			echo '<tr class="light">';
			echo '<td width="84" valign="top" align="center">';
			echo '<button class="icon" onclick="playSound(' . $id . ')" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.editSound(' . $id . ')" title="' . get_label('Edit [0]', get_label('sound')) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.deleteSound(' . $id . ', \'' . get_label('Are you sure you want to delete the sound?') . '\')" title="' . get_label('Delete [0]', get_label('sound')) . '"><img src="images/delete.png" border="0"></button>';
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
		
		function promptSoundChanged()
		{
			json.post("api/ops/sound.php",
			{
				op: 'set_def_sound'
				, club_id: <?php echo $this->id; ?>
				, prompt_sound_id: $("#def-prompt").val()
			});
		}
		
		function endSoundChanged()
		{
			json.post("api/ops/sound.php",
			{
				op: 'set_def_sound'
				, club_id: <?php echo $this->id; ?>
				, end_sound_id: $("#def-end").val()
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Sounds'));

?>