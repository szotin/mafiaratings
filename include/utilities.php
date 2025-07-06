<?php

function rand_string($length)
{
	$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";	

	$size = strlen($chars);
    $str = '';
	for ($i = 0; $i < $length; $i++)
    {
		$str .= $chars[mt_rand(0, $size - 1)];
	}
	return $str;
}

function format_float($number, $digits, $zeroes = true)
{
	if ($number == 0)
	{
		if ($zeroes)
		{
			return 0;
		}
		return '';
	}
	
	$int_number = (int)($number * pow(10, $digits + 1));
	if ($int_number % 10 >= 5)
	{
		$int_number /= 10;
		$int_number += 1;
	}
	else
	{
		$int_number /= 10;
	}
	
	$result = number_format($number, $digits);
	$pos = -1;
	for ($i = strlen($result) - 1; $i >= 0 && $result[$i] == '0'; --$i)
	{
		$pos = $i;
	}
	if ($pos >= 0)
	{
		if ($result[$i] == '.')
		{
			--$pos;
		}
		$result = substr($result, 0, $pos);
	}
	return $result;
}

if (!function_exists('array_is_list')) 
{
	function array_is_list(array &$arr)
	{
		return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
	}
}

?>