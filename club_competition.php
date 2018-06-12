<?php 

require_once 'include/club.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends ClubPageBase
{
	private $players_list;
	private $season; 
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		$this->season = 0;
		if (isset($_REQUEST['season']))
		{
			$this->season = (int)$_REQUEST['season'];
		}
		if ($this->season == 0)
		{
			$this->season = get_current_season($this->id);
		}
		
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
		}
		
		$scoring_system = new ScoringSystem($this->scoring_id);
		$scores = new Scores($scoring_system, new SQL(' AND g.club_id = ?', $this->id), get_season_condition($this->season, 'g.start_time', 'g.end_time'));
		$players_count = count($scores->players);
		$separator = '';
		if ($players_count > NUM_PLAYERS)
		{
			$players_count = NUM_PLAYERS;
		}
		
		for ($num = 0; $num < $players_count; ++$num)
		{
			$score = $scores->players[$num];
			$this->players_list .= $separator . $score->id;
			$separator = ',';
		}
		
		while ($num < 5)
		{
			$this->players_list .= $separator;
			++$num;
		}
	}
	
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
	}
	
	protected function show_body()
	{
		echo '<p>';
		show_scoring_select($this->id, $this->scoring_id, 'doUpdateChart()', get_label('Scoring system'));		
		echo ' ';
		$this->season = show_seasons_select($this->id, $this->season, 'doUpdateChart()', get_label('Standings by season.'));	
		echo '</p>';
		
		show_chart_legend();
		show_chart(CONTENT_WIDTH, floor(CONTENT_WIDTH/1.618)); // fibonacci golden ratio 1.618:1
	}
	
	protected function js()
	{
		parent::js();
?>
		function doUpdateChart()
		{
			chartParams.scoring = $("#scoring").val();
			chartParams.season = $("#season").val();
			updateChart();
		}
<?php
	}
	
	protected function js_on_load()
	{
		parent::js_on_load();
?>		
		chartParams.type = "club";
		chartParams.id = <?php echo $this->id; ?>;
		chartParams.name = "<?php echo $this->_title; ?>";
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = <?php echo NUM_PLAYERS; ?>;
		initChart("<?php echo get_label('Points'); ?>");
<?php 
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>