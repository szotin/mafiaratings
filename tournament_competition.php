<?php 

require_once 'include/tournament.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 4);

class Page extends TournamentPageBase
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
		
		list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		if ($this->normalizer_id != NULL && $this->normalizer_id > 0)
		{
			list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
		}
		else
		{
			$this->normalizer = '{}';
		}
		$this->scoring_options = json_decode($this->scoring_options);
		$this->scoring = json_decode($this->scoring);
		$this->normalizer = json_decode($this->normalizer);
        $players = tournament_scores($this->id, $this->flags, NULL, 0, $this->scoring, $this->normalizer, $this->scoring_options);
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
		if ($this->flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_OPTIONS;
		}
		else
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION;
		}
		
		echo '<p><form method="get" name="viewForm" action="tournament_competition.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'doUpdateChart', $scoring_select_flags);
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
			chartParams.normalizer_id = s.nId;
			chartParams.normalizer_version = s.nVer;
			chartParams.scoring_options = s.opt;
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
		chartParams.type = "tournament";
		chartParams.id = <?php echo $this->id; ?>;
		chartParams.name = "<?php echo get_label('Competition chart'); ?>"; 
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = <?php echo NUM_PLAYERS; ?>;
		chartParams.tournament_id = <?php echo $this->id; ?>;
		initChart("<?php echo get_label('Points'); ?>");
<?php 
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
