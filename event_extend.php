<?php

require_once 'include/session.php';
require_once 'include/event.php';

initiate_session();

try
{
	dialog_title(get_label('Event'));

	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('event')));
	}
	$id = $_REQUEST['id'];
	
	list($club_id, $start_time, $duration, $round_num) = Db::record(get_label('event'), 'SELECT club_id, start_time, duration, round_num FROM events WHERE id = ?', $id);
	if ($_profile == NULL || !$_profile->is_club_manager($club_id))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}

	$rounds = array();
	$rounds[] = get_label('Main round');
	$query = new DbQuery('SELECT name FROM rounds WHERE event_id = ? ORDER BY num', $id);
	while ($row = $query->next())
	{
		list ($round_name) = $row;
		$rounds[] = $round_name;
	}
	$round_count = count($rounds);

	$time = time();
	$def_extend = 3 * 3600;
	
	echo '<table class="dialog_form" width="100%">';
	if ($round_count > 1)
	{
		$def_extend = $round_num + 2;
		if ($def_extend > $round_count)
		{
			$def_extend = 0;
		}
		if ($round_num >= 0 && $round_num < $round_count)
		{
			echo '<tr><td colspan="2" align="center">' . get_label('Current round: [0]', $rounds[$round_num]) . '</td></tr>';
		}
	}
	echo '<tr><td width="100">' . get_label('We would like to').':</td><td>';
	echo '<select id="duration">';
	
	if ($time < $start_time + $duration)
	{
		if ($round_count > 1)
		{
			for ($i = 0; $i < $round_count; ++$i)
			{
				if ($i != $round_num)
				{
					show_option($i + 1, $def_extend, get_label('Change current round to: [0]', $rounds[$i]));
				}
			}
		}
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
		var choice = parseInt($("#duration").val());
		var eventId = <?php echo $id; ?>;
		if (choice > 0 && choice < 3600)
		{
			--choice;
			json.post("api/ops/event.php",
			{
				op: "set_round"
				, event_id: eventId
				, round: choice
			},
			onSuccess);
		}
		else
		{
			var new_duration = <?php echo $time - $start_time; ?> + choice;
			json.post("api/ops/event.php",
			{
				op: "extend"
				, event_id: eventId
				, duration: new_duration
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