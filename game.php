<?php

require_once 'include/page_base.php';
require_once 'include/game.php';
require_once 'include/event.php';
require_once 'include/cards.php';

class Page extends PageBase
{
	protected function add_headers()
	{
		global $_lang;
		// Through versioned_asset like everything page_base.php loads: these are built files that
		// change under the same name, and without the modification time in the address a browser
		// keeps handing back the copy it already has.
		// Only for the game itself. game.css imports common.css once more, after new_look.css, and
		// the old rules come back over the band of the screens before the game - the way back
		// turned into an empty pill with its word under it.
		if ($this->is_game_screen())
		{
			echo '<link rel="stylesheet" href="' . versioned_asset('game.css') . '" type="text/css" media="screen" />';
			// The look of the site over it, and the whole window for the game - see game_look.css.
			echo '<link rel="stylesheet" href="' . versioned_asset('game_look.css') . '" type="text/css" media="screen" />';
		}
		echo '<script src="' . versioned_asset('js/game.js') . '"></script>';
		echo '<script src="' . versioned_asset('js/game-ui.js') . '"></script>';
		echo '<script src="' . versioned_asset('js/game_' . get_lang_code($_lang) . '.js') . '"></script>';
	}
	
	// The game itself is drawn by js/game-ui.js and has a look of its own (game.css). The screens
	// before it - the game day, the table, the game, the unfinished games - are pages of the site.
	private function is_game_screen()
	{
		return !$this->unfinished && ($this->demo || ($this->event_id > 0 && $this->table_num > 0 && $this->game_num > 0));
	}

	// The band of the screens before the game: what is being chosen, and where.
	protected function show_title()
	{
		global $_lang, $_profile;

		if ($this->is_game_screen())
		{
			return; // no title to save space for the game
		}

		$sub = '';
		$actions = '';
		$art = '';
		$facts = array();
		if ($this->unfinished)
		{
			$title = get_label('Unfinished games');
		}
		else if ($this->event_id <= 0)
		{
			$title = get_label('The game');
			$sub = get_label('Choose a game day');
			if ($this->tournament_id > 0)
			{
				$create = 'mr.createRound(' . $this->tournament_id . ', true)';
				$create_title = get_label('Create [0]', get_label('tournament round'));
			}
			else
			{
				$create = 'mr.createEvent(' . ($this->club_id > 0 ? $this->club_id : 'undefined') . ', true)';
				$create_title = get_label('Create [0]', get_label('event'));
			}
			$actions =
				'<a class="btn ghost" href="game.php?demo=1">' . PageBase::icon('play') . '<span>' . get_label('Demo game') . '</span></a>' .
				'<a class="btn" href="#" onclick="' . $create . '; return false;">' . PageBase::icon('plus') . '<span>' . $create_title . '</span></a>';
		}
		else
		{
			// The game day and its tournament said in full, every one of them a link: whoever is
			// about to referee wants to look at them now and then.
			list ($event_name, $event_flags, $start_time, $duration, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $address_id, $address_name, $timezone) = Db::record(get_label('event'),
				'SELECT ne.name, e.flags, e.start_time, e.duration, t.id, nt.name, t.flags, c.id, nc.name, c.flags, a.id, na.name, ct.timezone FROM events e' .
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN clubs c ON c.id = e.club_id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE e.id = ?', $this->event_id);
			$event_url = 'event.php?bck=1&id=' . $this->event_id;
			$where = is_null($tournament_name) ? $event_name : $tournament_name . ': ' . $event_name;
			if ($this->table_num <= 0)
			{
				$title = '<a href="' . $event_url . '">' . $where . '</a>';
				$sub = get_label('Choose a table');
			}
			else
			{
				$title = get_label('Table [0]', $this->table_num);
				$sub = get_label('Choose a game');
			}

			$pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
			$pic->
				set($this->event_id, $event_name, $event_flags)->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($club_id, $club_name, $club_flags);
			$art = '<a class="eh-art" href="' . $event_url . '">' . PageBase::capture(function() use ($pic) { $pic->show(ICONS_DIR, false); }) . '</a>';

			if (!is_null($tournament_id))
			{
				$facts[] = array('trophy', '<a href="tournament.php?bck=1&id=' . $tournament_id . '">' . $tournament_name . '</a>');
			}
			$facts[] = array('cal', '<a href="' . $event_url . '">' . $event_name . '</a>');
			$facts[] = array('status', format_date_period($start_time, $duration, $timezone, true));
			$facts[] = array('users', '<a href="club_main.php?bck=1&id=' . $club_id . '">' . $club_name . '</a>');
			$facts[] = array('pin', '<a href="address_info.php?bck=1&id=' . $address_id . '">' . $address_name . '</a>');
		}

		echo '<div class="page-hd"><div class="game-hd">' . $art . '<div><h1>' . $title . '</h1>';
		if ($sub != '')
		{
			echo '<p class="page-hd-sub">' . $sub . '</p>';
		}
		echo '</div></div><div class="page-hd-side"><div class="page-hd-acts">' . $actions . '</div>';
		show_back_button();
		echo '</div></div>';
		if (count($facts) > 0)
		{
			echo '<dl class="eh-facts game-facts">';
			foreach ($facts as $fact)
			{
				echo '<div><dt>' . PageBase::icon($fact[0]) . '</dt><dd>' . $fact[1] . '</dd></div>';
			}
			echo '</dl>';
		}
	}

	protected function content_class()
	{
		return $this->is_game_screen() ? 'game-page' : 'nl';
	}

	// The game takes the whole window: no header, no footer, nothing to scroll.
	protected function body_class()
	{
		return $this->is_game_screen() ? 'game-full' : '';
	}

	protected function prepare()
	{
		$this->club_id = 0;
		$this->tournament_id = 0;
		$this->event_id = 0;
		$this->table_num = 0;
		$this->game_num = 0;
		$this->demo = false;
		$this->unfinished = false;
		
		if (isset($_REQUEST['event_id']))
		{
			$this->event_id = (int)$_REQUEST['event_id'];
			list ($this->club_id, $this->tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $this->event_id);
			$this->club_id = (int)$this->club_id;
			$this->tournament_id = is_null($this->tournament_id) ? 0 : (int)$this->tournament_id;
			
			if (isset($_REQUEST['table_num']))
			{
				$this->table_num = (int)$_REQUEST['table_num'];
			}
			if (isset($_REQUEST['game_num']))
			{
				$this->game_num = (int)$_REQUEST['game_num'];
			}
		}
		else if (isset($_REQUEST['tournament_id']))
		{
			$this->tournament_id = (int)$_REQUEST['tournament_id'];
			list ($this->club_id) = Db::record(get_label('tournament'), 'SELECT club_id FROM tournaments WHERE id = ?', $this->tournament_id);
			$this->club_id = (int)$this->club_id;
		}
		else if (isset($_REQUEST['club_id']))
		{
			$this->club_id = (int)$_REQUEST['club_id'];
		}
		else if (isset($_REQUEST['bug_id']))
		{
			$bug_id = (int)$_REQUEST['bug_id'];
			list ($event_id, $table_num, $game_num, $game, $log) = Db::record('bug', 'SELECT event_id, table_num, game_num, game, log FROM bug_reports WHERE id = ?', $bug_id);
			
			$langs = array();
			$lang = LANG_NO;
			while (($lang = get_next_lang($lang, LANG_ALL)) != LANG_NO)
			{
				$l = new stdClass();
				$l->code = get_lang_code($lang);
				$l->name = get_lang_str($lang);
				$langs[] = $l;
			}
			
			$data = new stdClass();
			$data->game = json_decode($game);
			$data->log = json_decode($log);
			$data->regs = get_event_reg_array($event_id);
			$data->langs = $langs;
			$_SESSION['demogame'] = $data;
			
			$this->table_num = $table_num;
			$this->game_num = $game_num;
			$this->demo = true;
		}
		else if (isset($_REQUEST['demo']))
		{
			$this->table_num = 1;
			$this->game_num = 1;
			$this->demo = true;
		}
		else if (isset($_REQUEST['unfinished']))
		{
			$this->unfinished = true;
		}
	}
	
	// The game days going on now where the referee may referee, as cards; the unfinished games of the
	// referee said over them.
	private function select_event()
	{
		global $_lang, $_profile;

		$condition = new SQL('UNIX_TIMESTAMP() >= e.start_time AND UNIX_TIMESTAMP() < e.start_time + e.duration + ' . EVENT_ALIVE_TIME);
		if ($this->club_id > 0)
		{
			$condition->add(' AND e.club_id = ?', $this->club_id);
			check_permissions(PERMISSION_CLUB_REFEREE, $this->club_id);
		}
		if ($this->tournament_id > 0)
		{
			$condition->add(' AND e.tournament_id = ?', $this->tournament_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->tournament_id);
		}
		if (!is_permitted(PERMISSION_ADMIN))
		{
			$condition->add(
				' AND ( e.club_id IN (SELECT club_id FROM club_regs WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?)' .
				' OR e.id IN (SELECT event_id FROM event_regs WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?)' .
				' OR e.tournament_id IN (SELECT tournament_id FROM tournament_regs WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?))', $_profile->user_id, $_profile->user_id, $_profile->user_id);
		}

		list ($unfinished_count) = Db::record(get_label('game'), 'SELECT COUNT(*) FROM current_games WHERE user_id = ?', $_profile->user_id);
		if ($unfinished_count > 0)
		{
			echo '<div class="game-note">' . PageBase::icon('status') . '<span>' . get_label('You have unfinished games. Please finish or cancel them.') . '</span>';
			echo '<a class="btn" href="game.php?bck=1&unfinished=1">' . get_label('Unfinished games') . ' (' . $unfinished_count . ')</a></div>';
		}

		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, e.id, ne.name, e.start_time, e.flags, ct.timezone, t.id, nt.name, t.flags, na.name' .
			' FROM events e' .
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id WHERE ',
			$condition);
		$query->add(' ORDER BY e.start_time, e.id');
		$count = 0;
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $event_id, $event_name, $start_time, $event_flags, $timezone, $tour_id, $tour_name, $tour_flags, $addr_name) = $row;
			if ($count++ == 0)
			{
				echo '<div class="pick-grid">';
			}
			$event_pic->
				set($event_id, $event_name, $event_flags)->
				set($tour_id, $tour_name, $tour_flags)->
				set($club_id, $club_name, $club_flags);
			echo '<a class="pick" href="game.php?bck=1&event_id=' . $event_id . '">' . card_logo($event_pic, is_null($tour_name) ? $event_name : $tour_name);
			if (!is_null($tour_name))
			{
				echo '<span class="pick-sub">' . $tour_name . '</span>';
			}
			echo '<span class="pick-title">' . $event_name . '</span>';
			echo '<span class="pick-sub">' . PageBase::icon('users') . $club_name . '</span>';
			echo '<span class="pick-sub">' . PageBase::icon('status') . format_date($start_time, $timezone, true) . '</span></a>';
		}
		if ($count > 0)
		{
			echo '</div>';
		}
		else
		{
			echo '<p class="empty">' . get_label('No events found.') . '</p>';
		}
	}

	// The tables of the game day: as many as its seating has, or one more than have been played at.
	private function select_table()
	{
		global $_lang;

		list ($misc, $event_id, $tournament_id, $club_id) = Db::record(get_label('event'), 'SELECT misc, id, tournament_id, club_id FROM events WHERE id = ?', $this->event_id);
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);

		$num_tables = -1;
		if (!is_null($misc))
		{
			$misc = json_decode($misc);
			// The rounds themselves and not just the seating around them: a seating saved
			// without them lays nothing out, and reading them off it handed count() a null,
			// which on PHP 8 is a TypeError and takes the page down instead of warning. Such
			// a seating is no seating, so the count below falls through to what has actually
			// been played. The same guard stream_text_tables() makes.
			if (isset($misc->seating->rounds) && is_array($misc->seating->rounds))
			{
				$t = $misc->seating->rounds;
				$num_tables = (count($t) > 0 && is_array($t[0])) ? count($t[0]) : 0;
			}
		}
		if ($num_tables < 0)
		{
			list ($max_table_num1) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM games WHERE event_id = ?', $this->event_id);
			$max_table_num1 = is_null($max_table_num1) ? 0 : (int)$max_table_num1;
			list ($max_table_num2) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM current_games WHERE event_id = ?', $this->event_id);
			$max_table_num2 = is_null($max_table_num2) ? 0 : (int)$max_table_num2;
			$num_tables = max($max_table_num1, $max_table_num2) + 1;
		}

		// How far every table has got: the games finished and the games going on.
		$played = array();
		$query = new DbQuery('SELECT table_num, COUNT(*) FROM games WHERE event_id = ? AND (flags & ' . GAME_FLAG_CANCELED . ') = 0 GROUP BY table_num', $this->event_id);
		while ($row = $query->next())
		{
			$played[(int)$row[0]] = (int)$row[1];
		}
		$playing = array();
		$query = new DbQuery('SELECT table_num, COUNT(*) FROM current_games WHERE event_id = ? GROUP BY table_num', $this->event_id);
		while ($row = $query->next())
		{
			$playing[(int)$row[0]] = (int)$row[1];
		}

		echo '<div class="pick-grid tables">';
		for ($i = 1; $i <= $num_tables; ++$i)
		{
			echo '<a class="pick" href="game.php?bck=1&event_id=' . $this->event_id . '&table_num=' . $i . '">';
			echo '<span class="pick-label">' . get_label('Table') . '</span><span class="pick-num">' . $i . '</span>';
			if (isset($playing[$i]))
			{
				echo '<span class="chip live">' . get_label('playing now') . '</span>';
			}
			if (isset($played[$i]))
			{
				echo '<span class="pick-sub">' . get_label('Games played') . ': ' . $played[$i] . '</span>';
			}
			echo '</a>';
		}
		echo '</div>';
	}

	// The games of the table: the finished ones lead to their pages, the one going on to the game,
	// one taken by another referee asks whether to take it over, and the next one starts a new game.
	private function select_game()
	{
		global $_lang, $_profile;

		list ($misc, $event_id, $tournament_id, $club_id) = Db::record(get_label('event'), 'SELECT misc, id, tournament_id, club_id FROM events WHERE id = ?', $this->event_id);
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);

		$num_games = -1;
		if (!is_null($misc))
		{
			$misc = json_decode($misc);
			if (isset($misc->seating->rounds) && is_array($misc->seating->rounds))
			{
				$rounds = $misc->seating->rounds;
				if (count($rounds) > 0 && is_array($rounds[0]) && $this->table_num <= count($rounds[0]))
				{
					$num_games = count($rounds);
				}
			}
		}
		$games = $num_games > 0 ? array_fill(0, $num_games, NULL) : array();

		$query = new DbQuery(
			'SELECT g.game_num, g.user_id, n.name'.
			' FROM current_games g'.
			' JOIN users u ON u.id = g.user_id'.
			' JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0'.
			' WHERE event_id = ? AND table_num = ?', $this->event_id, $this->table_num);
		while ($row = $query->next())
		{
			list ($game_num, $user_id, $user_name) = $row;
			$index = $game_num - 1;
			while ($index >= count($games))
			{
				$games[] = NULL;
			}
			$games[$index] = new stdClass();
			$games[$index]->user_id = (int)$user_id;
			$games[$index]->user_name = $user_name;
		}

		$query = new DbQuery('SELECT id, game_num, flags, result FROM games WHERE event_id = ? AND table_num = ?', $this->event_id, $this->table_num);
		while ($row = $query->next())
		{
			$r = new stdClass();
			list ($r->game_id, $game_num, $r->flags, $r->result) = $row;
			$index = $game_num - 1;
			while ($index >= count($games))
			{
				$games[] = NULL;
			}
			$games[$index] = $r;
		}
		if ($num_games <= 0)
		{
			$games[] = NULL;
		}

		echo '<div class="pick-grid tables">';
		for ($i = 0; $i < count($games); ++$i)
		{
			$r = $games[$i];
			$game_url = 'game.php?bck=1&event_id=' . $this->event_id . '&table_num=' . $this->table_num . '&game_num=' . ($i + 1);
			$class = 'pick';
			$onclick = '';
			if (is_null($r))
			{
				$class .= ' is-new';
				$url = $game_url;
				$state = '<span class="pick-sub">' . get_label('New game') . '</span>';
			}
			else if (isset($r->game_id))
			{
				$class .= ' is-done';
				$url = 'view_game.php?bck=1&event_id=' . $event_id . '&id=' . $r->game_id;
				if ($r->result <= 0)
				{
					$state = '<span class="pick-sub">' . get_label('Playing using different method') . '</span>';
					$url = NULL;
				}
				else if ($r->flags & GAME_FLAG_CANCELED)
				{
					$state = '<span class="chip off">' . get_label('Canceled') . '</span>';
				}
				else
				{
					$state = '<span class="chip">' . get_label('Complete') . '</span>';
				}
			}
			else if ($r->user_id == $_profile->user_id)
			{
				$url = $game_url;
				$state = '<span class="chip live">' . get_label('Playing now') . '</span>';
			}
			else
			{
				$class .= ' is-busy';
				$url = '#';
				$onclick = ' onclick="mr.ownGame(' . $this->event_id . ',' . $this->table_num . ',' . ($i + 1) . ',' . $r->user_id . ',\'' . get_label('[0] is already moderating this game. Do you want to take is over from them?', $r->user_name) . '\'); return false;"';
				$state = '<span class="pick-sub">' . get_label('Moderated by [0] now', $r->user_name) . '</span>';
			}

			$inner = '<span class="pick-label">' . get_label('Game') . '</span><span class="pick-num">' . ($i + 1) . '</span>' . $state;
			if (is_null($url))
			{
				echo '<div class="' . $class . '">' . $inner . '</div>';
			}
			else
			{
				echo '<a class="' . $class . '" href="' . $url . '"' . $onclick . '>' . $inner . '</a>';
			}
		}
		echo '</div>';
	}

	private function game()
	{
		global $_lang, $_profile;
		
		if ($this->event_id > 0)
		{
			list ($event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags) = 
				Db::record(get_label('event'), 
				'SELECT ne.name, e.flags, t.id, nt.name, t.flags, c.id, nc.name, c.flags'.
				' FROM events e'.
				' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
				' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0'.
				' JOIN clubs c ON c.id = e.club_id'.
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
				' WHERE e.id = ?', $this->event_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $this->event_id, $tournament_id);
		}
		else
		{
			$event_name = get_label('Demo');
		}
		
		if ($this->event_id > 0)
		{
			$pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
			$pic->
				set($this->event_id, $event_name, $event_flags)->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($club_id, $club_name, $club_flags);
		}
		else
		{
			$pic = new Picture(USER_PICTURE);
			$pic->set($_profile->user_id, $_profile->user_name, $_profile->user_flags);
			
			echo '<div id="demo">'.get_label('Demo').'</div>';
		}
		
		$back_page = get_back_page();
		echo '<ul id="ops-menu" style="position:absolute;" hidden>';
		if (empty($back_page))
		{
			echo '<li id="back" class="ops-item"><a href="#" onclick="goTo({game_num:undefined, demo:undefined})"><img src="images/prev.png" class="text"> '.get_label('Back').'</li>';
		}
		else
		{
			echo '<li id="back" class="ops-item"><a href="'.$back_page.'"><img src="images/prev.png" class="text"> '.get_label('Back').'</li>';
		}
		echo '<li id="cancel" class="ops-item"><a href="#" onclick="uiCancelGame(\''.$back_page.'\')"><img src="images/delete.png" class="text"> '.get_label('Cancel the game').'</li>';
		echo '<li type="separator"></li>';
		if ($this->event_id > 0)
		{
			echo '<li id="bug" class="ops-item"><a href="#" onclick="uiBugReport()"><img src="images/bug.png" class="text"> '.get_label('Report a bug').'</li>';
			echo '<li type="separator"></li>';
			// echo '<li id="voting" class="ops-item"><a href="#" onclick="gameToggleVoting()"><img src="images/vote.png" class="text"> <span id="voting-txt">'.get_label('Cancel voting').'</span></a></li>';
			// echo '<li type="separator"></li>';
			// Everything about the stream is set up on one page now - the addresses of the
			// overlays, what is shown between games, and which scene goes with which phase
			// of the game. A round of a tournament is set up through its tournament, so the
			// question asked here is the one that page will ask - but the page is opened on
			// the round being played, so that the recordings it lists are this round's.
			if (is_null($tournament_id)
				? is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE, $club_id, $this->event_id)
				: is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $tournament_id))
			{
				echo '<li id="obs" class="ops-item"><a href="#" onclick="goTo(\'streams.php\',{bck:1,';
				echo 'event_id:' . $this->event_id;
				echo '})"><img src="images/obs.png" class="text"> '.get_label('OBS').'</a></li>';
			}
		}
		// The whole screen for the game: on a phone the address bar and the system bars take a good part of
		// it. Hidden where the browser cannot do it (an iPhone) - see gameFullScreen().
		echo '<li id="fullscreen" class="ops-item"><a href="#" onclick="gameFullScreen()"><span class="ops-ic">⛶</span> '.get_label('Full screen').'</a></li>';
		echo '<li id="settings" class="ops-item"><a href="#" onclick="uiSetRules()"><img src="images/rules.png" class="text"> '.get_label('Rules').'</a></li>';
		echo '<li id="settings" class="ops-item"><a href="#" onclick="uiSettings()"><img src="images/settings.png" class="text"> '.get_label('Settings').'</a></li>';
		echo '</ul>';
		
		// In a box as tall as the window leaves it, and never taller: the table fills it, and Back and
		// Next under it stay in the window whatever the rows hold - see game_look.css.
		echo '<div class="game-table">';
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="day-empty header-row" align="center"><td id="head" colspan="6">';
		
		echo '<table class="transp" width="100%">';
		echo '<tr>';
		echo '<td width="64">';
		echo '<button id="ops" class="ops">';
		$pic->show(ICONS_DIR, false, 60);
		echo '</button>';
		echo '</td>';
		echo '<td align="center">';
		echo '<table class="transp" id="status-box" width="100%">';
		echo '<tr><td id="status-title"></td></tr>';
		echo '<tr><td id="status"></td></tr>';
		echo '</table>';
		echo '</td>';
		
		echo '<td width="320" id="clock">';
		// echo '<table id="t-area" class="timer timer-0" width="100%"><tr>';
		// echo '<td width="1"><button id="timerBtn" class="timer" onclick="uiToggleTimer()"><img id="timerImg" src="images/resume_big.png" class="timer"></button></td>';
		// echo '<td><div id="timer" class="timer"></div></td>';
		// echo '<td width="1"><button class="timer" onclick="uiIncTimer(-10)"><img src="images/dec_big.png" class="timer"></button></td>';
		// echo '<td width="1"><button class="timer" onclick="uiIncTimer(10)"><img src="images/inc_big.png" class="timer"></button></td>';
		// echo '</tr></table>';
		echo '</td>';
		
		echo '</tr>';
		echo '</table>';
		
		echo '</td></tr>';
		
		for ($i = 0; $i < 10; ++$i)
		{
			echo '<tr class="day-alive player-row" id="r'.$i.'">';
			echo '<td width="20" align="center" id="num'.$i.'">'.($i+1).'</td>';
			echo '<td id="name'.$i.'">';
			
			echo '<table class="invis" width="100%"><tr>';
			echo '<td width="24"><button id="reg-'.$i.'" class="icon" onclick="uiRegisterPlayer('.$i.')"><img src="images/user.png" class="icon"></button></td>';
			echo '<td width="24"><button id="reg-new-'.$i.'" class="icon" onclick="uiCreatePlayer('.$i.')"><img src="images/create.png" class="icon"></button></td>';
			echo '<td id="pselect'.$i.'"><select id="player'.$i.'" onchange="uiSetPlayer('.$i.')"></select></td>';
			echo '<td id="controlx'.$i.'" width="114" align="right"></td>';
			echo '</tr></table>';
			echo '</td>';
			
			echo '<td id="panel'.$i.'" width="114"></td>';
			echo '<td id="control'.$i.'" width="160"></td>';
			echo '<td id="warn'.$i.'" width="100"></td>';
			echo '<td id="btns-'.$i.'" width="60" align="center"></td>';
			echo '</tr>';
		}
		echo '<tr class="day-empty footer-row" id="r-1">';
		echo '<td colspan="3">';
		echo '<table class="invis" width="100%"><tr>';
		echo '<td width="36"><img id="saving-img" border="0" src="images/connected.png"></td><td>'.get_label('version').' <span id="version"></span></td>';
		echo '<td id="saving"></td>';
		echo '<td align="right"><button id="game-id" class="config-btn" onclick="uiConfig()"><b>'.get_label('[0]: Table [1]. Game [2].', $event_name, $this->table_num, $this->game_num).'</b></button></td>';
		echo '</tr></table>';
		echo '</td>';
		echo '<td id="control-1"></td>';
		echo '<td id="noms" colspan="2"></td>';
		echo '</tr>';
		echo '</table>';
		echo '</div>';
		
		echo '<div class="btn-panel"><table class="transp" width="100%"><tr>';
		echo '<td><button class="game-btn" id="game-back" onclick="uiBack()"><img src="images/prev.png" class="text" title="' . get_label('Back') . '"></button></td>';
		echo '<td align="center"><div id="scene-btns"></div></td>';
		echo '<td align="right"><button class="game-btn" id="game-next" onclick="uiNext()" title="' . get_label('Next') . '"><img src="images/next.png" class="text"></button></td>';
		echo '</tr></table></div>';
		echo '<span id="sound"></span>';
	}
	
	// The games the referee began and did not finish, as cards: back into one, or delete it.
	private function select_unfinished()
	{
		global $_lang, $_profile;

		check_permissions(PERMISSION_USER);

?>
		<script>
		function deleteGame(eventId, tableNum, gameNum)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete the game?'); ?>", null, null, function()
			{
				json.post('api/ops/game.php', {op:'cancel_current', event_id:eventId, table_num:tableNum, game_num:gameNum}, refr);
			});
		}
		</script>
<?php
		$pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		$count = 0;
		$query = new DbQuery(
			'SELECT e.id, ne.name, e.flags, t.id, nt.name, t.flags, c.id, nc.name, c.flags, g.table_num, g.game_num'.
			' FROM current_games g'.
			' JOIN events e ON e.id = g.event_id'.
			' JOIN names ne ON ne.id = e.name_id AND (ne.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' LEFT OUTER JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0'.
			' JOIN clubs c ON c.id = e.club_id'.
			' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0'.
			' WHERE g.user_id = ?'.
			' ORDER BY g.event_id, g.table_num, g.game_num',
			$_profile->user_id);
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $table_num, $game_num) = $row;
			if ($count++ == 0)
			{
				echo '<div class="pick-grid">';
			}
			$pic->
				set($event_id, $event_name, $event_flags)->
				set($tournament_id, $tournament_name, $tournament_flags)->
				set($club_id, $club_name, $club_flags);
			echo '<div class="pick-wrap"><a class="pick" href="game.php?bck=1&event_id=' . $event_id . '&table_num=' . $table_num . '&game_num=' . $game_num . '">';
			echo card_logo($pic, is_null($tournament_name) ? $event_name : $tournament_name);
			echo '<span class="pick-sub">' . (is_null($tournament_name) ? $club_name : $tournament_name) . '</span>';
			echo '<span class="pick-title">' . $event_name . '</span>';
			echo '<span class="chip">' . get_label('Table [0], Game [1]', $table_num, $game_num) . '</span></a>';
			echo '<button type="button" class="pick-del" onclick="deleteGame(' . $event_id . ', ' . $table_num . ', ' . $game_num . ')" title="' . get_label('Delete the game') . '" aria-label="' . get_label('Delete the game') . '">' . PageBase::icon('close') . '</button></div>';
		}
		if ($count > 0)
		{
			echo '</div>';
		}
		else
		{
			echo '<p class="empty">' . get_label('No unfinished games.') . '</p>';
		}
	}

	protected function show_body()
	{
		if ($this->unfinished)
		{
			$this->select_unfinished();
		}
		else if ($this->demo)
		{
			$this->game();
		}
		else if ($this->event_id <= 0)
		{
			$this->select_event();
		}
		else if ($this->table_num <= 0)
		{
			$this->select_table();
		}
		else if ($this->game_num <= 0)
		{
			$this->select_game();
		}
		else
		{
			$this->game();
		}
	}
	
	protected function js()
	{
		parent::js();
?>
		// The height of the game is what is seen of the window right now. A phone's browser counts
		// 100% - and does not always count 100dvh - with its address bar hidden, which left Back and
		// Next under the edge of the screen. The visual viewport says what is really there, and says
		// it again whenever the bar comes or goes. See --g-app-h in game_look.css.
		function gameFitHeight()
		{
			var h = window.visualViewport ? window.visualViewport.height : window.innerHeight;
			document.documentElement.style.setProperty('--g-app-h', Math.round(h) + 'px');
			document.documentElement.classList.add('g-fit');
			window.scrollTo(0, 0);
		}

		function gameFullScreen()
		{
			if (document.fullscreenElement)
			{
				document.exitFullscreen();
			}
			else if (document.documentElement.requestFullscreen)
			{
				document.documentElement.requestFullscreen({ navigationUI: 'hide' }).catch(function() {});
			}
		}

		// The scenes of the OBS standing beside this table, offered as buttons between Back and
		// Next while the game is not switching them by itself. Both the list and whether the
		// switching is on are settled elsewhere - the list by that OBS, the switching on the
		// stream settings page - and both can change while a game is being played, so they are
		// asked for again now and then rather than once when the game was opened.
		var _sceneEvent = 0, _sceneTable = 0;
		var _sceneNames = [], _sceneSwitching = true, _sceneCurrent = '';
		// What the game last asked for, and what somebody asked for over the top of it. A hand
		// switch stands until the game moves the scene on by itself, which is the same rule the
		// browser sources follow, so the two of them agree about what is showing.
		var _sceneAuto = '', _sceneOverride = '';

		function sceneButtonsStart(eventId, tableNum)
		{
			_sceneEvent = eventId;
			_sceneTable = tableNum;
			sceneButtonsRead();
			setInterval(sceneButtonsRead, 5000);
		}

		// Quietly: nobody is waiting for this, and a moment when the site does not answer is
		// not something to put a dialog up about in the middle of a game.
		function sceneButtonsRead()
		{
			fetch('api/get/stream.php?op=settings&event_id=' + _sceneEvent + '&table_num=' + _sceneTable).
				then(function(response) { return response.ok ? response.json() : Promise.reject(response.status); }).
				then(function(answer)
				{
					if (!answer || !answer.stream || !answer.stream.scene)
						return;
					let scene = answer.stream.scene;
					let names = scene.names ? scene.names : [];
					let switching = !!scene.switching;
					let auto = scene.auto ? scene.auto : '';
					if (auto != _sceneAuto)
					{
						_sceneAuto = auto;
						_sceneOverride = ''; // the game has moved on, so a hand switch is over
					}

					// What OBS is showing, as far as the site can tell: what somebody asked for
					// over the top of the game, or else the game's own scene, or else - where
					// the game is not switching at all - the last thing anybody asked for.
					// Somebody clicking about inside OBS is not seen from here, and that is the
					// one way this can be wrong.
					let current = _sceneOverride ? _sceneOverride :
						(switching ? auto : (scene.manual ? scene.manual : ''));
					if (switching == _sceneSwitching && current == _sceneCurrent &&
						JSON.stringify(names) == JSON.stringify(_sceneNames))
					{
						return; // nothing new to draw
					}
					_sceneNames = names;
					_sceneSwitching = switching;
					_sceneCurrent = current;
					sceneButtonsShow();
				}).
				catch(function(error) {});
		}

		// Offered whether or not the game is switching by itself - a scene put up by hand in a
		// pause is useful, and the game takes it back at the next phase anyway. Which of the two
		// is going on is said beside them rather than by taking them away, and the word sits in
		// a box of its own width with another to match on the other side, so that the buttons
		// stand in the same place either way. Nothing at all where the OBS at this table has
		// not said what scenes it has.
		function sceneButtonsShow()
		{
			let html = '';
			if (_sceneNames.length > 0)
			{
				html = '<span class="scene-mode">' + (_sceneSwitching ? <?php echo json_encode(get_label('Auto')); ?> : '') + '</span>';
				for (let i = 0; i < _sceneNames.length; ++i)
				{
					let current = (_sceneNames[i] == _sceneCurrent) ? ' current' : '';
					html += '<button class="scene-btn' + current + '" onclick="sceneButtonClick(' + i + ')">' +
						_sceneNames[i].replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</button>';
				}
				html += '<span class="scene-mode"></span>';
			}
			$('#scene-btns').html(html);
		}

		// Said out loud if it does not get through: this one somebody did ask for.
		function sceneButtonClick(i)
		{
			// Marked at once rather than when the next answer comes back: the scene is on its
			// way, and ten seconds of the button looking unpressed would be ten seconds of
			// wondering whether it worked.
			_sceneOverride = _sceneCurrent = _sceneNames[i];
			sceneButtonsShow();
			json.post('api/ops/stream.php',
				{ op: 'scene', event_id: _sceneEvent, table_num: _sceneTable, scene: _sceneNames[i] });
		}
<?php
	}

	protected function js_on_load()
	{
		echo 'gameFitHeight();';
		echo 'window.addEventListener("resize", gameFitHeight);';
		echo 'if (window.visualViewport) { window.visualViewport.addEventListener("resize", gameFitHeight); }';
		echo 'document.addEventListener("fullscreenchange", gameFitHeight);';
		echo 'if (!document.documentElement.requestFullscreen) { $("#fullscreen").remove(); }';
		if ($this->event_id > 0 && $this->table_num > 0 && $this->game_num > 0)
		{
			echo 'uiStart(' . $this->event_id . ', ' . $this->table_num . ', ' . $this->game_num . ');';

			// The buttons are offered only to somebody who may actually switch, which is the
			// same question the switching itself will ask. A round of a tournament is set up
			// through its tournament, so that is the one asked about.
			$may_switch = $this->tournament_id > 0
				? is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->tournament_id)
				: is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE, $this->club_id, $this->event_id);
			if ($may_switch)
			{
				echo 'sceneButtonsStart(' . $this->event_id . ', ' . $this->table_num . ');';
			}
		}
		else if ($this->demo)
		{
			echo 'uiStart(0, 0, 0);';
		}
	}
}

$page = new Page();
$page->run(get_label('The game'));

?>
