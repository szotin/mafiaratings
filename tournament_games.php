<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/game_rows.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

define('MAX_TABS', 5);

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;
		
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
		
		$result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$result_filter = (int)$_REQUEST['results'];
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$event_table = 0;
		$event_filter = 0;
		$table_filter = 0;
		if (isset($_REQUEST['et']))
		{
			$event_table = (int)$_REQUEST['et'];
			$event_filter = floor($event_table / 100);
			$table_filter = $event_table % 100;
		}
		
		$tables_count = 0;
		$rounds = array();
		$query = new DbQuery('SELECT g.event_id, g.table_num, ne.name, COUNT(*) FROM games g JOIN events e ON e.id = g.event_id JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0 WHERE g.tournament_id = ? GROUP BY ne.name, g.table_num ORDER BY g.event_id, g.table_num', $this->id);
		while ($row = $query->next())
		{
			list ($event_id, $table_num, $event_name, $games_count) = $row;
			
			if (array_key_exists($event_id, $rounds))
			{
				$round = $rounds[$event_id];
			}
			else
			{
				$round = new stdClass();
				$round->event_name = $event_name;
				$round->tables = array();
				$rounds[$event_id] = $round;
				++$tables_count;
			}
			
			if (!is_null($table_num) && !array_key_exists($table_num, $round->tables))
			{
				if (!empty($round->tables))
				{
					++$tables_count;
				}
				$round->tables[$table_num] = (int)$games_count;
			}
		}
		//print_json($rounds);
		
		// The toolbar of the lists of games (games.php), in two lines: the filter by name and the
		// result, with the round and the table when there are too many of them for a switch; then the
		// dates and the three-state filters.
		$condition = new SQL(' WHERE g.tournament_id = ?', $this->id);
		$search = game_search_condition($condition);
		echo '<div class="toolbar">' . game_search_field($search) . game_result_seg($result_filter);
		if ($tables_count > MAX_TABS)
		{
			echo '<select id="event-table" onchange="eventTableChange()">';
			show_option(0, $event_table, get_label('All'));
			foreach ($rounds as $event_id => $round)
			{
				$et = $event_id * 100;
				show_option($et, $event_table, $round->event_name);
				if (count($round->tables) > 1)
				{
					foreach ($round->tables as $table_num => $count)
					{
						show_option($et + $table_num, $event_table, get_label('[0], table [1]', $round->event_name, $table_num));
					}
				}
			}
			echo '</select>';
		}
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</div></div>';
		
		// A few rounds and tables: a switch of them, as the result above.
		if ($tables_count > 1 && $tables_count <= MAX_TABS)
		{
			$items = array(array(card_url(array('et' => NULL, 'page' => NULL)), get_label('All'), $event_table == 0));
			foreach ($rounds as $event_id => $round)
			{
				$et = $event_id * 100;
				$items[] = array(card_url(array('et' => $et, 'page' => NULL)), $round->event_name, $event_table == $et);
				if (count($round->tables) > 1)
				{
					foreach ($round->tables as $table_num => $count)
					{
						$e = $et + $table_num;
						$items[] = array(card_url(array('et' => $e, 'page' => NULL)), get_label('[0], table [1]', $round->event_name, $table_num), $event_table == $e);
					}
				}
			}
			echo '<div class="toolbar">' . card_seg($items, get_label('Round')) . '</div>';
		}

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
		
		if ($event_filter > 0)
		{
			$condition->add(' AND g.event_id = ?', $event_filter);
		}
		if ($table_filter > 0)
		{
			$condition->add(' AND g.table_num = ?', $table_filter);
		}
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$moder_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));
	
		// The table of the lists of games (include/game_rows.php): the game - its table and number over
		// its time - its result, the round, the referee in their photo of this tournament or club, and
		// the actions of whoever may change the game under "≡". The tournament is the page's own, so it
		// is neither a column nor under the game.
		$is_user = is_permitted(PERMISSION_USER);
		echo '<div class="sheet"><table class="tbl games">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Game') . '</th>';
		echo '<th scope="col">' . get_label('Result') . '</th>';
		echo '<th class="p3" scope="col">' . get_label('Round') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Referee') . '</th>';
		if ($is_user)
		{
			echo '<th scope="col"></th>';
		}
		echo '</tr></thead><tbody>';
		$query = new DbQuery(
			'SELECT g.id, g.user_id, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.flags,' . 
			' e.id, ne.name, e.flags, tu.flags, cu.flags, g.table_num, g.game_num' . 
				' FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = m.id AND tu.tournament_id = g.tournament_id' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = m.id AND cu.club_id = g.club_id' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$num = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			$g = new stdClass();
			list (
				$g->id, $game_user_id, $g->timezone, $g->moder_id, $g->moder_name, $g->moder_flags, $g->start, $duration, $g->result, $g->video_id, $g->flags, 
				$g->event_id, $g->event_name, $g->event_flags, $tournament_moder_flags, $club_moder_flags,
				$g->table_num, $g->game_num) = $row;
			
			// The game opens among the games of this tournament - of this round and table when the
			// list is narrowed to them - so that its arrows lead to the next game of the same.
			$href = '&tournament_id=' . $this->id;
			if ($event_table != 0)
			{
				$href .= '&event_id=' . $event_filter;
				if ($table_filter != 0)
				{
					$href .= '&table=' . $table_filter;
				}
			}
			$game_cell = game_row_cell($g, array('time'), $href);
			
			echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
			echo $game_cell . game_result_cell($g) . game_event_cell($g);
			
			// The referee in the photo they have in this tournament, or in its club, or their own.
			echo '<td class="p2">';
			if (is_null($g->moder_id))
			{
				echo '<span class="dim">&ndash;</span>';
			}
			else
			{
				$moder_pic->
					set($g->moder_id, $g->moder_name, $tournament_moder_flags, 't' . $this->id)->
					set($g->moder_id, $g->moder_name, $club_moder_flags, 'c' . $this->club_id)->
					set($g->moder_id, $g->moder_name, $g->moder_flags);
				echo '<a class="who" href="user_info.php?bck=1&id=' . $g->moder_id . '">' . card_avatar_of($moder_pic, $g->moder_id, $g->moder_name, '') . '<span class="who-txt"><span class="who-name">' . $g->moder_name . '</span></span></a>';
			}
			echo '</td>';
			
			if ($is_user)
			{
				// What the old row of icons offered, under "≡": the editor of the game, its video, delete.
				if ($is_manager || is_permitted(PERMISSION_OWNER, $game_user_id))
				{
					$items = array();
					$items[] = array('mr.editGame(' . $g->id . ')', 'edit.png', get_label('Edit game [0]', $g->id));
					if (is_null($g->video_id))
					{
						$items[] = array('mr.setGameVideo(' . $g->id . ')', 'film-add.png', get_label('Add game [0] video', $g->id));
					}
					else
					{
						$items[] = array('mr.setGameVideo(' . $g->id . ')', 'film.png', get_label('Change game [0] video', $g->id));
						$items[] = array('mr.deleteVideo(' . $g->video_id . ', \'' . get_label('Are you sure you want to remove video from the game [0]?', $g->id) . '\')', 'film-delete.png', get_label('Remove game [0] video', $g->id));
					}
					$items[] = array('mr.deleteGame(' . $g->id . ', \'' . get_label('Are you sure you want to delete the game [0]?', $g->id) . '\')', 'delete.png', get_label('Delete game [0]', $g->id));
					echo '<td class="tb-btns">' . card_row_menu($items) . '</td>';
				}
				else
				{
					echo '<td class="tb-btns"></td>';
				}
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
			goTo({filter: checkboxFilterFlags(), page: undefined });
		}
		
		function eventTableChange(e)
		{
			if (!isSet(e))
			{
				e = $('#event-table').val();
			}
			goTo({et: e != 0 ? e : undefined, page: undefined });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>
