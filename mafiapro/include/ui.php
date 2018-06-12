<?php

function service_host()
{
	$url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$pos = strpos($url, '/mafiapro');
	if ($pos === false)
	{
		return 'https://mafiaratings.com';
	}
	return substr($url, 0, $pos);
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