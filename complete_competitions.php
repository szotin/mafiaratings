<?php

require_once 'include/updater.php';
require_once 'include/broadcast.php';
require_once 'include/security.php';
require_once 'include/scoring.php';
require_once 'include/gaining.php';
require_once 'include/series.php';
require_once 'include/geo.php';

define('COMPLETE_EVENTS', 'events');
define('COMPLETE_TOURNAMENTS', 'tournaments');
define('CALCULATE_SERIES', 'calc-series');
define('COMPLETE_SERIES', 'series');

// The running total of one stage for one player: the best maxTournaments points of that stage,
// and what fell out of them.
//
// The points are offered one at a time, in the order the competitions were played, and the rule is
// the one the series standings have always used: the first maxTournaments are kept, and a later
// one takes the place of the smallest kept point when it is not smaller. A point that never makes
// it into the kept set is simply dropped - only an evicted one counts as cut off. A maximum of
// zero means every point counts.
class SeriesStageTotal
{
	public $count = 0;
	public $total_cut_off = 0;
	private $max;
	private $p = array();

	function __construct($max_tournaments)
	{
		$this->max = (int)$max_tournaments;
	}

	function add($points)
	{
		++$this->count;
		if ($this->max <= 0 || count($this->p) < $this->max)
		{
			$this->p[] = $points;
			return;
		}
		$min_index = 0;
		for ($i = 1; $i < $this->max; ++$i)
		{
			if ($this->p[$i] < $this->p[$min_index])
			{
				$min_index = $i;
			}
		}
		if ($this->p[$min_index] <= $points)
		{
			$this->total_cut_off += $this->p[$min_index];
			$this->p[$min_index] = $points;
		}
	}

	function points()
	{
		$points = 0;
		foreach ($this->p as $p)
		{
			$points += $p;
		}
		return $points;
	}

	// The smallest point that still counts - what the next one has to beat. There is nothing to
	// beat until the kept set is full, and nothing at all when every point counts.
	function cut_off()
	{
		if ($this->max > 0 && count($this->p) >= $this->max)
		{
			return min($this->p);
		}
		return 0;
	}
}

function compare_series_players($player1, $player2)
{
	// The deciding stage comes first and nothing outweighs it: whoever went further stands above,
	// and the points only order the players of one stage among themselves. That is what makes "a
	// finalist is always above a semifinalist" true by construction - there is no sum across
	// stages, so there are no coefficients to tune for it.
	if ($player1->stage_order != $player2->stage_order)
	{
		return $player2->stage_order - $player1->stage_order;
	}
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

	// One competition a series counts: a tournament of it, or a subseries entered into it.
	private function seriesSource($id, $stage, $stars, $is_series)
	{
		$source = new stdClass();
		$source->id = (int)$id;
		$source->stage = (int)$stage;
		$source->stars = $stars;
		$source->is_series = $is_series;
		$source->rating_sum = 0;
		$source->rating_sum_20 = 0;
		$source->traveling_distance = 0;
		$source->guest_coeff = 0;
		$source->num_players = 0;
		// The furthest stage this competition has, in the order its stages are played. For a
		// tournament that is its deepest round, for a subseries the last stage of its ladder.
		$source->max_stage = 0;
		$source->rows = array();
		return $source;
	}

	private function seriesSourceRow($user_id, $place, $games, $wins, $score)
	{
		$row = new stdClass();
		$row->user_id = (int)$user_id;
		$row->place = (int)$place;
		$row->games = (int)$games;
		$row->wins = (int)$wins;
		$row->score = $score;
		$row->points = 0;
		// How far this player got in this competition, in the order its stages are played, and
		// what they scored in each of those stages. The scores are only known for a subseries -
		// see readSeriesSources() - and a tournament leaves them null.
		$row->stage = 0;
		$row->stage_scores = NULL;
		return $row;
	}

	// round => its position in the order the rounds of a tournament are played. The main round,
	// zero, is played first; of the rest a bigger number is an earlier round, the final being one.
	//
	// It is a dense rank over the rounds that are actually there rather than arithmetic on their
	// numbers, so a tournament that skips a round does not leave a gap in the count.
	private function roundPlayOrder($rounds)
	{
		$has_main = false;
		$knockout = array();
		foreach ($rounds as $round)
		{
			if ($round == 0)
			{
				$has_main = true;
			}
			else
			{
				$knockout[] = $round;
			}
		}
		rsort($knockout);

		$order = array();
		$next = 0;
		if ($has_main)
		{
			$order[0] = $next++;
		}
		foreach ($knockout as $round)
		{
			$order[$round] = $next++;
		}
		return $order;
	}

	// How far each player got in each tournament of the series, and how far there was to get.
	//
	// A substitute is not in the count of the tournament at all, so the round they played as one
	// does not move them up its ladder.
	private function readTournamentStages($series_id)
	{
		$rounds = array();
		$played = array();
		$query = new DbQuery(
			'SELECT e.tournament_id, e.round, ep.user_id' .
			' FROM events e' .
			' JOIN event_places ep ON ep.event_id = e.id' .
			' JOIN series_tournaments st ON st.tournament_id = e.tournament_id AND st.series_id = ?' .
			' LEFT OUTER JOIN event_regs er ON er.event_id = e.id AND er.user_id = ep.user_id' .
			' WHERE (er.flags IS NULL OR (er.flags & ' . USER_FLAG_SUBSTITUTE . ') = 0)', $series_id);
		while ($row = $query->next())
		{
			list($tournament_id, $round, $user_id) = $row;
			$tournament_id = (int)$tournament_id;
			$round = (int)$round;
			$user_id = (int)$user_id;
			$rounds[$tournament_id][$round] = true;
			$played[$tournament_id][$user_id][$round] = true;
		}

		$stages = array();
		foreach ($rounds as $tournament_id => $present)
		{
			$order = $this->roundPlayOrder(array_keys($present));
			$entry = new stdClass();
			$entry->max_stage = count($order) - 1;
			$entry->players = array();
			foreach ($played[$tournament_id] as $user_id => $user_rounds)
			{
				$stage = 0;
				foreach (array_keys($user_rounds) as $round)
				{
					if ($order[$round] > $stage)
					{
						$stage = $order[$round];
					}
				}
				$entry->players[$user_id] = $stage;
			}
			$stages[$tournament_id] = $entry;
		}
		return $stages;
	}

	// Dense places over the participations that count, and how many of them there are.
	//
	// The place stored on a competition counts everybody who was in it. A player who is out of the
	// count of this series - an exhibition player, or a substitute in this tournament - must not be
	// there at all as far as the series is concerned: whoever stood below them moves up, and the
	// field they are all measured against shrinks. Without this the others keep the place and the
	// field size they had with that player in, so everybody's points come out slightly wrong -
	// which is what the series standings have been doing with exhibition players all along.
	//
	// Equal places stay equal: the dense place is one plus the number of counted participations
	// standing strictly above.
	private function closeSeriesSource($source)
	{
		$source->num_players = count($source->rows);
		$places = array();
		foreach ($source->rows as $row)
		{
			$places[] = $row->place;
		}
		sort($places);
		$dense = array();
		foreach ($places as $i => $place)
		{
			if (!isset($dense[$place]))
			{
				// The places are sorted, so the first index a place appears at is how many stand
				// strictly above it.
				$dense[$place] = $i + 1;
			}
		}
		foreach ($source->rows as $row)
		{
			$row->place = $dense[$row->place];
		}
	}

	// Everything the series counts, in the order the standings have always accumulated it: the
	// tournaments by id, then the subseries by id. Extra points are not a competition of the
	// series and are read separately.
	private function readSeriesSources($series_id, $stages)
	{
		$sources = array();
		$tournament_stages = $this->readTournamentStages($series_id);

		// series_regs holds the exhibition flag, which takes a player out of the whole series;
		// tournament_regs holds the substitute flag, which takes them out of this one tournament
		// only - they still stand in the table of the tournament itself.
		$query = new DbQuery(
			'SELECT st.tournament_id, st.stage, st.stars, t.rating_sum, t.rating_sum_20, t.traveling_distance, t.guest_coeff,' .
			' p.user_id, p.place, p.games_count, p.wins, p.main_points + p.bonus_points + p.shot_points' .
			' FROM tournament_places p' .
			' JOIN series_tournaments st ON st.tournament_id = p.tournament_id AND st.series_id = ?' .
			' JOIN tournaments t ON t.id = p.tournament_id' .
			' LEFT OUTER JOIN series_regs sr ON sr.series_id = st.series_id AND sr.user_id = p.user_id' .
			' LEFT OUTER JOIN tournament_regs tr ON tr.tournament_id = p.tournament_id AND tr.user_id = p.user_id' .
			' WHERE (st.flags & ' . SERIES_TOURNAMENT_FLAG_NOT_PAYED . ') = 0' .
			' AND (sr.flags IS NULL OR (sr.flags & ' . USER_FLAG_EXHIBITION_PLAYER . ') = 0)' .
			' AND (tr.flags IS NULL OR (tr.flags & ' . USER_FLAG_SUBSTITUTE . ') = 0)' .
			' ORDER BY st.tournament_id', $series_id);
		while ($row = $query->next())
		{
			list($tournament_id, $stage, $stars, $rating_sum, $rating_sum20, $trav_dist, $guest_coef,
				$user_id, $place, $games, $wins, $score) = $row;
			$key = 't' . (int)$tournament_id;
			if (!isset($sources[$key]))
			{
				$source = $this->seriesSource($tournament_id, series_stage($stages, $stage), $stars, false);
				$source->rating_sum = $rating_sum;
				$source->rating_sum_20 = $rating_sum20;
				$source->traveling_distance = $trav_dist;
				$source->guest_coeff = $guest_coef;
				if (isset($tournament_stages[(int)$tournament_id]))
				{
					$source->max_stage = $tournament_stages[(int)$tournament_id]->max_stage;
				}
				$sources[$key] = $source;
			}
			$source_row = $this->seriesSourceRow($user_id, $place, $games, $wins, $score);
			if (isset($tournament_stages[(int)$tournament_id]->players[$source_row->user_id]))
			{
				$source_row->stage = $tournament_stages[(int)$tournament_id]->players[$source_row->user_id];
			}
			$sources[$key]->rows[] = $source_row;
		}

		// A subseries has no stage of its own - series_series says nothing about stages - so it
		// counts in the base stage, which is where a subseries of a season belongs anyway. What
		// enters this series is its full ranking, the one cut at nothing.
		//
		// Its partial rankings are read all the same, because together they say what a player
		// scored in each single stage of it, which is what score(n) answers: the ranking cut at a
		// stage places a player by their furthest stage, so when that stage is the one asked
		// about, its score is the points of it, and a player who never got that far has none.
		$child_stage_scores = array();
		$query = new DbQuery(
			'SELECT sp.series_id, ss.stars, sp.user_id, sp.cut_stages, sp.stage, sp.place, sp.games, sp.wins, sp.score, s.stages' .
			' FROM series_places sp' .
			' JOIN series_series ss ON ss.child_id = sp.series_id' .
			' JOIN series s ON s.id = sp.series_id' .
			' LEFT OUTER JOIN series_regs sr ON sr.series_id = ss.parent_id AND sr.user_id = sp.user_id' .
			' WHERE ss.parent_id = ?' .
			' AND (ss.flags & ' . SERIES_SERIES_FLAG_NOT_PAYED . ') = 0 AND (s.flags & ' . SERIES_FLAG_FINISHED . ') <> 0' .
			' AND (sr.flags IS NULL OR (sr.flags & ' . USER_FLAG_EXHIBITION_PLAYER . ') = 0)' .
			' ORDER BY sp.series_id', $series_id);
		while ($row = $query->next())
		{
			list($child_series_id, $stars, $user_id, $cut_stages, $stage, $place, $games, $wins, $score, $child_stages) = $row;
			$user_id = (int)$user_id;
			$child_stages = series_stage_count($child_stages);
			$key = 's' . (int)$child_series_id;

			$stage_order = series_stage_order($child_stages, $stage);
			$asked_stage = $child_stages - 1 - (int)$cut_stages;
			$child_stage_scores[$key][$user_id][$asked_stage] = $stage_order == $asked_stage ? $score : 0;

			if ((int)$cut_stages != 0)
			{
				continue;
			}
			if (!isset($sources[$key]))
			{
				$source = $this->seriesSource($child_series_id, SERIES_STAGE_BASE, $stars, true);
				$source->max_stage = $child_stages - 1;
				$sources[$key] = $source;
			}
			$source_row = $this->seriesSourceRow($user_id, $place, $games, $wins, $score);
			$source_row->stage = $stage_order;
			$sources[$key]->rows[] = $source_row;
		}

		foreach ($sources as $key => $source)
		{
			foreach ($source->rows as $row)
			{
				if (isset($child_stage_scores[$key][$row->user_id]))
				{
					$scores = $child_stage_scores[$key][$row->user_id];
					ksort($scores);
					$row->stage_scores = $scores;
				}
			}
			$this->closeSeriesSource($source);
		}
		return $sources;
	}

	private function readSeriesExtraPoints($series_id)
	{
		$extra_points = array();
		$query = new DbQuery(
			'SELECT ep.user_id, ep.points, ep.stage FROM series_extra_points ep' .
			' LEFT OUTER JOIN series_regs sr ON sr.series_id = ep.series_id AND sr.user_id = ep.user_id' .
			' WHERE ep.series_id = ?' .
			' AND (sr.flags IS NULL OR (sr.flags & ' . USER_FLAG_EXHIBITION_PLAYER . ') = 0)', $series_id);
		while ($row = $query->next())
		{
			list($user_id, $points, $stage) = $row;
			$entry = new stdClass();
			$entry->user_id = (int)$user_id;
			$entry->points = $points;
			$entry->stage = (int)$stage;
			$extra_points[] = $entry;
		}
		return $extra_points;
	}

	private function seriesPlayer(&$players, $user_id)
	{
		$user_id = (int)$user_id;
		if (!isset($players[$user_id]))
		{
			$player = new stdClass();
			$player->id = $user_id;
			$player->tournaments = 0;
			$player->games = 0;
			$player->wins = 0;
			$player->stage_totals = array();
			$players[$user_id] = $player;
		}
		return $players[$user_id];
	}

	private function seriesPlayerStage($player, $stage, $max_tournaments)
	{
		if (!isset($player->stage_totals[$stage]))
		{
			$player->stage_totals[$stage] = new SeriesStageTotal($max_tournaments);
		}
		return $player->stage_totals[$stage];
	}

	// One ranking of the series, leaving out the last $cut_stages stages of its ladder.
	//
	// Inside a stage the points add up over the best maxTournaments of that stage - the existing
	// parameter now applies stage by stage rather than to the series as a whole. Between stages
	// nothing adds up: the deciding stage of a player is the furthest one where anything counted
	// for them, their score is the points of that stage alone, and the order is the deciding stage
	// first and those points second.
	private function rankSeries($sources, $extra_points, $stages, $cut_stages, $max_tournaments)
	{
		$top_order = $stages - 1 - $cut_stages;
		$players = array();
		foreach ($sources as $source)
		{
			if (series_stage_order($stages, $source->stage) > $top_order)
			{
				continue;
			}
			foreach ($source->rows as $row)
			{
				$player = $this->seriesPlayer($players, $row->user_id);
				$this->seriesPlayerStage($player, $source->stage, $max_tournaments)->add($row->points);
				++$player->tournaments;
				$player->games += $row->games;
				$player->wins += $row->wins;
			}
		}

		// Extra points are not a competition of the series but they do belong to a stage of it,
		// so a ranking that cuts that stage away leaves them out with everything else of it.
		foreach ($extra_points as $entry)
		{
			if (series_stage_order($stages, $entry->stage) > $top_order)
			{
				continue;
			}
			$player = $this->seriesPlayer($players, $entry->user_id);
			$this->seriesPlayerStage($player, series_stage($stages, $entry->stage), $max_tournaments)->add($entry->points);
			++$player->tournaments;
		}

		foreach ($players as $player)
		{
			$player->stage = SERIES_STAGE_BASE;
			$player->stage_order = -1;
			foreach ($player->stage_totals as $stage => $total)
			{
				$order = series_stage_order($stages, $stage);
				if ($order > $player->stage_order)
				{
					$player->stage_order = $order;
					$player->stage = $stage;
				}
			}
			$total = $player->stage_totals[$player->stage];
			$player->points = $total->points();
			$player->cut_off = $total->cut_off();
			$player->total_cut_off = $total->total_cut_off;
			unset($player->stage_totals);
		}

		$players = array_values($players);
		usort($players, "compare_series_players");
		return $players;
	}

	function calculate_series_task($items_count)
	{
		$count = 0;
		Db::begin();
		$top_query = new DbQuery(
			'SELECT s.id, s.flags, s.stages, g.gaining FROM series s' .
			' JOIN gaining_versions g ON g.gaining_id = s.gaining_id AND g.version = s.gaining_version' .
			' WHERE (s.flags & ' . SERIES_FLAG_DIRTY  . ') <> 0 LIMIT ' . $items_count);
		while ($row = $top_query->next())
		{
			list($series_id, $series_flags, $series_stages, $gaining) = $row;
			$gaining = json_decode($gaining);
			$stages = series_stage_count($series_stages);
			$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;


			$sources = $this->readSeriesSources($series_id, $stages);
			$extra_points = $this->readSeriesExtraPoints($series_id);

			// What one competition gives one player does not depend on where the table is cut, so
			// it is worked out once here and only bucketed by stage for each ranking below. The
			// gaining evaluator caches per competition, which is the other reason to do it in one
			// pass over the competitions.
			foreach ($sources as $source)
			{
				$competition_vars = array(
					'maxStage' => $source->max_stage,
					'currentStage' => series_stage_order($stages, $source->stage),
					'maxCurrentStage' => $stages - 1);
				foreach ($source->rows as $row)
				{
					$player_vars = array('stage' => $row->stage);
					if (is_array($row->stage_scores))
					{
						$player_vars['stageScores'] = $row->stage_scores;
					}
					$row->points = get_gaining_points(
						$source->id, $gaining, $source->stars, $row->place, $row->score, $source->num_players,
						$source->rating_sum, $source->rating_sum_20, $source->traveling_distance,
						$source->guest_coeff, $source->is_series, $competition_vars, $player_vars);
				}
			}

			Db::exec(get_label('series'), 'DELETE FROM series_places WHERE series_id = ?', $series_id);

			// One ranking per stage: the whole series first, then the table as it stood before the
			// last stage was played, and so on down to the base stage alone.
			$players_count = 0;
			for ($cut_stages = 0; $cut_stages < $stages; ++$cut_stages)
			{
				$players = $this->rankSeries($sources, $extra_points, $stages, $cut_stages, $max_tournaments);
				$ranking_count = count($players);
				$place = 1;
				foreach ($players as $player)
				{
					// Importance ranks the series of one player against each other, so only the
					// full table carries it - a partial one would compete with its own series.
					$importance = $cut_stages == 0 ? $this->getSeriesImportance($place, $ranking_count) : 0;
					Db::exec(get_label('series'),
						'INSERT INTO series_places (series_id, user_id, cut_stages, stage, place, importance, score, tournaments, games, wins, total_cut_off, cut_off)' .
						' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$series_id, $player->id, $cut_stages, $player->stage, $place, $importance,
						$player->points, $player->tournaments, $player->games, $player->wins,
						$player->total_cut_off, $player->cut_off);
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