<?php

require_once('include/localization.php');

// Put it where the chart has to be.
function show_chart($width, $height)
{
	echo '<span id="chatPlace"><canvas id="chart" width="' . $width . '" height="' . $height . '"></canvas></span>';
}

// Put it to the overriden PageBase::add_headers() function.
function add_chart_headers()
{
	echo '<script src="js/moment.js"></script>';
	echo '<script src="js/Chart.min.js"></script>';
}

// Put it to the overriden PageBase::js_on_load() function in case of error or inaplicable chart. It removes the whole chart area from the html.
function hide_chart($message = '')
{
	echo '$("#chatPlace").html("' . $message . '");';
}

class ChartData
{
	public $label;
	public $color; // This is a string. The format is 'rgb(100,200,100)' or 'rgba(100,200,100,0.2)'
	public $back_color; // This is a string. The format is 'rgb(100,200,100)' or 'rgba(100,200,100,0.2)'
	public $x; // array of x values
	public $y; // array of y values (timestamps expected)
	
	function __construct ($label, $r, $g, $b)
	{
		$this->label = $label;
		$this->back_color = $this->color = 'rgba(' . $r . ',' . $g . ',' . $b . ',0.7)';
		$this->x = array();
		$this->y = array();
	}
	
	function add_point($x, $y)
	{
		$this->x[] = $x;
		$this->y[] = $y;
	}
	
	function point_count()
	{
		return min(count($this->x), count($this->y));
	}
}

// Put it to the overriden PageBase::js_on_load() function
function init_chart($data_array)
{
	
?>	
	var ctx = document.getElementById("chart");
	var myChart = new Chart(ctx,
	{
		type: 'line',
		data:
		{
			datasets: 
			[
<?php
				foreach ($data_array as $chart_data)
				{
?>
					{
						label: '<?php echo $chart_data->label; ?>',
						lineTension: 0,
						fill: false,					
						backgroundColor: '<?php echo $chart_data->back_color; ?>',
						borderColor: '<?php echo $chart_data->color; ?>',
						data: 
						[
<?php
							for ($i = 0; $i < $chart_data->point_count(); ++$i)
							{
								echo '{ x: "' . date('m/d/Y H:i', $chart_data->x[$i]) . '", y: ' . $chart_data->y[$i] . ' }, ';
							}
?>
						],
					},
<?php
				}
?>
			],
			borderWidth: 1
		},
		options: 
		{
			responsive: false,
			scales: 
			{
				xAxes: 
				[
					{
						type: 'time',
						time: 
						{
							format: 'MM/DD/YYYY HH:mm',
							//  round: 'day',
							tooltipFormat: 'MM/DD/YYYY HH:mm'
						}
					}
				],
				yAxes: 
				[
					{
					scaleLabel: 
					{
						display: true,
						labelString: '<?php echo get_label('Rating'); ?>'
					}
				}]
			},
			maintainAspectRatio: false
		}
	});
<?php
}

?>