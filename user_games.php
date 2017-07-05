<?php 

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/scoring.php';

define("PAGE_SIZE", 20);

define('FLAG_MODER', 1);
define('FLAG_CIVIL_WON', 2);
define('FLAG_MAFIA_WON', 4);

define('FLAG_TERMINATED', 8);
define('FLAG_PLAYING', 16);

define('FLAG_WON', 8);
define('FLAG_LOST', 16);
define('FLAG_CIVIL', 32);
define('FLAG_SHERIFF', 64);
define('FLAG_MAFIA', 128);
define('FLAG_DON', 256);
define('FLAG_ANY_ROLE', 480);

class Page extends UserPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0]: games', $this->title);
	}
	
	protected function show_body()
	{
		global $_page;
	
		if (isset($_REQUEST['flags']))
		{
			$flags = $_REQUEST['flags'];
			if (!is_numeric($flags))
			{
				$flags = 0;
				$flags |= isset($_REQUEST['civil_won']) ? FLAG_CIVIL_WON : 0;
				$flags |= isset($_REQUEST['mafia_won']) ? FLAG_MAFIA_WON : 0;
				
				if (isset($_REQUEST['moder']))
				{
					$flags |= $_REQUEST['moder'] ? FLAG_MODER : 0;
				}
				
				if (($flags & FLAG_MODER) != 0)
				{
					$flags |= isset($_REQUEST['terminated']) ? FLAG_TERMINATED : 0;
					$flags |= isset($_REQUEST['playing']) ? FLAG_PLAYING : 0;
				}
				else
				{
					$flags |= isset($_REQUEST['won']) ? FLAG_WON : 0;
					$flags |= isset($_REQUEST['lost']) ? FLAG_LOST : 0;
					$flags |= isset($_REQUEST['civil']) ? FLAG_CIVIL : 0;
					$flags |= isset($_REQUEST['sheriff']) ? FLAG_SHERIFF : 0;
					$flags |= isset($_REQUEST['mafia']) ? FLAG_MAFIA : 0;
					$flags |= isset($_REQUEST['don']) ? FLAG_DON : 0;
				}
			}
		}
		else
		{
			$flags = 0;
			if (isset($_REQUEST['moder']))
			{
				$flags = $_REQUEST['moder'] ? FLAG_MODER : 0;
			}
			
			if ($flags == FLAG_MODER)
			{
				$flags = FLAG_MODER | FLAG_CIVIL_WON | FLAG_MAFIA_WON;
			}
			else
			{
				$flags = FLAG_CIVIL_WON | FLAG_MAFIA_WON | FLAG_WON | FLAG_LOST | FLAG_CIVIL | FLAG_SHERIFF | FLAG_MAFIA | FLAG_DON;
			}
		}
		
		if (($flags & FLAG_MODER) != 0)
		{
			$condition = new SQL('g.moderator_id = ? AND g.result IN (', $this->id);
			$delim = '';
			if (($flags & FLAG_CIVIL_WON) != 0)
			{
				$condition->add($delim . '1');
				$delim = ', ';
			}
			if (($flags & FLAG_MAFIA_WON) != 0)
			{
				$condition->add($delim . '2');
				$delim = ', ';
			}
			if (($flags & FLAG_TERMINATED) != 0)
			{
				$condition->add($delim . '3');
				$delim = ', ';
			}
			if (($flags & FLAG_PLAYING) != 0)
			{
				$condition->add($delim . '0');
				$delim = ', ';
			}
			if ($delim == '')
			{
				$condition->add('-1)');
			}
			else
			{
				$condition->add(')');
			}
			
			echo '<table class="transp" width="100%"><tr><td>';
			echo '<form method="get" name="filterForm" action="user_games.php">';
			echo '<input type="hidden" name="flags" value="">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<input type="hidden" name="moder" value="1">';
			echo '<input type="checkbox" value="" name="civil_won" onClick="document.filterForm.submit()"';
			if (($flags & FLAG_CIVIL_WON) != 0)
			{
				echo ' checked';
			}
			echo '>'.get_label('civilians won').' ';
			echo '<input type="checkbox" value="" name="mafia_won" onClick="document.filterForm.submit()"';
			if (($flags & FLAG_MAFIA_WON) != 0)
			{
				echo ' checked';
			}
			echo '>'.get_label('mafia won').' ';
			echo '<input type="checkbox" value="" name="terminated" onClick="document.filterForm.submit()"';
			if (($flags & FLAG_TERMINATED) != 0)
			{
				echo ' checked';
			}
			echo '>'.get_label('terminated').' ';
			echo '<input type="checkbox" value="" name="playing" onClick="document.filterForm.submit()"';
			if (($flags & FLAG_PLAYING) != 0)
			{
				echo ' checked';
			}
			echo '>'.get_label('still playing').'</td>';
			echo '</form>';
			
			echo '<td align="right" valign="top">';
			echo '<form method="get" name="moderForm" action="user_games.php">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<select name="moder" onChange = "document.moderForm.submit()">';
			echo '<option value="0">'.get_label('As a player').'</option>';
			echo '<option value="1" selected>'.get_label('As a moderator').'</option>';
			echo '</select>';
			echo '</form></td>';
			
			echo '</tr></table></form>';
			
			list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE ', $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="90"></td><td>'.get_label('Club name').'</td><td width="100">'.get_label('Moderator').'</td><td width="140">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="100">'.get_label('Result').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.name, ct.timezone, g.start_time, g.end_time - g.start_time, g.result FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE ',
				$condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				list ($game_id, $club_name, $timezone, $start, $duration, $game_result) = $row;
			
				echo '<tr><td class="dark"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
				echo '<td>' . $club_name . '</td>';
				echo '<td>' . cut_long_name($this->name, 40) . '</td>';
				echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
				echo '<td>' . format_time($duration) . '</td>';
				
				echo '<td>';
				switch ($game_result)
				{
					case 0:
						echo get_label('still playing');
						break;
					case 1: // civils won
						echo get_label('civilians won');
						break;
					case 2: // mafia won
						echo get_label('mafia won');
						break;
					case 3: // mafia won
						echo get_label('terminated');
						break;
				}
				echo '</td>';
				++$count;
			}
			echo '</table>';
		}
		else
		{
			$condition = new SQL('p.user_id = ?', $this->id);
			if (($flags & FLAG_ANY_ROLE) != FLAG_ANY_ROLE)
			{
				switch ($flags)
				{
					case FLAG_CIVIL:
						$condition->add(' AND p.role = 0');
						break;
					case FLAG_SHERIFF:
						$condition->add(' AND p.role = 1');
						break;
					case FLAG_MAFIA:
						$condition->add(' AND p.role = 2');
						break;
					case FLAG_DON:
						$condition->add(' AND p.role = 3');
						break;
					default:
						if (($flags & FLAG_ANY_ROLE) == 0)
						{
							$condition->add(' AND p.role < 0');
						}
						else
						{
							$condition->add(' AND p.role IN (');
							$delim = '';
							if(($flags & FLAG_CIVIL) != 0)
							{
								$condition->add($delim . '0');
								$delim = ', ';
							}
							if(($flags & FLAG_SHERIFF) != 0)
							{
								$condition->add($delim . '1');
								$delim = ', ';
							}
							if(($flags & FLAG_MAFIA) != 0)
							{
								$condition->add($delim . '2');
								$delim = ', ';
							}
							if(($flags & FLAG_DON) != 0)
							{
								$condition->add($delim . '3');
								$delim = ', ';
							}
							$condition->add(')');
						}
						break;
				}
			}
			if (($flags & FLAG_WON) == 0)
			{
				$condition->add(' AND p.won = 0');
			}
			if (($flags & FLAG_LOST) == 0)
			{
				$condition->add(' AND p.won > 0');
			}
			if (($flags & FLAG_CIVIL_WON) == 0)
			{
				$condition->add(' AND g.result <> 1');
			}
			if (($flags & FLAG_MAFIA_WON) == 0)
			{
				$condition->add(' AND g.result <> 2');
			}
			
			echo '<table class="transp" width="100%"><tr><td>';
			echo '<form method="get" name="filterForm" action="user_games.php">';
			echo '<input type="hidden" name="flags" value="">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<input type="hidden" name="moder" value="0">';
			echo '<input type="checkbox" value="" name="civil" onClick="document.filterForm.submit()" value="'.get_label('civil').'"';
			if (($flags & FLAG_CIVIL) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('civilian').' ';
			echo '<input type="checkbox" value="" name="sheriff" onClick="document.filterForm.submit()" value="'.get_label('sheriff').'"';
			if (($flags & FLAG_SHERIFF) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('sheriff').' ';
			echo '<input type="checkbox" value="" name="mafia" onClick="document.filterForm.submit()" value="'.get_label('mafia').'"';
			if (($flags & FLAG_MAFIA) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('mafia').' ';
			echo '<input type="checkbox" value="" name="don" onClick="document.filterForm.submit()" value="'.get_label('don').'"';
			if (($flags & FLAG_DON) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('don').'<br>';
			
			echo '<input type="checkbox" value="" name="won" onClick="document.filterForm.submit()" value="'.get_label('won').'"';
			if (($flags & FLAG_WON) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('won by').' ' . cut_long_name($this->name, 16) . ' ';
			echo '<input type="checkbox" value="" name="lost" onClick="document.filterForm.submit()" value="'.get_label('lost').'"';
			if (($flags & FLAG_LOST) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('lost by').' ' . cut_long_name($this->name, 16) . ' ';
			echo '<input type="checkbox" value="" name="civil_won" onClick="document.filterForm.submit()" value="1"';
			if (($flags & FLAG_CIVIL_WON) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('won by civilians').' ';
			echo '<input type="checkbox" value="" name="mafia_won" onClick="document.filterForm.submit()" value="'.get_label('mafia').'"';
			if (($flags & FLAG_MAFIA_WON) != 0)
			{
				echo ' checked';
			}
			echo '> '.get_label('won by mafia').' ';
			echo '</form></td>';
			
			echo '<td align="right" valign="top">';
			echo '<form method="get" name="moderForm" action="user_games.php">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<select name="moder" onChange = "document.moderForm.submit()">';
			echo '<option value="0" selected>'.get_label('As a player').'</option>';
			echo '<option value="1">'.get_label('As a moderator').'</option>';
			echo '</select>';
			echo '</form></td>';
			
			echo '</tr></table>';

			list ($count) = Db::record(get_label('player'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE ', $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="90"></td><td>'.get_label('Club name').'</td><td width="100">'.get_label('Moderator').'</td><td width="140">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="100">'.get_label('Result').'</td><td width="40">'.get_label('Rating berore the game').'</td><td width="40">'.get_label('Rating earned').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.name, ct.timezone, m.name, g.start_time, g.end_time - g.start_time, g.result, p.role, p.rating_before, p.rating_earned FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE ', 
				$condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				list (
					$game_id, $club_name, $timezone, $moder_name, $start, $duration, 
					$game_result, $role, $rating_before, $rating_earned) = $row;
			
				echo '<tr><td class="dark"><a href="view_game.php?id=' . $game_id . '&pid=' . $this->id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
				echo '<td>' . $club_name . '</td>';
				echo '<td>' . cut_long_name($moder_name, 36) . '</td>';
				echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
				echo '<td>' . format_time($duration) . '</td>';
				
				$row_text = get_label('invalid');
				switch ($game_result)
				{
					case 1: // civils won
						switch ($role)
						{
							case 0: // civil;
								$row_text = get_label('won as civil');
								break;
							case 1: // sherif;
								$row_text = get_label('won as sheriff');
								break;
							case 2: // mafia;
								$row_text = get_label('lost as mafia');
								break;
							case 3: // don
								$row_text = get_label('lost as don');
								break;
						}
						break;
					case 2: // mafia won
						switch ($role)
						{
							case 0: // civil;
								$row_text = get_label('lost as civil');
								break;
							case 1: // sherif;
								$row_text = get_label('lost as sheriff');
								break;
							case 2: // mafia;
								$row_text = get_label('won as mafia');
								break;
							case 3: // don
								$row_text = get_label('won as don');
								break;
						}
						break;
				}
				echo '<td>' . $row_text . '</td>';
				echo '<td>' . number_format($rating_before) . '</td>';
				echo '<td>' . number_format($rating_earned) . '</td></tr>';
			}
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run(get_label('[0]: games', get_label('User')), PERM_ALL);

?>