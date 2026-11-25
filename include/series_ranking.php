<?php

require_once __DIR__ . '/series.php';
require_once __DIR__ . '/gaining.php';

// How the standings of a series are worked out: what each competition of it gives each player, and
// how those add up into one ranking per stage. complete_competitions.php writes the result to
// series_places; series_competition.php plays the same ranking back moment by moment.

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
	// Whoever played at all stands above whoever played nothing, and of two who played it is the
	// better win rate - near enough rates counting as the same one.
	//
	// Two players who are equal all the way down are ordered by id, which is nothing but a way of
	// ordering them the same way every time. It has to be reached: saying "the first one is above"
	// about a pair that is equal says the same thing about the pair the other way round, and a
	// sort given such an answer puts a tied group in whatever order its own bookkeeping happens to
	// produce - so the table was not the same table after an unrelated change elsewhere.
	if ($player1->games != 0)
	{
		if ($player2->games == 0)
		{
			return -1;
		}
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
	else if ($player2->games != 0)
	{
		return 1;
	}
	return $player1->id - $player2->id;
}

class SeriesRanking
{
	public function getSeriesImportance($place, $players_count)
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
	public function seriesSource($id, $stage, $stars, $is_series)
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
		// When this competition was played, so that of the competitions of a series the latest one
		// a player counted in can be told - see seriesPlayerRatings().
		$source->time = 0;
		// And when it was over: the moment its results are known, which is when the race of the series
		// - see include/race.php - counts them in.
		$source->end_time = 0;
		// The furthest stage this competition has, in the order its stages are played. For a
		// tournament that is its deepest round, for a subseries the last stage of its ladder.
		$source->max_stage = 0;
		$source->rows = array();
		return $source;
	}

	public function seriesSourceRow($user_id, $place, $games, $wins, $score, $rating = 0)
	{
		$row = new stdClass();
		$row->user_id = (int)$user_id;
		$row->place = (int)$place;
		$row->games = (int)$games;
		$row->wins = (int)$wins;
		$row->score = $score;
		// What this player's rating was at this competition, above the thousand everybody starts
		// at - the same number tournament_regs stores. It says nothing about the standings and is
		// only there to measure the strength of the field, see seriesRatingSums().
		$row->rating = is_numeric($rating) ? (float)$rating : 0;
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
	public function roundPlayOrder($rounds)
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
	public function readTournamentStages($series_id)
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
	public function closeSeriesSource($source)
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
	public function readSeriesSources($series_id, $stages)
	{
		$sources = array();
		$tournament_stages = $this->readTournamentStages($series_id);

		// series_regs holds the exhibition flag, which takes a player out of the whole series;
		// tournament_regs holds the substitute flag, which takes them out of this one tournament
		// only - they still stand in the table of the tournament itself.
		$query = new DbQuery(
			'SELECT st.tournament_id, st.stage, st.stars, t.rating_sum, t.rating_sum_20, t.traveling_distance, t.guest_coeff, t.start_time, t.start_time + t.duration,' .
			' p.user_id, p.place, p.games_count, p.wins, p.main_points + p.bonus_points + p.shot_points, tr.rating' .
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
			list($tournament_id, $stage, $stars, $rating_sum, $rating_sum20, $trav_dist, $guest_coef, $start_time, $end_time,
				$user_id, $place, $games, $wins, $score, $rating) = $row;
			$key = 't' . (int)$tournament_id;
			if (!isset($sources[$key]))
			{
				$source = $this->seriesSource($tournament_id, series_stage($stages, $stage), $stars, false);
				$source->rating_sum = $rating_sum;
				$source->rating_sum_20 = $rating_sum20;
				$source->traveling_distance = $trav_dist;
				$source->guest_coeff = $guest_coef;
				$source->time = (int)$start_time;
				$source->end_time = (int)$end_time;
				if (isset($tournament_stages[(int)$tournament_id]))
				{
					$source->max_stage = $tournament_stages[(int)$tournament_id]->max_stage;
				}
				$sources[$key] = $source;
			}
			$source_row = $this->seriesSourceRow($user_id, $place, $games, $wins, $score, $rating);
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
			'SELECT sp.series_id, ss.stars, sp.user_id, sp.cut_stages, sp.stage, sp.place, sp.games, sp.wins, sp.score, s.stages,' .
			' sp.rating, s.rating_sum, s.rating_sum_20, s.traveling_distance, s.guest_coeff, s.start_time, s.start_time + s.duration' .
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
			list($child_series_id, $stars, $user_id, $cut_stages, $stage, $place, $games, $wins, $score, $child_stages,
				$rating, $rating_sum, $rating_sum20, $trav_dist, $guest_coef, $start_time, $end_time) = $row;
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
				// A subseries measures its field the same way a tournament does, and keeps the
				// numbers on its own row for whoever counts it - see seriesRatingSums(). Without
				// this a subseries came to the gaining formula as a field of nothing but
				// thousand-rated players, so a five-star subseries paid its winner the least a
				// five-star competition can pay.
				$source->rating_sum = $rating_sum;
				$source->rating_sum_20 = $rating_sum20;
				$source->traveling_distance = $trav_dist;
				$source->guest_coeff = $guest_coef;
				$source->time = (int)$start_time;
				$source->end_time = (int)$end_time;
				$source->max_stage = $child_stages - 1;
				$sources[$key] = $source;
			}
			$source_row = $this->seriesSourceRow($user_id, $place, $games, $wins, $score, $rating);
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

	// What each player of the series was rated, above the thousand everybody starts at.
	//
	// A tournament freezes the rating of a player when it is completed, and that is the number its
	// own strength is measured with. A series is played over a span rather than at a moment, so
	// the rating it takes for a player is the one they carried at the latest of its competitions
	// they counted in - the closest thing to "their rating at this competition" a series has. Ties
	// on time keep the first competition read, which is the lower id.
	public function seriesPlayerRatings($sources)
	{
		$ratings = array();
		$times = array();
		foreach ($sources as $source)
		{
			foreach ($source->rows as $row)
			{
				if (!isset($ratings[$row->user_id]) || $source->time > $times[$row->user_id])
				{
					$ratings[$row->user_id] = $row->rating;
					$times[$row->user_id] = $source->time;
				}
			}
		}
		return $ratings;
	}

	// The strength of the field of the series: the ratings of everybody it counts, and the ratings
	// of its twenty strongest - the two numbers ratingSum() and ratingSum20() answer with, held on
	// the series the same way a tournament holds them on itself.
	//
	// Extra points are not a competition and bring nobody into the field: a player who has nothing
	// but extra points in the series was at none of its competitions and has no rating in it.
	public function seriesRatingSums($ratings)
	{
		$sum = 0;
		foreach ($ratings as $rating)
		{
			$sum += $rating;
		}
		$top = array_values($ratings);
		rsort($top);
		$sum_20 = 0;
		for ($i = 0; $i < count($top) && $i < 20; ++$i)
		{
			$sum_20 += $top[$i];
		}
		return array($sum, $sum_20);
	}

	// How far the players of the series travelled to it and how much of a guest they were, summed
	// over its competitions. A series has no place of its own to measure a distance to, so it is
	// the distances of the competitions played in it that add up - and for a subseries among them,
	// the sum it made of its own.
	public function seriesTravelingSums($sources)
	{
		$distance = 0;
		$guest_coeff = 0;
		foreach ($sources as $source)
		{
			$distance += $source->traveling_distance;
			$guest_coeff += $source->guest_coeff;
		}
		return array($distance, $guest_coeff);
	}

	public function readSeriesExtraPoints($series_id)
	{
		$extra_points = array();
		$query = new DbQuery(
			'SELECT ep.user_id, ep.points, ep.stage, ep.time FROM series_extra_points ep' .
			' LEFT OUTER JOIN series_regs sr ON sr.series_id = ep.series_id AND sr.user_id = ep.user_id' .
			' WHERE ep.series_id = ?' .
			' AND (sr.flags IS NULL OR (sr.flags & ' . USER_FLAG_EXHIBITION_PLAYER . ') = 0)', $series_id);
		while ($row = $query->next())
		{
			list($user_id, $points, $stage, $time) = $row;
			$entry = new stdClass();
			$entry->user_id = (int)$user_id;
			$entry->points = $points;
			$entry->stage = (int)$stage;
			$entry->time = (int)$time;
			$extra_points[] = $entry;
		}
		return $extra_points;
	}

	public function seriesPlayer(&$players, $user_id)
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

	public function seriesPlayerStage($player, $stage, $max_tournaments)
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
	public function rankSeries($sources, $extra_points, $stages, $cut_stages, $max_tournaments)
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

	// What every row of every source gives its player - the gaining system of the series applied to
	// the place and the score the player had in that competition.
	public function scoreSources($sources, $gaining, $stages)
	{
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
	}
}

?>