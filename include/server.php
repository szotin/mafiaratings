<?php

function get_server_url()
{
	$row = 'www.mafiaratings.com';
	if (isset($_SERVER['SERVER_NAME']))
	{
		$row = $_SERVER['SERVER_NAME'];
		if ($_SERVER['SERVER_PORT'] != "80")
		{
			$row .= ':' . $_SERVER["SERVER_PORT"];
		}
	}
	return $row;
}

function get_server_protocol()
{
    $row = 'http';
    if ($_SERVER['HTTPS'] == 'on')
    {
        $row .= "s";
    }
	return $row;
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