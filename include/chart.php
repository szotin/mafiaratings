<?php

require_once('include/localization.php');

define('MAX_CHARTS_COUNT', 5);

$_chart_colors = array(
	new ChartColor(51, 153, 255),
	// new ChartColor(186, 140, 220),
	new ChartColor(175, 121, 215),
	new ChartColor(146, 208, 80),
	new ChartColor(215, 160, 100),
	new ChartColor(122, 124, 192));


// Put it where the chart has to be.
function show_chart($width, $height)
{
	echo '<canvas id="chart" width="' . $width . '" height="' . $height . '"></canvas>';
}

function show_chart_legend()
{
	echo '<span id="chart-legend"></span>';
}

function chart_list_to_array($player_list, $chart_count)
{
	$players = explode(',', $player_list);
	$count = count($players);
	$result = array();
	if ($chart_count <= 0)
	{
		for ($i = 0; $i < $count; ++$i)
		{
			$id = (int)$players[$i];
			if ($id > 0)
			{
				$result[] = $id;
			}
		}
	}
	else
	{
		for ($i = 0; $i < $count && $i < $chart_count; ++$i)
		{
			$result[] = (int)$players[$i];
		}
		for (; $i < $chart_count; ++$i)
		{
			$result[] = 0;
		}
	}
	return $result;
}

function chart_array_to_list($player_array, $chart_count)
{
	$player_list = '';
	$count = 0;
	if ($chart_count <= 0)
	{
		foreach ($player_array as $id)
		{
			if ($id > 0)
			{
				if ($count > 0)
				{
					$player_list .= ',';
				}
				$player_list .= $id;
				++$count;
			}
		}
	}
	else
	{
		foreach ($player_array as $id)
		{
			if ($count > 0)
			{
				$player_list .= ',';
			}
			
			if ($id > 0)
			{
				$player_list .= $id;
			}
			
			if (++$count >= $chart_count)
			{
				break;
			}
		}
		
		for (; $count < $chart_count; ++$count)
		{
			$player_list .= ',';
		}
	}
	return $player_list;
}

// Put it to the overriden PageBase::add_headers() function.
function add_chart_headers()
{
	echo '<script src="js/moment.js"></script>';
	echo '<script src="js/Chart.min.js"></script>';
	echo '<script src="js/mr.chart.js"></script>';
}

class ChartPoint
{
	public $x;
	public $y;
	
	function __construct($timestamp, $y)
	{
		$this->x = date('m/d/Y H:i', $timestamp);
		$this->y = (float)$y;
	}
}

class ChartColor
{
	public $r;
	public $g;
	public $b;
	
	function __construct($r, $g, $b)
	{
		$this->r = (int)$r;
		$this->g = (int)$g;
		$this->b = (int)$b;
	}
}

class ChartData
{
	public $label;
	public $lineTension;
	public $fill;
	public $backgroundColor; // This is a string. The format is 'rgb(100,200,100)' or 'rgba(100,200,100,0.2)'
	public $borderColor; // This is a string. The format is 'rgb(100,200,100)' or 'rgba(100,200,100,0.2)'
	public $data; // array of ChartPoint
	
	function __construct ($label, $color) // $color is ChartColor
	{
		$this->label = $label;
		$this->lineTension = 0;
		$this->fill = false;
		$this->backgroundColor = $this->borderColor = 'rgba(' . $color->r . ',' . $color->g . ',' . $color->b . ',0.7)';
		$this->data = array();
	}
	
	function add_point($timestamp, $delta)
	{
		$count = count($this->data);
		if ($count > 0)
		{
			$delta += $this->data[$count - 1]->y;
		}
		$this->data[] = new ChartPoint($timestamp, $delta);
	}
	
	function point_count()
	{
		return count($data);
	}
}

?>