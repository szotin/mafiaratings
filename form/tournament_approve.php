<?php

require_once '../include/session.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/tournament.php';
require_once '../include/timespan.php';
require_once '../include/scoring.php';

initiate_session();

try
{
	dialog_title(get_label('Approve tournament'));
	
	if (!isset($_REQUEST['league_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	
	list($league_name, $league_flags) = Db::record(get_label('league'), 'SELECT name, flags FROM leagues WHERE id = ?', $league_id);
	list($tournament_name, $tournament_flags, $stars, $tournament_league_id, $club_id, $club_name, $club_flags) = Db::record(get_label('tournament'), 'SELECT t.name, t.flags, t.stars, t.request_league_id, c.id, c.name, c.flags FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $tournament_id);
	if ($tournament_league_id != $league_id)
	{
		throw new Exc(get_label("There is no request to hold the tournament [0] in [1]", $tournament_name, $league_name));
	}
	
	$query = new DbQuery('SELECT stars FROM tournament_approves WHERE user_id = ? AND tournament_id = ? AND league_id = ?', $_profile->user_id, $tournament_id, $league_id);
	if ($row = $query->next())
	{
		list($stars) = $row;
	}
	$approved = ($stars >= 0);
	$stars = min(max($stars, 0), 5);
	
	echo '<table class="dialog_form" width="100%">';
//	echo '<tr><td width="160">' . get_label('Tournament name') . ':</td><td><input id="form-name" value=""></td></tr>';

	$league_pic = new Picture(LEAGUE_PICTURE);
	$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
	$user_pic = new Picture(USER_PICTURE);
	
	echo '<tr><td colspan="2"><table class="transp" width="100%"><tr><td width="' . (ICON_WIDTH * 2) . '">';
	$league_pic->set($league_id, $league_name, $league_flags);
	$league_pic->show(ICONS_DIR);
	$tournament_pic->
		set($tournament_id, $tournament_name, $tournament_flags)->
		set($club_id, $club_name, $club_flags);
	$tournament_pic->show(ICONS_DIR);
	echo '</td><td align="center">' . get_label('Please approve the request to hold [0] in [1]', $tournament_name, $league_name) . '</td></tr></table></td></tr>';
	
	
	echo '<tr><td width="120">' . $tournament_name . ':</td><td><input id="form-approve" type="checkbox"';
	if ($approved)
	{
		echo ' checked';
	}
	echo ' onclick="approveClicked()"> ' . get_label('approve') . '<br><div id="form-stars" class="stars"></div></td></tr>';
	
	$has_approvals = false;
	$query = new DbQuery('SELECT u.id, u.name, u.flags, a.stars FROM tournament_approves a JOIN users u ON u.id = a.user_id WHERE a.tournament_id = ? AND a.league_id = ? ORDER BY u.name', $tournament_id, $league_id);
	while ($row = $query->next())
	{
		list($user_id, $user_name, $user_flags, $user_stars) = $row;
		if (!$has_approvals)
		{
			echo '<tr><td valign="top">' . get_label('Existing approvals') . ':</td><td><table class="transp" width="100%">';
			$has_approvals = true;
		}
		
		echo '<tr><td width="36">';
		$user_pic->set($user_id, $user_name, $user_flags);
		$user_pic->show(ICONS_DIR, 32);
		echo '</td><td>';
		if ($user_stars >= 0)
		{
			echo get_label('[0] approved the tournament with [1] stars.', $user_name, $user_stars);
		}
		else
		{
			echo get_label('[0] denied the tournament.', $user_name);
		}
		echo '</td></tr>';
	}
	if ($has_approvals)
	{
		echo '</table></td></tr>';
	}
	
	echo '</table>';
?>	

	<script type="text/javascript" src="js/rater.min.js"></script>
	<script>
	
	$("#form-stars").rate(
	{
		max_value: 5,
		step_size: 0.5,
		initial_value: <?php echo $stars; ?>,
	});
	
	approveClicked();
	
	function approveClicked()
	{
		if ($('#form-approve').attr('checked'))
		{
			$("#form-stars").show();
		}
		else
		{
			$("#form-stars").hide();
		}
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/tournament.php", 
		{
			op: "approve",
			tournament_id: <?php echo $tournament_id; ?>,
			league_id: <?php echo $league_id; ?>,
			stars: $("#form-stars").rate("getValue"),
			approve: $('#form-approve').attr('checked') ? 1 : 0,
		}, onSuccess);
	}
	
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>