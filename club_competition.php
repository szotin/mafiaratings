<?php 

require_once 'include/club.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 4);

class Page extends ClubPageBase
{
	private $players_list;
	private $first;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
		$user_id = 0;
		if (isset($_REQUEST['user_id']))
		{
			$user_id = (int)$_REQUEST['user_id'];
		}
		
		$this->first = 0;
		if (isset($_REQUEST['first']))
		{
			$this->first = (int)$_REQUEST['first'];
		}
		
		$separator = '';
		$this->players_list = '';
		if ($user_id <= 0)
		{
			$query = new DbQuery('SELECT u.id FROM users u WHERE u.games > 0 AND u.club_id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $this->first . ', ' . NUM_PLAYERS, $this->id);
			while ($row = $query->next())
			{
				list ($user_id) = $row;
				$this->players_list .= $separator . $user_id;
				$separator = ',';
			}
		}
		else
		{
			// todo - implement a query around this user
		}
	}
	
	protected function add_headers()
	{
		parent::add_headers();
		add_chart_headers();
	}
	
	protected function show_body()
	{
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
	
	protected function js_on_load()
	{
		parent::js_on_load();
?>
		chartParams.type = "rating";
		chartParams.name = "<?php echo get_label('Competition chart'); ?>";
		chartParams.players = "<?php echo $this->players_list; ?>";
		chartParams.charts = "<?php echo NUM_PLAYERS; ?>";
		chartParams.club_id = "<?php echo $this->id; ?>";
		initChart("<?php echo get_label('Rating'); ?>");
<?php
	}
	
	protected function js()
	{
		parent::js();
?>
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
}

$page = new Page();
$page->run(get_label('Competition chart'));

?>