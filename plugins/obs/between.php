<?php

require_once 'screens.php';

// The page to point a browser source at for what is shown between games. It holds nothing of its
// own: between.js fetches one screen at a time and puts it up for a while, then the next. Each
// screen is a page of its own that reads straight from the database, so there is no api here and
// nothing to keep in step - a screen is simply re-read every time its turn comes round, which is
// also what keeps it current as games are written down.
//
// The pages take the same request params as the overlays:
//   tournament_id or event_id, together with table_num - which table this is standing beside;
//   locale      - the language of the screens;
//   delay       - seconds each screen stays up, 10 by default.

obs_screen_start();

$delay = OBS_SCREEN_DELAY;
if (isset($_REQUEST['delay']))
{
	$delay = max((float)$_REQUEST['delay'], 1);
}

// What is passed on to every screen. table_num travels with them because a screen is fetched by
// its own address and knows nothing of this page.
$params = array();
foreach (array('event_id', 'tournament_id', 'table_num', 'locale') as $param)
{
	if (isset($_REQUEST[$param]))
	{
		$params[$param] = $_REQUEST[$param];
	}
}

$options = new stdClass();
$options->params = $params;
$options->delay = $delay * 1000;
// In the order they are shown. A screen that has nothing to say answers with nothing and is
// left out of the round - which is where all the rules about when a screen appears are kept.
$options->screens = array('screen_results.php', 'screen_upcoming.php', 'screen_standings.php');
// Put up when every one of them had nothing, so that the source is never a blank rectangle.
$options->fallback = 'screen_title.php';

echo '<!DOCTYPE HTML>';
echo '<html><head>';
echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
echo '<title>' . get_label('OBS Studio integration') . '</title>';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700">';
echo '<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/gagalin">';
echo '<link rel="stylesheet" href="' . obs_file('between.css') . '">';
echo '<script src="' . obs_file('between.js') . '"></script>';
echo '</head><body class="between">';
echo '<div class="screen" id="screen"></div>';
echo '<script>obs.start(' . json_encode($options) . ');</script>';
echo '</body></html>';

?>
