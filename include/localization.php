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
		if ($label == '[EMPTY]')
		{
			$label = $labelitem;
		}
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

// $_date_translations = array();

// function translate_date($date, $lang = LANG_NO)
// {
	// global $_date_translations, $_default_date_translations, $_lang;
	
	// $translations = $_default_date_translations;
	// if (is_valid_lang($lang) && $lang != $_lang)
	// {
		// $lang_code = get_lang_code($lang);
		// if (!isset($_date_translations[$lang_code]))
		// {
			// $_date_translations[$lang_code] = include(__DIR__ . '/languages/' . $lang_code . '/date.php');
		// }
		// $translations = $_date_translations[$lang_code];
	// }

	// foreach ($translations as $eng => $localized)
	// {
		// $date = str_replace($eng, $localized, $date);
	// }
	// return $date;
// }

// // same format as DateTime.format
// function format_date($format, $timestamp, $timezone, $lang = LANG_NO)
// {
	// date_default_timezone_set($timezone);
	// return translate_date(date($format, $timestamp), $lang);
// }

function format_time($timestamp)
{
	$hours = floor($timestamp / 3600);
	$timestamp -= $hours * 3600;
	$minutes = floor($timestamp / 60);
	return sprintf('%02d:%02d', $hours, $minutes);
}

function get_month_name($month, $lang, $nominative)
{
	if ($lang == LANG_RUSSIAN)
	{
		if ($nominative)
		{
			switch ($month)
			{
			case 1:
				return 'Январь';
			case 2:
				return 'Февраль';
			case 3:
				return 'Март';
			case 4:
				return 'Апрель';
			case 5:
				return 'Май';
			case 6:
				return 'Июнь';
			case 7:
				return 'Июль';
			case 8:
				return 'Август';
			case 9:
				return 'Сентябрь';
			case 10:
				return 'Октябрь';
			case 11:
				return 'Ноябрь';
			case 12:
				return 'Декабрь';
			}
		}
		else switch ($month)
		{
		case 1:
			return 'января';
		case 2:
			return 'февраля';
		case 3:
			return 'марта';
		case 4:
			return 'апреля';
		case 5:
			return 'мая';
		case 6:
			return 'июня';
		case 7:
			return 'июля';
		case 8:
			return 'августа';
		case 9:
			return 'сентября';
		case 10:
			return 'октября';
		case 11:
			return 'ноября';
		case 12:
			return 'декабря';
		}
	}
	else if ($lang == LANG_UKRAINIAN)
	{
		if ($nominative)
		{
			switch ($month)
			{
			case 1:
				return 'Січень';
			case 2:
				return 'Лютий';
			case 3:
				return 'Березень';
			case 4:
				return 'Квітень';
			case 5:
				return 'Травень';
			case 6:
				return 'Червень';
			case 7:
				return 'Липень';
			case 8:
				return 'Серпень';
			case 9:
				return 'Вересень';
			case 10:
				return 'Жовтень';
			case 11:
				return 'Листопад';
			case 12:
				return 'Грудень';
			}
		}
		else switch ($month)
		{
		case 1:
			return 'січня';
		case 2:
			return 'лютого';
		case 3:
			return 'березня';
		case 4:
			return 'квітня';
		case 5:
			return 'травня';
		case 6:
			return 'червня';
		case 7:
			return 'липня';
		case 8:
			return 'серпня';
		case 9:
			return 'вересня';
		case 10:
			return 'жовтня';
		case 11:
			return 'листопада';
		case 12:
			return 'грудня';
		}
	}
	else switch ($month)
	{
	case 1:
		return 'January';
	case 2:
		return 'February';
	case 3:
		return 'March';
	case 4:
		return 'April';
	case 5:
		return 'May';
	case 6:
		return 'June';
	case 7:
		return 'July';
	case 8:
		return 'August';
	case 9:
		return 'September';
	case 10:
		return 'October';
	case 11:
		return 'November';
	case 12:
		return 'December';
	}
	return '';
}

function format_month($timestamp, $timezone, $lang = LANG_NO)
{
	global $_lang;
	if (!is_valid_lang($lang))
	{
		$lang = $_lang;
	}
	
	date_default_timezone_set($timezone);
	return get_month_name(date('n', $timestamp), $lang, true) . ' ' . date('Y', $timestamp);
}

function format_date($timestamp, $timezone, $with_time = false, $lang = LANG_NO)
{
	global $_lang;
	if (!is_bool($with_time))
	{
		$with_time = false;
		$lang = (int)$with_time;
	}
	
	if (!is_valid_lang($lang))
	{
		$lang = $_lang;
	}
	
	date_default_timezone_set($timezone);
	if ($lang == LANG_RUSSIAN || $lang == LANG_UKRAINIAN)
	{		
		$result = date('j', $timestamp) . ' ' . get_month_name(date('n', $timestamp), $lang, false) . ' ' . date('Y', $timestamp);
		if ($with_time)
		{
			$result .= date(', H:i', $timestamp);
		}
	}
	else if ($with_time)
	{
		$result = date('F j, Y, g:i a', $timestamp);
	}
	else
	{
		$result = date('F j, Y', $timestamp);
	}
	return $result;
}

function format_date_period($start, $duration, $timezone, $with_time = false, $lang = LANG_NO)
{
	global $_lang;
	if (!is_bool($with_time))
	{
		$with_time = false;
		$lang = (int)$with_time;
	}
	
	if (!is_valid_lang($lang))
	{
		$lang = $_lang;
	}
	
	$end = $start + $duration - 1;
	date_default_timezone_set($timezone);
	$start_year = date('Y', $start);
	$start_month = date('n', $start);
	$start_day = date('j', $start);
	$end_year = date('Y', $end);
	$end_month = date('n', $end);
	$end_day = date('j', $end);
	
	if ($lang == LANG_RUSSIAN || $lang == LANG_UKRAINIAN)
	{		
		if ($start_year != $end_year)
		{
			$result = 
				$start_day . ' ' . get_month_name($start_month, $lang, false) . ' ' . $start_year . ' - ' .
				$end_day . ' ' . get_month_name($end_month, $lang, false) . ' ' . $end_year;
		}
		else if ($start_month != $end_month)
		{
			$result = 
				$start_day . ' ' . get_month_name($start_month, $lang, false) . ' - ' .
				$end_day . ' ' . get_month_name($end_month, $lang, false) . ' ' . $start_year;
		}
		else if ($start_day != $end_day)
		{
			$result = $start_day . '-' . $end_day . ' ' . get_month_name($start_month, $lang, false) . ' ' . $start_year;
		}
		else
		{
			$result = $start_day . ' ' . get_month_name($start_month, $lang, false) . ' ' . $start_year;
			if ($with_time)
			{
				$result .= date(', H:i', $start);
			}
		}
	}
	else if ($start_year != $end_year)
	{
		$result = date('F j, Y', $start) . ' - ' . date('F j, Y', $end);
	}
	else if ($start_month != $end_month)
	{
		$result = date('F j', $start) . ' - ' . date('F j, Y', $end);
	}
	else if ($start_day != $end_day)
	{
		$result = date('F j', $start) . '-' . date('j, Y', $end);
	}
	else if ($with_time)
	{
		$result = date('F j, Y, g:i a', $start);
	}
	else
	{
		$result = date('F j, Y', $start);
	}
	return $result;
}

?>