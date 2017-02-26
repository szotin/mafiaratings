<?php

$URL_DETECTOR_PROTOCOLS = array('http://', 'https://', 'ftp://', 'mailto:');
$URL_DETECTOR_DOMAINS = array(
	'com',  'org', 'net',  'gov', 'edu',  'aero',   'asia', 'biz', 'cat', 'coop',
	'info', 'int', 'jobs', 'mil', 'mobi', 'museum', 'name', 'pro', 'tel', 'travel');
$URL_DETECTOR_DOMAINS2 = array (
	'a' => 'cdefgilmnoqrstuwxz', 
	'b' => 'abdefghijmnorstvwyz', 
	'c' => 'acdfghiklmnoruvxyz', 
	'd' => 'ejkmoz', 
	'e' => 'cegrstu', 
	'f' => 'ijkmor', 
	'g' => 'abdefghilmnpqrstuwy', 
	'h' => 'kmnrtu', 
	'i' => 'delmnoqrst', 
	'j' => 'emop', 
	'k' => 'eghimnprwyz', 
	'l' => 'abcikrstuvy', 
	'm' => 'acdeghklmnopqrstuvwxyz', 
	'n' => 'acefgilopruz', 
	'o' => 'm', 
	'p' => 'aefghklmnrstwy', 
	'q' => 'a', 
	'r' => 'eosuw', 
	's' => 'abcdeghijklmnortuvyz', 
	't' => 'cdfghjklmnoprtvwz', 
	'u' => 'agksyz', 
	'v' => 'aceginu', 
	'w' => 'fs', 
	'y' => 'et', 
	'z' => 'amw');

define('IS_URL_NO', 0);
define('IS_URL_YES', 1);
define('IS_URL_NO_PROTOCOL', 2);
define('IS_URL_IMG', 4);
define('IS_URL_VIDEO', 8);

function get_url_flags($url)
{
	$flags = IS_URL_YES;
	$len = strlen($url);
	if ($len < 5)
	{
		return $flags;
	}
	
	$url = strtolower($url);
	
	switch(substr($url, $len - 4, 4))
	{
		case '.jpg':
		case '.bmp':
		case '.png':
		case '.gif':
		case '.jpeg':
			$flags |= IS_URL_IMG;
			break;
		default:
			if (substr($url, $len - 5, 5) == '.jpeg')
			{
				$flags |= IS_URL_IMG;
			}
			break;
	}
	
	if (strpos($url, 'youtube.com/watch') !== false)
	{
		$flags |= IS_URL_VIDEO;
	}
	return $flags;
}

function is_url($str)
{
	global $URL_DETECTOR_PROTOCOLS, $URL_DETECTOR_DOMAINS, $URL_DETECTOR_DOMAINS2;
	
	$str = strtolower($str);
	foreach ($URL_DETECTOR_PROTOCOLS as $protocol)
	{
		if (strpos($str, $protocol) === 0)
		{
			return get_url_flags($str);
		}
	}
	
	$array = str_word_count($str, 2);
	$domain = '';
	foreach ($array as $pos => $word)
	{
		if ($pos == 0)
		{
			continue;
		}
		
		if (substr($str, $pos - 1, 1) != '.')
		{
			break;
		}
		$domain = $word;
	}
	
	$length = strlen($domain);
	if ($length == 2)
	{
		$first_letter = substr($domain, 0, 1);
		if (isset($URL_DETECTOR_DOMAINS2[$first_letter]))
		{
			$second_letter = substr($domain, 1, 1);
			if (strpos($URL_DETECTOR_DOMAINS2[$first_letter], $second_letter) !== false)
			{
				return get_url_flags($str) | IS_URL_NO_PROTOCOL;
			}
		}
	}
	else if ($length > 2 && $length <= 6)
	{
		foreach ($URL_DETECTOR_DOMAINS as $d)
		{
			if (strcmp($domain, $d) == 0)
			{
				return get_url_flags($str) | IS_URL_NO_PROTOCOL;
			}
		}
	}
	return IS_URL_NO;
}

function is_email($str)
{
	global $URL_DETECTOR_DOMAINS, $URL_DETECTOR_DOMAINS2;
	
	$str = strtolower($str);
	if (strpos($str, '@') === false)
	{
		return false;
	}
	
	$dot_index = strrpos($str, '.');
	if ($dot_index === false)
	{
		return false;
	}
	
	$domain = substr($str, $dot_index + 1);
	$length = strlen($domain);
	if ($length == 2)
	{
		$first_letter = substr($domain, 0, 1);
		if (isset($URL_DETECTOR_DOMAINS2[$first_letter]))
		{
			$second_letter = substr($domain, 1, 1);
			if (strpos($URL_DETECTOR_DOMAINS2[$first_letter], $second_letter) !== false)
			{
				return true;
			}
		}
	}
	else if ($length > 2 && $length <= 6)
	{
		foreach ($URL_DETECTOR_DOMAINS as $d)
		{
			if (strcmp($domain, $d) == 0)
			{
				return true;
			}
		}
	}
	return false;
}

function check_url($url)
{
	$url = trim($url);
	if ($url == '')
	{
		return $url;
	}
	$l_url = strtolower($url);
	$i = strpos($url, 'http');
	if ($i === false || $i > 0)
	{
		return 'http://' . $url;
	}
	return $url;
}

?>