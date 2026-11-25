<?php

require_once '../include/session.php';
require_once '../include/event.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	$id = (int)$_REQUEST['id'];

	list($club_id, $name, $duration) = Db::record(get_label('event'),
		'SELECT e.club_id, ne.name, e.duration FROM events e' .
		' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
		' WHERE e.id = ?', $id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

	dialog_title(get_label('Convert [0] to a tournament.', $name));

	// What the event becomes. An ordinary tournament is played in one go, so the event becomes its
	// only round and keeps the dates it has. A long-term tournament is a season played on the
	// ordinary days of the club, so the event stays an event of its own and, when it ran longer
	// than an event may last, it is dealt out into the sittings its games were really played in.
	//
	// An event that long is a season already, which is why it is what the dialog offers for one.
	$long_term = (int)$duration > EVENT_MAX_DURATION ? 1 : 0;

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('Tournament type') . ':</td><td><select id="form-long_term">';
	show_option(0, $long_term, get_label('ordinary tournament'));
	show_option(1, $long_term, get_label('long term tournament'));
	echo '</select></td></tr>';
	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: "to_tournament"
			, event_id: <?php echo $id; ?>
			, long_term: $("#form-long_term").val()
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
