<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	dialog_title(get_label('Replace player'));

	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	
	list ($tournament_name, $tournament_flags, $club_id, $club_name, $club_flags) = 
		Db::record(get_label('tournament'), 'SELECT t.name, t.flags, c.id, c.name, c.flags FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $tournament_id);
	if ($user_id > 0)
	{
		list ($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $user_id);
	}
	else
	{
		$user_name = '';
	}
	check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	
	if (isset($_REQUEST['nick']))
	{
		$nickname = $_REQUEST['nick'];
	}
	else
	{
		$nickname = $user_name;
	}
	
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td width="240">' . get_label('Tournament') . ':</td><td><table class="transp" width="100%"><tr><td width="60">';
	$tournament_pic = new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE));
	$tournament_pic->
		set($tournament_id, $tournament_name, $tournament_flags)->
		set($club_id, $club_name, $club_flags);
	$tournament_pic->show(ICONS_DIR, false, 50);
	echo '</td><td>' . $tournament_name . '</td></tr></table></td></tr>';
	
	echo '<tr><td>' . get_label('Change nickname in this tournament to') . ':</td><td><input id="form-nick" value="' . $nickname . '">';
	echo '</td></tr>';

	echo '<tr><td>' . get_label('Replace [0] in this tournament with', $user_name) . ':</td><td><table class="transp" width="100%"><tr><td>';
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
		json.post("api/ops/tournament.php",
		{
			op: "change_player"
			, tournament_id: <?php echo $tournament_id; ?>
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