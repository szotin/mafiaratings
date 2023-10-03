<?php

require_once '../include/session.php';
require_once '../include/user.php';
require_once '../include/city.php';
require_once '../include/country.php';
require_once '../include/timezone.php';
require_once '../include/image.php';
require_once '../include/languages.php';

initiate_session();

try
{
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	list($user_name, $club_id) = Db::record(get_label('user'), 
		'SELECT nu.name, u.club_id'.
		' FROM users u'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE u.id = ?', $user_id);
	
	$club_pic = new Picture(CLUB_PICTURE);
	echo '<p>' . get_label('Join club') . ': <select id="form-join-club" onChange="joinClub()">';
	$query = new DbQuery('SELECT id, name FROM clubs WHERE (flags & ' . CLUB_FLAG_RETIRED . ') = 0 AND id NOT IN(SELECT club_id FROM club_users WHERE user_id = ?) ORDER BY name', $user_id);
	show_option(0, 0, '');
	while ($row = $query->next())
	{
		list ($c_id, $c_name) = $row;
		show_option($c_id, 0, $c_name);
	}
	echo '</select></p>';
	echo '<table class="bordered dark" width="100%">';
	$query = new DbQuery('SELECT c.id, c.name, c.flags, u.flags FROM club_users u JOIN clubs c ON c.id = club_id WHERE u.user_id = ? ORDER BY c.name', $user_id);
	while ($row = $query->next())
	{
		list ($c_id, $c_name, $c_flags, $uc_flags) = $row;
		echo '<tr><td class="darker" width="90">';
		echo '<button class="icon" onclick="quitClub(' . $c_id . ')" title="' . get_label('Quit club [0]', $c_name) . '"><img src="images/delete.png" border="0"></button>';
		if ($uc_flags & USER_CLUB_FLAG_SUBSCRIBED)
		{
			echo '<button class="icon" onclick="subscribe(' . $c_id . ', 0)" title="' . get_label('Unsubscribe from [0] event notifications', $c_name) . '" checked';
		}
		else
		{
			echo '<button class="icon" onclick="subscribe(' . $c_id . ', 1)" title="' . get_label('Subscribe to [0] event notifications', $c_name) . '"';
		}
		echo '><img src="images/email.png"></button>';
		if ($c_id == $club_id)
		{
			echo '<button class="icon" onclick="mainClub(0)" title="' . get_label('Revoke [0] as a main club of [1]', $c_name, $user_name) . '"><img src="images/accept.png"></button>';
		}
		else
		{
			echo '<button class="icon" onclick="mainClub(' . $c_id . ')" title="' . get_label('Set [0] as a main club of [1]', $c_name, $user_name) . '"><img src="images/empty.png"></button>';
		}
		echo '</td><td><table class="transp" width="100%"><tr><td width="48">';
		$club_pic->set($c_id, $c_name, $c_flags);
		$club_pic->show(ICONS_DIR, false, 36);
		echo '</td><td>' . $c_name . '</td><td align="right">';
		if ($uc_flags & USER_PERM_MANAGER)
		{
			echo '<img src="images/manager.png" width="24" title="' . get_label('Manager') . '">';
		}
		else
		{
			echo '<img src="images/transp.png" width="24">';
		}
		if ($uc_flags & USER_PERM_REFEREE)
		{
			echo '<img src="images/referee.png" width="24" title="' . get_label('Referee') . '">';
		}
		else
		{
			echo '<img src="images/transp.png" width="24">';
		}
		if ($uc_flags & USER_PERM_PLAYER)
		{
			echo '<img src="images/player.png" width="24" title="' . get_label('Player') . '">';
		}
		else
		{
			echo '<img src="images/transp.png" width="24">';
		}
		echo '</tr></table></td></tr>';
	}
	echo '</table>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>