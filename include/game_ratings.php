<?php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/db.php';

// This is a part of rating descenting code. The lock period is updated but not used. This part will most likely remain the same in the final implementation.
define('MAX_LOCK_TIME', 31536000); // one year
// // This is a rating descenting code that is not implemented yet.
// define('RATING_DESCEND_PERIOD', 126144000); // four years

function adjust_rating($rating, $lock_time_end, $time)
{
	// // This is a rating descenting code that is not implemented yet.
	// if ($time <= $lock_time_end)
	// {
		// return $rating;
	// }
	// if ($time >= $lock_time_end + RATING_DESCEND_PERIOD)
	// {
		// return 0;
	// }
	// return ($rating * ($lock_time_end + RATING_DESCEND_PERIOD - $time)) / RATING_DESCEND_PERIOD;
	
	// Currently we are not adjusting rating based on the idle time. Rating descenting code will do it in the future.
	return $rating;
}

function update_game_ratings($game_id)
{
	$players = array();
	$query = new DbQuery(
		'SELECT p.user_id, p1.role_rating_before + p1.rating_earned, p2.rating_before + p2.rating_earned, p2.rating_lock_until, p.role, p.won, g.is_rating, t.id, t.flags, g.end_time'.
		' FROM players p'.
		' JOIN games g ON g.id = p.game_id'.
		' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id'.
		' LEFT OUTER JOIN players p1 ON p.user_id = p1.user_id AND p1.game_id = (SELECT p11.game_id FROM players p11 WHERE p11.is_rating <> 0 AND p11.user_id = p.user_id AND (p11.game_end_time < p.game_end_time OR (p11.game_end_time = p.game_end_time AND p11.game_id < p.game_id)) AND (p11.role DIV 2) = (p.role DIV 2) ORDER BY p11.game_end_time DESC, p11.game_id DESC LIMIT 1)'.
		' LEFT OUTER JOIN players p2 ON p.user_id = p2.user_id AND p2.game_id = (SELECT p21.game_id FROM players p21 WHERE p21.is_rating <> 0 AND p21.user_id = p.user_id AND (p21.game_end_time < p.game_end_time OR (p21.game_end_time = p.game_end_time AND p21.game_id < p.game_id)) ORDER BY p21.game_end_time DESC, p21.game_id DESC LIMIT 1)'.
		' WHERE g.id = ?', $game_id);
	while ($row = $query->next())
	{
		$player = new stdClass();
		list ($player->user_id, $player->role_rating, $player->rating, $player->rating_lock_until, $player->role, $player->won, $is_rating, $tournament_id, $tournament_flags, $game_end_time) = $row;
		if (is_null($player->rating))
		{
			$player->rating = 0;
		}
		if (is_null($player->role_rating))
		{
			$player->role_rating = 0;
		}
		if (is_null($player->rating_lock_until))
		{
			$player->rating_lock_until = $game_end_time;
		}
		$players[] = $player;
	}
	
	if (!isset($is_rating))
	{
		echo get_label('Game [0] has no players. Deleting it makes much sense.', $game_id) . '<br>';
		return;
	}
	
	if (!$is_rating)
	{
		foreach ($players as $player)
		{
			$player->role_rating = adjust_rating($player->role_rating, $player->rating_lock_until, $game_end_time);
			$player->rating = adjust_rating($player->rating, $player->rating_lock_until, $game_end_time);
			Db::exec(get_label('player'), 'UPDATE players SET role_rating_before = ?, rating_before = ?, rating_earned = 0, rating_lock_until = ? WHERE user_id = ? AND game_id = ?', $player->role_rating, $player->rating, $player->rating_lock_until, $player->user_id, $game_id);
		}
		return;
	}
	
	if (count($players) < 10)
	{
		if (is_null($tournament_id))
		{
			$WINNING_K = 2;
			$LOOSING_K = 1.5;
			$LOCK_PERIOD = 1209600; // two weeks
		}
		else if ($tournament_flags & TOURNAMENT_FLAG_ELITE)
		{
			$WINNING_K = 32;
			$LOOSING_K = 24;
			$LOCK_PERIOD = 2419200; // four weeks
		}
		else
		{
			$WINNING_K = 8;
			$LOOSING_K = 6;
			$LOCK_PERIOD = 2419200; // four weeks
		}
	}
	else if (is_null($tournament_id))
	{
		$WINNING_K = 4;
		$LOOSING_K = 3;
		$LOCK_PERIOD = 1209600; // two weeks
	}
	else if ($tournament_flags & TOURNAMENT_FLAG_ELITE)
	{
		$WINNING_K = 64;
		$LOOSING_K = 48;
		$LOCK_PERIOD = 2419200; // four weeks
	}
	else
	{
		$WINNING_K = 16;
		$LOOSING_K = 12;
		$LOCK_PERIOD = 2419200; // four weeks
	}
	
	$maf_sum = 0.0;
	$maf_count = 0;
	$civ_sum = 0.0;
	$civ_count = 0;
	foreach ($players as $player)
	{
		$player->role_rating = adjust_rating($player->role_rating, $player->rating_lock_until, $game_end_time);
		$player->rating = adjust_rating($player->rating, $player->rating_lock_until, $game_end_time);
		$player->rating_lock_until = min(max($player->rating_lock_until + $LOCK_PERIOD, $game_end_time + $LOCK_PERIOD), $game_end_time + MAX_LOCK_TIME);
		switch ($player->role)
		{
		case ROLE_CIVILIAN:
		case ROLE_SHERIFF:
			$civ_sum += $player->role_rating;
			++$civ_count;
			break;
		case ROLE_MAFIA:
		case ROLE_DON:
			$maf_sum += $player->role_rating;
			++$maf_count;
			break;
		}
	}
	
	$civ_odds = 0.0;
	if ($maf_count > 0 && $civ_count > 0)
	{
		$civ_odds = 1.0 / (1.0 + pow(10.0, ($maf_sum / $maf_count - $civ_sum / $civ_count) / 400));
	}
	
	if ($is_rating)
	{
		foreach ($players as $player)
		{
			$rating_earned = 0;
			switch ($player->role)
			{
				case ROLE_CIVILIAN:
				case ROLE_SHERIFF:
					if ($player->won)
					{
						$rating_earned = $WINNING_K * (1 - $civ_odds);
					}
					else
					{
						$rating_earned = - $LOOSING_K * $civ_odds;
					}
					Db::exec(get_label('player'), 'UPDATE players p JOIN users u ON u.id = p.user_id SET p.role_rating_before = ?, p.rating_before = ?, p.rating_earned = ?, p.rating_lock_until = ?, u.flags = u.flags | ' . USER_FLAG_RESET_RED_RATING . ' WHERE p.user_id = ? AND p.game_id = ?', $player->role_rating, $player->rating, $rating_earned, $player->rating_lock_until, $player->user_id, $game_id);
					break;
				case ROLE_MAFIA:
				case ROLE_DON:
					if ($player->won)
					{
						$rating_earned = $WINNING_K * $civ_odds;
					}
					else
					{
						$rating_earned = $LOOSING_K * ($civ_odds - 1);
					}
					Db::exec(get_label('player'), 'UPDATE players p JOIN users u ON u.id = p.user_id SET p.role_rating_before = ?, p.rating_before = ?, p.rating_earned = ?, p.rating_lock_until = ?, u.flags = u.flags | ' . USER_FLAG_RESET_BLACK_RATING . ' WHERE p.user_id = ? AND p.game_id = ?', $player->role_rating, $player->rating, $rating_earned, $player->rating_lock_until, $player->user_id, $game_id);
					break;
			}
		}
	}
	else
	{
	}
	Db::exec(get_label('game'), 'UPDATE games g SET civ_odds = ? WHERE id = ?', $civ_odds, $game_id);
}

?>