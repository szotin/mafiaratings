<?php

require_once '../include/session.php';
require_once '../include/languages.php';
require_once '../include/url.php';
require_once '../include/email.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	list($user_name, $user_flags) = Db::record(get_label('user'), 'SELECT u.name, u.flags FROM users u WHERE u.id = ?', $user_id);
	
	$club_id = 0;
	if (isset($_REQUEST['event_id']))
	{
		$event_id = (int)$_REQUEST['event_id'];
		list($club_id, $tour_id, $name, $user_flags) = Db::record(get_label('event'), 'SELECT e.club_id, e.tournament_id, e.name, eu.flags FROM event_users eu JOIN events e ON e.id = eu.event_id WHERE eu.event_id = ? AND eu.user_id = ?', $event_id, $user_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $event_id, $tour_id);
		dialog_title(get_label('[0] permissions in [1]', $user_name, $name));
	}
	else if (isset($_REQUEST['tournament_id']))
	{
		$tournament_id = (int)$_REQUEST['tournament_id'];
		list($club_id, $name, $user_flags) = Db::record(get_label('tournament'), 'SELECT t.club_id, t.name, tu.flags FROM tournament_users tu JOIN tournaments t ON t.id = tu.tournament_id WHERE tu.tournament_id = ? AND tu.user_id = ?', $tournament_id, $user_id);
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
		dialog_title(get_label('[0] permissions in [1]', $user_name, $name));
	}
	else if (isset($_REQUEST['club_id']))
	{
		$club_id = (int)$_REQUEST['club_id'];
		list($name, $user_flags) = Db::record(get_label('club'), 'SELECT c.name, cu.flags FROM club_users cu JOIN clubs c ON c.id = cu.club_id WHERE cu.club_id = ? AND cu.user_id = ?', $club_id, $user_id);
		check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		dialog_title(get_label('[0] permissions in [1]', $user_name, $name));
	}
	else
	{
		check_permissions(PERMISSION_ADMIN);
		dialog_title(get_label('[0] permissions', $user_name));
	}
	

	if ($club_id > 0)
	{
		echo '<input type="checkbox" id="form-manager" value="1"' . ((($user_flags & USER_PERM_MANAGER) != 0) ? ' checked' : '') . '> '.get_label('Manager');
		echo '<br><input type="checkbox" id="form-moder" value="1"' . ((($user_flags & USER_PERM_MODER) != 0) ? ' checked' : '') . '> '.get_label('Moderator');
		echo '<br><input type="checkbox" id="form-player" value="1"' . ((($user_flags & USER_PERM_PLAYER) != 0) ? ' checked' : '') . '> '.get_label('Player');
		if (isset($event_id))
		{
?>	
			<script>
			function commit(onSuccess)
			{
				json.post("api/ops/user.php",
				{
					op: 'access',
					user_id: <?php echo $user_id; ?>,
					event_id: <?php echo $event_id; ?>,
					manager: ($("#form-manager").attr("checked") ? 1 : 0),
					moder: ($("#form-moder").attr("checked") ? 1 : 0),
					player: ($("#form-player").attr("checked") ? 1 : 0)
				},
				onSuccess);
			}
			</script>
<?php
		}
		else if (isset($tournament_id))
		{
?>	
			<script>
			function commit(onSuccess)
			{
				json.post("api/ops/user.php",
				{
					op: 'access',
					user_id: <?php echo $user_id; ?>,
					tournament_id: <?php echo $tournament_id; ?>,
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
	}
	else
	{
		echo '<input type="checkbox" id="form-admin" value="1"' . ((($user_flags & USER_PERM_ADMIN) != 0) ? ' checked' : '') . '> '.get_label('Admin');
?>	
		<script>
		function commit(onSuccess)
		{
			json.post("api/ops/user.php",
			{
				op: 'access',
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