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
		
		$count = 1;
		$num = 1;
		$in_clause = '';
		$query = new DbQuery('SELECT id FROM users WHERE games > 0 AND rating > (SELECT u.rating FROM users u WHERE id = ?) ORDER BY rating, games DESC, games_won, id DESC LIMIT 2', $this->id);
		while ($row = $query->next())
		{
			list ($user_id) = $row;
			if (!empty($in_clause))
			{
				$in_clause .= ',';
			}
			$in_clause .= $user_id;
			++$count;
		}
		if (!empty($in_clause))
		{
			$in_clause .= ',';
		}
		$in_clause .= $this->id;
		$query = new DbQuery('SELECT id FROM users WHERE games > 0 AND rating < (SELECT u.rating FROM users u WHERE id = ?) ORDER BY rating DESC, games, games_won DESC, id LIMIT ' . (5 - $count), $this->id);
		while ($row = $query->next())
		{
			list ($user_id) = $row;
			if (!empty($in_clause))
			{
				$in_clause .= ',';
			}
			$in_clause .= $user_id;
		}
		
		list($min_time, $max_time) = Db::record(get_label('game'), 'SELECT MIN(g.end_time), MAX(g.end_time) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id IN (' . $in_clause . ')');
		if ($min_time == NULL || $max_time == NULL || $max_time - $min_time < MIN_PERIOD_ON_GRAPH)
		{
			hide_chart(get_label('Not enought data to show [0] competition', $this->name));
			return;
		}
		
		$period = floor(($max_time - $min_time) / MAX_POINTS_ON_GRAPH);
		$query = new DbQuery('SELECT u.id, CEILING(g.end_time/' . $period . ') * ' . $period . ' as period, u.name, MAX(p.rating_before+p.rating_earned) FROM players p JOIN games g ON p.game_id = g.id JOIN users u ON p.user_id = u.id WHERE u.id IN (' . $in_clause . ') GROUP BY u.id, period ORDER BY u.id, period', $this->id);
		
		$colors = array(
			array(48, 207, 207),
			array(207, 48, 207),
			array(207, 207, 48),
			array(48, 207, 48),
			array(207, 48, 48),
			array(48, 48, 207),
			array(48, 48, 48),
			array(207, 207, 207));
		
		$current_color = 0;
		$dataset = array();
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
				$data = new ChartData($user_name, $colors[$current_color][0], $colors[$current_color][1], $colors[$current_color][2]);
				++$current_color;
				$dataset[] = $data;
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