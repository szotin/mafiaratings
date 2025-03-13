<?php

require_once 'include/page_base.php';
require_once 'include/game.php';
require_once 'include/event.php';

define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends PageBase
{
	protected function add_headers()
	{
		global $_lang;
		echo '<link rel="stylesheet" href="game.css" type="text/css" media="screen" />';
		echo '<script src="js/game1.js"></script>';
		echo '<script src="js/game1-ui.js"></script>';
		echo '<script src="js/game_' . get_lang_code($_lang) . '.js"></script>';
	}
	
	// no title to save space for the game
	protected function show_title()
	{
	}
	
	protected function prepare()
	{
		$this->club_id = 0;
		$this->tournament_id = 0;
		$this->event_id = 0;
		$this->table = -1;
		$this->round = -1;
		$this->demo = false;
		
		if (isset($_REQUEST['event_id']))
		{
			$this->event_id = (int)$_REQUEST['event_id'];
			list ($this->club_id, $this->tournament_id) = Db::record(get_label('event'), 'SELECT club_id, tournament_id FROM events WHERE id = ?', $this->event_id);
			$this->club_id = (int)$this->club_id;
			$this->tournament_id = is_null($this->tournament_id) ? 0 : (int)$this->tournament_id;
			
			if (isset($_REQUEST['table']))
			{
				$this->table = (int)$_REQUEST['table'];
			}
			if (isset($_REQUEST['round']))
			{
				$this->round = (int)$_REQUEST['round'];
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
			list ($event_id, $table, $round, $game, $log) = Db::record('bug', 'SELECT event_id, table_num, round_num, game, log FROM bug_reports WHERE id = ?', $bug_id);
			
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
			$_SESSION['demo_game'] = $data;
			
			$this->table = $table;
			$this->round = $round;
			$this->demo = true;
		}
		else if (isset($_REQUEST['demo']))
		{
			$this->table = 0;
			$this->round = 0;
			$this->demo = true;
		}
	}
	
	private function select_event()
	{
		global $_lang, $_profile;
		
		$event_count = 2;
		$column_count = 2;
		
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
				' AND ( e.club_id IN (SELECT club_id FROM club_users WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?)' .
				' OR e.id IN (SELECT event_id FROM event_users WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?)' .
				' OR e.tournament_id IN (SELECT tournament_id FROM tournament_users WHERE (flags & ' . USER_PERM_REFEREE . ') <> 0 AND user_id = ?))', $_profile->user_id, $_profile->user_id, $_profile->user_id);
		}

		echo '<p><table class="transp" width="100%"><tr><td>';
		show_back_button();
		echo '</td><tr></table></p>';

		echo '<table class="bordered light" width="100%"><tr>';
		
		echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="center" class="light">';	
		echo '<p><a href="game1.php?demo=1">' . get_label('Demo game');
		echo '</p><p><img src="images/thegame.png" border="0"></p>';
		echo '</td>';
		
		if ($this->tournament_id > 0)
		{
			$create_event_func = 'mr.createRound(' . $this->tournament_id . ', true)';
			$create_event_title = get_label('Create [0]', get_label('tournament round'));
		}
		else 
		{
			$create_event_title = get_label('Create [0]', get_label('event'));
			if ($this->club_id > 0)
			{
				$create_event_func = 'mr.createEvent(' . $this->club_id . ', true)';
			}
			else
			{
				$create_event_func = 'mr.createEvent(undefined, true)';
			}
		}
		
		echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="center" class="light">';	
		echo '<p><a href="#" onclick="' . $create_event_func . '">' . $create_event_title;
		echo '</p><p><img src="images/create_big.png" border="0" width="48"></p>';
		echo '</td>';
		
		$club_pic = new Picture(CLUB_PICTURE);
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE));
		$query = new DbQuery(
			'SELECT c.id, c.name, c.flags, e.id, e.name, e.start_time, e.duration, e.flags, nct.name, ncr.name, ct.timezone, t.id, t.name, t.flags, a.id, a.flags, a.address, a.map_url, a.name' .
			' FROM events e' .
			' JOIN clubs c ON e.club_id = c.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' LEFT OUTER JOIN tournaments t ON e.tournament_id = t.id' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN countries cr ON ct.country_id = cr.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
			' JOIN names ncr ON ncr.id = cr.name_id AND (ncr.langs & '.$_lang.') <> 0 WHERE ',
			$condition);
		$query->add(' ORDER BY e.start_time, e.id');
		while ($row = $query->next())
		{
			list ($club_id, $club_name, $club_flags, $event_id, $event_name, $start_time, $duration, $event_flags, $city_name, $country_name, $event_timezone, $tour_id, $tour_name, $tour_flags, $addr_id, $addr_flags, $addr, $addr_url, $addr_name) = $row;
			if (!is_null($tour_name))
			{
				$event_name = $tour_name . ': ' . $event_name;
			}
			
			if ($column_count == 0)
			{
				if ($event_count == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			
			echo '<tr class="darker"><td align="center" width="32">';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false, 32);
			echo '</td><td align="center"><b>' . $event_name . '</b></td></tr><tr><td align="center" colspan="2"><p><a href="game1.php?bck=1&event_id=' . $event_id . '">';
			$event_pic->
				set($event_id, $event_name, $event_flags)->
				set($tour_id, $tour_name, $tour_flags);
			$event_pic->show(ICONS_DIR, false);
			echo '</a></p></td></tr></table>';
			
			echo '</td>';
			
			++$event_count;
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($event_count > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
	
	private function select_table()
	{
		list ($misc, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags) = 
			Db::record(get_label('event'), 
			'SELECT e.misc, e.id, e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags'.
			' FROM events e'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' JOIN clubs c ON c.id = e.club_id'.
			' WHERE e.id = ?', $this->event_id);
		if (!is_null($tournament_name))
		{
			$event_name = $tournament_name . ': ' . $event_name;
		}
		
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
		
		$pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		$pic->
			set($event_id, $event_name, $event_flags)->
			set($tournament_id, $tournament_name, $tournament_flags)->
			set($club_id, $club_name, $club_flags);
		
		$num_tables = -1;
		if (!is_null($misc))
		{
			$misc = json_decode($misc);
			if (isset($misc->seating))
			{
				$num_tables = count($misc->seating);
			}
		}
		
		if ($num_tables < 0)
		{
			list ($max_table_num1) = Db::record(get_label('game'), 'SELECT MAX(game_table) FROM games WHERE event_id = ?', $this->event_id);
			$max_table_num1 = is_null($max_table_num1) ? -1 : (int)$max_table_num1;
			list ($max_table_num2) = Db::record(get_label('game'), 'SELECT MAX(table_num) FROM current_games WHERE event_id = ?', $this->event_id);
			$max_table_num2 = is_null($max_table_num2) ? -1 : (int)$max_table_num2;
			$num_tables = max($max_table_num1, $max_table_num2) + 2;
		}
		
		$column_count = 0;
		
		echo '<p><table class="transp" width="100%"><tr><td width="60">';
		$pic->show(ICONS_DIR, false, 56);
		echo '</td><td><h2>' . $event_name . '</h2></td><td>';
		show_back_button();
		echo '</td><tr></table></p>';

		for ($i = 0; $i < $num_tables; ++$i)
		{
			if ($column_count == 0)
			{
				if ($i == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			
			echo '<tr class="darker"><td align="center"><p><b>' . get_label('Table [0]', $i + 1) . '</b></p></td></tr>';
			echo '<tr><td align="center" colspan="2"><p><a href="game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $i . '">';
			echo '<img src="images/game_table.png" width="70">';
			echo '</a></p></td></tr></table>';
			
			echo '</td>';
			
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($i > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
	
	private function select_round()
	{
		global $_lang, $_profile;
		
		list ($misc, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags) = 
			Db::record(get_label('event'), 
			'SELECT e.misc, e.id, e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags'.
			' FROM events e'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' JOIN clubs c ON c.id = e.club_id'.
			' WHERE e.id = ?', $this->event_id);
		if (!is_null($tournament_name))
		{
			$event_name = $tournament_name . ': ' . $event_name;
		}
		
		check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $event_id, $tournament_id);
		
		$pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		$pic->
			set($event_id, $event_name, $event_flags)->
			set($tournament_id, $tournament_name, $tournament_flags)->
			set($club_id, $club_name, $club_flags);
		
		$num_rounds = -1;
		if (!is_null($misc))
		{
			$misc = json_decode($misc);
			if (isset($misc->seating) && isset($misc->seating[$this->table]))
			{
				$num_rounds = count($misc->seating[$this->table]);
			}
		}
		
		if ($num_rounds > 0)
		{
			$rounds = array_fill(0, $num_rounds, NULL);
		}
		else
		{
			$rounds = array();
		}
		
		$query = new DbQuery(
			'SELECT g.round_num, g.user_id, n.name'.
			' FROM current_games g'.
			' JOIN users u ON u.id = g.user_id'.
			' JOIN names n ON n.id = u.name_id AND (n.langs & '.$_lang.') <> 0'.
			' WHERE event_id = ? AND table_num = ?', $this->event_id, $this->table);
		while ($row = $query->next())
		{
			list ($round, $user_id, $user_name) = $row;
			while ($round >= count($rounds))
			{
				$rounds[] = NULL;
			}
			$rounds[$round] = new stdClass();
			$rounds[$round]->user_id = (int)$user_id;
			$rounds[$round]->user_name = $user_name;
		}
		
		$query = new DbQuery('SELECT id, game_number, is_canceled, result FROM games WHERE event_id = ? AND game_table = ?', $this->event_id, $this->table);
		while ($row = $query->next())
		{
			$r = new stdClass();
			list ($r->game_id, $round, $r->is_canceled, $r->result) = $row;
			while ($round >= count($rounds))
			{
				$rounds[] = NULL;
			}
			$rounds[$round] = $r;
		}
		
		if ($num_rounds <= 0)
		{
			$rounds[] = NULL;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td width="60">';
		$pic->show(ICONS_DIR, false, 56);
		echo '</td><td><h2>' . $event_name . '</h2></td><td>';
		show_back_button();
		echo '</td><tr></table></p>';
		
		$column_count = 0;
		for ($i = 0; $i < count($rounds); ++$i)
		{
			if ($column_count == 0)
			{
				if ($i == 0)
				{
					echo '<table class="bordered light" width="100%">';
				}
				else
				{
					echo '</tr>';
				}
				echo '<tr>';
			}
			
			echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
			
			echo '<table class="transp" width="100%">';
			
			$r = $rounds[$i];
			if (is_null($r))
			{
				$darker_class = ' class="darker"';
				$normal_class = '';
				$text = '';
				$url = 'game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $this->table . '&round=' . $i;
				$onclick = '';
			}
			else if (isset($r->game_id))
			{
				$darker_class = ' class="darkest"';
				$normal_class = ' class="darker"';
				$url = 'view_game.php?bck=1&id=' . $rounds[$i]->game_id;
				if ($r->result <= 0)
				{
					$text = get_label('Playing using different method');
					$url = NULL;
				}
				else if ($r->is_canceled)
				{
					$text = get_label('Canceled');
				}
				else
				{
					$text = get_label('Complete');
				}
				$onclick = '';
			}
			else
			{
				$darker_class = ' class="darker"';
				$normal_class = ' class="dark"';
				if ($rounds[$i]->user_id == $_profile->user_id)
				{
					$text = get_label('Playing now', $rounds[$i]->user_name);
					$url = 'game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $this->table . '&round=' . $i;
					$onclick = '';
				}
				else
				{
					$text = get_label('Moderated by [0] now', $rounds[$i]->user_name);
					$url = '#';
					$onclick = ' onclick="mr.ownGame('.$this->event_id.','.$this->table.','.$i.','.$rounds[$i]->user_id.',\''.get_label('[0] is already moderating this game. Do you want to take is over from them?', $rounds[$i]->user_name).'\')"';
				}
			}
			
			echo '<tr' . $darker_class . '><td align="center"><p><b>' . get_label('Game [0]', $i + 1) . '</b></p></td></tr>';
			echo '<tr' . $normal_class . '><td align="center" colspan="2"><p>';
			if (!is_null($url))
			{
				echo '<a href="' . $url .'"' . $onclick . '>';
			}
			echo '<img src="images/thegame.png"><br>' . $text;
			echo '</a></p></td></tr></table>';
			
			echo '</td>';
			
			++$column_count;
			if ($column_count >= COLUMN_COUNT)
			{
				$column_count = 0;
			}
		}
		if ($i > 0)
		{
			if ($column_count > 0)
			{
				echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
			}
			echo '</tr></table>';
		}
	}
	
	private function game()
	{
		global $_profile;
		
		if ($this->event_id > 0)
		{
			list ($event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $club_prompt_sound_id, $club_end_sound_id) = 
				Db::record(get_label('event'), 
				'SELECT e.name, e.flags, t.id, t.name, t.flags, c.id, c.name, c.flags, c.prompt_sound_id, c.end_sound_id'.
				' FROM events e'.
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
				' JOIN clubs c ON c.id = e.club_id'.
				' WHERE e.id = ?', $this->event_id);
			check_permissions(PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, $club_id, $this->event_id, $tournament_id);
		}
		else
		{
			$event_name = get_label('Demo');
			$club_prompt_sound_id = GAME_DEFAULT_PROMPT_SOUND;
			$club_end_sound_id = GAME_DEFAULT_END_SOUND;
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
		
		echo '<ul id="ops-menu" style="position:absolute;" hidden>';
		echo '<li id="back" class="ops-item"><a href="#" onclick="goTo({round:undefined, demo:undefined})"><img src="images/prev.png" class="text"> '.get_label('Back').'</li>';
		echo '<li id="cancel" class="ops-item"><a href="#" onclick="uiCancelGame()"><img src="images/delete.png" class="text"> '.get_label('Cancel the game').'</li>';
		echo '<li type="separator"></li>';
		if ($this->event_id > 0)
		{
			echo '<li id="bug" class="ops-item"><a href="#" onclick="uiBugReport()"><img src="images/bug.png" class="text"> '.get_label('Report a bug').'</li>';
			echo '<li type="separator"></li>';
			// echo '<li id="voting" class="ops-item"><a href="#" onclick="gameToggleVoting()"><img src="images/vote.png" class="text"> <span id="voting-txt">'.get_label('Cancel voting').'</span></a></li>';
			// echo '<li type="separator"></li>';
			echo '<li id="obs" class="ops-item"><a href="#" onclick="mr.';
			if (is_null($tournament_id))
			{
				echo 'eventObs(' . $this->event_id;
			}
			else
			{
				echo 'tournamentObs(' . $tournament_id;
			}
			echo ', ' . $_profile->user_id . ')"><img src="images/obs.png" class="text"> '.get_label('OBS').'</a></li>';
		}
		echo '<li id="settings" class="ops-item"><a href="#" onclick="uiSettings()"><img src="images/settings.png" class="text"> '.get_label('Settings').'</a></li>';
		echo '</ul>';
		
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="day-empty header-row" align="center"><td id="head" colspan="6">';
		
		echo '<table class="transp" width="100%">';
		echo '<tr>';
		echo '<td width="64">';
		echo '<button id="ops" class="ops">';
		$pic->show(ICONS_DIR, false, 60);
		echo '</button>';
		echo '</td>';
		echo '<td id="status" align="center"></td>';
		
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
		echo '<td><img id="saving-img" border="0" src="images/connected.png"></td>';
		echo '<td id="saving"></td>';
		echo '<td align="right"><button id="game-id" class="config-btn" onclick="uiConfig()"><b>'.get_label('[0]: Table [1]. Game [2].', $event_name, $this->table + 1, $this->round + 1).'</b></button></td>';
		echo '</tr></table>';
		echo '</td>';
		echo '<td id="control-1"></td>';
		echo '<td id="noms" colspan="2"></td>';
		echo '</tr>';
		echo '</table>';
		
		echo '<div class="btn-panel"><table class="transp" width="100%"><tr>';
		echo '<td><button class="game-btn" id="game-back" onclick="uiBack()"><img src="images/prev.png" class="text" title="' . get_label('Back') . '"></button></td>';
		echo '<td id="info" align="center"></td>';
		echo '<td align="right"><button class="game-btn" id="game-next" onclick="uiNext()" title="' . get_label('Next') . '"><img src="images/next.png" class="text"></button></td>';
		echo '</tr></table></div>';
		
		$prompt_sound_id = is_null($club_prompt_sound_id) ? GAME_DEFAULT_PROMPT_SOUND : $club_prompt_sound_id;
		$end_sound_id = is_null($club_end_sound_id) ? GAME_DEFAULT_END_SOUND : $club_end_sound_id;
		$query = new DbQuery('SELECT prompt_sound_id, end_sound_id FROM game_settings WHERE user_id = ?', $_profile->user_id);
		if ($row = $query->next())
		{
			list($p_id, $e_id) = $row;
			if (!is_null($p_id))
			{
				$prompt_sound_id = $p_id;
			}
			if (!is_null($e_id))
			{
				$end_sound_id = $e_id;
			}
		}
		if ($prompt_sound_id == GAME_NO_SOUND)
		{
			echo '<audio id="prompt-snd"></audio>';
		}
		else
		{
			echo '<audio id="prompt-snd" src="sounds/' . $prompt_sound_id . '.mp3" preload="auto"></audio>';
		}
		if ($end_sound_id == GAME_NO_SOUND)
		{
			echo '<audio id="end-snd"></audio>';
		}
		else
		{
			echo '<audio id="end-snd" src="sounds/' . $end_sound_id . '.mp3" preload="auto"></audio>';
		}
	}
	
	protected function show_body()
	{
		if ($this->demo)
		{
			$this->game();
		}
		else if ($this->event_id <= 0)
		{
			$this->select_event();
		}
		else if ($this->table < 0)
		{
			$this->select_table();
		}
		else if ($this->round < 0)
		{
			$this->select_round();
		}
		else
		{
			$this->game();
		}
	}
	
	protected function js_on_load()
	{
		if ($this->event_id > 0 && $this->table >= 0 && $this->round >= 0)
		{
			echo 'uiStart(' . $this->event_id . ', ' . $this->table . ', ' . $this->round . ');';
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
