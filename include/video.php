<?php

function get_youtube_id($url)
{
	$pos = strpos($url, 'v=');
	if ($pos !== false)
	{
		$end = strpos($url, '&', $pos + 2);
		if ($end === false)
		{
			$url = substr($url, $pos + 2);
		}
		else
		{
			$url = substr($url, $pos + 2, $end - $pos - 2);
		}
	}
	return $url;
}

function get_youtube_info($youtube_id)
{
	if (empty($youtube_id))
	{
		throw new Exc(get_label('Please enter a valid youtube link or youtube video id.', $youtube_id));
	}
	
	$json = file_get_contents('http://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=' . $youtube_id . '&format=json');
	if (empty($json))
	{
		throw new Exc(get_label('[0] is not a valid youtube video id.', $youtube_id));
	}
	$info = json_decode($json, true);
	return $info;
}


?>