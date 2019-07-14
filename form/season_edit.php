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
	
	list ($name, $start, $end, $club_id, $timezone) = Db::record(get_label('season'), 'SELECT s.name, s.start_time, s.end_time, c.id, ct.timezone FROM seasons s JOIN clubs c ON c.id = s.club_id JOIN cities ct ON ct.id = c.city_id WHERE s.id = ?', $season_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"> </td></tr>';
	
	echo '<tr><td>'.get_label('Dates').':</td><td>';
	echo '<input type="text" id="form-start" value="' . timestamp_to_string($start, $timezone, false) . '">';
	echo '  ' . get_label('to') . '  ';
	echo '<input type="text" id="form-end" value="' . timestamp_to_string($end, $timezone, false) . '">';
	echo '</td></tr>';
	
	echo '</table>';
	
?>	
	<script>
	var dateFormat = "<?php echo JS_DATETIME_FORMAT; ?>";
	var startDate = $('#form-start').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true });
	
	function commit(onSuccess)
	{
		json.post("api/ops/season.php",
			{
				op: 'change'
				, season_id: <?php echo $season_id; ?>
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