<?php

require_once 'include/general_page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
?>
		<table class="bordered light" width="100%">
		<tr class="darker"><td width="200">Link</td><td>Description</td></tr>
		
		<tr>
			<td><a href="ws_games.php?help">ws_games.php</a></td>
			<td>
				<p>Returns games logs containg all information about a game: nominations, votings, shooting, checking etc...</p>
				<p>All stats can be calculated by analyzing these logs.</p>
			</td>
		</tr>

		<tr>
			<td><a href="ws_ratings.php?help">ws_ratings.php</a></td>
			<td>
				<p>Returns players global Elo ratings.</p>
				<p><?php echo PRODUCT_NAME; ?> has global ratings and club/event scores. Scores are calculated using configurable standard scoring systems like ФИИМ system, or 3-4-4-5. Every club/event has its own scoring.</p>
				<p>In addition to this <?php echo PRODUCT_NAME; ?> has global ratings. Standard scoring does not work well for inter-club comparison. Because players played different number of games, some of them never meet each other, etc. So we implmented Elo ratings, which is a proven rating sistem in chess, go, and some other games.</p>
			</td>
		</tr>
		
		<tr>
			<td><a href="ws_scores.php?help">ws_scores.php</a></td>
			<td>
				<p>Returns players scores in games/events using configurable scoring systems.</p>
			</td>
		</tr>
		
		<tr>
			<td><a href="ws_player_stats.php?help">ws_player_stats.php</a></td>
			<td>
				<p>Returns player's statistics.</p>
				<p>A number of games played in different roles; shooting/voting/nominating stats; moderating stats; surviving stats; etc.</p>
			</td>
		</tr>
		
		<tr>
			<td><a href="ws_clubs.php?help">ws_clubs.php</a></td>
			<td>
				<p>Returns a list of known clubs.</p>
			</td>
		</tr>

		<tr>
			<td><a href="ws_cities.php?help">ws_cities.php</a></td>
			<td>
				<p>Returns a list of known cities.</p>
				<p>A good place to find city ids.</p>
			</td>
		</tr>

		<tr>
			<td><a href="ws_countries.php?help">ws_countries.php</a></td>
			<td>
				<p>Returns a list of known countries.</p>
				<p>A good place to find country ids.</p>
			</td>
		</tr>
		
<!--		
		<tr>
			<td><a href="ws_adverts.php?help">ws_adverts.php</a></td>
			<td>
				<p>Returns club advertisements.</p>
			</td>
		</tr>
-->
		</table>
<?php
	}
}

$page = new Page();
$page->run(PRODUCT_NAME + ' web services'), PERM_ALL);

?>