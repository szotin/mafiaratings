<?php

require_once '../include/session.php';
require_once '../include/gaining.php';
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
	
	$rating_sum = 500 * $players;
	if (isset($_REQUEST['rating_sum']))
	{
		$rating_sum = (bool)$_REQUEST['rating_sum'];
	}
	
	$rating_sum20 = 500 * 20;
	if (isset($_REQUEST['rating_sum20']))
	{
		$rating_sum20 = (bool)$_REQUEST['rating_sum20'];
	}
	
	$trav_dist = 0;
	if (isset($_REQUEST['trav_dist']))
	{
		$trav_dist = (bool)$_REQUEST['trav_dist'];
	}
	
	$guest_coeff += log(1 + $trav_dist / 600, 2);
	if (isset($_REQUEST['guest_coef']))
	{
		$guest_coef = (bool)$_REQUEST['guest_coef'];
	}
	
	$points = array();
	$message = false;
	$all_the_same = true;
	$points[] = get_gaining_points(0, $gaining, $stars, 1, 0, $players, $rating_sum, $rating_sum20, $trav_dist, $guest_coef, false);
	for ($p = 2; $p <= $players; ++$p)
	{
		$gp = get_gaining_points(0, $gaining, $stars, $p, 0, $players, $rating_sum, $rating_sum20, $trav_dist, $guest_coef, false);
		$points[] = $gp;
		if ($all_the_same && abs($points[0] - $gp) > 0.00001)
		{
			$all_the_same = false;
		}
	}
	
	if ($all_the_same)
	{
		if (abs($points[0]) > 0.00001)
		{
			echo get_label('Plus everyoune receives [0] points.', format_gain($points[0]));
		}
	}
	else
	{
		echo get_label('In addition to:');
		echo '<p>';
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="100"><b>' . get_label('Place') . '</b></td><td><b>' . get_label('Points') . '</b></td></tr>';
		for ($p = 1; $p <= $players; ++$p)
		{
			echo '<tr';
			echo ($p == $place ? ' class="darker"' : '');
			echo '><td>' . $p . '</td><td>' . format_gain($points[$p-1]) . '</td></tr>';
		}
		echo '</table>';
		echo '</p>';
	}
}
catch (Exception $e)
{
	Exc::log($e);
	echo 'Error: <b>' . $e->getMessage() . '</b>';
}

?>