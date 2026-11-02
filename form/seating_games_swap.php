<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	global $_lang;

	dialog_title(get_label('Swap games'));

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

	// Swapping two games moves whole rounds of the seating around each other, so there has to be
	// a seating with at least two of them to move.
	$games_count = 0;
	if (!is_null($misc) && isset($misc->seating->rounds) && is_array($misc->seating->rounds))
	{
		$games_count = count($misc->seating->rounds);
	}
	if ($games_count < 2)
	{
		throw new Exc(get_label('There is nothing to swap: this seating has fewer than two games.'));
	}

	echo '<table class="dialog_form" width="100%">';

	for ($n = 1; $n <= 2; ++$n)
	{
		echo '<tr><td width="120">' . get_label('Game [0]', $n) . ':</td><td>';
		echo '<select id="form-game' . $n . '" style="width:100%">';
		echo '<option value="0">' . get_label('Select a game.') . '</option>';
		for ($i = 1; $i <= $games_count; ++$i)
		{
			echo '<option value="' . $i . '">' . get_label('Game [0]', $i) . '</option>';
		}
		echo '</select></td></tr>';
	}

	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var game1 = parseInt($("#form-game1").val()) || 0;
		var game2 = parseInt($("#form-game2").val()) || 0;
		if (game1 <= 0)
		{
			dlg.error("<?php echo get_label('Please select game [0].', 1); ?>");
		}
		else if (game2 <= 0)
		{
			dlg.error("<?php echo get_label('Please select game [0].', 2); ?>");
		}
		else if (game1 == game2)
		{
			dlg.error("<?php echo get_label('Games must be different.'); ?>");
		}
		else
		{
			json.post("api/ops/event.php",
			{
				op: "swap_seating_games",
				event_id: <?php echo $event_id; ?>,
				game1: game1,
				game2: game2,
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
