<?php

require_once __DIR__ . '/branding.php';

$_testing_server = -1;
$_demo_server = -1;

function is_web()
{
	return array_key_exists('HTTP_HOST', $_SERVER);
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
	if (array_key_exists('REQUEST_URI', $_SERVER))
	{
		return $_SERVER['REQUEST_URI'];
	}
	return '';
}

// same as get_page_url() but without request params ?a=b&c=d etc
function get_page_name()
{
	if (array_key_exists('SCRIPT_NAME', $_SERVER))
	{
		return $_SERVER['SCRIPT_NAME'];
	}
	return '';
}

function get_server_name()
{
	if (array_key_exists('SERVER_NAME', $_SERVER))
	{
		return $_SERVER['SERVER_NAME'];
	}
	return '';
}

function get_client_ip() 
{
	// Check for shared internet/proxy IP
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) 
	{
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} 
	// Check for IPs passing through proxies
	else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) 
	{
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} 
	// Default remote address
	else 
	{
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	return $ip;
}

function is_testing_server()
{
	global $_testing_server;
	
	if ($_testing_server < 0)
	{
		if (PHP_SAPI === 'cli' && isset($_SERVER['argv']))
		{
			$_testing_server = false;
			foreach ($_SERVER['argv'] as $arg)
			{
				if ($arg == '-test')
				{
					$_testing_server = true;
					break;
				}
			}
		}
		else
		{
			$server = get_server_name();
			$_testing_server = ($server == 'localhost' || $server == '127.0.0.1');
		}
	}
	return $_testing_server;
}

function is_demo_server()
{
	global $_demo_server;
	
	if ($_demo_server < 0)
	{
		$_demo_server = (PHP_SAPI !== 'cli' && stripos(get_server_name(), 'demo') !== false);
	}
	return $_demo_server;
}

function is_production_server()
{
	return !is_testing_server() && !is_demo_server();
}

function is_ratings_server()
{
	return true;
}

function get_server_url($https = true)
{
	if (is_testing_server())
	{
		return 'http://127.0.0.1/projects/mafiaratings';
	}
	if ($https)
	{
		return PRODUCT_URL;
	}
	return PRODUCT_URL_HTTP;
}

?>