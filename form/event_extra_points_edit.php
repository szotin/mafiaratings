<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	dialog_title(get_label('Change extra points'));

	if (!isset($_REQUEST['points_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('points')));
	}
	$points_id = (int)$_REQUEST['points_id'];
	
	list($user_id, $event_id, $club_id, $reason, $details, $points) = 
		Db::record(get_label('points'), 'SELECT p.user_id, p.event_id, e.club_id, p.reason, p.details, p.points FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE p.id = ?', $points_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('Reason') . ':</td><td><input id="form-reason" value="' . $reason . '"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Details') . ':</td><td><textarea id="form-details" cols="93" rows="8">' . $details . '</textarea></td></tr>';
	echo '<tr><td>' . get_label('Points') . ':</td><td>';
	echo '<table class="transp"><tr><td width="80"><input id="form-points" value="' . $points . '"></td><td><input type="checkbox" id="form-av-points" onclick="averageClicked()"';
	if ($points == 0)
	{
		echo ' checked';
	}
	echo '> ' . get_label('average points per game for this event.') . '</td></tr></table>';
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	var savedPoints = <?php echo $points; ?>;
	
	$("#form-points").spinner({ step:0.1 }).width(30);
	averageClicked();
	
	$("#form-reason").autocomplete(
	{ 
		source: function( request, response )
		{
			$.getJSON("api/control/extra_points_reason.php?term=" + $("#form-reason").val(), null, response);
		}
		, minLength: 0
	})
	.on("focus", function () { $(this).autocomplete("search", ''); }).width(400);
	
	function averageClicked()
	{
		if ($("#form-av-points").attr("checked"))
		{
			savedPoints = $("#form-points").val();
			$("#form-points").val('').spinner("disable");
		}
		else
		{
			$("#form-points").val(savedPoints).spinner("enable");
		}
	}
	
	function commit(onSuccess)
	{
		if ($("#form-points").val() == 0 && !$("#form-av-points").attr("checked"))
		{
			dlg.error("<?php echo get_label('Please enter points.'); ?>");
		}
		else
		{
			json.post("api/ops/event.php",
			{
				op: "change_extra_points"
				, points_id: <?php echo $points_id; ?>
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