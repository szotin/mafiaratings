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

<table class="transp" width="100%"><tr><td align="center" colspan="2"><h3>Data model</h3></td></tr><tr>
<td><img src="../../images/GeneralDiagram.png" width="600" usemap="#general_map"></td>
<td align="right"><img src="../../images/ScoringsDiagram.png" width="600" usemap="#scoring_map"></td>
</tr></table>

<map name="general_map">
  <area shape="rect" coords="147,11,239,46" title="Leagues" href="leagues.php?help">
  <area shape="rect" coords="294,17,352,41" title="Clubs" href="clubs.php?help">
  <area shape="rect" coords="401,17,459,41" title="Clubs" href="clubs.php?help">
  <area shape="rect" coords="34,83,157,108" title="Series" href="series.php?help">
  <area shape="rect" coords="245,83,335,108" title="Tournaments" href="tournaments.php?help">
  <area shape="rect" coords="401,83,456,108" title="Events" href="events.php?help">
  <area shape="rect" coords="523,83,578,108" title="Games" href="games.php?help">
  <area shape="rect" coords="12,170,81,194" title="Cities" href="cities.php?help">
  <area shape="rect" coords="170,170,228,194" title="Clubs" href="clubs.php?help">
  <area shape="rect" coords="12,238,81,261" title="Countries" href="countries.php?help">
  <!-- <area shape="rect" coords="165,238,234,261" title="Addresses" href="addresses.php?help"> -->
</map>

<map name="scoring_map">
  <area shape="rect" coords="12,56,102,90" title="Leagues" href="leagues.php?help">
  <!-- <area shape="rect" coords="156,12,291,36" title="Rules filters" href="rules_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,44,291,69" title="Scoring filters" href="scoring_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,78,291,103" title="Normalizer filters" href="normalizer_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,112,291,136" title="Gaining filters" href="gaining_filters.php?help"> -->
  <area shape="rect" coords="12,211,68,235" title="Clubs" href="clubs.php?help">
  <area shape="rect" coords="156,167,291,191" title="Game rules" href="rules.php?help">
  <area shape="rect" coords="156,200,291,225" title="Scoring systems" href="scorings.php?help">
  <area shape="rect" coords="156,233,291,258" title="Scoring Normalizers" href="normalizers.php?help">
  <area shape="rect" coords="156,266,291,291" title="Gaining systems" href="gainings.php?help">
  <area shape="rect" coords="345,167,423,191" title="Games" href="games.php?help">
  <area shape="rect" coords="345,200,423,225" title="Events" href="events.php?help">
  <area shape="rect" coords="381,234,446,258" title="Tournaments" href="tournaments.php?help">
  <area shape="rect" coords="345,266,490,291" title="Series" href="series.php?help">
</map>


<table class="bordered light" width="100%">
<tr class="darker"><th width="200">Service</th><th>Link</th><th>Description</th></tr>

<tr>
	<td>Games</td>
	<td><a href="games.php?help"><?php echo PRODUCT_URL; ?>/api/get/games.php</a></td>
	<td>
		<p>Returns games logs containg all information about a game: nominations, votings, shooting, checking etc...</p>
		<p>All stats can be calculated by analyzing these logs.</p>
	</td>
</tr>

<tr>
	<td>Events</td>
	<td><a href="events.php?help"><?php echo PRODUCT_URL; ?>/api/get/events.php</a></td>
	<td>
		<p>Returns a list of past, current and future events.</p>
	</td>
</tr>

<tr>
	<td>Tournaments</td>
	<td><a href="tournaments.php?help"><?php echo PRODUCT_URL; ?>/api/get/tournaments.php</a></td>
	<td>
		<p>Returns a list of past, current and future tournaments.</p>
	</td>
</tr>

<tr>
	<td>Series</td>
	<td><a href="series.php?help"><?php echo PRODUCT_URL; ?>/api/get/series.php</a></td>
	<td>
		<p>Returns a list of past, current and future series.</p>
	</td>
</tr>

<tr>
	<td>Clubs</td>
	<td><a href="clubs.php?help"><?php echo PRODUCT_URL; ?>/api/get/clubs.php</a></td>
	<td>
		<p>Returns a list of known clubs.</p>
	</td>
</tr>

<tr>
	<td>Leagues</td>
	<td><a href="leagues.php?help"><?php echo PRODUCT_URL; ?>/api/get/leagues.php</a></td>
	<td>
		<p>Returns a list of known leagues.</p>
	</td>
</tr>

<tr>
	<td>Ratings</td>
	<td><a href="ratings.php?help"><?php echo PRODUCT_URL; ?>/api/get/ratings.php</a></td>
	<td>
		<p>Returns players global Elo ratings.</p>
		<p><?php echo PRODUCT_NAME; ?> has global ratings and club/event scores. Scores are calculated using configurable standard scoring systems like ФИИМ system, or 3-4-4-5. Every club/event has its own scoring.</p>
		<p>In addition to this <?php echo PRODUCT_NAME; ?> has global ratings. Standard scoring does not work well for inter-club comparison. Because players played different number of games, some of them never meet each other, etc. So we implmented Elo ratings, which is a proven rating sistem in chess, go, and some other games.</p>
	</td>
</tr>

<tr>
	<td>Scores</td>
	<td><a href="scores.php?help"><?php echo PRODUCT_URL; ?>/api/get/scores.php</a></td>
	<td>
		<p>Returns players scores in games/events using configurable scoring systems.</p>
	</td>
</tr>

<tr>
	<td>Rules</td>
	<td><a href="rules.php?help"><?php echo PRODUCT_URL; ?>/api/get/rules.php</a></td>
	<td>
		<p>Returns game rules descriptions for different clubs, events, tournaments.</p>
	</td>
</tr>

<tr>
	<td>Club Rules</td>
	<td><a href="club_rules.php?help"><?php echo PRODUCT_URL; ?>/api/get/club_rules.php</a></td>
	<td>
		<p>Returns the list of custom game rules used in a specific club.</p>
	</td>
</tr>

<tr>
	<td>Player Statistics</td>
	<td><a href="player_stats.php?help"><?php echo PRODUCT_URL; ?>/api/get/player_stats.php</a></td>
	<td>
		<p>Returns player's statistics.</p>
		<p>A number of games played in different roles; shooting/voting/nominating stats; moderating stats; surviving stats; etc.</p>
	</td>
</tr>

<tr>
	<td>Cities</td>
	<td><a href="cities.php?help"><?php echo PRODUCT_URL; ?>/api/get/cities.php</a></td>
	<td>
		<p>Returns a list of known cities.</p>
		<p>A good place to find city ids.</p>
	</td>
</tr>

<tr>
	<td>Countries</td>
	<td><a href="countries.php?help"><?php echo PRODUCT_URL; ?>/api/get/countries.php</a></td>
	<td>
		<p>Returns a list of known countries.</p>
		<p>A good place to find country ids.</p>
	</td>
</tr>

<tr>
	<td>Club Advertisements</td>
	<td><a href="adverts.php?help"><?php echo PRODUCT_URL; ?>/api/get/adverts.php</a></td>
	<td>
		<p>Returns club advertisements.</p>
	</td>
</tr>

<tr>
	<td>Scoring systems</td>
	<td><a href="scorings.php?help"><?php echo PRODUCT_URL; ?>/api/get/scorings.php</a></td>
	<td>
		<p>Scoring systems. The configurable rules for calculating tournament scores.</p>
	</td>
</tr>

<tr>
	<td>Scoring normalizers</td>
	<td><a href="normalizers.php?help"><?php echo PRODUCT_URL; ?>/api/get/normalizers.php</a></td>
	<td>
		<p>Scoring normalizers. The configurable rules for normalizing tournament scores.</p><p>It is used in a long term tournaments where players play a significantly different number of games. In this case we don't want a user who plays more to win just because they played more games than others. So we use normalization. The simplest (but not the fairest) is to use average score per game. Normalizers can be way more complicated. Mafia Ratings lets users to configure custom normalizers and use whatever they think is the best way to normalize scores.</p>
	</td>
</tr>

<tr>
	<td>Gaining systems</td>
	<td><a href="gainings.php?help"><?php echo PRODUCT_URL; ?>/api/get/gainings.php</a></td>
	<td>
		<p>Gaining systems. The configurable rules for calculating series scores. Scoring systems are applied to the tournaments. They calculate scores based on the tournament games. Gaining system is applied to series of tournaments. They calculate scores based on the results of players in the participating tournaments.</p>
	</td>
</tr>

<tr>
	<td>Gaining points</td>
	<td><a href="gaining_points.php?help"><?php echo PRODUCT_URL; ?>/api/get/gaining_points.php</a></td>
	<td>
		<p>Gaining points. Returns gaining points for a tournament with a certain number of players and stars.</p>
	</td>
</tr>

<tr>
	<td>Current Game</td>
	<td><a href="current_game.php?help"><?php echo PRODUCT_URL; ?>/api/get/current_game.php</a></td>
	<td>
		<p>Shows current game status for OBS integration.</p>
		<p>This request allows to create pages that can be embedded to OBS Studio to show current game status - who is playing; roles; who is speaking; warnings; current nominees; current stage of the game; etc..</p>
	</td>
</tr>

</table>

</body></html>