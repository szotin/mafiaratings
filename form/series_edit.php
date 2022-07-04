<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/series.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Edit [0]', get_label('sеriеs')));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('sеriеs')));
	}
	$series_id = (int)$_REQUEST['id'];
	$timezone = get_timezone();	
	
	list ($league_id, $name, $start_time, $duration, $langs, $notes, $flags, $league_langs) = 
		Db::record(get_label('sеriеs'), 
			'SELECT s.league_id, s.name, s.start_time, s.duration, s.langs, s.notes, s.flags, l.langs FROM series s' . 
			' JOIN leagues l ON l.id = s.league_id' .
			' WHERE s.id = ?', $series_id);
	check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $league_id, $series_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Series name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="12" width="120">';
	start_upload_logo_button($series_id);
	echo get_label('Change logo') . '<br>';
	$series_pic = new Picture(SERIES_PICTURE);
	$series_pic->set($series_id, $name, $flags);
	$series_pic->show(ICONS_DIR, false);
	end_upload_logo_button(SERIES_PIC_CODE, $series_id);
	echo '</td></tr>';
	
s	$end_time = $start_time + $duration - 24*60*60;
	if ($end_time < $start_time)
	{
		$end_time = $start_time;
	}
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . timestamp_to_string($start_time, $timezone, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . timestamp_to_string($end_time, $timezone, false) . '">';
	echo '</td></tr>';
	echo '</td></tr>';
	
	if (is_valid_lang($league_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $league_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes($langs, $league_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="60" rows="4">' . $notes . '</textarea></td></tr>';
		
	echo '</table>';
	
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	function onMinDateChange()
	{
		$('#form-end').attr("min", $('#form-start').val());
		var f = new Date($('#form-start').val());
		var t = new Date($('#form-end').val());
		if (f > t)
		{
			$('#form-end').val($('#form-start').val());
		}
	}
	
	function commit(onSuccess)
	{
		var _langs = mr.getLangs('form-');
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		var params =
		{
			op: "change",
			series_id: <?php echo $series_id; ?>,
			name: $("#form-name").val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
		};
		
		json.post("api/ops/series.php", params, onSuccess);
	}
	
	function uploadLogo(seriesId, onSuccess)
	{
		json.upload('api/ops/series.php', 
		{
			op: "change"
			, series_id: seriesId
			, logo: document.getElementById("upload").files[0]
		}, 
		<?php echo UPLOAD_LOGO_MAX_SIZE; ?>, 
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