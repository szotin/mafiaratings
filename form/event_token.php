<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rand_str.php';

initiate_session();

try
{
	if (!isset($_REQUEST['event_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('event')));
	}
	$event_id = (int)$_REQUEST['event_id'];
	list($club_id, $token) = Db::record(get_label('event'), 'SELECT club_id, security_token FROM events WHERE id = ?', $event_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

	dialog_title(get_label('Security token'));
	if (is_null($token))
	{
		$token = rand_string(32);
		Db::exec(get_label('event'), 'UPDATE events SET security_token = ? WHERE id = ?', $token, $event_id);
	}
	echo '<p>' . $token . '</p>';
	
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>