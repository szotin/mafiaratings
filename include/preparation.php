<?php

require_once __DIR__ . '/tournament.php';   // get_round_name()
require_once __DIR__ . '/seating.php';
require_once __DIR__ . '/broadcast.php';
require_once __DIR__ . '/details_tab.php';

// PREP_TAB_DETAILS and PREP_TAB_SERIES, the tabs of the fields of the thing itself, are defined in
// include/details_tab.php: create.php opens them too.

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
		// Every way a seating that is already there can be rearranged, in one dialog with a tab
		// for each: players, games, tables. A player clicked in the grid below arrives as the
		// first of the two to exchange, and the dialog opens on the players tab, where he is.
		function editSeating(eventId, userId)
		{
			var url = "form/seating_edit.php?event_id=" + eventId;
			if (userId)
			{
				url += "&player1_id=" + userId;
			}
			dlg.form(url, refr, 650);
		}

		function uploadSeating(eventId)
		{
			// Emptied first, so that choosing the same file again still counts as a change: a sheet
			// that was refused and then mended comes back under the name it went out under.
			document.getElementById('upload-' + eventId).value = '';
			$('#upload-' + eventId).trigger('click');
		}

		// A seating file may be asked about twice before it is applied, and the two questions
		// belong to different sides. Whether to throw away a seating that is already there is
		// asked here, before anything is sent, because only the page knows there is one to lose.
		// Whether to move the round to fit a file made for a different number of tables comes
		// back from the server, because only the server knows what the round is set up for.
		function doUploadSeating(eventId, replace)
		{
			if (!document.getElementById("upload-" + eventId).files[0])
			{
				return;
			}
			if (replace)
			{
				dlg.yesNo('<?php echo addslashes(get_label('This round already has a seating. Replace it with the one in the file?')); ?>',
					null, null, function() { sendSeating(eventId, 0); });
				return;
			}
			sendSeating(eventId, 0);
		}

		function sendSeating(eventId, updateEvent)
		{
			var request =
			{
				op: "import_seating",
				event_id: eventId,
				file: document.getElementById("upload-" + eventId).files[0]
			};
			if (updateEvent)
			{
				request.update_event = 1;
			}
			json.upload('api/ops/event.php', request, 2097152, function(data)
			{
				if (data && isSet(data.confirm))
				{
					dlg.yesNo(data.confirm, null, null, function() { sendSeating(eventId, 1); });
				}
				else
				{
					refr();
				}
			});
		}

		// The seating as a file to keep. The dialog asks which of the three kinds of file it is
		// to be and sends the browser to the page that writes it; what comes back from there is
		// marked as something to save rather than to show, so this page stays where it is. See
		// form/seating_export.php and seating_export.php.
		//
		// Nothing on the page changes by saving a file, so the dialog closes onto what is
		// already there instead of reloading it.
		function exportSeating(eventId)
		{
			dlg.form('form/seating_export.php?event_id=' + eventId, function() {}, 400);
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

	// Whether the tab after this one cannot be opened - the seating before there is a scheme. There
	// is nowhere further to go then, as on the last tab, and Next becomes Finish.
	protected function nextUnavailable()
	{
		return false;
	}

	// Where Finish leads: the page of the thing being prepared.
	protected function finishUrl()
	{
		return 'index.php';
	}

	// Back, to the tab before. It is never held back: what was done there is done.
	private function backButton()
	{
		$prev = $this->prevTab();
		return is_null($prev) ? '' : wizard_back_button('goToTab(' . $prev . ')');
	}

	// The steps of the card (see include/details_tab.php): $tabs is an array of tab number =>
	// array(name, whether it can be opened), in their order.
	private function wizardBegin($tabs)
	{
		$steps = array();
		$cur = 0;
		foreach ($tabs as $idx => $tab)
		{
			list($name, $open) = $tab;
			if ($idx == $this->tab)
			{
				$cur = count($steps);
			}
			$steps[] = array($name, $open ? 'goToTab(' . $idx . ')' : NULL);
		}
		wizard_begin($steps, $cur);
	}

	// The end of the card: Back and Next on the bar at its foot, if the tab asked for them with
	// navBar(), and the summary beside it.
	private function wizardEnd($summary)
	{
		if ($this->wz_nav)
		{
			wizard_end($this->backButton(), $this->nextButtonHtml(), $summary);
		}
		else
		{
			wizard_end('', '', $summary);
		}
	}

	// The tab "Basics" - the fields of the thing itself, those of the dialog that edits it (see
	// include/details_tab.php) - stands before all the others; see PREP_TAB_DETAILS.
	protected function canEditDetails()
	{
		return false;
	}

	// The first of the other tabs this user may open.
	protected function firstTab()
	{
		return 0;
	}

	// The last of the tabs of the fields of the thing: "Basics", or a tab of its own after it.
	protected function lastDetailsTab()
	{
		return PREP_TAB_DETAILS;
	}

	// Whether this user has tab $tab. A page where a tab in the middle is somebody's alone - the
	// mailing of an event is its managers' - says so, and Back and Next step over it.
	protected function hasTab($tab)
	{
		return true;
	}

	// Where Next goes from here.
	protected function nextTab()
	{
		$next = $this->tab + 1;
		while ($next < $this->tab_count - 1 && !$this->hasTab($next))
		{
			++$next;
		}
		return $next;
	}

	// Where Back goes from here, NULL when nowhere.
	private function prevTab()
	{
		if ($this->tab > $this->firstTab())
		{
			$prev = $this->tab - 1;
			while ($prev > $this->firstTab() && !$this->hasTab($prev))
			{
				--$prev;
			}
			return $prev;
		}
		if ($this->canEditDetails())
		{
			if ($this->tab == $this->firstTab())
			{
				return $this->lastDetailsTab();
			}
			if ($this->tab > PREP_TAB_DETAILS && $this->tab <= $this->lastDetailsTab())
			{
				return $this->tab - 1;
			}
		}
		return NULL;
	}

	// A tab of the fields of the thing: the form, and Next under it, which saves it and goes on.
	// $form_url is the dialog that edits the thing; $only and $without pick the rows of it the tab
	// shows - see show_details_form().
	private function showDetailsTab($form_url, $only = NULL, $without = NULL)
	{
		show_details_form($form_url, $only, $without);
		$this->navBar();
	}

	// Back and Next, on the bar at the foot of the card - see wizardEnd(). Every tab that has them
	// has them there, whatever else it has.
	private $wz_nav = false;
	private function navBar()
	{
		$this->wz_nav = true;
	}

	// Next, or Finish where there is nowhere further to go. The thing exists from its first tab on, so
	// whatever is left can always be left for later: Finish is never held back.
	private function nextButtonHtml()
	{
		if ($this->tab < $this->tab_count - 1 && !$this->nextUnavailable())
		{
			return wizard_next_button(get_label('Next'), 'nextStep()', 'prep-next-btn', $this->nextDisabled());
		}
		return wizard_next_button(get_label('Finish'), 'goTo(' . htmlspecialchars(json_encode($this->finishUrl()), ENT_QUOTES) . ')', 'prep-finish-btn');
	}

	private function showSeatingTab($round_index)
	{
		global $_lang;

		list($event_id, $round_num, $misc_str) = $this->rounds[$round_index];
		$misc = json_decode($misc_str);

		$has_seating = !empty($misc->seating);

		$url = 'https://dimtom.github.io/web_schedule';

		// Whether a seating is already there is settled when the page is drawn, and the file
		// picker carries it: by the time a file has been chosen there is nothing left to ask the page.
		echo '<input type="file" id="upload-' . $event_id . '" style="display:none" onchange="doUploadSeating(' . $event_id . ', ' . ($has_seating ? 1 : 0) . ')">';
		// What the seating is named in the seatings table, and whether a better version of it has
		// turned up since. Looked up before anything is drawn, because both the Update button and
		// the line under the buttons are made of it.
		$stored_hash = null;
		$current_version = null;
		$has_improved = false;
		if ($has_seating)
		{
			$stored_hash    = isset($misc->seating->hash)    ? $misc->seating->hash    : null;
			$stored_version = isset($misc->seating->version) ? $misc->seating->version : null;
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
					$has_improved = !$is_manual && !is_null($stored_version) && $stored_version !== $current_version && ($nr - $nvr) != 0;
				}
			}
		}

		// Back and Next in a row of their own, and what can be done to the seating under them,
		// in one row: on the left what is done to the seating as a whole - thrown away, read in
		// from a file, written out to one - and on the right the one button that opens the
		// seating itself for rearranging. Three buttons all saying "swap" stood here before, and
		// none of them said what the dialog behind it would ask; they are tabs of one dialog now,
		// see form/seating_edit.php.
		$this->navBar();
		echo '<div class="seat-bar"><div>';

		$buttons = array();
		if (!$has_seating)
		{
			$buttons[] = '<button onclick="makeSeating(' . $event_id . ')"><img src="images/create.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Make seating') . '</button>';
		}
		else
		{
			$buttons[] = '<button onclick="clearSeating(' . $event_id . ', \'' . addslashes(get_label('Are you sure you want to delete the seating?')) . '\')"><img src="images/delete.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Clear seating') . '</button>';
		}
		// Uploading stands whether there is a seating or not. A file can be uploaded over one that
		// is already there - that is how a sheet comes back after being moved about in a
		// spreadsheet - and doUploadSeating asks before the old one is thrown away.
		$buttons[] = '<button onclick="uploadSeating(' . $event_id . ')" title="' . htmlspecialchars(get_label('Upload a seating: either a file from [0] or one exported from here.', $url)) . '"><img src="images/upload_file.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Upload file') . '</button>';
		if ($has_seating)
		{
			$buttons[] = '<button onclick="exportSeating(' . $event_id . ')" title="' . htmlspecialchars(get_label('Save the seating as a file: a sheet of nicknames to read and to hand back, or a schedule for [0].', 'DimTom')) . '"><img src="images/download.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Download file') . '</button>';
		}
		// Last, and only when there is one to take: a better version of this very arrangement has
		// turned up in the seatings table since it was taken from there.
		if ($has_improved)
		{
			$title = htmlspecialchars(get_label('An improved seating version [0] is available.', $current_version));
			$buttons[] = '<button onclick="makeSeating(' . $event_id . ')" title="' . $title . '"><img src="images/refresh.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Update seating') . '</button>';
		}
		echo implode('', $buttons);

		echo '</div>';
		if ($has_seating)
		{
			echo '<button onclick="editSeating(' . $event_id . ')" title="' . htmlspecialchars(get_label('Rearrange the seating: exchange two players, two games or two tables.')) . '"><img src="images/edit.png" border="0" style="vertical-align:middle"> &nbsp;' . get_label('Edit seating') . '</button>';
		}
		echo '</div><p>';

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
			// Ten seats across must fit the card: see .seat-grid in new_look.css.
			echo '<table class="bordered light seat-grid" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; ++$k)
				echo '<td width="9.2%" align="center"><b>' . ($k + 1) . '</b></td>';
			echo '</tr>';
			for ($g_idx = 0; $g_idx < $num_rounds; ++$g_idx)
			{
				$game = isset($tables_data[$g_idx][$t_idx]) ? $tables_data[$g_idx][$t_idx] : null;
				if (is_null($game) || count((array)$game) < 10) continue;
				echo '<tr><td align="center" class="dark"><b>' . get_label('Game [0]~seating', $g_idx + 1) . '</b></td>';
				for ($k = 0; $k < 10; ++$k)
				{
					$raw = (int)$game[$k];
					$uid = is_null($raw_mapping) ? $raw : (isset($index_to_uid[$raw]) ? $index_to_uid[$raw] : 0);
					if ($uid > 0 && isset($users[$uid]))
					{
						$u = $users[$uid];
						echo '<td align="center" style="cursor:pointer" onclick="editSeating(' . $event_id . ', ' . $uid . ')">';
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
