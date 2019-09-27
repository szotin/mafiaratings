<?php 

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/scoring.php';
require_once 'include/event.php';

define("PAGE_SIZE", 20);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_page;
	
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
		
		$with_video = isset($_REQUEST['video']);
		$condition = new SQL();
		if ($with_video)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		
		echo '<p><form method="get" name="filterForm" action="user_games.php">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<select name="moder" onChange = "document.filterForm.submit()">';
		show_option(0, $moder, get_label('As a player'));
		show_option(1, $moder, get_label('As a moderator'));
		echo '</select>';
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, new Picture(CLUB_PICTURE)));
		if ($moder != 0)
		{
			if ($result_filter > 2 && $result_filter < 5)
			{
				$result_filter = 0;
			}
			
			echo ' <select name="result" onChange="document.filterForm.submit()">';
			show_option(0, $result_filter, get_label('All games'));
			show_option(1, $result_filter, get_label('Town wins'));
			show_option(2, $result_filter, get_label('Mafia wins'));
			echo '</select>';
			echo ' <input type="checkbox" name="video" onclick="document.filterForm.submit()"';
			if ($with_video)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show only games with video');
			echo '</form></p>';
			
			switch ($result_filter)
			{
				case 1:
					$condition->add(' AND g.result = 1');
					break;
				case 2:
					$condition->add(' AND g.result = 2');
					break;
				default:
					$condition->add(' AND g.result <> 0');
					break;
			}
			
			list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE g.moderator_id = ?', $this->id, $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker" align="center"><td colspan="2"></td><td width="48">'.get_label('Club').'</td><td width="120">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td><td width="60">'.get_label('Video').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, g.start_time, g.end_time - g.start_time, g.result, g.canceled, v.video, e.id, e.name, e.flags, t.id, t.name, t.flags FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' LEFT OUTER JOIN videos v ON v.id = g.video_id' .
				' WHERE g.moderator_id = ?',
				$this->id, $condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				list ($game_id, $club_id, $club_name, $club_flags, $timezone, $start, $duration, $game_result, $is_canceled, $video, $event_id, $event_name, $event_flags, $tour_id, $tour_name, $tour_flags) = $row;
				
				if ($is_canceled)
				{
					echo '<tr align="center" class="dark"><td align="left"><s>';
				}
				else
				{
					echo '<tr align="center"><td align="left" colspan="2">';
				}
				echo '<a href="view_game.php?moderator_id=' . $this->id . '&id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a>';
				if ($is_canceled)
				{
					echo '</s></td><td width="150" class="darker"><b>' . get_label('Game canceled') . '</b></td>';
				}
				echo '</td>';
				
				echo '<td>';
				$event_pic->
					set($event_id, $event_name, $event_flags)->
					set($tour_id, $tour_name, $tour_flags)->
					set($club_id, $club_name, $club_flags);
				$event_pic->show(ICONS_DIR, 48);
				echo '</td>';
				
				if ($is_canceled)
				{
					echo '<td><s>' . format_date('M j Y, H:i', $start, $timezone) . '</s></td>';
					echo '<td><s>' . format_time($duration) . '</s></td>';
				}
				else
				{
					echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
					echo '<td>' . format_time($duration) . '</td>';
				}
				
				echo '<td>';
				switch ($game_result)
				{
					case 0:
						break;
					case 1: // civils won
						echo '<img src="images/civ.png" title="' . get_label('town\'s vicory') . '" style="opacity: 0.5;">';
						break;
					case 2: // mafia won
						echo '<img src="images/maf.png" title="' . get_label('mafia\'s vicory') . '" style="opacity: 0.5;">';
						break;
				}
				echo '</td><td>';
				if ($video != NULL)
				{
					echo '<button class="icon" onclick="mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/film.png" border="0"></button>';
				}
				echo '</td></tr>';
				++$count;
			}
			echo '</table>';
		}
		else
		{
			$roles = 0;
			if (isset($_REQUEST['roles']))
			{
				$roles = (int)$_REQUEST['roles'];
			}

			echo ' <select name="result" onChange="document.filterForm.submit()">';
			show_option(0, $result_filter, get_label('All games'));
			show_option(1, $result_filter, get_label('Town wins'));
			show_option(2, $result_filter, get_label('Mafia wins'));
			show_option(3, $result_filter, get_label('[0] wins', $this->name));
			show_option(4, $result_filter, get_label('[0] losses', $this->name));
			echo '</select> ';
			show_roles_select($roles, 'document.filterForm.submit()', get_label('Games where [0] was in a specific role.', $this->name), ROLE_NAME_FLAG_SINGLE);
			echo ' <input type="checkbox" name="video" onclick="document.filterForm.submit()"';
			if ($with_video)
			{
				echo ' checked';
			}
			echo '> ' . get_label('show only games with video');
			echo '</form></p>';
			
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
				default:
					$condition->add(' AND g.result <> 0');
					break;
			}
			
			list ($count) = Db::record(get_label('player'), 'SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = ?', $this->id, $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker" align="center"><td colspan="2"></td><td width="48">'.get_label('Event').'</td><td width="120">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Role').'</td><td width="60">'.get_label('Result').'</td><td width="100">'.get_label('Rating').'</td><td width="60">'.get_label('Video').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.canceled, p.role, p.rating_before, p.rating_earned, v.video, e.id, e.name, e.flags, t.id, t.name, t.flags FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' LEFT OUTER JOIN videos v ON v.id = g.video_id' .
				' WHERE p.user_id = ?', 
				$this->id, $condition);
			$query->add(' ORDER BY g.start_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			while ($row = $query->next())
			{
				list (
					$game_id, $club_id, $club_name, $club_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, 
					$game_result, $is_canceled, $role, $rating_before, $rating_earned, $video, $event_id, $event_name, $event_flags, $tour_id, $tour_name, $tour_flags) = $row;
			
				if ($is_canceled)
				{
					$s_open = '<s>';
					$s_close = '</s>';
					echo '<tr align="center" class="dark"><td align="left"><s>';
				}
				else
				{
					$s_open = $s_close = '';
					echo '<tr align="center"><td align="left" colspan="2">';
				}
				echo '<a href="view_game.php?user_id=' . $this->id . '&id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a>';
				if ($is_canceled)
				{
					echo '</s></td><td width="150" class="darker"><b>' . get_label('Game canceled') . '</b></td>';
				}
				echo '</td>';
				
				echo '<td>';
				$event_pic->
					set($event_id, $event_name, $event_flags)->
					set($tour_id, $tour_name, $tour_flags)->
					set($club_id, $club_name, $club_flags);
				$event_pic->show(ICONS_DIR, 48);
				echo '</td>';

				echo '<td>' . $s_open . format_date('M j Y, H:i', $start, $timezone) . $s_close . '</td>';
				echo '<td>' . $s_open . format_time($duration) . $s_close . '</td>';
				
				$win = 0;
				echo '<td>';
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
				echo '<td>';
				switch ($win)
				{
					case 1:
						echo '<img src="images/won.png" title="' . get_label('win') . '" style="opacity: 0.8;">';
						break;
					case 2:
						echo '<img src="images/lost.png" title="' . get_label('loss') . '" style="opacity: 0.8;">';
						break;
				}
				echo '</td>';
				echo '<td>' . $s_open . format_rating($rating_before);
				if ($rating_earned >= 0)
				{
					echo ' + ' . format_rating($rating_earned);
				}
				else
				{
					echo ' - ' . format_rating(-$rating_earned);
				}
				echo ' = ' . format_rating($rating_before + $rating_earned) . $s_close . '</td>';
				// echo '<td>' . format_rating($rating_earned);
				echo '</td><td>';
				if ($video != NULL)
				{
					echo '<button class="icon" onclick="mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/film.png" border="0"></button>';
				}
				echo '</td></tr>';
			}
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>