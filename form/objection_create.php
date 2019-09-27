<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['game_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = (int)$_REQUEST['game_id'];
	
	dialog_title(get_label('File an objection to the game [0] results.', $game_id));
	
	check_permissions(PERMISSION_USER);
	
	list ($club_id, $moderator_id) = Db::record(get_label('game'), 'SELECT club_id, moderator_id FROM games WHERE id = ?', $game_id);
	$can_edit = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $moderator_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="100" valign="top">' . get_label('Reason').':</td><td><textarea id="form-message" cols="62" rows="8"></textarea></td></tr>';
	if ($can_edit)
	{
		echo '<tr><td>' . get_label('On behalf of') . ':</td><td>';
		show_user_input('form-user', $_profile->user_name, '', get_label('Select user.'), 'onSelectUser');
		echo '</td></tr>';
	}
	echo '</table>';
	
	
?>	
	<script>
	var userId = <?php echo $_profile->user_id; ?>;
	
	function onSelectUser(_user)
	{
		if (typeof _user.id == "number")
		{
			userId = _user.id;
		}
		else
		{
			userId = <?php echo $_profile->user_id; ?>;
		}
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/objection.php",
		{
			op: "create"
			, game_id: <?php echo $game_id; ?>
			, user_id: userId
			, message: $("#form-message").val()
		},
		onSuccess);
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