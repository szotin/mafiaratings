<?php

require_once 'include/session.php';
require_once 'include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('season')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	
	$day = date("d");
	$month = date("n");
	$year = date("Y");

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name"> </td></tr>';
	echo '<tr><td>' . get_label('Start') . ':</td><td>';
	show_date_controls($day, $month, $year, 'form-start_');
	echo '</td></tr>';
	echo '<tr><td>' . get_label('End') . ':</td><td>';
	show_date_controls($day, $month, $year + 1, 'form-end_');
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	function commit(onSuccess)
	{
		json.post("season_ops.php",
		{
			id: <?php echo $club_id; ?>,
			name: $("#form-name").val(),
			start_month: $("#form-start_month").val(),
			start_day: $("#form-start_day").val(),
			start_year: $("#form-start_year").val(),
			end_month: $("#form-end_month").val(),
			end_day: $("#form-end_day").val(),
			end_year: $("#form-end_year").val(),
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
	echo '<error=' . $e->getMessage() . '>';
}

?>