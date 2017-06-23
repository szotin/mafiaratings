<?php

function service_host()
{
	$server = $_SERVER['SERVER_NAME'];
	if ($server == 'localhost' || $server == '127.0.0.1')
	{
		return 'http://' . $server . ':' . $_SERVER['SERVER_PORT'];
	}
	return 'https://mafiaratings.com';
}

function get_json($page)
{
	$content = file_get_contents(service_host() . '/' . $page);
//	echo $content;
	while (ord($content) == 239)
	{
		$content = substr($content, 3);
	}
	$result = json_decode($content);
	if (isset($result->error))
	{
		throw new Exception($result->error);
	}
	return $result;
}

?>