<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Change extra points'));

	if (!isset($_REQUEST['points_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('points')));
	}
	$points_id = (int)$_REQUEST['points_id'];
	
	list($user_id, $series_id, $league_id, $reason, $details, $points, $time) = 
		Db::record(get_label('points'), 'SELECT p.user_id, p.series_id, s.league_id, p.reason, p.details, p.points, p.time FROM series_extra_points p JOIN series s ON s.id = p.series_id WHERE p.id = ?', $points_id);
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>' . get_label('Date').':</td><td><input type="date" id="form-time" value="' . timestamp_to_string($time, get_timezone(), false) . '"></td></tr>';
	echo '<tr><td width="120">' . get_label('Reason') . ':</td><td><input id="form-reason" value="' . $reason . '"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Details') . ':</td><td><textarea id="form-details" cols="93" rows="8">' . $details . '</textarea></td></tr>';
	echo '<tr><td>' . get_label('Points') . ':</td><td><input type="number" style="width: 45px;" step="1" id="form-points" value="' . $points . '"></td></tr>';
	echo '</table>';

?>
	<script>
	var savedPoints = <?php echo $points; ?>;
	
	$("#form-reason").autocomplete(
	{ 
		source: function( request, response )
		{
			$.getJSON("api/control/series_extra_points_reason.php?term=" + $("#form-reason").val(), null, response);
		}
		, minLength: 0
	})
	.on("focus", function () { $(this).autocomplete("search", ''); }).width(400);
	
	function commit(onSuccess)
	{
		if ($("#form-points").val() == 0)
		{
			dlg.error("<?php echo get_label('Please enter points.'); ?>");
		}
		else
		{
			json.post("api/ops/series.php",
			{
				op: "change_extra_points"
				, points_id: <?php echo $points_id; ?>
				, time: $('#form-time').val()
				, reason: $("#form-reason").val()
				, details: $("#form-details").val()
				, points: $("#form-points").val()
			},
			onSuccess);
		}
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