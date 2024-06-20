<?php

require_once __DIR__ . '/server.php';

$_mwt_site = 'https://mafiaworldtour.com';
if (!is_production_server())
{
	$_mwt_site = 'https://develop.mafiaworldtour.com';
}


?>