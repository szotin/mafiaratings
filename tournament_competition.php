<?php

require_once 'include/tournament.php';
require_once 'include/general_page_base.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';
require_once 'include/race.php';
require_once 'include/rounds.php';

// The race of a tournament: its top twenty after every game, played back. The chart of a few
// players it used to be is tournament_competition_chart.php.
class Page extends TournamentPageBase
{
	private $race;

	protected function prepare()
	{
		parent::prepare();

		list($scoring) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		if ($this->normalizer_id != NULL && $this->normalizer_id > 0)
		{
			list($normalizer) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
		}
		else
		{
			$normalizer = '{}';
		}
		$scoring = json_decode($scoring);
		$scoring->id = (int)$this->scoring_id; // it is needed for caching
		$scoring->version = (int)$this->scoring_version;
		$normalizer = json_decode($normalizer);
		$normalizer->id = (int)$this->normalizer_id;
		$normalizer->version = (int)$this->normalizer_version;
		$players = tournament_scores($this->id, $this->flags, NULL, SCORING_LOD_HISTORY, $scoring, $normalizer, json_decode($this->scoring_options));

		$pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		$id = $this->id;
		$club_id = $this->club_id;
		$params = '&id=' . $this->id . $this->show_all;
		$this->race = race_scoring_players($players,
			function($p) use ($pic, $id, $club_id)
			{
				$pic->
					set($p->id, $p->name, $p->tournament_reg_flags, 't' . $id)->
					set($p->id, $p->name, $p->club_reg_flags, 'c' . $club_id)->
					set($p->id, $p->name, $p->flags);
				return card_avatar_of($pic, $p->id, $p->name, '');
			},
			function($p) use ($params)
			{
				return 'tournament_player.php?bck=1&user_id=' . $p->id . $params;
			});
	}

	protected function show_body()
	{
		if (!$this->show_hidden_table_message())
		{
			return;
		}
		echo '<div class="toolbar">' . race_switch('tournament_competition.php?id=' . $this->id . $this->show_all, 'tournament_competition_chart.php?id=' . $this->id . $this->show_all, true) . '</div>';
		if ($this->flags & TOURNAMENT_FLAG_LONG_TERM)
		{
			// A season is played over months: the day says where it is, the hour says nothing.
			show_race($this->race, $this->timezone, array('days' => true, 'step' => get_label('Game [0] of [1]')));
		}
		else
		{
			show_race($this->race, $this->timezone, array('moments' => $this->moments(), 'step' => get_label('Game [0] of [1]')));
		}
	}

	// The stage and the tour of each game of the tournament, by the moment it ended. The stage only
	// where there is more than the main round - otherwise it is the tournament itself.
	private function moments()
	{
		$games = array();
		$stages = false;
		$query = new DbQuery(
			'SELECT g.end_time, e.round, g.game_num FROM games g JOIN events e ON e.id = g.event_id' .
			' WHERE g.tournament_id = ? AND (g.flags & ' . GAME_FLAG_CANCELED . ') = 0', $this->id);
		while ($row = $query->next())
		{
			$games[] = $row;
			if ((int)$row[1] > 0)
			{
				$stages = true;
			}
		}
		$moments = array();
		foreach ($games as $game)
		{
			list($end_time, $round, $game_num) = $game;
			$what = array();
			if ($stages)
			{
				$name = get_round_name((int)$round);
				$what[] = mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($name, 1, NULL, 'UTF-8');
			}
			if (!is_null($game_num) && $game_num > 0)
			{
				$what[] = get_label('Game~seating') . ' ' . (int)$game_num;
			}
			if (count($what) > 0)
			{
				$moments[(int)$end_time] = implode(' · ', $what);
			}
		}
		return $moments;
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
