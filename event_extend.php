<?php

require_once 'include/session.php';
require_once 'include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Extend the event'));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	$id = $_REQUEST['id'];
	
	list($club_id, $start_time, $duration) = Db::record(get_label('event'), 'SELECT club_id, start_time, duration FROM events WHERE id = ?', $id);
	
	if ($_profile == NULL || !$_profile->is_manager($club_id))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}

	$time = time();
	$def_extend = 3 * 3600;
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="160">' . get_label('We would like to').':</td><td>';
	echo '<select id="duration">';
	if ($time < $start_time + $duration)
	{
		show_option(0, $def_extend, get_label('End event now'));
	}
	for ($i = 1; $i <= 12; ++$i)
	{
		$value = $i * 3600;
		if ($time + $value > $start_time + $duration)
		{
			show_option($value, $def_extend, get_label('Play [0] more hours', $i));
		}
	}
	for ($i = 1; $i <= 5; ++$i)
	{
		$value = $i * 86400;
		if ($time + $value > $start_time + $duration)
		{
			show_option($value, $def_extend, get_label('Play [0] more days', $i));
		}
	}
	echo '</select>';
	echo '</td></tr></table>';
	
?>	
	<script>
	function commit(onSuccess)
	{
		var new_duration = <?php echo $time - $start_time; ?> + parseInt($("#duration").val());
		json.post("event_ops.php",
			{
				id: <?php echo $id; ?>,
				duration: new_duration,
				extend: ""
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