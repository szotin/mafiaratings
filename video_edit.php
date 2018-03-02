<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/club.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('video')));
	
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}

	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('video')));
	}
	$video_id = (int)$_REQUEST['id'];
	
	list ($club_id, $event_id, $user_id, $game_id, $type, $lang, $time) = Db::record(get_label('video'), 'SELECT v.club_id, v.event_id, v.user_id, g.id, v.type, v.lang, v.video_time FROM videos v LEFT OUTER JOIN games g ON g.video_id = v.id WHERE v.id = ?', $video_id);
	if (!$_profile->is_manager($club_id) && $_profile->user_id != $user_id)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	$club = $_profile->clubs[$club_id];
	
	if ($game_id != NULL)
	{
		throw new Exc(get_label('This video [1] is attached to the game #[0]. It can not be edited.', $game_id, $video_id));
	}
	
	$langs = $club->langs;
	
	date_default_timezone_set($club->timezone);
	$date = date('m/d/Y', $time);
	$hour = date('G', $time);
	$minute = 0;
	
	// echo '<script src="js/datepicker-' . $_lang_code . '.js"></script>';
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="40">';
	show_club_pic($club_id, $club->name, $club->club_flags, ICONS_DIR, 40, 40);
	echo '</td><td align="center"><b>' . $club->name;
	echo '</b></td></tr></table></td></tr>';
	echo '<tr><td width="140">'.get_label('Video type').':</td><td><select id="form-type">';
	show_option(VIDEO_TYPE_LEARNING, $type, get_label('Learning video'));
	show_option(VIDEO_TYPE_GAME, $type, get_label('Game'));
	echo '</select></td></tr>';
	if ($event_id != NULL)
	{
	}
	else
	{
		echo '<tr><td>' . get_label('Date') . ':</td><td><input type="text" id="form-date" value="' . $date . '"></td></tr>';
		echo '<tr><td>' . get_label('Time') . ':</td><td><input id="form-hour" value="' . $hour . '"> : <input id="form-minute" value="' . $minute . '"></td></tr>';
?>
		<script>
		$('#form-date').datepicker();
		$( "#form-hour" ).spinner({ step: 1, max: 23, min: 0, page: 4 });
		$( "#form-minute" ).spinner({ step: 1, max: 59, min: 0, page: 10 });
		</script>
<?php
	}
	
	if (is_valid_lang($langs))
	{
		echo '<input type="hidden" id="form-lang" value="' . $lang . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Language').':</td><td>';
		show_lang_select('form-lang', $lang, $langs);
		echo '</td></tr>';
	}
	echo '</table>';

	//	$('#datepicker').datepicker($.datepicker.regional[" echo $_lang_code; "]);
?>
	<script>
	function commit(onSuccess)
	{
		json.post("video_ops.php",
		{
			vtype: $("#form-type").val(),
			lang: $("#form-lang").val(),
			time: $("#form-date").val() + " " + $("#form-hour").val() + ":" + $("#form-minute").val(),
			edit: <?php echo $video_id; ?>
		},
		onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>