<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/evaluator.php';
require_once __DIR__ . '/utilities.php';

define('GAINING_DEFAULT_ID', 1); // Default gaining system is hardcoded here to MWT (Mafia World Tour)

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

function api_gaining_help($param)
{
	$param->sub_param('Help on gaining json structure is not implemented yet.', '', '-');
}

?>
