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
		$url = 'https://dimtom.github.io/web_schedule';

		echo '<input type="file" id="upload-' . $event_id . '" style="display:none" onchange="doUploadDimTom(' . $event_id . ')">';
		echo '<p><table width="100%"><tr><td>';
		if (!$has_seating)
		{
			echo '<button onclick="makeSeating(' . $event_id . ')"><img src="images/create.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Make seating') . '</button>';
			echo ' <button onclick="uploadDimTom(' . $event_id . ')" title="' . get_label('Import seating from [0]', $url) . '"><img src="images/save.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Upload seating file') . '</button>';
		}
		else
		{
			echo '<button onclick="swapPlayers(' . $event_id . ')"><img src="images/user_change.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Swap players') . '</button>';
			echo ' <button onclick="clearSeating(' . $event_id . ', \'' . addslashes(get_label('Are you sure you want to delete the seating?')) . '\')"><img src="images/delete.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Clear seating') . '</button>';

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
		$this->nextButton();
		echo '</td></tr></table>';

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
				'SELECT u.id, nu.name, tr.flags, u.flags FROM users u' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & ?) <> 0' .
				' LEFT JOIN tournament_regs tr ON tr.user_id = u.id AND tr.tournament_id = ?' .
				' WHERE u.id IN (' . $ids_sql . ')',
				$_lang, $this->prep_tournament_id
			);
			while ($row = $q->next())
			{
				$u = new stdClass();
				$u->name = $row[1];
				$u->tr_flags = (int)$row[2];
				$u->flags = (int)$row[3];
				$users[(int)$row[0]] = $u;
			}
		}

		$pic = new Picture(USER_TOURNAMENT_PICTURE, $this->user_pic);

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
						$pic->set($uid, $u->name, $u->tr_flags, 't' . $this->prep_tournament_id)->set($uid, $u->name, $u->flags);
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

	// Where the broadcasts of the tournament are set up, round by round. One table of one round
	// can have several of them: YouTube will not take a stream of unlimited length, and a round
	// can carry over to the next day. They are told apart by the day and the part, and a game
	// goes to whichever of them was on the air when it started.
	//
	// Beside each one is what the broadcast says about itself - when it went on the air, how
	// long the recording is - and whether that agrees with the interruptions we measured. The
	// agreement is what says the links into it can be trusted; see include/broadcast.php.
	private function showStreamsTab()
	{
		require_once 'include/broadcast.php';

		echo '<p align="right">';
		$this->nextButton();
		echo '</p>';

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
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="58"><button class="icon" onclick="mr.eventCreateBroadcast(' . $event_id . ')" title="' . get_label('Create broadcast for [0]', $named) . '"><img src="images/create.png"></button></td>';
			echo '<td width="46" align="center">' . get_label('Day') . '</td>';
			echo '<td width="52" align="center">' . get_label('Table') . '</td>';
			echo '<td width="46" align="center">' . get_label('Part') . '</td>';
			echo '<td>' . get_label('URL') . '</td>';
			echo '<td width="150">' . get_label('On the air') . '</td>';
			echo '<td width="110">' . get_label('Interruptions') . '</td>';
			echo '<td width="110">' . get_label('Out by') . '</td>';
			echo '<td width="100">' . get_label('Correction') . '</td></tr>';

			$rows = 0;
			$query = new DbQuery('SELECT DISTINCT table_num FROM event_broadcasts WHERE event_id = ? ORDER BY table_num', $event_id);
			$tables = array();
			while ($row = $query->next())
			{
				// Counted from one from here on, as the games count them; the table they are
				// read out of counts them from zero.
				list ($t) = $row;
				$tables[] = (int)$t + 1;
			}
			foreach ($tables as $table_num)
			{
				// Asked of the broadcast itself here, where somebody is looking at the page and
				// a moment's wait is no trouble. Nothing else on the site does the asking.
				foreach (broadcast_list($event_id, $table_num) as $b)
				{
					++$rows;
					echo '<tr><td><button class="icon" onclick="mr.eventDeleteBroadcast(' . $event_id . ',' . $b->day_num . ',' . ($b->table_num - 1) . ',' . $b->part_num . ', \'' . get_label('Are you sure you want to remove the broadcast?') . '\')"><img src="images/delete.png"></button>';
					echo '<button class="icon" onclick="mr.eventEditBroadcast(' . $event_id . ',' . $b->day_num . ',' . ($b->table_num - 1) . ',' . $b->part_num . ')"><img src="images/edit.png"></button></td>';
					echo '<td align="center">' . $b->day_num . '</td>';
					echo '<td align="center">' . $b->table_num . '</td>';
					echo '<td align="center">' . $b->part_num . '</td>';
					echo '<td><a href="' . htmlspecialchars($b->url) . '" target="_blank">' . htmlspecialchars($b->url) . '</a></td>';

					if (is_null($b->start))
					{
						echo '<td>&mdash;</td><td>&mdash;</td><td>&mdash;</td><td>&mdash;</td></tr>';
						continue;
					}
					echo '<td>' . date('Y-m-d H:i', $b->start) . '</td>';

					// How long this table was reported to be off the air. Only what OBS said
					// is in here - see include/broadcast.php - so it is a floor rather than a
					// total, and the column beside it says how far short it falls.
					$until = is_null($b->end) ? time() : $b->end;
					$measured = broadcast_lost_seconds($owner, $b->table_num, $b->start, $until);
					echo '<td>' . format_lost_time($measured) . '</td>';

					// And what the recording says about the same thing. It is shorter than the
					// wall clock by exactly the time cut out of it, so the two should come to
					// the same; where they do not, that is how far the links into this
					// recording may be out.
					$out_by = get_label('How far the length of the recording and the breaks we measured disagree. The links into this recording may be out by that much.');
					$difference = broadcast_difference($owner, $b);
					if (is_null($difference))
					{
						echo '<td title="' . $out_by . '">&mdash;</td>';
					}
					else
					{
						$text = ($difference > 0 ? '+' : ($difference < 0 ? '&minus;' : '')) .
							format_lost_time(abs($difference));
						if (abs($difference) > BROADCAST_TOLERANCE_SECONDS)
						{
							$text = '<b class="stream-out-by">' . $text . '</b>';
						}
						echo '<td title="' . $out_by . '">' . $text . '</td>';
					}

					// What somebody taught this recording by moving a game of it to where it really
					// begins. Everything placed on it from then on is moved by the same amount, so
					// it is worth seeing that it is there.
					$shift = isset($b->shift) ? (int)$b->shift : 0;
					$shift_title = get_label('Every link into this recording is moved by this much: it is how long the stream was delayed, since the time YouTube stamps on a broadcast is the moment the viewers saw the picture rather than the moment it was made. It is measured off the finished recording, some hours after the broadcast ends; until then the recording borrows what this table was delayed by last time. Moving a game to where it really begins overrules both.');
					echo '<td title="' . $shift_title . '">' . ($shift == 0 ? '&mdash;' :
						(($shift > 0 ? '+' : '&minus;') . format_lost_time(abs($shift)))) . '</td></tr>';
				}
			}
			if ($rows == 0)
			{
				echo '<tr><td colspan="9">' . get_label('No broadcasts yet.') . '</td></tr>';
			}
			echo '</table>';

			// A game played now is tied to its broadcast as it is written down, so this is for
			// a round that was played before the addresses went in - an old tournament, or one
			// where somebody added the links afterwards. Nothing that already has a video is
			// touched, so it can be pressed twice without harm.
			if ($rows > 0)
			{
				echo '<p align="right"><button onclick="bindBroadcastGames(' . $event_id . ')">' .
					get_label('Tie the games to the broadcasts') . '</button></p>';
			}
		}
	}
}

?>
