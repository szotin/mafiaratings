<?php
require_once '../../include/api.php';
?>

<!DOCTYPE HTML>
<html>
<head>
<title><?php echo PRODUCT_NAME; ?> API reference</title>
<META content="text/html; charset=utf-8" http-equiv=Content-Type>
<link rel="stylesheet" href="../api.css" type="text/css" media="screen" />
</head><body>

<h1>Getting data from <?php echo PRODUCT_NAME; ?></h1>
<p><a href="..">Back to the <?php echo PRODUCT_NAME; ?> API reference</a></p>
<p>These services do not require authentification. <?php echo PRODUCT_NAME; ?> is fully accessible for reading to everyone.</p>

<table class="bordered light" width="100%">
<tr class="darker"><th width="200">Service</th><th>Link</th><th>Description</th></tr>

<tr>
	<td>Get Games</td>
	<td><a href="games.php?help"><?php echo PRODUCT_URL; ?>/api/get/games.php</a></td>
	<td>
		<p>Returns games logs containg all information about a game: nominations, votings, shooting, checking etc...</p>
		<p>All stats can be calculated by analyzing these logs.</p>
	</td>
</tr>

<tr>
	<td>Get Ratings</td>
	<td><a href="ratings.php?help"><?php echo PRODUCT_URL; ?>/api/get/ratings.php</a></td>
	<td>
		<p>Returns players global Elo ratings.</p>
		<p><?php echo PRODUCT_NAME; ?> has global ratings and club/event scores. Scores are calculated using configurable standard scoring systems like ФИИМ system, or 3-4-4-5. Every club/event has its own scoring.</p>
		<p>In addition to this <?php echo PRODUCT_NAME; ?> has global ratings. Standard scoring does not work well for inter-club comparison. Because players played different number of games, some of them never meet each other, etc. So we implmented Elo ratings, which is a proven rating sistem in chess, go, and some other games.</p>
	</td>
</tr>

<tr>
	<td>Get Scores</td>
	<td><a href="scores.php?help"><?php echo PRODUCT_URL; ?>/api/get/scores.php</a></td>
	<td>
		<p>Returns players scores in games/events using configurable scoring systems.</p>
	</td>
</tr>

<tr>
	<td>Get Player Statistics</td>
	<td><a href="player_stats.php?help"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php</a></td>
	<td>
		<p>Returns player's statistics.</p>
		<p>A number of games played in different roles; shooting/voting/nominating stats; moderating stats; surviving stats; etc.</p>
	</td>
</tr>

<tr>
	<td>Get Clubs</td>
	<td><a href="clubs.php?help"><?php echo PRODUCT_URL; ?>/api/get/clubs.php</a></td>
	<td>
		<p>Returns a list of known clubs.</p>
	</td>
</tr>

<tr>
	<td>Get Cities</td>
	<td><a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a></td>
	<td>
		<p>Returns a list of known cities.</p>
		<p>A good place to find city ids.</p>
	</td>
</tr>

<tr>
	<td>Get Countries</td>
	<td><a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a></td>
	<td>
		<p>Returns a list of known countries.</p>
		<p>A good place to find country ids.</p>
	</td>
</tr>

<tr>
	<td>Get Club Advertisements</td>
	<td><a href="adverts.php?help"><?php echo PRODUCT_URL; ?>/api/get/adverts.php</a></td>
	<td>
		<p>Returns club advertisements.</p>
	</td>
</tr>

</table>

</body></html>