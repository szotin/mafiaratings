<?php

require_once '../include/session.php';
require_once '../include/security.php';

initiate_session();

try
{
	dialog_title(get_label('Delete event'));

	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	list($event_id, $name, $club_id, $tournament_id, $flags, $games_count) = Db::record(get_label('event'), 'SELECT e.id, e.name, e.club_id, e.tournament_id, e.flags, count(g.id) FROM events e LEFT OUTER JOIN games g ON g.event_id = e.id WHERE e.id = ? GROUP BY e.id', $event_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
	$canceled = (($flags & EVENT_FLAG_CANCELED) != 0);
	if ($games_count > 0)
	{
		echo '<p><b>' . get_label('WARNING!') . '</b> ' . get_label('[0] games were played in this event. They will be deleted if you choose to delete this event. Are you sure you want to do it?', $games_count) . '</p>';
	}
	else if ($flags & EVENT_FLAG_CANCELED)
	{
		echo '<p>' . get_label('Are you sure you want to delete the event?') . '</p>';
	}
	else
	{
		echo '<p>'.get_label('Do you want to?').'</p>';
		echo '<p><input type="radio" name="option" checked> ' . get_label('cancel the event.');
		echo '<br><input type="radio" name="option" id="form-delete"> ' . get_label('delete the event completely.');
		echo '</p>';
	}
	
?>
	<script>
	function deleteEvent(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: 'delete',
			event_id: <?php echo $event_id; ?>
		},
		onSuccess);
	}
	
	function cancelEvent(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: 'cancel',
			event_id: <?php echo $event_id; ?>
		},
		function()
		{
			dlg.form("form/event_mailing_create.php?events=<?php echo $event_id; ?>&type=1", onSuccess, 500, onSuccess);
		});
	}
	
	function commit(onSuccess)
	{
		if ($('#form-delete').length <= 0)
		{
			deleteEvent(onSuccess);
		}
		else if ($("#form-delete").attr("checked"))
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete the event?'); ?>", null, null, function() { deleteEvent(onSuccess); } );
		}
		else
		{
			cancelEvent(onSuccess);
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