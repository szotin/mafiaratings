<?php

require_once '../../include/session.php';
require_once '../../include/stream.php';

// How often (in milliseconds) the page asks the site for the game state. The game screen itself
// saves what the moderator does once a second, so this is the other half of the delay between a
// click on a role and the card turning over on the stream.
define('OBS_RETRY_DELAY', 1000);
// How often (in milliseconds) a source asks which OBS scene the table should be showing.
define('OBS_SCENE_DELAY', 2000);
// How often (in milliseconds) a source asks how the table is set up - the language, the
// ground, whether it is to show anything at all. None of it has to follow the game, so it is
// asked for about as often as a between-games screen stays up.
define('OBS_SETTINGS_DELAY', 10000);

// Shows a browser source page for OBS Studio. $view is one of:
//   'overlay'   - the info bar and the players;
//   'gamestats' - the info bar only;
//   'players'   - the players only.
// The pages take the same request params as api/get/current_game.php plus:
//   (whether the roles are shown is decided by the game itself, see EVENT_FLAG_HIDE_ROLES)
//   hide_warnings - do not show warnings and the way the players died;
//   retry_delay   - how often (in milliseconds) the page asks the site for the game state.
//
// The language is not among them. It is what the table is set to show, under Management /
// Stream settings, like everything else about the table - see stream_request_lang(). A locale
// in the address is looked at only where the address names no table at all.
function show_obs_page($view)
{
	global $_lang; // the language initiate_session() settled on, for the script to watch

	initiate_session(stream_request_lang_code());

	$options = new stdClass();

	// The game state is taken from our own site, so the pages work the same way on production and on a testing server.
	// A page names a table - event_id or tournament_id together with table_num. user_id is what
	// the overlays were addressed by before and still works, so sources set up back then keep
	// playing without being retyped.
	$options->params = array();
	foreach (array('event_id', 'tournament_id', 'table_num', 'game_num', 'user_id', 'viewer') as $param)
	{
		if (isset($_REQUEST[$param]))
		{
			$options->params[$param] = $_REQUEST[$param];
		}
	}

	// Where to ask how this table is set up. It is kept on the site rather than in the address
	// of the source, so a change made there reaches a source that is already running.
	$options->settingsUrl = '../../api/get/stream.php?op=settings';
	$options->settingsInterval = OBS_SETTINGS_DELAY;

	// The language this page was drawn in. Its words - the referee, the round, OFFLINE - are
	// written into it here and cannot be changed after the fact, so the script watches the
	// settings for a different language and loads the page again when it finds one. That is
	// what makes a language changed on the site reach a source that is already running.
	$options->locale = get_lang_code($_lang);

	$options->hideWarnings = isset($_REQUEST['hide_warnings']) && $_REQUEST['hide_warnings'] !== 'false';
	$options->retryDelay = OBS_RETRY_DELAY;
	if (isset($_REQUEST['retry_delay']))
	{
		$options->retryDelay = max((int)$_REQUEST['retry_delay'], 100);
	}

	// The words the script writes into the overlay. They are settled here because the page is
	// drawn in the language the table is set to show, and a script cannot ask get_label().
	$options->labels = new stdClass();
	$options->labels->day = get_label('Day [0]');
	$options->labels->night = get_label('Night [0]');
	$options->labels->table = get_label('Table [0]');
	$options->labels->game = get_label('Game [0]');
	$options->labels->nominated = get_label('Nominees');
	$options->labels->noVoting = get_label('No voting this round');
	$options->labels->tieTag = get_label('Tie');
	$options->labels->townWins = get_label('Town win');
	$options->labels->mafiaWins = get_label('Mafia win');
	$options->labels->tie = get_label('Tie');
	$options->labels->gameOver = get_label('Game over');
	$options->labels->offline = get_label('OFFLINE');
	// One for a single vote, one for a few of them and one for many: Russian and Ukrainian need
	// all three, English uses the last two. The script picks by the count - see votesWord().
	$options->labels->votes = explode('|', get_label('vote|votes|votes'));
	// What the game is doing right now. A speech is not among them: the card of whoever is
	// speaking says it better, and the label would be up for most of the game.
	$options->labels->states = array(
		'notStarted' => get_label('Not started'),
		'starting' => get_label('Dealing roles'),
		'arranging' => get_label('Mafia arranging'),
		'voting' => get_label('Voting'),
		'nomineeSpeaking' => get_label('Tie speeches'),
		'shooting' => get_label('Mafia shooting'),
		'donChecking' => get_label('Don\'s check'),
		'sheriffChecking' => get_label('Sheriff\'s check'),
		'mafiaWon' => get_label('Game over'),
		'townWon' => get_label('Game over'),
		'tie' => get_label('Game over'),
		'end' => get_label('Game over'));

	echo '<!DOCTYPE HTML>';
	// The page says on the html element which of the three it is, so that the stylesheet
	// knows the size this one is meant to be given. The name is not the one the body carries:
	// the body classes are what the parts are styled by, and the root must not pick those up.
	//
	// A page somebody is watching rather than one standing in front of a camera says so too:
	// the scrims the pills and the cards are given to read over a picture have no picture to
	// read over there, and on the light ground of a text broadcast they are a grey haze over
	// the words. See viewer in text_broadcast.php.
	$viewed = (isset($_REQUEST['viewer']) && (int)$_REQUEST['viewer'] != 0);
	echo '<html class="view-' . $view . ($viewed ? ' no-scrim' : '') . '"><head>';
	echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
	echo '<title>' . get_label('OBS Studio integration') . '</title>';
	echo '<link rel="stylesheet" href="' . obs_file('obs.css') . '">';
	echo '<script src="' . obs_file('obs.js') . '"></script>';
	// Changes the OBS scene from inside the source, which is why there is no
	// obs-websocket here. Outside OBS it does nothing at all.
	echo '<script src="' . obs_file('scenes.js') . '"></script>';
	echo '</head><body>';

	// The stage everything is placed against. It is the same drawing in all three views, with
	// the parts the view does not show left out of it - see obs.css, which gives each view the
	// height it is meant to be given in OBS Studio.
	echo '<div class="ov day" id="stage">';
	if ($view != 'players')
	{
		echo '<div class="tl" id="tl"></div>';
		echo '<div class="sheet" id="nom"></div>';
		echo '<div class="tr" id="tr"></div>';
	}
	if ($view != 'gamestats')
	{
		echo '<div class="players" id="players"></div>';
	}
	// The banner that says who won. Only where the whole picture is: it is drawn across the
	// middle of the camera, which the two narrow views do not have.
	if ($view == 'overlay')
	{
		echo '<div class="sheet" id="over"></div>';
	}
	echo '</div>';

	echo '<script>obs.start(' . json_encode($options) . ');</script>';
	echo '<script>obsScenes.start("../../api/get/stream.php?op=settings", ' .
		json_encode($options->params) . ', ' . OBS_SCENE_DELAY . ');</script>';
	echo '</body></html>';
}

// OBS Studio keeps a browser source open for a whole tournament and holds on to what it once
// loaded, so a changed script or stylesheet would never reach it. Naming the file by the time it
// was changed makes every change a new address, which no cache can answer with the old file.
function obs_file($name)
{
	$time = filemtime(__DIR__ . '/' . $name);
	return $time ? $name . '?' . $time : $name;
}

?>
