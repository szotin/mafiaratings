<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	global $_lang;

	dialog_title(get_label('Swap players'));

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

	$player_ids = array();
	$misc = is_null($misc_str) ? null : json_decode($misc_str);
	if (!is_null($misc) && isset($misc->seating) && is_object($misc->seating) && isset($misc->seating->mapping))
	{
		foreach ($misc->seating->mapping as $uid)
		{
			$uid = (int)$uid;
			if ($uid > 0)
				$player_ids[] = $uid;
		}
	}

	$reg_users = array();
	if (!empty($player_ids))
	{
		$q = new DbQuery(
			'SELECT u.id, n.name FROM users u' .
			' JOIN names n ON n.id = u.name_id AND (n.langs & ?) <> 0' .
			' WHERE u.id IN (' . implode(',', $player_ids) . ')' .
			' ORDER BY n.name',
			$_lang);
		while ($row = $q->next())
		{
			$u = new stdClass();
			$u->id   = (int)$row[0];
			$u->name = $row[1];
			$reg_users[] = $u;
		}
	}

	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td width="120">' . get_label('Player [0]', 1) . ':</td><td>';
	echo '<select id="form-player1" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
	foreach ($reg_users as $u)
		echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
	echo '</select></td></tr>';

	echo '<tr><td>' . get_label('Player [0]', 2) . ':</td><td>';
	echo '<select id="form-player2" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
	foreach ($reg_users as $u)
		echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
	echo '</select></td></tr>';

	echo '</table>';
?>
	<script>
	function commit(onSuccess)
	{
		var player1Id = parseInt($("#form-player1").val()) || 0;
		var player2Id = parseInt($("#form-player2").val()) || 0;
		if (player1Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 1); ?>");
		}
		else if (player2Id <= 0)
		{
			dlg.error("<?php echo get_label('Please enter player [0].', 2); ?>");
		}
		else if (player1Id == player2Id)
		{
			dlg.error("<?php echo get_label('Players must be different.'); ?>");
		}
		else
		{
			json.post("api/ops/event.php",
			{
				op: "swap_seating_players",
				event_id: <?php echo $event_id; ?>,
				user1_id: player1Id,
				user2_id: player2Id,
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
