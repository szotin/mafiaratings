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
	
	$json = file_get_contents('https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=' . $youtube_id . '&format=json');
	$info = json_decode($json, true);
	return $info;
}

function get_videos_title($video_type)
{
	switch ($video_type)
	{
		case VIDEO_TYPE_GAME:
			return get_label('Games');
		case VIDEO_TYPE_LEARNING:
			return get_label('Lectures');
		case VIDEO_TYPE_AWARD:
			return get_label('Award ceremonies');
		case VIDEO_TYPE_PARTY:
			return get_label('Parties');
	}
	return get_label('Videos');
}

function get_video_title($video_type)
{
	switch ($video_type)
	{
		case VIDEO_TYPE_GAME:
			return get_label('Game');
		case VIDEO_TYPE_LEARNING:
			return get_label('Lecture');
		case VIDEO_TYPE_AWARD:
			return get_label('Award ceremony');
		case VIDEO_TYPE_PARTY:
			return get_label('Party');
	}
	return get_label('Video');
}

function show_video_type_select($video_type, $select_id, $on_change)
{
	echo '<select id="' . $select_id . '"';
	if ($on_change != NULL)
	{
		echo ' onchange="' . $on_change . '"';
	}
	echo '>';
	show_option(-1, $video_type, get_label('All videos'));
	show_option(VIDEO_TYPE_GAME, $video_type, get_label('Games'));
	show_option(VIDEO_TYPE_LEARNING, $video_type, get_label('Lectures'));
	show_option(VIDEO_TYPE_AWARD, $video_type, get_label('Award ceremonies'));
	show_option(VIDEO_TYPE_PARTY, $video_type, get_label('Parties'));
	show_option(VIDEO_TYPE_CUSTOM, $video_type, get_label('Other'));
	echo '</select>';
}

?>