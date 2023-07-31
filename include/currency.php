<?php

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/location.php';

define('DEFAULT_CURRENCY', 1);

function format_currency($amount, $pattern, $zero_as_free = true)
{
	if (is_null($pattern))
	{
		return '';
	}
	
	if ($zero_as_free && $amount == 0)
	{
		return get_label('free');
	}
	
	$pos = 0;
	while ($pos < strlen($pattern))
	{
		$pos = strpos($pattern, '#');
		if ($pos === false)
		{
			break;
		}
		
		if ($pos + 1 < strlen($pattern) && $pattern[$pos + 1] == '#')
		{
			$pos = $pos + 2;
		}
		else
		{
			return substr($pattern, 0, $pos) . number_format($amount) . substr($pattern, $pos + 1);
		}
	}
	return number_format($amount);
}

?>