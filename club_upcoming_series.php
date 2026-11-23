<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/series.php';
require_once 'include/series_cards.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page, $_profile, $_lang;

		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER, $this->id);
		$owner = new SeriesOwner(0, $this->id);
		$owner->name = $this->name;
		$owner->flags = $this->flags;
		$page_size = ROW_COUNT * COLUMN_COUNT;
		
		$condition = new SQL('s.club_id = ? AND UNIX_TIMESTAMP() <= s.start_time + s.duration', $this->id);
		
		// The button that makes a new series stands in the toolbar, as in the other lists of the look.
		if ($is_manager)
		{
			echo '<div class="toolbar"><a class="btn tb-create" href="create.php?bck=1&type=series&owner=c' . $this->id . '">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('sеriеs')) . '</span></a></div>';
		}
		
		list ($count) = Db::record(get_label('series'), 'SELECT count(*) FROM series s WHERE ', $condition);
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery(
			'SELECT s.id, ns.name, s.start_time, s.duration, s.flags, s.max_stars,' .
			' (SELECT count(*) FROM series_tournaments _ts JOIN tournaments _t ON _t.id = _ts.tournament_id WHERE _ts.series_id = s.id AND (_t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0) as tournaments' .
			' FROM series s JOIN names ns ON ns.id = s.name_id AND (ns.langs & '.$_lang.') <> 0 WHERE ', $condition);
		$query->add(' ORDER BY s.start_time LIMIT ' . ($_page * $page_size) . ',' . $page_size);

		// The tiles of series.php (include/series_cards.php), the buttons of a manager on each.
		$list = array();
		while ($row = $query->next())
		{
			$series = new stdClass();
			list ($series->id, $series->name, $series->start_time, $series->duration, $series->flags, $series->max_stars, $series->tournaments) = $row;
			$series->owner = $owner;
			if ($_profile != NULL)
			{
				$series->buttons = PageBase::capture(function() use ($series, $owner) { show_series_buttons($series->id, $series->start_time, $series->duration, $series->flags, $owner); });
			}
			$list[] = $series;
		}
		series_tiles_show($list, get_label('No series found.'));
		show_pages_navigation($page_size, $count);
	}
}
$page = new Page();
$page->run(get_label('Upcoming series'));

?>
