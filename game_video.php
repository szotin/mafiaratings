<?php

require_once 'include/session.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/timezone.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$id = $_REQUEST['game'];
	
	list($url) = Db::record(get_label('game'), 'SELECT video FROM games WHERE id = ?', $id);
		
	dialog_title(get_label('Game [0] video', $id));
		
	echo '<p><a href="' . $url . '" target="_blank">' . $url . '</a></p>';
	$pos = strpos($url, 'v=');
	if ($pos !== false)
	{
		$end = strpos($url, '&', $pos + 2);
		if ($end === false)
		{
			$code = substr($url, $pos + 2);
		}
		else
		{
			$code = substr($url, $pos + 2, $end - $pos - 2);
		}
		echo '<p><iframe title="YouTube video player" width="780" height="460" src="http://www.youtube.com/embed/' . $code . '" frameborder="0" allowfullscreen></iframe></p>';
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>