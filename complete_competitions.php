<?php

require_once 'include/updater.php';
require_once 'include/security.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';
require_once 'include/geo.php';

define('COMPLETE_EVENTS', 'events');
define('COMPLETE_TOURNAMENTS', 'tournaments');
define('CALCULATE_SERIES', 'calc-series');
define('COMPLETE_SERIES', 'series');

function compare_series_players($player1, $player2)
{
	if ($player1->points < $player2->points)
	{
		return 1;
	}
	if ($player1->points > $player2->points)
	{
		return -1;
	}
	if ($player1->tournaments > $player2->tournaments)
	{
		return 1;
	}
	if ($player1->tournaments < $player2->tournaments)
	{
		return -1;
	}
	if ($player1->games != 0)
	{
		if ($player2->games != 0)
		{
			$win_rate1 = $player1->wins / $player1->games;
			$win_rate2 = $player2->wins / $player2->games;
			if ($win_rate1 + 0.00001 < $win_rate2)
			{
				return 1;
			}
			else if ($win_rate2 + 0.00001 < $win_rate1)
			{
				return -1;
			}
		}
		return -1;
	}
	if ($player2->games != 0)
	{
		return 1;
	}
	return $player1->id - $player2->id;
}

class CompleteCompetitions extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// CompleteCompetitions.events
	//-------------------------------------------------------------------------------------------------------
	function events_task($items_count)
	{
		$count = 0;
		Db::begin();
		$query = new DbQuery(
			'SELECT e.id, sv.scoring, e.scoring_options, e.flags, t.flags, e.round FROM events e'.
			' JOIN scoring_versions sv ON sv.scoring_id = e.scoring_id AND sv.version = e.scoring_version'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' WHERE e.start_time + e.duration < UNIX_TIMESTAMP() AND (e.flags & ' . EVENT_FLAG_FINISHED . ') = 0 LIMIT ' . $items_count);
		while ($row = $query->next())
		{
			list($event_id, $scoring, $scoring_options, $flags, $tournament_flags, $round_num) = $row;
			$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK); // no hiding tables/bonuses any more - event is complete
			
			$players_count = 0;
			Db::exec(get_label('event'), 'DELETE FROM event_places WHERE event_id = ?', $event_id);
			if (($flags & EVENT_FLAG_CANCELED) == 0)
			{
				$scoring = json_decode($scoring);
				$scoring_options = json_decode($scoring_options);
				
				
				$players = event_scores($event_id, null, SCORING_LOD_PER_GROUP, $scoring, $scoring_options, $tournament_flags, $round_num);
				$players_count = count($players);
				if ($players_count > 0)
				{
					$coeff = log10($players_count) / $players_count;
					for ($number = 0; $number < $players_count; ++$number)
					{
						$player = $players[$number];
						$importance = ($players_count - $number) * $coeff;
						if ($number == 0)
						{
							$importance *= 10;
						}
						else if ($number <= 3)
						{
							$importance *= 5;
						}
						else if ($number <= 10)
						{
							$importance *= 2;
						}
						$main_points = $player->main_points;
						$bonus_points = $player->extra_points + $player->legacy_points + $player->penalty_points;
						$shot_points = $player->night1_points;
						Db::exec(get_label('player'), 'INSERT INTO event_places (event_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $event_id, $player->id, $number + 1, $importance, $main_points, $bonus_points, $shot_points, $player->night1_points, $player->games_count);
					}
				}
			}
			Db::exec(get_label('event'), 'UPDATE events SET flags = flags | ' . EVENT_FLAG_FINISHED .  ' WHERE id = ?', $event_id);
			$this->log('Wrote ' . $players_count . ' players to event ' . $event_id . '. Event is finished.');
			
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// CompleteCompetitions.tournaments
	//-------------------------------------------------------------------------------------------------------
	private function getTournamentImportance($stars, $place, $players_count)
	{
		if ($players_count <= 0)
		{
			return 0;
		}
		$coeff = log10($players_count) * $stars / $players_count;
		$importance = ($players_count - $place + 1) * $coeff;
		if ($place == 1)
		{
			$importance *= 10;
		}
		else if ($place <= 3)
		{
			$importance *= 5;
		}
		else if ($place <= 10)
		{
			$importance *= 2;
		}
		return $importance;
	}

	function tournaments_task($items_count)
	{
		$count = 0;
		Db::begin();
		
		$query = new DbQuery(
			'SELECT t.id, t.flags, sv.scoring, t.scoring_options, t.num_players, nv.normalizer, (SELECT max(st.stars) FROM series_tournaments st WHERE st.tournament_id = t.id) as stars, a.lat, a.lon'.
			' FROM tournaments t' . 
			' JOIN scoring_versions sv ON sv.scoring_id = t.scoring_id AND sv.version = t.scoring_version' .
			' LEFT OUTER JOIN normalizer_versions nv ON nv.normalizer_id = t.normalizer_id AND nv.version = t.normalizer_version' .
			' JOIN addresses a ON a.id = t.address_id'.
			' WHERE t.start_time + t.duration < UNIX_TIMESTAMP() AND (t.flags & ' . TOURNAMENT_FLAG_FINISHED . ') = 0 LIMIT ' . $items_count);
		while ($row = $query->next())
		{
			list($tournament_id, $tournament_flags, $scoring, $scoring_options, $num_players, $normalizer, $stars, $lat, $lon) = $row;
			$tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK); // no hiding tables/bonuses any more - tournament is complete
			$real_count = $players_count = 0;
			$min_games = 0;
			$rating_sum = 0;
			$rating_sum_20 = 0; 
			$traveling_distance = 0;
			$guest_coeff = 0;
			if (($tournament_flags & TOURNAMENT_FLAG_CANCELED))
			{
				Db::exec(get_label('tournament'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
			}
			else if ($tournament_flags & TOURNAMENT_FLAG_MANUAL_SCORE)
			{
				$players = array();
				$query1 = new DbQuery(
					'SELECT tp.user_id, tp.place, uc.id, uc.lat, uc.lon, tc.id, tc.lat, tc.lon, p.rating_before'.
					' FROM tournament_places tp'.
					' JOIN tournaments t ON t.id = tp.tournament_id'.
					' JOIN users u ON u.id = tp.user_id'.
					' JOIN cities uc ON uc.id = u.city_id'.
					' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = tp.user_id AND tu.tournament_id = tp.tournament_id'.
					' LEFT OUTER JOIN cities tc ON tc.id = tu.city_id'.
					' LEFT OUTER JOIN players p ON p.user_id = u.id AND p.game_end_time = (SELECT MAX(game_end_time) FROM players WHERE user_id = u.id AND game_end_time < t.start_time)'.
					' WHERE tp.tournament_id = ?'.
					' ORDER BY p.rating_before DESC', $tournament_id);
				while ($row1 = $query1->next())
				{
					$player = new stdClass();
					list($player->id, $player->place, $user_city_id, $user_lat, $user_lon, $player->lat, $player->lon, $player->city_id, $player->rating) = $row1;
					if (is_null($player->city_id))
					{
						$player->city_id = $user_city_id;
						$player->lat = $user_lat;
						$player->lon = $user_lon;
					}
					if (is_null($player->rating))
					{
						$player->rating = 0;
					}
					$players[] = $player;
				}
				$real_count = $players_count = count($players);
				
				$counter = 0;
				foreach ($players as $player)
				{
					$importance = $this->getTournamentImportance($stars, $player->place, $players_count);
					Db::exec(get_label('player'), 'UPDATE tournament_places SET importance = ? WHERE tournament_id = ? AND user_id = ?', $importance, $tournament_id, $player->id);
					Db::exec(get_label('player'), 
						'INSERT INTO tournament_regs(tournament_id, user_id, flags, city_id, rating) VALUES (?, ?, ?, ?, ?)'.
						' ON DUPLICATE KEY UPDATE rating = ?, flags = (flags | ' . USER_PERM_PLAYER . ') & ~' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED, 
						$tournament_id, $player->id, USER_TOURNAMENT_NEW_PLAYER_FLAGS, $player->city_id, $player->rating, $player->rating);
						
					$td = get_distance($player->lat, $player->lon, $lat, $lon, GEO_MILES);
					$rating_sum += $player->rating;
					if ($counter < 20)
					{
						$rating_sum_20 += $player->rating;
						++$counter;
					}
					$traveling_distance += $td;
					$guest_coeff += log(1 + $td / 600, 2);
				}
			}
			else
			{
				if (is_null($stars))
				{
					$stars = 1;
				}
				$scoring = json_decode($scoring);
				$scoring_options = json_decode($scoring_options);
				if (!is_null($normalizer))
				{
					$normalizer = json_decode($normalizer);
				}
				
				$players = tournament_scores($tournament_id, $tournament_flags, null, SCORING_LOD_PER_GROUP | SCORING_LOD_PER_ROLE, $scoring, $normalizer, $scoring_options);
				$players_count = count($players);
				if ($tournament_flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS)
				{
					$real_count = $num_players;
				}
				else
				{
					list($all_games) = Db::record(get_label('tournament'), 
						'SELECT COUNT(g.id)'.
						' FROM games g'.
						' JOIN events e ON e.id = g.event_id'.
						' WHERE g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.' AND e.round = 0', $tournament_id);
					if (empty($all_games))
					{
						list($all_games) = Db::record(get_label('tournament'), 
							'SELECT COUNT(g.id)'.
							' FROM games g'.
							' WHERE g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING, $tournament_id);
						if (empty($all_games))
						{
							$all_games = $max_games = 0;
						}
						else
						{
							list($max_games) = Db::record(get_label('tournament'), 
								'SELECT MAX(games) FROM (SELECT COUNT(p.game_id) as games FROM players p'.
								' JOIN games g ON g.id = p.game_id'.
								' WHERE g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.' GROUP BY p.user_id) as players', $tournament_id);
							if (empty($max_games))
							{
								$max_games = 0;
							}
						}
					}
					else
					{
						list($max_games) = Db::record(get_label('tournament'), 
							'SELECT MAX(games) FROM (SELECT COUNT(p.game_id) as games FROM players p'.
							' JOIN games g ON g.id = p.game_id'.
							' JOIN events e ON e.id = g.event_id'.
							' WHERE g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.' AND e.round = 0 GROUP BY p.user_id) as players', $tournament_id);
						if (empty($max_games))
						{
							$max_games = 0;
						}
					}
					
					if ($max_games > 0)
					{
						$real_count = round($all_games * 10 / $max_games);
					}
					else
					{
						$real_count = 0;
					}
					//echo '<p>tournament id: ' . $tournament_id . '<br>max_games: ' . $max_games . '<br>all_games: ' . $all_games . '<br>players: ' . $real_count . '<br>players(origin): ' . $players_count . '</p>';
				}
				$real_count = min($real_count, $players_count);
				
				Db::exec(get_label('tournament'), 'DELETE FROM tournament_places WHERE tournament_id = ?', $tournament_id);
				$top20_ratings = array();
				$place = 1;
				for ($number = 0; $number < $real_count; ++$number)
				{
					$player = $players[$number];
					$importance = $this->getTournamentImportance($stars, $place, $real_count);
					$main_points = $player->main_points;
					$bonus_points = $player->extra_points + $player->legacy_points + $player->penalty_points;
					$shot_points = $player->night1_points;
					if (is_null($player->rating))
					{
						$player->rating = 0;
					}
					
					Db::exec(get_label('player'), 
						'INSERT INTO tournament_places (tournament_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count, flags, wins) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
						$tournament_id, $player->id, $place, $importance, $main_points, $bonus_points, $shot_points, $player->games_count, $player->nom_flags, $player->wins);
					Db::exec(get_label('player'), 
						'INSERT INTO tournament_regs(tournament_id, user_id, flags, city_id, rating) VALUES (?, ?, ?, ?, ?)'.
						' ON DUPLICATE KEY UPDATE rating = ?, flags = (flags | ' . USER_PERM_PLAYER . ') & ~' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED, 
						$tournament_id, $player->id, USER_TOURNAMENT_NEW_PLAYER_FLAGS, $player->city_id, $player->rating, $player->rating);
						
					$td = get_distance($player->lat, $player->lon, $lat, $lon, GEO_MILES);
					$rating_sum += $player->rating;
					$traveling_distance += $td;
					$guest_coeff += log(1 + $td / 600, 2);
					if (count($top20_ratings) >= 20)
					{
						$min_index = 0;
						for ($i = 1; $i < 20; ++$i)
						{
							if ($top20_ratings[$i] < $top20_ratings[$min_index])
							{
								$min_index = $i;
							}
						}
						if ($top20_ratings[$min_index] < $player->rating)
						{
							$top20_ratings[$min_index] = $player->rating;
						}
					}
					else
					{
						$top20_ratings[] = $player->rating;
					}
					++$place;
				}
				
				foreach ($top20_ratings as $rating20)
				{
					$rating_sum_20 += $rating20;
				}
			}
			
			if (($tournament_flags & TOURNAMENT_FLAG_FORCE_NUM_PLAYERS) == 0)
			{
				Db::exec(get_label('tournament'), 
					'UPDATE tournaments SET num_players = ?, flags = flags | ' . TOURNAMENT_FLAG_FINISHED .  ', num_regs = ?, rating_sum = ?, rating_sum_20 = ?, traveling_distance = ?, guest_coeff = ?'.
					' WHERE id = ?', $real_count, $real_count, $rating_sum, $rating_sum_20, $traveling_distance, $guest_coeff, $tournament_id);
			}
			else
			{
				Db::exec(get_label('tournament'), 
					'UPDATE tournaments SET flags = flags | ' . TOURNAMENT_FLAG_FINISHED .  ', num_regs = ?, rating_sum = ?, rating_sum_20 = ?, traveling_distance = ?, guest_coeff = ?'.
					' WHERE id = ?', $real_count, $rating_sum, $rating_sum_20, $traveling_distance, $guest_coeff, $tournament_id);
			}
			Db::exec(get_label('series'), 'UPDATE series s JOIN series_tournaments st ON st.series_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE st.tournament_id = ?', $tournament_id);
			
			$log_str = 'Wrote ' . $real_count;
			if ($real_count != $players_count)
			{
				$log_str .= ' (out of ' . $players_count . ')';
			}
			$log_str .= ' players to tournament ' . $tournament_id . '.';
			if ($min_games > 0)
			{
				$log_str .= ' Minimum games requered for a player is ' . $min_games . '.';
			}
			$log_str .= ' Tournament is finished.';
			$this->log($log_str);
			
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// CompleteCompetitions.calculate_series
	//-------------------------------------------------------------------------------------------------------
	private function getSeriesImportance($place, $players_count)
	{
		if ($players_count <= 0)
		{
			return 0;
		}
		$coeff = log10($players_count) * 4 / $players_count;
		$importance = ($players_count - $place + 1) * $coeff;
		if ($place == 1)
		{
			$importance *= 10;
		}
		else if ($place <= 3)
		{
			$importance *= 5;
		}
		else if ($place <= 10)
		{
			$importance *= 2;
		}
		return $importance;
	}

	function calculate_series_task($items_count)
	{
		$count = 0;
		Db::begin();
		$top_query = new DbQuery(
			'SELECT s.id, s.flags, g.gaining, s.finals_id FROM series s' . 
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version' .
			' WHERE (s.flags & ' . SERIES_FLAG_DIRTY  . ') <> 0 LIMIT ' . $items_count);
		while ($row = $top_query->next())
		{
			list($series_id, $series_flags, $gaining, $finals_id) = $row;
			$gaining = json_decode($gaining);
//			print_json($gaining);
			
			$tournament_players = array();
			$query = new DbQuery(
				'SELECT st.tournament_id, COUNT(tp.user_id)'.
				' FROM series_tournaments st'.
				' JOIN tournament_places tp ON tp.tournament_id = st.tournament_id'.
				' WHERE st.series_id = ? AND (st.flags & ' . SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0'.
				' GROUP BY st.tournament_id', $series_id);
			while ($row = $query->next())
			{
				list($tournament_id, $num_players) = $row;
				if ($tournament_id != $finals_id)
				{
					$tournament_players[$tournament_id] = (int)$num_players;
				}
			}
			
			$child_series_players = array();
			$query = new DbQuery(
				'SELECT ss.child_id, COUNT(sp.user_id)'.
				' FROM series_series ss'.
				' JOIN series_places sp ON sp.series_id = ss.child_id'.
				' JOIN series s ON s.id = ss.child_id'.
				' WHERE ss.parent_id = ? AND (ss.flags & ' . SERIES_SERIES_FLAG_NOT_PAYED . ') = 0 AND (s.flags & ' . SERIES_FLAG_FINISHED . ') <> 0'.
				' GROUP BY ss.child_id', $series_id);
			while ($row = $query->next())
			{
				list($child_series_id, $num_players) = $row;
				$child_series_players[$child_series_id] = (int)$num_players;
			}
			
			$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;
			$players = array();
			
			$query = new DbQuery(
				'SELECT st.tournament_id, p.user_id, p.place, p.games_count, p.wins, p.main_points + p.bonus_points + p.shot_points, st.stars, t.rating_sum, t.rating_sum_20, t.traveling_distance, t.guest_coeff'.
				' FROM tournament_places p'.
				' JOIN series_tournaments st ON st.tournament_id = p.tournament_id AND st.series_id = ?'.
				' JOIN tournaments t ON t.id = p.tournament_id'.
				' WHERE (st.flags & ' . SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0'.
				' ORDER BY t.id', $series_id);
			while ($row = $query->next())
			{
				list($tournament_id, $player_id, $place, $games, $wins, $score, $stars, $rating_sum, $rating_sum20, $trav_dist, $guest_coef) = $row;
				if ($tournament_id == $finals_id)
				{
					continue;
				}
				
				if (!isset($players[$player_id]))
				{
					$player = new stdClass();
					$player->id = (int)$player_id;
					$player->tournaments = 0;
					$player->games = 0;
					$player->wins = 0;
					$player->total_cut_off = 0;
					$player->cut_off = 0;
					if ($max_tournaments > 0)
					{
						$player->p = array();
					}
					else
					{
						$player->points = 0;
					}
					$players[$player_id] = $player;
				}
				else
				{
					$player = $players[$player_id];
				}
				
				$points = get_gaining_points($tournament_id, $gaining, $stars, $place, $score, $tournament_players[$tournament_id], $rating_sum, $rating_sum20, $trav_dist, $guest_coef, false);
				if ($max_tournaments > 0)
				{
					if (count($player->p) >= $max_tournaments)
					{
						$min_index = 0;
						for ($i = 1; $i < $max_tournaments; ++$i)
						{
							if ($player->p[$i] < $player->p[$min_index])
							{
								$min_index = $i;
							}
						}
						if ($player->p[$min_index] <= $points)
						{
							$player->total_cut_off += $player->p[$min_index];
							$player->p[$min_index] = $points;
						}
					}
					else
					{
						$player->p[] = $points;
					}
				}
				else
				{
					$player->points += $points;
				}
				++$player->tournaments;
				$player->games += $games;
				$player->wins += $wins;
			}
			
			$query = new DbQuery(
				'SELECT sp.series_id, sp.user_id, sp.place, sp.games, sp.wins, sp.score, ss.stars'.
				' FROM series_places sp'.
				' JOIN series_series ss ON ss.child_id = sp.series_id'.
				' JOIN series s ON s.id = sp.series_id'.
				' WHERE ss.parent_id = ? AND (ss.flags & ' . SERIES_SERIES_FLAG_NOT_PAYED . ') = 0 AND (s.flags & ' . SERIES_FLAG_FINISHED . ') <> 0'.
				' ORDER BY sp.series_id', $series_id);
			while ($row = $query->next())
			{
				list($child_series_id, $player_id, $place, $games, $wins, $score, $stars) = $row;
				if (!isset($players[$player_id]))
				{
					$player = new stdClass();
					$player->id = (int)$player_id;
					$player->tournaments = 0;
					$player->games = 0;
					$player->wins = 0;
					$player->total_cut_off = 0;
					$player->cut_off = 0;
					if ($max_tournaments > 0)
					{
						$player->p = array();
					}
					else
					{
						$player->points = 0;
					}
					$players[$player_id] = $player;
				}
				else
				{
					$player = $players[$player_id];
				}
				
				$points = get_gaining_points($child_series_id, $gaining, $stars, $place, $score, $child_series_players[$child_series_id], 0, 0, 0, 0, true);
				if ($max_tournaments > 0)
				{
					if (count($player->p) >= $max_tournaments)
					{
						$min_index = 0;
						for ($i = 1; $i < $max_tournaments; ++$i)
						{
							if ($player->p[$i] < $player->p[$min_index])
							{
								$min_index = $i;
							}
						}
						if ($player->p[$min_index] <= $points)
						{
							$player->total_cut_off += $player->p[$min_index];
							$player->p[$min_index] = $points;
						}
					}
					else
					{
						$player->p[] = $points;
					}
				}
				else
				{
					$player->points += $points;
				}
				++$player->tournaments;
				$player->games += $games;
				$player->wins += $wins;
			}
			
			$query = new DbQuery('SELECT user_id, points FROM series_extra_points WHERE series_id = ?', $series_id);
			while ($row = $query->next())
			{
				list($player_id, $points) = $row;
				if (!isset($players[$player_id]))
				{
					$player = new stdClass();
					$player->id = (int)$player_id;
					$player->tournaments = 0;
					$player->games = 0;
					$player->wins = 0;
					$player->total_cut_off = 0;
					$player->cut_off = 0;
					if ($max_tournaments > 0)
					{
						$player->p = array();
					}
					else
					{
						$player->points = 0;
					}
					$players[$player_id] = $player;
				}
				else
				{
					$player = $players[$player_id];
				}
				
				if ($max_tournaments > 0)
				{
					if (count($player->p) >= $max_tournaments)
					{
						$min_index = 0;
						for ($i = 1; $i < $max_tournaments; ++$i)
						{
							if ($player->p[$i] < $player->p[$min_index])
							{
								$min_index = $i;
							}
						}
						if ($player->p[$min_index] <= $points)
						{
							$player->total_cut_off += $player->p[$min_index];
							$player->p[$min_index] = $points;
						}
					}
					else
					{
						$player->p[] = $points;
					}
				}
				else
				{
					$player->points += $points;
				}
				++$player->tournaments;
			}
			
			if ($max_tournaments > 0)
			{
				foreach ($players as $player)
				{
					$player->points = 0;
					$player->cut_off = 1000000000;
					foreach ($player->p as $p)
					{
						$player->points += $p;
						$player->cut_off = min($player->cut_off, $p);
					}
					if (count($player->p) < $max_tournaments)
					{
						$player->cut_off = 0;
					}
					unset($player->p);
				}
			}
			
			usort($players, "compare_series_players");
			
			Db::exec(get_label('series'), 'DELETE FROM series_places WHERE series_id = ?', $series_id);
			$place = 1;
			$players_count = count($players);
			foreach ($players as $player)
			{
				$importance = $this->getSeriesImportance($place, $players_count);
				Db::exec(get_label('series'), 'INSERT INTO series_places (series_id, user_id, place, importance, score, tournaments, games, wins, total_cut_off, cut_off) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 
					$series_id, $player->id, $place, $importance, $player->points, $player->tournaments, $player->games, $player->wins, $player->total_cut_off, $player->cut_off);
				++$place;
			}
			
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags & ' . ~SERIES_FLAG_DIRTY .  ' WHERE id = ?', $series_id);
			if ($series_flags & SERIES_FLAG_FINISHED)
			{
				Db::exec(get_label('series'), 'UPDATE series s JOIN series_series ss ON ss.parent_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE ss.child_id = ?', $series_id);
			}
			
			$this->log('Wrote ' . $players_count . ' players to series ' . $series_id . '. Series is up to date.');
			
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// CompleteCompetitions.series
	//-------------------------------------------------------------------------------------------------------
	function series_task($items_count)
	{
		$count = 0;
		Db::begin();
		$query = new DbQuery(
			'SELECT s.id FROM series s WHERE s.start_time + s.duration < UNIX_TIMESTAMP() AND (s.flags & ' . SERIES_FLAG_FINISHED . ') = 0 LIMIT ' . $items_count);
		while ($row = $query->next())
		{
			list($series_id) = $row;
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_FINISHED .  ' WHERE id = ?', $series_id);
			Db::exec(get_label('series'), 'UPDATE series s JOIN series_series ss ON ss.parent_id = s.id SET s.flags = s.flags | ' . SERIES_FLAG_DIRTY .  ' WHERE ss.child_id = ?', $series_id);
			$this->log('Series ' . $series_id . ' is finished.');
			
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}
		Db::commit();
		return $count;
	}
}

$updater = new CompleteCompetitions();
$updater->run();

?>