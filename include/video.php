<?php

function get_youtube_id($url, &$id, &$time)
{
	$parsed_url = parse_url($url);
	if (isset($parsed_url['query']))
	{
		parse_str($parsed_url['query'], $params);
	}
	else
	{
		$params = array();
	}
	
	if (isset($params['v']))
	{
		$id = $params['v'];
	}
	else if (isset($parsed_url['path']))
	{
		$id = basename($parsed_url['path']);
	}
	else
	{
		throw new Exc(get_label('Please enter a valid youtube link or youtube video id.'));
	}
	
	if (isset($params['t']))
	{
		$time = $params['t'];
	}
	else
	{
		$time = NULL;
	}
}

function get_video_url($id, $vtime)
{
	$url = 'https://www.youtube.com/watch?v=' . $id;
	if (!is_null($vtime))
	{
		$url .= '&t=' . $vtime;
	}
	return $url;
}

function get_embed_video_url($id, $vtime)
{
	$url = 'https://www.youtube.com/embed/' . $id;
	if (!is_null($vtime))
	{
		$url .= '?start=' . $vtime;
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