<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rand_str.php';

initiate_session();

try
{
	if (!isset($_REQUEST['tournament_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tournament_id'];
	list($club_id, $token) = Db::record(get_label('tournament'), 'SELECT club_id, security_token FROM tournaments WHERE id = ?', $tournament_id);
	check_permissions(PERMISSION_CLUB_MANAGER, $club_id);

	dialog_title(get_label('Security token'));
	if (is_null($token))
	{
		$token = rand_string(32);
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET security_token = ? WHERE id = ?', $token, $tournament_id);
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