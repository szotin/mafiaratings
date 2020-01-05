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
		
		$this->season = SEASON_LATEST;
		if (isset($_REQUEST['season']))
		{
			$this->season = (int)$_REQUEST['season'];
		}
		if ($this->season == 0)
		{
			$this->season = get_current_club_season($this->id);
		}
		
		$this->scoring = NULL;
		if (isset($_REQUEST['scoring_id']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring_id'];
			if (isset($_REQUEST['scoring_version']))
			{
				$this->scoring_version = (int)$_REQUEST['scoring_version'];
				list($this->scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
			}
		}
		if ($this->scoring == NULL)
		{
			list($this->scoring, $this->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->scoring_id);
		}
		
		$start_time = $end_time = 0;
		if ($this->season > SEASON_LATEST)
		{
			list($start_time, $end_time) = Db::record(get_label('season'), 'SELECT start_time, end_time FROM club_seasons WHERE id = ?', $this->season);
		}
		else if ($this->season < SEASON_ALL_TIME)
		{
			date_default_timezone_set($this->timezone);
			$start_time = mktime(0, 0, 0, 1, 1, -$this->season);
			$end_time = mktime(0, 0, 0, 1, 1, 1 - $this->season);
		}
		
		$players = club_scores($this->id, $start_time, $end_time, NULL, 0, $this->scoring);
		
		$players_count = count($players);
		$separator = '';
		if ($players_count > NUM_PLAYERS)
		{
			$players_count = NUM_PLAYERS;
		}
		
		for ($num = 0; $num < $players_count; ++$num)
		{
			$score = $players[$num];
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
		show_scoring_select($this->id, $this->scoring_id, $this->scoring_version, 'doUpdateChart');
		echo ' ';
		$this->season = show_club_seasons_select($this->id, $this->season, 'doUpdateChart()', get_label('Standings by season.'));	
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
			chartParams.scoring_id = $('#scoring-sel').val();
			chartParams.scoring_version = $('#scoring-ver').val();
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