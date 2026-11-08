<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/rules.php';
require_once '../include/series.php';

initiate_session();

try
{
	if (!isset($_REQUEST['series_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('sеriеs')));
	}
	$series_id = (int)$_REQUEST['series_id'];
	
	list($series_rules, $series_name, $league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT rules, name, league_id, club_id FROM series WHERE id = ?', $series_id);
	$owner = new SeriesOwner($league_id, $club_id);
	check_series_permissions($owner, $series_id);
	
	// A series of a club carries its club's rules code rather than a filter, and a code is
	// picked rather than filtered - there is nothing here for this form to edit. The rulebook
	// page does not offer the button for one, so this only answers a hand-written address.
	if (!$owner->is_league())
	{
		throw new FatalExc(get_label('A series of a club is played by the rules of the club.'));
	}
	
	dialog_title(get_label('Edit [0]', get_label('rules [0]', $series_name)));
	show_rules_filter_form($series_rules, 'api/ops/series.php', array('op' => 'change_rules', 'series_id' => $series_id));
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>
