<?php

require_once 'include/session.php';
require_once 'include/chart.php';
require_once 'include/scoring.php';
require_once 'include/club.php';

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
	
	$chart_count = MAX_CHARTS_COUNT;
	if (isset($_REQUEST['charts']))
	{
		$chart_count = (int)$_REQUEST['charts'];
		if ($chart_count <= 0 || $chart_count > MAX_CHARTS_COUNT)
		{
			$chart_count = MAX_CHARTS_COUNT;
		}
	}
	
	if (!isset($_REQUEST['players']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('player')));
	}
	$player_list = $_REQUEST['players'];
	$user_ids = chart_list_to_array($player_list, $chart_count);
	$player_list = chart_array_to_list($user_ids, 0);
	
	$name = '';
	if (isset($_REQUEST['name']))
	{
		$name = $_REQUEST['name'];
	}
	
	if (!empty($player_list))
	{
		$current_color = 0;
		if ($type == 'rating')
		{
			date_default_timezone_set(get_timezone());
			
			foreach ($user_ids as $user_id)
			{
				$result[] = new ChartData('', $_chart_colors[$current_color++]);
			}
		
			list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $player_list . ')');
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
		else if ($type == 'event')
		{
			if (!isset($_REQUEST['id']))
			{
				throw new FatalExc(get_label('Unknown [0]', get_label('event')));
			}
			$event_id = (int)$_REQUEST['id'];
			
			list($scoring_id, $timezone) = Db::record(get_label('event'), 'SELECT e.scoring_id, c.timezone FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE e.id = ?', $event_id);
			if (isset($_REQUEST['scoring']))
			{
				$sid = (int)$_REQUEST['scoring'];
				if ($sid > 0)
				{
					$scoring_id = $sid;
				}
			}
			date_default_timezone_set($timezone);
			
			$scoring_system = new ScoringSystem($scoring_id);
			$scores = new Scores($scoring_system, new SQL(' AND g.event_id = ?', $event_id), new SQL(' AND p.user_id IN(' . $player_list . ')'), MAX_POINTS_ON_GRAPH);
	
			$players_count = count($scores->players);
			foreach ($user_ids as $user_id)
			{
				if ($user_id > 0)
				{
					$player = NULL;
					for ($i = 0; $i < $players_count; ++$i)
					{
						if ($scores->players[$i]->id == $user_id)
						{
							$player = $scores->players[$i];
							break;
						}
					}
					
					if ($player != NULL)
					{
						$data = new ChartData($player->name, $_chart_colors[$current_color]);
						foreach ($player->history as $point)
						{
							$data->data[] = new ChartPoint($point->timestamp, $point->points);
						}
						$result[] = $data;
					}
				}
				++$current_color;
			}
		}
		else if ($type == 'club')
		{
			if (!isset($_REQUEST['id']))
			{
				throw new FatalExc(get_label('Unknown [0]', get_label('event')));
			}
			$club_id = (int)$_REQUEST['id'];
			
			list($scoring_id, $timezone) = Db::record(get_label('event'), 'SELECT c.scoring_id, ct.timezone FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
			if (isset($_REQUEST['scoring']))
			{
				$sid = (int)$_REQUEST['scoring'];
				if ($sid > 0)
				{
					$scoring_id = $sid;
				}
			}
			date_default_timezone_set($timezone);
			
			if (isset($_REQUEST['scoring']))
			{
				$sid = (int)$_REQUEST['scoring'];
				if ($sid > 0)
				{
					$scoring_id = $sid;
				}
			}
			
			$season = 0;
			if (isset($_REQUEST['season']))
			{
				$season = (int)$_REQUEST['season'];
			}
			if ($season == 0)
			{
				$season = get_current_season($club_id);
			}
			
			$scoring_system = new ScoringSystem($scoring_id);
			$scores = new Scores($scoring_system, new SQL(' AND g.club_id = ?', $club_id), new SQL(' AND p.user_id IN(' . $player_list . ')', get_season_condition($season, 'g.start_time', 'g.end_time')), MAX_POINTS_ON_GRAPH);
	
			$players_count = count($scores->players);
			foreach ($user_ids as $user_id)
			{
				if ($user_id > 0)
				{
					$player = NULL;
					for ($i = 0; $i < $players_count; ++$i)
					{
						if ($scores->players[$i]->id == $user_id)
						{
							$player = $scores->players[$i];
							break;
						}
					}
					
					if ($player != NULL)
					{
						$data = new ChartData($player->name, $_chart_colors[$current_color]);
						foreach ($player->history as $point)
						{
							$data->data[] = new ChartPoint($point->timestamp, $point->points);
						}
						$result[] = $data;
					}
				}
				++$current_color;
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