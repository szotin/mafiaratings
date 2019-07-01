<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	dialog_title(get_label('Add extra points'));

	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('Player').':</td><td><input type="text" id="form-player" title="' . get_label('Select player.') . '"/></td></tr>';
	echo '<tr><td>' . get_label('Reason').':</td><td><input id="form-reason"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Details').':</td><td><textarea id="form-details" cols="93" rows="8"></textarea></td></tr>';
	echo '<tr><td>' . get_label('Points') . ':</td><td>';
	echo '<table class="transp"><tr><td width="80"><input id="form-points"></td><td><input type="checkbox" id="form-av-points" onclick="averageClicked()"> ' . get_label('average points per game for this event.') . '</td></tr></table>';
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	var userInfo = null;
	var savedPoints = 0;
	
	$("#form-points").spinner({ step:0.1 }).width(30);
	
	$("#form-reason").autocomplete(
	{ 
		source: function( request, response )
		{
			$.getJSON("api/control/extra_points_reason.php?term=" + $("#form-reason").val(), null, response);
		}
		, minLength: 0
	})
	.on("focus", function () { $(this).autocomplete("search", ''); }).width(400);
	
	$("#form-player").autocomplete(
	{ 
		source: function( request, response )
		{
			$.getJSON("api/control/user.php?term=" + $("#form-player").val(), null, response);
		}
		, select: function(event, ui) { userInfo = ui.item; }
		, minLength: 0
	})
	.on("focus", function () { $(this).autocomplete("search", ''); });
	
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
		if (userInfo == null)
		{
			dlg.error("<?php echo get_label('Please enter player.'); ?>");
		}
		else if ($("#form-points").val() == 0 && !$("#form-av-points").attr("checked"))
		{
			dlg.error("<?php echo get_label('Please enter points.'); ?>");
		}
		else
		{
			json.post("api/ops/event.php",
			{
				op: "add_extra_points"
				, event_id: <?php echo $event_id; ?>
				, user_id: userInfo.id
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