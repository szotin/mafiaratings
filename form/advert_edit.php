<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/message.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Edit advert'));

	if (!isset($_REQUEST['advert']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('advert')));
	}
	$advert_id = $_REQUEST['advert'];
	
	list ($message, $start, $end, $timezone) = Db::record(get_label('advert'), 'SELECT n.raw_message, n.timestamp, n.expires, c.timezone FROM news n JOIN clubs cl ON cl.id = n.club_id JOIN cities c ON c.id = cl.city_id WHERE n.id = ?', $advert_id);
	
	$start_date = timestamp_to_string($start, $timezone);
	$end_date = timestamp_to_string($end, $timezone);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80" valign="top">' . get_label('Text').':</td><td><textarea id="form-advert" cols="93" rows="8">' . $message . '</textarea></td></tr>';
	echo '<tr><td valign="top">' . get_label('Starting from').':</td><td>';
	echo '<input type="datetime-local" id="form-start-date" value="' . $start_date . '" onchange="onMinDateChange()"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Ending at').':</td><td>';
	echo '<input type="datetime-local" id="form-end-date" value="' . $end_date . '"></td></tr>';
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
			op: "change"
			, advert_id: <?php echo $advert_id; ?>
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