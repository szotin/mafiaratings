<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/security.php';

initiate_session();

try
{
	$club_id = 0;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list($club_id, $name) = Db::record(get_label('event'), 'SELECT club_id, name FROM events WHERE id = ?', $event_id);
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		list($club_id, $name) = Db::record(get_label('tournament'), 'SELECT club_id, name FROM tournaments WHERE id = ?', $tournament_id);
	}
	else if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
	}
	
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
	
	$club = $_profile->clubs[$club_id];

	if (isset($event_id) || isset($tournament_id))
	{
		dialog_title(get_label('Add new participant to [0]', $name));
	}
	else 
	{
		dialog_title(get_label('Add new member to [0]', $club->name));
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="120">' . get_label('User') . ':</td><td>';
	show_user_input('form-user', '', '', get_label('Select user.'), 'onSelect');
	echo '</td></tr>';
	echo '</table>';

?>
	<script>
	var user = null;
	function onSelect(_user)
	{
		user = _user;
	}
	
	function commit(onSuccess)
	{
		if (user != null)
		{
			json.post("api/ops/user.php",
			{
<?php	
		
	if (isset($event_id))
	{
?>
				op: "join_event"
				, user_id: user.id
				, event_id: <?php echo $event_id; ?>
<?php
	}
	else if (isset($tournament_id))
	{
?>
				op: "join_tournament"
				, user_id: user.id
				, tournament_id: <?php echo $tournament_id; ?>
<?php
	}
	else
	{
?>
				op: "join_club"
				, user_id: user.id
				, club_id: <?php echo $club_id; ?>
<?php
	}
?>
			},
			onSuccess);
		}
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