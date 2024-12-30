<?php

require_once 'include/page_base.php';

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
		global $_lang;
		
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
		
		$query = new DbQuery('SELECT id, game_number FROM games WHERE event_id = ? AND game_table = ?  AND is_canceled = FALSE AND result > 0', $this->event_id, $this->table);
		while ($row = $query->next())
		{
			list ($game_id, $round) = $row;
			while ($round >= count($rounds))
			{
				$rounds[] = NULL;
			}
			$rounds[$round] = new stdClass();
			$rounds[$round]->game_id = (int)$game_id;
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
			
			if (is_null($rounds[$i]))
			{
				$darker_class = ' class="darker"';
				$normal_class = '';
				$text = '';
				$url = 'game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $this->table . '&round=' . $i;
				$onclick = '';
			}
			else if (isset($rounds[$i]->game_id))
			{
				$darker_class = ' class="darkest"';
				$normal_class = ' class="darker"';
				$text = get_label('Complete');
				$url = 'view_game.php?bck=1&id=' . $rounds[$i]->game_id;
				$onclick = '';
			}
			else
			{
				$darker_class = ' class="darker"';
				$normal_class = ' class="dark"';
				$text = get_label('Playing now');
				if ($rounds[$i]->game_id == $_profile->user_id)
				{
					$url = 'game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $this->table . '&round=' . $i;
					$onclick = '';
				}
				else
				{
					$url = 'game1.php?bck=1&event_id=' . $this->event_id . '&table=' . $this->table . '&round=' . $i;
					$onclick = ' onclick="mr.ownGame('.$this->event_id.','.$this->table.','.$this->round.','.$rounds[$i]->user_id.',\''.get_label('[0] is already moderating this game. Do you want to take is over from them?', $rounds[$i]->user_name).'\')"';
				}
			}
			
			echo '<tr' . $darker_class . '><td align="center"><p><b>' . get_label('Game [0]', $i + 1) . '</b></p></td></tr>';
			echo '<tr' . $normal_class . '><td align="center" colspan="2"><p><a href="' . $url .'">';
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
		echo '<div id="game-area" tabindex="0"></div>';
	}
	
	protected function show_body()
	{
		if ($this->event_id <= 0)
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
			echo 'mafia.ui.start(' . $this->event_id . ', ' . $this->table . ', ' . $this->round . ');';
		}
	}
}

$page = new Page();
$page->run(get_label('The game'));

?>
