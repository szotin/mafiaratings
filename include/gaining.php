<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/utilities.php';

define('GAINING_DEFAULT_ID', 1); // Default gaining system is hardcoded here to MWT (Mafia World Tour)

define('GAINING_FUNCTION_ROUND',              0x0001); //      1
define('GAINING_FUNCTION_FLOOR',              0x0002); //      2
define('GAINING_FUNCTION_CEIL',               0x0004); //      4
define('GAINING_FUNCTION_LOG',                0x0008); //      8
define('GAINING_FUNCTION_MIN',                0x0010); //     16
define('GAINING_FUNCTION_MAX',                0x0020); //     32
define('GAINING_FUNCTION_TABLE',              0x0040); //     64
define('GAINING_FUNCTION_SCORE',              0x0080); //    128
define('GAINING_FUNCTION_NUM_PLAYERS',        0x0100); //    256
define('GAINING_FUNCTION_STARS',              0x0200); //    512
define('GAINING_FUNCTION_RATING_SUM',         0x0400); //   1024
define('GAINING_FUNCTION_RATING_SUM_20',      0x0800); //   2048
define('GAINING_FUNCTION_TRAVELING_DISTANCE', 0x1000); //   4096
define('GAINING_FUNCTION_GUEST_COEF',         0x2000); //   8192

function get_gaining_functions()
{
	return array(
		new EvFuncRound(), 
		new EvFuncFloor(), 
		new EvFuncCeil(), 
		new EvFuncLog(), 
		new EvFuncMin(), 
		new EvFuncMax(), 
		new EvFuncParam('table'),
		new EvFuncParam('score'),
		new EvFuncParam('numPlayers'),
		new EvFuncParam('stars'),
		new EvFuncParam('ratingSum'),
		new EvFuncParam('ratingSum20'),
		new EvFuncParam('travelingDistance'),
		new EvFuncParam('guestCoef'));
}

function get_gaining_points($competition_id, $gaining, $stars, $place, $score, $num_players, $rating_sum, $rating_sum20, $trav_dist, $guest_coef, $is_series = false)
{
	if (!isset($gaining->__cache) || $gaining->__cache->competition_id != $competition_id || $gaining->__cache->is_series != $is_series)
	{
		$gaining->__cache = new stdClass();
		$gaining->__cache->competition_id = $competition_id;
		$gaining->__cache->is_series = $is_series;
		
		if ($is_series && isset($gaining->seriesPoints))
		{
			$eq = $gaining->seriesPoints;
		}
		else
		{
			$eq = $gaining->points;
		}
		$evaluator = $gaining->__cache->evaluator = new Evaluator($eq, get_gaining_functions());
		if (isset($gaining->table))
		{
			$evaluator->set_var('table', $gaining->table);
		}
		else
		{
			$evaluator->set_var('table', array());
		}
		$evaluator->set_var('stars', $stars);
		$evaluator->set_var('numPlayers', $num_players);
		$evaluator->set_var('ratingSum', $rating_sum);
		$evaluator->set_var('ratingSum20', $rating_sum20);
		$evaluator->set_var('travelingDistance', $trav_dist);
		$evaluator->set_var('guestCoef', $guest_coef);
		
		if (isset($gaining->globals))
		{
			$evaluator->set_vars($gaining->globals);
		}
	}
	else
	{
		$evaluator = $gaining->__cache->evaluator;
	}
	$evaluator->set_var('score', $score);
	$evaluator->set_var('place', $place);
	if (isset($gaining->vars))
	{
		$evaluator->set_vars($gaining->vars);
	}
	return $evaluator->evaluate();
}

function format_gain($gain, $zeroes = true)
{
	return format_float($gain, 2, $zeroes);
}

function show_gaining_info($gaining)
{
	echo 'points: ' . $gaining->points . '<br>';
	if (isset($gaining->globals))
	{
		foreach ($gaining->globals as $name =>$value)
		{
			echo $name . ': ' . $value . '<br>';
		}
	}
	if (isset($gaining->vars))
	{
		foreach ($gaining->vars as $name =>$value)
		{
			echo $name . ': ' . $value . '<br>';
		}
	}
	if (isset($gaining->__cache))
	{
		$gaining->__cache->evaluator->print_vars();
	}
}

function get_gaining_function_flags($gaining)
{
	$functions = array();
	
	if (isset($gaining->seriesPoints))
	{
		$evaluator = new Evaluator($gaining->seriesPoints, get_gaining_functions());
		$evaluator->add_functions($functions);
	}
	if (isset($gaining->points))
	{
		$evaluator = new Evaluator($gaining->points, get_gaining_functions());
		$evaluator->add_functions($functions);
	}
	
	$flags = 0;
	foreach ($functions as $name => $count)
	{
		switch ($name)
		{
		case 'round':
			$flags |= GAINING_FUNCTION_ROUND;
			break;
		case 'floor':
			$flags |= GAINING_FUNCTION_FLOOR;
			break;
		case 'ceil':
			$flags |= GAINING_FUNCTION_CEIL;
			break;
		case 'log':
			$flags |= GAINING_FUNCTION_LOG;
			break;
		case 'min':
			$flags |= GAINING_FUNCTION_MIN;
			break;
		case 'max':
			$flags |= GAINING_FUNCTION_MAX;
			break;
		case 'table':
			$flags |= GAINING_FUNCTION_TABLE;
			break;
		case 'score':
			$flags |= GAINING_FUNCTION_SCORE;
			break;
		case 'numplayers':
			$flags |= GAINING_FUNCTION_NUM_PLAYERS;
			break;
		case 'stars':
			$flags |= GAINING_FUNCTION_STARS;
			break;
		case 'ratingsum':
			$flags |= GAINING_FUNCTION_RATING_SUM;
			break;
		case 'ratingsum20':
			$flags |= GAINING_FUNCTION_RATING_SUM_20;
			break;
		case 'travelingdistance':
			$flags |= GAINING_FUNCTION_TRAVELING_DISTANCE;
			break;
		case 'guestcoef':
			$flags |= GAINING_FUNCTION_GUEST_COEF;
			break;
		}
	}
	return $flags;
}

function api_gaining_help($param)
{
	$param->sub_param('Help on gaining json structure is not implemented yet.', '', '-');
}

?>
