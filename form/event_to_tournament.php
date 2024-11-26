<?php

require_once '../include/session.php';
require_once '../include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Convert event to tournament round'));

	if ($_profile == NULL)
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}

	if (!isset($_REQUEST['event_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = $_REQUEST['event_id'];
	
	list($event_id, $name, $club_id, $tournament_id, $start, $duration) = Db::record(get_label('event'), 'SELECT id, name, club_id, tournament_id, start_time, duration FROM events WHERE id = ?', $event_id);
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tournament_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="230">' . get_label('Convert to the round of the tournament') . ':</td><td><select id="form-tournament">';
	show_option(0, 0, get_label('Create a new tournament'));
	$query = new DbQuery('SELECT id, name FROM tournaments WHERE start_time <= ? AND start_time + duration >= ? AND club_id = ?', $start + $duration, $start, $club_id);
	while ($row = $query->next())
	{
		list($tournmanent_id, $tournament_name) = $row;
		show_option($tournmanent_id, 0, $tournament_name);
	}
	echo '</select></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/event.php",
			{
				op: "to_tournament"
				, event_id: <?php echo $event_id; ?>
				, tournament_id: $('#form-tournament').val()
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
