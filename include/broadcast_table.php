<?php

require_once __DIR__ . '/broadcast.php';

// The broadcasts of one round, drawn as a table that can be added to, changed and taken from.
//
// Two pages show it. The preparation of a tournament or an event, where a round is got ready,
// and the stream settings, where the links stand under everything that points OBS Studio at
// the same tables. They are the same rows written by the same operations, so they are drawn by
// the same code rather than by two copies of it that would have to be remembered about
// together.
//
// One table of one round can have several of them: YouTube will not take a stream of unlimited
// length, and a round can carry over to the next day. They are told apart by the day and the
// part, and a game goes to whichever of them was on the air when it started.
//
// Beside each one is what the broadcast says about itself - when it went on the air, how long
// the recording is - and whether that agrees with the interruptions we measured. The agreement
// is what says the links into it can be trusted; see include/broadcast.php.
//
//   $owner      the tournament or the event the stream settings hang under - see stream_owner().
//   $event_id   the round whose broadcasts these are.
//   $named      what this round is called, for the tooltip of the button that adds one.
//   $only_table 0 for every table of the round. A table number lists that table alone, and the
//               button that adds a broadcast fills that table in and does not let it be
//               changed: the page showing this is the page of that one table.
//
// Returns how many broadcasts were printed.
function show_broadcast_table($owner, $event_id, $named, $only_table = 0)
{
	$add = 'mr.eventCreateBroadcast(' . $event_id . ($only_table > 0 ? ', ' . $only_table : '') . ')';

	echo '<table class="bordered light" width="100%">';
	echo '<tr class="th darker"><td width="58"><button class="icon" onclick="' . $add . '" title="' .
		htmlspecialchars(get_label('Create broadcast for [0]', $named)) . '"><img src="images/create.png"></button></td>';
	echo '<td width="46" align="center">' . get_label('Day') . '</td>';
	echo '<td width="52" align="center">' . get_label('Table') . '</td>';
	echo '<td width="46" align="center">' . get_label('Part') . '</td>';
	echo '<td>' . get_label('URL') . '</td>';
	echo '<td width="150">' . get_label('On the air') . '</td>';
	echo '<td width="110">' . get_label('Interruptions') . '</td>';
	echo '<td width="110">' . get_label('Out by') . '</td>';
	echo '<td width="100">' . get_label('Correction') . '</td></tr>';

	// Which tables to walk. One of them where this is the page of one table; otherwise every
	// table that has a broadcast written down against it, because a table with none has nothing
	// to show and the button above adds to whichever table is named in the dialog.
	$tables = array();
	if ($only_table > 0)
	{
		$tables[] = $only_table;
	}
	else
	{
		$query = new DbQuery('SELECT DISTINCT table_num FROM event_broadcasts WHERE event_id = ? ORDER BY table_num', $event_id);
		while ($row = $query->next())
		{
			// Counted from one from here on, as the games count them; the table they are read
			// out of counts them from zero.
			list ($t) = $row;
			$tables[] = (int)$t + 1;
		}
	}

	$rows = 0;
	foreach ($tables as $table_num)
	{
		// Asked of the broadcast itself here, where somebody is looking at the page and a
		// moment's wait is no trouble. Nothing else on the site does the asking.
		foreach (broadcast_list($event_id, $table_num) as $b)
		{
			++$rows;
			echo '<tr><td><button class="icon" onclick="mr.eventDeleteBroadcast(' . $event_id . ',' . $b->day_num . ',' . ($b->table_num - 1) . ',' . $b->part_num . ', \'' . get_label('Are you sure you want to remove the broadcast?') . '\')"><img src="images/delete.png"></button>';
			echo '<button class="icon" onclick="mr.eventEditBroadcast(' . $event_id . ',' . $b->day_num . ',' . ($b->table_num - 1) . ',' . $b->part_num . ')"><img src="images/edit.png"></button></td>';
			echo '<td align="center">' . $b->day_num . '</td>';
			echo '<td align="center">' . $b->table_num . '</td>';
			echo '<td align="center">' . $b->part_num . '</td>';

			// The address itself, and beside it a button that puts it in the clipboard. Selecting
			// a link with the mouse without following it is a trick of its own, and these
			// addresses are made to be handed on - to somebody writing up the tournament, or
			// pasted into a player's message. The button is held against the right edge of the
			// column, halfway down however many lines the address takes; see common.css.
			echo '<td><span class="broadcast-url"><a href="' . htmlspecialchars($b->url) . '" target="_blank">' .
				htmlspecialchars($b->url) . '</a>';
			echo '<button class="copy-url" title="' . get_label('Copy') . '" onclick="mr.copyText(' .
				htmlspecialchars(json_encode($b->url), ENT_QUOTES) . ')"><img src="images/copy.png"></button></span></td>';

			if (is_null($b->start))
			{
				echo '<td>&mdash;</td><td>&mdash;</td><td>&mdash;</td><td>&mdash;</td></tr>';
				continue;
			}
			echo '<td>' . date('Y-m-d H:i', $b->start) . '</td>';

			// How long this table was reported to be off the air. Only what OBS said is in here
			// - see include/broadcast.php - so it is a floor rather than a total, and the column
			// beside it says how far short it falls.
			$until = is_null($b->end) ? time() : $b->end;
			$measured = broadcast_lost_seconds($owner, $b->table_num, $b->start, $until);
			echo '<td>' . format_lost_time($measured) . '</td>';

			// And what the recording says about the same thing. It is shorter than the wall
			// clock by exactly the time cut out of it, so the two should come to the same;
			// where they do not, that is how far the links into this recording may be out.
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
			// begins. Everything placed on it from then on is moved by the same amount, so it is
			// worth seeing that it is there.
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
	return $rows;
}

?>
