<?php

require_once '../include/session.php';
require_once '../include/event.php';
require_once '../include/user.php';

initiate_session();

try
{
	dialog_title(get_label('Replace player'));

	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	
	list ($event_name, $event_flags, $club_id, $club_name, $club_flags) = Db::record(get_label('event'), 'SELECT e.name, e.flags, c.id, c.name, c.flags FROM events e JOIN clubs c ON c.id = e.club_id WHERE e.id = ?', $event_id);
	if ($user_id > 0)
	{
		list ($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $user_id);
	}
	else
	{
		$user_name = '';
	}
	
	if (isset($_REQUEST['nick']))
	{
		$nickname = $_REQUEST['nick'];
	}
	else
	{
		$nickname = $user_name;
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="240">' . get_label('Event') . ':</td><td><table class="transp" width="100%"><tr><td width="60">';
	show_event_pic($event_id, $event_name, $event_flags, $club_id, $club_name, $club_flags, ICONS_DIR, 50);
	echo '</td><td>' . $event_name . '</td></tr></table></td></tr>';
	
	// echo '<tr><td>' . get_label('User') . ':</td><td width="60">';
	// if ($user_id > 0)
	// {
		// show_user_pic($user_id, $user_name, $user_flags, ICONS_DIR, 50, 50);
	// }
	// else
	// {
		// echo '<img src="images/create_user.png" width="50">';
	// }
	// echo '</td><td>' . $user_name . '</td></tr>';
	
	echo '<tr><td>' . get_label('Change nickname in this event to') . ':</td><td><input id="form-nick" value="' . $nickname . '">';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Replace [0] in this event with', $user_name) . ':</td><td><table class="transp" width="100%"><tr><td>';
	show_user_input('form-user', $user_name, '', get_label('Select user.'), 'onSelect');
	echo '</td><td align="right"><div id="form-del"></div></td></tr></table></td></tr>';
	
	echo '</table>';
	
	
?>	
	<script>
	// todo: remove in the bright future when problems with incomers and incomer_suspects will be solved
	var INCOMERS_IMPLEMENTATION_COMPLETE = false;
	
	var newUserId = <?php echo $user_id; ?>;
	var deleteButton = '<button class="icon" onclick="remove()"><img src="images/delete.png" title="<?php echo get_label('Replace [0] with a temporaty player who has no account.', $user_name); ?>"></button>';
	setEnables();
	
	function setEnables()
	{
		if (newUserId > 0 && INCOMERS_IMPLEMENTATION_COMPLETE)
		{
			$("#form-del").html(deleteButton);
		}
		else
		{
			$("#form-del").html('');
			$('#form-user').val('');
		}
	}
	
	function onSelect(_user)
	{
		if (typeof _user.id == "number")
		{
			newUserId = _user.id;
		}
		else
		{
			newUserId = <?php echo $user_id; ?>;
		}
		setEnables();
	}
	
	function remove()
	{
		newUserId = 0;
		setEnables();
	}
	
	function commit(onSuccess)
	{
		console.log(newUserId);
		json.post("api/ops/event.php",
		{
			op: "change_player"
			, event_id: <?php echo $event_id; ?>
			, user_id: <?php echo $user_id; ?>
			, new_user_id: newUserId
			, nick: $("#form-nick").val()
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