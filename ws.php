<?php

require_once 'include/general_page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
?>
		<dl>
		  <dt><h4><a href="ws_games.php?help">ws_games.php</a></h4></dt>
			<dd>
				<p>Returns games logs containg all information about a game: nominations, votings, shooting, checking etc... All stats can be calculated by analyzing these logs.</p>
			</dd>
		  <dt><h4><a href="ws_ratings.php?help">ws_ratings.php</a></h4></dt>
			<dd>
				<p>Returns players global Elo ratings. <?php echo PRODUCT_NAME; ?> has global ratings and club/event scores. Scores are calculated using configurable standard scoring systems like ФИИМ system, or 3-4-4-5. Every club/event has its own scoring.</p>
				<p>In addition to this <?php echo PRODUCT_NAME; ?> has global ratings. Standard scoring does not work well for inter-club comparison. Because players played different number of games, some of them never meet each other, etc. So we implmented Elo ratings, which is a proven rating sistem in chess, go, and some other games.</p>
			</dd>
		</dl>	
<?php
	}
}

$page = new Page();
$page->run(get_label('[0] web services', PRODUCT_NAME), PERM_ALL);

?>