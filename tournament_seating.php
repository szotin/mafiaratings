<?php

require_once 'include/player_stats.php';
require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', EVENTS_PAGE_SIZE);

define('VIEW_BY_GAME', 0);
define('VIEW_BY_TABLE', 1);
define('VIEW_COUNT', 2);

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
	
	protected function show_body()
	{
		global $_lang;
		
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
			
			$disabled = ' disabled';
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
				$view = 0;
			}
		}
		if (!is_null($this->misc) && isset($this->misc->seating))
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
			
			echo '<p><div class="tab">';
			echo '<button' . ($view == VIEW_BY_GAME ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_BY_GAME.'})">' . get_label('By game') . '</button>';
			echo '<button' . ($view == VIEW_BY_TABLE ? ' class="active"' : '') . ' onclick="goTo({view:'.VIEW_BY_TABLE.'})">' . get_label('By table') . '</button>';
			echo '</div></p>';

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
			if (isset($this->misc->seating))
			{
				foreach ($this->misc->seating as $table)
				{
					foreach ($table as $game)
					{
						if (is_null($game))
						{
							continue;
						}
						foreach ($game as $user_id)
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
			}
				
			if (!empty($players_list))
			{
				$query = new DbQuery(
					'SELECT u.id, nu.name, u.flags, ni.name, tu.flags, cu.flags'.
					' FROM users u'.
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' JOIN cities i ON i.id = u.city_id'.
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
					' WHERE u.id IN ('.$players_list.')', $this->id, $this->club_id);
				while ($row = $query->next())
				{
					$user = $this->users[$row[0]];
					list($user->id, $user->name, $user->flags, $user->city_name, $user->tournament_user_flags, $user->club_user_flags) = $row;
				}
			}
			
			$this->hide_games();
			
			switch ($view)
			{
				case VIEW_BY_GAME:
					$this->showByGame();
					break;
				case VIEW_BY_TABLE:
					$this->showByTable();
					break;
			}
		}
	}
	
	private function hide_games()
	{
		$normalize = false;
		
		if ($this->options & ONLY_HIGHLIGHTED)
		{
			for ($i = 0; $i < count($this->misc->seating); ++$i)
			{
				$table = $this->misc->seating[$i];
				for ($j = 0; $j < count($table); ++$j)
				{
					$game = $table[$j];
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
						$this->misc->seating[$i][$j] = NULL;
						$normalize = true;
					}
				}
			}
		}
		
		if ($this->options & ONLY_MY)
		{
			for ($i = 0; $i < count($this->misc->seating); ++$i)
			{
				$table = $this->misc->seating[$i];
				for ($j = 0; $j < count($table); ++$j)
				{
					$game = $table[$j];
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
						$this->misc->seating[$i][$j] = NULL;
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
					!is_null($t) && $t > 0 && $t <= count($this->misc->seating) && 
					$this->misc->seating[$t] != NULL &&
					!is_null($g) && $g > 0 && $g <= count($this->misc->seating[$t-1]))
				{
					$this->misc->seating[$t-1][$g-1] = NULL;
					$normalize = true;
				}
			}
		}
		
		if ($normalize)
		{
			for ($i = 0; $i < count($this->misc->seating); ++$i)
			{
				$table = $this->misc->seating[$i];
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
					$this->misc->seating[$i] = NULL;
				}
			}
		}
	}
	
	private function showPlayer($user_id)
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
		echo '<td align="center" ' . ($this->highlight_id == $user_id ? ' class="darker"' : '') . '>';
		if ($user_id > 0)
		{
			$user = $this->users[$user_id];
			echo '<table class="transp" width="100%">';
			if ($this->options & SHOW_ICONS)
			{
				echo '<tr><td align="center">' . $ref_beg;
				$this->user_pic->
					set($user->id, $user->name, $user->tournament_user_flags, 't' . $this->id)->
					set($user->id, $user->name, $user->club_user_flags, 'c' . $this->club_id)->
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
		echo '</td>';
	}
	
	private function showByTable()
	{
		for ($i = 0; $i < count($this->misc->seating); ++$i)
		{
			$table = $this->misc->seating[$i];
			if (is_null($table))
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
			for ($j = 0; $j < count($table); ++$j)
			{
				$game = $table[$j];
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
	
	private function showByGame()
	{
		$by_game = array();
		for ($i = 0; $i < count($this->misc->seating); ++$i)
		{
			$table = $this->misc->seating[$i];
			if (is_null($table))
			{
				continue;
			}
			for ($j = 0; $j < count($table); ++$j)
			{
				while ($j >= count($by_game))
				{
					$by_game[] = array();
				}
				while ($i >= count($by_game[$j]))
				{
					$by_game[$j][] = NULL;
				}
				$game = $table[$j];
				if (!is_null($game) && count($game) >= 10)
				{
					$by_game[$j][$i] = $table[$j];
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
<?php
	}
}

$page = new Page();
$page->run(get_label('Seating'));

?>