<?php 

require_once 'include/user.php';
require_once 'include/chart.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD_ON_GRAPH', 10*24*60*60);
		
class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0]: competition', $this->title);
	}
	
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
	}
	
	protected function show_body()
	{
		show_chart(CONTENT_WIDTH, floor(CONTENT_WIDTH/1.618)); // fibonacci golden ratio 1.618:1
	}
	
	protected function js_on_load()
	{
		parent::js();
		
		$colors = array(
			array(51, 153, 255),
			array(146, 208, 80),
			array(186, 140, 220),
			array(215, 160, 100),
			array(122, 124, 192));
		
		$count = 0;
		$players = array();
		$players[$this->id] = $count++;
		$query = new DbQuery('SELECT id FROM users WHERE games > 0 AND rating > (SELECT u.rating FROM users u WHERE id = ?) ORDER BY rating, games DESC, games_won, id DESC LIMIT 2', $this->id);
		while ($row = $query->next())
		{
			list ($user_id) = $row;
			$players[$user_id] = $count++;
		}
		$query = new DbQuery('SELECT id FROM users WHERE games > 0 AND rating < (SELECT u.rating FROM users u WHERE id = ?) ORDER BY rating DESC, games, games_won DESC, id LIMIT ' . (5 - $count), $this->id);
		while ($row = $query->next())
		{
			list ($user_id) = $row;
			$players[$user_id] = $count++;
		}
		
		$in_clause = $this->id;
		foreach ($players as $user_id => $index)
		{
			if ($user_id != $this->id)
			{
				$in_clause .= ',' . $user_id;
			}
		}
		
		list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $in_clause . ')');
		// list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = ?', $this->id);
		if ($min_time == NULL || $max_time == NULL || $max_time - $min_time < MIN_PERIOD_ON_GRAPH)
		{
			hide_chart(get_label('Not enought data to show [0] competition', $this->name));
			return;
		}
		
		$period = floor(($max_time - $min_time) / MAX_POINTS_ON_GRAPH);
		$query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, MAX(p.rating_before+p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE u.id IN (' . $in_clause . ') GROUP BY u.id, period ORDER BY u.id, period');
		// $query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, MAX(p.rating_before+p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE g.end_time >= ? AND g.end_time <= ? AND u.id IN (' . $in_clause . ') GROUP BY u.id, period ORDER BY u.id, period', $min_time, $max_time);
		
		$current_color = 1;
		$dataset = array(NULL, NULL, NULL, NULL, NULL);
		$current_user_id = -1;
		$first = true;
		$labels = '';
		$total_games_count = 0;
		date_default_timezone_set(get_timezone());
		while ($row = $query->next())
		{
			list ($user_id, $timestamp, $user_name, $rating) = $row;
			if ($current_user_id != $user_id)
			{
				$index = $players[$user_id];
				$data = new ChartData($user_name, $colors[$index][0], $colors[$index][1], $colors[$index][2]);
				$dataset[$index] = $data;
				$data->add_point($timestamp - $period, 0);
				$current_user_id = $user_id;
			}
			$data->add_point($timestamp, $rating);
		}
		
		init_chart($dataset);
	}
}

$page = new Page();
$page->run('', PERM_ALL);

?>