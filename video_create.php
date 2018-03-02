<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/club.php';

initiate_session();

try
{
	dialog_title(get_label('Add [0]', get_label('video')));
	
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}

	if (isset($_REQUEST['club']))
	{
		$club_id = (int)$_REQUEST['club'];
		$timestamp = time();
	}
	else if (isset($_REQUEST['event']))
	{
		$event_id = (int)$_REQUEST['event'];
		list($club_id, $timestamp) = Db::record(get_label('event'), 'SELECT club_id, start_time FROM events WHERE id = ?', $event_id);
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	if (!isset($_profile->clubs[$club_id]))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	$club = $_profile->clubs[$club_id];
	
	$langs = $club->langs;
	$lang = $_profile->user_def_lang;
	if (($langs & $lang) == 0)
	{
		$lang = get_next_lang(LANG_NO);
	}
	
	$vtype = VIDEO_TYPE_LEARNING;
	if (isset($_REQUEST['vtype']))
	{
		$vtype = (int)$_REQUEST['vtype'];
	}

	date_default_timezone_set($club->timezone);
	$date = date('m/d/Y', $timestamp);
	$hour = date('G', $timestamp);
	$minute = 0; //date('i', $timestamp);
	
	// echo '<script src="js/datepicker-' . $_lang_code . '.js"></script>';
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="40">';
	show_club_pic($club_id, $club->name, $club->club_flags, ICONS_DIR, 40, 40);
	echo '</td><td align="center"><b>' . $club->name;
	echo '</b></td></tr></table></td></tr>';
	echo '<tr><td width="140">'.get_label('Youtube link').':</td><td><input id="form-video" size="65"></td></tr>';
	echo '<tr><td>'.get_label('Video type').':</td><td><select id="form-type">';
	show_option(VIDEO_TYPE_LEARNING, $vtype, get_label('Learning video'));
	show_option(VIDEO_TYPE_GAME, $vtype, get_label('Game'));
	echo '</select></td></tr>';
	if (isset($event_id))
	{
		echo '<input type="hidden" id="form-date" value="' . $date . '">';
		echo '<input type="hidden" id="form-hour" value="' . $hour . '">';
		echo '<input type="hidden" id="form-minute" value="' . $minute . '">';
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
<?php
			if (isset($event_id))
			{
				echo 'event_id: ' . $event_id . ",\n";
			}
			else if (isset($club_id))
			{
				echo 'club_id: ' . $club_id . ",\n";
			}
?>
			video: $("#form-video").val(),
			vtype: $("#form-type").val(),
			lang: $("#form-lang").val(),
			time: $("#form-date").val() + " " + $("#form-hour").val() + ":" + $("#form-minute").val(),
			create: ""
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