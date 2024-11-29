<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Add broadcast'));
	
	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="80">' . get_label('Day') . '</td><td><input type="number" min="1" style="width: 45px;" id="form-day" value="1"></td></tr>';
	
	
	echo '<tr><td>' . get_label('Table') . ':</td><td><select id="form-table">';
	for ($i = 0; $i < 26; ++$i)
	{
		show_option($i, 0, chr(65 + $i));
	}
	echo '</td></tr>';
	
	
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
			, table: $("#form-table").val()
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