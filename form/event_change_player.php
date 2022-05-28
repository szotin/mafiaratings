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
	
	list ($event_name, $event_flags, $tour_id, $tour_name, $tour_flags, $club_id, $club_name, $club_flags) = 
		Db::record(get_label('event'), 'SELECT e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags FROM events e JOIN clubs c ON c.id = e.club_id LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE e.id = ?', $event_id);
	if ($user_id > 0)
	{
		list ($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $user_id);
	}
	else
	{
		$user_name = '';
	}
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);
	
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
	$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
	$event_pic->
		set($event_id, $event_name, $event_flags)->
		set($tour_id, $tour_name, $tour_flags)->
		set($club_id, $club_name, $club_flags);
	$event_pic->show(ICONS_DIR, false, 50);
	echo '</td><td>' . $event_name . '</td></tr></table></td></tr>';
	
	echo '<tr><td>' . get_label('Change nickname in this event to') . ':</td><td><input id="form-nick" value="' . $nickname . '">';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Replace [0] in this event with', $user_name) . ':</td><td><table class="transp" width="100%"><tr><td>';
	show_user_input('form-user', $user_name, '', get_label('Select user.'), 'onSelect');
	echo '</td><td align="right"><div id="form-del"></div></td></tr></table></td></tr>';
	
	echo '</table>';
	
	
?>	
	<script>
	var newUserId = <?php echo $user_id; ?>;
	
	function onSelect(_user)
	{
		if (typeof _user.id == "number")
		{
			newUserId = _user.id;
			$("#form-nick").val(_user.name);
		}
		else
		{
			newUserId = <?php echo $user_id; ?>;
		}
	}
	
	function commit(onSuccess)
	{
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