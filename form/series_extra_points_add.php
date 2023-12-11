<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/datetime.php';

initiate_session();

try
{
	dialog_title(get_label('Add extra points'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('Player') . ':</td><td>';
	
	if (!isset($_REQUEST['series_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('series')));
	}
	
	$series_id = (int)$_REQUEST['series_id'];
	show_user_input('form-user', '', 'series=' . $series_id, get_label('Select player.'), 'onSelect');
	echo '</td></tr>';
	
	$datetime = get_datetime(time(), get_timezone());
	$time = datetime_to_string($datetime, false);
	
	echo '<tr><td>' . get_label('Date').':</td><td><input type="date" id="form-time" value="' . $time . '"></td></tr>';
	echo '<tr><td>' . get_label('Reason').':</td><td><input id="form-reason"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Details').':</td><td><textarea id="form-details" cols="93" rows="8"></textarea></td></tr>';
	echo '<tr><td>' . get_label('Points') . ':</td><td><input type="number" style="width: 45px;" step="1" id="form-points"></td></tr>';
	echo '</table>';

?>
	<script>
	var savedPoints = 0;
	var userId = 0;
	function onSelect(_user)
	{
		if (typeof _user.id == "number")
		{
			userId = _user.id;
			$("#form-nick").val(_user.name);
		}
		else
		{
			userId = 0;
		}
	}
	
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
		if (userId <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player.'); ?>");
		}
		else if ($("#form-points").val() == 0)
		{
			dlg.error("<?php echo get_label('Please enter points.'); ?>");
		}
		else
		{
			json.post("api/ops/series.php",
			{
				op: "add_extra_points"
				, series_id: <?php echo $series_id; ?>
				, user_id: userId
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