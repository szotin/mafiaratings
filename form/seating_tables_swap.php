<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	global $_lang;

	dialog_title(get_label('Swap tables'));

	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	if ($event_id <= 0)
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}

	list($club_id, $tournament_id, $misc_str) = Db::record(get_label('event'),
		'SELECT club_id, tournament_id, misc FROM events WHERE id = ?', $event_id);
	check_permissions(
		PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
		PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
		$club_id, $event_id, $tournament_id);

	$misc = is_null($misc_str) ? null : json_decode($misc_str);

	// A table swap moves two tables around each other in every game, so only the tables that are
	// there in every game can be offered. A handful of stored seatings do not run the same number
	// of tables throughout - a final round on fewer tables, say - and offering a table that half
	// the games do not have would only produce an error on submit.
	$tables_count = 0;
	if (!is_null($misc) && isset($misc->seating->rounds) && is_array($misc->seating->rounds) && count($misc->seating->rounds))
	{
		$tables_count = PHP_INT_MAX;
		foreach ($misc->seating->rounds as $round)
		{
			$tables_count = min($tables_count, is_array($round) ? count($round) : 0);
		}
	}
	if ($tables_count < 2)
	{
		throw new Exc(get_label('There is nothing to swap: this seating has fewer than two tables.'));
	}

	echo '<table class="dialog_form" width="100%">';

	for ($n = 1; $n <= 2; ++$n)
	{
		echo '<tr><td width="120">' . get_label('Table [0]', $n) . ':</td><td>';
		echo '<select id="form-table' . $n . '" style="width:100%">';
		echo '<option value="0">' . get_label('Select a table.') . '</option>';
		for ($i = 1; $i <= $tables_count; ++$i)
		{
			echo '<option value="' . $i . '">' . get_label('Table [0]', $i) . '</option>';
		}
		echo '</select></td></tr>';
	}

	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var table1 = parseInt($("#form-table1").val()) || 0;
		var table2 = parseInt($("#form-table2").val()) || 0;
		if (table1 <= 0)
		{
			dlg.error("<?php echo get_label('Please select table [0].', 1); ?>");
		}
		else if (table2 <= 0)
		{
			dlg.error("<?php echo get_label('Please select table [0].', 2); ?>");
		}
		else if (table1 == table2)
		{
			dlg.error("<?php echo get_label('Tables must be different.'); ?>");
		}
		else
		{
			json.post("api/ops/event.php",
			{
				op: "swap_seating_tables",
				event_id: <?php echo $event_id; ?>,
				table1: table1,
				table2: table2,
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
