<?php

require_once 'screens.php';

// The page to point a browser source at for what is shown between games. It holds nothing of
// its own: between.js asks the site how this table is set up, then fetches one screen at a
// time and puts it up for a while, then the next. Each screen is a page of its own that reads
// straight from the database and prints itself, so there is nothing here to keep in step - a
// screen is simply re-read every time its turn comes round, which is also what keeps it
// current as games are written down.
//
// The address says which table this is standing beside and nothing else:
//   tournament_id or event_id, together with table_num.
//
// Everything else - the language, how long a screen stays up, which of them are shown, what
// ground they stand on - is set on the site, under Management / Stream settings, and is read
// from tournament_streams by api/get/stream.php. between.js asks it again every few seconds,
// so a change made on the site reaches a source that is already running: nobody has to retype
// an address in OBS, or be at the machine the source is open on.

obs_screen_start();

// What travels with every request this page makes - the settings it asks for, and the screens
// it fetches. A screen is reached by its own address and knows nothing of this page, so the
// table has to be named again each time.
$params = array();
foreach (array('event_id', 'tournament_id', 'table_num', 'viewer') as $param)
{
	if (isset($_REQUEST[$param]))
	{
		$params[$param] = $_REQUEST[$param];
	}
}

$options = new stdClass();
$options->params = $params;
// Where the settings come from, and how often they are asked for again. It is about as long
// as a screen stays up, so a change on the site shows within a screen or two.
$options->settingsUrl = '../../api/get/stream.php?op=settings';
$options->settingsInterval = OBS_SETTINGS_DELAY;

echo '<!DOCTYPE HTML>';
echo '<html><head>';
echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
echo '<title>' . get_label('OBS Studio integration') . '</title>';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700">';
echo '<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/gagalin">';
echo '<link rel="stylesheet" href="' . obs_file('between.css') . '">';
echo '<script src="' . obs_file('between.js') . '"></script>';
// Changes the OBS scene from inside the source. Outside OBS it does nothing.
echo '<script src="' . obs_file('scenes.js') . '"></script>';
// The ground a table gets unless it says otherwise, so that the page is not one colour for the
// moment it takes the first answer to come back and then another.
echo '</head><body class="between bg-light">';
echo '<div class="screen" id="screen"></div>';
echo '<script>obs.start(' . json_encode($options) . ');</script>';
// The scene is asked for far more often than the settings are: a screen can wait ten
// seconds for a change to the language, but a scene has to follow the game.
echo '<script>obsScenes.start(' . json_encode($options->settingsUrl) . ', ' .
	json_encode($options->params) . ', ' . OBS_SCENE_DELAY . ');</script>';
echo '</body></html>';

?>
