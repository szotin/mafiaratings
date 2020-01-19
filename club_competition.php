<?php 

require_once 'include/club.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends ClubPageBase
{
	private $players_list;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		$separator = '';
		$this->players_list = '';
		$query = new DbQuery('SELECT u.id FROM users u WHERE u.games > 0 AND u.club_id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . NUM_PLAYERS, $this->id);
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
		parent::js_on_load();
?>
		chartParams.type = "rating";
		chartParams.name = "<?php echo get_label('Competition chart'); ?>";
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = "<?php echo NUM_PLAYERS; ?>";
		initChart("<?php echo get_label('Rating'); ?>");
<?php
	}
}

$page = new Page();
$page->run(get_label('Competition chart'));

?>