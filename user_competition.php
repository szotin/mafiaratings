<?php 

require_once 'include/user.php';
require_once 'include/chart.php';

define('MAX_POINTS_ON_GRAPH', 50);
define('MIN_PERIOD_ON_GRAPH', 10*24*60*60);
		
class Page extends UserPageBase
{
	private $players_list;
	
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0]: competition', $this->title);
		
		$rating_pos = -1;
		$query = new DbQuery('SELECT rating, games, games_won FROM users WHERE id = ?', $this->id);
		if ($row = $query->next())
		{
			list ($rating, $games, $won) = $row;
			list ($rating_pos) = Db::record(get_label('rating'), 
				'SELECT count(*) FROM users WHERE games > 0 AND (rating > ? OR (rating = ? AND (games < ? OR (games = ? AND (games_won > ? OR (games_won = ? AND id < ?))))))', 
				$rating, $rating, $games, $games, $won, $won, $this->id);
		}
		
		$rating_page = $rating_pos - 2;
		if ($rating_page < 0)
		{
			$rating_page = 0;
		}
		
		$separator = '';
		$this->players_list = '';
		$query = new DbQuery('SELECT u.id FROM users u WHERE u.games > 0 ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $rating_page . ',5');
		while ($row = $query->next())
		{
			list ($user_id) = $row;
			$this->players_list .= $separator . $user_id;
			$separator = ',';
		}
	}
	
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
	}
	
	protected function show_body()
	{
		show_chart_legend();
		show_chart(CONTENT_WIDTH, floor(CONTENT_WIDTH/1.618)); // fibonacci golden ratio 1.618:1
	}
	
	protected function js_on_load()
	{
		parent::js();
		echo 'initChart("' . get_label('Rating') . '", { type: "rating", name: "' . get_label('[0] competition', $this->name) . '", players: "' . $this->players_list . '", main: ' . $this->id . ', charts:5 });';
	}
}

$page = new Page();
$page->run('', PERM_ALL);

?>