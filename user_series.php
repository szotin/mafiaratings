<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/datetime.php';
require_once 'include/series.php';
require_once 'include/series_cards.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		$timezone = get_timezone();
		
		$condition = new SQL(' FROM series_places sp JOIN series s ON s.id = sp.series_id JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' . series_owner_join() . ' WHERE sp.user_id = ? AND sp.cut_stages = 0', $this->id);
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND s.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND s.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		echo '<div class="toolbar"><div class="tb-dates">';
		show_date_filter();
		echo '</div></div>';
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, s.max_stars, ' . series_owner_fields() . ',' .
			' (SELECT count(*) FROM series_tournaments WHERE series_id = s.id) as tournaments,' .
			' sp.place, sp.score, sp.tournaments, sp.games, sp.wins',
			$condition);
		$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		// The tiles of series.php (include/series_cards.php), each with the player's own result in
		// it: the place, the points, how many of its tournaments and games they played and won.
		$list = array();
		while ($row = $query->next())
		{
			$series = new stdClass();
			list ($series->id, $series->name, $series->flags, $series->start_time, $series->duration, $series->max_stars,
				$league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $series->tournaments,
				$place, $score, $tournaments_count, $games_count, $wins_count) = $row;
			$series->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			
			$res = '';
			if (!is_null($place) && $place > 0)
			{
				$res .= '<span class="medal' . ($place <= 3 ? ' m' . $place : '') . '" title="' . get_label('[0] place', $place) . '">' . get_label('[0] place', $place) . '</span>';
			}
			if (!is_null($score))
			{
				$res .= '<span title="' . get_label('Points') . '">' . PageBase::icon('trophy') . format_score($score) . '</span>';
			}
			$res .= '<span title="' . get_label('Tournaments played') . '">' . PageBase::icon('layers') . $tournaments_count . '</span>';
			$res .= '<span title="' . get_label('Games played') . '">' . PageBase::icon('play') . $games_count . '</span>';
			if ($games_count > 0)
			{
				$res .= '<span title="' . get_label('Winning %') . '">' . number_format(($wins_count * 100.0) / $games_count, 1) . '%</span>';
			}
			$series->extra = '<span class="tile-meta pc-res">' . $res . '</span>';
			$list[] = $series;
		}
		series_tiles_show($list, get_label('No series found.'));		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>