<?php
require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/image.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/game_rows.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0008);
define('FLAG_FILTER_RATING', 0x0010);
define('FLAG_FILTER_NO_RATING', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NO_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends AddressPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;

		$result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$result_filter = (int)$_REQUEST['results'];
			if ($result_filter == 0 && !$this->is_manager)
			{
				$result_filter = -1;
			}
		}

		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}

		$condition = new SQL(' WHERE e.address_id = ?', $this->id);
		if ($result_filter > 0)
		{
			$condition->add(' AND g.result = ?', $result_filter);
		}

		if ($filter & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND (g.flags & '.GAME_FLAG_CANCELED.') = 0');
		}

		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}

		// The filter by name and the toolbar of games.php (include/game_rows.php): the name and the
		// result on the first line, the dates and the three-state filters on the second.
		$search = game_search_condition($condition);
		echo '<div class="toolbar">' . game_search_field($search) . game_result_seg($result_filter);
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</div></div>';

		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		// The table of games.php with the columns this page had: the game day, the tournament and the
		// referee. The address is the page's own.
		echo '<div class="sheet"><table class="tbl games">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Game') . '</th>';
		echo '<th scope="col">' . get_label('Result') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Tournament') . '</th>';
		echo '<th class="p4" scope="col">' . get_label('Event') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Referee') . '</th>';
		if ($this->is_manager)
		{
			echo '<th scope="col"></th>';
		}
		echo '</tr></thead><tbody>';

		$query = new DbQuery(
			'SELECT g.id, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.flags, e.id, ne.name, e.flags, t.id, nt.name, t.flags, g.table_num, g.game_num FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN users m ON m.id = g.moderator_id'.
			' LEFT OUTER JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$num = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			$g = new stdClass();
			list ($g->id, $g->moder_id, $g->moder_name, $g->moder_flags, $g->start, $duration, $g->result, $g->video_id, $g->flags, $g->event_id, $g->event_name, $g->event_flags, $g->tournament_id, $g->tournament_name, $g->tournament_flags, $g->table_num, $g->game_num) = $row;
			$g->timezone = $this->timezone;

			echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
			// The game opens in the context of the address, as it did.
			echo game_row_cell($g, array('time'), '&address_id=' . $this->id);
			echo game_result_cell($g) . game_tournament_cell($g) . game_event_cell($g, 'p4') . game_referee_cell($g);
			if ($this->is_manager)
			{
				echo game_actions_cell($g, true);
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		show_pages_navigation(PAGE_SIZE, $count);
	}

	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({filter: checkboxFilterFlags(), page: 0});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>
