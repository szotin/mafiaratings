<?php 

require_once 'include/general_page_base.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 4);

class Page extends GeneralPageBase
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
		
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$query = new DbQuery('SELECT u.id FROM users u WHERE u.games > 0');
		$ccc_id = $this->ccc_filter->get_id();
		switch ($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$query->add(' AND u.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$query->add(' AND u.club_id IN (SELECT club_id FROM club_users WHERE (flags & ' . USER_CLUB_FLAG_BANNED . ') = 0 AND user_id = ?)', $_profile->user_id);
			}
			break;
		case CCCF_CITY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$query->add(' AND u.city_id IN (SELECT id FROM cities WHERE country_id = ?)', $ccc_id);
			break;
		}
		
		$separator = '';
		$this->players_list = '';
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $this->first . ', ' . NUM_PLAYERS);
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
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$this->ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('players')));
		echo '</td></tr></table></p>';
		
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