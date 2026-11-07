<?php

// By full path on purpose: a bare 'video.php' is resolved against the working directory first,
// and the working directory is the site root, where video.php is a page of its own.
require_once __DIR__ . '/video.php';
require_once __DIR__ . '/stream.php';
// The squares of what is over are paged through - see show_broadcasts().
require_once __DIR__ . '/pages.php';
// And they carry the badge of the club on some of the pages - see show_broadcast_tile().
require_once __DIR__ . '/picture.php';

// Finding a game inside a stream.
//
// A game and a broadcast are both stamped in wall clock time, so where the game begins in the
// recording looks like a subtraction and is not one. A live stream that loses its connection
// is spliced: the recording carries on as one video with the dead air left out of it, so
// everything after an interruption sits earlier in the recording than the clock would say, and
// every game after that is out by the sum of every interruption before it. On one real
// broadcast of ours that came to an hour and a half over a thirteen hour day.
//
// What is subtracted, then, is the clock less the time that was cut out of the recording
// before the game began. The interruptions are measured rather than guessed at: the overlays
// run inside the same OBS that is streaming and ask this site what to show every couple of
// seconds, so a hole in that asking is a machine that was off the air, and we timed it
// ourselves.
//
// Only what OBS actually said is written down. A silence is not a break in the stream: the
// overlays may simply not be there - nobody put them in the scene, or they were taken out, or
// OBS let them go - and the stream will have been running all the while. A break invented out
// of such a silence would not merely be a gap in what we know: it would move every game after
// it in that recording, and so spoil times that were right.
//
// A silence can be told from a break often enough to be worth it, because an overlay that is
// alive keeps asking OBS about the stream whether or not it can reach us, remembers the
// answers, and sends them on when the connection comes back. So a silence somebody accounts
// for afterwards tells us what was in it; a silence nobody ever accounts for is almost always
// an overlay that was not running. Where neither holds - the machine itself died with the
// stream - we are left not knowing, and we would rather not know than guess.
//
// So the error only ever runs one way: we may count less time off the air than there was,
// never more. The recording settles the rest, and only after the fact - it is shorter than
// the wall clock by exactly the time cut out of it, so what we recorded should come to that
// same difference. Where it falls short, that is the measure of how far the links of that
// broadcast may be out.
//
// Nothing is withheld on account of it. A game is tied to its recording as it is written down,
// hours before the broadcast ends and there is anything to compare with, and a link that is a
// little out is worth more than no link. What the disagreement is for is to be shown to
// whoever runs the tournament, so that they can move one game to where it really begins and
// take the rest of that recording with it - see broadcast_shift_following().

// Tables are counted from one everywhere in here, as they are in games.table_num, in the
// settings a browser source is pointed at, and on the screen. event_broadcasts counts them
// from zero, and that is converted away at the one place that reads it.

// How long what a broadcast says about itself is believed. One that has said when it began
// is asked again only now and then, because the only thing left to learn is when it ends and
// that can wait. One that has not said when it began is asked after far more often: until it
// does, a game played at that table cannot be tied to it as the game is written down, which
// is the whole point of tying it then rather than afterwards.
define('BROADCAST_RECHECK_SECONDS', 3600);
define('BROADCAST_PENDING_RECHECK_SECONDS', 120);
// How many of them may be asked after in one request. Reading a broadcast means going out to
// another site and waiting, and a tournament can have scores of them; without a limit, one
// page could sit there making one call after another.
define('BROADCAST_REFRESH_LIMIT', 4);

// The most we will believe of a stream's delay. It is seconds, tens of them at the worst;
// anything longer is not a delay but a mistake, and is left alone rather than written into
// every link of the recording. See broadcast_measure_delay().
define('BROADCAST_MAX_DELAY_SECONDS', 60);
// How long after a broadcast has ended we go on asking after it. Its delay can only be read
// off the finished recording, and YouTube takes hours over a long one; a week of asking
// once an hour, and only when somebody opens a page, is cheap against never knowing.
define('BROADCAST_MEASURE_SECONDS', 604800);
// How far the two sums may disagree before the broadcast is worth somebody's attention. Ten
// seconds is already a game starting visibly late in the recording, and the two sums are each
// rounded to the second at both ends, so there is no sense in asking for closer than this.
define('BROADCAST_TOLERANCE_SECONDS', 10);

// A length of time off the air, as minutes and seconds, or as hours too once there are any.
function format_lost_time($seconds)
{
	$seconds = (int)$seconds;
	if ($seconds >= 3600)
	{
		return sprintf('%d:%02d:%02d', (int)($seconds / 3600), (int)(($seconds % 3600) / 60), $seconds % 60);
	}
	return sprintf('%d:%02d', (int)($seconds / 60), $seconds % 60);
}

// Stretches of time, as [start, end] pairs sorted by start and never overlapping. The three
// below are all the interval arithmetic this file needs, and they are kept apart from the
// database so that they can be read and tested on their own.
function broadcast_tidy($spans)
{
	usort($spans, function ($a, $b) { return $a[0] - $b[0]; });
	$tidy = array();
	foreach ($spans as $span)
	{
		if ($span[1] <= $span[0])
		{
			continue;
		}
		$last = count($tidy) - 1;
		if ($last >= 0 && $span[0] <= $tidy[$last][1])
		{
			$tidy[$last][1] = max($tidy[$last][1], $span[1]);
		}
		else
		{
			$tidy[] = array($span[0], $span[1]);
		}
	}
	return $tidy;
}

// What is left of one set of stretches once another is taken out of it.
function broadcast_subtract($spans, $cuts)
{
	$cuts = broadcast_tidy($cuts);
	$left = array();
	foreach (broadcast_tidy($spans) as $span)
	{
		list ($from, $to) = $span;
		foreach ($cuts as $cut)
		{
			if ($cut[1] <= $from)
			{
				continue;
			}
			if ($cut[0] >= $to)
			{
				break;
			}
			if ($cut[0] > $from)
			{
				$left[] = array($from, $cut[0]);
			}
			$from = max($from, $cut[1]);
			if ($from >= $to)
			{
				break;
			}
		}
		if ($to > $from)
		{
			$left[] = array($from, $to);
		}
	}
	return $left;
}

// What two sets of stretches have in common.
function broadcast_intersect($spans, $others)
{
	return broadcast_subtract($spans, broadcast_subtract($spans, $others));
}

// What the overlay says OBS said about its stream, as stretches of wall clock time. It is sent
// as ages rather than as times - "from 300 seconds ago to 12 seconds ago it was on the air" -
// because the clock we can trust is this one, not the clock of a machine in a playing hall.
function broadcast_parse_runs($text, $now)
{
	$up = array();
	$down = array();
	if (!is_string($text) || $text === '')
	{
		return array($up, $down);
	}

	$parts = explode(',', $text);
	$count = 0;
	foreach ($parts as $part)
	{
		if (++$count > 50)
		{
			break; // a stream that flapped more than this says all it has to say in fifty
		}
		if (!preg_match('/^(\d+)-(\d+):([01])$/', trim($part), $m))
		{
			continue;
		}
		$from = $now - (int)$m[1];
		$to = $now - (int)$m[2];
		if ($to <= $from)
		{
			continue;
		}
		if ($m[3] === '1')
		{
			$up[] = array($from, $to);
		}
		else
		{
			$down[] = array($from, $to);
		}
	}
	// On the air wins where the two overlap: that is the half of the account we are sure of,
	// and it is what keeps a guess from standing in the way of something seen.
	$up = broadcast_tidy($up);
	return array($up, broadcast_subtract($down, $up));
}

// Writes a break down, joining it to the one before it where they meet. A stream that stays
// off the air is reported again every couple of seconds, and that has to come out as one
// break rather than as hundreds of two second ones.
function broadcast_add_outage($owner, $table_num, $start, $end)
{
	if ($end <= $start)
	{
		return;
	}

	$column = $owner->is_tournament ? 'tournament_id' : 'event_id';
	Db::exec(get_label('event'),
		'UPDATE stream_outages SET end_time = ? WHERE ' . $column . ' = ? AND table_num = ?' .
		' AND end_time >= ? AND end_time < ?', $end, $owner->id, $table_num, $start, $end);
	if (Db::affected_rows() > 0)
	{
		return;
	}
	Db::exec(get_label('event'),
		'INSERT INTO stream_outages (' . $column . ', table_num, start_time, end_time) VALUES (?, ?, ?, ?)',
		$owner->id, $table_num, $start, $end);
}

// The overlay at this table has been heard from, and brings with it what OBS has been saying
// about its stream since we last heard anything - including the part it could not deliver at
// the time, which is the whole point of it carrying an account rather than just a pulse.
//
// Only the stretches OBS reported as off the air are written down, and only the part of them
// we have not already accounted for, which is what the window from the last word to now is
// for: the overlay goes on re-sending its account for an hour, and the same break must not be
// written down twice. Everything else - including a silence nobody ever explains - is left
// alone. See the top of this file for why a silence is not taken for a break.
function broadcast_note_heartbeat($owner, $table_num, $runs_text = '')
{
	$now = time();
	$query = new DbQuery('SELECT seen_time FROM ' . $owner->table . ' WHERE ' . $owner->column . ' = ? AND table_num = ?',
		$owner->id, $table_num);
	if (!($row = $query->next()))
	{
		// Nothing has been set up for this table yet, so the row is made as it is for every
		// other setting: with what a table gets when nobody has said otherwise.
		$settings = stream_settings($owner, $table_num);
		Db::exec(get_label('event'),
			'INSERT INTO ' . $owner->table . ' (' . $owner->column . ', table_num, lang, delay, screens, flags, seen_time)' .
			' VALUES (?, ?, ?, ?, ?, ?, ?)',
			$owner->id, $table_num, $settings->lang, $settings->delay,
			implode(',', $settings->screens), $settings->flags, $now);
		return;
	}

	list ($seen) = $row;
	$seen = (int)$seen;
	list ($up, $down) = broadcast_parse_runs($runs_text, $now);

	if ($seen > 0 && $now > $seen)
	{
		foreach (broadcast_intersect(array(array($seen, $now)), $down) as $span)
		{
			broadcast_add_outage($owner, $table_num, $span[0], $span[1]);
		}
	}

	Db::exec(get_label('event'),
		'UPDATE ' . $owner->table . ' SET seen_time = ? WHERE ' . $owner->column . ' = ? AND table_num = ?',
		$now, $owner->id, $table_num);
}

// OBS has gone on the air at this table and the overlay inside it saw that happen. It comes as
// an age in seconds, like the rest of the account, so that it is read against our clock rather
// than the clock of a hall laptop, and only where the overlay watched it happen: a stream that
// was already running when the overlay opened says nothing about when it began.
//
// Nothing is worked out from it any more. It was meant to give the delay of the stream, as the
// gap between this moment and the moment YouTube stamps on the broadcast - but YouTube does
// not begin a broadcast when the pictures start arriving, it begins when somebody presses Go
// live, so that gap is mostly however long they took. It is kept because it is the one thing
// we know for certain about when a stream began, and it costs nothing to write down.
function broadcast_note_stream_start($owner, $table_num, $age_text)
{
	$age_text = trim($age_text);
	if ($age_text === '' || !preg_match('/^\d+$/', $age_text))
	{
		return;
	}

	$age = (int)$age_text;
	if ($age > BROADCAST_RECHECK_SECONDS)
	{
		return; // older than an hour: whatever it belonged to has long been settled
	}

	// The overlay goes on sending the same start until it ages out, so the same moment arrives
	// again and again. Only a later one is of interest - that one is a new stream.
	$moment = time() - $age;
	Db::exec(get_label('event'),
		'UPDATE ' . $owner->table . ' SET obs_start_time = ?' .
		' WHERE ' . $owner->column . ' = ? AND table_num = ? AND obs_start_time < ?',
		$moment, $owner->id, $table_num, $moment);
}

// What a new recording of this table should be moved by until it can be measured on its own.
//
// The time YouTube stamps on a broadcast is the moment the viewers saw the picture, which is
// seconds after the picture was made, so a game placed by that stamp lands early by exactly
// the delay of the stream. That delay can be read off the recording once YouTube has finished
// it, which is hours later - and the games of the evening are written down while it is still
// going on. So the table answers in the meantime: it keeps what its last measured stream was
// delayed by, and the same machine on the same connection will be delayed by much the same
// again. See broadcast_measure_delay() and broadcast_teach_table().
//
// A recording that already carries a shift keeps it: what was measured on it, or set on it by
// hand, is worth more than what the table knows in general.
function broadcast_table_shift($event_id, $table_num, $b)
{
	if (is_null($b->start) || $b->shift != 0)
	{
		return 0;
	}

	list ($tournament_id) = Db::record(get_label('event'), 'SELECT tournament_id FROM events WHERE id = ?', $event_id);
	$owner = stream_owner(is_null($tournament_id) ? 0 : (int)$tournament_id, $event_id);
	if (is_null($owner))
	{
		return 0;
	}

	$query = new DbQuery('SELECT time_shift FROM ' . $owner->table .
		' WHERE ' . $owner->column . ' = ? AND table_num = ?', $owner->id, $table_num);
	if (!($row = $query->next()))
	{
		return 0;
	}

	list ($shift) = $row;
	$shift = (int)$shift;
	if ($shift <= 0 || $shift > BROADCAST_MAX_DELAY_SECONDS)
	{
		return 0;
	}
	return $shift;
}

// Moves what is already placed on a recording, for when its delay becomes known after a game
// has been written down on it. Only the links the site worked out itself: one set by hand is
// where somebody put it on purpose.
function broadcast_shift_recording($url, $delta)
{
	if ($delta == 0)
	{
		return 0;
	}

	$id = NULL;
	$vtime = NULL;
	try
	{
		get_youtube_id($url, $id, $vtime);
	}
	catch (Exception $e)
	{
		return 0;
	}
	if (is_null($id))
	{
		return 0;
	}

	$query = new DbQuery('SELECT id, vtime FROM videos WHERE video = ? AND type = ? AND (flags & ?) <> 0',
		$id, VIDEO_TYPE_GAME, VIDEO_FLAG_AUTO_TIME);
	$shift = array();
	while ($row = $query->next())
	{
		list ($video_id, $vtime) = $row;
		$shift[(int)$video_id] = max(broadcast_vtime_seconds($vtime) + $delta, 0);
	}
	foreach ($shift as $video_id => $seconds)
	{
		Db::exec(get_label('video'), 'UPDATE videos SET vtime = ? WHERE id = ?', $seconds, $video_id);
	}
	return count($shift);
}
// How much of the time between two moments this table spent off the air. Only the part of an
// interruption that falls inside the window counts, so one that straddles either end is not
// counted whole.
function broadcast_lost_seconds($owner, $table_num, $from, $to)
{
	if ($to <= $from)
	{
		return 0;
	}

	$column = $owner->is_tournament ? 'tournament_id' : 'event_id';
	$query = new DbQuery(
		'SELECT start_time, end_time FROM stream_outages WHERE ' . $column . ' = ? AND table_num = ?' .
		' AND end_time > ? AND start_time < ?', $owner->id, $table_num, $from, $to);
	$lost = 0;
	while ($row = $query->next())
	{
		list ($start, $end) = $row;
		$start = max((int)$start, $from);
		$end = min((int)$end, $to);
		if ($end > $start)
		{
			$lost += $end - $start;
		}
	}
	return $lost;
}

// What a broadcast says about its own timing: when it went on the air, when it came off it,
// and how long the recording turned out to be. All three are on the page itself, so this needs
// no key and no account.
function broadcast_read_timing($youtube_id)
{
	$context = stream_context_create(array('http' => array(
		'timeout' => 15,
		'header' => "User-Agent: Mozilla/5.0 (compatible; mafiaratings)\r\n")));
	$page = @file_get_contents('https://www.youtube.com/watch?v=' . $youtube_id, false, $context);
	if ($page === false)
	{
		return NULL;
	}
	return broadcast_parse_timing($page);
}

// Kept apart from the fetching so that it can be held against a page we already have.
function broadcast_parse_timing($page)
{
	$timing = new stdClass();
	$timing->start = NULL;
	$timing->end = NULL;
	$timing->length = NULL;
	// Whether the page says in so many words that the broadcast is on the air. It is the only
	// thing that may take an end away from a recording that had one - see broadcast_list().
	$timing->live = (strpos($page, '"isLiveNow":true') !== false);
	if (preg_match('/"startTimestamp":"([^"]+)"/', $page, $match))
	{
		$timing->start = strtotime($match[1]);
	}
	if (preg_match('/"endTimestamp":"([^"]+)"/', $page, $match))
	{
		$timing->end = strtotime($match[1]);
	}
	if (preg_match('/"lengthSeconds":"?([0-9]+)"?/', $page, $match))
	{
		$timing->length = (int)$match[1];
	}

	// Without a start there is nothing to measure from, and a video that was never live has
	// none - it is an upload rather than a broadcast, and we have nothing to say about it.
	return is_null($timing->start) ? NULL : $timing;
}

// The broadcasts of one table of one round, with what each says about its timing.
//
// One that has not come off the air yet is asked again every couple of minutes, because its
// end is the very thing we are waiting for: while it is unknown the recording looks open and
// swallows games played after it was stopped.
//
// One that has ended is asked after about once an hour until it says how long its stream was
// delayed, and then never again. That answer is the length of the finished recording, which
// YouTube does not settle until hours after the broadcast is over and which does not change
// once it has - see broadcast_measure_delay(). Every answer up to that one repeats the window
// and teaches us nothing, and costs one page read an hour. A recording that has still not
// said it a week later is given up on.
//
// $force asks anyway, whatever any of that says. It is what happens when a game cannot be
// placed in any of the recordings we know: a page read between two sessions of the same
// stream answers for the session before, and that has to be thrown away rather than kept for
// ever.
function broadcast_list($event_id, $table_num, $refresh = true, $force = false)
{
	$now = time();
	$list = array();
	$query = new DbQuery(
		'SELECT day_num, part_num, url, stream_start, stream_end, video_length, checked_time, time_shift' .
		' FROM event_broadcasts WHERE event_id = ? AND table_num = ? ORDER BY day_num, part_num',
		$event_id, $table_num - 1);
	while ($row = $query->next())
	{
		list ($day, $part, $url, $start, $end, $length, $checked, $shift) = $row;
		$b = new stdClass();
		$b->event_id = $event_id;
		$b->table_num = $table_num;
		$b->day_num = (int)$day;
		$b->part_num = (int)$part;
		$b->url = $url;
		$b->start = is_null($start) ? NULL : (int)$start;
		$b->end = is_null($end) ? NULL : (int)$end;
		$b->length = is_null($length) ? NULL : (int)$length;
		$b->checked = (int)$checked;
		$b->shift = (int)$shift;
		$list[] = $b;
	}

	if (!$refresh)
	{
		return $list;
	}

	$owner = NULL;
	list ($tournament_id) = Db::record(get_label('event'), 'SELECT tournament_id FROM events WHERE id = ?', $event_id);
	$owner = stream_owner(is_null($tournament_id) ? 0 : (int)$tournament_id, $event_id);

	$asked = 0;
	foreach ($list as $b)
	{
		if ($asked >= BROADCAST_REFRESH_LIMIT)
		{
			break; // the rest keep until somebody asks again
		}
		// A recording that has ended and whose delay we have read off it has nothing left to
		// say: what we were waiting for has arrived and it will not arrive differently. One
		// that has still not said it a week later never will - that is a stream that was off
		// the air for time nobody accounted for, not a recording YouTube is slow with.
		if (!$force && !is_null($b->end) &&
			(broadcast_measure_delay($owner, $b) > 0 || $now - $b->end > BROADCAST_MEASURE_SECONDS))
		{
			continue;
		}
		// Until it has ended it is still moving, and the end is what the games after it turn
		// on; after that we are only waiting for YouTube to finish the recording.
		$wait = is_null($b->end) ? BROADCAST_PENDING_RECHECK_SECONDS : BROADCAST_RECHECK_SECONDS;
		if (!$force && $b->checked > 0 && $now - $b->checked < $wait)
		{
			continue;
		}
		++$asked;

		$id = NULL;
		$vtime = NULL;
		try
		{
			get_youtube_id($b->url, $id, $vtime);
		}
		catch (Exception $e)
		{
			$id = NULL;
		}

		$timing = is_null($id) ? NULL : broadcast_read_timing($id);
		if (!is_null($timing))
		{
			$b->start = $timing->start;

			// What the page says is taken, but what it leaves out is not. The same broadcast
			// asked after twice within a minute has answered us once with its end and its
			// length and once as though it were still on the air, and believing the second
			// would throw away what we already knew: a recording with no end swallows the
			// games played after it, and a length of nought is no length at all.
			//
			// So an end is only taken away by a page that says in so many words that the
			// broadcast is live - a stream really can be started again - and the length only
			// ever grows. It grows twice in the ordinary way of things: first to the window
			// the broadcast was live for, and then, once YouTube has finished the recording,
			// to what the recording actually holds. That second growth is the delay.
			if (!is_null($timing->end) || $timing->live)
			{
				$b->end = $timing->end;
			}
			if (!is_null($timing->length) && (is_null($b->length) || $timing->length > $b->length))
			{
				$b->length = $timing->length;
			}

			// Where the recording really begins, as against where its own start says it does.
			// The finished recording settles it exactly, and until YouTube has finished it we
			// go with what this table's streams were delayed by the last time one of them
			// could be measured. The exact figure replaces the borrowed one when it arrives,
			// and the games already placed on this recording move with it either way.
			$delay = broadcast_measure_delay($owner, $b);
			if ($delay > 0)
			{
				broadcast_set_shift($b, $delay);
				broadcast_teach_table($owner, $table_num, $delay);
			}
			else if ($b->shift == 0)
			{
				broadcast_set_shift($b, broadcast_table_shift($event_id, $table_num, $b));
			}
		}
		$b->checked = $now;
		Db::exec(get_label('broadcast'),
			'UPDATE event_broadcasts SET stream_start = ?, stream_end = ?, video_length = ?, checked_time = ?' .
			' WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ?',
			$b->start, $b->end, $b->length, $b->checked, $event_id, $b->day_num, $table_num - 1, $b->part_num);
	}
	return $list;
}

// Which round's broadcasts to keep fresh. A browser source may be pointed at the tournament
// rather than at one of its rounds, and then nobody has said which round is being played: it
// is the one the table is playing now, or, between rounds, the last one that has begun.
function broadcast_current_event($owner, $table_num)
{
	if (!$owner->is_tournament)
	{
		return (int)$owner->id;
	}

	$query = new DbQuery(
		'SELECT g.event_id FROM current_games g JOIN events e ON e.id = g.event_id' .
		' WHERE e.tournament_id = ? AND g.table_num = ? ORDER BY g.game_num DESC LIMIT 1',
		$owner->id, $table_num);
	if ($row = $query->next())
	{
		return (int)$row[0];
	}

	// Between games, the round the last game of this table was played in.
	$query = new DbQuery(
		'SELECT event_id FROM games WHERE tournament_id = ? AND table_num = ?' .
		' ORDER BY end_time DESC, id DESC LIMIT 1', $owner->id, $table_num);
	if ($row = $query->next())
	{
		return (int)$row[0];
	}

	// And before the first game of all, the round somebody has given this table an address
	// for. Rounds often share a day, so the last one entered wins over the last one listed.
	$query = new DbQuery(
		'SELECT e.id FROM events e JOIN event_broadcasts b ON b.event_id = e.id AND b.table_num = ?' .
		' WHERE e.tournament_id = ? ORDER BY e.start_time DESC, e.id DESC LIMIT 1',
		$table_num - 1, $owner->id);
	if ($row = $query->next())
	{
		return (int)$row[0];
	}
	return 0;
}

// Which broadcast of this round a video came out of, found by the recording itself rather than
// by the table: a game old enough not to say which table it was played at still carries the
// address of what it was recorded on. Nothing is asked of YouTube here - this runs while
// somebody is opening a game, and the timing was read long ago.
function broadcast_of_video($event_id, $youtube_id, $table_num = NULL)
{
	$tables = array();
	if (!is_null($table_num))
	{
		$tables[] = (int)$table_num;
	}
	else
	{
		$query = new DbQuery('SELECT DISTINCT table_num FROM event_broadcasts WHERE event_id = ? ORDER BY table_num', $event_id);
		while ($row = $query->next())
		{
			$tables[] = (int)$row[0] + 1;
		}
	}

	foreach ($tables as $table)
	{
		foreach (broadcast_list($event_id, $table, false) as $b)
		{
			$id = NULL;
			$vtime = NULL;
			try
			{
				get_youtube_id($b->url, $id, $vtime);
			}
			catch (Exception $e)
			{
				continue;
			}
			if ($id === $youtube_id)
			{
				return $b;
			}
		}
	}
	return NULL;
}

// Which of them was on the air at that moment. One that is still running has no end yet, so it
// stands as long as the moment is after its start.
//
// Where more than one could hold the moment, the latest of them wins. They overlap when an
// earlier part has not yet been asked about since it finished and so still looks open ended;
// taking the first would put a game played this evening into the recording of this morning,
// and the later start is always the nearer truth.
function broadcast_covering($list, $moment)
{
	$found = NULL;
	foreach ($list as $b)
	{
		if (is_null($b->start) || $moment < $b->start)
		{
			continue;
		}
		if (!is_null($b->end) && $moment > $b->end)
		{
			continue;
		}
		if (is_null($found) || $b->start > $found->start)
		{
			$found = $b;
		}
	}
	return $found;
}

// Where a moment falls in the recording, counting out the time that was cut out of it before
// then. NULL when nothing was on the air.
//
// The shift is what somebody taught us by moving a game of this recording to where it really
// begins: YouTube says a broadcast began when the viewers saw it, which is later than the
// moment the picture was taken by as much as the stream is delayed, so every position worked
// out from that start is early by the same amount. One correction says how much, and the
// games placed afterwards carry it - see broadcast_shift_following().
function broadcast_offset($owner, $b, $moment)
{
	if (is_null($b) || is_null($b->start) || $moment < $b->start)
	{
		return NULL;
	}
	$shift = isset($b->shift) ? (int)$b->shift : 0;
	$offset = $moment - $b->start - broadcast_lost_seconds($owner, $b->table_num, $b->start, $moment) + $shift;
	return max($offset, 0);
}

// Whether what we measured and what the recording says agree. A recording is shorter than the
// wall clock by exactly the time taken out of it, and our interruptions should come to the
// same. NULL while the broadcast is still running - there is no recording to compare with yet.
function broadcast_agrees($owner, $b)
{
	$difference = broadcast_difference($owner, $b);
	return is_null($difference) ? NULL : (abs($difference) <= BROADCAST_TOLERANCE_SECONDS);
}

// By how much, and which way. Positive means the recording had more taken out of it than we
// ever saw go missing, so the games of it sit earlier in the recording than their links say.
// Negative means we counted time off the air that the recording was never short of, and the
// games sit later than their links say. NULL while the broadcast is still running.
function broadcast_difference($owner, $b)
{
	if (is_null($b) || is_null($b->start) || is_null($b->end) || is_null($b->length))
	{
		return NULL;
	}
	$cut_out = ($b->end - $b->start) - $b->length;
	$measured = broadcast_lost_seconds($owner, $b->table_num, $b->start, $b->end);
	// The delay we have already put back is part of that difference and no longer a doubt, so
	// what is left is what the links into this recording may still be out by - see
	// broadcast_measure_delay(), which is where the rest of the difference goes.
	return $cut_out - $measured + (isset($b->shift) ? (int)$b->shift : 0);
}

// How long this broadcast's stream was delayed, read off the recording itself, or 0 where it
// cannot be told yet.
//
// A recording is not the same length as the window YouTube says the broadcast was live for. It
// is shorter by whatever was cut out of it while the stream was off the air - that is what
// everything above measures - and it is longer by the delay. What the viewers had not been
// shown yet when the broadcast began is in the recording all the same, so the recording
// reaches that much further back than its own start says. Take one away from the other and
// the delay is what is left:
//
//     delay = length - (end - start) + time off the air
//
// On four of our own broadcasts with nothing to cut out this came to 9, 13, 13 and 16 seconds,
// against ten-odd seconds measured by hand on the same streams.
//
// It can only be read once YouTube has finished the recording, which is hours after the
// broadcast ends: until then the length it reports is the window over again and the answer
// comes out at nought. So nothing is claimed unless the answer is a delay - a positive number
// of seconds, and not more than a stream is ever delayed by. Anything else is a recording
// YouTube has not finished, or time off the air that nobody accounted for, and both are left
// alone rather than guessed at.
function broadcast_measure_delay($owner, $b)
{
	if (is_null($b->start) || is_null($b->end) || is_null($b->length))
	{
		return 0;
	}
	$lost = broadcast_lost_seconds($owner, $b->table_num, $b->start, $b->end);
	$delay = $b->length - ($b->end - $b->start) + $lost;
	return ($delay > 0 && $delay <= BROADCAST_MAX_DELAY_SECONDS) ? $delay : 0;
}

// Moves a recording to where it really begins, and every game already placed on it with it.
//
// The write is a swap rather than a set - the shift it had for the shift it is given - so that
// two of these at once cannot both move the games: the second finds the shift already changed,
// writes nothing and moves nothing. A time somebody set by hand is not touched either way,
// since only the times the site worked out carry the mark that lets them be moved.
function broadcast_set_shift($b, $shift)
{
	$delta = $shift - (int)$b->shift;
	if ($delta == 0)
	{
		return 0;
	}

	Db::exec(get_label('broadcast'),
		'UPDATE event_broadcasts SET time_shift = ?' .
		' WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ? AND time_shift = ?',
		$shift, $b->event_id, $b->day_num, $b->table_num - 1, $b->part_num, (int)$b->shift);
	if (Db::affected_rows() <= 0)
	{
		return 0;
	}
	$b->shift = $shift;
	return broadcast_shift_recording($b->url, $delta);
}

// What this table's streams are delayed by, as the last recording of it that could be measured
// turned out. It is what a new recording of the same table is given to start with, since there
// is nothing to measure on it yet - the same machine on the same connection is delayed by the
// same, near enough, and near enough is a great deal better than nothing.
function broadcast_teach_table($owner, $table_num, $delay)
{
	if (is_null($owner) || $delay <= 0 || $delay > BROADCAST_MAX_DELAY_SECONDS)
	{
		return;
	}
	Db::exec(get_label('event'),
		'UPDATE ' . $owner->table . ' SET time_shift = ?' .
		' WHERE ' . $owner->column . ' = ? AND table_num = ?',
		$delay, $owner->id, $table_num);
}

// Ties a game that has just been written down to the moment it begins in the broadcast of its
// table. The link is put where a link put there by hand goes - a row in videos carrying the
// recording and the offset into it - so that everything that already shows videos shows this
// one without being told anything new.
//
// It is done as the game is written rather than afterwards because everything it needs is
// known by then: an interruption after the game does not move the game, and the ones before it
// have already been measured. What is not known yet is whether the sums will agree, since the
// recording is not finished; that is settled later, by broadcast_agrees().
// Nothing here asks the broadcast about itself: this runs while a referee is writing a game
// down, and a slow answer from YouTube would be a referee sitting in front of a game that will
// not save. It goes on what is already known, and a game it cannot place is left for the
// broadcasts page to pick up once the timing has been read.
function broadcast_bind_game($game_id, $event_id, $tournament_id, $table_num, $moment, $language, $club_id, $refresh = false)
{
	global $_profile;

	if ($game_id <= 0 || $event_id <= 0 || $table_num <= 0 || $moment <= 0)
	{
		return NULL;
	}

	$owner = stream_owner($tournament_id, $event_id);
	if (is_null($owner))
	{
		return NULL;
	}

	$b = broadcast_covering(broadcast_list($event_id, $table_num, $refresh), $moment);
	if (is_null($b))
	{
		// Nothing we know of was on the air then, and what we know may simply be old: a part
		// that had not started when we last asked, or one we read between two sessions of it.
		// Ask them all again before leaving the game unplaced.
		$b = broadcast_covering(broadcast_list($event_id, $table_num, true, true), $moment);
	}
	$offset = broadcast_offset($owner, $b, $moment);
	if (is_null($offset))
	{
		return NULL;
	}

	return broadcast_link_game($game_id, $event_id, $tournament_id, $club_id, $language, $moment, $b, $offset);
}

// Writes the link down, once it is settled which recording and how far into it. Marked as a
// time the site worked out, so that a correction made to another game of the same recording
// may move it - see broadcast_shift_following().
function broadcast_link_game($game_id, $event_id, $tournament_id, $club_id, $language, $moment, $b, $offset)
{
	global $_profile;

	$id = NULL;
	$vtime = NULL;
	get_youtube_id($b->url, $id, $vtime);

	// Whoever is at the keyboard owns the link, and where nobody is - a pass run from the
	// command line - it goes to whoever wrote the game down. The column will not take a nought.
	$user_id = is_null($_profile) ? 0 : (int)$_profile->user_id;
	if ($user_id <= 0)
	{
		list ($user_id) = Db::record(get_label('game'), 'SELECT user_id FROM games WHERE id = ?', $game_id);
	}

	Db::exec(get_label('video'),
		'INSERT INTO videos (name, video, type, club_id, event_id, tournament_id, lang, post_time, video_time, user_id, vtime, flags)' .
		' VALUES (?, ?, ?, ?, ?, ?, ?, UNIX_TIMESTAMP(), ?, ?, ?, ?)',
		get_label('Game [0]', $game_id), $id, VIDEO_TYPE_GAME, $club_id, $event_id, $tournament_id,
		$language, $moment, $user_id, $offset, VIDEO_FLAG_AUTO_TIME);
	list ($video_id) = Db::record(get_label('video'), 'SELECT LAST_INSERT_ID()');
	Db::exec(get_label('game'), 'UPDATE games SET video_id = ? WHERE id = ?', (int)$video_id, $game_id);
	return (int)$video_id;
}

// How far into the recording a stored time is, in seconds. YouTube takes the offset as a plain
// count of seconds or as hours, minutes and seconds run together, and either may be in the
// table: ours are written as seconds, one pasted from a link is whatever the person copied.
function broadcast_vtime_seconds($vtime)
{
	if (is_null($vtime) || $vtime === '')
	{
		return 0;
	}
	if (preg_match('/^\s*(\d+)\s*$/', $vtime, $match))
	{
		return (int)$match[1];
	}
	if (preg_match('/^\s*(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s?)?\s*$/i', $vtime, $match))
	{
		$h = isset($match[1]) && $match[1] !== '' ? (int)$match[1] : 0;
		$m = isset($match[2]) && $match[2] !== '' ? (int)$match[2] : 0;
		$sec = isset($match[3]) && $match[3] !== '' ? (int)$match[3] : 0;
		return $h * 3600 + $m * 60 + $sec;
	}
	return 0;
}

// A person has moved one game to where it really begins, and in doing so has said how much the
// time off the air was out by. Everything after it in the same recording was placed with that
// same wrong figure, so it is out by the same amount and is moved with it.
//
// Only links the site worked out itself are moved. One somebody set by hand is where they put
// it on purpose, and the correction that reaches it is already in the number they typed.
// Everything before the corrected game is left alone: the hole we missed is somewhere between
// the game before it and it, so it cannot have moved anything earlier.
//
// The recording is told as well. Whatever was out by - a hole we missed, or the delay between
// the picture being taken and the broadcast going live, which is what YouTube stamps as its
// start - is out by the same amount for a game placed on it tomorrow, so the figure is kept
// with the recording and counted in from then on. See broadcast_offset().
function broadcast_shift_following($video_id, $delta)
{
	if ($delta == 0)
	{
		return 0;
	}

	list ($youtube_id, $moment) = Db::record(get_label('video'),
		'SELECT video, video_time FROM videos WHERE id = ?', $video_id);

	$query = new DbQuery(
		'SELECT id, vtime FROM videos WHERE video = ? AND video_time > ? AND type = ? AND (flags & ?) <> 0',
		$youtube_id, $moment, VIDEO_TYPE_GAME, VIDEO_FLAG_AUTO_TIME);
	$shift = array();
	while ($row = $query->next())
	{
		list ($id, $vtime) = $row;
		$shift[(int)$id] = max(broadcast_vtime_seconds($vtime) + $delta, 0);
	}
	foreach ($shift as $id => $vtime)
	{
		Db::exec(get_label('video'), 'UPDATE videos SET vtime = ? WHERE id = ?', $vtime, $id);
	}
	// And the recording itself is told how far out it was, so that a game placed on it after
	// this lands right without anybody having to move that one too.
	list ($event_id) = Db::record(get_label('video'), 'SELECT event_id FROM videos WHERE id = ?', $video_id);
	if (!is_null($event_id))
	{
		$b = broadcast_of_video((int)$event_id, $youtube_id);
		if (!is_null($b))
		{
			Db::exec(get_label('broadcast'),
				'UPDATE event_broadcasts SET time_shift = time_shift + ?' .
				' WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ?',
				$delta, (int)$event_id, $b->day_num, $b->table_num - 1, $b->part_num);

			// And so is the table, which is where a recording that cannot be measured yet
			// gets its figure from. Somebody moving a game has said what this stream was
			// delayed by better than any guess, so it stands until a finished recording says
			// otherwise - and a figure that could not be a delay teaches nothing, since a
			// game moved by half an hour is somebody placing one game rather than correcting
			// the lot. See broadcast_teach_table().
			list ($learned) = Db::record(get_label('broadcast'),
				'SELECT time_shift FROM event_broadcasts' .
				' WHERE event_id = ? AND day_num = ? AND table_num = ? AND part_num = ?',
				(int)$event_id, $b->day_num, $b->table_num - 1, $b->part_num);
			list ($tournament_id) = Db::record(get_label('event'),
				'SELECT tournament_id FROM events WHERE id = ?', (int)$event_id);
			broadcast_teach_table(stream_owner(is_null($tournament_id) ? 0 : (int)$tournament_id, (int)$event_id),
				$b->table_num, (int)$learned);
		}
	}

	return count($shift);
}

// How many games of a round have no recording against them. That is what the binding has to
// work on - see broadcast_bind_event() - and so also what says whether there is any point in
// offering it: with none of these the pass reads every game, ties nothing, and says so.
function broadcast_unlinked_games($event_id)
{
	list ($count) = Db::record(get_label('game'),
		'SELECT COUNT(*) FROM games WHERE event_id = ? AND video_id IS NULL', $event_id);
	return (int)$count;
}

// Ties the games of a round that have none to the broadcasts of their table. This is the way
// in for everything played before any of this existed: the games are long written down, the
// addresses of the recordings go in afterwards, and the times are worked out from what is
// there now.
//
// For such a round nothing was measured while it was on the air, so the holes come out as
// nothing and every game after an interruption lands too late by the length of it. That is
// what correcting one game by hand is for: the rest of the recording follows it.
//
// The moment is taken from the game itself, which on an old game is the clock of the browser
// it was written down on. A game written down now carries a moment the site stamped instead -
// see Game::create() - so this is only as good as that clock was.
function broadcast_bind_event($event_id, $refresh = true)
{
	$result = new stdClass();
	$result->bound = 0;      // games given a link by this pass
	$result->had = 0;        // games that had one already
	$result->unplaced = 0;   // games still without one
	$result->untimed = 0;    // games with no moment to go on
	$result->untabled = 0;   // games that do not say which table they were played at

	list ($tournament_id, $club_id) = Db::record(get_label('event'),
		'SELECT tournament_id, club_id FROM events WHERE id = ?', $event_id);
	$tournament_id = is_null($tournament_id) ? 0 : (int)$tournament_id;
	$owner = stream_owner($tournament_id, $event_id);
	if (is_null($owner))
	{
		return $result;
	}

	// One table at a time, so that each broadcast is read about once however many games it
	// holds. Counted from one here, as the games count them.
	$query = new DbQuery('SELECT DISTINCT table_num FROM event_broadcasts WHERE event_id = ? ORDER BY table_num', $event_id);
	$lists = array();
	while ($row = $query->next())
	{
		$table_num = (int)$row[0] + 1;
		$lists[$table_num] = broadcast_list($event_id, $table_num, $refresh);
	}
	if (empty($lists))
	{
		return $result;
	}

	// A game only began saying which table it was played at in the middle of 2025, and
	// everything older carries nothing. Where the round was broadcast from one table there is
	// nothing to work out - that is the table - and where it was broadcast from several there
	// is nothing to go on at all, and the game is left alone rather than tied to a guess.
	$only_table = (count($lists) == 1) ? key($lists) : NULL;

	$query = new DbQuery(
		'SELECT id, table_num, start_time, language, video_id FROM games WHERE event_id = ? ORDER BY start_time',
		$event_id);
	$games = array();
	while ($row = $query->next())
	{
		$games[] = $row;
	}

	$forced = array();
	foreach ($games as $row)
	{
		list ($game_id, $table_num, $moment, $language, $video_id) = $row;
		if (!is_null($video_id))
		{
			++$result->had;
			continue;
		}
		$table_num = is_null($table_num) ? $only_table : (int)$table_num;
		if (is_null($table_num))
		{
			++$result->untabled;
			continue;
		}
		if (!isset($lists[$table_num]))
		{
			continue; // nobody was broadcasting that table
		}
		$moment = (int)$moment;
		if ($moment <= 0)
		{
			++$result->untimed;
			continue;
		}

		$b = broadcast_covering($lists[$table_num], $moment);
		if (is_null($b) && !isset($forced[$table_num]))
		{
			// What we hold about this table may be old - a part that had not started when it was
			// last read, or one read between two sessions of the same stream, which then says what
			// it said of the session before. Ask them all again, once per table.
			$forced[$table_num] = true;
			$lists[$table_num] = broadcast_list($event_id, $table_num, true, true);
			$b = broadcast_covering($lists[$table_num], $moment);
		}
		$offset = broadcast_offset($owner, $b, $moment);
		if (is_null($offset))
		{
			continue;
		}
		broadcast_link_game($game_id, $event_id, $tournament_id, $club_id, $language, $moment, $b, $offset);
		++$result->bound;
	}

	// Games at a table nobody is broadcasting are not a failure of the binding, but they are
	// still games left without a link, and that is what the page has to show.
	$result->unplaced = max(broadcast_unlinked_games($event_id) - $result->untimed - $result->untabled, 0);
	return $result;
}

// How many squares go in a row, the same number the lists on the front page are laid out in.
define('BROADCAST_COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('BROADCAST_ICON_WIDTH', 48);
// And how wide the club's badge is, where it is shown. It fills the height of the row the name
// stands on, which is as large as it can be without taking the room the name needs - and large
// enough to be told apart at a glance, which is the whole use of it. Still smaller than the
// picture in the middle of the square: that one is the thing to click.
define('BROADCAST_CLUB_ICON_WIDTH', 36);

// How many squares of broadcasts that are over go on one page. Six rows of them: enough to be
// worth turning a page for, few enough that a club with years of streaming behind it does not
// send thousands of squares to somebody who wanted last Saturday.
define('BROADCASTS_PAGE_SIZE', BROADCAST_COLUMN_COUNT * 6);

// The two tabs. What is on the air and what is still to come answer one question - is there
// anything to watch? - and what is over answers quite another, which is a search through a
// history. They are kept apart because the first has to be at the top of the page every time,
// and the second has to be paged through.
define('BROADCASTS_VIEW_CURRENT', 0);
define('BROADCASTS_VIEW_PAST', 1);

// How often the lights ask whether the tables are still playing, in milliseconds. A game turns
// over in minutes and a page of broadcasts is left open for hours, so this is slow enough to
// cost the site nothing and quick enough that the light is never telling yesterday's news.
define('BROADCAST_LIGHT_DELAY', 10000);

// What a page of broadcasts is about.
//
// A broadcast is always of one table of one round, but it is looked for from all over: from the
// round itself, from the tournament, from the club or the league or the series that runs it, from
// the address it is played at, and from the front of the site. All of those pages draw the same
// wall of squares, and they differ in two things only - which broadcasts belong to them, and
// whether the competition has to be named on every square or is already the heading of the page.
//
// Which broadcasts belong to them is kept as a condition on the events table under the alias e,
// because that is what every row of every query here hangs from: a broadcast is of an event, a
// game being played is in an event, and an event carries the club, the address and the tournament
// it belongs to. The same condition is handed to the lights - see stream_live_keys() - so that
// they are asked about exactly what the page is showing.
define('BROADCAST_SCOPE_SITE', 0);
define('BROADCAST_SCOPE_TOURNAMENT', 1);
define('BROADCAST_SCOPE_EVENT', 2);
define('BROADCAST_SCOPE_CLUB', 3);
define('BROADCAST_SCOPE_LEAGUE', 4);
define('BROADCAST_SCOPE_SERIES', 5);
define('BROADCAST_SCOPE_ADDRESS', 6);

function broadcast_scope($kind, $id, $param, $condition)
{
	$scope = new stdClass();
	$scope->kind = $kind;
	$scope->id = (int)$id;
	// How the scope is written in an address, so that the page the lights ask can be handed the
	// same scope the page itself was opened with.
	$scope->param = $param;
	$scope->condition = $condition;
	// Whether every square is of the same competition. Where it is, the name of that competition
	// is the heading of the page already and writing it on each square says nothing.
	$scope->one_competition = ($kind == BROADCAST_SCOPE_TOURNAMENT || $kind == BROADCAST_SCOPE_EVENT);
	// And whether the club has to be shown on each square. A club's own page, a tournament's, a
	// round's and a hall's have settled that by being open - a hall belongs to one club - so the
	// badge would be the same one on every square there. A league, a series and the site at large
	// have the tables of several clubs side by side, and then whose game it is is the first thing
	// somebody wants off the square.
	$scope->show_club = ($kind == BROADCAST_SCOPE_LEAGUE || $kind == BROADCAST_SCOPE_SERIES ||
		$kind == BROADCAST_SCOPE_SITE);
	return $scope;
}

function broadcast_site_scope()
{
	return broadcast_scope(BROADCAST_SCOPE_SITE, 0, '', new SQL('TRUE'));
}

function broadcast_tournament_scope($tournament_id)
{
	return broadcast_scope(BROADCAST_SCOPE_TOURNAMENT, $tournament_id, 'tournament_id=' . (int)$tournament_id,
		new SQL('e.tournament_id = ?', (int)$tournament_id));
}

function broadcast_event_scope($event_id)
{
	return broadcast_scope(BROADCAST_SCOPE_EVENT, $event_id, 'event_id=' . (int)$event_id,
		new SQL('e.id = ?', (int)$event_id));
}

function broadcast_club_scope($club_id)
{
	return broadcast_scope(BROADCAST_SCOPE_CLUB, $club_id, 'club_id=' . (int)$club_id,
		new SQL('e.club_id = ?', (int)$club_id));
}

// Everything streamed from one hall. The round's own address is what counts rather than the
// tournament's: a tournament is entered with one address and its rounds can be played at others,
// and what is asked here is what was broadcast from this hall.
function broadcast_address_scope($address_id)
{
	return broadcast_scope(BROADCAST_SCOPE_ADDRESS, $address_id, 'address_id=' . (int)$address_id,
		new SQL('e.address_id = ?', (int)$address_id));
}

// A league holds its tournaments through its series, which is how its own pages of tournaments
// and of games find them too.
function broadcast_league_scope($league_id)
{
	return broadcast_scope(BROADCAST_SCOPE_LEAGUE, $league_id, 'league_id=' . (int)$league_id,
		new SQL('e.tournament_id IN (SELECT st.tournament_id FROM series_tournaments st' .
			' JOIN series s ON s.id = st.series_id WHERE s.league_id = ?)', (int)$league_id));
}

// A series counts its subseries as its own, the way its standings and its games do, so the whole
// tree of them is taken - see get_subseries_csv(). The series pages are asked for here rather
// than at the top of the file on purpose: this file is loaded by everything that writes a game
// down, and that has no business dragging them in after it.
function broadcast_series_scope($series_id)
{
	require_once __DIR__ . '/series.php';
	return broadcast_scope(BROADCAST_SCOPE_SERIES, $series_id, 'series_id=' . (int)$series_id,
		new SQL('e.tournament_id IN (SELECT st.tournament_id FROM series_tournaments st' .
			' WHERE st.series_id IN (' . get_subseries_csv((int)$series_id) . '))'));
}

// The scope an address names. The lights are asked with the same parameter the page carries, so
// this is the one place that reads it, and anything it cannot make sense of is the whole site.
function broadcast_request_scope()
{
	if (isset($_REQUEST['tournament_id']) && (int)$_REQUEST['tournament_id'] > 0)
	{
		return broadcast_tournament_scope((int)$_REQUEST['tournament_id']);
	}
	if (isset($_REQUEST['event_id']) && (int)$_REQUEST['event_id'] > 0)
	{
		return broadcast_event_scope((int)$_REQUEST['event_id']);
	}
	if (isset($_REQUEST['club_id']) && (int)$_REQUEST['club_id'] > 0)
	{
		return broadcast_club_scope((int)$_REQUEST['club_id']);
	}
	if (isset($_REQUEST['league_id']) && (int)$_REQUEST['league_id'] > 0)
	{
		return broadcast_league_scope((int)$_REQUEST['league_id']);
	}
	if (isset($_REQUEST['series_id']) && (int)$_REQUEST['series_id'] > 0)
	{
		return broadcast_series_scope((int)$_REQUEST['series_id']);
	}
	if (isset($_REQUEST['address_id']) && (int)$_REQUEST['address_id'] > 0)
	{
		return broadcast_address_scope((int)$_REQUEST['address_id']);
	}
	return broadcast_site_scope();
}

// Whether a broadcast is over, as the database can answer it. It used to be worked out after the
// rows were read, which is no longer enough: the ones that are over are paged through, and a page
// cannot be cut out of a list the server has not sorted into those that belong on it.
//
// What the broadcast says of itself comes first, because that is the stream rather than the plan -
// but it is only ever read by the machines that stream and by the preparation page, so for one
// nobody has read, which is every broadcast entered before any of this was written, the day it
// was played on answers instead.
function broadcast_past_expr($now)
{
	return '(b.stream_end IS NOT NULL OR e.start_time + e.duration < ' . (int)$now .
		' OR (e.flags & ' . EVENT_FLAG_FINISHED . ') <> 0)';
}

// And whether one that is not over has begun. A stream that has said when it went on the air has;
// otherwise, again, the day it is played on answers.
function broadcast_started_expr($now)
{
	return '(b.stream_start IS NOT NULL OR e.start_time <= ' . (int)$now . ')';
}

// What the light says of itself, in one place: it is written into the page when the page is
// drawn and into the script that keeps it up to date, and the two must say the same thing.
//
// Three things, not two. A table with no game on it is either between games or has not started
// playing yet, and those are not the same answer to somebody deciding whether to open it: one
// is a table that has been playing all evening and is changing over, the other a camera
// pointed at an empty hall. Green, amber and red, in that order.
function broadcast_light_labels()
{
	$labels = new stdClass();
	$labels->on = get_label('A game is on');
	$labels->idle = get_label('Between games');
	$labels->off = get_label('No games yet');
	return $labels;
}

// The light itself, in the state the table is in. The name of the table is written on it so that
// the script can find it again without being told the order the squares came out in - and that
// name carries the competition as well as the number, because the page of a club has the tables
// of several of them on it. See stream_live_key().
function broadcast_light_html($key, $on, $played)
{
	$labels = broadcast_light_labels();
	$state = $on ? 'on' : ($played ? 'idle' : 'off');
	$title = $on ? $labels->on : ($played ? $labels->idle : $labels->off);
	return '<span class="bc-light ' . $state . '" data-live="' . htmlspecialchars($key) .
		'" title="' . htmlspecialchars($title) . '"></span>';
}

// The lights, kept up to date while the page is open. Somebody picking a table to follow is
// choosing between tables that are playing and tables that are between games, and that changes
// every few minutes all evening - nobody reloads the page to find out. So the lights ask the
// site which tables are playing and recolour themselves; see api/get/live_tables.php. Nothing
// else on the page moves, because nothing else changes that often: a broadcast beginning or
// ending adds or moves a square, and that is worth a reload.
function show_broadcast_lights_script($scope)
{
	$labels = broadcast_light_labels();
	$options = new stdClass();
	$options->url = 'api/get/live_tables.php' . ($scope->param == '' ? '' : ('?' . $scope->param));
	$options->delay = BROADCAST_LIGHT_DELAY;
	$options->onLabel = $labels->on;
	$options->idleLabel = $labels->idle;
	$options->offLabel = $labels->off;

	$script = <<<'SCRIPT'
$(function()
{
	var lights = $('span.bc-light');
	if (lights.length == 0)
	{
		return;
	}

	function ask()
	{
		$.getJSON(broadcastLights.url, function(answer)
		{
			// An answer that carries an error is not news about the tables: the site could not
			// say what is happening at them. Taken as "nobody is playing" it would turn every
			// light red while the games go on, so it is passed over and the lights stand.
			if (!answer || answer.error || !answer.playing)
			{
				return;
			}

			var on = {};
			for (var i = 0; i < answer.playing.length; ++i)
			{
				on[answer.playing[i]] = true;
			}

			// And which of them have played at all today. A table whose first game ends while
			// somebody has this page open goes from red to amber there and then, which is the
			// one change on the page worth watching for: that is the table coming to life.
			var had = {};
			var played = answer.played || [];
			for (var i = 0; i < played.length; ++i)
			{
				had[played[i]] = true;
			}

			lights.each(function()
			{
				var key = $(this).attr('data-live');
				var state = (on[key] === true) ? 'on' : ((had[key] === true) ? 'idle' : 'off');
				$(this).attr('class', 'bc-light ' + state);
				$(this).attr('title', (state == 'on') ? broadcastLights.onLabel :
					((state == 'idle') ? broadcastLights.idleLabel : broadcastLights.offLabel));
			});
		});
	}

	setInterval(ask, broadcastLights.delay);
});
SCRIPT;

	echo '<script>var broadcastLights = ' . json_encode($options) . ';' . $script . '</script>';
}

// One square: whose game it is and which table of which round, the picture to click in the
// middle, and what kind of broadcast it is underneath. The competition and the table are what
// somebody is looking for, so they are the heading; the kind is how they will be watching it,
// which the picture has already said.
function show_broadcast_tile($tile)
{
	// The badge of the club, where the page has the tables of more than one of them on it - see
	// broadcast_scope(). It leads to the club, as a club's badge does everywhere else on the site.
	//
	// It stands against the left edge of the square while the words stay in the middle of it, and
	// that is why it is placed rather than set in the line: the names run to two and three lines,
	// and a badge in the line would both move with them and push them off centre. The words are
	// kept clear of it by the same room on either side, so that they are centred on the square
	// itself and no long name ever runs under the badge.
	//
	// The square fills the height of its place in the row, and the words take whatever is left
	// over, so that the picture and the bar under it stand at the same height on every square of
	// the row however many lines the names run to. All the squares of a row are as tall as the
	// tallest of them - that is how a row of cells works - and without this the picture and the
	// bar hung from the words and stepped up and down across the page.
	//
	// Three things together do it, and none of them alone: the cell the square sits in is given
	// a height, so that the square has something to be a share of (see show_broadcast_group());
	// the square is told to fill it; and the row of words is told to fill what the other two
	// rows leave. Without that last one the spare height is shared out among all three rows and
	// the picture and the bar move again, by less. A browser that will not work the height out
	// leaves the square as it was.
	$badge = (isset($tile->club_id) && $tile->club_id > 0);
	echo '<table class="transp" width="100%" style="height: 100%;">';
	echo '<tr class="dark" style="height: 100%;"><td align="center" style="height: 40px;' .
		($badge ? ' position: relative;' : '') . '">';
	if ($badge)
	{
		$club_pic = new Picture(CLUB_PICTURE);
		$club_pic->set($tile->club_id, $tile->club_name, $tile->club_flags);
		echo '<span style="position: absolute; left: 2px; top: 50%; transform: translateY(-50%);">';
		$club_pic->show(ICONS_DIR, true, BROADCAST_CLUB_ICON_WIDTH);
		echo '</span>';
	}
	// A name longer than the room left beside the badge is broken rather than allowed to widen
	// the square: the columns are shares of the page, and one long word in one of them would
	// pull the whole row of squares out of true.
	echo '<b' . ($badge ? (' style="display: block; padding: 0px ' . (BROADCAST_CLUB_ICON_WIDTH + 4) .
		'px; word-wrap: break-word;"') : '') . '>' . $tile->caption . '</b></td></tr>';
	echo '<tr style="height: 70px;"><td align="center">';
	echo '<a href="' . htmlspecialchars($tile->url) . '" target="_blank" title="' .
		htmlspecialchars(str_replace('<br>', ' ', $tile->caption)) . '">';
	echo '<img src="' . $tile->icon . '" width="' . BROADCAST_ICON_WIDTH . '"></a>';
	echo '</td></tr>';
	echo '<tr class="dark" style="height: 30px;"><td align="center">';
	// A light before the kind at a table something could be happening at right now - see
	// show_broadcast_lights_script(). A square for a broadcast that is over, or one that has
	// not begun, carries none: there is nothing for it to be saying.
	if (isset($tile->key) && isset($tile->on))
	{
		echo broadcast_light_html($tile->key, $tile->on, isset($tile->played) && $tile->played);
	}
	echo $tile->kind . '</td></tr>';
	echo '</table>';
}

// A heading and the squares under it, left to right and then down. Nothing is drawn for a
// heading with nothing under it.
function show_broadcast_group($title, $tiles)
{
	if (empty($tiles))
	{
		return;
	}

	echo '<p><table class="bordered light" width="100%">';
	echo '<tr class="darker"><td colspan="' . BROADCAST_COLUMN_COUNT . '"><b>' . $title . '</b></td></tr>';
	$column = 0;
	foreach ($tiles as $tile)
	{
		if ($column == 0)
		{
			echo '<tr>';
		}
		// The one pixel is not a height, it is a statement that there is one: a cell of a row is
		// as tall as the row whatever is asked of it, and the square inside is only allowed to
		// fill the cell once the cell has a height of its own to be filled. See
		// show_broadcast_tile(), where the rest of that is done.
		echo '<td width="' . floor(100 / BROADCAST_COLUMN_COUNT) . '%" valign="top" style="height: 1px;">';
		show_broadcast_tile($tile);
		echo '</td>';
		if (++$column >= BROADCAST_COLUMN_COUNT)
		{
			echo '</tr>';
			$column = 0;
		}
	}
	if ($column > 0)
	{
		echo '<td colspan="' . (BROADCAST_COLUMN_COUNT - $column) . '"></td></tr>';
	}
	echo '</table></p>';
}

// Puts the club on a square, where the page is one that shows it. A square whose club nothing
// says - which should not happen, since every event has one - is simply left without a badge
// rather than given a broken picture.
function broadcast_tile_club($tile, $club_id, $club_name, $club_flags)
{
	if ((int)$club_id <= 0)
	{
		return;
	}
	$tile->club_id = (int)$club_id;
	$tile->club_name = $club_name;
	$tile->club_flags = (int)$club_flags;
}

// The squares of the recordings in scope: either the ones that are over, a page of them at a
// time, or the ones that are running and the ones still to come, which are few by nature and are
// all shown at once.
function broadcast_video_tiles($scope, $past, $now)
{
	global $_page;

	$past_expr = broadcast_past_expr($now);
	$query = new DbQuery(
		'SELECT b.event_id, e.name, e.tournament_id, t.name, b.day_num, b.table_num, b.part_num, b.url, ' .
			broadcast_started_expr($now) . ', c.id, c.name, c.flags' .
		' FROM event_broadcasts b JOIN events e ON e.id = b.event_id' .
		' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
		' LEFT OUTER JOIN clubs c ON c.id = e.club_id' .
		' WHERE ' . ($past ? $past_expr : ('NOT ' . $past_expr)) . ' AND ', $scope->condition);
	if ($past)
	{
		$page = isset($_page) ? max((int)$_page, 0) : 0;
		// The latest first - somebody looking through what is over is looking for the last of it -
		// but the days and the tables of one competition in the order they were played.
		$query->add(' ORDER BY e.start_time DESC, e.id DESC, b.day_num, b.table_num, b.part_num' .
			' LIMIT ' . ($page * BROADCASTS_PAGE_SIZE) . ',' . BROADCASTS_PAGE_SIZE);
	}
	else
	{
		$query->add(' ORDER BY e.start_time, e.id, b.day_num, b.table_num, b.part_num');
	}

	// Read whole first, because a square cannot be written until it is known whether there are
	// others beside it: a part number means nothing where there is one part, and the same goes
	// for the day and for the round.
	$rows = array();
	$events = array();
	while ($row = $query->next())
	{
		$rows[] = $row;
		$events[(int)$row[0]] = true;
	}
	if (empty($rows))
	{
		return array();
	}

	$event_csv = implode(',', array_keys($events));
	$days = array();
	$parts = array();
	$query = new DbQuery('SELECT event_id, day_num, table_num, COUNT(*) FROM event_broadcasts' .
		' WHERE event_id IN (' . $event_csv . ') GROUP BY event_id, day_num, table_num');
	while ($row = $query->next())
	{
		list ($event_id, $day, $table, $count) = $row;
		$parts[(int)$event_id . '-' . (int)$day . '-' . (int)$table] = (int)$count;
		$days[(int)$event_id][(int)$day] = true;
	}

	// And how many rounds the tournaments on this page are played over: a round is worth naming
	// only where there is more than one of them to tell apart.
	$rounds = array();
	$query = new DbQuery('SELECT tournament_id, COUNT(*) FROM events WHERE tournament_id IN' .
		' (SELECT tournament_id FROM events WHERE id IN (' . $event_csv . ') AND tournament_id IS NOT NULL)' .
		' GROUP BY tournament_id');
	while ($row = $query->next())
	{
		list ($tournament_id, $count) = $row;
		$rounds[(int)$tournament_id] = (int)$count;
	}

	$tiles = array();
	foreach ($rows as $row)
	{
		list ($event_id, $event_name, $tournament_id, $tournament_name, $day, $table, $part, $url, $started,
			$club_id, $club_name, $club_flags) = $row;
		$event_id = (int)$event_id;
		$tournament_id = is_null($tournament_id) ? 0 : (int)$tournament_id;
		$day = (int)$day;
		// Tables are counted from zero in this one table and from one everywhere else.
		$table_num = (int)$table + 1;

		$tile = new stdClass();
		$tile->kind = get_label('YouTube');
		$tile->icon = 'images/video.png';
		$tile->url = $url;
		$tile->live = (!$past && (int)$started != 0);
		if ($tile->live)
		{
			// Which table it is of, kept for the light underneath. Only a square that is running
			// carries one: a broadcast that is over is over whatever the table is doing this
			// evening, and one that has not begun has nothing to be saying either.
			$tile->key = stream_live_key($tournament_id, $event_id, $table_num);
		}

		if ($scope->show_club)
		{
			broadcast_tile_club($tile, $club_id, $club_name, $club_flags);
		}

		// What is being played first, then the table it is being played at: on a page of several
		// competitions the name is what tells one square from the next, and the table only
		// narrows it down afterwards. Each on its own line, so that the tables line up across the
		// squares and the eye runs down them.
		$tile->caption = '';
		if (!$scope->one_competition)
		{
			$tile->caption = ($tournament_id > 0 ? $tournament_name : $event_name) . '<br>';
		}
		$tile->caption .= get_label('Table [0]', $table_num);

		// Then the round, but never on the round's own page - there it is the heading.
		$which = ($tournament_id > 0 && $scope->kind != BROADCAST_SCOPE_EVENT &&
			isset($rounds[$tournament_id]) && $rounds[$tournament_id] > 1) ? $event_name : '';
		if (count($days[$event_id]) > 1)
		{
			$which .= ($which == '' ? '' : ' ') . get_label('Day [0]', $day);
		}
		if ($parts[$event_id . '-' . $day . '-' . (int)$table] > 1)
		{
			// In brackets after what it is a part of, or on its own where there is nothing
			// for it to be in brackets after.
			$part = get_label('Part [0]', (int)$part);
			$which .= ($which == '') ? $part : (' (' . $part . ')');
		}
		if ($which != '')
		{
			$tile->caption .= '<br>' . $which;
		}
		$tiles[] = $tile;
	}
	return $tiles;
}

// One competition that can be followed in words, as the stream owner its tables hang under and
// the name it is to be called on a page that has more than one of them.
function broadcast_competition($owner, $param, $name, $tournament_id, $event_id,
	$club_id = 0, $club_name = '', $club_flags = 0)
{
	$competition = new stdClass();
	$competition->owner = $owner;          // what its tables hang under - see stream_owner()
	$competition->param = $param;          // how it is named in the address of a text broadcast
	$competition->name = $name;
	$competition->tournament_id = (int)$tournament_id;
	$competition->event_id = (int)$event_id;
	$competition->club_id = (int)$club_id;
	$competition->club_name = $club_name;
	$competition->club_flags = (int)$club_flags;
	return $competition;
}

// The competitions in scope that are being played right now. That is what the tables which can be
// read in words are offered for, and it is the only thing it is used for: a broadcast of its own
// says when it went on the air and when it came off it.
function broadcast_live_competitions($scope)
{
	$live = array();

	if ($scope->kind == BROADCAST_SCOPE_TOURNAMENT)
	{
		list ($name, $start_time, $flags) = Db::record(get_label('tournament'),
			'SELECT name, start_time, flags FROM tournaments WHERE id = ?', $scope->id);
		if (stream_is_live($start_time, ($flags & TOURNAMENT_FLAG_FINISHED) != 0, ($flags & TOURNAMENT_FLAG_CANCELED) != 0))
		{
			$live[] = broadcast_competition(stream_tournament($scope->id), $scope->param, $name, $scope->id, 0);
		}
		return $live;
	}

	if ($scope->kind == BROADCAST_SCOPE_EVENT)
	{
		// A round of a tournament is read at the tournament's tables - that is what stream_owner()
		// is for - but whether there is anything to read is the round's own answer: a round that
		// is over is over however long the tournament it belongs to runs.
		list ($name, $start_time, $flags, $tournament_id) = Db::record(get_label('event'),
			'SELECT name, start_time, flags, tournament_id FROM events WHERE id = ?', $scope->id);
		if (stream_is_live($start_time, ($flags & EVENT_FLAG_FINISHED) != 0, ($flags & EVENT_FLAG_CANCELED) != 0))
		{
			$tournament_id = is_null($tournament_id) ? 0 : (int)$tournament_id;
			$live[] = broadcast_competition(stream_owner($tournament_id, $scope->id), $scope->param,
				$name, $tournament_id, $scope->id);
		}
		return $live;
	}

	// A tournament is in scope wherever a round of it is: the club, the address and the series a
	// round belongs to are the tournament's own, and a tournament with no rounds at all has no
	// table to read either. The two questions stream_is_live() asks, asked of the database: it has
	// begun, and nobody has marked it over or called it off.
	$now = time();
	$query = new DbQuery(
		'SELECT DISTINCT t.id, t.name, t.start_time, c.id, c.name, c.flags' .
		' FROM events e JOIN tournaments t ON t.id = e.tournament_id' .
		' LEFT OUTER JOIN clubs c ON c.id = t.club_id' .
		' WHERE t.start_time <= ' . $now .
		' AND (t.flags & ' . (TOURNAMENT_FLAG_FINISHED | TOURNAMENT_FLAG_CANCELED) . ') = 0 AND ', $scope->condition);
	$query->add(' ORDER BY t.start_time, t.id');
	while ($row = $query->next())
	{
		list ($id, $name, $start_time, $club_id, $club_name, $club_flags) = $row;
		$live[] = broadcast_competition(stream_tournament($id), 'tournament_id=' . (int)$id, $name, (int)$id, 0,
			$club_id, $club_name, $club_flags);
	}

	$query = new DbQuery(
		'SELECT e.id, e.name, e.start_time, c.id, c.name, c.flags FROM events e' .
		' LEFT OUTER JOIN clubs c ON c.id = e.club_id' .
		' WHERE e.tournament_id IS NULL' .
		' AND e.start_time <= ' . $now .
		' AND (e.flags & ' . (EVENT_FLAG_FINISHED | EVENT_FLAG_CANCELED) . ') = 0 AND ', $scope->condition);
	$query->add(' ORDER BY e.start_time, e.id');
	while ($row = $query->next())
	{
		list ($id, $name, $start_time, $club_id, $club_name, $club_flags) = $row;
		$live[] = broadcast_competition(stream_event($id), 'event_id=' . (int)$id, $name, 0, (int)$id,
			$club_id, $club_name, $club_flags);
	}
	return $live;
}

// And the tables that can be read in words. They are of the thing being played rather than of a
// broadcast of it, there is one for each table being played at, and they are running by
// definition: nothing is offered here unless there is something to read right now.
function broadcast_text_tiles($scope)
{
	$tiles = array();
	foreach (broadcast_live_competitions($scope) as $competition)
	{
		foreach (stream_text_tables($competition->owner) as $table_num)
		{
			// Nothing but the table in the address: the words come out in the language the table
			// is set to show, which the page reads for itself - see stream_request_lang().
			$tile = new stdClass();
			$tile->kind = get_label('Textual');
			$tile->icon = 'images/text.png';
			$tile->url = 'text_broadcast.php?' . $competition->param . '&table_num=' . $table_num;
			if ($scope->show_club)
			{
				broadcast_tile_club($tile, $competition->club_id, $competition->club_name, $competition->club_flags);
			}
			// What is being played first and the table afterwards, as on the squares of a
			// recording - see broadcast_video_tiles().
			$tile->caption = $scope->one_competition ? '' : ($competition->name . '<br>');
			$tile->caption .= get_label('Table [0]', $table_num);
			$tile->live = true;
			$tile->key = stream_live_key($competition->tournament_id, $competition->event_id, $table_num);
			$tiles[] = $tile;
		}
	}
	return $tiles;
}

// Everything there is to follow in scope, as a wall of squares under two tabs: one for what is on
// the air and what is still to come, one for what is over. A round shows its own broadcasts, a
// tournament every round's, and a club, a league, a series, an address and the whole site all of
// theirs - so every one of those pages calls this and none of them draws anything of its own.
//
// The tab of what is over is paged through, because it only ever grows: a club that has been
// streaming for years has thousands of recordings and nobody scrolls through them. The other one
// is not, because there is only ever a handful of things on the air at once. The page opens on
// what there is to watch, and where there is none of it, on the history instead - an empty page
// with a tab to press on it is a page that looks broken.
function show_broadcasts($scope)
{
	$now = time();
	$from = ' FROM event_broadcasts b JOIN events e ON e.id = b.event_id WHERE ';
	$past_expr = broadcast_past_expr($now);
	list ($past_count) = Db::record(get_label('broadcast'),
		'SELECT COUNT(*)' . $from . $past_expr . ' AND ', $scope->condition);
	list ($current_count) = Db::record(get_label('broadcast'),
		'SELECT COUNT(*)' . $from . 'NOT ' . $past_expr . ' AND ', $scope->condition);
	$past_count = (int)$past_count;
	$current_count = (int)$current_count;

	// Pressing a tab says so in the address, and then which of them to open is settled and is not
	// asked again.
	$view = isset($_REQUEST['view'])
		? (((int)$_REQUEST['view'] == BROADCASTS_VIEW_PAST) ? BROADCASTS_VIEW_PAST : BROADCASTS_VIEW_CURRENT)
		: -1;

	// The tables being read in words are of the thing itself rather than of a broadcast of it, so
	// they are in neither count - and they are what makes the first tab worth opening at a club
	// whose whole stream is a referee with a keyboard. They are asked after only where they could
	// be shown, since asking means going round every competition that is on.
	$text = ($view == BROADCASTS_VIEW_PAST) ? array() : broadcast_text_tiles($scope);
	if ($view < 0)
	{
		if ($current_count <= 0 && empty($text) && $past_count <= 0)
		{
			echo '<p>' . get_label('No broadcasts yet.') . '</p>';
			return;
		}
		$view = ($current_count > 0 || !empty($text)) ? BROADCASTS_VIEW_CURRENT : BROADCASTS_VIEW_PAST;
	}

	echo '<div class="tab">';
	echo '<button ' . ($view == BROADCASTS_VIEW_CURRENT ? 'class="active" ' : '') .
		'onclick="goTo({view:' . BROADCASTS_VIEW_CURRENT . ', page:undefined})">' .
		get_label('Live and upcoming') . '</button>';
	echo '<button ' . ($view == BROADCASTS_VIEW_PAST ? 'class="active" ' : '') .
		'onclick="goTo({view:' . BROADCASTS_VIEW_PAST . ', page:undefined})">' .
		get_label('Past') . '</button>';
	echo '</div>';
	echo '<div class="tabcontent">';

	if ($view == BROADCASTS_VIEW_PAST)
	{
		show_pages_navigation(BROADCASTS_PAGE_SIZE, $past_count);
		$tiles = broadcast_video_tiles($scope, true, $now);
		if (empty($tiles))
		{
			echo '<p>' . get_label('No broadcasts yet.') . '</p>';
		}
		show_broadcast_group(get_label('Past'), $tiles);
	}
	else
	{
		$live = array();
		$future = array();
		foreach (broadcast_video_tiles($scope, false, $now) as $tile)
		{
			if ($tile->live)
			{
				$live[] = $tile;
			}
			else
			{
				$future[] = $tile;
			}
		}
		$live = array_merge($live, $text);

		// Which of those tables are playing right now and which have played at all, asked once
		// each for the whole page. They come out in that order: a table with a game on is what
		// somebody came for, a table between games is worth opening and waiting at, and a table
		// that has not begun is worth neither yet. On a page that lists a whole league there can
		// be rows of squares before the first of them.
		//
		// Sorted by hand rather than with usort(), which does not promise to leave equals where it
		// found them on every PHP we run on - and the order they are already in is the order the
		// rounds and the tables were played in, which is worth keeping.
		if (!empty($live))
		{
			$playing = stream_live_keys($scope->condition);
			$played = stream_played_keys($scope->condition, $now);
			$on = array();
			$idle = array();
			$off = array();
			foreach ($live as $tile)
			{
				$tile->on = isset($playing[$tile->key]);
				$tile->played = isset($played[$tile->key]);
				if ($tile->on)
				{
					$on[] = $tile;
				}
				else if ($tile->played)
				{
					$idle[] = $tile;
				}
				else
				{
					$off[] = $tile;
				}
			}
			$live = array_merge($on, $idle, $off);
		}

		if (empty($live) && empty($future))
		{
			echo '<p>' . get_label('Nothing is on the air right now.') . '</p>';
		}
		show_broadcast_group(get_label('Live now'), $live);
		show_broadcast_group(get_label('Future'), $future);

		// And from here on they keep themselves up to date.
		if (!empty($live))
		{
			show_broadcast_lights_script($scope);
		}
	}

	echo '</div>';
}

?>
