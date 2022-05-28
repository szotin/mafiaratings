<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/user.php';
require_once '../include/picture.php';

initiate_session();

try
{
	if (isset($_REQUEST['game_id']))
	{
		$game_id = (int)$_REQUEST['game_id'];
		$parent_id = 0;
		list ($club_id, $event_id, $tournament_id, $owner_id) = Db::record(get_label('game'), 'SELECT club_id, event_id, tournament_id, user_id FROM games WHERE id = ?', $game_id);
	}
	else if (isset($_REQUEST['parent_id']))
	{
		$parent_id = (int)$_REQUEST['parent_id'];
		list ($game_id, $club_id, $event_id, $tournament_id, $owner_id, $parent_user_id, $parent_user_name, $parent_user_flags, $parent_message) = 
			Db::record(get_label('game'), 
				'SELECT g.id, g.club_id, g.event_id, g.tournament_id, g.user_id, u.id, u.name, u.flags, o.message FROM objections o' .
				' JOIN games g ON g.id = o.game_id' .
				' JOIN users u ON u.id = o.user_id' .
				' WHERE o.id = ?', $parent_id);
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('game')));
	}
	
	if ($parent_id <= 0)
	{
		check_permissions(PERMISSION_USER);
		$can_edit = is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id);
		dialog_title(get_label('File an objection to the game [0] results.', $game_id));
	}
	else
	{
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id);
		$can_edit = true;
		dialog_title(get_label('Respond to the objection [0].', $parent_id));
		
		$parent_message = stripslashes($parent_message);
		$parent_message = htmlspecialchars($parent_message, ENT_QUOTES, "UTF-8");
		$parent_message = replace_returns($parent_message);
		
		echo '<p><table class="dialog_form" width="100%">';
		echo '<tr><td width="60" valign="top">';
		$user_pic = new Picture(USER_PICTURE);
		$user_pic->set($parent_user_id, $parent_user_name, $parent_user_flags);
		$user_pic->show(ICONS_DIR, false, 48);
		echo '<br>' . $parent_user_name . '</td><td valign="top">' . $parent_message . '</td></tr>';
		echo '</table></p>';
		
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="100" valign="top">' . get_label('Reason').':</td><td><textarea id="form-message" cols="62" rows="8"></textarea></td></tr>';
	if ($can_edit)
	{
		echo '<tr><td>' . get_label('On behalf of') . ':</td><td>';
		show_user_input('form-user', $_profile->user_name, 'event=' . $event_id, get_label('Select user.'), 'onSelectUser');
		echo '</td></tr>';
		
		echo '<tr><td colspan="2">';
		echo '<input type="radio" name="accept" value="0" checked/> ' . get_label('postpone decision');
		echo ' <input type="radio" id="form-decline" name="accept" value="-1"/> ' . get_label('decline objection');
		echo ' <input type="radio" id="form-accept" name="accept" value="1"/> ' . get_label('accept objection');
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
		var accept = 0;
		if ($("#form-accept").attr("checked"))
		{
			accept = 1;
		}
		else if ($("#form-decline").attr("checked"))
		{
			accept = -1;
		}
		
		json.post("api/ops/objection.php",
		{
			op: "create"
			, game_id: <?php echo $game_id; ?>
			, parent_id: <?php echo $parent_id; ?>
			, user_id: userId
			, message: $("#form-message").val()
			, accept: accept
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