<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	global $_lang;

	dialog_title(get_label('Swap players'));

	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	$player1_id = isset($_REQUEST['player1_id']) ? (int)$_REQUEST['player1_id'] : 0;
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

	// Everybody who could stand in this round: the players of the tournament, and whoever the
	// seating names on top of them. Two of them seated means a swap, one of them seated means
	// the other is a substitute taking their place - a player of the tournament who did not
	// make it into this round, which is what the list is for.
	$player_ids = array();
	if (!is_null($misc) && isset($misc->seating) && isset($misc->seating->mapping))
	{
		foreach ($misc->seating->mapping as $uid)
		{
			$uid = is_object($uid) ? (isset($uid->id) ? (int)$uid->id : 0) : (int)$uid;
			if ($uid > 0)
			{
				$player_ids[$uid] = true;
			}
		}
	}

	$q = new DbQuery(
		'SELECT user_id FROM tournament_regs WHERE tournament_id = ? AND (flags & ?) = 0 AND (flags & ?) = 0',
		$tournament_id, USER_TOURNAMENT_FLAG_NOT_ACCEPTED, USER_PERM_REFEREE);
	while ($row = $q->next())
	{
		$player_ids[(int)$row[0]] = true;
	}

	$reg_users = array();
	if (!empty($player_ids))
	{
		$q = new DbQuery(
			'SELECT u.id, n.name FROM users u' .
			' JOIN names n ON n.id = u.name_id AND (n.langs & ?) <> 0' .
			' WHERE u.id IN (' . implode(',', array_keys($player_ids)) . ')' .
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
		echo '<option value="' . $u->id . '"' . ($u->id == $player1_id ? ' selected' : '') . '>' . htmlspecialchars($u->name) . '</option>';
	echo '</select></td></tr>';

	echo '<tr><td>' . get_label('Player [0]', 2) . ':</td><td>';
	echo '<select id="form-player2" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
	foreach ($reg_users as $u)
		echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
	echo '</select></td></tr>';

	// Swapping in every game exchanges the two players outright, which is what this dialog has
	// always done. Swapping in one game exchanges only their seats there, and is offered only
	// when there is a seating to pick a game from.
	$games_count = 0;
	if (!is_null($misc) && isset($misc->seating) && isset($misc->seating->rounds) && is_array($misc->seating->rounds))
	{
		$games_count = count($misc->seating->rounds);
	}
	if ($games_count > 0)
	{
		echo '<tr><td>' . get_label('Game') . ':</td><td>';
		echo '<select id="form-game" style="width:100%">';
		echo '<option value="0" selected>' . get_label('All games') . '</option>';
		for ($i = 1; $i <= $games_count; ++$i)
		{
			echo '<option value="' . $i . '">' . get_label('Game [0]', $i) . '</option>';
		}
		echo '</select></td></tr>';
	}

	echo '</table>';
?>
	<script>
	// Options handed back by api/get/seating_swap_options.php, kept while the choice dialog is up
	// so its buttons can say which one they apply.
	var swapOptions = [];
	var swapArgs = null;
	var swapChoiceDlg = null;
	var swapOnSuccess = null;

	function swapApply(planIndex)
	{
		var params =
		{
			op: "swap_seating_players",
			event_id: <?php echo $event_id; ?>,
			user1_id: swapArgs.player1Id,
			user2_id: swapArgs.player2Id,
			game: swapArgs.game,
		};
		// -1 is the swap on its own, with the statistics left as they fall. Anything else is one
		// of the plans that puts them back, sent as it came so the server can check it still does.
		if (planIndex >= 0)
		{
			params.plan = JSON.stringify(swapOptions[planIndex]);
		}
		if (swapChoiceDlg)
		{
			swapChoiceDlg.dialog("close");
			swapChoiceDlg = null;
		}
		json.post("api/ops/event.php", params, swapOnSuccess);
	}

	function swapPlanText(plan)
	{
		var parts = [];
		for (var i = 0; i < plan.length; ++i)
		{
			parts.push(<?php echo json_encode(get_label('Game [0]', '%N%')); ?>.replace('%N%', plan[i].game) +
				': ' + plan[i].user1 + ' &harr; ' + plan[i].user2);
		}
		return parts.join('; ');
	}

	function swapShowChoice(data)
	{
		swapOptions = data.options || [];

		if (!swapOptions.length && data.kind == 'absent')
		{
			// Nothing can give the game back, so there is nothing to offer.
			dlg.error(<?php echo json_encode(get_label('Both players must play game [0] to be swapped in it. Swapping a player who sits it out with one who does not would change how many games they each play.', '%N%')); ?>.replace('%N%', swapArgs.game));
			return;
		}

		var html = '<p>';
		if (data.kind == 'absent')
		{
			html += <?php echo json_encode(get_label('In game [0] one of these two does not play, so this is not an exchange of seats but a change of how many games they each play. That cannot simply be accepted - it needs swaps elsewhere to give the game back.', '%N%')); ?>.replace('%N%', swapArgs.game);
		}
		else
		{
			html += <?php echo json_encode(get_label('Swapping [0] and [1] in game [2] moves them between seats [3] and [4], which changes the seat number statistics.', '%U1%', '%U2%', '%N%', '%S1%', '%S2%')); ?>
				.replace('%U1%', data.user1).replace('%U2%', data.user2)
				.replace('%N%', swapArgs.game)
				.replace('%S1%', data.seat1).replace('%S2%', data.seat2);
		}
		html += '</p><table class="bordered light" width="100%">';

		if (data.kind != 'absent')
		{
			html += '<tr><td>' + <?php echo json_encode(get_label('Just this swap, and leave the statistics as they fall.')); ?> +
				'</td><td width="90" align="center"><button onclick="swapApply(-1)">' +
				<?php echo json_encode(get_label('Apply')); ?> + '</button></td></tr>';
		}
		if (swapOptions.length)
		{
			html += '<tr class="darker"><td colspan="2">' +
				<?php echo json_encode(get_label('These further swaps put the seat numbers back exactly as they were:')); ?> + '</td></tr>';
			for (var i = 0; i < swapOptions.length; ++i)
			{
				html += '<tr><td>' + swapPlanText(swapOptions[i]) +
					'</td><td width="90" align="center"><button onclick="swapApply(' + i + ')">' +
					<?php echo json_encode(get_label('Apply')); ?> + '</button></td></tr>';
			}
		}
		else
		{
			html += '<tr><td colspan="2">' +
				<?php echo json_encode(get_label('No way to put the seat numbers back was found.')); ?> + '</td></tr>';
		}
		html += '</table>';

		// Close means do nothing, which leaves the swap dialog underneath still open.
		swapChoiceDlg = dlg.custom(html, <?php echo json_encode(get_label('Swap players')); ?>, 620,
			[{ text: <?php echo json_encode(get_label('Close')); ?>, click: function() { $(this).dialog("close"); swapChoiceDlg = null; } }]);
	}

	function commit(onSuccess)
	{
		var player1Id = parseInt($("#form-player1").val()) || 0;
		var player2Id = parseInt($("#form-player2").val()) || 0;
		var game = parseInt($("#form-game").val()) || 0;
		if (player1Id <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please enter player [0].', 1)); ?>);
			return;
		}
		if (player2Id <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please enter player [0].', 2)); ?>);
			return;
		}
		if (player1Id == player2Id)
		{
			dlg.error(<?php echo json_encode(get_label('Players must be different.')); ?>);
			return;
		}

		swapArgs = { player1Id: player1Id, player2Id: player2Id, game: game };
		swapOnSuccess = onSuccess;

		// Swapping them in every game trades their whole seatings, so each seat number stays
		// occupied exactly as often as before and there is nothing to warn about.
		if (game <= 0)
		{
			swapApply(-1);
			return;
		}

		json.get("api/get/seating_swap_options.php?event_id=<?php echo $event_id; ?>" +
			"&user1_id=" + player1Id + "&user2_id=" + player2Id + "&game=" + game,
			function(data)
			{
				// The same seat number at another table, where the statistic counts numbers and
				// not tables, so it cannot tell the difference.
				if (!data || data.kind == 'harmless')
				{
					swapApply(-1);
					return;
				}
				// Somebody the seating never named has no slot of their own, and the mapping that
				// says who a slot is holds for the whole round - so they cannot stand in for one
				// game. Said here rather than left to the server, which refuses it as well.
				if (data.kind == 'substitute')
				{
					dlg.error(<?php echo json_encode(get_label('A player who is not in this round\'s seating can only take somebody\'s place in every game of the round. To change one game only, change the player in the game itself.')); ?>);
					return;
				}
				swapShowChoice(data);
			});
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
