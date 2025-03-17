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
	
	$sounds = array();
	if (!is_null($club_id) && $club_id > 0)
	{
		$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id = ? ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			$sounds[] = $row;
		}
	}
	
	$global_sounds = array();
	$query = new DbQuery('SELECT id, name FROM sounds WHERE club_id IS NULL AND user_id IS NULL ORDER BY name');
	while ($row = $query->next())
	{
		$global_sounds[] = $row;
	}
	
	$prompt_sound = GAME_DEFAULT_PROMPT_SOUND;
	$end_sound = GAME_DEFAULT_END_SOUND;
	if (!is_null($club_id) && $club_id > 0)
	{
		list($ps, $es) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $club_id);
		if (!is_null($ps))
		{
			$prompt_sound = $ps;
		}
		if (is_null($es))
		{
			$end_sound = $es;
		}
	}
	
	$query = new DbQuery('SELECT prompt_sound_id, end_sound_id FROM game_settings WHERE user_id = ?', $_profile->user_id);
	if ($row = $query->next())
	{
		list($ps, $es) = $row;
		if (!is_null($ps))
		{
			$prompt_sound = $ps;
		}
		if (!is_null($es))
		{
			$end_sound = $es;
		}
	}
		
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td>'.get_label('Default 10 sec prompt sound').':</td><td><table class="transp" width="100%"><tr>';
	echo '<td width="32"><button class="icon" onclick="playPSound()" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button></td>';
	echo '<td><select id="form-prompt-sound">';
	show_option(GAME_NO_SOUND, $prompt_sound, '');
	foreach ($global_sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $prompt_sound, $name);
	}
	foreach ($sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $prompt_sound, $name);
	}
	echo '</select></td></tr></table></td></tr>';
	
	echo '<tr><td>'.get_label('Default end of speech sound').':</td><td><table class="transp" width="100%"><tr>';
	echo '<td width="32"><button class="icon" onclick="playESound()" title="' . get_label('Play sound') . '"><img src="images/resume.png" border="0"></button></td>';
	echo '<td><select id="form-end-sound">';
	show_option(GAME_NO_SOUND, $end_sound, '');
	foreach ($global_sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $end_sound, $name);
	}
	foreach ($sounds as $row)
	{
		list ($id, $name) = $row;
		show_option($id, $end_sound, $name);
	}
	echo '</select></td></tr></table></td></tr>';
	
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
		playSound($("#form-prompt-sound").val());
	}
	
	function playESound()
	{
		playSound($("#form-end-sound").val());
	}
	
	function commit(onSuccess)
	{
		var request =
		{
			op: 'set_def_sound'
			, prompt_sound_id: $("#form-prompt-sound").val()
			, end_sound_id: $("#form-end-sound").val()
			, confirm: ($('#form-confirm').attr('checked') ? 1 : 0)
		};
		json.post("api/ops/sound.php", request, onSuccess);
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