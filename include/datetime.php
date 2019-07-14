<?php

define('JS_DATETIME_FORMAT', 'yy-mm-dd');
define('DEF_DATETIME_FORMAT', 'Y-m-d H:i');
define('DEF_DATETIME_FORMAT_NO_TIME', 'Y-m-d');

function timestamp_to_string($timestamp, $timezone, $include_time = true)
{
	date_default_timezone_set($timezone);
	if ($include_time)
	{
		return date(DEF_DATETIME_FORMAT, $timestamp);
	}
	return date(DEF_DATETIME_FORMAT_NO_TIME, $timestamp);
}

function get_datetime($str, $timezone)
{
	if (is_string($timezone))
	{
		$timezone = new DateTimeZone($timezone);
	}
	if (is_numeric($str))
	{
		date_default_timezone_set($timezone->getName());
		$str = timestamp_to_string((int)$str, $timezone->getName());
	}
	return new DateTime($str, $timezone);
}

function datetime_to_string($datetime, $include_time = true)
{
	date_default_timezone_set($datetime->getTimezone()->getName());
	if ($include_time)
	{
		return $datetime->format(DEF_DATETIME_FORMAT);
	}
	return $datetime->format(DEF_DATETIME_FORMAT_NO_TIME);
}

?>