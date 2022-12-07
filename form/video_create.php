<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/club.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Add [0]', get_label('video')));
	
	//throw new Exc(formatted_json($_REQUEST));
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list($club_id, $event_name, $event_flags, $timestamp, $tournament_id, $tournament_name, $tournament_flags) = Db::record(get_label('event'), 'SELECT e.club_id, e.name, e.flags, e.start_time, t.id, t.name, t.flags FROM events e LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE e.id = ?', $event_id);
		if (is_null($tournament_name))
		{
			$title = $event_name;
		}
		else
		{
			$title = $tournament_name . ': ' . $event_name;
		}
		check_permissions(PERMISSION_CLUB_MEMBER | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		$event_id = NULL;
		list($club_id, $timestamp, $tournament_name, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.start_time, t.name, t.flags FROM tournaments t WHERE t.id = ?', $tournament_id);
		$title = $tournament_name;
		check_permissions(PERMISSION_CLUB_MEMBER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id);
	}
	else if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
		$tournament_id = $event_id = NULL;
		$timestamp = time();
		check_permissions(PERMISSION_CLUB_MEMBER, $club_id);
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	
	if (isset($_profile->clubs[$club_id]))
	{
		$club = $_profile->clubs[$club_id];
	}
	else
	{
		$club = new stdClass();
		list($club->name, $club->langs, $club->club_flags, $club->timezone) = Db::record(get_label('club'), 'SELECT c.name, c.langs, c.flags, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
	}
	
	if (!isset($title))
	{
		$title = $club->name;
	}
	
	$langs = $club->langs;
	$lang = $_lang;
	if (($langs & $lang) == 0)
	{
		$lang = get_next_lang(LANG_NO);
	}
	
	$vtype = VIDEO_TYPE_LEARNING;
	if (isset($_REQUEST['vtype']))
	{
		$t = (int)$_REQUEST['vtype'];
		if ($t >= VIDEO_TYPE_MIN && $t <= VIDEO_TYPE_MAX)
		{
			$vtype = $t;
		}
	}

	$datetime = timestamp_to_string($timestamp, $club->timezone, true);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr>';
	
	echo '<td width="40">';
	$pic = new Picture(CLUB_PICTURE);
	$pic->set($club_id, $club->name, $club->club_flags);
	$pic->show(ICONS_DIR, false, 40);
	echo '</td>';
	if ($tournament_id != NULL)
	{
		echo '<td width="40">';
		$pic = new Picture(TOURNAMENT_PICTURE);
		$pic->set($tournament_id, $tournament_name, $tournament_flags);
		$pic->show(ICONS_DIR, false, 40);
		echo '</td>';
	}
	if ($event_id != NULL)
	{
		echo '<td width="40">';
		$pic = new Picture(EVENT_PICTURE);
		$pic->set($event_id, $event_name, $event_flags);
		$pic->show(ICONS_DIR, false, 40);
		echo '</td>';
	}
	
	echo '<td align="center"><b>' . $title;
	echo '</b></td></tr></table></td></tr>';
	echo '<tr><td width="200">' . get_label('Youtube link') . ':</td><td><input id="form-video" size="60"></td></tr>';
	echo '<tr><td valign="top">' . get_label('In this video') . ':</td><td>';
	echo '<input type="radio" name="form-type" onclick="setType(' . VIDEO_TYPE_GAME . ')"' . ($vtype == VIDEO_TYPE_GAME ? ' checked' : '') . '> ' . get_label('Game') . '<br>';
	echo '<input type="radio" name="form-type" onclick="setType(' . VIDEO_TYPE_LEARNING . ')"' . ($vtype == VIDEO_TYPE_LEARNING ? ' checked' : '') . '> ' . get_label('Lecture') . '<br>';
	echo '<input type="radio" name="form-type" onclick="setType(' . VIDEO_TYPE_AWARD . ')"' . ($vtype == VIDEO_TYPE_AWARD ? ' checked' : '') . '> ' . get_label('Award ceremony') . '<br>';
	echo '<input type="radio" name="form-type" onclick="setType(' . VIDEO_TYPE_PARTY . ')"' . ($vtype == VIDEO_TYPE_PARTY ? ' checked' : '') . '> ' . get_label('Party') . '<br>';
	echo '<input type="radio" name="form-type" onclick="setType(' . VIDEO_TYPE_CUSTOM . ')"' . ($vtype == VIDEO_TYPE_CUSTOM ? ' checked' : '') . '> ' . get_label('Other');
	echo '</td></tr>';
	if (isset($event_id))
	{
		echo '<input type="hidden" id="form-datetime" value="' . $datetime . '">';
	}
	else
	{
		echo '<tr><td>' . get_label('Date') . ':</td><td><input type="datetime-local" id="form-datetime" value="' . $datetime . '"></td></tr>';
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

?>
	<script>
	var videoType = <?php echo VIDEO_TYPE_LEARNING; ?>;
	function setType(vType)
	{
		videoType = vType;
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/video.php",
		{
			op: "create",
<?php
			if (!is_null($event_id))
			{
				echo 'event_id: ' . $event_id . ",\n";
			}
			if (!is_null($tournament_id))
			{
				echo 'tournament_id: ' . $tournament_id . ",\n";
			}
			if (!is_null($club_id))
			{
				echo 'club_id: ' . $club_id . ",\n";
			}
?>
			video: $("#form-video").val(),
			vtype: videoType,
			lang: $("#form-lang").val(),
			time: $("#form-datetime").val()
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
	echo '<error=' . $e->getMessage() . '>';
}

?>