<?php

require_once 'include/session.php';
require_once 'include/stream.php'; // the language this table is set to show

// The text broadcast of one table: what a stream of that table would carry, for a tournament
// that is not streaming it - or for somebody who would rather read than watch.
//
// It is the stream's own pages, not a copy of them. The cards are plugins/obs/overlay.php laid
// over the page, exactly as they are laid over the picture in OBS, and what is shown between
// games is plugins/obs/between.php, which is what a stream cuts to between them. In the middle,
// where the picture would be, the game is told in words - the same words view_game.php shows
// under a game that has been played, line by line as the moderator enters them. See
// include/game_log.php.
//
// The address names a table and nothing else:
//   tournament_id or event_id, together with table_num.
// Everything else - which cards are shown, which screens stand between games, whether the roles
// are given away - is set on the site under Management / Stream settings, the same settings the
// stream of that table uses, and reaches this page without anybody retyping a link.

define('TB_DELAY', 2000); // how often (in milliseconds) the page asks what is happening
// And how often it asks whether the table has been set up differently since. Only the language
// matters to this page, and a language is changed by hand once in a while, so it is asked for
// far more rarely than the game itself.
define('TB_SETTINGS_DELAY', 10000);

// In the language the table is set to show, the same as the stream of it - see
// stream_request_lang(). Nothing about this page is in its address but the table.
initiate_session(stream_request_lang_code());

$tournament_id = isset($_REQUEST['tournament_id']) ? (int)$_REQUEST['tournament_id'] : 0;
$event_id = isset($_REQUEST['event_id']) ? (int)$_REQUEST['event_id'] : 0;
$table_num = isset($_REQUEST['table_num']) ? max((int)$_REQUEST['table_num'], 1) : 1;

// Whose table it is, for the title of the window. A round names its tournament, because that
// is what somebody reading has come for.
$title = '';
if ($tournament_id > 0)
{
	list ($title) = Db::record(get_label('tournament'), 'SELECT name FROM tournaments WHERE id = ?', $tournament_id);
}
else if ($event_id > 0)
{
	list ($title, $event_tournament_id) = Db::record(get_label('event'),
		'SELECT e.name, e.tournament_id FROM events e WHERE e.id = ?', $event_id);
	if (!is_null($event_tournament_id))
	{
		list ($tournament_name) = Db::record(get_label('tournament'),
			'SELECT name FROM tournaments WHERE id = ?', (int)$event_tournament_id);
		$title = $tournament_name . ' - ' . $title;
	}
}
else
{
	$title = get_label('Text broadcast');
}
$title .= ' - ' . get_label('Table [0]', $table_num);

// What travels with every address this page builds. The children are pages of their own and
// know nothing of this one, so the table has to be named again in each of them.
$scope = ($event_id > 0 ? 'event_id=' . $event_id : 'tournament_id=' . $tournament_id) .
	'&table_num=' . $table_num;
$locale = get_lang_code($_lang);

$options = new stdClass();
$options->gameUrl = 'api/get/current_game.php?' . $scope . '&log=1&lang=' . $locale;
$options->delay = TB_DELAY;
// The language a table is shown in can be changed on the site while it is being broadcast. The
// plates inside the two frames watch for that and load themselves again; this is the page around
// them doing the same for its own words - the headings between the lines, the button, and the
// log itself, which the site writes in the language this page asks it for. None of it can be put
// into another language where it stands, so the page is loaded again instead.
//
// A table with no language of its own is left out of it: the page then follows the reader - the
// address or their session - and the answer would say English against a page they are reading in
// their own language. See stream_request_lang(), which is what both sides of this read.
$stream_lang = stream_request_lang();
$options->locale = ($stream_lang > 0) ? get_lang_code($stream_lang) : NULL;
// viewer=1: a reader is not the table, and nothing they ask for is word from it - see
// api/get/stream.php.
$options->settingsUrl = 'api/get/stream.php?op=settings&' . $scope . '&viewer=1';
$options->settingsInterval = TB_SETTINGS_DELAY;
// The headings between which the lines are grouped. They are made here so that they are in the
// language the rest of the page is in, rather than built out of pieces in the script.
$options->dayLabel = get_label('Day [0]', '[0]');
$options->nightLabel = get_label('Night [0]', '[0]');
// What the full screen button says it does, which is one thing or the other depending on where
// the page already is.
$options->fullLabel = get_label('Full screen');
$options->windowLabel = get_label('Leave full screen');

echo '<!DOCTYPE HTML>';
echo '<html><head>';
echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>' . htmlspecialchars($title) . '</title>';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700">';
echo '<link rel="stylesheet" href="text_broadcast.css?' . filemtime(__DIR__ . '/text_broadcast.css') . '">';
echo '</head><body>';

echo '<div class="tb-stage">';
echo '<div class="tb-text" id="tb-text"></div>';
// viewer=1 says that these are being watched rather than run. They are the same pages a stream
// puts over its picture, but nothing here is standing beside the table, so nothing they ask for
// is to be taken as word from it - see api/get/stream.php.
//
// bg=light says what they are laid on. The stage here is a pale sheet - see .tb-stage - and the
// marks those pages make are made of light, which on a pale sheet is no mark at all.
echo '<iframe class="tb-overlay" id="tb-overlay" scrolling="no" src="plugins/obs/overlay.php?' . $scope . '&viewer=1&bg=light"></iframe>';
echo '<iframe class="tb-between" id="tb-between" scrolling="no" src="plugins/obs/between.php?' . $scope . '&viewer=1"></iframe>';
echo '</div>';

// Full screen, and with it the phone turned the way the drawing is. A browser will do neither
// unless the reader asked for it, so it hangs on a button; the script takes the button away
// where the browser cannot do it at all.
echo '<button class="tb-full" id="tb-full" type="button" title="' . get_label('Full screen') . '">';
echo '<svg class="tb-enter" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9V3h6v2H5v4H3zm12-6h6v6h-2V5h-4V3zM3 15h2v4h4v2H3v-6zm16 0h2v6h-6v-2h4v-4z"/></svg>';
echo '<svg class="tb-leave" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h2v6H5V7h4V3zm4 0h2v4h4v2h-6V3zM5 15h6v6H9v-4H5v-2zm8 0h6v2h-4v4h-2v-6z"/></svg>';
echo '</button>';

?>
<script>
(function()
{
	var options = <?php echo json_encode($options); ?>;
	var text = document.getElementById('tb-text');
	var between = document.getElementById('tb-between');
	var overlay = document.getElementById('tb-overlay');

	// What is on the page now, a line to an entry, each one the day or night it belongs to and
	// the line itself. It is held as flat text so that what came back can be compared with it
	// without walking the page.
	var shown = [];
	var head = null; // the heading the next line goes under, as it stands on the page
	var wasLive = null; // whether a game was being played the last time we heard, if we have
	var waiting = false; // true while the screens are getting ready to be shown - see hold()

	// Which of the two the page is showing: the game, or the screens that stand between games.
	function faces(live)
	{
		between.className = 'tb-between' + (live ? ' tb-hidden' : '');
		overlay.className = 'tb-overlay' + (live ? '' : ' tb-hidden');
		text.className = 'tb-text' + (live ? '' : ' tb-hidden');
	}

	// Between games - and while the roles are being handed out, and while the referee is
	// entering the bonus points at the end - a stream shows the between-games screens over the
	// whole picture. So does this.
	//
	// The screens are a page of their own, in a frame, and they are told whether anybody can
	// see them - which is exactly what OBS tells them when it is a browser source. While a game
	// is on they stand down: they stop turning over and wait on the screen their round begins
	// with, so that when the game ends it is the first screen that is already there and not
	// whichever one a clock had reached behind the game.
	function setLive(live)
	{
		if (wasLive === live)
		{
			return; // nothing has changed since we last heard
		}
		var first = (wasLive === null);
		wasLive = live;

		if (live)
		{
			waiting = false;
			tell(false);
			faces(true);
			return;
		}

		// A game has just ended. The screens have been standing on the first one of their round
		// all through it, and they fetch it afresh now - the game that has just been played is
		// what it is about. That takes a moment, and the page waits it out with the game still
		// on it rather than showing the screens halfway there - see hold().
		if (!first && hold())
		{
			return;
		}
		waiting = false;
		tell(true);
		faces(false);
	}

	// Tells the screens whether they are being watched. Returns false where there is nobody to
	// tell - the frame is a page like any other and may not have got that far yet.
	function tell(on, done)
	{
		var inside = null;
		try
		{
			inside = between.contentWindow;
		}
		catch (error) { }

		if (!inside || !inside.obs || !inside.obs.shown)
		{
			return false;
		}
		inside.obs.shown(on, done);
		return true;
	}

	// Holds the game on the page until the screens say the first one of their round is up.
	// Returns false where there is nobody to ask, and then they are simply shown.
	function hold()
	{
		waiting = true;
		var show = function()
		{
			if (!waiting)
			{
				return; // shown already, or a game began again while we waited
			}
			waiting = false;
			faces(false);
		};

		if (!tell(true, show))
		{
			waiting = false;
			return false;
		}

		// The screens are fetched from the site like everything else, and the site can be slow
		// or not answer at all. The page is not held on them longer than it takes to notice.
		setTimeout(show, 2000);
		return true;
	}

	function flatten(parts)
	{
		var flat = [];
		for (var i = 0; i < parts.length; ++i)
		{
			var part = parts[i];
			for (var j = 0; j < part.lines.length; ++j)
			{
				flat.push({
					key: (part.night ? 'n' : 'd') + part.round,
					night: part.night,
					round: part.round,
					line: part.lines[j]
				});
			}
		}
		return flat;
	}

	function addLine(entry, fresh)
	{
		if (head === null || head.key !== entry.key)
		{
			var part = document.createElement('div');
			part.className = 'tb-part' + (entry.night ? ' night' : '');
			var heading = document.createElement('div');
			heading.className = 'tb-part-head';
			heading.textContent = (entry.night ? options.nightLabel : options.dayLabel).replace('[0]', entry.round);
			part.appendChild(heading);
			text.appendChild(part);
			head = { key: entry.key, node: part };
		}

		var line = document.createElement('div');
		line.className = 'tb-line' + (fresh ? ' new' : '');
		line.textContent = entry.line;
		head.node.appendChild(line);
	}

	// The telling only ever grows, so what came back usually begins with what is already up and
	// only the end of it is new. When it does not - the moderator took a step back, or the next
	// game has begun - it is drawn again from the top rather than patched.
	function render(parts)
	{
		var flat = flatten(parts);
		var keep = (flat.length >= shown.length);
		if (keep)
		{
			for (var i = 0; i < shown.length; ++i)
			{
				if (shown[i] !== flat[i].key + '\n' + flat[i].line)
				{
					keep = false;
					break;
				}
			}
		}

		if (!keep)
		{
			text.innerHTML = '';
			shown = [];
			head = null;
		}
		if (flat.length === shown.length)
		{
			return;
		}

		// Reading back up the game is left alone: only somebody who is at the end of it is
		// carried along with it.
		var atEnd = (text.scrollHeight - text.scrollTop - text.clientHeight) < 40;
		for (var j = shown.length; j < flat.length; ++j)
		{
			addLine(flat[j], keep);
			shown.push(flat[j].key + '\n' + flat[j].line);
		}
		if (atEnd)
		{
			text.scrollTop = text.scrollHeight;
		}
	}

	// Whether this table is still shown in the language this page was written in. See the
	// options above, and settings() in plugins/obs/obs.js, which is the same watch kept by the
	// pages inside the frames.
	function settings()
	{
		fetch(options.settingsUrl).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				if (!answer || !answer.stream || !answer.stream.locale)
				{
					return;
				}
				if (answer.stream.locale != options.locale)
				{
					options.locale = answer.stream.locale; // so that one change asks for one reload
					window.location.reload();
				}
			}).
			catch(function(error) { /* the site is not answering: leave the page as it stands */ });
	}

	function ask()
	{
		fetch(options.gameUrl).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				// An answer that carries an error is not news about the table: the site could
				// not say what is happening there. Taken as "no game" it would put the
				// between-games screens up over a game that is being played, and leave them
				// there. So it is passed over, and the page goes on showing what it has.
				if (!answer || answer.error)
				{
					return;
				}

				var game = answer.game ? answer.game : null;
				var live = !!(game && game.live);
				setLive(live);
				if (live)
				{
					render(game.log ? game.log : []);
				}
			}).
			// The site not answering is not news: the page goes on showing what it has and asks
			// again next time round.
			catch(function(error) { });
	}

	// Full screen, and the phone turned the way the drawing is drawn. The page is one picture
	// of a fixed shape: standing it up on end leaves a strip of it across the middle of the
	// screen with nothing above or below, so the screen is asked to stay lying down. Neither
	// can be asked for except out of something the reader did, which is what the button is
	// for, and a browser that will not do it at all is left without one.
	var full = document.getElementById('tb-full');
	var root = document.documentElement;
	var enter = root.requestFullscreen || root.webkitRequestFullscreen;
	var leave = document.exitFullscreen || document.webkitExitFullscreen;

	function fullscreen()
	{
		return document.fullscreenElement || document.webkitFullscreenElement || null;
	}

	// A phone that cannot be held still - a tablet, a desktop, a phone whose browser does not
	// do this - simply says no, and the page is no worse off for having asked.
	function lie(down)
	{
		var orientation = screen.orientation;
		if (!orientation)
		{
			return;
		}
		try
		{
			var promise = down ? orientation.lock('landscape') : orientation.unlock();
			if (promise && promise.catch)
			{
				promise.catch(function(error) { });
			}
		}
		catch (error) { }
	}

	if (!enter || !leave)
	{
		full.parentNode.removeChild(full);
	}
	else
	{
		full.addEventListener('click', function()
		{
			if (fullscreen())
			{
				leave.call(document);
			}
			else
			{
				var promise = enter.call(root);
				// The screen can only be held still once the page has the whole of it, so the
				// one waits for the other. An older browser answers with nothing at all.
				if (promise && promise.then)
				{
					promise.then(function() { lie(true); }).catch(function(error) { });
				}
				else
				{
					lie(true);
				}
			}
		});

		// However the page got in or out - the button, the key that leaves full screen, the
		// phone's own way back - the button says what it would do next.
		function changed()
		{
			var on = !!fullscreen();
			full.className = 'tb-full' + (on ? ' on' : '');
			full.title = on ? options.windowLabel : options.fullLabel;
			if (!on)
			{
				lie(false);
			}
		}

		document.addEventListener('fullscreenchange', changed);
		document.addEventListener('webkitfullscreenchange', changed);
	}

	setLive(false);
	ask();
	setInterval(ask, options.delay);
	if (options.locale)
	{
		setInterval(settings, options.settingsInterval);
	}

	// A page nobody is looking at is slowed down by the browser and can be stopped outright:
	// a tab behind another one, or a window behind another window, is held to one turn a
	// minute and after a few minutes of that is frozen where it stands. So a reader who comes
	// back to this page finds it as old as the last turn the browser allowed, and without this
	// it stays that old until the next one - which is how a table that had finished its
	// voting was still being voted at here. Coming back into view is itself the moment to
	// ask. The frames do the same for the half of the page they draw; see obs.js.
	document.addEventListener('visibilitychange', function()
	{
		if (!document.hidden)
		{
			ask();
			if (options.locale)
			{
				settings();
			}
		}
	});

	// And a page the browser put away whole when the reader followed a link, to hand back
	// when they pressed Back, comes back without ever having been hidden.
	window.addEventListener('pageshow', function(event) { if (event.persisted) { ask(); } });
})();
</script>
</body></html>
