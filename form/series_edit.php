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
	
	list ($league_id, $name, $start_time, $duration, $langs, $notes, $flags, $league_langs, $gaining_id, $gaining_version, $fee, $currency_id) = 
		Db::record(get_label('sеriеs'), 
			'SELECT s.league_id, s.name, s.start_time, s.duration, s.langs, s.notes, s.flags, l.langs, s.gaining_id, s.gaining_version, s.fee, s.currency_id FROM series s' . 
			' JOIN leagues l ON l.id = s.league_id' .
			' WHERE s.id = ?', $series_id);
	check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, $league_id, $series_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="240">' . get_label('Series name') . ':</td><td><input id="form-name" value="' . $name . '"></td>';
	
	echo '<td align="center" valign="top" rowspan="12" width="120">';
	start_upload_logo_button($series_id);
	echo get_label('Change logo') . '<br>';
	$series_pic = new Picture(SERIES_PICTURE);
	$series_pic->set($series_id, $name, $flags);
	$series_pic->show(ICONS_DIR, false);
	end_upload_logo_button(SERIES_PIC_CODE, $series_id);
	echo '</td></tr>';
	
	$end_time = $start_time + $duration - 24*60*60;
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
	
	echo '<tr><td>'.get_label('Admission rate per player-tournament').':</td><td><input type="number" min="0" style="width: 45px;" id="form-fee" value="'.(is_null($fee)?'':$fee).'" onchange="feeChanged()">';
	$query = new DbQuery('SELECT c.id, n.name FROM currencies c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 ORDER BY n.name');
	echo ' <input id="form-fee-unknown" type="checkbox" onclick="feeUnknownClicked()"' . (is_null($fee)?' checked':'') .'> '.get_label('unknown');
	echo ' <select id="form-currency" onChange="currencyChanged()">';
	show_option(0, $currency_id, '');
	while ($row = $query->next())
	{
		list($cid, $cname) = $row;
		show_option($cid, $currency_id, $cname);
	}
	echo '</select></td></tr>';
	
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
	
	$query = new DbQuery('SELECT id, name FROM gainings WHERE league_id IS NULL OR league_id = ? ORDER BY name', $league_id);
	echo '<tr><td>' . get_label('Gaining system') . ':</td><td><select id="form-gaining">';
	while ($row = $query->next())
	{
		list($gid, $gname) = $row;
		show_option($gid, $gaining_id, $gname);
	}
	echo '</select></td></tr>';
	
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
	
	function feeChanged()
	{
		$("#form-fee-unknown").prop('checked', 0);
	}
	
	function feeUnknownClicked()
	{
		if ($("#form-fee-unknown").attr('checked'))
		{
			$("#form-fee").val('');
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
			fee: ($("#form-fee-unknown").attr('checked')?-1:$("#form-fee").val()),
			currency_id: $('#form-currency').val(),
			start: $('#form-start').val(),
			end: dateToStr(_end),
			gaining_id: $('#form-gaining').val(),
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