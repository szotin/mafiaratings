<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/message.php';

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
	
	date_default_timezone_set($timezone);
	$start_date = date('Y-m-d', $start);
	$start_hour = date('H', $start);
	$start_minute = date('i', $start);
	$end_date = date('Y-m-d', $end);
	$end_hour = date('H', $end);
	$end_minute = date('i', $end);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80" valign="top">' . get_label('Text').':</td><td><textarea id="form-advert" cols="93" rows="8">' . $message . '</textarea></td></tr>';
	echo '<tr><td valign="top">' . get_label('Starting from').':</td><td>';
	echo '<input type="text" id="form-start-date" value="' . $start_date . '"> <input id="form-start-hour" value="' . $start_hour . '"> : <input id="form-start-minute" value="' . $start_minute . '"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Ending at').':</td><td>';
	echo '<input type="text" id="form-end-date" value="' . $end_date . '"> <input id="form-end-hour" value="' . $end_hour . '"> : <input id="form-end-minute" value="' . $end_minute . '"></td></tr>';
	echo '</table>';
	
?>	
	<script>
	
	var dateFormat = "yy-mm-dd";
	var parts = "<?php echo $end_date; ?>".split("-")
	var endDate = null;
	if (parts.length > 2)
	{
		endDate = new Date(parts[0], parts[1] - 1, parts[2]);
	}
	var startDate = $('#form-start-date').datepicker({ maxDate:14, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { endDate.datepicker("option", "minDate", this.value); });
	var endDate = $('#form-end-date').datepicker({ minDate:0, dateFormat:dateFormat, changeMonth: true, changeYear: true }).on("change", function() { startDate.datepicker("option", "maxDate", this.value); });
	
	$("#form-start-hour").spinner({ step:1, max:23, min:0 }).width(16);
	$("#form-end-hour").spinner({ step:1, max:23, min:0 }).width(16);
	$("#form-start-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(16);
	$("#form-end-minute").spinner({ step:10, max:50, min:0, numberFormat: "d2" }).width(16);
	
	function addZero(str)
	{
		switch (str.length)
		{
			case 0:
				return "00";
			case 1:
				return "0" + str;
		}
		return str;
	}
	
	function commit(onSuccess)
	{
		var start = startDate.val() + " " + addZero($("#form-start-hour").val()) + ":" + addZero($("#form-start-minute").val());
		var end = endDate.val() + " " + addZero($("#form-end-hour").val()) + ":" + addZero($("#form-end-minute").val());
		json.post("api/ops/advert.php",
		{
			op: "change"
			, advert_id: <?php echo $advert_id; ?>
			, message: $('#form-advert').val()
			, start: start
			, end: end
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