<?php

require_once 'include/session.php';
require_once 'include/security.php';   // check_permissions()
require_once 'include/seating.php';

initiate_session();

// The seating of a round as a file to keep, in whichever of the three ways was asked for:
// a sheet of nicknames separated by tabs or by commas, which a person can read, move about in a
// spreadsheet and hand back to the upload on the preparation page, or a DimTom schedule for the
// tool the seatings are arranged in. seating_to_text() and seating_to_dimtom() in
// include/seating.php have the formats, and import_seating in api/ops/event.php is the way back
// in - it reads all three.
//
// A page of its own rather than an api call, because what comes out is the file itself. The
// browser is simply sent to this address and saves what it is given; wrapping the sheet in json
// would only mean unwrapping it again in the browser and building the download there.

try
{
	global $_lang;

	$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
	if ($event_id <= 0)
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}

	// Which of the three ways out was asked for; form/seating_export.php is the dialog that
	// asks. Anything unknown is read as the tab separated sheet, which is what this page handed
	// out before there was a choice to make.
	$format = isset($_REQUEST['format']) ? strtolower(trim($_REQUEST['format'])) : 'tsv';

	// The tournament's name comes along for the file name. A left join: a round can belong to no
	// tournament at all, and then there is simply nothing to put in front of its own name.
	list($club_id, $tournament_id, $misc_str, $event_name, $tournament_name) = Db::record(get_label('event'),
		'SELECT e.club_id, e.tournament_id, e.misc, ne.name, t.name FROM events e' .
		' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
		' LEFT JOIN tournaments t ON t.id = e.tournament_id WHERE e.id = ?', $event_id);

	// Whoever may replace the seating may certainly read it out, so this asks for no more than
	// the upload does - see PERMISSION_REFEREE in api/ops/event.php, which is this same list.
	check_permissions(
		PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
		PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE,
		$club_id, $event_id, $tournament_id);

	$misc = is_null($misc_str) ? NULL : json_decode($misc_str);
	if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
	{
		throw new Exc(get_label('There is no seating for this round yet.'));
	}
	$seating = $misc->seating;

	// A seating made before the mapping was introduced holds user ids in its seats rather than
	// places in a mapping. Both are written out the same way: what matters here is only what
	// each seat says and whose name that is.
	$mapping = isset($seating->mapping) ? (array)$seating->mapping : NULL;
	if (is_null($mapping))
	{
		$mapping = array();
		foreach ($seating->rounds as $round)
		{
			foreach ((array)$round as $table)
			{
				foreach ((array)$table as $seat)
				{
					$mapping[(int)$seat] = (int)$seat;
				}
			}
		}
	}

	$user_ids = array();
	foreach ($mapping as $entry)
	{
		$uid = is_object($entry) ? (isset($entry->id) ? (int)$entry->id : 0) : (int)$entry;
		if ($uid > 0)
		{
			$user_ids[] = $uid;
		}
	}
	// The players of this round, which is what decides whether a nickname can stand on its own
	// in the sheet. The upload asks the same question of the same list, so the two agree on
	// which nicknames have to carry an id - see seating_slot_names().
	list($by_name, $ids) = seating_round_players($event_id, $tournament_id, $misc);
	$slot_names = seating_slot_names($mapping, seating_user_names($user_ids, $_lang), $by_name);

	if ($format == 'dimtom')
	{
		$text = seating_to_dimtom($seating->rounds, $slot_names);
		$extension = '.json';
		$content_type = 'application/json; charset=utf-8';
	}
	else if ($format == 'csv')
	{
		$text = seating_to_text($seating->rounds, $slot_names, ',');
		$extension = '.csv';
		$content_type = 'text/csv; charset=utf-8';
	}
	else
	{
		$text = seating_to_text($seating->rounds, $slot_names);
		$extension = '.txt';
		$content_type = 'text/plain; charset=utf-8';
	}

	// "<tournament> <round> <seating>", so that a folder full of these says which seating each
	// one is without opening any of them. Either of the first two may be missing - a round of
	// no tournament, a round with no name of its own - and then it is simply left out; the last
	// word is in the language the page is being read in and is always there, so the name never
	// comes out empty.
	//
	// A nickname may be in any alphabet and so may a tournament's name, and a header carries
	// bytes rather than text - hence the two forms of it below: filename for a browser that
	// reads only the plain one, filename* for everything of this century. See RFC 5987.
	$parts = array();
	foreach (array($tournament_name, $event_name) as $part)
	{
		$part = trim(preg_replace('/\s+/u', ' ', (string)$part));
		if ($part != '')
		{
			$parts[] = $part;
		}
	}
	$parts[] = get_label('Seating');
	// Only letters, digits and the quietest punctuation: these may be called anything, and a
	// name is about to be put in a header and then saved as a file.
	$base = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\p{L}\p{N} ._-]/u', '', implode(' ', $parts))));
	$name = $base . $extension;

	// The plain name is for a browser that reads only that one. What is not ASCII is dropped
	// rather than turned into underscores - a Russian tournament would otherwise come out as a
	// row of them - and if nothing readable is left at all, the round's id stands in.
	$plain = trim(preg_replace('/ +/', ' ', preg_replace('/[^A-Za-z0-9._ -]/', ' ', $base)));
	// A word dropped from the middle can leave a hyphen or a dot hanging at either end.
	$plain = trim($plain, ' ._-');
	if (!preg_match('/[A-Za-z0-9]/', $plain))
	{
		$plain = 'seating-' . $event_id;
	}
	$plain .= $extension;

	header('Content-Type: ' . $content_type);
	header('Content-Disposition: attachment; filename="' . $plain . '"; filename*=UTF-8\'\'' . rawurlencode($name));
	header('Content-Length: ' . strlen($text));
	header('Cache-Control: no-store');
	echo $text;
}
catch (Throwable $e)
{
	// Nothing has been sent yet when this is reached - the sheet is built whole before a single
	// header goes out - so there is still a page to say it on. Throwable rather than Exception:
	// since PHP 7 a mistake in the code itself arrives as an Error, which is not an Exception, and
	// left uncaught here it would hand the browser a file made of an error message.
	Exc::log($e);
	echo '<html><body><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>';
}

?>
