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
	
	$message = false;
	$table = create_gaining_table($gaining, $stars, $players, 0, false);
	$all_the_same = true;
	$default_points = get_gaining_points($table, 1, 0);
	for ($p = 2; $p <= $table->players; ++$p)
	{
		if (abs($default_points - get_gaining_points($table, $p, 0)) > 0.00001)
		{
			$all_the_same = false;
			break;
		}
	}
	
	if (isset($table->pointsPool) && $table->players > 0)
	{
		echo '<p>';
		switch ($table->tournamentScorePower)
		{
			case 0:
				break;
			case 1:
				echo get_label('Еach player receives points from the pool in accordance with the points scored in the tournament[1]. The pool size is [0] points.', $table->pointsPool, '');
				$message = true;
				break;
			case 2:
				echo get_label('Еach player receives points from the pool in accordance with the points scored in the tournament[1]. The pool size is [0] points.', $table->pointsPool, get_label(' squared'));
				$message = true;
				break;
			case 3:
				echo get_label('Еach player receives points from the pool in accordance with the points scored in the tournament[1]. The pool size is [0] points.', $table->pointsPool, get_label(' cubed'));
				$message = true;
				break;
			default:
				echo get_label('Еach player receives points from the pool in accordance with the points scored in the tournament[1]. The pool size is [0] points.', $table->pointsPool, get_label(' raized to the [0] power', $table->tournamentScorePower));
				$message = true;
				break;
		}
		echo '</p>';
	}	
	if ($all_the_same)
	{
		if (abs($default_points) > 0.00001)
		{
			echo get_label('Plus everyoune receives [0] points.', format_score($default_points));
		}
	}
	else
	{
		echo get_label('In addition to:');
		echo '<p>';
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="100"><b>' . get_label('Place') . '</b></td><td><b>' . get_label('Points') . '</b></td></tr>';
		for ($p = 1; $p <= $table->players; ++$p)
		{
			echo '<tr';
			echo ($p == $place ? ' class="darker"' : '');
			echo '><td>' . $p . '</td><td>' . format_score(get_gaining_points($table, $p, 0)) . '</td></tr>';
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