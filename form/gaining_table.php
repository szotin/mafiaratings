<?php

require_once '../include/session.php';
require_once '../include/scoring.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['gaining_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('gaining system')));
	}
	$gaining_id = (int)$_REQUEST['gaining_id'];
	
	if (isset($_REQUEST['gaining_version']))
	{
		$gaining_version = (int)$_REQUEST['gaining_version'];
		list($gaining, $name, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? AND v.version = ?', $gaining_id, $gaining_version);
	}
	else
	{
		list($gaining, $name, $gaining_version, $league_id) = Db::record(get_label('gaining'), 'SELECT v.gaining, s.name, v.version, s.league_id FROM gaining_versions v JOIN gainings s ON s.id = v.gaining_id WHERE v.gaining_id = ? ORDER BY version DESC LIMIT 1', $gaining_id);
		$gaining_version = (int)$gaining_version;
	}
	$gaining = json_decode($gaining);
	
	$players = 30;
	if (isset($_REQUEST['players']))
	{
		$players = (int)$_REQUEST['players'];
	}
	
	$stars = 2;
	if (isset($_REQUEST['stars']))
	{
		$stars = (double)$_REQUEST['stars'];
	}
	
	$place = 0;
	if (isset($_REQUEST['place']))
	{
		$place = (int)$_REQUEST['place'];
	}
	
	$table = create_gaining_table($gaining, $stars, $players, false);
	echo '<table class="bordered light" width="100%">';
	echo '<tr class="darker"><td width="100"><b>' . get_label('Place') . '</b></td><td><b>' . get_label('Points') . '</b></td></tr>';
	for ($p = 1; $p <= $table->players; ++$p)
	{
		echo '<tr';
		echo ($p == $place ? ' class="darker"' : '');
		echo '><td>' . $p . '</td><td>' . format_score(get_gaining_points($table, $p)) . '</td></tr>';
	}
	echo '</table>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo 'Error: <b>' . $e->getMessage() . '</b>';
}

?>