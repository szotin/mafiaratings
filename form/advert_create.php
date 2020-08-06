<?php

require_once '../include/session.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Create [0]', get_label('advert')));

	if (!isset($_REQUEST['club']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('club')));
	}
	$club_id = $_REQUEST['club'];
	
	if (!isset($_profile->clubs[$club_id]))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	$club = $_profile->clubs[$club_id];
	
	$timezone = new DateTimeZone($club->timezone);
	$start_date = new DateTime("now", $timezone);
	$end_date = new DateTime("now", $timezone);
	$end_date->add(new DateInterval('P2W'));
	
	$start_date->setTime($start_date->format('G'), 0);
	$end_date->setTime($end_date->format('G'), 0);
	
	$start_date_str = datetime_to_string($start_date);
	$end_date_str = datetime_to_string($end_date);
	
	echo datetime_to_string($start_date, true);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80" valign="top">' . get_label('Text').':</td><td><textarea id="form-advert" cols="93" rows="8"></textarea></td></tr>';
	echo '<tr><td valign="top">' . get_label('Starting from').':</td><td>';
	echo '<input type="datetime-local" id="form-start-date" value="' . $start_date_str . '" onchange="onMinDateChange()"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Ending at').':</td><td>';
	echo '<input type="datetime-local" id="form-end-date" value="' . $end_date_str . '"></td></tr>';
	echo '</table>';

?>
	<script>
	function onMinDateChange()
	{
		$('#form-end-date').attr("min", $('#form-start-date').val());
		var f = new Date($('#form-start-date').val());
		var t = new Date($('#form-end-date').val());
		if (f > t)
		{
			$('#form-end-date').val($('#form-start-date').val());
		}
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/advert.php",
		{
			op: "create"
			, club_id: <?php echo $club_id; ?>
			, message: $('#form-advert').val()
			, start: $('#form-start-date').val()
			, end: $('#form-end-date').val()
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