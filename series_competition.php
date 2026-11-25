<?php

require_once 'include/series.php';
require_once 'include/series_ranking.php';
require_once 'include/general_page_base.php';
require_once 'include/picture.php';
require_once 'include/race.php';

// The race of a series: its top twenty after each of its competitions, played back. Each moment is
// the standings of the series as they stood once that competition was over - worked out by the same
// SeriesRanking the recount writes series_places with, over the competitions finished by then and
// the extra points given by then. Whoever went further up the ladder of the stages stands above
// whoever has more points, as in the standings.
class Page extends SeriesPageBase
{
	private $race;

	protected function prepare()
	{
		global $_lang;

		parent::prepare();

		list($gaining) = Db::record(get_label('gaining'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $this->gaining_id, $this->gaining_version);
		$gaining = set_gaining_series_type(json_decode($gaining), $this->type);
		$max_tournaments = isset($gaining->maxTournaments) ? $gaining->maxTournaments : 0;

		$ranking = new SeriesRanking();
		$sources = $ranking->readSeriesSources($this->id, $this->stages);
		$extra_points = $ranking->readSeriesExtraPoints($this->id);
		$ranking->scoreSources($sources, $gaining, $this->stages);

		$moments = array();
		foreach ($sources as $source)
		{
			$moments[$source->end_time] = true;
		}
		foreach ($extra_points as $entry)
		{
			$moments[$entry->time] = true;
		}
		$moments = array_keys($moments);
		sort($moments);

		$history = array();
		foreach ($moments as $time)
		{
			// The competitions over by then, in the order the recount adds them up - which matters
			// to which points a best-of keeps.
			$now = array();
			foreach ($sources as $key => $source)
			{
				if ($source->end_time <= $time)
				{
					$now[$key] = $source;
				}
			}
			$extra_now = array();
			foreach ($extra_points as $entry)
			{
				if ($entry->time <= $time)
				{
					$extra_now[] = $entry;
				}
			}
			foreach ($ranking->rankSeries($now, $extra_now, $this->stages, 0, $max_tournaments) as $player)
			{
				$points = round((float)$player->points, 2);
				$history[$player->id][] = array((int)$time, $points, $player->stage_order * 1000000 + $points);
			}
		}

		$this->race = array();
		if (count($history) > 0)
		{
			$pic = new Picture(USER_PICTURE);
			$club_pic = new Picture(CLUB_PICTURE);
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, c.id, nc.name, c.flags FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & ' . $_lang . ') <> 0' .
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id LEFT OUTER JOIN names nc ON nc.id = c.name_id AND (nc.langs & ' . $_lang . ') <> 0' .
				' WHERE u.id IN (' . implode(',', array_keys($history)) . ')');
			while ($row = $query->next())
			{
				list($id, $name, $flags, $club_id, $club_name, $club_flags) = $row;
				$id = (int)$id;
				$this->race[] = race_player($id, $name, card_avatar($pic, $id, $name, $flags, ''),
					'series_player.php?bck=1&id=' . $this->id . '&user_id=' . $id, $history[$id],
					race_club($club_pic, $club_id, $club_name, $club_flags));
			}
		}
	}

	protected function show_body()
	{
		show_race($this->race, $this->timezone, array('days' => true));
	}
}

$page = new Page();
$page->run(get_label('Competition Chart'));

?>
