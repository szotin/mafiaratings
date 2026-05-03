<?php

require_once 'include/seating.php';
require_once 'include/player_stats.php';
require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);

define('VIEW_PAIRS', 0);
define('VIEW_SETUP', 1);
define('VIEW_BY_GAME', 2);
define('VIEW_BY_TABLE', 3);
define('VIEW_TABLE_STATS', 4);
define('VIEW_PVP_STATS', 5);
define('VIEW_NUMBERS_STATS', 6);
define('VIEW_COUNT', 7);

define('HIDE_PLAYED', 1);
define('SHOW_ICONS', 2);
define('ONLY_MY', 4);
define('ONLY_HIGHLIGHTED', 8);

class Page extends TournamentPageBase
{
	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		$this->user_id = 0;
		if ($_profile)
		{
			$this->user_id = $_profile->user_id;
		}
		
		$this->highlight_id = $this->user_id;
		if (isset($_REQUEST['hlt']))
		{
			$this->highlight_id = (int)$_REQUEST['hlt'];
		}
		
		$now = time();
		if (isset($_REQUEST['ops']))
		{
			$this->options = (int)$_REQUEST['ops'];
		}
		else if ($this->start_time <= $now && $this->start_time + $this->duration >= $now)
		{
			$this->options = HIDE_PLAYED | SHOW_ICONS;
		}
		else
		{
			$this->options = SHOW_ICONS;
		}

		$this->round_id = 0;
		if (isset($_REQUEST['round_id']))
		{
			$this->round_id = (int)$_REQUEST['round_id'];
		}
		
		$this->mwt_players = NULL;
		list ($tournament_misc) = Db::record(get_label('tournament'), 'SELECT misc FROM tournaments WHERE id = ?', $this->id);
		if (!is_null($tournament_misc))
		{
			$tournament_misc = json_decode($tournament_misc);
			if (isset($tournament_misc->mwt_players))
			{
				$this->mwt_players = $tournament_misc->mwt_players;
			}
		}
		
		$query = new DbQuery('SELECT id, round, misc FROM events WHERE tournament_id = ? ORDER BY round', $this->id);
		$tmp_rounds = array();
		$this->rounds = array();
		while ($row = $query->next())
		{
			if ($row[1] == 0)
			{
				$this->rounds[] = $row;
			}
			else
			{
				$tmp_rounds[] = $row;
			}
		}
		for ($i = count($tmp_rounds) - 1; $i >= 0; --$i)
		{
			$this->rounds[] = $tmp_rounds[$i];
		}
	}
	
	private function generateSeating()
	{
		if (!is_null($this->misc) && isset($this->misc->seating))
		{
			return true;
		}			
		
		if (($this->flags & TOURNAMENT_FLAG_FINISHED) == 0)
		{
			return false;
		}
		
		// generates seating using existing games if needed
		$old_misc = $this->misc;
		if ($this->misc == null)
		{
			$this->misc = new stdClass();
		}
		$rounds = array();

		$query = new DbQuery('SELECT p.user_id, p.number, g.table_num, g.game_num FROM players p JOIN games g ON g.id = p.game_id WHERE g.event_id = ? AND g.table_num IS NOT NULL AND g.game_num IS NOT NULL', $this->round_id);
		while ($row = $query->next())
		{
			list ($user_id, $number, $table, $game) = $row;
			while (count($rounds) < $game)
			{
				$rounds[] = array();
			}
			while (count($rounds[$game-1]) < $table)
			{
				$rounds[$game-1][] = array(0,0,0,0,0,0,0,0,0,0);
			}
			$rounds[$game-1][$table-1][$number-1] = (int)$user_id;
		}
		if (count($rounds) > 0)
		{
			$this->misc->seating = new stdClass();
			$this->misc->seating->rounds = $rounds;
		}
		if (isset($this->misc->seating))
		{
			// cashe it for the future
			Db::exec(get_label('round'), 'UPDATE events SET misc = ? WHERE id = ?', json_encode($this->misc), $this->round_id);
			return true;
		}
		$this->misc = $old_misc;
		return false;
	}
	
	private function initVars()
	{
		global $_lang;

		$this->players_pct = null;
		$this->numbers_pct = null;
		$this->tables_pct  = null;

		if (isset($this->misc->seating->hash))
		{
			$hash = $this->misc->seating->hash;
			$srow = (new DbQuery('SELECT players_score, numbers_score, tables_score FROM seatings WHERE hash = ?', $hash))->next();
			if ($srow)
			{
				list($ps, $ns, $ts) = $srow;
				$parts   = explode('_', $hash);
				$players = isset($parts[0]) ? (int)$parts[0] : 0;
				$tables  = isset($parts[1]) ? (int)$parts[1] : 0;
				$games   = isset($parts[2]) ? (int)$parts[2] : 0;
				$calc_pct = function($score, $max_score) {
					if ($max_score <= 0) return 100.0;
					return (1 - min(max($score / $max_score, 0), 1)) * 100;
				};
				$this->players_pct = ($players > 10)
					? $calc_pct($ps, SeatingDef::worst_acceptable_players_score($players, $tables, $games))
					: null;
				$this->numbers_pct = $calc_pct($ns, SeatingDef::worst_acceptable_numbers_score($players, $tables, $games));
				$this->tables_pct  = ($tables >= 3)
					? $calc_pct($ts, SeatingDef::worst_acceptable_tables_score($players, $tables, $games))
					: null;
			}
		}
		$this->tables = &$this->misc->seating->rounds;
		if (isset($this->misc->seating->mapping))
		{
			$this->mapping = &$this->misc->seating->mapping;
				for ($i = 0; $i < count($this->tables); ++$i)
				{
					$games = &$this->tables[$i];
					for ($j = 0; $j < count($games); ++$j)
					{
						$game = &$games[$j];
						for ($k = 0; $k < count($game); ++$k)
						{
							$index = $game[$k];
							if ($index >= count($this->mapping))
							{
								$game[$k] = -$index - 1;
							}
							else if ($index >= 0)
							{
								$player_id = $this->mapping[$index];
								if (is_object($player_id))
								{
									$player_id = isset($player_id->id) ? (int)$player_id->id : 0;
								}
								$game[$k] = ($player_id > 0) ? $player_id : -$index - 1;
							}
						}
					}
				}
			}

		if ($this->options & SHOW_ICONS)
		{
			$this->user_pic =
				new Picture(USER_TOURNAMENT_PICTURE,
				new Picture(USER_CLUB_PICTURE,
				new Picture(USER_PICTURE)));
		}
		$players_list = '';
		$this->users = array();
		$delim = '';
		for ($i = 0; $i < count($this->tables); ++$i)
		{
			for ($j = 0; $j < count($this->tables[$i]); ++$j)
			{
				if (is_null($this->tables[$i][$j]))
				{
					continue;
				}
				foreach ($this->tables[$i][$j] as $user_id)
				{
					if ($user_id > 0 && !isset($this->users[$user_id]))
					{
						$user = new stdClass();
						$user->id = $user_id;
						$user->name = '';
						$user->flags = 0;
						
						$this->users[$user_id] = $user;
						$players_list .= $delim . $user_id;
						$delim = ',';
					}
				}
			}
		}
			
		if (!empty($players_list))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, ni.name, tu.flags, cu.flags'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities i ON i.id = u.city_id'.
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
				' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = ?' .
				' WHERE u.id IN ('.$players_list.')', $this->id, $this->club_id);
			while ($row = $query->next())
			{
				$user = $this->users[$row[0]];
				list($user->id, $user->name, $user->flags, $user->city_name, $user->tournament_reg_flags, $user->club_reg_flags) = $row;
			}
		}
		
		$this->hideGames();
	}
	
	private function showOptLevelBar($percent)
	{
		$pct = round($percent);
		echo '<p><div style="display:flex;align-items:center;gap:8px;">';
		echo '<span style="white-space:nowrap;">' . get_label('Quality') . ':</span>';
		echo '<div style="position:relative;flex:1;height:24px;line-height:24px;overflow:hidden;">';
		if ($pct > 0)
		{
			echo '<img src="images/red_dot.png" style="position:absolute;left:0;top:0;width:' . $pct . '%;height:24px;opacity:0.6;">';
		}
		if ($pct < 100)
		{
			echo '<img src="images/black_dot.png" style="position:absolute;left:' . $pct . '%;top:0;width:' . (100 - $pct) . '%;height:24px;opacity:0.6;">';
		}
		echo '<b style="position:absolute;left:0;top:0;width:100%;text-align:center;color:white;">' . $pct . '%</b>';
		echo '</div></div></p>';
	}

	private function showSeatingTop()
	{
		echo '<p><input type="checkbox" id="hide_played"'.(($this->options & HIDE_PLAYED) ? ' checked' : '').' onclick="hidePlayed()"> '.get_label('show only non-played games');
		echo ' <input type="checkbox" id="show_icons"'.(($this->options & SHOW_ICONS) ? ' checked' : '').' onclick="showIcons()"> '.get_label('show user pictures');
		if ($this->user_id > 0)
		{
			echo ' <input type="checkbox" id="my_only"'.(($this->options & ONLY_MY) ? ' checked' : '').' onclick="onlyMy()"> '.get_label('show only my games');
		}
		if ($this->highlight_id > 0 && $this->highlight_id != $this->user_id)
		{
			echo ' <input type="checkbox" id="my_only"'.(($this->options & ONLY_HIGHLIGHTED) ? ' checked' : '').' onclick="onlyHighlighted()"> '.get_label('show only the games with the higlighted player');
		}
		echo '</p>';
	}
	
	protected function show_body()
	{
		$this->misc = NULL;
		$this->round_num = 0;
		echo '<div class="tab">';
		foreach ($this->rounds as $row)
		{
			list($event_id, $round_num, $misc) = $row;
			if ($this->round_id <= 0)
			{
				$this->round_id = $event_id;
			}
			
			$disabled = $this->is_manager ? '' : ' disabled';
			if (!is_null($misc))
			{
				$misc = json_decode($misc);
				if (isset($misc->mwt_schema))
				{
					$disabled = '';
				}
			}
			
			$active = '';
			if ($event_id == $this->round_id)
			{
				$this->misc = $misc;
				$this->round_num = $round_num;
				$active = ' class="active"';
			}
			
			echo '<button' . $active . ' onclick="goTo({round_id:' . $event_id . '})"' . $disabled . '>';
			echo get_round_name($round_num);
			echo '</button>';
		}
		echo '</div>';
		
		$view = VIEW_BY_GAME;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
			if ($view >= VIEW_COUNT || $view < 0)
			{
				$view = VIEW_BY_GAME;
			}
		}
		
		$seating_exists = $this->generateSeating();

		if (!$seating_exists)
		{
			echo '<p>' . get_label('Seating is not generated for this round') . '</p>';
			return;
		}

		if ($view == VIEW_PAIRS || $view == VIEW_SETUP)
		{
			$view = VIEW_BY_GAME;
		}

		echo '<p><div class="tab">';
		echo '<button' . ($view == VIEW_BY_GAME ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_BY_GAME.'})">' . get_label('By game') . '</button>';
		echo '<button' . ($view == VIEW_BY_TABLE ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_BY_TABLE.'})">' . get_label('By table') . '</button>';
		echo '<button' . ($view == VIEW_TABLE_STATS ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_TABLE_STATS.'})">' . get_label('By table stats') . '</button>';
		echo '<button' . ($view == VIEW_PVP_STATS ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_PVP_STATS.'})">' . get_label('PvP stats') . '</button>';
		echo '<button' . ($view == VIEW_NUMBERS_STATS ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_NUMBERS_STATS.'})">' . get_label('By numbers stats') . '</button>';
		echo '</div></p>';

		$this->mapping = null;
		switch ($view)
		{
		case VIEW_BY_GAME:
			$this->initVars();
			$this->showSeatingTop();
			$this->showByGame();
			break;
		case VIEW_BY_TABLE:
			$this->initVars();
			$this->showSeatingTop();
			$this->showByTable();
			break;
		case VIEW_TABLE_STATS:
			$this->initVars();
			$this->showTableStats();
			break;
		case VIEW_PVP_STATS:
			$this->initVars();
			$this->showPvpStats();
			break;
		case VIEW_NUMBERS_STATS:
			$this->initVars();
			$this->showNumbersStats();
			break;
		}
	}
	
	private function hideGames()
	{
		$normalize = false;
		
		if ($this->options & ONLY_HIGHLIGHTED)
		{
			for ($i = 0; $i < count($this->tables); ++$i)
			{
				$table = &$this->tables[$i];
				for ($j = 0; $j < count($table); ++$j)
				{
					$game = &$table[$j];
					if (is_null($game))
					{
						continue;
					}
					
					$found = false;
					for ($k = 0; $k < count($game); ++$k)
					{
						if ($game[$k] == $this->highlight_id)
						{
							$found = true;
							break;
						}
					}
					if (!$found)
					{
						$table[$j] = NULL;
						$normalize = true;
					}
				}
			}
		}
		
		if ($this->options & ONLY_MY)
		{
			for ($i = 0; $i < count($this->tables); ++$i)
			{
				$table = &$this->tables[$i];
				for ($j = 0; $j < count($table); ++$j)
				{
					$game = &$table[$j];
					if (is_null($game))
					{
						continue;
					}
					
					$found = false;
					for ($k = 0; $k < count($game); ++$k)
					{
						if ($game[$k] == $this->user_id)
						{
							$found = true;
							break;
						}
					}
					if (!$found)
					{
						$table[$j] = NULL;
						$normalize = true;
					}
				}
			}
		}
		
		if ($this->options & HIDE_PLAYED)
		{
			$query = new DbQuery('SELECT table_num, game_num FROM games WHERE event_id = ?', $this->round_id);
			while ($row = $query->next())
			{
				list($t, $g) = $row;
				if (
					!is_null($g) && $g > 0 && $g <= count($this->tables) &&
					$this->tables[$g-1] != NULL &&
					!is_null($t) && $t > 0 && $t <= count($this->tables[$g-1]))
				{
					$this->tables[$g-1][$t-1] = NULL;
					$normalize = true;
				}
			}
		}
		
		if ($normalize)
		{
			for ($i = 0; $i < count($this->tables); ++$i)
			{
				$table = &$this->tables[$i];
				$found = false;
				for ($j = 0; $j < count($table); ++$j)
				{
					if (!is_null($table[$j]))
					{
						$found = true;
					}
				}
				if (!$found)
				{
					$this->tables[$i] = NULL;
				}
			}
		}
	}
	
	private function getPlayerName($user_id)
	{
		if ($user_id > 0)
		{
			return $this->users[$user_id]->name;
		}
		
		if (!is_null($this->mwt_players))
		{
			foreach ($this->mwt_players as $p)
			{
				if ($p->id == $user_id)
				{
					return $p->name;
				}
			}
		}
		
		if ($user_id < 0)
		{
			$user_id = -$user_id - 1;
			if (!is_null($this->mapping) && $user_id < count($this->mapping) && isset($this->mapping[$user_id]->name))
			{
				return $this->mapping[$user_id]->name;
			}
		}
		return '#' . ($user_id + 1);
	}
	
	private function showPlayer($user_id, $cell_attributes = '')
	{
		$class = '';
		if ($this->highlight_id == $user_id)
		{
			$class = ' class="darker"';
			if ($this->user_id == $user_id)
			{
				$ref_beg = '';
				$ref_end = '';
			}
			else
			{
				$ref_beg = '<a href="javascript:highlight()">';
				$ref_end = '</a>';
			}
		}
		else
		{
			$ref_beg = '<a href="javascript:highlight('.$user_id.')">';
			$ref_end = '</a>';
		}
		
		if (empty($cell_attributes))
		{
			$cell_attributes = $this->highlight_id == $user_id ? ' class="darker"' : '';
		}
		echo '<td align="center"' . $cell_attributes . '>';
		if ($user_id > 0)
		{
			$user = $this->users[$user_id];
			echo '<table class="transp" width="100%">';
			if ($this->options & SHOW_ICONS)
			{
				echo '<tr><td align="center">' . $ref_beg;
				$this->user_pic->
					set($user->id, $user->name, $user->tournament_reg_flags, 't' . $this->id)->
					set($user->id, $user->name, $user->club_reg_flags, 'c' . $this->club_id)->
					set($user->id, $user->name, $user->flags);
				$this->user_pic->show(ICONS_DIR, false, 48);
				echo $ref_end.'</td></tr>';
			}
			echo '<tr><td align="center" style="height:30px">' . $ref_beg . $user->name;
			echo $ref_end . '</td></tr></table>';
		}
		else if (!is_null($this->mwt_players))
		{
			foreach ($this->mwt_players as $p)
			{
				if ($p->id == $user_id)
				{
					echo $ref_beg . $p->name . $ref_end;
					break;
				}
			}
		}
		else if ($user_id < 0)
		{
			$index = -$user_id - 1;
			echo $ref_beg;
			if (!is_null($this->mapping) && $index < count($this->mapping) && isset($this->mapping[$index]->name))
			{
				echo $this->mapping[$index]->name;
			}
			else
			{
				echo '#' . ($index + 1);
			}
			echo $ref_end;
		}
		echo '</td>';
	}
	
	private function showByTable()
	{
		$num_rounds = count($this->tables);
		$num_tables = $num_rounds > 0 ? count($this->tables[0]) : 0;
		for ($i = 0; $i < $num_tables; ++$i)
		{
			$has_data = false;
			for ($j = 0; $j < $num_rounds; ++$j)
			{
				if (!is_null($this->tables[$j]) && !is_null($this->tables[$j][$i]))
				{
					$has_data = true;
					break;
				}
			}
			if (!$has_data)
			{
				continue;
			}
			echo '<p><center><h2>' . get_label('Table [0]', $i + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; ++$k)
			{
				echo '<td width="9.2%" align="center"><b>'.($k+1).'</b></td>';
			}
			echo '</tr>';
			for ($j = 0; $j < $num_rounds; ++$j)
			{
				if (is_null($this->tables[$j]))
				{
					continue;
				}
				$game = $this->tables[$j][$i];
				if (is_null($game) || count($game) < 10)
				{
					continue;
				}
				echo '<tr><td align="center" class="dark"><b>' . get_label('Game [0]', $j+1) . '</b></td>';
				for ($k = 0; $k < count($game) && $k < 10; ++$k)
				{
					$this->showPlayer($game[$k]);
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}
	
	private function showTableStats()
	{
		if (!is_null($this->tables_pct)) $this->showOptLevelBar($this->tables_pct);
		$num_rounds = count($this->tables);
		$num_tables = $num_rounds > 0 ? count($this->tables[0]) : 0;
		$pl = array();
		for ($i = 0; $i < $num_rounds; ++$i)
		{
			$round = &$this->tables[$i];
			if (is_null($round)) { continue; }
			for ($j = 0; $j < count($round); ++$j)
			{
				if (is_null($round[$j])) { continue; }
				for ($k = 0; $k < count($round[$j]) && $k < 10; ++$k)
				{
					$user_id = $round[$j][$k];
					if (!array_key_exists($user_id, $pl))
					{
						$player = new stdClass();
						$player->id = $user_id;
						$player->name = $this->getPlayerName($user_id);
						$player->tables = array_fill(0, $num_tables, 0);
						$pl[$user_id] = $player;
					}
					++$pl[$user_id]->tables[$j];
				}
			}
		}

		$players = array();
		foreach ($pl as $user_id => $player)
		{
			$players[] = $player;
		}
		usort($players, function($p1, $p2) { return strcmp($p1->name, $p2->name); });

		echo '<table class="bordered light">';
		echo '<tr class="darker"><td width="120"></td>';
		for ($i = 0; $i < $num_tables; ++$i)
		{
			echo '<td width="80" align="center"><b>'.get_label('Table [0]', $i + 1).'</b></td>';
		}
		echo '</tr>';
		
		foreach ($players as $player)
		{
			if ($player->id == $this->highlight_id)
			{
				echo '<tr class="dark">';
				$cell_attributes = ' class="darker"';
			}
			else
			{
				echo '<tr>';
				$cell_attributes = ' class="dark"';
			}
			$this->showPlayer($player->id, $cell_attributes);
			foreach ($player->tables as $count)
			{
				echo '<td align="center">' . $count . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	private function showPvpStats()
	{
		if (!is_null($this->players_pct)) $this->showOptLevelBar($this->players_pct);
		$highlight_id = -1;
		$pl = array();
		for ($i = 0; $i < count($this->tables); ++$i)
		{
			$table = &$this->tables[$i];
			for ($j = 0; $j < count($table); ++$j)
			{
				for ($k = 0; $k < count($table[$j]) && $k < 10; ++$k)
				{
					$user_id = $table[$j][$k];
					if ($user_id == 0) { continue; }
					if (!array_key_exists($user_id, $pl))
					{
						$player = new stdClass();
						$player->id = $user_id;
						$player->name = $this->getPlayerName($user_id);
						$player->players = array();
						$pl[$user_id] = $player;
					}
				}
			}
		}
		
		foreach ($pl as $user1_id => $player1)
		{
			foreach ($pl as $user2_id => $player2)
			{
				if ($user1_id != $user2_id)
				{
					$player1->players[$user2_id] = 0;
				}
			}
		}
		
		
		for ($i = 0; $i < count($this->tables); ++$i)
		{
			$table = &$this->tables[$i];
			for ($j = 0; $j < count($table); ++$j)
			{
				for ($k = 0; $k < count($table[$j]) && $k < 10; ++$k)
				{
					$user1_id = $table[$j][$k];
					if ($user1_id == 0) { continue; }
					if ($this->highlight_id == $user1_id)
					{
						$highlight_id = $this->highlight_id;
					}
					for ($l = 0; $l < count($table[$j]) && $l < 10; ++$l)
					{
						if ($k != $l)
						{
							$user2_id = $table[$j][$l];
							if ($user2_id == 0) { continue; }
							++$pl[$user2_id]->players[$user1_id];
							++$pl[$user1_id]->players[$user2_id];
						}
					}
				}
			}
		}
		
		$players = array();
		foreach ($pl as $user_id => $player)
		{
			$players[] = $player;
		}
		usort($players, function($p1, $p2) { return strcmp($p1->name, $p2->name); });

		echo '<p><select id="player" onchange="highlight($(\'#player\').val())">';
		show_option(0, $highlight_id, '');
		foreach ($players as $player)
		{
			show_option($player->id, $highlight_id, $player->name);
		}
		echo '</select></p>';
		
		if ($highlight_id > 0)
		{
			$player = $pl[$highlight_id];
			$playing_with = array();
			foreach ($player->players as $pid => $p)
			{
				$index = $p / 2;
				for ($i = count($playing_with); $i <= $index; ++$i)
				{
					$playing_with[] = array();
				}
				$playing_with[$index][] = $pid;
			}
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><th width="80">'.get_label('Games together').'</th><th></th></tr>';
			for ($i = count($playing_with) - 1; $i >= 0; --$i)
			{
				if (count($playing_with[$i]) == 0)
				{
					continue;
				}
				echo '<tr><td align="center" class="dark"><b>'.$i.'</b></td><td>';
				$count = 0;
				foreach ($playing_with[$i] as $user_id)
				{
					if ($count == 0)
					{
						echo '<table class="transp"><tr>';
					}
					else if ($count % 14 == 0)
					{
						echo '</tr><tr>';
					}
					$this->showPlayer($user_id, 'width="60"');
					++$count;
				}
				if ($count > 0)
				{
					$cols = 14 - $count % 14;
					if ($cols < 14)
					{
						echo '<td colspan="'.$cols.'"></td>';
					}
					echo '</tr></table>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
		else
		{
			$pairs = array();
			$max = 1;
			$sum = 0;
			$min_index = 10000;
			foreach ($players as $player1)
			{
				foreach ($players as $player2)
				{
					if ($player1->id > $player2->id)
					{
						$index = $player1->players[$player2->id] / 2;
						for ($i = count($pairs); $i <= $index; ++$i)
						{
							$pairs[] = 0;
						}
						$max = max(++$pairs[$index], $max);
						$min_index = min($index, $min_index);
						++$sum;
					}
				}
			}
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker"><th width="80">'.get_label('Games together').'</th><th width="80">'.get_label('Pairs').'</th><th></th></tr>';
			for ($i = count($pairs) - 1; $i >= $min_index; --$i)
			{
				echo '<tr align="center"><td class="dark"><b>' . $i . '</b></td><td>' . $pairs[$i] . '</td>';
				echo '<td align="left"><img src="images/black_dot.png" width="' . round((760 * $pairs[$i]) / $max) . '" height="12" title="' . $pairs[$i] . ' ('.format_float(100 * $pairs[$i] / $sum, 1).'%)" style="opacity: 0.3;"></td>';
				echo '</tr>';
			}
			echo '</table>';
		}
	}
	
	private function showNumbersStats()
	{
		if (!is_null($this->numbers_pct)) $this->showOptLevelBar($this->numbers_pct);
		$pl = array();
		for ($i = 0; $i < count($this->tables); ++$i)
		{
			$table = &$this->tables[$i];
			for ($j = 0; $j < count($table); ++$j)
			{
				for ($k = 0; $k < count($table[$j]) && $k < 10; ++$k)
				{
					$user_id = $table[$j][$k];
					if (!array_key_exists($user_id, $pl))
					{
						$player = new stdClass();
						$player->id = $user_id;
						$player->name = $this->getPlayerName($user_id);
						$player->numbers = array_fill(0, 10, 0);
						$pl[$user_id] = $player;
					}
					++$pl[$user_id]->numbers[$k];
				}
			}
		}
		
		$players = array();
		foreach ($pl as $user_id => $player)
		{
			$players[] = $player;
		}
		usort($players, function($p1, $p2) { return strcmp($p1->name, $p2->name); });
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="120"></td>';
		for ($i = 0; $i < 10; ++$i)
		{
			echo '<td width="80" align="center"><b>'.($i + 1).'</b></td>';
		}
		echo '</tr>';
		
		foreach ($players as $player)
		{
			if ($player->id == $this->highlight_id)
			{
				echo '<tr class="dark">';
				$cell_attributes = ' class="darker"';
			}
			else
			{
				echo '<tr>';
				$cell_attributes = ' class="dark"';
			}
			$this->showPlayer($player->id, $cell_attributes);
			for ($i = 0; $i < 10; ++$i)
			{
				echo '<td align="center">' . $player->numbers[$i] . '</td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
	
	private function showPairs()
	{
		global $_lang;

		$pairs = get_tournament_pairs($this->id, $this->club_id, $_lang);
		$user_pic = new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE)));

		echo '<p><table class="bordered light" width="100%"><tr class="darker"><th width="32"><button class="icon" onclick="createPair()" title="' . get_label('Create new pair') . '"><img src="images/create.png"></button></th>';
		echo '<th width="200">' . get_label('Player [0]', 1) . '</th>';
		echo '<th width="200">' . get_label('Player [0]', 2) . '</th>';
		echo '<th>' . get_label('Policy') . '</th>';
		echo '<th width="200">' . get_label('Where the policy is set') . '</th></tr>';
		foreach ($pairs as $pair)
		{
			echo '<tr>';
			echo '<td><button class="icon" onclick="deletePair(' . $pair->user1_id . ',' . $pair->user2_id . ')" title="' . get_label('Delete pair') . '"><img src="images/delete.png"></button></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->
				set($pair->user1_id, $pair->user1_name, $pair->user1_tournament_flags, 't' . $this->id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_club_flags, 'c' . $this->club_id)->
				set($pair->user1_id, $pair->user1_name, $pair->user1_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . $pair->user1_id . '&bck=1">' . $pair->user1_name . '</a></td></tr></table></td>';

			echo '<td><table class="transp" width="100%"><tr><td width="52">';
			$user_pic->
				set($pair->user2_id, $pair->user2_name, $pair->user2_tournament_flags, 't' . $this->id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_club_flags, 'c' . $this->club_id)->
				set($pair->user2_id, $pair->user2_name, $pair->user2_flags);
			$user_pic->show(ICONS_DIR, false, 48);
			echo '</td><td><a href="user_info.php?id=' . $pair->user2_id . '&bck=1">' . $pair->user2_name . '</a></td></tr></table></td>';

			echo '<td align="center">' . get_pair_policy_name($pair->policy) . '</td>';
			echo '<td align="center">' . $pair->source . '</td>';
			echo '</tr>';
		}
		echo '</table></p>';
	}
	
	private function showSetup()
	{
		global $_lang;
		
		if (!$this->is_manager)
		{
			return;
		}
	
		if ($this->round_id <= 0)
		{
			list ($tournament_type) = Db::record(get_label('tournament'), 'SELECT type FROM tournaments WHERE id = ?', $this->id);
			echo '<p>' . get_label('This tournament has no rounds. Please create rounds to apply seating. The easiest way of doing it is to change tournament type here.') . '</p><p>';
			show_tournament_type_select($tournament_type, 'tournament-type');
			echo '</p><p><button onclick="changeTournamentType()">' . get_label('Change') . '</button></p>';
			return;
		}
		
		echo '<p><table class="transp" width="100%"><tr><td><input type="file" id="upload" style="display:none" onchange="doUploadDimTom()">';
		$url = 'https://dimtom.github.io/web_schedule';
		echo get_label('Import seating from [0]', '<a href="' . $url . '" target="_blank">' . $url . '</a>');  
		echo ': <button class="upload" onclick="uploadDimTom();">' . get_label('Upload seating file') . '</button></td>';
		if (!is_null($this->mapping))
		{
			$buttons = 0;
			foreach ($this->mapping as $p)
			{
				if (is_object($p))
				{
					if (isset($p->id))
					{
						$buttons |= 1;
					}
					else
					{
						$buttons |= 2;
					}
				}
				else if ($p > 0)
				{
					$buttons |= 1;
				}
				else
				{
					$buttons |= 2;
				}
				if ($buttons == 3)
				{
					break;
				}
			}
			
			if ($buttons > 0)
			{
				echo '<td align="right">';
				if ($buttons & 1)
				{
					echo '<button onclick="clearMappings()">' . get_label('Clear mappings') . '</button>';
				}
				if ($buttons & 2)
				{
					echo ' <button onclick="fillMappings()">' . get_label('Fill mappings') . '</button>';
				}
			}
		}
		echo '</tr></table></p>';
		
		if (!is_null($this->mapping))
		{
			$players_list = '';
			$delim = '';
			foreach ($this->mapping as $player)
			{
				$id = 0;
				if (!is_object($player))
				{
					$id = $player;
				}
				else if (isset($player->id))
				{
					$id = $player->id;
				}

				if ($id !== 0)
				{
					$players_list .= $delim . $id;
					$delim = ',';
				}
			}
			
			$users = array();
			if (!empty($players_list))
			{
				$query = new DbQuery(
					'SELECT u.id, nu.name, u.flags, ni.name, tu.flags, cu.flags'.
					' FROM users u'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' JOIN cities i ON i.id = u.city_id'.
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN tournament_regs tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
					' LEFT OUTER JOIN club_regs cu ON cu.user_id = u.id AND cu.club_id = ?' .
					' WHERE u.id IN ('.$players_list.')', $this->id, $this->club_id);
				while ($row = $query->next())
				{
					$user = new stdClass();
					list($user->id, $user->name, $user->flags, $user->city_name, $user->tournament_reg_flags, $user->club_reg_flags) = $row;
					$users[$user->id] = $user;
				}
			}
			
			$number = 0;
			echo '<p><h3>' . get_label('Players') . '</h3>';
			echo '<table class="bordered light" width="100%">';
			foreach ($this->mapping as $player)
			{
				$player_name = '';
				if (is_object($player) && isset($player->name))
				{
					$player_name = $player->name;
				}
				
				echo '<tr><td width="40" align="center">' . ($number + 1) . '</td><td width="420">';
				echo '<table class="transp" width="100%"><tr><td width="32"><button class="icon" onclick="createPlayer(' . $number . ', \'' . $player_name . '\')" title="' . get_label('Create new user') . '"><img src="images/create.png"></button></td>';
				echo '<td>' . $player_name . '</td></tr></table>';
				echo '</td>';
				
				echo '<td width="40" align="center"><button class="big_icon" onclick="mapPlayer(' . $number . ')"><img src="images/right.png" width="32"></button>';
				if (isset($player->id) && $player->id > 0 && array_key_exists($player->id, $users))
				{
					$user = $users[$player->id];
					
					echo '<td><table class="transp" width="100%"><tr>';
					echo '<td width="52">';
					$this->user_pic->
						set($user->id, $user->name, $user->tournament_reg_flags, 't' . $this->id)->
						set($user->id, $user->name, $user->club_reg_flags, 'c' . $this->club_id)->
						set($user->id, $user->name, $user->flags);
					$this->user_pic->show(ICONS_DIR, false, 48);
					echo '</td><td><a href="user_info.php?id='.$player->id.'&bck=1">' . $user->name . '</a></td></tr></table></td>';
				}
				else
				{
					echo '<td></td>';
				}
				echo '</td></tr>';
				++$number;
			}
			echo '</table></p>';
		}
	}
	
	private function showByGame()
	{
		$by_game = array();
		for ($i = 0; $i < count($this->tables); ++$i)
		{
			$round = &$this->tables[$i];
			if (is_null($round))
			{
				continue;
			}
			for ($j = 0; $j < count($round); ++$j)
			{
				while ($i >= count($by_game))
				{
					$by_game[] = array();
				}
				while ($j >= count($by_game[$i]))
				{
					$by_game[$i][] = NULL;
				}
				$game = $round[$j];
				if (!is_null($game) && count($game) >= 10)
				{
					$by_game[$i][$j] = $round[$j];
				}
			}
		}
		
		for ($i = 0; $i < count($by_game); ++$i)
		{
			$game = $by_game[$i];
			$found = false;
			for ($j = 0; $j < count($game); ++$j)
			{
				if (!is_null($game[$j]))
				{
					$found = true;
					break;
				}
			}
			if (!$found)
			{
				continue;
			}
			
			echo '<p><center><h2>' . get_label('Game [0]', $i + 1) . '</h2></center></p>';
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="dark"><td width="8%"></td>';
			for ($k = 0; $k < 10; ++$k)
			{
				echo '<td width="9.2%" align="center"><b>'.($k+1).'</b></td>';
			}
			echo '</tr>';
			$all_games = true;
			for ($j = 0; $j < count($game); ++$j)
			{
				$table = $game[$j];
				if (is_null($table))
				{
					$all_games = false;
					continue;
				}
				echo '<tr><td align="center" class="dark"><b>' . get_label('Table [0]', $j + 1) . '</b></td>';
				for ($k = 0; $k < count($table) && $k < 10; ++$k)
				{
					$this->showPlayer($table[$k]);
				}
				echo '</tr>';
			}
			if ($all_games)
			{
				foreach ($this->users as $user_id => $user)
				{
					$user->skipping = true;
				}
				foreach ($game as $table)
				{
					foreach ($table as $user_id)
					{
						if ($user_id > 0)
						{
							$user = $this->users[$user_id];
							$user->skipping = false;
						}
					}
				}
				
				$skipping_count = 0;
				foreach ($this->users as $user_id => $user)
				{
					if ($user->skipping)
					{
						++$skipping_count;
					}
				}
				
				if ($skipping_count > 0)
				{
					$rows = 1 + ($skipping_count - 1) / 10;
					echo '<tr class="dark"><td align="center" class="dark"';
					if ($rows > 1)
					{
						echo ' rowspan="' . $rows . '"';
					}
					echo '><b>' . get_label('Skipping') . '</b></td>';
					$col_count = 0;
					foreach ($this->users as $user_id => $user)
					{
						if (!$user->skipping)
						{
							continue;
						}
						
						if ($col_count == 10)
						{
							$col_count = 0;
							echo '</tr><tr class="dark">';
						}
						
						$this->showPlayer($user_id);
						++$col_count;
					}
					echo '</tr>';
				}
			}
			echo '</table>';
		}
	}
	
	function changeOp($op)
	{
		if ($this->options & $op)
		{
			return $this->options & ~$op;
		}
		return $this->options | $op;
	}
	
	protected function js()
	{
?>
		function hidePlayed()
		{
			goTo({ops: <?php echo $this->changeOp(HIDE_PLAYED); ?>});
		}
		
		function showIcons()
		{
			goTo({ops: <?php echo $this->changeOp(SHOW_ICONS); ?>});
		}
		
		function onlyMy()
		{
			goTo({ops: <?php echo $this->changeOp(ONLY_MY); ?>});
		}
		
		function onlyHighlighted()
		{
			goTo({ops: <?php echo $this->changeOp(ONLY_HIGHLIGHTED); ?>});
		}
		
		function highlight(userId)
		{
			goTo({hlt: userId});
		}
		
		function uploadDimTom()
		{
<?php
			if (!is_null($this->misc) && isset($this->misc->seating))
			{
?>
				dlg.yesNo("<?php echo get_label('This round already has seating. Do you want to replace it?'); ?>", null, null, function() { $('#upload').trigger('click'); });
<?php
			}
			else
			{
?>
				$('#upload').trigger('click');
<?php
			}
?>
		}
		
		function doUploadDimTom()
		{
			console.log(document.getElementById("upload").files[0]);
			json.upload('api/ops/event.php', 
			{
				op: "import_dimtom"
				, event_id: <?php echo $this->round_id; ?>
				, file: document.getElementById("upload").files[0]
			}, 
			2097152, 
			refr);
		}
		
		function mapPlayer(number)
		{
			dlg.form("form/map_player.php?number=" + number + "&event_id=<?php echo $this->round_id; ?>", refr, 480);
		}
		
		function createPlayer(number, name)
		{
			dlg.form("form/create_user.php?name=" + name + "&club_id=<?php echo $this->club_id; ?>", 
				function(data)
				{
					json.post("api/ops/event.php",
					{
						op: "map_player"
						, event_id: <?php echo $this->round_id; ?>
						, user_id: data.id
						, number: number
					}, refr);
					
				}, 480);
		}
		
		function createPair()
		{
			dlg.form("form/pair_create.php?tournament_id=<?php echo $this->id; ?>", refr, 600);
		}

		function deletePair(user1Id, user2Id)
		{
			var html = '<p><?php echo get_label('Are you sure you want to delete this pair?'); ?></p>';
			html += '<p><input type="checkbox" id="delete-pair-tournament-only"> <?php echo get_label('for this tournament only'); ?></p>';
			dlg.custom(html, "<?php echo get_label('Confirmation'); ?>", null,
			{
				yes: { id: "dlg-yes", text: "<?php echo get_label('Yes'); ?>", click: function()
				{
					var global = $('#delete-pair-tournament-only').is(':checked') ? 0 : 1;
					$(this).dialog('close');
					json.post("api/ops/seating.php",
					{
						op: "delete_pair"
						, tournament_id: <?php echo $this->id; ?>
						, user1_id: user1Id
						, user2_id: user2Id
						, global: global
					}, refr);
				}},
				no: { id: "dlg-no", text: "<?php echo get_label('No'); ?>", click: function() { $(this).dialog('close'); } }
			});
		}
		
		function clearMappings()
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to clear player mappings?'); ?>", null, null, function() {
				json.post("api/ops/event.php",
				{
					op: "clear_mappings"
					, event_id: <?php echo $this->round_id; ?>
				}, refr)});
		}

		function fillMappings()
		{
			json.post("api/ops/event.php",
			{
				op: "fill_mappings"
				, event_id: <?php echo $this->round_id; ?>
			}, refr);
		}
		
		function changeTournamentType()
		{
			json.post("api/ops/tournament.php", 			
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				type: $('#tournament-type').val(),
			}, refr);
			
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Seating'));

?>