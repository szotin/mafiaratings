<?php 

require_once 'include/event.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 4);

class Page extends EventPageBase
{
	private $players_list;
	private $first;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		$this->first = 0;
		if (isset($_REQUEST['first']))
		{
			$this->first = (int)$_REQUEST['first'];
		}
		
		list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		$scoring = json_decode($scoring);
		$this->scoring_options = json_decode($this->scoring_options);
		$players = event_scores($this->id, NULL, 0, $scoring, $this->scoring_options, $this->tournament_flags, $this->round_num);
		$players_count = count($players);
		
		$separator = '';
		$num = $this->first;
		for ($i = 0; $i < NUM_PLAYERS; ++$i)
		{
			if ($num >= $players_count)
			{
				break;
			}
			
			$player = $players[$num];
			$this->players_list .= $separator . $player->id;
			$separator = ',';
			++$num;
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
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		
		echo '<p><form method="get" name="viewForm" action="event_competition.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, 0, 0, $this->scoring_options, ' ', 'doUpdateChart', SCORING_SELECT_FLAG_NO_NORMALIZER | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION);
		echo '</form></p>';
		
		echo '<table width="100%"><tr><td width="36">';
		if ($this->first > 0)
		{
			echo '<button class="navigate-btn" onclick="goPrev()"><img src="images/prev.png" class="text"></button>';
		}
		echo '</td><td>';
		show_chart_legend();
		echo '</td><td><td align="right" width="34"><button class="navigate-btn" onclick="goNext()"><img src="images/next.png" class="text"></button></td></tr></table>';
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
	
		function goNext()
		{
			goTo({first: <?php echo $this->first + 1; ?>});
		}
		
		function goPrev()
		{
			goTo({first: <?php echo $this->first - 1; ?>});
		}
<?php 
	}
	
	protected function js_on_load()
	{
		parent::js_on_load();
?>		
		chartParams.type = "event";
		chartParams.id = <?php echo $this->id; ?>;
		chartParams.name = "<?php echo get_label('Competition chart'); ?>"; 
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = <?php echo NUM_PLAYERS; ?>;
		chartParams.event_id = <?php echo $this->id; ?>;
		chartParams.show_all = <?php echo $this->show_all ? 'null' : 'undefined'; ?>;
		initChart("<?php echo get_label('Points'); ?>");
<?php 
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
