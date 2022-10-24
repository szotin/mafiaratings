<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/picture.php';

try
{
	initiate_session();
	check_maintenance();
	
	$club_pic = new Picture(CLUB_PICTURE);
	$tournament_pic = new Picture(TOURNAMENT_PICTURE, $club_pic);
	$tournament_user_pic =
		new Picture(USER_TOURNAMENT_PICTURE,
		new Picture(USER_CLUB_PICTURE,
		new Picture(USER_PICTURE)));
	
	list ($tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags) = 
		Db::record(get_label('tournament'), 'SELECT t.id, t.name, t.flags, c.id, c.name, c.flags FROM tournaments t JOIN clubs c ON c.id = t.club_id WHERE t.id = ?', $_REQUEST['id']);
	
	$is_manager = is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $club_id, $tournament_id);
	$rows = 0;
	$cols = 0;
	$refr = 0;
	$title = 1;
	$logo_height = TNAIL_HEIGHT;
	$edit_settings = isset($_REQUEST['settings']);
	
	if ($is_manager && isset($_REQUEST['save']))
	{
		if (isset($_REQUEST['r']))
		{
			$rows = (int)$_REQUEST['r'];
		}
		
		if (isset($_REQUEST['c']))
		{
			$cols = (int)$_REQUEST['c'];
		}

		$title = isset($_REQUEST['t']);

		if (isset($_REQUEST['refr']))
		{
			$refr = (int)$_REQUEST['refr'];
		}
		
		if (isset($_REQUEST['l']))
		{
			$logo_height = (int)$_REQUEST['l'];
		}
		$json = json_encode(array($rows, $cols, $logo_height, $title, $refr));
		$query = Db::exec(get_label('tournament'), 'UPDATE tournaments SET standings_settings = ? WHERE id = ?', $json, $tournament_id);
		header('location: tournament_screen.php?id=' . $tournament_id);
	}
	else
	{
		echo '<!DOCTYPE HTML>';
		echo '<html>';
		echo '<head>';
		echo '<META content="text/html; charset=utf-8" http-equiv=Content-Type>';
		echo '<script src="js/labels_' . $_lang_code . '.js"></script>';
		echo '<link rel="stylesheet" href="desktop.css" type="text/css" media="screen" />';
		echo '<link rel="stylesheet" href="common.css" type="text/css" media="screen" />';
		
		echo '</head><body>';
		
		list ($standings_settings) = Db::record(get_label('tournament'), 'SELECT e.standings_settings FROM tournaments e WHERE e.id = ?', $tournament_id);
		if (is_null($standings_settings))
		{
			$edit_settings = true;
			$query = Db::query('SELECT e.standings_settings FROM tournaments e WHERE e.standings_settings IS NOT NULL AND e.club_id = ? ORDER BY e.id DESC LIMIT 1', $club_id);
			if ($row = $query->next())
			{
				list ($standings_settings) = $row;
			}
		}
		
		if (!is_null($standings_settings))
		{
			list ($rows, $cols, $logo_height, $title, $refr) = json_decode($standings_settings);
		}
	
		if ($rows <= 0)
		{
			$rows = 10;
		}
		if ($cols <= 0)
		{
			$cols = 2;
		}
		if ($refr <= 0)
		{
			$refr = 60;
		}
		
		if ($edit_settings && $is_manager)
		{
			echo '<h1>' . get_label('Standings screen settings') . '</h1>';
			echo '<form method="get" action="tournament_screen.php">';
			echo '<input type="hidden" name="id" value="' . $tournament_id . '">';
			echo '<input type="hidden" name="save">';
			echo '<table class="dialog_form" width="100%">';
			echo '<tr><td width="100">'.get_label('Rows').':</td><td><input name="r" value="' . $rows . '"></td></tr>';
			echo '<tr><td width="100">'.get_label('Columns').':</td><td><input name="c" value="' . $cols . '"></td></tr>';
			echo '<tr><td width="100">'.get_label('Logo height').':</td><td><input name="l" value="' . $logo_height . '"> (' . get_label('Use 0 for no logo') . ')</td></tr>';
			echo '<tr><td width="100">'.get_label('Refresh every').':</td><td><input name="refr" value="' . $refr . '"> ' . get_label('sec') . '</td></tr>';
			echo '<tr><td colspan="2"><input type="checkbox" name="t"';
			if ($title)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show title') . '</td></tr>';
			echo '</table>';
			echo '<br><input value="'.get_label('Save').'" class="btn norm" type="submit">';
			// echo ' <input value="'.get_label('Default values').'" type="submit" class="btn long" onClick="resetToDefault()">';
			echo '</form>';
		}
		else
		{
			$page_size = $rows * $cols;
			
			if ($title)
			{
				echo '<table width="100%"><tr><td><h2>' . $tournament_name . '</h2>';
				if ($logo_height > 0)
				{
					echo '</td><td align="right">';
					$icon = (abs($logo_height - ICON_HEIGHT) < abs($logo_height - TNAIL_HEIGHT));
					$tournament_pic->
						set($tournament_id, $tournament_name, $tournament_flags)->
						set($club_id, $club_name, $club_flags);
					$tournament_pic->show($icon ? ICONS_DIR : TNAILS_DIR, false, 0, $logo_height);
				}
				echo '</td></tr></table>';
			}
			
			list($scoring, $normalizer, $scoring_options) =  Db::record(get_label('tournament'), 'SELECT s.scoring, n.normalizer, t.scoring_options FROM tournaments t JOIN scoring_versions s ON s.scoring_id = t.scoring_id AND s.version = t.scoring_version LEFT OUTER JOIN normalizer_versions n ON n.normalizer_id = t.normalizer_id AND n.version = t.normalizer_version WHERE t.id = ?', $tournament_id);
			if (is_null($normalizer))
			{
				$normalizer = '{}';
			}
			$scoring = json_decode($scoring);
			$normalizer = json_decode($normalizer);
			$scoring_options = json_decode($scoring_options);
			$players = tournament_scores($tournament_id, $tournament_flags, NULL, SCORING_LOD_PER_GROUP, $scoring, $normalizer, $scoring_options);
			$players_count = count($players);
				
			if ($players_count == 0)
			{
				$players = array();
				$page_size = $rows * $cols;
			
				$query = new DbQuery(
					'SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags, tu.flags, cu.flags' . 
						' FROM tournament_users tu' . 
						' JOIN users u ON tu.user_id = u.id' .
						' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
						' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
						' WHERE tu.tournament_id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $page_size,
						$club_id, $tournament_id);
				while ($row = $query->next())
				{
					$players[] = $row;
				}

				if (count($players) == 0)
				{
					$query = new DbQuery(
						'SELECT u.id, u.name, u.rating, u.games, u.games_won, u.flags, c.id, c.name, c.flags, NULL, cu.flags' . 
						' FROM users u' . 
						' LEFT OUTER JOIN clubs c ON u.club_id = c.id' .
						' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = c.id' .
						' WHERE c.id = ? ORDER BY u.rating DESC, u.games, u.games_won DESC, u.id LIMIT ' . $page_size,
						$club_id);
					while ($row = $query->next())
					{
						$players[] = $row;
					}
				}
				
				$number = 0;
				echo '<table width="100%"><tr>';
				for ($i = 0; $i < $cols; ++$i)
				{
					for ($j = 0; $j < $rows; ++$j)
					{
						
						if ($number >= count($players))
						{
							break;
						}
						list ($id, $name, $points, $games_played, $games_won, $flags, $user_club_id, $user_club_name, $club_user_flags, $tournament_user_flags, $club_user_flags) = $players[$number++];
						
						if ($j == 0)
						{
							echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
							echo '<tr class="th-long darker">';
							echo '<td width="20"><button class="icon" onclick="window.location.replace(\'tournament_screen.php?id=' . $tournament_id . '&settings\')" title="' . get_label('Settings') . '"><img src="images/settings.png" border="0"></button></td>';
							echo '<td colspan="3">'.get_label('Player').'</td>';
							echo '<td width="60" align="center">'.get_label('Rating').'</td>';
							echo '<td width="60" align="center">'.get_label('Games played').'</td>';
							echo '<td width="60" align="center">'.get_label('Wins').'</td>';
							echo '</tr>';
						}
						
						echo '<tr>';
						echo '<td align="center" class="dark">' . $number . '</td>';
						echo '<td width="50">';
						$tournament_user_pic->
							set($id, $name, $tournament_user_flags, 't' . $tournament_id)->
							set($id, $name, $club_user_flags, 'c' . $club_id)->
							set($id, $name, $flags);
						$tournament_user_pic->show(ICONS_DIR, false, 50);
						echo '</td><td>' . $name . '</td>';
						echo '<td width="50" align="center">';
						$club_pic->set($user_club_id, $user_club_name, $club_user_flags);
						$club_pic->show(ICONS_DIR, false, 40);
						echo '</td>';
						echo '<td align="center" class="lighter">';
						echo format_rating($points);
						echo '</td>';
						echo '<td align="center">' . $games_played . '</td>';
						echo '<td align="center">' . $games_won . '</td>';
						echo '</tr>';
					}
					echo '</table></td>';
				}
				echo '</tr></table>';
			}
			else
			{
				$number = 0;
				echo '<table width="100%"><tr>';
				for ($i = 0; $i < $cols; ++$i)
				{
					for ($j = 0; $j < $rows; ++$j)
					{
						if ($number >= $players_count)
						{
							break;
						}
						$player = $players[$number++];
						
						if ($j == 0)
						{
							echo '<td width="' . (100 / $cols) . '%" valign="top"><table class="bordered light" width="100%">';
							echo '<tr class="th darker">';
							echo '<td width="20" rowspan="2">';
							if ($is_manager)
							{
								echo '<button class="icon" onclick="window.location.replace(\'tournament_screen.php?id=' . $tournament_id . '&settings\')" title="' . get_label('Settings') . '"><img src="images/settings.png" border="0"></button>';
							}
							echo '</td>';
							echo '<td colspan="3" rowspan="2">'.get_label('Player').'</td>';
							echo '<td align="center" colspan="6">'.get_label('Points').'</td>';
							echo '<td width="36" align="center" rowspan="2">'.get_label('Games played').'</td>';
							echo '<td width="36" align="center" rowspan="2">'.get_label('Wins').'</td>';
							echo '</tr>';
							echo '<tr class="th darker" align="center"><td width="36">' . get_label('Sum') . '</td><td width="36">' . get_label('Main') . '</td><td width="36">' . get_label('Legacy') . '</td><td width="36">' . get_label('Bonus') . '</td><td width="36">' . get_label('Penlt') . '</td><td width="36">' . get_label('FK') . '</td></tr>';
						}
						
						echo '<tr>';
						echo '<td align="center" class="dark">' . $number . '</td>';
						echo '<td width="50">';
						$tournament_user_pic->
							set($player->id, $player->name, $player->tournament_user_flags, 't' . $tournament_id)->
							set($player->id, $player->name, $player->club_user_flags, 'c' . $club_id)->
							set($player->id, $player->name, $player->flags);
						$tournament_user_pic->show(ICONS_DIR, false, 50);
						echo '</td><td>' . $player->name . '</td>';
						echo '<td width="50" align="center">';
						if (!is_null($player->club_id) && $player->club_id > 0)
						{
							$club_pic->set($player->club_id, $player->club_name, $player->club_flags);
							$club_pic->show(ICONS_DIR, false, 40);
						}
						echo '</td>';
						
						echo '<td align="center" class="dark">' . format_score($player->points) . '</td>';
						echo '<td align="center">' . format_score($player->main_points) . '</td>';
						echo '<td align="center">' . format_score($player->legacy_points) . '</td>';
						echo '<td align="center">' . format_score($player->extra_points) . '</td>';
						echo '<td align="center">' . format_score($player->penalty_points) . '</td>';
						echo '<td align="center">' . format_score($player->night1_points) . '</td>';
						echo '<td align="center">' . $player->games_count . '</td>';
						echo '<td align="center">' . $player->wins . '</td>';
						
						echo '</tr>';
					}
					echo '</table></td>';
				}
				echo '</tr></table>';
			}
			echo '</body>';
		}
	}
}
catch (Exception $e)
{
	echo $e->getMessage();
	Exc::log($e);
}
?>

<script>
setTimeout(function() { window.location.replace(document.URL); }, <?php echo $refr * 1000; ?>);
</script>
