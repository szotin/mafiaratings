<?php

require_once '../include/session.php';
require_once '../include/datetime.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('season')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = (int)$_REQUEST['club'];
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	$start = new DateTime();
	$end = new DateTime();
	$end->add(new DateInterval('P1Y'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name"> </td></tr>';
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="date" id="form-start" value="' . datetime_to_string($start, false) . '" onchange="onMinDateChange()">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="date" id="form-end" value="' . datetime_to_string($end, false) . '">';
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
		json.post("api/ops/club_season.php",
		{
			op: 'create'
			, club_id: <?php echo $club_id; ?>
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