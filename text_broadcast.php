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
// The headings between which the lines are grouped. They are made here so that they are in the
// language the rest of the page is in, rather than built out of pieces in the script.
$options->dayLabel = get_label('Day [0]', '[0]');
$options->nightLabel = get_label('Night [0]', '[0]');

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
echo '<iframe class="tb-overlay" id="tb-overlay" scrolling="no" src="plugins/obs/overlay.php?' . $scope . '&viewer=1"></iframe>';
echo '<iframe class="tb-between" id="tb-between" scrolling="no" src="plugins/obs/between.php?' . $scope . '&viewer=1"></iframe>';
echo '</div>';

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

	// Between games - and while the roles are being handed out, and while the referee is
	// entering the bonus points at the end - a stream shows the between-games screens over the
	// whole picture. So does this.
	function setLive(live)
	{
		between.className = 'tb-between' + (live ? ' tb-hidden' : '');
		overlay.className = 'tb-overlay' + (live ? '' : ' tb-hidden');
		text.className = 'tb-text' + (live ? '' : ' tb-hidden');
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

	function ask()
	{
		fetch(options.gameUrl).
			then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
			then(function(answer)
			{
				var game = (answer && answer.game) ? answer.game : null;
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

	setLive(false);
	ask();
	setInterval(ask, options.delay);
})();
</script>
</body></html>
