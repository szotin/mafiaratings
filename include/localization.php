<?php

require_once __DIR__ . '/languages.php';
require_once __DIR__ . '/db.php';

// Example:
// get_label('Hi [1]! You scored [0] points! [2] [aaa]', 40, 'Vasya');
// returns: 'Hi Vasya! You scored 40 points! [2] [aaa]'
function get_label($labelitem)
{
	global $labelMenu; #from the included labels file
	
	$label = $labelitem;
	if (isset($labelMenu[$labelitem]))
	{
		$label = $labelMenu[$labelitem];
	}
	
	$num_args = func_num_args() - 1;
	if ($num_args <= 0)
	{
		return $label;
	}
	
	$parsed_label = '';
	$end = 0;
	while (($beg = strpos($label, '[', $end)) !== false)
	{
		$parsed_label .= substr($label, $end, $beg - $end);
		++$beg;
		
		$end = strpos($label, ']', $beg);
		if ($end === false)
		{
			$parsed_label .= substr($label, $beg - 1);
			return $parsed_label;
		}
		
		$index = substr($label, $beg, $end - $beg);
		if (is_numeric($index) && $index < $num_args && $index >= 0)
		{
			$parsed_label .= func_get_arg($index + 1);
			++$end;
		}
		else
		{
			$parsed_label .= '[';
			$end = $beg;
		}
	}
	$parsed_label .= substr($label, $end);
	
	return $parsed_label;
}

$_date_translations = array();

// same format as DateTime.format
function format_date($format, $timestamp, $timezone, $lang = LANG_NO)
{
	global $_date_translations, $_default_date_translations, $_lang_code;
	
	$translations = $_default_date_translations;
	if (!is_valid_lang($lang))
	{
		$lang_code = $_lang_code;
	}
	else
	{
		$lang_code = get_lang_code($lang);
	}
	
	if ($lang_code != $_lang_code)
	{
		if (!isset($_date_translations[$lang_code]))
		{
			$_date_translations[$lang_code] = include(__DIR__ . '/languages/' . $lang_code . '/date.php');
		}
		$translations = $_date_translations[$lang_code];
	}

	date_default_timezone_set($timezone);
	$row = date($format, $timestamp);
	foreach ($translations as $eng => $localized)
	{
		$row = str_replace($eng, $localized, $row);
	}
	return $row;
}

function format_time($timestamp)
{
	$hours = floor($timestamp / 3600);
	$timestamp -= $hours * 3600;
	$minutes = floor($timestamp / 60);
	return sprintf('%02d:%02d', $hours, $minutes);
}

?>