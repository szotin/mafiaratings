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
		<p>Manipulating leagues. League unites clubs to a group. League can do tournaments, unite them to a season, and make a competiotion between clubs and their members.</p> 
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
	<td>Season Operations</td>
	<td><a href="season.php?help"><?php echo PRODUCT_URL; ?>/api/ops/season.php</a></td>
	<td>
		<p>Creating, editing, and deleting seasons in a club. Season is a time interval used in a club to separate long term competitions. All stats can be viewed per configurable season instead of all-time stats.</p>
	</td>
</tr>

<tr>
	<td>Scoring Operations</td>
	<td><a href="scoring.php?help"><?php echo PRODUCT_URL; ?>/api/ops/scoring.php</a></td>
	<td>
		<p>Creating, editing, deleting, and manipulating scoring systems. Scoring system is a set of scoring rules that are used to calculate points in competitions. Examples of scoring systems are: Очковая система ФИИМ and 3-4-4-5.</p>
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
	<td>Photo Operations</td>
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

</table>


</body></html>