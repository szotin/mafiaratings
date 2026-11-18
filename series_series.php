<?php

require_once 'include/general_page_base.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';
require_once 'include/series_cards.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

define('FLAG_FILTER_EMPTY', 0x0001);
define('FLAG_FILTER_NOT_EMPTY', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NOT_CANCELED', 0x0008);

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

	// Laid out as the tournaments of the series are - see series_tournaments.php: a subseries is
	// what a tournament is to the series, so the two lists show the same things the same way.
	// What a subseries has no counterpart for is left out: the stage, which only a tournament
	// has, and the games, the rounds and the videos, which are counted by tournament.
	protected function show_body()
	{
		global $_profile, $_page, $_lang;

		$stars = 0;
		if (isset($_REQUEST['stars']))
		{
			$stars = (int)$_REQUEST['stars'];
		}

		// The toolbar of the lists of series (series.php): past and future, the stars, the dates and
		// the switches.
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$this->future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $this->future)), get_label('Subseries'));
		// Only as many stars as this series hands out, and none at all when it does not rate what
		// is in it - there is nothing to filter by then.
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
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $this->filter);
		}
		echo '</div>';

		$subseries_csv = get_subseries_csv($this->id);
		$condition = new SQL(
			' FROM series_series ss'.
			' JOIN series s ON s.id = ss.child_id' .
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
			series_owner_join().
			' WHERE ss.parent_id IN ('.$subseries_csv.')');
		if ($this->future)
		{
			$condition->add(' AND s.start_time + s.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND ' . series_past_condition());

			if ($this->filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT t.tournament_id FROM series_tournaments t WHERE t.series_id = s.id)');
			}
			if ($this->filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT t.tournament_id FROM series_tournaments t WHERE t.series_id = s.id)');
			}
			if ($this->filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') <> 0');
			}
			if ($this->filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0');
			}
		}

		if ($stars > 0)
		{
			$condition->add(' AND ss.stars = ?', $stars);
		}

		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND s.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND s.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}

		list ($count) = Db::record(get_label('series'), 'SELECT COUNT(DISTINCT s.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		if ($this->future)
		{
			$order_by = ' ORDER BY s.start_time + s.duration, s.id';
		}
		else
		{
			$order_by = ' ORDER BY s.start_time DESC, s.id DESC';
		}

		$query = new DbQuery(
			'SELECT DISTINCT s.id, ns.name, s.flags, s.start_time, s.duration, s.max_stars, ' . series_owner_fields() . ',' .
			' (SELECT count(*) FROM series_tournaments WHERE series_id = s.id) as tournaments,' .
			' (SELECT count(*) FROM series_places WHERE series_id = s.id AND cut_stages = 0) as players',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);

		$timezone = get_timezone();
		$subseries = array();
		$delim = '';
		$cs_subseries = '';
		while ($row = $query->next())
		{
			$s = new stdClass();
			list ($s->id, $s->name, $s->flags, $s->time, $s->duration, $s->max_stars, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $s->tournaments_count, $s->players_count) = $row;
			$s->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$s->parents = array();
			$s->series_series_flags = 0;
			$subseries[] = $s;
			$cs_subseries .= $delim . $s->id;
			$delim = ',';
		}

		// The series each of them is in: this one gives the stars under its name, the others are
		// shown next to it - a subseries further down is in one of the subseries of this series.
		if ($cs_subseries != '')
		{
			$by_id = array();
			foreach ($subseries as $s)
			{
				$by_id[$s->id] = $s;
			}
			$query = new DbQuery(
				'SELECT ss.child_id, ss.stars, ss.flags, s.max_stars, s.id, ns.name, s.flags, ' . series_owner_fields() .
				' FROM series_series ss' .
				' JOIN series s ON s.id = ss.parent_id' .
				' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
				series_owner_join() .
				' WHERE ss.child_id IN (' . $cs_subseries . ') ORDER BY s.start_time, s.id');
			while ($row = $query->next())
			{
				list ($child_id, $p_stars, $ss_flags, $p_max_stars, $p_id, $p_name, $p_flags, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags) = $row;
				$s = $by_id[$child_id];
				// Stars only from a series that grades what is in it.
				$p_stars = series_membership_stars($p_max_stars, $p_stars);
				if ($p_id == $this->id)
				{
					$s->stars = $p_stars;
					$s->series_series_flags = (int)$ss_flags;
				}
				else
				{
					$p = new stdClass();
					$p->stars = $p_stars;
					$p->id = $p_id;
					$p->name = $p_name;
					$p->flags = $p_flags;
					$p->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
					$s->parents[] = $p;
				}
			}
		}

		// The tiles of series.php (include/series_cards.php). Under the dates of each: the stars it
		// has in this series, whether it is paid for, the other series it is in with their stars, and
		// how many players it has.
		$parent_pic = new SeriesPicture();
		$list = array();
		foreach ($subseries as $s)
		{
			$series = new stdClass();
			$series->id = $s->id;
			$series->name = $s->name;
			$series->flags = $s->flags;
			$series->start_time = $s->time;
			$series->duration = $s->duration;
			$series->max_stars = $s->max_stars;
			$series->owner = $s->owner;
			$series->tournaments = $s->tournaments_count;
			
			$extra = '';
			if (isset($s->stars) && $s->stars > 0)
			{
				$extra .= '<span class="stars" title="' . htmlspecialchars(strip_tags($this->name), ENT_QUOTES, 'UTF-8') . '">' . tournament_stars_str($s->stars, false) . '</span>';
			}
			if ($s->series_series_flags & SERIES_SERIES_FLAG_NOT_PAYED)
			{
				$extra .= '<span class="chip unpaid">' . get_label('not payed') . '</span>';
			}
			foreach ($s->parents as $p)
			{
				$pic = PageBase::capture(function() use ($parent_pic, $p) { $parent_pic->set($p->id, $p->name, $p->flags, $p->owner)->show(ICONS_DIR, false, 20); });
				$extra .= '<a class="ss-parent" href="series_standings.php?bck=1&id=' . $p->id . '" title="' . htmlspecialchars(strip_tags($p->name), ENT_QUOTES, 'UTF-8') . '">' . $pic .
					($p->stars > 0 ? '<span class="stars">' . tournament_stars_str($p->stars, false) . '</span>' : '') . '</a>';
			}
			if (!$this->future)
			{
				$extra .= '<a class="ss-players" href="series_standings.php?bck=1&id=' . $s->id . '" title="' . get_label('Players') . '">' . PageBase::icon('users') . $s->players_count . '</a>';
			}
			if ($extra != '')
			{
				$series->extra = '<span class="tile-meta ss-extra">' . $extra . '</span>';
			}
			$list[] = $series;
		}
		series_tiles_show($list, get_label('No series found.'), $timezone);		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Subseries'));

?>
