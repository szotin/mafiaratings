<?php

require_once 'include/session.php';
require_once 'include/languages.php';
require_once 'include/url.php';
require_once 'include/email.php';
require_once 'include/editor.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['id'];
	
	$club_id = 0;
	if (isset($_REQUEST['club']))
	{
		$club_id = (int)$_REQUEST['club'];
	}
	
	list($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT u.name, u.flags FROM users u WHERE u.id = ?', $user_id);
	
	dialog_title(get_label('[0] permissions', $user_name));

	if ($club_id > 0)
	{
		list($user_flags) = Db::record(get_label('user'), 'SELECT uc.flags FROM user_clubs uc WHERE uc.user_id = ? AND uc.club_id = ?', $user_id, $club_id);
		echo '<input type="checkbox" id="form-manager" value="1"' . ((($user_flags & UC_PERM_MANAGER) != 0) ? ' checked' : '') . '> '.get_label('Manager');
		echo '<br><input type="checkbox" id="form-moder" value="1"' . ((($user_flags & UC_PERM_MODER) != 0) ? ' checked' : '') . '> '.get_label('Moderator');
		echo '<br><input type="checkbox" id="form-player" value="1"' . ((($user_flags & UC_PERM_PLAYER) != 0) ? ' checked' : '') . '> '.get_label('Player');
?>	
		<script>
		
		function commit(onSuccess)
		{
			json.post("api/ops/user.php",
			{
				op: 'access',
				user_id: <?php echo $user_id; ?>,
				club_id: <?php echo $club_id; ?>,
				manager: ($("#form-manager").attr("checked") ? 1 : 0),
				moder: ($("#form-moder").attr("checked") ? 1 : 0),
				player: ($("#form-player").attr("checked") ? 1 : 0)
			},
			onSuccess);
		}
		</script>
<?php
	}
	else
	{
		echo '<input type="checkbox" id="form-admin" value="1"' . ((($user_flags & U_PERM_ADMIN) != 0) ? ' checked' : '') . '> '.get_label('Admin');
?>	
		<script>
		
		function commit(onSuccess)
		{
			json.post("api/ops/user.php",
			{
				op: 'site_access',
				user_id: <?php echo $user_id; ?>,
				admin: ($("#form-admin").attr("checked") ? 1 : 0)
			},
			onSuccess);
		}
		</script>
<?php
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>