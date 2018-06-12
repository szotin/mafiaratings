<?php 

require_once 'include/general_page_base.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 5);

class Page extends GeneralPageBase
{
	private $players_list;
	
	protected function prepare()
	{
		global $_profile;
		parent::prepare();
		
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
				$query->add(' AND u.club_id IN (SELECT club_id FROM user_clubs WHERE (flags & ' . UC_FLAG_BANNED . ') = 0 AND user_id = ?)', $_profile->user_id);
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
		$query->add(' ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . NUM_PLAYERS);
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