<?php 

require_once 'include/event.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends EventPageBase
{
	private $players_list;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->event->scoring_id, $this->event->scoring_version);
		$scoring = json_decode($scoring);
		$this->scoring_options = json_decode($this->event->scoring_options);
		$players = event_scores($this->event->id, NULL, 0, $scoring, $this->event->scoring_options);
		$players_count = count($players);
		if ($players_count > NUM_PLAYERS)
		{
			$players_count = NUM_PLAYERS;
		}
		
		$separator = '';
		for ($num = 0; $num < $players_count; ++$num)
		{
			$player = $players[$num];
			$this->players_list .= $separator . $player->id;
			$separator = ',';
		}
		
		while ($num < NUM_PLAYERS)
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
		show_scoring_select($this->event->club_id, $this->event->scoring_id, $this->event->scoring_version, 0, 0, $this->scoring_options, ' ', 'doUpdateChart', SCORING_SELECT_FLAG_NO_NORMALIZER | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION);
		echo '</form></p>';
		
		show_chart_legend();
		show_chart(CONTENT_WIDTH, floor(CONTENT_WIDTH/1.618)); // fibonacci golden ratio 1.618:1
	}
	
	protected function js()
	{
		parent::js();
?>		
		function doUpdateChart(s)
		{
			chartParams.scoring_id = s.sId;
			chartParams.scoring_version = s.sVer;
			chartParams.scoring_options = s.ops;
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
$page->run(get_label('Competition Chart'));

?>
