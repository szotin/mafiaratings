<?php

require_once '../../include/api.php';
require_once '../../include/chart.php';
require_once '../../include/scoring.php';
require_once '../../include/club.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD', 24*60*60);

class ApiPage extends ControlApiPageBase
{
	protected function prepare_response()
	{
		global $_chart_colors, $_lang;
		
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
					$this->response[] = new ChartData('', $_chart_colors[$current_color++]);
				}
			
				list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $player_list . ') AND g.is_canceled = FALSE AND g.result > 0 AND g.is_rating <> 0');
				if ($min_time != NULL && $max_time != NULL) // || $max_time - $min_time < MIN_PERIOD_ON_GRAPH)
				{
					$period = floor(($max_time - $min_time) / MAX_POINTS_ON_GRAPH);
					if ($period <= 0)
					{
						$period = MIN_PERIOD;
					}
					$query = new DbQuery(
						'SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, nu.name, SUM(p.rating_earned)'.
						' FROM players p'.
						' JOIN games g ON p.game_id = g.id'.
						' JOIN users u ON p.user_id = u.id'.
						' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
						' WHERE u.id IN (' . $player_list . ') AND g.is_canceled = FALSE AND g.result > 0 AND g.is_rating <> 0'.
						' GROUP BY u.id, period'.
						' ORDER BY u.id, period');
					
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
								$data = $this->response[$index];
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
			else if ($type == 'event')
			{
				if (!isset($_REQUEST['id']))
				{
					throw new FatalExc(get_label('Unknown [0]', get_label('event')));
				}
				$event_id = (int)$_REQUEST['id'];
				
				list($scoring_id, $scoring_version, $scoring, $scoring_options, $timezone, $tournament_id, $tournament_flags, $round_num, $club_id) = 
					Db::record(get_label('event'), 
						'SELECT e.scoring_id, e.scoring_version, s.scoring, e.scoring_options, c.timezone, t.id, t.flags, e.round, e.club_id'.
						' FROM events e'.
						' JOIN addresses a ON a.id = e.address_id'.
						' JOIN cities c ON c.id = a.city_id'.
						' JOIN scoring_versions s ON s.scoring_id = e.scoring_id AND s.version = e.scoring_version'.
						' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
						' WHERE e.id = ?', $event_id);
				if (isset($_REQUEST['scoring_id']) && $_REQUEST['scoring_id'] > 0)
				{
					$scoring_id = (int)$_REQUEST['scoring_id'];
					if (isset($_REQUEST['scoring_version']) && $_REQUEST['scoring_version'] > 0)
					{
						$scoring_version = (int)$_REQUEST['scoring_version'];
						list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $scoring_id, $scoring_version);
					}
					else
					{
						list($scoring, $scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
					}
				}
				$scoring = json_decode($scoring);
				$scoring_options = json_decode($scoring_options);
				
				if (isset($_REQUEST['scoring_options']))
				{
					$ops = $_REQUEST['scoring_options'];
					if (is_string($ops))
					{
						$ops = json_decode($ops);
					}
					foreach($ops as $key => $value) 
					{
						$scoring_options->$key = $value;
					}
				}

				if (isset($_REQUEST['show_all']) &&
					is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id))
				{
					$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
				}	
				
				$players = NULL;
				if (isset($_REQUEST['players']))
				{
					$players = explode(',', $_REQUEST['players']);
				}
				
				$players = event_scores($event_id, $players, SCORING_LOD_HISTORY | SCORING_LOD_NO_SORTING, $scoring, $scoring_options, $tournament_flags, $round_num);
				$players_count = count($players);
				foreach ($user_ids as $user_id)
				{
					if ($user_id > 0 && isset($players[$user_id]))
					{
						$player = $players[$user_id];
						if ($player != NULL)
						{
							$data = new ChartData($player->name, $_chart_colors[$current_color]);
							foreach ($player->history as $point)
							{
								$data->data[] = new ChartPoint($point->time, $point->points);
							}
							$this->response[] = $data;
						}
					}
					++$current_color;
				}
			}
			else if ($type == 'tournament')
			{
				if (!isset($_REQUEST['id']))
				{
					throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
				}
				$tournament_id = (int)$_REQUEST['id'];
				
				list($scoring_id, $scoring, $normalizer_id, $normalizer, $scoring_options, $timezone, $tournament_flags, $club_id) = Db::record(get_label('tournament'), 'SELECT t.scoring_id, s.scoring, t.normalizer_id, n.normalizer, t.scoring_options, c.timezone, t.flags, t.club_id FROM tournaments t JOIN addresses a ON a.id = t.address_id JOIN cities c ON c.id = a.city_id JOIN scoring_versions s ON s.scoring_id = t.scoring_id AND s.version = t.scoring_version LEFT OUTER JOIN normalizer_versions n ON n.normalizer_id = t.normalizer_id AND n.version = t.normalizer_version WHERE t.id = ?', $tournament_id);
				if (isset($_REQUEST['scoring_id']) && $_REQUEST['scoring_id'] > 0)
				{
					$scoring_id = (int)$_REQUEST['scoring_id'];
					if (isset($_REQUEST['scoring_version']) && $_REQUEST['scoring_version'] > 0)
					{
						$scoring_version = (int)$_REQUEST['scoring_version'];
						list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $scoring_id, $scoring_version);
					}
					else
					{
						list($scoring, $scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
					}
				}
				$scoring = json_decode($scoring);
				
				if (is_null($normalizer))
				{
					$normalizer = '{}';
				}
				if (isset($_REQUEST['normalizer_id']))
				{
					$normalizer_id = (int)$_REQUEST['normalizer_id'];
					if ($normalizer_id <= 0)
					{
						$normalizer = '{}';
					}
					else if (isset($_REQUEST['normalizer_version']) && $_REQUEST['normalizer_version'] > 0)
					{
						$normalizer_version = (int)$_REQUEST['normalizer_version'];
						list($normalizer) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $normalizer_id, $normalizer_version);
					}
					else
					{
						list($normalizer, $normalizer_version) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer, version FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $normalizer_id);
					}
				}
				$normalizer = json_decode($normalizer);
				
				$scoring_options = json_decode($scoring_options);
				if (isset($_REQUEST['scoring_options']))
				{
					$ops = $_REQUEST['scoring_options'];
					if (is_string($ops))
					{
						$ops = json_decode($ops);
					}
					foreach($ops as $key => $value) 
					{
						$scoring_options->$key = $value;
					}
				}

				if (isset($_REQUEST['show_all']) &&
					is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id))
				{
					$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
				}	
				
				$players = NULL;
				if (isset($_REQUEST['players']))
				{
					$players = explode(',', $_REQUEST['players']);
				}
				
				$players = tournament_scores($tournament_id, $tournament_flags, $players, SCORING_LOD_HISTORY | SCORING_LOD_NO_SORTING, $scoring, $normalizer, $scoring_options);
				//print_json($players);
				$players_count = count($players);
				foreach ($user_ids as $user_id)
				{
					if ($user_id > 0 && isset($players[$user_id]))
					{
						$player = $players[$user_id];
						if ($player != NULL)
						{
							$data = new ChartData($player->name, $_chart_colors[$current_color]);
							foreach ($player->history as $point)
							{
								$data->data[] = new ChartPoint($point->time, $point->points);
							}
							$this->response[] = $data;
						}
					}
					++$current_color;
				}
			}
		}
	}
	
	protected function show_help()
	{
		$this->show_help_title();
		$this->show_help_request_params_head();
?>
		<dt>type</dt>
			<dd>
				Type of the chart. Possible values are:
				<ul>
					<li>rating - returns chart data for global ratings. For example: <a href="chart.php?type=rating&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=rating&players=264</a> returns Tigra rating all time chart data.</li>
					<li>event - returns chart data for event points. For example: <a href="chart.php?type=event&id=7927&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=event&id=7927&players=264</a> returns Tigra scoring chart data during VaWaCa-2017.</li>
					<li>tournament - returns chart data for tournament points. For example: <a href="chart.php?type=tournament&id=22&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=tournament&id=22&players=264</a> returns Tigra scoring chart data during Alcatraz-2019.</li>
					<li>club - returns chart data for club points. For example: <a href="chart.php?type=club&id=1&players=264"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=club&id=1&players=264</a> returns Tigra scoring chart data in Vancouver Mafia Club.</li>
				</ul>
			</dd>
		<dt>players</dt>
			<dd>Comma separated list of player ids. For example: <a href="chart.php?type=rating&players=264,25,137"><?php echo PRODUCT_URL; ?>/api/control/chart.php?type=rating&players=264,25,137</a> returns all time rating chart data for three players: Tigra, Fantomas, and Alena respectively.</dd>
		<dt>id</dt>
			<dd>When the type is "event" or "club", this param must contain id of the respective object.</dd>
		<dt>scoring</dt>
			<dd>When the type is "event" or "club", this param can contain id of the alternative scoring system. </dd>
<?php
	}
}

$page = new ApiPage();
$page->run('Chart Data');

?>