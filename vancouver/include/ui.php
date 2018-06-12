<?php

function menu_item($current_url, $url, $item, $tip)
{
	if (strpos($url, $current_url) === 0)
	{
		echo '<li>' . $item . '</li>';
	}
	else
	{
		echo '<li><a href="' . $url . '" title="' . $tip . '">' . $item . '</a></li>';
	}
}

function page_name()
{
	$page_name = strtolower($_SERVER['SCRIPT_NAME']);
	$page_name = substr($page_name, strrpos($page_name, '/') + 1);
	return $page_name;
}

function service_host()
{
	$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$pos = strpos($url, '/vancouver');
	if ($pos === false)
	{
		return 'https://mafiaratings.com';
	}
	return substr($url, 0, $pos);
}

function show_header($title, $show_ratings = true)
{
	echo '<html>';
	echo '<head>';
	echo '<title>Vancouver Mafia</title>';
	echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
	echo '<link rel="stylesheet" href="main.css" type="text/css" media="screen" />';
	echo '</head>';

	echo '<body><center>';
//	echo '<img src="images/vancouver.jpg" width="500">';
	echo '<table class="transp" cellpadding="5" cellspacing="0"  width="1020">';

	$url = strtolower($_SERVER['SCRIPT_NAME']);
	$url = substr($url, strrpos($url, '/') + 1);

	if ($show_ratings)
	{
		echo '<tr><td colspan="2" class="back">';
	}
	else
	{
		echo '<tr><td class="back" valign="top">';
	}
	echo '<ul id="tabs" width="100%">';
	menu_item($url, 'index.php', 'Adverts', 'What\'s going on');
	menu_item($url, 'ratings.php', 'Ratings', 'Ratings and statistics');
	menu_item($url, 'photo_albums.php', 'Photos', 'Photo albums');
	menu_item($url, 'events.php', 'Events', 'Past events');
	echo '</ul>';
	echo '</td></tr>';
	
	echo '<tr><td class="back" valign="top">';
}

function show_footer($show_ratings = true)
{
	if ($show_ratings)
	{
		echo '</td><td width="200" class="back" valign="top">';
		echo '<iframe src="' . service_host() . '/iratings.php?club=1&psize=10&h=0&cols=nhur" seamless="seamless" scrolling="none" frameBorder="0"></iframe>';
	}
	echo '</td></tr></table></center>';
	echo '</body>';
}

function get_json($page)
{
	$content = file_get_contents(service_host() . '/' . $page);
	while (ord($content) == 239)
	{
		$content = substr($content, 3);
	}
	return json_decode($content);
}

?>