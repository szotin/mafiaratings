<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timespan.php';
require_once '../include/datetime.php';
require_once '../include/security.php';
require_once '../include/picture.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('sеriеs')));
	
	if (!isset($_REQUEST['league_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('league')));
	}
	
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	$league_id = 0;
	if (isset($_REQUEST['league_id']))
	{
		$league_id = (int)$_REQUEST['league_id'];
	}

	echo '<table class="dialog_form" width="100%">';
	list($league_name, $league_flags, $league_langs) = Db::record(get_label('league'), 'SELECT name, flags, langs FROM leagues WHERE id = ?', $league_id);
	
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="' . ICON_WIDTH . '">';
	$league_pic = new Picture(LEAGUE_PICTURE);
	$league_pic->set($league_id, $league_name, $league_flags);
	$league_pic->show(ICONS_DIR, false);
	echo '</td><td align="center"><b>' . $league_name . '</b></td></tr></table></td></tr>';
	
	echo '<tr><td width="160">' . get_label('Series name') . ':</td><td><input id="form-name" value=""></td></tr>';
	
	$timezone = get_timezone();
	$datetime = get_datetime(time(), $timezone);
	$date = datetime_to_string($datetime, false);
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . $date . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . $date . '">';
	echo '</td></tr>';
	
	if (is_valid_lang($league_langs))
	{
		echo '<input type="hidden" id="form-langs" value="' . $league_langs . '">';
	}
	else
	{
		echo '<tr><td>'.get_label('Languages').':</td><td>';
		langs_checkboxes(LANG_ALL, $league_langs, NULL, '<br>', 'form-');
		echo '</td></tr>';
	}
	
	echo '<tr><td>'.get_label('Notes').':</td><td><textarea id="form-notes" cols="80" rows="4"></textarea></td></tr>';
		
?>	

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
		
		var _flags = 0;
		var _end = strToDate($('#form-end').val());
		_end.setDate(_end.getDate() + 1); // inclusive
		
		var params =
		{
			op: "create",
			league_id: <?php echo $league_id; ?>,
			name: $("#form-name").val(),
			type: $('#form-type').val(),
			price: $("#form-price").val(),
			notes: $("#form-notes").val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			langs: _langs,
			flags: _flags
		};
		
		json.post("api/ops/series.php", params, onSuccess);
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