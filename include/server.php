<?php

require_once __DIR__ . '/branding.php';

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

function is_testing_server()
{
	$server = get_server_name();
	return $server == 'localhost' || $server == '127.0.0.1';
}

function is_demo_server()
{
	return stripos(get_server_name(), 'demo') !== false;
}

function is_production_server()
{
	return !is_testing_server() && !is_demo_server();
}

function is_ratings_server()
{
	return true;
}

function get_server_url()
{
	if (is_testing_server())
	{
		return 'http://127.0.0.1/projects/mafiaratings';
	}
	return PRODUCT_URL;
}

?>