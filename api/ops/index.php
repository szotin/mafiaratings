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

<h1>Changing <?php echo PRODUCT_NAME; ?> data</h1>
<p><a href="..">Back to the <?php echo PRODUCT_NAME; ?> API reference</a></p>

<p>These services require authentification. Use <a href="account.php?help">User Account Operations</a> to log in.</p>

<p>Some of the functions require permissions. The required permissions will always be specified in this help pages. Permissions are always related to the operated object.</p>
<p>Possible permissions are:</p>
	<ul>
		<li><em>everyone</em> - do not even have to log in.</li>
		<li><em>user</em> - any user.</li>
		<li><em>object-owner</em> - the user who created the object.</li>
		<li><em>club-member</em> - user should be a member of the club that this object belongs to. </li>
		<li><em>club-official</em> - the club should be the main club of this user. Every user can be a member of many clubs, but user has only one main club. The club that he/she represents.</li>
		<li><em>club-player</em> - a member of the club who is allowed to play in this club. The club might have a manager or moderator without playing permission. He will not be shown in the list of players in the game. He will not be able to use functions that require this permission.</li>
		<li><em>club-moderator</em> - a member of the club who is allowed to moderate games in this club.</li>
		<li><em>club-manager</em> - a manager of the club that this object belongs to.</li>
		<li><em>admin</em> - <?php echo PRODUCT_NAME; ?> administrator.</li>
	</ul>
<p>

<table class="transp" width="100%"><tr><td align="center" colspan="2"><h3>Data model</h3></td></tr><tr>
<td><img src="../../images/GeneralDiagram.png" width="600" usemap="#general_map"></td>
<td align="right"><img src="../../images/ScoringsDiagram.png" width="600" usemap="#scoring_map"></td>
</tr></table>

<map name="general_map">
  <area shape="rect" coords="147,11,239,46" title="Leagues" href="league.php?help">
  <area shape="rect" coords="294,17,352,41" title="Clubs" href="club.php?help">
  <area shape="rect" coords="401,17,459,41" title="Clubs" href="club.php?help">
  <area shape="rect" coords="34,83,157,108" title="Series" href="series.php?help">
  <area shape="rect" coords="245,83,335,108" title="Tournaments" href="tournament.php?help">
  <area shape="rect" coords="401,83,456,108" title="Events" href="event.php?help">
  <area shape="rect" coords="523,83,578,108" title="Games" href="game.php?help">
  <area shape="rect" coords="12,170,81,194" title="Cities" href="city.php?help">
  <area shape="rect" coords="170,170,228,194" title="Clubs" href="club.php?help">
  <area shape="rect" coords="12,238,81,261" title="Countries" href="country.php?help">
  <area shape="rect" coords="165,238,234,261" title="Addresses" href="address.php?help">
</map>

<map name="scoring_map">
  <area shape="rect" coords="12,56,102,90" title="Leagues" href="league.php?help">
  <!-- <area shape="rect" coords="156,12,291,36" title="Rules filters" href="rules_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,44,291,69" title="Scoring filters" href="scoring_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,78,291,103" title="Normalizer filters" href="normalizer_filters.php?help"> -->
  <!-- <area shape="rect" coords="156,112,291,136" title="Gaining filters" href="gaining_filters.php?help"> -->
  <area shape="rect" coords="12,211,68,235" title="Clubs" href="club.php?help">
  <area shape="rect" coords="156,167,291,191" title="Game rules" href="rules.php?help">
  <area shape="rect" coords="156,200,291,225" title="Scoring systems" href="scoring.php?help">
  <area shape="rect" coords="156,233,291,258" title="Scoring Normalizers" href="normalizer.php?help">
  <area shape="rect" coords="156,266,291,291" title="Gaining systems" href="gaining.php?help">
  <area shape="rect" coords="345,167,423,191" title="Games" href="game.php?help">
  <area shape="rect" coords="345,200,423,225" title="Events" href="event.php?help">
  <area shape="rect" coords="381,234,446,258" title="Tournaments" href="tournament.php?help">
  <area shape="rect" coords="345,266,490,291" title="Series" href="series.php?help">
</map>

<table class="bordered light" width="100%">
<tr class="darker"><th width="200">Service</th><th>Link</th><th>Description</th></tr>

<tr>
	<td>User Account Operations</td>
	<td><a href="account.php?help"><?php echo PRODUCT_URL; ?>/api/ops/account.php</a></td>
	<td>
		<p>Creating, and configuring user account in <?php echo PRODUCT_NAME; ?>.</p>
	</td>
</tr>

<tr>
	<td>League Operations</td>
	<td><a href="league.php?help"><?php echo PRODUCT_URL; ?>/api/ops/league.php</a></td>
	<td>
		<p>Manipulating leagues. League unites clubs to a group. League can do series of tournaments, and make a competiotion between clubs and their members in the series.</p> 
	</td>
</tr>

<tr>
	<td>Club Operations</td>
	<td><a href="club.php?help"><?php echo PRODUCT_URL; ?>/api/ops/club.php</a></td>
	<td>
		<p>Club operations: creating, accepting, declining, retiring, restoring, changing.</p>
	</td>
</tr>

<tr>
	<td>Event Operations</td>
	<td><a href="event.php?help"><?php echo PRODUCT_URL; ?>/api/ops/event.php</a></td>
	<td>
		<p>Manipulating events.</p>
	</td>
</tr>

<tr>
	<td>Tournament Operations</td>
	<td><a href="tournament.php?help"><?php echo PRODUCT_URL; ?>/api/ops/tournament.php</a></td>
	<td>
		<p>Manipulating tournaments. Tournament is normally a set of events.</p> 
		<p>For example a Regular Season Championship consists on weekly events. The scoring is a sum of scoring in these events.</p>
		<p>Another example: An Alcatraz tournament consists of 3 events: Main Round, Semi-final and Final. Every event has a scoring weight. The scoring in the tournament is a sum of scoring in every event multiplied by weight.</p>
	</td>
</tr>

<tr>
	<td>Series Operations</td>
	<td><a href="series.php?help"><?php echo PRODUCT_URL; ?>/api/ops/series.php</a></td>
	<td>
		<p>Manipulating series. Series is a set of tournaments.</p> 
		<p>Players gain points by winning places in the tournaments. <a href="gaining.php?help">Gaining systems</a> are used to calculate series points.</p>
	</td>
</tr>

<tr>
	<td>Game Operations</td>
	<td><a href="game.php?help"><?php echo PRODUCT_URL; ?>/api/ops/game.php</a></td>
	<td>
		<p>Working with a game. Synchronization with the client software and standard manipulation deleting/editing/commenting.</p>
	</td>
</tr>

<tr>
	<td>Objection Operations</td>
	<td><a href="objection.php?help"><?php echo PRODUCT_URL; ?>/api/ops/objection.php</a></td>
	<td>
		<p>Manipulating objections. Users can file objections to a game results. Managers of the club and game moderators can manipulate these objections - respond, accept, decline, delete, and edit. They can also file objections on behalf of other users.</p>
	</td>
</tr>

<tr>
	<td>Address Operations</td>
	<td><a href="address.php?help"><?php echo PRODUCT_URL; ?>/api/ops/address.php</a></td>
	<td>
		<p>Manipulating club addresses. Addresses of different locations where a club plays games.</p>
	</td>
</tr>

<tr>
	<td>User Operations</td>
	<td><a href="user.php?help"><?php echo PRODUCT_URL; ?>/api/ops/user.php</a></td>
	<td>
		<p>Banning, and unbanning users and setting user permissions.</p>
	</td>
</tr>

<tr>
	<td>Scoring Operations</td>
	<td><a href="scoring.php?help"><?php echo PRODUCT_URL; ?>/api/ops/scoring.php</a></td>
	<td>
		<p>Creating, editing, deleting, and manipulating scoring systems. Scoring system is a set of scoring rules that are used to calculate points in tournaments and events. Examples of scoring systems are: Очковая система ФИИМ and 3-4-4-5.</p>
	</td>
</tr>

<tr>
	<td>Scoring Normalizer Operations</td>
	<td><a href="normalizer.php?help"><?php echo PRODUCT_URL; ?>/api/ops/normalizer.php</a></td>
	<td>
		<p>Scoring normalizers are used in the tournaments where players play different number of games. They normalize the result because otherwise a player who played more games wins no matter how good he/she is.</p><p>The simplest normalizer would divide player's score to the number of games played. This is not the best one because it gives the advantage to a player who played only one game and won it. Thus more complicated rules can be created to make normalization as fair as possible.</p>
	</td>
</tr>

<tr>
	<td>Gaining Operations</td>
	<td><a href="gaining.php?help"><?php echo PRODUCT_URL; ?>/api/ops/gaining.php</a></td>
	<td>
		<p>Creating, editing, deleting, and manipulating gaining systems. Gaining system is a set of rules that are used to calculate points in series of tournaments. When users take a certain place in a tournament they gain a certain number of points. Gaining systems are used in series the same way as scoring systems are used in tournaments. The difference is that in a tournament users earn points in each game they play; though in series users earn points by winning a place in each tournament they play.</p>
	</td>
</tr>

<tr>
	<td>Rules Operations</td>
	<td><a href="rules.php?help"><?php echo PRODUCT_URL; ?>/api/ops/rules.php</a></td>
	<td>
		<p>Creating, editing, deleting, and manipulating game rules. All clubs use slighly different rules. The best example is killing 2 when 4 players left. Some clubs allow it, some do not. These operations are configuring rules for a club. Club can have many rule sets and use different rules for different events.</p>
	</td>
</tr>

<tr>
	<td>Advertisement Operations</td>
	<td><a href="advert.php?help"><?php echo PRODUCT_URL; ?>/api/ops/advert.php</a></td>
	<td>
		<p>Advertizements are published club announcements created by club manager to notify club members about something. For example, a manager can create advert: Christmas break, no games until January 12. This message will be shown in the main club page on <?php echo PRODUCT_NAME; ?></p>
	</td>
</tr>

<tr>
	<td>Club Notes Operations</td>
	<td><a href="note.php?help"><?php echo PRODUCT_URL; ?>/api/ops/note.php</a></td>
	<td>
		<p>Notes are displayed in the main club page. They provide the brief information about the club. Managers can create, delete, edit, and move them up and down to provide the best presentation of the current club state.</p>
	</td>
</tr>

<tr>
	<td>Video Operations</td>
	<td><a href="video.php?help"><?php echo PRODUCT_URL; ?>/api/ops/video.php</a></td>
	<td>
		<p>Assigning youtube videos to games, clubs, and events on <?php echo PRODUCT_NAME; ?>.</p>
	</td>
</tr>

<tr>
	<td>Photo Album Operations</td>
	<td><a href="album.php?help"><?php echo PRODUCT_URL; ?>/api/ops/album.php</a></td>
	<td>
		<p>Manipulating photo albums.</p>
	</td>
</tr>

<tr>
	<td>Single Photo Operations</td>
	<td><a href="photo.php?help"><?php echo PRODUCT_URL; ?>/api/ops/photo.php</a></td>
	<td>
		<p>Manipulating photos.</p>
	</td>
</tr>

<tr>
	<td>Country Operations</td>
	<td><a href="country.php?help"><?php echo PRODUCT_URL; ?>/api/ops/country.php</a></td>
	<td>
		<p>Manipulating countries.</p>
	</td>
</tr>

<tr>
	<td>City Operations</td>
	<td><a href="city.php?help"><?php echo PRODUCT_URL; ?>/api/ops/city.php</a></td>
	<td>
		<p>Manipulating cities.</p>
	</td>
</tr>

<tr>
	<td>Game Sounds Operations</td>
	<td><a href="sound.php?help"><?php echo PRODUCT_URL; ?>/api/ops/sound.php</a></td>
	<td>
		<p>Manipulating prompt sounds that are played in the game. Uploading custom sounds, setting sounds for a club or for a user.</p>
	</td>
</tr>

</table>


</body></html>