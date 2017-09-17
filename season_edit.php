<?php

require_once 'include/session.php';
require_once 'include/event.php';

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
	
	date_default_timezone_set($timezone);
	$start_day = date('j', $start);
	$start_month = date('n', $start);
	$start_year = date('Y', $start);
	$end_day = date('j', $end);
	$end_month = date('n', $end);
	$end_year = date('Y', $end);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="140">' . get_label('Name') . ':</td><td><input class="longest" id="form-name" value="' . htmlspecialchars($name, ENT_QUOTES) . '"> </td></tr>';
	echo '<tr><td>' . get_label('Start') . ':</td><td>';
	show_date_controls($start_day, $start_month, $start_year, 'form-start_');
	echo '</td></tr>';
	echo '<tr><td>' . get_label('End') . ':</td><td>';
	show_date_controls($end_day, $end_month, $end_year, 'form-end_');
	echo '</td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("season_ops.php",
			{
				id: <?php echo $season_id; ?>,
				name: $("#form-name").val(),
				start_month: $("#form-start_month").val(),
				start_day: $("#form-start_day").val(),
				start_year: $("#form-start_year").val(),
				end_month: $("#form-end_month").val(),
				end_day: $("#form-end_day").val(),
				end_year: $("#form-end_year").val(),
				update: ""
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
	echo $e->getMessage();
}

?>