<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Change broadcast'));
	
	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	if (!isset($_REQUEST['day']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('day')));
	}
	$day = (int)$_REQUEST['day'];
	
	if (!isset($_REQUEST['table']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('table')));
	}
	$table = (int)$_REQUEST['table'];
	
	if (!isset($_REQUEST['part']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('part')));
	}
	$part = (int)$_REQUEST['part'];
	
	list ($url, $event_name) = Db::record(get_label('broadcast'), 'SELECT es.url, e.name FROM event_broadcasts es JOIN events e ON e.id = es.event_id WHERE es.event_id = ? AND es.day_num = ? AND es.table_num = ? AND es.part_num = ?', $event_id, $day, $table, $part);
	list ($parts) = Db::record(get_label('broadcast'), 'SELECT count(*) FROM event_broadcasts WHERE event_id = ? AND day_num = ? AND table_num = ?', $event_id, $day, $table);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td colspan="2" align="center"><b>';
	if ($parts > 1)
	{
		echo get_label('[0]: Day [1], table [2] - part [3]', $event_name, $day, $table + 1, $part);
	}
	else
	{
		echo get_label('[0]: Day [1], table [2]', $event_name, $day, $table + 1, $part);
	}
	echo '</b></td></tr>';
	
	echo '<tr><td>' . get_label('URL') . ':</td><td><input type="url" id="form-url" value="' . $url . '"></td></tr>';
	echo '</table>';
	

?>
	<script>
	function commit(onSuccess)
	{
		json.post("api/ops/event.php",
		{
			op: "change_broadcast"
			, event_id: <?php echo $event_id; ?>
			, day: <?php echo $day; ?>
			, table: <?php echo $table; ?>
			, part: <?php echo $part; ?>
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