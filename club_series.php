<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/series.php';
require_once 'include/series_cards.php';

define('PAGE_SIZE', SERIES_PAGE_SIZE);

define('FLAG_FILTER_EMPTY', 0x0001);
define('FLAG_FILTER_NOT_EMPTY', 0x0002);
define('FLAG_FILTER_CANCELED', 0x0004);
define('FLAG_FILTER_NOT_CANCELED', 0x0008);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends ClubPageBase
{
	// Making one of the club, beside its menu "≡". See PageBase::create_button().
	protected function create_action()
	{
		return PageBase::create_button('series', 'c' . $this->id);
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		$timezone = get_timezone();

		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}

		// A club's recurring tournament is often the thing it is known for, so it is on by default
		// here. A site-wide list keeps them off: they never end, so every one of them would stand
		// among the series that do.
		$types = get_series_type_filter(SERIES_TYPE_FILTER_ALL);

		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}

		$condition = new SQL(' FROM series s JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0 WHERE s.club_id = ?', $this->id);
		if ($future)
		{
			$condition->add(' AND s.start_time + s.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND ' . series_past_condition());

			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT t.id FROM series_tournaments ts JOIN tournaments t ON t.id = ts.tournament_id WHERE ts.series_id = s.id AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND EXISTS (SELECT t.id FROM series_tournaments ts JOIN tournaments t ON t.id = ts.tournament_id WHERE ts.series_id = s.id AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0)');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (s.flags & ' . SERIES_FLAG_CANCELED . ') = 0');
			}
		}

		$condition->add(series_type_filter_condition($types));

		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND s.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND s.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}

		// The look of series.php: the switch, the dates, the types and the other filters in a toolbar,
		// the series as tiles (include/series_cards.php).
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $future)), get_label('Series'));
		if (!$future)
		{
			echo '<div class="tb-dates">';
			show_date_filter();
			echo '</div>';
		}
		echo '<div class="tb-line">';
		show_series_type_filter($types);
		if (!$future)
		{
			show_checkbox_filter(array(get_label('unplayed series'), get_label('canceled series')), $filter, 'filterSeries');
		}
		echo '</div></div>';
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, s.max_stars,' .
			' (SELECT count(*) FROM series_tournaments _ts JOIN tournaments _t ON _t.id = _ts.tournament_id WHERE _ts.series_id = s.id AND (_t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0) as tournaments',
			$condition);
		if ($future)
		{
			$query->add(' ORDER BY s.start_time, s.id');
		}
		else
		{
			$query->add(' ORDER BY s.start_time DESC, s.id DESC');
		}
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$owner = new SeriesOwner(0, $this->id);
		$owner->name = $this->name;
		$owner->flags = $this->flags;
		$list = array();
		while ($row = $query->next())
		{
			$series = new stdClass();
			list ($series->id, $series->name, $series->flags, $series->start_time, $series->duration, $series->max_stars, $series->tournaments) = $row;
			$series->owner = $owner;
			$list[] = $series;
		}
		series_tiles_show($list, get_label('No series found.'), $timezone);
		show_pages_navigation(PAGE_SIZE, $count);
	}

	protected function js()
	{
?>
		function filterSeries()
		{
			goTo({ filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>
