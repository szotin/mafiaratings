<?php

require_once 'include/updater.php';
require_once 'include/broadcast.php';
require_once 'include/security.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';
require_once 'include/series.php';
require_once 'include/series_ranking.php';
require_once 'include/geo.php';

define('COMPLETE_EVENTS', 'events');
define('COMPLETE_TOURNAMENTS', 'tournaments');
define('CALCULATE_SERIES', 'calc-series');
define('COMPLETE_SERIES', 'series');

class CompleteCompetitions extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// CompleteCompetitions.broadcasts
	//-------------------------------------------------------------------------------------------------------
	// Asking the broadcasts of the last week what they have to say for themselves.
	//
	// Everything else asks them in passing: a game being written down asks after the recording
	// it belongs to, and the preparation page asks after all of them while somebody is looking
	// at it. That is enough while a tournament is being played and nothing at all afterwards -
	// and afterwards is when the two things worth waiting for arrive. A broadcast that was
	// stopped says so, which closes the recording and keeps the games of the next one out of
	// it; and the finished recording says how long the stream was delayed, which cannot be
	// read until YouTube has processed it, hours after everybody has gone home. Both of them
	// move games that are already written down to where they really belong, which is this
	// script's business - see broadcast_measure_delay().
	//
	// One table of one round is an item. What is worth asking and how often is decided inside
	// broadcast_list(), which is what everything else goes through as well: a recording that
	// has not ended is asked after every couple of minutes, a finished one about once an hour,
	// and one that has told us all it can is not asked at all.
	function broadcasts_task($items_count)
	{
		if (!isset($this->vars->broadcast_event_id))
		{
			$this->vars->broadcast_event_id = 0;
			$this->vars->broadcast_table_num = -1;
		}

		// Of rounds that were played within the window, and no others: an old recording is not
		// going to change its mind, and a video that was never a live broadcast at all has
		// nothing to say however often it is asked.
		$query = new DbQuery(
			'SELECT DISTINCT b.event_id, b.table_num FROM event_broadcasts b' .
			' JOIN events e ON e.id = b.event_id' .
			' WHERE e.start_time + e.duration > UNIX_TIMESTAMP() - ' . BROADCAST_MEASURE_SECONDS .
			' AND (b.event_id > ? OR (b.event_id = ? AND b.table_num > ?))' .
			' ORDER BY b.event_id, b.table_num LIMIT ' . $items_count,
			$this->vars->broadcast_event_id, $this->vars->broadcast_event_id, $this->vars->broadcast_table_num);
		$tables = array();
		while ($row = $query->next())
		{
			list ($this->vars->broadcast_event_id, $this->vars->broadcast_table_num) = $row;
			$tables[] = array((int)$this->vars->broadcast_event_id, (int)$this->vars->broadcast_table_num);
		}

		$count = 0;
		foreach ($tables as $table)
		{
			list ($event_id, $table_num) = $table;
			try
			{
				// Counted from one here, as the games count them; the table they came out of
				// counts them from zero.
				broadcast_list($event_id, $table_num + 1);
			}
			catch (Exception $e)
			{
				$this->error('Broadcasts of event ' . $event_id . ' table ' . ($table_num + 1) . ': ' . $e->getMessage());
			}
			++$count;
			if (!$this->canDoOneMoreItem())
			{
				break;
			}
		}

		if ($count == 0)
		{
			// Round again from the beginning next time this task comes up.
			$this->vars->broadcast_event_id = 0;
			$this->vars->broadcast_table_num = -1;
		}
		return $count;
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
				// Drop exhibition players before scoring — they must not appear in tournament standings.
				Db::exec(get_label('tournament'),
					'DELETE tp FROM tournament_places tp'.
					' JOIN tournament_regs tr ON tr.tournament_id = tp.tournament_id AND tr.user_id = tp.user_id'.
					' WHERE tp.tournament_id = ? AND (tr.flags & ?) <> 0',
					$tournament_id, USER_FLAG_EXHIBITION_PLAYER);

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

				list($max_reg_order) = Db::record(get_label('registration'), 'SELECT COALESCE(MAX(reg_order), 0) FROM tournament_regs WHERE tournament_id = ?', $tournament_id);
				$next_reg_order = (int)$max_reg_order;
				$counter = 0;
				foreach ($players as $player)
				{
					++$next_reg_order;
					$importance = $this->getTournamentImportance($stars, $player->place, $players_count);
					Db::exec(get_label('player'), 'UPDATE tournament_places SET importance = ? WHERE tournament_id = ? AND user_id = ?', $importance, $tournament_id, $player->id);
					Db::exec(get_label('player'),
						'INSERT INTO tournament_regs(tournament_id, user_id, flags, city_id, rating, reg_order) VALUES (?, ?, ?, ?, ?, ?)'.
						' ON DUPLICATE KEY UPDATE rating = ?, flags = (flags | ' . USER_PERM_PLAYER . ') & ~' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED,
						$tournament_id, $player->id, USER_TOURNAMENT_NEW_PLAYER_FLAGS, $player->city_id, $player->rating, $next_reg_order, $player->rating);
						
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
				// We are not adding scoring_id and normalizer_id because we don't want caching here
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
				list($max_reg_order) = Db::record(get_label('registration'), 'SELECT COALESCE(MAX(reg_order), 0) FROM tournament_regs WHERE tournament_id = ?', $tournament_id);
				$next_reg_order = (int)$max_reg_order;
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
					++$next_reg_order;
					Db::exec(get_label('player'),
						'INSERT INTO tournament_places (tournament_id, user_id, place, importance, main_points, bonus_points, shot_points, games_count, flags, wins) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$tournament_id, $player->id, $place, $importance, $main_points, $bonus_points, $shot_points, $player->games_count, $player->nom_flags, $player->wins);
					Db::exec(get_label('player'),
						'INSERT INTO tournament_regs(tournament_id, user_id, flags, city_id, rating, reg_order) VALUES (?, ?, ?, ?, ?, ?)'.
						' ON DUPLICATE KEY UPDATE rating = ?, flags = (flags | ' . USER_PERM_PLAYER . ') & ~' . USER_TOURNAMENT_FLAG_NOT_ACCEPTED,
						$tournament_id, $player->id, USER_TOURNAMENT_NEW_PLAYER_FLAGS, $player->city_id, $player->rating, $next_reg_order, $player->rating);
						
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
	function calculate_series_task($items_count)
	{
		$ranking = new SeriesRanking();
		$count = 0;
		Db::begin();
		$top_query = new DbQuery(
			'SELECT s.id, s.flags, s.stages, g.gaining, s.start_time, s.max_stars FROM series s' .
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version' .
			' WHERE (s.flags & ' . SERIES_FLAG_DIRTY  . ') <> 0 LIMIT ' . $items_count);
		while ($row = $top_query->next())
		{
			list($series_id, $series_flags, $series_stages, $gaining, $series_start, $series_max_stars) = $row;
			$gaining = set_gaining_series_type(json_decode($gaining), series_type($series_start, $series_max_stars));
			$stages = series_stage_count($series_stages);
			$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;


			$sources = $ranking->readSeriesSources($series_id, $stages);
			$extra_points = $ranking->readSeriesExtraPoints($series_id);

			// What this series looks like to whoever counts it as a competition of theirs. It is
			// written before its own standings because it does not depend on them, and it is read
			// by the series above only on their own recount, which this one asks for below.
			$ratings = $ranking->seriesPlayerRatings($sources);
			list ($rating_sum, $rating_sum_20) = $ranking->seriesRatingSums($ratings);
			list ($traveling_distance, $guest_coeff) = $ranking->seriesTravelingSums($sources);
			Db::exec(get_label('series'),
				'UPDATE series SET rating_sum = ?, rating_sum_20 = ?, traveling_distance = ?, guest_coeff = ? WHERE id = ?',
				$rating_sum, $rating_sum_20, $traveling_distance, $guest_coeff, $series_id);

			// What one competition gives one player does not depend on where the table is cut, so
			// it is worked out once here and only bucketed by stage for each ranking below. The
			// gaining evaluator caches per competition, which is the other reason to do it in one
			// pass over the competitions.
			$ranking->scoreSources($sources, $gaining, $stages);

			Db::exec(get_label('series'), 'DELETE FROM series_places WHERE series_id = ?', $series_id);

			// One ranking per stage: the whole series first, then the table as it stood before the
			// last stage was played, and so on down to the base stage alone.
			$players_count = 0;
			for ($cut_stages = 0; $cut_stages < $stages; ++$cut_stages)
			{
				$players = $ranking->rankSeries($sources, $extra_points, $stages, $cut_stages, $max_tournaments);
				$ranking_count = count($players);
				$place = 1;
				foreach ($players as $player)
				{
					// Importance ranks the series of one player against each other, so only the
					// full table carries it - a partial one would compete with its own series.
					$importance = $cut_stages == 0 ? $ranking->getSeriesImportance($place, $ranking_count) : 0;
					Db::exec(get_label('series'),
						'INSERT INTO series_places (series_id, user_id, cut_stages, stage, place, importance, score, tournaments, games, wins, total_cut_off, cut_off, rating)' .
						' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$series_id, $player->id, $cut_stages, $player->stage, $place, $importance,
						$player->points, $player->tournaments, $player->games, $player->wins,
						$player->total_cut_off, $player->cut_off,
						isset($ratings[$player->id]) ? $ratings[$player->id] : 0);
					++$place;
				}
				if ($cut_stages == 0)
				{
					$players_count = $ranking_count;
				}
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