<?php

require_once 'include/branding.php';

function get_server_url()
{
	if (!isset($_SESSION['root_url']))
	{
		$port = '';
		if (isset($_SERVER['HTTPS']))
		{
			$url = 'https://';
			if ($_SERVER['SERVER_PORT'] != "443")
			{
				$port = $_SERVER["SERVER_PORT"];
			}
		}
		else
		{
			$url = 'http://';
			if ($_SERVER['SERVER_PORT'] != "80")
			{
				$port = $_SERVER["SERVER_PORT"];
			}
		}
		
		if (isset($_SERVER['SERVER_NAME']))
		{
			$url .= $_SERVER['SERVER_NAME'];
		}
		else
		{
			$url .= PRODUCT_SITE;
		}
		$url .= $port;
		
		if (isset($_SERVER['REQUEST_URI']))
		{
			$uri = $_SERVER['REQUEST_URI'];
			$pos = strrpos($uri, '/');
			if ($pos !== false && $pos > 0)
			{
				$url .= substr($uri, 0, $pos);
			}
		}
		$_SESSION['root_url'] = $url;
	}
	return $_SESSION['root_url'];
}

function get_server_protocol()
{
    if (isset($_SERVER['HTTPS']))
    {
		return 'https';
    }
	return 'http';
}

function get_page_url()
{
	return $_SERVER['REQUEST_URI'];
}

// same as get_page_url() but without request params ?a=b&c=d etc
function get_page_name()
{
	return $_SERVER['SCRIPT_NAME'];
}

function is_testing_server()
{
	$server = $_SERVER['SERVER_NAME'];
	return $server == 'localhost' || $server == '127.0.0.1';
}

function is_demo_server()
{
	$server = $_SERVER['SERVER_NAME'];
	return stripos($server, 'demo') !== false;
}

function is_production_server()
{
	return !is_testing_server() && !is_demo_server();
}

function is_ratings_server()
{
	return true;
}

?>