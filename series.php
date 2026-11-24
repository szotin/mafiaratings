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

class Page extends GeneralPageBase
{
	// The button that makes a series, beside the menu "≡" of the band. See PageBase::create_button().
	protected function title_actions()
	{
		return PageBase::create_button('series');
	}

	protected function prepare()
	{
		parent::prepare();
		$this->filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		
		// Every type at first: the list tells them apart by look (see the classes of the tiles below).
		$this->types = get_series_type_filter(SERIES_TYPE_FILTER_ALL);

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
		
		// The toolbar of a list (see new_look.css): the name, the switch and - there is no place to
		// filter by - the dates; then the types and the three-state filters.
		$search = card_search_condition($condition, 's.name_id');
		echo '<div class="toolbar">' . card_search_field($search, get_label('Series name'));
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
			'SELECT s.id, ns.name, s.flags, s.start_time, s.duration, s.max_stars, ' . series_owner_fields() . ',' .
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
		
		$list = array();
		while ($row = $query->next())
		{
			$series = new stdClass();
			list ($series->id, $series->name, $series->flags, $series->start_time, $series->duration, $series->max_stars, $league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags, $series->tournaments) = $row;
			$series->owner = SeriesOwner::from_row($league_id, $league_name, $league_flags, $club_id, $club_name, $club_flags);
			$list[] = $series;
		}
		// The tiles: see include/series_cards.php, shared by every list of series.
		series_tiles_show($list, get_label('No series found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Series'));

?>