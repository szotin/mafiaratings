<?php

require_once 'include/session.php';

initiate_session();

try
{
	if ($_profile == NULL)
	{
		throw new FatalExc(get_label('No permissions'));
	}
	
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('log record')));
	}
	$id = $_REQUEST['id'];

	dialog_title(get_label('Log details'));
	
	list($user_id, $user_name, $time, $obj, $obj_id, $ip, $message, $club_id, $club_name, $page, $details) =
		Db::record(get_label('log record'),
			'SELECT u.id, nu.name, l.time, l.obj, l.obj_id, l.ip, l.message, c.id, c.name, l.page, l.details FROM log l' .
				' LEFT OUTER JOIN users u ON u.id = l.user_id' .
				' LEFT OUTER JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = l.club_id WHERE l.id = ?',
			$id);

	if (!$_profile->is_admin() && ($club_id == NULL || !$_profile->is_club_manager($club_id)))
	{
		throw new FatalExc(get_label('No permissions'));
	}
	echo '<table class="dialog_form" width="100%">';
	
	echo '<tr><td width="140">' . get_label('Time') . ':</td><td>' . format_date('d/m/y H:i', $time, get_timezone()) . '</td></tr>';
	if ($page != '')
	{
		echo '<tr><td>' . get_label('Page') . ':</td><td>' . $page . '</td></tr>';
	}
	if ($user_id != NULL)
	{
		echo '<tr><td>' . get_label('User') . ':</td><td>' . $user_name . '</td></tr>';
	}
	if ($ip != '')
	{
		echo '<tr><td>' . get_label('IP') . ':</td><td>' . $ip . '</td></tr>';
	}
	if ($club_id != NULL)
	{
		echo '<tr><td>' . get_label('Club') . ':</td><td>' . $club_name . '</td></tr>';
	}
	echo '<tr><td>' . get_label('Object') . ':</td><td>' . $obj . ' ' . $obj_id . '</td></tr>';
	echo '<tr><td>' . get_label('Message') . ':</td><td>' . $message . '</td></tr>';
	echo '<tr><td>' . get_label('Details') . ':</td><td>' . $details . '</td></tr>';
	echo '</table>';
				
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>