<?php

define('TIMESPAN_WEEK', 604800);
define('TIMESPAN_DAY', 86400);
define('TIMESPAN_HOUR', 3600);
define('TIMESPAN_MINUTE', 60);

function timespan_to_string($timespan)
{
	$string = '';
	if ($timespan >= TIMESPAN_WEEK)
	{
		$weeks = floor($timespan / TIMESPAN_WEEK);
		$string .= $weeks . 'w';
		$timespan -= $weeks * TIMESPAN_WEEK;
	}
	
	if ($timespan >= TIMESPAN_DAY)
	{
		if (!empty($string))
		{
			$string .= ' ';
		}
		$days = floor($timespan / TIMESPAN_DAY);
		$string .= $days . 'd';
		$timespan -= $days * TIMESPAN_DAY;
	}
	
	if ($timespan >= TIMESPAN_HOUR)
	{
		if (!empty($string))
		{
			$string .= ' ';
		}
		$hours = floor($timespan / TIMESPAN_HOUR);
		$string .= $hours . 'h';
		$timespan -= $hours * TIMESPAN_HOUR;
	}

	if ($timespan >= TIMESPAN_MINUTE)
	{
		if (!empty($string))
		{
			$string .= ' ';
		}
		$minutes = floor($timespan / TIMESPAN_MINUTE);
		$string .= $minutes . 'm';
		$timespan -= $minutes * TIMESPAN_MINUTE;
	}
	
	if ($timespan > 0)
	{
		if (!empty($string))
		{
			$string .= ' ';
		}
		$string .= $timespan . 's';
	}
	return $string;
}

function string_to_timespan($string)
{
	$timespan = 0;
	$record_expected = true;
	$number = 0;
	$last_unit = 0;
	for ($pos = 0; $pos < strlen($string); ++$pos)
	{
		$char = $string[$pos];
		if ($record_expected)
		{
			if (is_numeric($char))
			{
				$number *= 10;
				$number += $char;
			}
			else 
			{
				$record_expected = false;
				if ($number <= 0)
				{
					return 0;
				}
				
				if ($char == 'w')
				{
					if ($last_unit >= 1)
					{
						return 0;
					}
					$last_unit = 1;
					$timespan += $number * TIMESPAN_WEEK;
				}
				else if ($char == 'd')
				{
					if ($last_unit >= 2)
					{
						return 0;
					}
					$last_unit = 2;
					$timespan += $number * TIMESPAN_DAY;
				}
				else if ($char == 'h')
				{
					if ($last_unit >= 3)
					{
						return 0;
					}
					$last_unit = 3;
					$timespan += $number * TIMESPAN_HOUR;
				}
				else if ($char == 'm')
				{
					if ($last_unit >= 4)
					{
						return 0;
					}
					$last_unit = 4;
					$timespan += $number * TIMESPAN_MINUTE;
				}
				else if ($char == 's')
				{
					if ($last_unit >= 5)
					{
						return 0;
					}
					$last_unit = 5;
					$timespan += $number;
				}
				else
				{
					return 0;
				}
				$number = 0;
			}
		}
		else if ($char == ' ')
		{
			$record_expected = true;
			$number = 0;
		}
		else
		{
			return 0;
		}
	}
	if ($record_expected)
	{
		return 0;
	}
	return $timespan;
}

?>