<?php

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/db.php';

function update_game_ratings($game_id)
{
	$players = array();
	$query = new DbQuery('SELECT p.user_id, p1.rating_before + p1.rating_earned, p.role, p.won, g.is_rating FROM players p JOIN games g ON g.id = p.game_id LEFT OUTER JOIN games g1 ON g1.id = (SELECT g2.id FROM players p2 JOIN games g2 ON g2.id = p2.game_id WHERE p2.user_id = p.user_id AND (g2.end_time < g.end_time OR (g2.end_time = g.end_time AND g2.id < g.id)) ORDER BY g2.end_time DESC, g2.id DESC LIMIT 1) LEFT OUTER JOIN players p1 ON p.user_id = p1.user_id AND p1.game_id = g1.id WHERE g.id = ?', $game_id);
	while ($row = $query->next())
	{
		$player = new stdClass();
		list ($player->user_id, $player->rating, $player->role, $player->won, $is_rating) = $row;
		if (is_null($player->rating))
		{
			$player->rating = USER_INITIAL_RATING;
		}
		$players[] = $player;
	}
	
	if (!isset($is_rating))
	{
		echo get_label('Game [0] has no players. Deleting it makes much sense.', $game_id) . '<br>';
		$is_rating = 0;
	}
	
	$maf_sum = 0.0;
	$maf_count = 0;
	$civ_sum = 0.0;
	$civ_count = 0;
	foreach ($players as $player)
	{
		switch ($player->role)
		{
			case ROLE_CIVILIAN:
			case ROLE_SHERIFF:
				$civ_sum += $player->rating;
				++$civ_count;
				break;
			case ROLE_MAFIA:
			case ROLE_DON:
				$maf_sum += $player->rating;
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
			$WINNING_K = 20;
			$LOOSING_K = 15;
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
					break;
			}
			
			// $this->rating_earned += 1;
			if ($player->rating + $rating_earned < USER_INITIAL_RATING)
			{
				$rating_earned = USER_INITIAL_RATING - $player->rating;
			}
			
			Db::exec(get_label('player'), 'UPDATE players SET rating_before = ?, rating_earned = ? WHERE user_id = ? AND game_id = ?', $player->rating, $rating_earned, $player->user_id, $game_id);
		}
	}
	else
	{
		foreach ($players as $player)
		{
			Db::exec(get_label('player'), 'UPDATE players SET rating_before = ?, rating_earned = 0 WHERE user_id = ? AND game_id = ?', $player->rating, $player->user_id, $game_id);
		}
	}
	Db::exec(get_label('game'), 'UPDATE games g SET civ_odds = ? WHERE id = ?', $civ_odds, $game_id);
}

?>