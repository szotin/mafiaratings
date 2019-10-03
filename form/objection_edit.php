<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['objection_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('objection')));
	}
	$objection_id = (int)$_REQUEST['objection_id'];
	list ($objection_id, $game_id, $message, $user_id, $user_name, $club_id, $moderator_id, $parent_id, $accept) = 
		Db::record(get_label('objection'), 
			'SELECT o.id, o.game_id, o.message, u.id, u.name, g.club_id, g.moderator_id, o.objection_id, o.accept FROM objections o' .
			' JOIN users u ON u.id = o.user_id' .
			' JOIN games g ON g.id = o.game_id' .
			' WHERE o.id = ?', $objection_id);
	
	dialog_title(get_label('Edit objection [0] to the game [1] results.', $objection_id, $game_id));
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $moderator_id);
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="100" valign="top">' . get_label('Reason').':</td><td><textarea id="form-message" cols="62" rows="8">' . $message . '</textarea></td></tr>';
	
	echo '<tr><td>' . get_label('On behalf of') . ':</td><td>';
	show_user_input('form-user', $user_name, '', get_label('Select user.'), 'onSelectUser');
	echo '</td></tr>';
	
	echo '<tr><td colspan="2">';
	echo '<input type="radio" name="accept" value="0"';
	if ($accept == 0)
	{
		echo ' checked';
	}
	echo '/> ' . get_label('postpone decision');
	echo ' <input type="radio" id="form-decline" name="accept" value="-1"';
	if ($accept < 0)
	{
		echo ' checked';
	}
	echo '/> ' . get_label('decline objection');
	echo ' <input type="radio" id="form-accept" name="accept" value="1"';
	if ($accept > 0)
	{
		echo ' checked';
	}
	echo '/> ' . get_label('accept objection');
	echo '</td></tr>';
		
	echo '</table>';
	
	
?>	
	<script>
	var userId = <?php echo $user_id; ?>;
	
	function onSelectUser(_user)
	{
		console.log(_user);
		if (typeof _user.id == "number")
		{
			userId = _user.id;
		}
		else
		{
			userId = <?php echo $user_id; ?>;
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
			op: "change"
			, objection_id: <?php echo $objection_id; ?>
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