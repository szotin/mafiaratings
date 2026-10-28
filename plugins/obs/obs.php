<?php

require_once '../../include/session.php';

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
define('OBS_NO_LOGO', 'images/aml_logo.png'); // the picture shown instead of a logo that we do not have

// Shows a browser source page for OBS Studio. $view is one of:
//   'overlay'   - the info bar and the players;
//   'gamestats' - the info bar only;
//   'players'   - the players only.
// The pages take the same request params as api/get/current_game.php plus:
//   locale        - the language of the page;
//   (whether the roles are shown is decided by the game itself, see EVENT_FLAG_HIDE_ROLES)
//   hide_warnings - do not show warnings and the way the players died;
//   retry_delay   - how often (in milliseconds) the page asks the site for the game state.
function show_obs_page($view)
{
	if (isset($_REQUEST['locale']))
	{
		initiate_session(correct_lang($_REQUEST['locale']));
	}
	else
	{
		initiate_session();
	}

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

	$options->hideWarnings = isset($_REQUEST['hide_warnings']) && $_REQUEST['hide_warnings'] !== 'false';
	$options->retryDelay = OBS_RETRY_DELAY;
	if (isset($_REQUEST['retry_delay']))
	{
		$options->retryDelay = max((int)$_REQUEST['retry_delay'], 100);
	}

	$options->labels = new stdClass();
	$options->labels->referee = get_label('Referee');
	$options->labels->stages = array(get_label('round'), get_label('finals'), get_label('semis'), get_label('quarters'));

	echo '<!DOCTYPE HTML>';
	// The page says on the html element which of the three it is, so that the stylesheet
	// knows the size this one is meant to be given. The name is not the one the body carries:
	// the body classes are what the parts are styled by, and the root must not pick those up.
	echo '<html class="view-' . $view . '"><head>';
	echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
	echo '<title>' . get_label('OBS Studio integration') . '</title>';
	echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700">';
	echo '<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/gagalin">';
	echo '<link rel="stylesheet" href="' . obs_file('obs.css') . '">';
	echo '<script src="' . obs_file('obs.js') . '"></script>';
	// Changes the OBS scene from inside the source, which is why there is no
	// obs-websocket here. Outside OBS it does nothing at all.
	echo '<script src="' . obs_file('scenes.js') . '"></script>';
	echo '</head><body>';

	if ($view == 'overlay')
	{
		echo '<div class="top-bar">';
		show_gamestats();
		show_referee();
		show_round();
		echo '</div>';
	}
	else if ($view == 'gamestats')
	{
		show_gamestats();
	}

	if ($view != 'gamestats')
	{
		echo '<div class="players" id="players"></div>';
	}

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

// The info bar: the best move, the checks, and the nominees. obs.js fills the sections in.
function show_gamestats()
{
	echo '<div class="gamestats" id="gamestats">';
	foreach (array('legacy', 'sheriff-check', 'don-check', 'nominees') as $section)
	{
		echo '<div class="section ' . $section . ' empty" id="' . $section . '"><div class="label"></div><div class="elements"></div></div>';
	}
	echo '<div class="connection-status">' . get_label('OFFLINE') . '</div>';
	echo '</div>';
}

function show_referee()
{
	echo '<div class="referee" id="referee">';
	echo '<div class="photo"><img id="referee-photo" src="' . OBS_NO_LOGO . '"></div>';
	echo '<span class="name" id="referee-name">' . get_label('Referee') . '</span>';
	echo '</div>';
}

function show_round()
{
	echo '<div class="round" id="round" style="display: none;">';
	echo '<div class="photo"><img id="round-logo" src="' . OBS_NO_LOGO . '"></div>';
	echo '<span class="name" id="round-name"></span>';
	echo '</div>';
}

?>
