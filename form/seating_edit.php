<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

// Everything that can be changed about a seating that is already there, in one dialog. These
// were three buttons of their own on the preparation page, which made a row of buttons all
// saying "swap" and none of them saying what the dialog behind it would ask. They are three
// tabs here: three questions about the same seating.
//
// A tab is left out where there would be nothing in it to choose - a seating with a single game
// has no two games to exchange, and one with a single table no two tables - so a seating that
// cannot be rearranged that way opens on the players alone.
//
// The three ops behind them are swap_seating_players, swap_seating_games and swap_seating_tables
// in api/ops/event.php, unchanged; so is everything the players tab does about the seat number
// statistics, which is the one of the three that has anything to warn about.

try
{
	global $_lang;

	dialog_title(get_label('Edit seating'));

	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	// Clicking a player in the grid opens this dialog with him already chosen.
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
	if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds) || count($misc->seating->rounds) <= 0)
	{
		throw new Exc(get_label('There is no seating for this round yet.'));
	}

	// How much there is to move around. Tables are counted as the most any game has: a few
	// seatings do not run the same number throughout - the last game of two tables played on one,
	// say - and their tables are swapped in the games that have both (swap_seating_tables).
	$games_count = count($misc->seating->rounds);
	$tables_count = 0;
	foreach ($misc->seating->rounds as $round)
	{
		$tables_count = max($tables_count, is_array($round) ? count($round) : 0);
	}

	// Everybody who could stand in this round: the players of the tournament, and whoever the
	// seating names on top of them. Two of them seated means a swap, one of them seated means
	// the other is a substitute taking their place - a player of the tournament who did not
	// make it into this round, which is what the list is for.
	$player_ids = array();
	if (isset($misc->seating->mapping))
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

	// The widget draws a frame round the whole thing, another round the tab strip and a third
	// round the panel below it. Inside a dialog, which has a frame of its own, that is four
	// borders where a single line under the tabs says everything. The tabs themselves keep their
	// shape - they are what has to look like tabs.
	//
	// The two rules about the anchors are there because this site's jquery-ui.css has
	// "text-decoration: none" commented out everywhere it would have applied, so a tab is
	// underlined like any other link; and because its hover rule puts a border round the anchor
	// (.ui-state-hover a { border: 1px solid #999999 }) - a border that was not there a moment
	// before, so the tab grows by two pixels under the mouse and everything beside it jumps.
	echo '<style>' .
		'#seating-edit-tabs.ui-tabs { border:none; background:none; padding:0; }' .
		'#seating-edit-tabs .ui-tabs-nav { background:none; border:none; border-bottom:1px solid #c0c0c0; border-radius:0; padding:0; }' .
		'#seating-edit-tabs .ui-tabs-nav li a { text-decoration:none; outline:none; }' .
		'#seating-edit-tabs .ui-tabs-nav li a:hover, #seating-edit-tabs .ui-tabs-nav li a:link, #seating-edit-tabs .ui-tabs-nav li a:visited { text-decoration:none; border:none; }' .
		'#seating-edit-tabs .ui-tabs-panel { border:none; background:none; padding:10px 0 0 0; }' .
		'</style>';

	echo '<div id="seating-edit-tabs">';

	// Named for what each one does rather than for what it is about: a tab called "Players" in a
	// dialog about a seating could mean anything, and these three are three actions.
	echo '<ul>';
	echo '<li><a href="#seating-edit-players">' . get_label('Swap players') . '</a></li>';
	if ($games_count > 1)
	{
		echo '<li><a href="#seating-edit-games">' . get_label('Swap games') . '</a></li>';
	}
	if ($tables_count > 1)
	{
		echo '<li><a href="#seating-edit-tables">' . get_label('Swap tables') . '</a></li>';
	}
	echo '</ul>';

	//-------------------------------------------------------------------------------------------
	// Players
	//-------------------------------------------------------------------------------------------
	echo '<div id="seating-edit-players">';
	echo '<p style="color:gray">' . get_label('Exchange two players: everything they play in this round, or their seats in one game of it.') . '</p>';
	echo '<table class="dialog_form" width="100%">';

	echo '<tr><td width="120">' . get_label('Player [0]', 1) . ':</td><td>';
	echo '<select id="form-player1" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
	foreach ($reg_users as $u)
	{
		echo '<option value="' . $u->id . '"' . ($u->id == $player1_id ? ' selected' : '') . '>' . htmlspecialchars($u->name) . '</option>';
	}
	echo '</select></td></tr>';

	echo '<tr><td>' . get_label('Player [0]', 2) . ':</td><td>';
	echo '<select id="form-player2" style="width:100%"><option value="0">' . get_label('Select player.') . '</option>';
	foreach ($reg_users as $u)
	{
		echo '<option value="' . $u->id . '">' . htmlspecialchars($u->name) . '</option>';
	}
	echo '</select></td></tr>';

	// Swapping in every game exchanges the two players outright, which is what this dialog has
	// always done. Swapping in one game exchanges only their seats there.
	echo '<tr><td>' . get_label('Game~seating') . ':</td><td>';
	echo '<select id="form-game" style="width:100%">';
	echo '<option value="0" selected>' . get_label('All games~seating') . '</option>';
	for ($i = 1; $i <= $games_count; ++$i)
	{
		echo '<option value="' . $i . '">' . get_label('Game [0]~seating', $i) . '</option>';
	}
	echo '</select></td></tr>';

	echo '</table></div>';

	//-------------------------------------------------------------------------------------------
	// Games
	//-------------------------------------------------------------------------------------------
	if ($games_count > 1)
	{
		echo '<div id="seating-edit-games">';
		echo '<p style="color:gray">' . get_label('Exchange two games: everything played in one of them moves to the other.') . '</p>';
		echo '<table class="dialog_form" width="100%">';
		for ($n = 1; $n <= 2; ++$n)
		{
			echo '<tr><td width="120">' . get_label('Game [0]~seating', $n) . ':</td><td>';
			echo '<select id="form-game' . $n . '" style="width:100%">';
			echo '<option value="0">' . get_label('Select a game.') . '</option>';
			for ($i = 1; $i <= $games_count; ++$i)
			{
				echo '<option value="' . $i . '">' . get_label('Game [0]~seating', $i) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '</table></div>';
	}

	//-------------------------------------------------------------------------------------------
	// Tables
	//-------------------------------------------------------------------------------------------
	if ($tables_count > 1)
	{
		echo '<div id="seating-edit-tables">';
		echo '<p style="color:gray">' . get_label('Exchange two tables: in every game or in one of them, whoever sits at one of them moves to the other.') . '</p>';
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
		// The same choice as for the players: in every game, or in one of them only.
		echo '<tr><td>' . get_label('Game~seating') . ':</td><td>';
		echo '<select id="form-table-game" style="width:100%">';
		echo '<option value="0" selected>' . get_label('All games~seating') . '</option>';
		for ($i = 1; $i <= $games_count; ++$i)
		{
			echo '<option value="' . $i . '">' . get_label('Game [0]~seating', $i) . '</option>';
		}
		echo '</select></td></tr>';
		echo '</table></div>';
	}

	echo '</div>';
?>
	<script>
	// Run when the dialog's contents are already in the page: jQuery puts the html in first and
	// the scripts after it, and a ready handler on a page that is long since ready fires on the
	// next turn of the loop, by which time the dialog is up as well.
	$(function() { $("#seating-edit-tabs").tabs(); });

	// Which tab the Ok button belongs to. Asked of the page rather than of the widget, because
	// the tabs that are there depend on what the seating has in it - a seating with one table
	// has no tables tab, and then no index means what it would otherwise mean.
	function seatingEditPane()
	{
		return $("#seating-edit-tabs > div:visible").attr("id");
	}

	//---------------------------------------------------------------------------------------
	// Players. The seat number statistics are the reason this one is not a single post: a
	// swap in one game moves two players between two numbers, and api/get/seating_swap_options
	// finds the further swaps that would put the numbers back.
	//---------------------------------------------------------------------------------------

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
			parts.push(<?php echo json_encode(get_label('Game [0]~seating', '%N%')); ?>.replace('%N%', plan[i].game) +
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

		// Close means do nothing, which leaves the edit dialog underneath still open.
		swapChoiceDlg = dlg.custom(html, <?php echo json_encode(get_label('Swap players')); ?>, 620,
			[{ text: <?php echo json_encode(get_label('Close')); ?>, click: function() { $(this).dialog("close"); swapChoiceDlg = null; } }]);
	}

	function commitPlayers(onSuccess)
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

	//---------------------------------------------------------------------------------------
	// Games and tables. Whole rounds and whole tables moving around each other, which leaves
	// every player with the same games and the same seat numbers - nothing to warn about.
	//---------------------------------------------------------------------------------------
	function commitGames(onSuccess)
	{
		var game1 = parseInt($("#form-game1").val()) || 0;
		var game2 = parseInt($("#form-game2").val()) || 0;
		if (game1 <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please select game [0].', 1)); ?>);
			return;
		}
		if (game2 <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please select game [0].', 2)); ?>);
			return;
		}
		if (game1 == game2)
		{
			dlg.error(<?php echo json_encode(get_label('Games must be different.')); ?>);
			return;
		}
		json.post("api/ops/event.php",
		{
			op: "swap_seating_games",
			event_id: <?php echo $event_id; ?>,
			game1: game1,
			game2: game2,
		},
		onSuccess);
	}

	function commitTables(onSuccess)
	{
		var table1 = parseInt($("#form-table1").val()) || 0;
		var table2 = parseInt($("#form-table2").val()) || 0;
		var game = parseInt($("#form-table-game").val()) || 0;
		if (table1 <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please select table [0].', 1)); ?>);
			return;
		}
		if (table2 <= 0)
		{
			dlg.error(<?php echo json_encode(get_label('Please select table [0].', 2)); ?>);
			return;
		}
		if (table1 == table2)
		{
			dlg.error(<?php echo json_encode(get_label('Tables must be different.')); ?>);
			return;
		}
		json.post("api/ops/event.php",
		{
			op: "swap_seating_tables",
			event_id: <?php echo $event_id; ?>,
			table1: table1,
			table2: table2,
			game: game,
		},
		onSuccess);
	}

	function commit(onSuccess)
	{
		var pane = seatingEditPane();
		if (pane == "seating-edit-games")
		{
			commitGames(onSuccess);
		}
		else if (pane == "seating-edit-tables")
		{
			commitTables(onSuccess);
		}
		else
		{
			commitPlayers(onSuccess);
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
