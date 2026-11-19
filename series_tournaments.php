<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/tournament.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';
require_once 'include/tournament_cards.php';

define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$stars = 0;
		if (isset($_REQUEST['stars']))
		{
			$stars = (int)$_REQUEST['stars'];
		}
		
		// The toolbar of tournaments.php: past or future, the filters, the dates.
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$this->future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $this->future)), get_label('Tournaments'));
		echo '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('tournaments')));
		echo '</div>';
		// Only as many stars as this series hands out, and none at all when it does not rate its
		// tournaments - there is nothing to filter by then.
		if ($this->max_stars > 0)
		{
			echo '<label>' . get_label('Stars') . ': <select id="starsfilter" onchange="goTo({page:undefined,stars:$(\'#starsfilter\').val()})">';
			show_option(0, $stars, '');
			for ($s = 1; $s <= $this->max_stars; ++$s)
			{
				show_option($s, $stars, str_repeat('★', $s));
			}
			echo '</select></label>';
		}
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('with video'), get_label('unplayed'), get_label('canceled')), $this->filter);
		}
		echo '</div>';
		
		$subseries_csv = get_subseries_csv($this->id);
		
		$condition = new SQL(
			' FROM series_tournaments st' .
			' JOIN tournaments t ON t.id = st.tournament_id' .
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN series s ON s.id = st.series_id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON ct.id = a.city_id' .
			' WHERE st.series_id IN ('.$subseries_csv.')');
		if ($this->future)
		{
			$condition->add(' AND t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND t.start_time < UNIX_TIMESTAMP()');
			
			if ($this->filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND (EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) OR EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id))');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		if ($stars > 0)
		{
			$condition->add(' AND st.stars = ?', $stars);
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND t.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND t.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT COUNT(DISTINCT t.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		if ($this->future)
		{
			$order_by = ' ORDER BY t.start_time + t.duration, t.id';
		}
		else
		{
			$order_by = ' ORDER BY t.start_time DESC, t.id DESC';
		}
		
		$query = new DbQuery(
			'SELECT DISTINCT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, c.id, nc.name, c.flags, t.langs, t.num_players, st.flags, a.id, a.address, a.flags, ni.name,' .
			' (SELECT count(*) FROM games WHERE tournament_id = t.id AND (flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(*) FROM events WHERE tournament_id = t.id AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0) as events,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);

		$tournaments = array();
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->club_id, $tournament->club_name, $tournament->club_flags, $tournament->languages, $tournament->num_players, $tournament->series_tournament_flags,
				$tournament->addr_id, $tournament->addr, $tournament->addr_flags, $tournament->city,
				$tournament->games_count, $tournament->rounds_count, $tournament->videos_count) = $row;
			$tournaments[] = $tournament;
		}
		
		// The cards of tournaments.php: see include/tournament_cards.php.
		tournament_cards_load($tournaments, $this->future);
		
		// On the page of a series a tournament wears the stars it has in this series, not the most it
		// has in any: the series it is in are replaced with this one. Its stage in it, when the series
		// has a ladder, goes into the hint of the stars.
		$by_id = array();
		foreach ($tournaments as $tournament)
		{
			$by_id[$tournament->id] = $tournament;
		}
		if (count($by_id) > 0)
		{
			$query = new DbQuery(
				'SELECT st.tournament_id, st.stars, st.stage, s.stages, s.start_time, s.max_stars, s.id, ns.name, s.flags, ' . series_owner_fields() .
				' FROM series_tournaments st' .
				' JOIN series s ON s.id = st.series_id' .
				' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
				series_owner_join() .
				' WHERE st.series_id = ? AND st.tournament_id IN (' . implode(',', array_keys($by_id)) . ')', $this->id);
			while ($row = $query->next())
			{
				list ($tournament_id, $stars, $stage, $series_stages, $series_start, $series_max_stars, $series_id, $series_name, $series_flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags) = $row;
				// Stars only from a series that grades its tournaments. One that does not - a
				// recurring tournament - may still hold the single star stored before it stopped.
				$series_stages = series_stage_count($series_stages);
				$series = new stdClass();
				$series->stars = series_membership_stars($series_max_stars, $stars);
				$series->id = $series_id;
				$series->name = $series_name;
				$extra = '';
				if ($series_stages > 1)
				{
					$stage_name = get_series_stage_name(series_type($series_start, $series_max_stars), $series_stages, $stage);
					$series->name .= ' (' . $stage_name . ')';
					$extra .= '<span class="chip stage">' . $stage_name . '</span>';
				}
				// A tournament the series has not been paid for yet, as the old list marked it.
				if ($by_id[$tournament_id]->series_tournament_flags & SERIES_TOURNAMENT_FLAG_NOT_PAYED)
				{
					$extra .= '<span class="chip unpaid">' . get_label('not payed') . '</span>';
				}
				if ($extra != '')
				{
					$by_id[$tournament_id]->extra = '<div class="pc-tags">' . $extra . '</div>';
				}
				$series->flags = $series_flags;
				$series->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
				$by_id[$tournament_id]->series = array($series);
			}
		}
		
		tournament_cards_show($tournaments, $this->future, get_label('No tournaments found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>