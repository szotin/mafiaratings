<?php 

require_once 'include/event.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends EventPageBase
{
	private $players_list;
	private $scoring_id;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		$this->_title = get_label('[0]: competition', $this->event->name);
		
		$this->scoring_id = $this->event->scoring_id;
		if (isset($_REQUEST['scoring']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring'];
		}
		
		$condition = new SQL(' AND g.event_id = ?', $this->event->id);
		$scoring_system = new ScoringSystem($this->scoring_id);
		$scores = new Scores($scoring_system, $condition);
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
		echo '<p><form method="get" name="viewForm" action="event_competition.php">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		show_scoring_select($this->event->club_id, $this->scoring_id, 'doUpdateChart()', get_label('Scoring system'));
		echo '</form></p>';
		
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
			updateChart();
		}
<?php 
	}
	
	protected function js_on_load()
	{
		parent::js_on_load();
?>		
		chartParams.type = "event";
		chartParams.id = <?php echo $this->event->id; ?>;
		chartParams.name = "<?php echo get_label('Competition chart'); ?>"; 
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = <?php echo NUM_PLAYERS; ?>;
		initChart("<?php echo get_label('Points'); ?>");
<?php 
	}
}

$page = new Page();
$page->run(get_label('Competition'), PERM_ALL);

?>