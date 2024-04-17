<?php

//require_once '../include/json.php';

define('CURRENT_ROUND_FILENAME', 'current_round.json');

if (isset($_REQUEST['t']) && isset($_REQUEST['r']))
{
	$file = fopen(CURRENT_ROUND_FILENAME, "r");
	$current_rounds_str = fread($file, filesize(CURRENT_ROUND_FILENAME));
	fclose($file);

	$current_rounds = json_decode($current_rounds_str);

	$t = min(max((int)$_REQUEST['t'],0),count($current_rounds)-1);
	$current_rounds[$t] = (int)$_REQUEST['r'];

	$file = fopen(CURRENT_ROUND_FILENAME, "w");
	fwrite($file, json_encode($current_rounds));
	fclose($file);
}

?>