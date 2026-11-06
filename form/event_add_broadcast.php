<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/scoring.php';
require_once '../include/stream.php';

initiate_session();

try
{
	dialog_title(get_label('Add broadcast'));

	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];

	// How many tables there are to stream. A seating is somebody stating how many, so where
	// there is one it settles the question; where there is none, the games are the only
	// evidence, and one more than has been played at - because a table is set up to be
	// streamed before anybody plays at it, and the first one before anything has been played
	// at all.
	$tables = stream_seated_tables(stream_event($event_id));
	if ($tables <= 0)
	{
		list ($played) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM games WHERE event_id = ?', $event_id);
		list ($playing) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM current_games WHERE event_id = ?', $event_id);
		$tables = max((int)$played, (int)$playing) + 1;
	}

	// Asked from the page of one table - the stream settings - the table is settled before the
	// dialog opens: it is the table whose tab the button stands on. It is still shown, because a
	// dialog that does not say which table it is adding to says half of what it is doing, but
	// there is nothing there to choose. A table past the ones anybody has counted is taken as it
	// is given rather than pulled back to the last one that was: the tab it came from is a table
	// somebody is setting up before it has been played at.
	$table = isset($_REQUEST['table']) ? max(1, (int)$_REQUEST['table']) : 1;
	$fixed = isset($_REQUEST['fixed']) && (int)$_REQUEST['fixed'] != 0;
	$tables = max($tables, $table);

	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('Day') . '</td><td><input type="number" min="1" style="width: 45px;" id="form-day" value="1"></td></tr>';
	echo '<tr><td>' . get_label('Table') . ':</td><td><input type="number" min="1" max="' . $tables . '" style="width: 45px;" id="form-table" value="' . $table . '"' . ($fixed ? ' disabled' : '') . '></td></tr>';
	echo '<tr><td>' . get_label('URL') . ':</td><td><input type="url" id="form-url"></td></tr>';
	echo '</table>';


?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: "add_broadcast"
			, event_id: <?php echo $event_id; ?>
			, day: $("#form-day").val()
			// Tables are counted from one on the screen and from zero in event_broadcasts. A
			// number field takes whatever is typed into it whatever its min and max say, so
			// what is typed is brought back inside them here.
			, table: Math.min(Math.max(parseInt($("#form-table").val(), 10) || <?php echo $table; ?>, 1), <?php echo $tables; ?>) - 1
			, url: $("#form-url").val()
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
