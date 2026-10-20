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
//   delay       - seconds each screen stays up, 10 by default;
//   screens     - which of them to show, by name, separated by commas: results, upcoming,
//                 standings, stats. They are shown in the order they are named, so the param
//                 settles the running order as well as what is in it. Left out, all four are
//                 shown. A name nobody knows is ignored, and a param that names nothing at all
//                 is taken as not being there - a mistyped source still shows something;
//   background  - dark, the wall these screens have always stood on and what you get by
//                 leaving it out, or light, the same screens on a pale ground. The cards keep
//                 their own photographs either way;
//   transparent - 1 paints no ground at all and lets the scene behind the browser source
//                 through, leaving only the cards and the panes. It goes with either
//                 background: the one still says which set of colours the screens are drawn
//                 in, dark lettering or light. background=transparent is the old spelling of
//                 transparent=1 and still answers.

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
$all_screens = array(
	'results' => 'screen_results.php',
	'upcoming' => 'screen_upcoming.php',
	'standings' => 'screen_standings.php',
	'stats' => 'screen_stats.php');

$options->screens = array_values($all_screens);
if (isset($_REQUEST['screens']))
{
	// The name may be written the short way or the way the file is called - screen_stats.php
	// reads as plainly as stats to anybody looking at the address, so both are taken.
	$wanted = array();
	foreach (preg_split('/[\s,;]+/', strtolower($_REQUEST['screens'])) as $name)
	{
		$name = preg_replace('/^screen_|\.php$/', '', $name);
		if (isset($all_screens[$name]) && !in_array($all_screens[$name], $wanted))
		{
			$wanted[] = $all_screens[$name];
		}
	}
	if (!empty($wanted))
	{
		$options->screens = $wanted;
	}
}
// Put up when every one of them had nothing, so that the source is never a blank rectangle.
$options->fallback = 'screen_title.php';

// The ground the screens stand on: which set of colours they are drawn in, and whether
// anything is painted behind them at all. Two separate questions, because a see-through source
// still has to be either dark or light - it is the lettering and the panes that have to suit
// whatever the scene behind them is. Named rather than described, so that the stylesheet keeps
// the say over what each one looks like; anything else asked for is taken as the dark one.
$background = 'dark';
$transparent = false;
if (isset($_REQUEST['background']))
{
	$asked = strtolower(trim($_REQUEST['background']));
	if ($asked == 'light' || $asked == 'dark')
	{
		$background = $asked;
	}
	else if ($asked == 'transparent')
	{
		$transparent = true; // what the two of them went by while they were one param
	}
}
if (isset($_REQUEST['transparent']))
{
	$asked = strtolower(trim($_REQUEST['transparent']));
	$transparent = !in_array($asked, array('', '0', 'no', 'false'));
}

echo '<!DOCTYPE HTML>';
echo '<html><head>';
echo '<meta content="text/html; charset=utf-8" http-equiv="Content-Type">';
echo '<title>' . get_label('OBS Studio integration') . '</title>';
echo '<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700">';
echo '<link rel="stylesheet" href="https://fonts.cdnfonts.com/css/gagalin">';
echo '<link rel="stylesheet" href="' . obs_file('between.css') . '">';
echo '<script src="' . obs_file('between.js') . '"></script>';
echo '</head><body class="between bg-' . $background . ($transparent ? ' bg-clear' : '') . '">';
echo '<div class="screen" id="screen"></div>';
echo '<script>obs.start(' . json_encode($options) . ');</script>';
echo '</body></html>';

?>
