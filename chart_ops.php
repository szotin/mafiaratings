<?php

require_once 'include/session.php';
require_once 'include/chart.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD', 24*60*60);

ob_start();
$result = array();
try
{
	initiate_session();
	check_maintenance();
	
	if (!isset($_REQUEST['type']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('chart type')));
	}
	$type = $_REQUEST['type'];
	
	if ($type == 'rating')
	{
		if (!isset($_REQUEST['players']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		$player_list = $_REQUEST['players'];
		
		$chart_count = MAX_CHARTS_COUNT;
		if (isset($_REQUEST['charts']))
		{
			$chart_count = (int)$_REQUEST['charts'];
			if ($chart_count <= 0 || $chart_count > MAX_CHARTS_COUNT)
			{
				$chart_count = MAX_CHARTS_COUNT;
			}
		}
		
		$name = '';
		if (isset($_REQUEST['name']))
		{
			$name = $_REQUEST['name'];
		}

		$user_ids = chart_list_to_array($player_list, $chart_count);
		$player_list = chart_array_to_list($user_ids, 0);
		
		if (!empty($player_list))
		{
			$current_color = 0;
			foreach ($user_ids as $user_id)
			{
				$result[] = new ChartData('', $_chart_colors[$current_color++]);
			}
		
			list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $player_list . ')');
			// list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = ?', $this->id);
			if ($min_time == NULL || $max_time == NULL) // || $max_time - $min_time < MIN_PERIOD_ON_GRAPH)
			{
				throw new Exc(get_label('Not enought data to show [0]', $name));
			}
			
			$period = floor(($max_time - $min_time) / MAX_POINTS_ON_GRAPH);
			if ($period <= 0)
			{
				$period = MIN_PERIOD;
			}
			$query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, SUM(p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE u.id IN (' . $player_list . ') GROUP BY u.id, period ORDER BY u.id, period');
			// $query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, MAX(p.rating_before+p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE g.end_time >= ? AND g.end_time <= ? AND u.id IN (' . $player_list . ') GROUP BY u.id, period ORDER BY u.id, period', $min_time, $max_time);
			
			$current_user_id = -1;
			while ($row = $query->next())
			{
				list ($user_id, $timestamp, $user_name, $rating) = $row;
				if ($current_user_id != $user_id)
				{
					for ($index = 0; $index < $chart_count; ++$index)
					{
						if ($user_ids[$index] == $user_id)
						{
							break;
						}
					}
					
					if ($index < $chart_count)
					{
						$data = $result[$index];
						$data->label = $user_name;
						$data->add_point($timestamp - $period, 0);
					}
					else
					{
						$data = NULL;
					}
					$current_user_id = $user_id;
				}
				
				if ($data != NULL)
				{
					$data->add_point($timestamp, $rating);
				}
			}
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	Exc::log($e);
	$result['error'] = $e->getMessage();
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = $result['message'] . '<hr>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>