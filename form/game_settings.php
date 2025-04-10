<?php

require_once '../include/session.php';
require_once '../include/game.php';

initiate_session();

try
{
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}
	else
	{
		$club_id = $_profile->user_club_id;
	}
	
	$club_prompt_sound = GAME_DEFAULT_PROMPT_SOUND;
	$club_end_sound = GAME_DEFAULT_END_SOUND;
	if (!is_null($club_id) && $club_id > 0)
	{
		list($ps, $es) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $club_id);
		if (!is_null($ps))
		{
			$club_prompt_sound = $ps;
		}
		if (!is_null($es))
		{
			$club_end_sound = $es;
		}
	}
	
	$club_prompt_sound_name = '';
	$club_end_sound_name = '';
	$sounds = array();
	if (!is_null($club_id) && $club_id > 0)
	{
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id = ? OR (club_id IS NULL AND user_id IS NULL) ORDER BY name', $club_id);
	}
	else
	{
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id IS NULL AND user_id IS NULL ORDER BY name');
	}
	while ($row = $query->next())
	{
		$sounds[] = $row;
		if ($row[0] == $club_prompt_sound)
		{
			$club_prompt_sound_name = '(' . $row[1] . ')';
		}
		if ($row[0] == $club_end_sound)
		{
			$club_end_sound_name = '(' . $row[1] . ')';
		}
	}
	
	$prompt_sound = 0;
	$end_sound = 0;
	$flags = 0;
	$query = new DbQuery('SELECT prompt_sound_id, end_sound_id, flags FROM game_settings WHERE user_id = ?', $_profile->user_id);
	if ($row = $query->next())
	{
		list($prompt_sound, $end_sound, $flags) = $row;
	}
		
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td>'.get_label('10 sec prompt sound').':</td><td><table class="transp" width="100%"><tr>';
	echo '<td width="32"><button class="icon" onclick="playPSound()" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button></td>';
	echo '<td><select id="form-prompt-sound">';
	show_option(GAME_NO_SOUND, $prompt_sound, '');
	show_option(0, $prompt_sound, get_label('default [0]', $club_prompt_sound_name));
	foreach ($sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $prompt_sound, $name);
	}
	echo '</select></td></tr></table></td></tr>';
	
	echo '<tr><td>'.get_label('End of speech sound').':</td><td><table class="transp" width="100%"><tr>';
	echo '<td width="32"><button class="icon" onclick="playESound()" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button></td>';
	echo '<td><select id="form-end-sound">';
	show_option(GAME_NO_SOUND, $end_sound, '');
	show_option(0, $end_sound, get_label('default [0]', $club_end_sound_name));
	foreach ($sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $end_sound, $name);
	}
	echo '</select></td></tr></table></td></tr>';
	
	echo '<tr><td colspan="2">';
	echo '<input type="checkbox" id="form-start-timer"' . (($flags & GAME_SETTINGS_START_TIMER) ? ' checked' : '') . '> ' . get_label('start timer automaticaly');
	echo '<br><input type="checkbox" id="form-change-roles"' . (($flags & GAME_SETTINGS_CHANGE_ROLES_IN_ARRANGEMENT) ? ' checked' : '') . '> ' . get_label('roles can be changed during arrangement');
	echo '<br><input type="checkbox" id="form-no-blink"' . (($flags & GAME_SETTINGS_NO_BLINKING) ? ' checked' : '') . '> ' . get_label('timer is not blinking in the end');
	echo '<br><input type="checkbox" id="form-rand-seating"' . (($flags & GAME_SETTINGS_RANDOMIZE_SEATING) ? ' checked' : '') . '> ' . get_label('ability to randomize seating before the game');
	echo '</td></tr>';
	
	echo '</table>';
	echo '<audio id="snd" preload></audio>';
	
?>
	<script>
	function playSound(id)
	{
		if (id != <?php echo GAME_NO_SOUND; ?>)
		{
			var sound = document.getElementById('snd').cloneNode(true);
			var time = new Date();
			sound.src = "sounds/" + id + ".mp3?" + time.getTime();
			sound.play();
		}
	}
	
	function playPSound()
	{
		let id = $("#form-prompt-sound").val();
		if (id <= 0)
			id = <?php echo $club_prompt_sound; ?>;
		playSound(id);
	}
	
	function playESound()
	{
		let id = $("#form-end-sound").val();
		if (id <= 0)
			id = <?php echo $club_end_sound; ?>;
		playSound(id);
	}
	
	function commit(onSuccess)
	{
		let flags = 0;
		if ($("#form-start-timer").attr('checked'))
			flags |= <?php echo GAME_SETTINGS_START_TIMER; ?>;
		if ($("#form-change-roles").attr('checked'))
			flags |= <?php echo GAME_SETTINGS_CHANGE_ROLES_IN_ARRANGEMENT; ?>;
		if ($("#form-no-blink").attr('checked'))
			flags |= <?php echo GAME_SETTINGS_NO_BLINKING; ?>;
		if ($("#form-rand-seating").attr('checked'))
			flags |= <?php echo GAME_SETTINGS_RANDOMIZE_SEATING; ?>;
		
		let request =
		{
			op: 'settings'
			, prompt_sound_id: $("#form-prompt-sound").val()
			, end_sound_id: $("#form-end-sound").val()
			, flags: flags
		};
		json.post("api/ops/game.php", request, onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>