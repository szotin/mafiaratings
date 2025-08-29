<html>
<head>
<title><?php echo PRODUCT_NAME; ?> API reference</title>
<META content="text/html; charset=utf-8" http-equiv=Content-Type>
<script src="js/common.js"></script>
</head><body>

<?php

require_once 'include/session.php';
require_once 'include/email.php';

initiate_session();

try
{
	Db::begin();
	
	$is_admin = false;
	$obj = EMAIL_OBJ_SIGN_IN;
	if (isset($_REQUEST['user_id']))
	{
		$user_id = $_REQUEST['user_id'];
		if (substr($user_id, 0, 1) == 'a')
		{
			$is_admin = true;
			$user_id = substr($user_id, 1);
		}
		$user_id = (int)$user_id;
		list ($user_name) = Db::record(get_label('user'), 'SELECT n.name FROM users u JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
	}
	else if (isset($_REQUEST['code']))
	{
		$code = $_REQUEST['code'];
		list ($obj, $obj_id, $flags, $user_id, $user_name) = Db::record(get_label('email'),
			'SELECT e.obj, e.obj_id, u.flags, u.id, n.name'.
			' FROM emails e'.
			' JOIN users u ON u.id = e.user_id'.
			' JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0'.
			' WHERE e.code = ?', $code);
	}
	else
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}


	if ($is_admin)
	{
		if (isset($_REQUEST['undo']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . USER_FLAG_ADMIN_NOTIFY . ') WHERE id = ?', $user_id);
			if (!is_null($_profile) && $_profile->user_id == $user_id)
			{
				$_profile->user_flags |= USER_FLAG_ADMIN_NOTIFY;
			}
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'subscribed to notifications', NULL, $user_id);
			}
			echo '<h2>' . get_label('Hello, [0]. Your subscription to administrative notifications from [1] is restored.', $user_name, PRODUCT_NAME) . '</h2>';
		}
		else
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . USER_FLAG_ADMIN_NOTIFY . ') WHERE id = ?', $user_id);
			if (!is_null($_profile) && $_profile->user_id == $user_id)
			{
				$_profile->user_flags &= ~USER_FLAG_ADMIN_NOTIFY;
			}
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'unsubscribed from notifications', NULL, $user_id);
			}
			echo '<h2>' . get_label('Hello, [0]. You will no longer recieve administrative notifications from [1].', $user_name, PRODUCT_NAME) . '</h2>';
		}			
	}
	else if ($obj == EMAIL_OBJ_EVENT_INVITATION)
	{
		list ($club_id, $club_name) = Db::record(get_label('email'), 'SELECT c.id, c.name FROM event_mailings em JOIN events e ON e.id = em.event_id JOIN clubs c ON c.id = e.club_id WHERE em.id = ?', $obj_id);
		if (isset($_REQUEST['undo']))
		{
			Db::exec(get_label('user'), 'UPDATE club_users SET flags = (flags | ' . USER_CLUB_FLAG_SUBSCRIBED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'subscribed', NULL, $user_id, $club_id);
			}
			
			if (!is_null($_profile) && $_profile->user_id == $user_id && isset($_profile->clubs) && array_key_exists($club_id, $_profile->clubs))
			{
				$_profile->clubs[$club_id]->flags |= USER_CLUB_FLAG_SUBSCRIBED;
			}
			echo '<h2>' . get_label('Hello, [0]. Your subscription to [1] is restored.', $user_name, $club_name) . '</h2>';
		}
		else
		{
			Db::exec(get_label('user'), 'UPDATE club_users SET flags = (flags & ~' . USER_CLUB_FLAG_SUBSCRIBED . ') WHERE user_id = ? AND club_id = ?', $user_id, $club_id);
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'unsubscribed', NULL, $user_id, $club_id);
			}
			
			if (!is_null($_profile) && $_profile->user_id == $user_id && isset($_profile->clubs) && array_key_exists($club_id, $_profile->clubs))
			{
				$_profile->clubs[$club_id]->flags &= ~USER_CLUB_FLAG_SUBSCRIBED;
			}
			echo '<h2>' . get_label('Hello, [0]. You will no longer recieve emails from [1].', $user_name, $club_name) . '</h2>';
		}
	}
	else
	{
		if (isset($_REQUEST['undo']))
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . USER_FLAG_NOTIFY . ') WHERE id = ?', $user_id);
			if (!is_null($_profile) && $_profile->user_id == $user_id)
			{
				$_profile->user_flags &= ~USER_FLAG_NOTIFY;
			}
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'unsubscribed from comments', NULL, $user_id);
			}
			echo '<h2>' . get_label('Hello, [0]. Your subscription to notifications from [1] is restored.', $user_name, PRODUCT_NAME) . '</h2>';
		}
		else
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ~' . USER_FLAG_NOTIFY . ') WHERE id = ?', $user_id);
			if (!is_null($_profile) && $_profile->user_id == $user_id)
			{
				$_profile->user_flags &= ~USER_FLAG_NOTIFY;
			}
			if (Db::affected_rows() > 0)
			{
				db_log(LOG_OBJECT_USER, 'unsubscribed from comments', NULL, $user_id);
			}
			echo '<h2>' . get_label('Hello, [0]. You will no longer recieve notifications from [1].', $user_name, PRODUCT_NAME) . '</h2>';
		}
	}
	Db::commit();
	
	if (isset($_REQUEST['undo']))
	{
		echo '<p><a href="#" onclick="goTo({undo:undefined})">' . get_label('Click here to unsubscribe') . '</a></p>';
	}
	else
	{
		echo '<p><a href="#" onclick="goTo({undo:1})">' . get_label('Click here to subscribe back') . '</a></p>';
	}
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h2>' . get_label('Error: [0]', $e->getMessage()) . '</h2>';
}

?>

</body></html>
