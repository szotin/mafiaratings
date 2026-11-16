<?php

require_once 'include/general_page_base.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

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

		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		// Only as many stars as this series hands out, and none at all when it does not rate what
		// is in it - there is nothing to filter by then.
		if ($this->max_stars > 0)
		{
			echo get_label('Stars') . ': <select id="starsfilter" onchange="goTo({page:undefined,stars:$(\'#starsfilter\').val()})">';
			show_option(0, $stars, '');
			for ($s = 1; $s <= $this->max_stars; ++$s)
			{
				show_option($s, $stars, str_repeat('★', $s));
			}
			echo '</select>';
			echo '&emsp;&emsp;';
		}
		show_date_filter();
		echo '&emsp;&emsp;';
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $this->filter);
		}
		echo '</td></tr></table></p>';

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

		echo '<div class="tab">';
		echo '<button ' . ($this->future ? '' : 'class="active" ') . 'onclick="goTo({future:0,page:0})">' . get_label('Past') . '</button>';
		echo '<button ' . (!$this->future ? '' : 'class="active" ') . 'onclick="goTo({future:1,page:0})">' . get_label('Future') . '</button>';
		echo '</div>';
		echo '<div class="tabcontent">';

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
			'SELECT DISTINCT s.id, ns.name, s.flags, s.start_time, s.duration, ' . series_owner_fields() . ',' .
			' (SELECT count(*) FROM series_tournaments WHERE series_id = s.id) as tournaments,' .
			' (SELECT count(*) FROM series_places WHERE series_id = s.id AND cut_stages = 0) as players',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);

		$timezone = get_timezone();
		$subseries = array();
		$delim = '';
		$cs_subseries = '';
		$first_month_series = NULL;
		while ($row = $query->next())
		{
			$s = new stdClass();
			list ($s->id, $s->name, $s->flags, $s->time, $s->duration, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $s->tournaments_count, $s->players_count) = $row;
			$s->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$m = format_month($this->future ? $s->time + $s->duration : $s->time, $timezone);
			if ($first_month_series == NULL || $first_month_series->month != $m)
			{
				$s->month = $m;
				$s->month_count = 1;
				$first_month_series = $s;
			}
			else
			{
				++$first_month_series->month_count;
			}
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

		echo '<table class="bordered light" width="100%"><tr class="th-long darker">';
		echo '<td width="100"></td>';
		echo '<td align="center" colspan="3">' . get_label('Series') . '</td>';
		if (!$this->future)
		{
			echo '<td width="60" align="center">' . get_label('Players') . '</td>';
		}
		echo '<td width="60" align="center">' . get_label('Tournaments') . '</td>';
		echo '</tr>';

		$num = $_page * PAGE_SIZE;
		$now = time();
		$series_pic = new Picture(SERIES_PICTURE);
		$parent_pic = new SeriesPicture();
		foreach ($subseries as $s)
		{
			$playing = ($now >= $s->time && $now < $s->time + $s->duration && !series_is_infinite($s->time));
			if ($playing)
			{
				echo '<tr class="dark">';
			}
			else
			{
				echo '<tr>';
			}

			if (isset($s->month))
			{
				echo '<td rowspan="' . $s->month_count . '" class="darker" width="100" align="center"><b>' . $s->month . '</b></td>';
			}

			echo '<td align="center" width="30"><b>' . ++$num . '</b></td>';

			echo '<td><table width="100%" class="transp"><tr>';
			echo '<td width="80" align="center" valign="center">';
			$series_pic->set($s->id, $s->name, $s->flags);
			$series_pic->show(ICONS_DIR, true, 60, 60, NULL, ($s->series_series_flags & SERIES_SERIES_FLAG_NOT_PAYED) ? 'not_payed.png' : NULL);
			echo '</td>';
			echo '<td><b><a href="series_standings.php?bck=1&id=' . $s->id . '">' . $s->name;
			if ($playing)
			{
				echo ' (' . get_label('playing now') . ')';
			}
			echo '</a></b>';
			if (isset($s->stars) && $s->stars > 0)
			{
				echo '<br><font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($s->stars) . '</font>';
			}
			echo '</td>';
			foreach ($s->parents as $p)
			{
				echo '<td width="36" align="center" valign="center">';
				if ($p->stars > 0)
				{
					echo '<font style="color:#B8860B; font-size:14px;">' . tournament_stars_str($p->stars) . '</font><br>';
				}
				echo '<a href="series_standings.php?bck=1&id=' . $p->id . '">';
				$parent_pic->set($p->id, $p->name, $p->flags, $p->owner)->show(ICONS_DIR, false, 32);
				echo '</a></td>';
			}
			echo '</tr></table>';
			echo '</td>';

			echo '<td width="160" valign="center" class="dark">';
			echo '<table width="100%" class="transp"><tr>';
			echo '<td width="60" align="center" valign="center">';
			$s->owner->picture()->show(ICONS_DIR, false, 40);
			echo '</td>';
			echo '<td>' . series_period($s->time, $s->duration, $timezone) . '</td>';
			echo '</tr></table></td>';

			if (!$this->future)
			{
				echo '<td align="center"><a href="series_standings.php?bck=1&id=' . $s->id . '">' . $s->players_count . '</a></td>';
			}
			echo '<td align="center"><a href="series_tournaments.php?bck=1&id=' . $s->id . '">' . $s->tournaments_count . '</a></td>';
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Subseries'));

?>
