<?php

require_once 'include/event.php';
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

class Page extends EventPageBase
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
		
		$table_filter = 0;
		if (isset($_REQUEST['table']))
		{
			$table_filter = (int)$_REQUEST['table'];
		}
		
		$tables = array();
		$query = new DbQuery('SELECT table_num, COUNT(*) FROM games WHERE event_id = ? GROUP BY table_num ORDER BY table_num', $this->id);
		while ($row = $query->next())
		{
			list ($t, $games_count) = $row;
			if (!is_null($t))
			{
				$tables[$t] = $games_count;
			}
		}
		
		// The toolbar of the lists of games (games.php), in two lines: the filter by name and the
		// result; then the dates and the three-state filters. The tables of the round, when there are
		// several, as a switch under it.
		$condition = new SQL(' WHERE g.event_id = ?', $this->id);
		$search = game_search_condition($condition);
		echo '<div class="toolbar">' . game_search_field($search) . game_result_seg($result_filter);
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</div></div>';
		
		if (count($tables) > 1)
		{
			$items = array(array(card_url(array('table' => NULL, 'page' => NULL)), get_label('All'), $table_filter == 0));
			foreach ($tables as $table_num => $games_count)
			{
				$items[] = array(card_url(array('table' => $table_num, 'page' => NULL)), get_label('Table [0]', $table_num), $table_filter == $table_num);
			}
			echo '<div class="toolbar">' . card_seg($items, get_label('Tables')) . '</div>';
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
		if ($table_filter > 0)
		{
			$condition->add(' AND g.table_num = ?', $table_filter);
		}
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$referee_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
	
		// The table of the lists of games (include/game_rows.php): the game - its table and number over
		// its time - its result, the referee under the name and in the photo they have
		// in this round, and the actions of whoever may change the game under "≡". The round is the
		// page's own, and so is its tournament: neither is a column nor under the game.
		$is_user = is_permitted(PERMISSION_USER);
		echo '<div class="sheet"><table class="tbl games">';
		echo '<thead><tr><th class="rk" scope="col">#</th>';
		echo '<th scope="col">' . get_label('Game') . '</th>';
		echo '<th scope="col">' . get_label('Result') . '</th>';
		echo '<th class="p2" scope="col">' . get_label('Referee') . '</th>';
		if ($is_user)
		{
			echo '<th scope="col"></th>';
		}
		echo '</tr></thead><tbody>';
		$query = new DbQuery(
			'SELECT g.id, g.user_id, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.flags,' .
			' t.id, nt.name, t.flags,' . 
			' eu.nickname, eu.flags, tu.flags, cu.flags, g.table_num, g.game_num FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN event_regs eu ON eu.user_id = m.id AND eu.event_id = g.event_id' .
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
				$g->tournament_id, $g->tournament_name, $g->tournament_flags,
				$event_referee_nickname, $event_referee_flags, $tournament_referee_flags, $club_referee_flags, $g->table_num, $g->game_num) = $row;
			
			// The game opens among the games of this round - of this table when the list is narrowed to
			// it - so that its arrows lead to the next game of the same.
			$href = '&event_id=' . $this->id;
			if ($table_filter != 0)
			{
				$href .= '&table=' . $table_filter;
			}
			$game_cell = game_row_cell($g, array('time'), $href);
			
			echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
			echo $game_cell . game_result_cell($g);
			
			// The referee under the nickname and in the photo they have in this round, or in its
			// tournament, or its club, or their own.
			echo '<td class="p2">';
			if (is_null($g->moder_id))
			{
				echo '<span class="dim">&ndash;</span>';
			}
			else
			{
				$referee_pic->
					set($g->moder_id, $event_referee_nickname, $event_referee_flags, 'e' . $this->id)->
					set($g->moder_id, $g->moder_name, $tournament_referee_flags, 't' . $this->tournament_id)->
					set($g->moder_id, $g->moder_name, $club_referee_flags, 'c' . $this->club_id)->
					set($g->moder_id, $g->moder_name, $g->moder_flags);
				$referee_name = empty($event_referee_nickname) ? $g->moder_name : $event_referee_nickname;
				echo '<a class="who" href="user_info.php?bck=1&id=' . $g->moder_id . '">' . card_avatar_of($referee_pic, $g->moder_id, $referee_name, '') . '<span class="who-txt"><span class="who-name">' . $referee_name . '</span></span></a>';
			}
			echo '</td>';
			
			if ($is_user)
			{
				// What the old row of icons offered, under "≡": the editor of the game, its video, delete.
				if ($this->is_manager || is_permitted(PERMISSION_OWNER, $game_user_id))
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
<?php
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>