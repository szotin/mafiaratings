<?php

function get_datetime($str, $timezone)
{
	if (is_string($timezone))
	{
		$timezone = new DateTimeZone($timezone);
	}
	if (is_numeric($str))
	{
		date_default_timezone_set($timezone->getName());
		$str = date('Y-m-d H:i', (int)$str);
	}
	return new DateTime($str, $timezone);
}

?>