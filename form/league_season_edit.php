<?php

require_once '../include/session.php';
require_once '../include/datetime.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Edit season'));

	if (!isset($_REQUEST['season']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('season')));
	}
	$season_id = $_REQUEST['season'];
	
	list ($name, $start, $end, $league_id) = Db::record(get_label('season'), 'SELECT name, start_time, end_time, league_id FROM league_seasons WHERE id = ?', $season_id);
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"> </td></tr>';
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . timestamp_to_string($start, $_profile->timezone, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . timestamp_to_string($end, $_profile->timezone, false) . '">';
	echo '</td></tr>';
	
	echo '</table>';
	
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
		json.post("api/ops/league_season.php",
			{
				op: 'change'
				, season_id: <?php echo $season_id; ?>
				, name: $("#form-name").val()
				, start: $('#form-start').val()
				, end: $('#form-end').val()
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