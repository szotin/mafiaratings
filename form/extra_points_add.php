<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Add extra points'));

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('Player') . ':</td><td>';
	
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		show_user_input('form-user', '', 'event=' . $event_id, get_label('Select player.'), 'onSelect');
		echo '</td></tr>';
		echo '<input id="form-event" type="hidden" value="' . $event_id . '">';
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		show_user_input('form-user', '', 'tournament=' . $tournament_id, get_label('Select player.'), 'onSelect');
		echo '</td></tr>';
		
		$query = new DbQuery('SELECT id, name, flags FROM events WHERE tournament_id = ? ORDER BY start_time, id', $tournament_id);
		$events = array();
		while ($row = $query->next())
		{
			$events[] = $row;
		}
		switch (count($events))
		{
			case 0:
				throw new Exc(get_label('This tournament has no rounds'));
			case 1:
				echo '<input id="form-event" type="hidden" value="' . $events[0][0] . '">';
				break;
			default:
				echo '<tr><td>' . get_label('Round') . ':</td><td><select id="form-event">';
				foreach ($events as $row)
				{
					list($event_id, $event_name, $event_flags) = $row;
					show_option((int)$event_id, 0, $event_name);
				}
				echo '</select></td></tr>';
				break;
		}
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	
	echo '<tr><td>' . get_label('Reason').':</td><td><input id="form-reason"></td></tr>';
	echo '<tr><td valign="top">' . get_label('Details').':</td><td><textarea id="form-details" cols="93" rows="8"></textarea></td></tr>';
	echo '<tr><td>' . get_label('Points') . ':</td><td><input type="number" style="width: 45px;" step="0.1" id="form-points"></td></tr>';
	echo '<tr><td>' . get_label('MVP') . ':</td><td><input id="form-mvp" type="checkbox" checked> ' . get_label('these points are used in the MVP competition.') . '</td></tr>';
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
			$.getJSON("api/control/extra_points_reason.php?term=" + $("#form-reason").val(), null, response);
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
			json.post("api/ops/event.php",
			{
				op: "add_extra_points"
				, event_id: $("#form-event").val()
				, user_id: userId
				, reason: $("#form-reason").val()
				, details: $("#form-details").val()
				, points: $("#form-points").val()
				, mvp: ($("#form-mvp").attr("checked") ? 1 : 0)
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