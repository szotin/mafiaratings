<?php 

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/scoring.php';

define("PAGE_SIZE", 20);

define('FLAG_MODER', 1);
define('FLAG_CIVIL_WON', 2);
define('FLAG_MAFIA_WON', 4);

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
				
				if (($flags & FLAG_MODER) == 0)
				{
					$flags |= isset($_REQUEST['won']) ? FLAG_WON : 0;
					$flags |= isset($_REQUEST['lost']) ? FLAG_LOST : 0;
					$flags |= isset($_REQUEST['civil']) ? FLAG_CIVIL : 0;
					$flags |= isset($_REQUEST['sheriff']) ? FLAG_SHERIFF : 0;
					$flags |= isset($_REQUEST['mafia']) ? FLAG_MAFIA : 0;
					$flags |= isset($_REQUEST['don']) ? FLAG_DON : 0;
				}
			}
			
			if (isset($_REQUEST['moder']))
			{
				$flags |= $_REQUEST['moder'] ? FLAG_MODER : 0;
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
			if ($delim == '')
			{
				$condition->add('-1)');
			}
			else
			{
				$condition->add(')');
			}
			
			echo '<form method="get" name="moderForm" action="user_games.php">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<select name="moder" onChange = "document.moderForm.submit()">';
			echo '<option value="0">'.get_label('As a player').'</option>';
			echo '<option value="1" selected>'.get_label('As a moderator').'</option>';
			echo '</select>';
			echo '</form>';
			
			list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE ', $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="90" align="center">';
			echo '<button class="icon" onclick="filterGames()" title="' . get_label('Filter [0]', get_label('games')) . '"><img src="images/filter.png" border="0"></button>';
			echo '</td><td width="48">'.get_label('Club').'</td><td>'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, g.start_time, g.end_time - g.start_time, g.result FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE ',
				$condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				list ($game_id, $club_id, $club_name, $club_flags, $timezone, $start, $duration, $game_result) = $row;
				
				echo '<tr><td class="dark"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
				echo '<td>';
				show_club_pic($club_id, $club_flags, ICONS_DIR, 48, 48, ' title="' . $club_name . '"');
				echo '</td>';
				echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
				echo '<td align="center">' . format_time($duration) . '</td>';
				
				echo '<td align="center">';
				switch ($game_result)
				{
					case 0:
						break;
					case 1: // civils won
						echo '<img src="images/civ.png" title="' . get_label('civilians won') . '" style="opacity: 0.5;">';
						break;
					case 2: // mafia won
						echo '<img src="images/maf.png" title="' . get_label('mafia won') . '" style="opacity: 0.5;">';
						break;
				}
				echo '</td>';
				++$count;
			}
			echo '</table>';

			echo '<script type="text/javascript">';
			echo "var filterText = '";
			
			echo '<input type="checkbox" value="" id="filter-civil_won"';
			if (($flags & FLAG_CIVIL_WON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games won by [0]', get_label('town'));
			echo '<br><input type="checkbox" value="" id="filter-mafia_won"';
			if (($flags & FLAG_MAFIA_WON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games won by [0]', get_label('mafia'));
			echo "';";
?>
			function resetFilter()
			{
				window.location.replace("user_games.php?id=<?php echo $this->id; ?>&moder=1");
			}
			
			function setFilter()
			{
				var flags = 0;
				if ($('#filter-civil_won').attr('checked'))
				{
					flags |= <?php echo FLAG_CIVIL_WON; ?>;
				}
				if ($('#filter-mafia_won').attr('checked'))
				{
					flags |= <?php echo FLAG_MAFIA_WON; ?>;
				}
				var url = "user_games.php?id=<?php echo $this->id; ?>&moder=1&flags=" + flags;
				window.location.replace(url);
			}
<?php 
			echo '</script>';
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
			
			echo '<form method="get" name="moderForm" action="user_games.php">';
			echo '<input type="hidden" name="id" value="' . $this->id . '">';
			echo '<select name="moder" onChange = "document.moderForm.submit()">';
			echo '<option value="0" selected>'.get_label('As a player').'</option>';
			echo '<option value="1">'.get_label('As a moderator').'</option>';
			echo '</select>';
			echo '</form>';
			
			// echo '</td></tr></table>';

			list ($count) = Db::record(get_label('player'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE ', $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker"><td width="90" align="center">';
			echo '<button class="icon" onclick="filterGames()" title="' . get_label('Filter [0]', get_label('games')) . '"><img src="images/filter.png" border="0"></button>';
			echo '</td><td width="48" align="center">'.get_label('Club').'</td><td width="48" align="center">'.get_label('Moderator').'</td><td>'.get_label('Time').'</td><td width="60" align="center">'.get_label('Duration').'</td><td width="60" align="center">'.get_label('Role').'</td><td width="60" align="center">'.get_label('Result').'</td><td width="40" align="center">'.get_label('Rating berore the game').'</td><td width="40" align="center">'.get_label('Rating earned').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, p.role, p.rating_before, p.rating_earned FROM players p' .
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
					$game_id, $club_id, $club_name, $club_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, 
					$game_result, $role, $rating_before, $rating_earned) = $row;
			
				echo '<tr><td class="dark"><a href="view_game.php?id=' . $game_id . '&pid=' . $this->id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
				echo '<td>';
				show_club_pic($club_id, $club_flags, ICONS_DIR, 48, 48, ' title="' . $club_name . '"');
				echo '</td>';
				echo '<td align="center">';
				show_user_pic($moder_id, $moder_flags, ICONS_DIR, 32, 32, ' title="' . $moder_name . '" style="opacity: 0.8;"');
				echo '</td>';
				echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
				echo '<td align="center">' . format_time($duration) . '</td>';
				
				$win = 0;
				echo '<td align="center">';
				switch ($role)
				{
					case 0: // civil;
						echo '<img src="images/civ.png" title="' . get_label('civil') . '" style="opacity: 0.5;">';
						$win = $game_result == 1 ? 1 : 2;
						break;
					case 1: // sherif;
						echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
						$win = $game_result == 1 ? 1 : 2;
						break;
					case 2: // mafia;
						echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
						$win = $game_result == 2 ? 1 : 2;
						break;
					case 3: // don
						echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
						$win = $game_result == 2 ? 1 : 2;
						break;
				}
				echo '</td>';
				echo '<td align="center">';
				switch ($win)
				{
					case 1:
						echo '<img src="images/won.png" title="' . get_label('won') . '" style="opacity: 0.8;">';
						break;
					case 2:
						echo '<img src="images/lost.png" title="' . get_label('lost') . '" style="opacity: 0.8;">';
						break;
				}
				echo '</td>';
				echo '<td align="center">' . format_rating($rating_before) . '</td>';
				echo '<td align="center">' . format_rating($rating_earned) . '</td></tr>';
			}
			echo '</table>';

			echo '<script type="text/javascript">';
			echo "var filterText = '";
			echo '<table class="dialog_form" width="100%"><tr><td>';
			echo '<input type="checkbox" value="" id="filter-civil"';
			if (($flags & FLAG_CIVIL) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games where [0] was [1]', $this->name, get_label('civilian'));
			echo '<br><input type="checkbox" value="" id="filter-sheriff"';
			if (($flags & FLAG_SHERIFF) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games where [0] was [1]', $this->name, get_label('sheriff'));
			echo '<br><input type="checkbox" value="" id="filter-mafia"';
			if (($flags & FLAG_MAFIA) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games where [0] was [1]', $this->name, get_label('mafia'));
			echo '<br><input type="checkbox" value="" id="filter-don"';
			if (($flags & FLAG_DON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games where [0] was [1]', $this->name, get_label('don'));
			
			echo '</td></tr><tr><td><input type="checkbox" value="" id="filter-won"';
			if (($flags & FLAG_WON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games won by [0]', $this->name);
			echo '<br><input type="checkbox" value="" id="filter-lost"';
			if (($flags & FLAG_LOST) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games lost by [0]', $this->name);
			echo '</td></tr><tr><td><input type="checkbox" value="" id="filter-civil_won" value="1"';
			if (($flags & FLAG_CIVIL_WON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games won by [0]', get_label('town'));
			echo '<br><input type="checkbox" value="" id="filter-mafia_won"';
			if (($flags & FLAG_MAFIA_WON) != 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show games won by [0]', get_label('mafia'));
			
			echo "</td></tr></table>';";
?>
			function resetFilter()
			{
				window.location.replace("user_games.php?id=<?php echo $this->id; ?>");
			}
			
			function setFilter()
			{
				var flags = 0;
				if ($('#filter-civil').attr('checked'))
				{
					flags |= <?php echo FLAG_CIVIL; ?>;
				}
				if ($('#filter-sheriff').attr('checked'))
				{
					flags |= <?php echo FLAG_SHERIFF; ?>;
				}
				if ($('#filter-mafia').attr('checked'))
				{
					flags |= <?php echo FLAG_MAFIA; ?>;
				}
				if ($('#filter-don').attr('checked'))
				{
					flags |= <?php echo FLAG_DON; ?>;
				}
				if ($('#filter-won').attr('checked'))
				{
					flags |= <?php echo FLAG_WON; ?>;
				}
				if ($('#filter-lost').attr('checked'))
				{
					flags |= <?php echo FLAG_LOST; ?>;
				}
				if ($('#filter-civil_won').attr('checked'))
				{
					flags |= <?php echo FLAG_CIVIL_WON; ?>;
				}
				if ($('#filter-mafia_won').attr('checked'))
				{
					flags |= <?php echo FLAG_MAFIA_WON; ?>;
				}
				var url = "user_games.php?id=<?php echo $this->id; ?>&flags=" + flags;
				window.location.replace(url);
			}
<?php 
			echo '</script>';
		}
	}
}

$page = new Page();
$page->run(get_label('[0]: games', get_label('User')), PERM_ALL);

?>

<script type="text/javascript">

	function filterGames()
	{
		dlg.custom(filterText, "<?php echo get_label('Filter [0]', get_label('games')); ?>", 400, 
		{
			reset: { id:"dlg-reset", text: "<?php echo get_label('Remove'); ?>", click: resetFilter },
			apply: { id:"dlg-apply", text: "<?php echo get_label('Apply'); ?>", click: setFilter }
		});
	}
	
</script>
