<?php

require_once '../include/session.php';
require_once '../include/datetime.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('season')));

	if (!isset($_REQUEST['league']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = (int)$_REQUEST['league'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	$start = new DateTime();
	$end = new DateTime();
	$end->add(new DateInterval('P1Y'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name"> </td></tr>';
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="text" id="form-start" value="' . datetime_to_string($start, false) . '">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="text" id="form-end" value="' . datetime_to_string($end, false) . '">';
	echo '</td></tr>';
	
	echo '</table>';

?>
	<script>
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var startDate = $('#form-start').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	
	function commit(onSuccess)
	{
		json.post("api/ops/league_season.php",
		{
			op: 'create'
			, league_id: <?php echo $league_id; ?>
			, name: $("#form-name").val()
			, start: startDate.val()
			, end: endDate.val()
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