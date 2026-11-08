<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rules.php';

initiate_session();

try
{
	if (!isset($_REQUEST['league_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('league')));
	}
	$league_id = (int)$_REQUEST['league_id'];
	check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
	
	list($league_rules, $league_name) = Db::record(get_label('league'), 'SELECT rules, name FROM leagues WHERE id = ?', $league_id);
	dialog_title(get_label('Edit [0]', get_label('rules [0]', $league_name)));
	show_rules_filter_form($league_rules, 'api/ops/league.php', array('op' => 'change', 'league_id' => $league_id));
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>
