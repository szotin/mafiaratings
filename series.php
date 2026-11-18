<?php

require_once 'include/general_page_base.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/series.php';
require_once 'include/ccc_filter.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/cards.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

define('FLAG_FILTER_EMPTY', 0x0001);
define('FLAG_FILTER_NOT_EMPTY', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NOT_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		$this->types = get_series_type_filter();

		$this->future = false;
		if (isset($_REQUEST['future']))
		{
			$this->future = ((int)$_REQUEST['future'] > 0);
		}
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$condition = new SQL(
			' FROM series s' .
			' JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0' .
			series_owner_join());
		if ($this->future)
		{
			$condition->add(' WHERE s.start_time + s.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' WHERE ' . series_past_condition());
			
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
		
		$condition->add(series_type_filter_condition($this->types));

		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND s.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND s.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$this->future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $this->future)), get_label('Series'));
		echo '<div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		// The type filter belongs on both tabs: a recurring tournament is always under way, so the
		// future tab is exactly where one shows up. It goes on a line of its own with the other
		// filters: after the dates there is no room for it.
		echo '<div class="tb-line">';
		show_series_type_filter($this->types);
		if (!$this->future)
		{
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $this->filter);
		}
		echo '</div></div>';
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$colunm_counter = 0;
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, ' . series_owner_fields() . ',' .
			' (SELECT count(*) FROM series_tournaments WHERE series_id = s.id) as tournaments',
			$condition);
		if ($this->future)
		{
			$query->add(' ORDER BY s.start_time, s.id');
		}
		else
		{
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$timezone = get_timezone();
		$now = time();
		$series_pic = new SeriesPicture();
		$series_count = 0;
		while ($row = $query->next())
		{
			list ($series_id, $series_name, $series_flags, $series_time, $series_duration, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $tournaments_count) = $row;
			$owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			// A recurring tournament is always under way. Saying so adds nothing to its type.
			$playing = ($now >= $series_time && $now < $series_time + $series_duration && !series_is_infinite($series_time));
			$canceled = ($series_flags & SERIES_FLAG_CANCELED) != 0;
			if ($series_count++ == 0)
			{
				echo '<div class="tiles wide">';
			}
			
			echo '<div class="tile' . ($canceled ? ' is-off' : '') . '">' . card_logo($series_pic->set($series_id, $series_name, $series_flags, $owner), $series_name);
			echo '<div class="who-txt"><a class="who-name" href="series_standings.php?bck=1&id=' . $series_id . '">' . $series_name . '</a>';
			echo '<span class="who-sub">' . PageBase::icon($owner->is_league() ? 'trophy' : 'users') . $owner->name . '</span>';
			echo '<span class="tile-meta"><span>' . PageBase::icon('cal') . series_period($series_time, $series_duration, $timezone) . '</span>';
			if ($canceled)
			{
				echo '<span class="chip off">' . get_label('canceled') . '</span>';
			}
			else if ($playing)
			{
				echo '<span class="chip live">' . get_label('playing now') . '</span>';
			}
			echo '</span></div>';
			echo '<a class="tile-num" href="series_tournaments.php?bck=1&id=' . $series_id . '" title="' . get_label('Tournaments') . '"><b>' . $tournaments_count . '</b><span>' . get_label('Tournaments') . '</span></a>';
			echo '</div>';
		}
		if ($series_count > 0)
		{
			echo '</div>';
		}
		else
		{
			echo '<p class="empty">' . get_label('No series found.') . '</p>';
		}
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>