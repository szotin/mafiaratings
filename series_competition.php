<?php 

require_once 'include/series.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/chart.php';

define('NUM_PLAYERS', 4);

class Page extends SeriesPageBase
{
	protected function show_body()
	{
		echo '<h1>' . get_label('Under construction') . '</h1>';
		echo '<img src="images/repairs.png">';
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
