<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rules.php';

initiate_session();

// The rules of a league are filters: what a club that picks them may keep of its own default
// rules. league_id alone edits the default filter of the league - its rulebook, leagues.rules.
// rules_id edits named rules of a league, create with league_id makes new ones; both are rows of
// the table rules.
try
{
	if (isset($_REQUEST['rules_id']))
	{
		$rules_id = (int)$_REQUEST['rules_id'];
		list($league_id, $rules, $rules_name) = Db::record(get_label('rules'), 'SELECT league_id, rules, name FROM rules WHERE id = ?', $rules_id);
		if (is_null($league_id))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('rules')));
		}
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		dialog_title(get_label('Edit [0]', get_label('rules [0]', $rules_name)));
		show_rules_filter_form($rules, 'api/ops/rules.php', array('op' => 'change', 'rules_id' => $rules_id), $rules_name);
	}
	else
	{
		if (!isset($_REQUEST['league_id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('league')));
		}
		$league_id = (int)$_REQUEST['league_id'];
		check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
		
		list($league_rules, $league_name) = Db::record(get_label('league'), 'SELECT rules, name FROM leagues WHERE id = ?', $league_id);
		if (isset($_REQUEST['create']))
		{
			// New rules start as the default filter of the league.
			dialog_title(get_label('Create [0]', get_label('rules for [0]', $league_name)));
			show_rules_filter_form($league_rules, 'api/ops/rules.php', array('op' => 'create', 'league_id' => $league_id), '');
		}
		else
		{
			dialog_title(get_label('Edit [0]', get_label('rules [0]', $league_name)));
			show_rules_filter_form($league_rules, 'api/ops/league.php', array('op' => 'change', 'league_id' => $league_id));
		}
	}
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>
