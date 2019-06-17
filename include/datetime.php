<?php

function timestamp_to_string($timestamp, $timezone, $include_time = true)
{
	date_default_timezone_set($timezone);
	if ($include_time)
	{
		return date('Y-m-d H:i', $timestamp);
	}
	return date('Y-m-d', $timestamp);
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
		return $datetime->format('Y-m-d H:i');
	}
	return $datetime->format('Y-m-d');
}

?>