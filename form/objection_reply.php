<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/user.php';
require_once '../include/picture.php';

initiate_session();

try
{
	if (!isset($_REQUEST['objection_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('objection')));
	}
	$objection_id = (int)$_REQUEST['objection_id'];
	list ($objection_id, $game_id, $message, $user_id, $user_name, $user_flags, $club_id, $moderator_id) = 
		Db::record(get_label('objection'), 
			'SELECT o.id, o.game_id, o.message, u.id, u.name, u.flags, g.club_id, g.moderator_id FROM objections o' .
			' JOIN users u ON u.id = o.user_id' .
			' JOIN games g ON g.id = o.game_id' .
			' WHERE o.id = ? AND o.objection_id IS NULL', $objection_id);
	
	dialog_title(get_label('Respond to the objection [0].', $objection_id));
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $moderator_id);
	
	$message = stripslashes($message);
	$message = htmlspecialchars($message, ENT_QUOTES, "UTF-8");
	$message = replace_returns($message);
	
	echo '<p><table class="dialog_form" width="100%">';
	echo '<tr><td width="60" valign="top">';
	$user_pic = new Picture(USER_PICTURE);
	$user_pic->set($user_id, $user_name, $user_flags);
	$user_pic->show(ICONS_DIR, 48);
	echo '<br>' . $user_name . '</td><td valign="top">' . $message . '</td></tr>';
	echo '</table></p>';
	
	
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="100" valign="top">' . get_label('Responce').':</td><td><textarea id="form-message" cols="62" rows="8"></textarea></td></tr>';
	
	echo '<tr><td>' . get_label('On behalf of') . ':</td><td>';
	show_user_input('form-user', $_profile->user_name, '', get_label('Select user.'), 'onSelectUser');
	echo '</td></tr>';
	
	echo '<tr><td colspan="2"><input type="checkbox" id="form-accept"> ' . get_label(' accept the objection and cancel the game.') . '</td></tr>';
	
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
			op: "reply"
			, objection_id: <?php echo $objection_id; ?>
			, user_id: userId
			, message: $("#form-message").val()
			, accept: ($('#form-accept').attr('checked') ? 1 : 0)
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