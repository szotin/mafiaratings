<?php

require_once __DIR__ . '/localization.php'; // get_label

// A round of a tournament: what it is called, and how many tables it is played at.
//
// events.round numbers the rounds by how near the end they are, not by the order they are
// played: zero is the main round, and above it 1 is the final, 2 the semi-final, 3 the
// quarter-final, and a deeper rung is named by the part of the field it holds. So a bigger
// number is an EARLIER round - except zero, which comes before all of them. A series numbers
// its own stages the same way; see SERIES_STAGE_BASE in series.php.
//
// This stands on its own, under both tournament.php and stream.php, because the two things in
// it are wanted where neither of those belongs: a browser source for OBS Studio has to name
// the round on the overlay without dragging in a page base, and api/get/current_game.php
// counts the tables of a round on every poll of a game.

// The deepest rung worth a name. A tournament of 32 tables that plays all the way down would
// reach this; nobody has, and a round numbered past it is named by the same fraction anyway -
// this only says how far the list handed to a script has to go.
define('ROUND_DEEPEST_STAGE', 6);

function get_round_name($round_num)
{
	switch ($round_num)
	{
		case 0:
			return get_label('main round');
		case 1:
			return get_label('final');
		case 2:
			return get_label('semi-final');
		case 3:
			return get_label('quarter-final');
		default:
			return get_label('1/[0] final', pow(2, $round_num - 1));
	}
}

// The rungs above the main round, keyed by the number events.round holds. For a script, which
// cannot ask get_label() for the one it turns out to need: the page it belongs to is drawn
// once, in one language, and the round can change under it while it runs.
//
// The main round is not in the list. It is the tournament itself rather than a part of it, and
// naming it on a stream would say nothing a viewer does not already see.
function get_round_stage_names()
{
	$names = array();
	for ($stage = 1; $stage <= ROUND_DEEPEST_STAGE; ++$stage)
	{
		$names[$stage] = get_round_name($stage);
	}
	return $names;
}

// How many tables the seating of a round lays out, or 0 where it has no seating.
//
// The seating is kept game by game - rounds[game][table][seat] - so the tables of the round are
// the widest game in it. $misc is the events.misc column, as it comes out of the database or
// already decoded.
//
// Zero means "nothing says so" rather than "none": a round whose seating has not been arranged
// yet is played at as many tables as it turns out to need.
function seating_tables($misc)
{
	if (is_string($misc))
	{
		$misc = json_decode($misc);
	}
	if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
	{
		return 0;
	}

	$tables = 0;
	foreach ($misc->seating->rounds as $round)
	{
		if (is_array($round))
		{
			$tables = max($tables, count($round));
		}
	}
	return $tables;
}

// How many games the seating of a round lays out for one table, or 0 where it has no seating.
//
// A round is seated game by game, and a game that needs fewer tables than the round does seats
// none at this one - so this counts the games this table is actually in rather than the games
// of the round. Which game of how many is the one number a frame says about a table; see
// obs_head_game() in plugins/obs/screens.php and tourCount in api/get/current_game.php.
function seating_games($misc, $table_num)
{
	if (is_string($misc))
	{
		$misc = json_decode($misc);
	}
	if (is_null($misc) || !isset($misc->seating->rounds) || !is_array($misc->seating->rounds))
	{
		return 0;
	}

	$games = 0;
	foreach ($misc->seating->rounds as $round)
	{
		if (is_array($round) && $table_num <= count($round))
		{
			++$games;
		}
	}
	return $games;
}

?>
