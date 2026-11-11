<?php

require_once __DIR__ . '/tournament.php';   // get_round_name()
require_once __DIR__ . '/seating.php';
require_once __DIR__ . '/broadcast.php';

// The parts of getting ready that a tournament and a single event do in exactly the same way.
//
// A tournament is prepared round by round, and a round of it is a perfectly good event in its
// own right - so the two pages are not alike by accident, they are looking at the same rows.
// The broadcasts of a round and its seating are kept here so that there is one of each rather
// than two that have to be remembered about together. Registrations are not: a tournament is
// entered once for all its rounds, while an event - round or not - records who turned up to
// it, and those are two different lists.
//
// What the page using this has to provide:
//   $this->id             the tournament, or the event when it stands on its own;
//   $this->prep_tournament_id   the tournament these rounds belong to, 0 when there is none;
//   $this->rounds         the rounds to show, as [event_id, round, misc, players, tables, games];
//   $this->club_id, $this->user_pic, $this->tab, $this->tab_count,
//   $this->scheme_valid, $this->regs_complete.
trait PreparationTabs
{
	// The scripts behind the two shared tabs. A page prints these inside its own js(),
	// where refr(), json and dlg are already to hand.
	protected function prepScripts()
	{
?>
		function bindBroadcastGames(eventId)
		{
			json.post('api/ops/event.php',
			{
				op: 'bind_broadcast_games',
				event_id: eventId,
			},
			function(data)
			{
				var text = '<?php echo get_label('Games tied to a broadcast'); ?>: ' + data.bound +
					'<br><?php echo get_label('Games that had a video already'); ?>: ' + data.had +
					'<br><?php echo get_label('Games still without one'); ?>: ' + data.unplaced;
				if (data.untabled > 0)
				{
					// Old enough games do not say which table they were played at, and when
					// the round was broadcast from more than one there is no telling.
					text += '<br><?php echo get_label('Games that do not say which table they were played at'); ?>: ' + data.untabled;
				}
				dlg.info(text, '<?php echo get_label('Streams'); ?>', null, refr);
			});
		}

		function makeSeating(eventId)
		{
			json.post('api/ops/event.php',
			{
				op: 'set_seating',
				event_id: eventId,
			},
			function(data)
			{
				// Offer to optimize only a seating the optimizer has never worked on. One taken
				// from the table has had hours of it already, and spending the user's ten
				// minutes redoing that would be a poor trade.
				if (data && data.seating_hash &&
					(data.seating_status == 'new' || data.seating_status == 'similar'))
				{
					mr.offerFullOptimization(data.seating_hash, eventId, data.seating_status, refr);
					return;
				}
				refr();
			});
		}

		function clearSeating(eventId, confirmMessage)
		{
			dlg.yesNo(confirmMessage, null, null, function()
			{
				json.post('api/ops/event.php',
				{
					op: 'clear_seating',
					event_id: eventId,
				}, refr);
			});
		}
		function swapPlayers(eventId, userId)
		{
			var url = "form/seating_swap.php?event_id=" + eventId;
			if (userId) url += "&player1_id=" + userId;
			dlg.form(url, refr, 400);
		}

		function swapGames(eventId)
		{
			dlg.form("form/seating_games_swap.php?event_id=" + eventId, refr, 400);
		}

		function swapTables(eventId)
		{
			dlg.form("form/seating_tables_swap.php?event_id=" + eventId, refr, 400);
		}

		function uploadDimTom(eventId)
		{
			$('#upload-' + eventId).trigger('click');
		}

		function doUploadDimTom(eventId)
		{
			json.upload('api/ops/event.php',
			{
				op: "import_dimtom",
				event_id: eventId,
				file: document.getElementById("upload-" + eventId).files[0]
			},
			2097152,
			function(data)
			{
				if (data && isSet(data.confirm))
				{
					dlg.yesNo(data.confirm, null, null, function()
					{
						json.upload('api/ops/event.php',
						{
							op: "import_dimtom",
							event_id: eventId,
							update_event: 1,
							file: document.getElementById("upload-" + eventId).files[0]
						},
						2097152,
						refr);
					});
				}
				else
				{
					refr();
				}
			});
		}
<?php
	}

	// Whether the step forward is barred from where we are standing. Nothing bars it by
	// default; a page with tabs that are locked until something is done says so itself, since
	// only it knows what its tabs are and in what order.
	protected function nextDisabled()
	{
		return false;
	}

	// The way through the tabs: Next at the right of the row the tab puts it in, and Back at the
	// far left of the same row - backCell() is the first cell of it. Back is never held back:
	// what was done there is done.
	private function backCell()
	{
		$html = '<td width="1" nowrap>';
		if ($this->tab > 0)
		{
			$html .= '<button id="prep-back-btn" onclick="goToTab(' . ($this->tab - 1) . ')"><img src="images/prev.png" border="0" style="vertical-align:middle">&nbsp; ' . get_label('Back') . '</button>&nbsp; ';
		}
		return $html . '</td>';
	}

	// A row with nothing but the two of them.
	private function navBar()
	{
		echo '<p><table width="100%"><tr>' . $this->backCell() . '<td align="right">';
		$this->nextButton();
		echo '</td></tr></table></p>';
	}

	private function nextButton()
	{
		if ($this->tab < $this->tab_count - 1)
		{
			$disabled = $this->nextDisabled() ? ' disabled' : '';
			echo '<button id="prep-next-btn" onclick="nextStep()"' . $disabled . '>' . get_label('Next') . ' &nbsp;<img src="images/next.png" border="0" style="vertical-align:middle"></button>';
		}
	}

	private function showSeatingTab($round_index)
	{
		global $_lang;

		list($event_id, $round_num, $misc_str) = $this->rounds[$round_index];
		$misc = json_decode($misc_str);

		$has_seating = !empty($misc->seating);

		// How much there is to move around. A seating with a single game has no two games to
		// swap and one with a single table no two tables, so those buttons would only open a
		// dialog that refuses. Tables are counted as the least any game has: a few seatings do
		// not run the same number throughout, and a table missing from some games cannot be
		// swapped - see form/seating_tables_swap.php.
		$games_count = 0;
		$tables_count = 0;
		if ($has_seating && isset($misc->seating->rounds) && is_array($misc->seating->rounds))
		{
			$games_count = count($misc->seating->rounds);
			if ($games_count > 0)
			{
				$tables_count = PHP_INT_MAX;
				foreach ($misc->seating->rounds as $round)
				{
					$tables_count = min($tables_count, is_array($round) ? count($round) : 0);
				}
			}
		}
		$url = 'https://dimtom.github.io/web_schedule';

		echo '<input type="file" id="upload-' . $event_id . '" style="display:none" onchange="doUploadDimTom(' . $event_id . ')">';
		// Back and Next in a row of their own, and what can be done to the seating under them.
		// Clearing it stands apart at the right, away from the buttons that work on it.
		$this->navBar();
		echo '<p><table width="100%"><tr><td>';
		if (!$has_seating)
		{
			echo '<button onclick="makeSeating(' . $event_id . ')"><img src="images/create.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Make seating') . '</button>';
			echo ' <button onclick="uploadDimTom(' . $event_id . ')" title="' . get_label('Import seating from [0]', $url) . '"><img src="images/save.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Upload seating file') . '</button>';
		}
		else
		{
			echo '<button onclick="swapPlayers(' . $event_id . ')"><img src="images/user_change.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Swap players') . '</button>';
			if ($games_count > 1)
			{
				echo ' <button onclick="swapGames(' . $event_id . ')"><img src="images/table.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Swap games') . '</button>';
			}
			if ($tables_count > 1)
			{
				echo ' <button onclick="swapTables(' . $event_id . ')"><img src="images/table.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Swap tables') . '</button>';
			}

			$stored_hash    = isset($misc->seating->hash)    ? $misc->seating->hash    : null;
			$stored_version = isset($misc->seating->version) ? $misc->seating->version : null;
			$current_version = null;
			// Arranged by hand: taking an improved version would replace the whole arrangement
			// and throw the edit away, so that button is not offered. Same rule as on
			// tournament_seating.php.
			$is_manual = isset($misc->seating->manual) && $misc->seating->manual;

			if (!is_null($stored_hash))
			{
				$srow = (new DbQuery(
					'SELECT players_runs, players_void_runs, tables_runs, tables_void_runs, numbers_runs, numbers_void_runs FROM seatings WHERE hash = ?',
					$stored_hash))->next();
				if ($srow)
				{
					list($pr, $pvr, $tr, $tvr, $nr, $nvr) = $srow;
					$current_version = ($pr - $pvr) . '.' . ($tr - $tvr) . '.' . ($nr - $nvr);
					if (!$is_manual && !is_null($stored_version) && $stored_version !== $current_version && ($nr - $nvr) != 0)
					{
						$title = htmlspecialchars(get_label('An improved seating version [0] is available.', $current_version));
						echo ' <button onclick="makeSeating(' . $event_id . ')" title="' . $title . '"><img src="images/refresh.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Update seating') . '</button>';
					}
				}
			}
		}
		echo '</td><td align="right">';
		if ($has_seating)
		{
			echo '<button onclick="clearSeating(' . $event_id . ', \'' . addslashes(get_label('Are you sure you want to delete the seating?')) . '\')"><img src="images/delete.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Clear seating') . '</button>';
		}
		echo '</td></tr></table></p><p>';

		if (!empty($misc->seating) && !is_null($stored_hash))
		{
			echo '<small style="color:gray"><a href="#" onclick="mr.optimizeSeating(null, \'' . addslashes($stored_hash) . '\'); return false;" title="' . get_label('Optimize seating') . '">' . htmlspecialchars($stored_hash);
			if (!is_null($current_version))
				echo ' &nbsp; v' . htmlspecialchars($current_version);
			echo '</a></small>';
		}
		echo '</p>';

		if (empty($misc->seating))
		{
			return;
		}

		$seating = $misc->seating;
		$tables_data = $seating->rounds;
		$raw_mapping = isset($seating->mapping) ? $seating->mapping : null;

		// Build index → user_id lookup from mapping
		$index_to_uid = array();
		if (!is_null($raw_mapping))
		{
			foreach ($raw_mapping as $idx => $entry)
			{
				if (is_numeric($entry))
					$index_to_uid[$idx] = (int)$entry;
				elseif (is_object($entry) && isset($entry->id))
					$index_to_uid[$idx] = (int)$entry->id;
				else
					$index_to_uid[$idx] = 0;
			}
		}

		// Collect real user IDs for player info lookup
		$user_ids = array();
		foreach ($tables_data as $table)
			foreach ($table as $game)
				foreach ($game as $raw)
				{
					$uid = is_null($raw_mapping) ? (int)$raw : (isset($index_to_uid[(int)$raw]) ? $index_to_uid[(int)$raw] : 0);
					if ($uid > 0) $user_ids[$uid] = true;
				}

		$users = array();
		if (!empty($user_ids))
		{
			$ids_sql = implode(',', array_keys($user_ids));
			$q = new DbQuery(
				'SELECT u.id, nu.name, tr.flags, u.flags, cr.flags FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ?) <> 0' .
				' LEFT JOIN tournament_regs tr ON tr.user_id = u.id AND tr.tournament_id = ?' .
				// The club running this can have a photo of the player of its own, between the
				// tournament's and the account's - see form/user_custom_photo.php.
				' LEFT JOIN club_regs cr ON cr.user_id = u.id AND cr.club_id = ' . (int)$this->club_id .
				' WHERE u.id IN (' . $ids_sql . ')',
				$_lang, $this->prep_tournament_id
			);
			while ($row = $q->next())
			{
				$u = new stdClass();
				$u->name = $row[1];
				$u->tr_flags = (int)$row[2];
				$u->flags = (int)$row[3];
				$u->cr_flags = (int)$row[4];
				$users[(int)$row[0]] = $u;
			}
		}

		$pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));

		$num_rounds = count($tables_data);
		$num_tables = $num_rounds > 0 ? count($tables_data[0]) : 0;
		for ($t_idx = 0; $t_idx < $num_tables; ++$t_idx)
		{
			echo '<p><center><h2>' . get_label('Table [0]', $t_idx + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; ++$k)
				echo '<td width="9.2%" align="center"><b>' . ($k + 1) . '</b></td>';
			echo '</tr>';
			for ($g_idx = 0; $g_idx < $num_rounds; ++$g_idx)
			{
				$game = isset($tables_data[$g_idx][$t_idx]) ? $tables_data[$g_idx][$t_idx] : null;
				if (is_null($game) || count((array)$game) < 10) continue;
				echo '<tr><td align="center" class="dark"><b>' . get_label('Game [0]', $g_idx + 1) . '</b></td>';
				for ($k = 0; $k < 10; ++$k)
				{
					$raw = (int)$game[$k];
					$uid = is_null($raw_mapping) ? $raw : (isset($index_to_uid[$raw]) ? $index_to_uid[$raw] : 0);
					if ($uid > 0 && isset($users[$uid]))
					{
						$u = $users[$uid];
						echo '<td align="center" style="cursor:pointer" onclick="swapPlayers(' . $event_id . ', ' . $uid . ')">';
						echo '<table class="transp" width="100%">';
						echo '<tr><td align="center">';
						$pic->set($uid, $u->name, $u->tr_flags, 't' . $this->prep_tournament_id)->
							set($uid, $u->name, $u->cr_flags, 'c' . $this->club_id)->
							set($uid, $u->name, $u->flags);
						$pic->show(ICONS_DIR, false, 48);
						echo '</td></tr>';
						echo '<tr><td align="center">' . htmlspecialchars($u->name) . '</td></tr>';
						echo '</table>';
					}
					else
					{
						echo '<td align="center">';
						echo '#' . ($raw + 1);
					}
					echo '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}

	// Where the broadcasts of the tournament are set up, round by round. The table itself is
	// drawn by show_broadcast_table(), which the stream settings page shows as well - see
	// include/broadcast_table.php, where what stands in it is described.
	private function showStreamsTab()
	{
		require_once 'include/broadcast_table.php';

		$this->navBar();

		// What this tab is not. Here are the recordings - where to find afterwards what was
		// broadcast - while everything about the broadcast as it happens belongs to a table
		// rather than to a round, and is set up on a page of its own. Somebody getting a
		// tournament ready has to go there too, and until now nothing said so.
		//
		// A round of a tournament is streamed at the tournament's tables, so the link goes to
		// the tournament wherever there is one; that is the pairing stream_owner() makes, and
		// the same one the Management menu uses.
		//
		// bck=1 so that the page it leads to carries a way back here. Getting ready is a walk
		// through tabs in an order, and this is a step out of it.
		$url = ($this->prep_tournament_id > 0)
			? ('streams.php?bck=1&tournament_id=' . $this->prep_tournament_id)
			: ('streams.php?bck=1&event_id=' . $this->id);
		echo '<p>' . get_label(
			'Everything else about a stream - the addresses to point OBS Studio at, the language, the scenes and what the cards show - is set up table by table on the [0] page.',
			'<a href="' . $url . '">' . get_label('Stream settings') . '</a>') . '</p>';

		foreach ($this->rounds as $round_row)
		{
			list ($event_id, $round_num) = $round_row;
			$owner = stream_owner($this->prep_tournament_id, $event_id);
			// Named only where there is more than one of them. On the page of a single event
			// the title of the page has already said which round this is.
			$named = (count($this->rounds) > 1) ? get_round_name($round_num) : $this->name;
			if (count($this->rounds) > 1)
			{
				echo '<h3 class="stream-section">' . $named . '</h3>';
			}
			$rows = show_broadcast_table($owner, $event_id, $named);

			// A game played now is tied to its broadcast as it is written down, so this is for
			// a round that was played before the addresses went in - an old tournament, or one
			// where somebody added the links afterwards. Nothing that already has a video is
			// touched, so it can be pressed twice without harm.
			//
			// It is offered only where there is something for it to do: addresses to tie games
			// to, and games not tied to one yet. Otherwise pressing it reads every game of the
			// round to report that it tied none of them, which is a button that does nothing.
			if ($rows > 0 && broadcast_unlinked_games($event_id) > 0)
			{
				echo '<p align="right"><button onclick="bindBroadcastGames(' . $event_id . ')">' .
					get_label('Tie the games to the broadcasts') . '</button></p>';
			}
		}
	}
}

?>
