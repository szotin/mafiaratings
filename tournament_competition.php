<?php 

require_once 'include/tournament.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends TournamentPageBase
{
	private $players_list;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		$this->scoring_options = json_decode($this->scoring_options);
		$this->scoring = json_decode($this->scoring);
        $players = tournament_scores($this->id, $this->flags, NULL, 0, $this->scoring, $this->scoring_options);
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
		echo '<p><form method="get" name="viewForm" action="tournament_competition.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->scoring_options, 'doUpdateChart');
		echo '</form></p>';
		
		show_chart_legend();
		show_chart(CONTENT_WIDTH, floor(CONTENT_WIDTH/1.618)); // fibonacci golden ratio 1.618:1
	}
	
	protected function js()
	{
		parent::js();
?>		
		function doUpdateChart(id, version, options)
		{
			chartParams.scoring_id = id;
			chartParams.scoring_version = version;
			chartParams.scoring_options = options;
			updateChart();
		}
<?php 
	}
	
	protected function js_on_load()
	{
		parent::js_on_load();
?>		
		chartParams.type = "tournament";
		chartParams.id = <?php echo $this->id; ?>;
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
