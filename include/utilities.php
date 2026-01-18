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

function format_coeff($coeff, $sign_digits = 3)
{
	return round($coeff, $sign_digits - floor(log10($coeff)) - 1);
}

function format_score($score, $zeroes = true)
{
	return format_float($score, 3, $zeroes);
}

function format_rating($rating)
{
	$fraction = 100;
	$rat = abs($rating);
	$digits = 0;
	if ($rat > 0.0001)
	{
		while ($rat < $fraction)
		{
			$fraction /= 10;
			++$digits;
		}
	}
	return number_format($rating, $digits);
}

if (!function_exists('array_is_list')) 
{
	function array_is_list(array &$arr)
	{
		return $arr === [] || array_keys($arr) === range(0, count($arr) - 1);
	}
}

class VarianceUpdater 
{
    public $n;
    public $mean;
    public $variance;
	
	public function sigma()
	{
		return sqrt($this->variance);
	}

	public function __construct($n = 0, $mean = 0, $variance = 0) 
	{
		if (is_object($n))
		{
			$this->n = (int)$n->n;
			$this->mean = (float)$n->mean;
			$this->variance = (float)$n->variance;
		}
		else
		{
			$this->n = (int)$n;
			$this->mean = (float)$mean;
			$this->variance = (float)$variance;
		}
	}

    public function addNumber($x) 
	{
		$oldMean = $this->mean;
		$this->n++;

		$this->mean = $oldMean + ($x - $oldMean) / $this->n;
        if ($this->n > 1) 
		{
            $this->variance = (($this->n - 2) * $this->variance + ($x - $oldMean) * ($x - $this->mean)) / ($this->n - 1);
        }
		else
		{
			$this->variance = 0;
		}
    }
	
	public function addSet($n, $mean = 0, $variance = 0)
	{
		if (is_object($n))
		{
			$mean = (float)$n->mean;
			$variance = (float)$n->variance;
			$n = (int)$n->n;
		}
		
		$n_combined = $this->n + $n;
		if ($n_combined > 0)
		{
			$mean_combined = ($this->mean * $this->n + $mean * $n) / $n_combined;
			$this_d = ($this->mean - $mean_combined) * ($this->mean - $mean_combined);
			$d = ($mean - $mean_combined) * ($mean - $mean_combined);
			if ($n_combined > 1)
			{
				$this->variance = ($this->n * ($this->variance + $this_d) + $n * ($variance + $d) - $this->variance - $variance) / ($n_combined - 1);
			}
			else
			{
				$this->variance = 0;
			}
			$this->mean = $mean_combined;
			$this->n = $n_combined;
		}
	}
}

?>