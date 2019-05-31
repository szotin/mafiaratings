<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';
require_once 'include/video.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];
	
	list($video, $title, $vtime) = Db::record(get_label('game'), 'SELECT v.video, v.name, v.vtime FROM games g JOIN videos v ON v.id = g.video_id WHERE g.id = ?', $id);
		
	dialog_title(get_label('Game [0] video', $id));
		
	$url = get_video_url($video, $vtime);
	echo '<p><a href="' . $url . '" target="_blank">' . $title . '</a></p>';
	echo '<p><iframe title="YouTube video player" width="780" height="460" src="' . get_embed_video_url($video, $vtime) . '" frameborder="0" allowfullscreen></iframe></p>';
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>