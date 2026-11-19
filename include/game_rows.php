<?php

require_once __DIR__ . '/cards.php';
require_once __DIR__ . '/localization.php';

// The cells of the lists of games, the way games.php shows them (the preview of the v3 design):
// the game - its table and number over its tournament, round and time - its result as a chip, the
// club, the tournament and the referee with their names, the actions of an administrator under
// "≡". Every list of games builds its rows of these and of whatever cells of its own it has - the
// round of a tournament, the role and the rating of a player - so that they all look alike and
// none loses what it shows.
//
// A game is an object with these fields:
//   id, table_num, game_num, start, timezone, result, flags, video_id,
//   event_id, event_name, tournament_id, tournament_name, tournament_flags,
//   club_id, club_name, club_flags, moder_id, moder_name, moder_flags
// Whatever a page does not read may be left out where it does not show that cell.

// What a game is called: "Table 1, Game 4", "Game 4", "Game #123".
function game_row_title($g)
{
	if (is_null($g->game_num))
	{
		return get_label('Game #[0]', $g->id);
	}
	if (is_null($g->table_num))
	{
		return get_label('Game [0]', $g->game_num);
	}
	return get_label('Table [0], Game [1]', $g->table_num, $g->game_num);
}

// The game: its name, and under it where and when it was played. $show is what the line under it
// names - a page of a tournament does not repeat the tournament on every row, nor a page of a round
// the round: 'tournament', 'event', 'time'. $url_extra is added to the address of the game, so that
// it opens in the context of the list: '&club_id=59', '&user_id=1269'.
function game_row_cell($g, $show = array('tournament', 'event', 'time'), $url_extra = '')
{
	$sub = array();
	if (in_array('tournament', $show) && isset($g->tournament_name) && !is_null($g->tournament_name))
	{
		$sub[] = $g->tournament_name;
	}
	if (in_array('event', $show) && isset($g->event_name))
	{
		$sub[] = $g->event_name;
	}
	if (in_array('time', $show))
	{
		$sub[] = format_date($g->start, $g->timezone, true);
	}
	$html = '<td><span class="game-cell"><a class="who" href="view_game.php?id=' . $g->id . $url_extra . '&bck=1"><span class="who-txt"><span class="who-name">' . game_row_title($g) . '</span>';
	if (count($sub) > 0)
	{
		$html .= '<span class="who-sub">' . implode(', ', $sub) . '</span>';
	}
	$html .= '</span></a>';
	if (!is_null($g->video_id))
	{
		$html .= '<a class="icon video" href="javascript:mr.watchGameVideo(' . $g->id . ')" title="' . get_label('Watch game [0] video', $g->id) . '">' . PageBase::icon('video') . '</a>';
	}
	return $html . '</span></td>';
}

// The result as a chip - town red, mafia dark, a tie grey - and under it what makes the game count
// less: canceled, non-rating.
function game_result_cell($g)
{
	$html = '<td class="res">';
	switch ($g->result)
	{
		case GAME_RESULT_TOWN:
			$html .= '<span class="chip res town">' . get_label('Town wins~game') . '</span>';
			break;
		case GAME_RESULT_MAFIA:
			$html .= '<span class="chip res maf">' . get_label('Mafia wins~game') . '</span>';
			break;
		case GAME_RESULT_TIE:
			$html .= '<span class="chip res tie">' . get_label('Tie') . '</span>';
			break;
	}
	if ($g->flags & GAME_FLAG_CANCELED)
	{
		$html .= '<span class="chip res note">' . get_label('Canceled') . '</span>';
	}
	else if (($g->flags & GAME_FLAG_RATING) == 0)
	{
		$html .= '<span class="chip res note">' . get_label('Non-rating') . '</span>';
	}
	return $html . '</td>';
}

// The club with its logo and name.
function game_club_cell($g, $class = 'p2')
{
	static $club_pic = NULL;
	if (is_null($club_pic))
	{
		$club_pic = new Picture(CLUB_PICTURE);
	}
	return '<td class="' . $class . '">' . card_club($club_pic, $g->club_id, $g->club_name, $g->club_flags) . '</td>';
}

// The tournament with its logo and name, a dash for a game of no tournament.
function game_tournament_cell($g, $class = 'p3')
{
	static $tournament_pic = NULL;
	if (is_null($tournament_pic))
	{
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
	}
	if (is_null($g->tournament_id))
	{
		return '<td class="' . $class . '"><span class="dim">&ndash;</span></td>';
	}
	$tournament_pic->set($g->tournament_id, $g->tournament_name, $g->tournament_flags);
	$logo = $tournament_pic->has_image() ? '<img src="' . $tournament_pic->url(ICONS_DIR) . '" alt="" loading="lazy" decoding="async">' : '<span class="club-ini" aria-hidden="true">' . card_initials($g->tournament_name) . '</span>';
	return '<td class="' . $class . '"><a class="club" href="tournament.php?bck=1&id=' . $g->tournament_id . '" title="' . strip_tags($g->tournament_name) . '">' . $logo . '<span>' . $g->tournament_name . '</span></a></td>';
}

// A round of a tournament or an event, linked, with its picture.
function game_event_cell($g, $class = 'p3')
{
	static $event_pic = NULL;
	if (is_null($event_pic))
	{
		$event_pic = new Picture(EVENT_PICTURE);
	}
	$event_pic->set($g->event_id, $g->event_name, isset($g->event_flags) ? $g->event_flags : 0);
	$logo = $event_pic->has_image() ? '<img src="' . $event_pic->url(ICONS_DIR) . '" alt="" loading="lazy" decoding="async">' : '<span class="club-ini" aria-hidden="true">' . card_initials($g->event_name) . '</span>';
	return '<td class="' . $class . '"><a class="club" href="event.php?bck=1&id=' . $g->event_id . '" title="' . strip_tags($g->event_name) . '">' . $logo . '<span>' . $g->event_name . '</span></a></td>';
}

// The referee with their picture and name.
function game_referee_cell($g, $class = 'p2')
{
	static $user_pic = NULL;
	if (is_null($user_pic))
	{
		$user_pic = new Picture(USER_PICTURE);
	}
	if (is_null($g->moder_id))
	{
		return '<td class="' . $class . '"><span class="dim">&ndash;</span></td>';
	}
	return '<td class="' . $class . '"><a class="who" href="user_info.php?bck=1&id=' . $g->moder_id . '">' . card_avatar($user_pic, $g->moder_id, $g->moder_name, $g->moder_flags) . '<span class="who-txt"><span class="who-name">' . $g->moder_name . '</span></span></a></td>';
}

// The actions of whoever may change the game, under "≡": edit, the video, delete. $can_edit is
// whether the viewer may; an empty cell otherwise, so that the columns stay in their places.
function game_actions_cell($g, $can_edit)
{
	if (!$can_edit)
	{
		return '<td class="tb-btns"></td>';
	}
	$items = array();
	$items[] = array('mr.editGame(' . $g->id . ')', 'edit.png', get_label('Edit game json [0]', $g->id));
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
	return '<td class="tb-btns">' . card_row_menu($items) . '</td>';
}

// The filter by name of the preview, asked of the database rather than of the rows on the page:
// a list is a page of a hundred out of thousands. It finds the games whose tournament, round, club
// or referee is called so. Adds the condition to $condition (a list of games aliased g) and returns
// the words searched for.
function game_search_condition($condition)
{
	$search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
	if ($search != '')
	{
		$like = '%' . str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $search) . '%';
		$condition->add(
			' AND (g.club_id IN (SELECT c.id FROM clubs c JOIN names n ON n.id = c.name_id WHERE n.name LIKE ?)' .
			' OR g.event_id IN (SELECT e.id FROM events e JOIN names n ON n.id = e.name_id WHERE n.name LIKE ?)' .
			' OR g.tournament_id IN (SELECT t.id FROM tournaments t JOIN names n ON n.id = t.name_id WHERE n.name LIKE ?)' .
			' OR g.moderator_id IN (SELECT u.id FROM users u JOIN names n ON n.id = u.name_id WHERE n.name LIKE ?))',
			$like, $like, $like, $like);
	}
	return $search;
}

// Its field, for the toolbar. Its id is not "search": that is the search of the site in the header.
function game_search_field($search)
{
	$html = '<div class="field grow">' . PageBase::icon('search');
	$html .= '<input type="text" id="games-search" value="' . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . '" placeholder="' . get_label('Filter by name') . '" title="' . get_label('Tournament, round, club or referee') . '" onkeydown="if (event.key == \'Enter\') gamesSearch()">';
	$html .= '<button class="small_icon" onclick="$(\'#games-search\').val(\'\'); gamesSearch()" title="' . get_label('Clear') . '"><img src="images/clear.png" width="12"></button>';
	$html .= '</div>';
	$html .= '<script>function gamesSearch() { var s = $.trim($("#games-search").val()); goTo({search: (s == "" ? undefined : s), page: undefined}); }</script>';
	return $html;
}

// The switch of the result of the toolbar: all, town, mafia, ties.
function game_result_seg($result_filter)
{
	$results = array();
	foreach (array(-1 => get_label('All games'), GAME_RESULT_TOWN => get_label('Town wins'), GAME_RESULT_MAFIA => get_label('Mafia wins'), GAME_RESULT_TIE => get_label('Ties')) as $r => $words)
	{
		$results[] = array(card_url(array('results' => ($r > 0 ? $r : NULL), 'page' => NULL)), $words, ($r > 0 ? $result_filter == $r : $result_filter <= 0));
	}
	return card_seg($results, get_label('Filter games by result.'));
}

?>
