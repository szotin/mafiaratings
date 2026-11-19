<?php 

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/scoring.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/ccc_filter.php';
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

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_page, $_lang, $_profile;
	
		$moder = 0;
		if (isset($_REQUEST['moder']))
		{
			$moder = (int)$_REQUEST['moder'];
		}
		
		$result_filter = 0;
		if (isset($_REQUEST['result']))
		{
			$result_filter = (int)$_REQUEST['result'];
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$roles = 0;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		$condition = new SQL();
		// The filter by name of the preview (include/game_rows.php).
		$search = game_search_condition($condition);
		
		// The toolbar of the lists of games (games.php), in two lines: as a player or as a referee, the
		// filter by name, the club/city/country, the result - which for a player also says whether
		// they won - and their role; then the dates and the three-state filters.
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('moder' => NULL, 'result' => NULL, 'page' => NULL)), get_label('As a player'), $moder == 0),
			array(card_url(array('moder' => 1, 'result' => NULL, 'roles' => NULL, 'page' => NULL)), get_label('As a referee'), $moder != 0)), get_label('Games'));
		echo game_search_field($search);
		echo '<div class="tb-field">';
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '</div>';
		if ($moder != 0)
		{
			$options = array(0 => get_label('All games'), GAME_RESULT_TOWN => get_label('Town wins'), GAME_RESULT_MAFIA => get_label('Mafia wins'), GAME_RESULT_TIE => get_label('Ties'));
		}
		else
		{
			$options = array(0 => get_label('All games'), 1 => get_label('Town wins'), 2 => get_label('Mafia wins'), 3 => get_label('[0] wins', $this->name), 4 => get_label('[0] losses', $this->name));
		}
		$results = array();
		foreach ($options as $r => $words)
		{
			$results[] = array(card_url(array('result' => ($r > 0 ? $r : NULL), 'page' => NULL)), $words, $result_filter == $r);
		}
		echo card_seg($results, get_label('Filter games by result.'));
		if ($moder == 0)
		{
			echo '<div class="tb-field">';
			show_roles_select($roles, 'filterChanged()', get_label('Games where [0] was in a specific role.', $this->name), ROLE_NAME_FLAG_SINGLE);
			echo '</div>';
		}
		echo '<div class="tb-line"><div class="tb-dates">';
		show_date_filter();
		echo '</div>';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</div></div>';
		
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.id = ? OR i.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
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
		
		if ($moder != 0)
		{
			if ($result_filter > 0)
			{
				$condition->add(' AND g.result = ?', $result_filter);
			}
			
			list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE g.moderator_id = ?', $this->id, $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			// The games the user refereed: the table of games.php without the referee - that is them.
			echo '<div class="sheet"><table class="tbl games">';
			echo '<thead><tr><th class="rk" scope="col">#</th>';
			echo '<th scope="col">' . get_label('Game') . '</th>';
			echo '<th scope="col">' . get_label('Result') . '</th>';
			echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
			echo '<th class="p3" scope="col">' . get_label('Tournament') . '</th>';
		echo '<th class="p4" scope="col">' . get_label('Event') . '</th>';
			echo '</tr></thead><tbody>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, nc.name, c.flags, ct.timezone, g.start_time, g.end_time - g.start_time, g.result, g.flags, g.video_id, e.id, ne.name, e.flags, t.id, nt.name, t.flags, g.table_num, g.game_num FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE g.moderator_id = ?',
				$this->id, $condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			$num = $_page * PAGE_SIZE;
			while ($row = $query->next())
			{
				$g = new stdClass();
				list ($g->id, $g->club_id, $g->club_name, $g->club_flags, $g->timezone, $g->start, $duration, $g->result, $g->flags, $g->video_id, $g->event_id, $g->event_name, $g->event_flags, $g->tournament_id, $g->tournament_name, $g->tournament_flags, $g->table_num, $g->game_num) = $row;
				echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
				// The game opens on the referee's side, as it always did. The round and the tournament have columns of their own.
				echo game_row_cell($g, array('time'), '&moderator_id=' . $this->id);
				echo game_result_cell($g) . game_club_cell($g) . game_tournament_cell($g) . game_event_cell($g, 'p4');
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			show_pages_navigation(PAGE_SIZE, $count);
		}
		else
		{
			$condition->add(get_roles_condition($roles));
			switch ($result_filter)
			{
				case 1:
					$condition->add(' AND g.result = 1');
					break;
				case 2:
					$condition->add(' AND g.result = 2');
					break;
				case 3:
					$condition->add(' AND p.won > 0');
					break;
				case 4:
					$condition->add(' AND p.won = 0');
					break;
			}
			
			list ($count) = Db::record(get_label('player'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = ?', $this->id, $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			// The games the user played: the table of games.php with their own part in each - their
			// role, whether they won, and the rating it brought them - instead of the referee.
			echo '<div class="sheet"><table class="tbl games">';
			echo '<thead><tr><th class="rk" scope="col">#</th>';
			echo '<th scope="col">' . get_label('Game') . '</th>';
			echo '<th scope="col">' . get_label('Result') . '</th>';
			echo '<th scope="col">' . get_label('Role') . '</th>';
			echo '<th class="num" scope="col">' . get_label('Rating') . '</th>';
			echo '<th class="p2" scope="col">' . get_label('Club') . '</th>';
			echo '<th class="p3" scope="col">' . get_label('Tournament') . '</th>';
		echo '<th class="p4" scope="col">' . get_label('Event') . '</th>';
			echo '</tr></thead><tbody>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, nc.name, c.flags, ct.timezone, g.start_time, g.end_time - g.start_time, g.result, g.flags, p.role, p.rating_before, p.rating_earned, g.video_id, e.id, ne.name, e.flags, t.id, nt.name, t.flags, g.table_num, g.game_num FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE p.user_id = ?', 
				$this->id, $condition);
			$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			$num = $_page * PAGE_SIZE;
			while ($row = $query->next())
			{
				$g = new stdClass();
				list ($g->id, $g->club_id, $g->club_name, $g->club_flags, $g->timezone, $g->start, $duration, 
					$g->result, $g->flags, $role, $rating_before, $rating_earned, $g->video_id, $g->event_id, $g->event_name, $g->event_flags, $g->tournament_id, $g->tournament_name, $g->tournament_flags, $g->table_num, $g->game_num) = $row;
				echo '<tr' . (($g->flags & GAME_FLAG_CANCELED) ? ' class="off"' : '') . '><td class="rk">' . ++$num . '</td>';
				// The game opens on the player's side, as it always did. The round and the tournament have columns of their own.
				echo game_row_cell($g, array('time'), '&user_id=' . $this->id);
				echo game_result_cell($g);
				
				// The role, and whether the player won in it: a role of the town wins with the town.
				$win = 0;
				switch ($role)
				{
					case 0: // civil
						$role_img = 'civ.png';
						$role_name = get_label('civil');
						$win = $g->result == GAME_RESULT_TOWN ? 1 : 2;
						break;
					case 1: // sheriff
						$role_img = 'sheriff.png';
						$role_name = get_label('sheriff');
						$win = $g->result == GAME_RESULT_TOWN ? 1 : 2;
						break;
					case 2: // mafia
						$role_img = 'maf.png';
						$role_name = get_label('mafia');
						$win = $g->result == GAME_RESULT_MAFIA ? 1 : 2;
						break;
					default: // don
						$role_img = 'don.png';
						$role_name = get_label('don');
						$win = $g->result == GAME_RESULT_MAFIA ? 1 : 2;
						break;
				}
				echo '<td class="role"><span class="role-cell"><img src="images/' . $role_img . '" alt="" width="20" height="20">' . $role_name . '</span>';
				if ($win == 1)
				{
					echo '<span class="chip res win">' . get_label('win') . '</span>';
				}
				else if ($win == 2)
				{
					echo '<span class="chip res loss">' . get_label('loss') . '</span>';
				}
				echo '</td>';
				
				// The rating: what the game brought, and under it the rating after (before and after in the hint). A canceled or non-rating game brought
				// none - its result says which.
				if (($g->flags & (GAME_FLAG_RATING | GAME_FLAG_CANCELED)) != GAME_FLAG_RATING)
				{
					echo '<td class="num dim">&ndash;</td>';
				}
				else
				{
					echo '<td class="num"><span class="strong">' . ($rating_earned >= 0 ? '+' : '&minus;') . format_rating(abs($rating_earned)) . '</span><span class="who-sub" style="white-space: nowrap;" title="' .
						format_rating(USER_INITIAL_RATING + $rating_before) . ' &rarr; ' . format_rating(USER_INITIAL_RATING + $rating_before + $rating_earned) . '">&rarr; ' . format_rating(USER_INITIAL_RATING + $rating_before + $rating_earned) . '</span></td>';
				}
				
				echo game_club_cell($g) . game_tournament_cell($g) . game_event_cell($g, 'p4');
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			show_pages_navigation(PAGE_SIZE, $count);
		}
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({roles: $('#roles').val(), filter: checkboxFilterFlags(), page: undefined});
		}
<?php
	}
}
$page = new Page();
$page->run(get_label('Games'));

?>
