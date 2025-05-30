<?php 

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/scoring.php';
require_once 'include/event.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/ccc_filter.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0008);
define('FLAG_FILTER_RATING', 0x0010);
define('FLAG_FILTER_NO_RATING', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NO_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends UserPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;
	
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
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<p>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</p>';
		
		echo '<div class="tab">';
		echo '<button' . ($moder ? '' : ' class="active"') . ' onclick="goTo({moder:0})">' . get_label('As a player') . '</button>';
		echo '<button' . ($moder ? ' class="active"' : '') . ' onclick="goTo({moder:1})">' . get_label('As a referee') . '</button>';
		echo '</div>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$event_pic = new Picture(EVENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		
		$condition = new SQL();
		$ccc_id = $ccc_filter->get_id();
		switch($ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND g.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND g.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.id = ? OR i.area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND g.event_id IN (SELECT e.id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities i ON i.id = a.city_id WHERE i.country_id = ?)', $ccc_id);
			break;
		}
		
		if ($filter & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$condition->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND g.is_rating <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND g.is_rating = 0');
		}
		if ($filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND g.is_canceled <> 0');
		}
		if ($filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND g.is_canceled = 0');
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		
		if ($moder != 0)
		{
			if ($result_filter > 2 && $result_filter < 5)
			{
				$result_filter = 0;
			}
			
			echo '<p><select id="result" onChange="filterChanged()">';
			show_option(-1, $result_filter, get_label('All games'));
			show_option(GAME_RESULT_TOWN, $result_filter, get_label('Town wins'));
			show_option(GAME_RESULT_MAFIA, $result_filter, get_label('Mafia wins'));
			show_option(GAME_RESULT_TIE, $result_filter, get_label('Ties'));
			echo '</select>';
			echo '</p>';
			
			if ($result_filter < 0)
			{
				$condition->add(' AND g.result > 0');
			}
			else
			{
				$condition->add(' AND g.result = ?', $result_filter);
			}
			
			list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE g.moderator_id = ?', $this->id, $condition);
			show_pages_navigation(PAGE_SIZE, $count);
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="th darker" align="center"><td width="48"></td><td colspan="2"></td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Event').'</td><td width="48">'.get_label('Tournament').'</td><td width="48">'.get_label('Result').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, g.start_time, g.end_time - g.start_time, g.result, g.is_rating, g.is_canceled, g.video_id, e.id, e.name, e.flags, t.id, t.name, t.flags, a.id, a.name, a.flags FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE g.moderator_id = ?',
				$this->id, $condition);
			$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			$num = $_page * PAGE_SIZE;
			while ($row = $query->next())
			{
				list ($game_id, $club_id, $club_name, $club_flags, $timezone, $start, $duration, $game_result, $is_rating, $is_canceled, $video_id, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $address_id, $address_name, $address_flags) = $row;
				
				echo '<tr align="center"';
				if ($is_canceled || !$is_rating)
				{
					echo ' class="dark"';
				}
				echo '>';
				echo '<td>' . ++$num . '</td>';

				if ($is_canceled || !$is_rating)
				{
					echo '<td align="left" style="padding-left:12px;">';
				}
				else
				{
					echo '<td align="left" colspan="2" style="padding-left:12px;">';
				}
				
				if ($video_id != NULL)
				{
					echo '<table class="transp" width="100%"><tr><td>';
				}
				echo '<a href="view_game.php?id=' . $game_id . '&moderator_id=' . $this->id . '&bck=1"><b>' . get_label('Game #[0]', $game_id) . '</b><br>';
				if ($tournament_name != NULL)
				{
					echo $tournament_name . ': ';
				}
				echo $event_name . '<br>' . format_date($start, $timezone, true) . '</a>';
				if ($video_id != NULL)
				{
					echo '</td><td align="right"><a href="javascript:mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/video.png" width="40" height="40"></a>';
					echo '</td></tr></table>';
				}
			
				if ($is_canceled)
				{
					echo '</td><td width="100" class="darker"><b>' . get_label('Canceled');
					if (!$is_rating)
					{
						echo '<br>' . get_label('Non-rating');
					}
					echo '</b></td>';
				}
				else if (!$is_rating)
				{
					echo '</s></td><td width="100" class="darker"><b>' . get_label('Non-rating') . '</b></td>';
				}
				echo '</td>';
				
				echo '<td>';
				$club_pic->set($club_id, $club_name, $club_flags);
				$club_pic->show(ICONS_DIR, true, 48);
				echo '</td>';
				
				echo '<td>';
				$event_pic->set($event_id, $event_name, $event_flags);
				$event_pic->show(ICONS_DIR, true, 48);
				echo '</td>';
				
				echo '<td>';
				$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
				$tournament_pic->show(ICONS_DIR, true, 48);
				echo '</td>';
				
				echo '<td>';
				switch ($game_result)
				{
					case GAME_RESULT_PLAYING:
						break;
					case GAME_RESULT_TOWN:
						echo '<img src="images/civ.png" title="' . get_label('town\'s vicory') . '" style="opacity: 0.5;">';
						break;
					case GAME_RESULT_MAFIA:
						echo '<img src="images/maf.png" title="' . get_label('mafia\'s vicory') . '" style="opacity: 0.5;">';
						break;
					case GAME_RESULT_TIE:
					echo '<img src="images/tie.png" title="' . get_label('tie') . '" style="opacity: 0.5;">';
						break;
				}
				echo '</td></tr>';
				++$count;
			}
			echo '</table>';
			show_pages_navigation(PAGE_SIZE, $count);
		}
		else
		{
			$roles = 0;
			if (isset($_REQUEST['roles']))
			{
				$roles = (int)$_REQUEST['roles'];
			}

			echo '<p><select id="result" onChange="filterChanged()">';
			show_option(0, $result_filter, get_label('All games'));
			show_option(1, $result_filter, get_label('Town wins'));
			show_option(2, $result_filter, get_label('Mafia wins'));
			show_option(3, $result_filter, get_label('[0] wins', $this->name));
			show_option(4, $result_filter, get_label('[0] losses', $this->name));
			echo '</select> ';
			echo '&emsp;&emsp;';
			show_roles_select($roles, 'filterChanged()', get_label('Games where [0] was in a specific role.', $this->name), ROLE_NAME_FLAG_SINGLE);
			echo '</p>';
			
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
			echo '<tr class="th darker" align="center"><td width="48"></td><td></td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Event').'</td><td width="48">'.get_label('Tournament').'</td><td width="48">'.get_label('Role').'</td><td width="48">'.get_label('Result').'</td><td width="100">'.get_label('Rating').'</td></tr>';
			
			$query = new DbQuery(
				'SELECT g.id, c.id, c.name, c.flags, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.is_rating, g.is_canceled, p.role, p.rating_before, p.rating_earned, g.video_id, e.id, e.name, e.flags, t.id, t.name, t.flags, a.id, a.name, a.flags FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
				' JOIN cities ct ON ct.id = a.city_id' .
				' WHERE p.user_id = ?', 
				$this->id, $condition);
			$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			$num = $_page * PAGE_SIZE;
			while ($row = $query->next())
			{
				list (
					$game_id, $club_id, $club_name, $club_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, 
					$game_result, $is_rating, $is_canceled, $role, $rating_before, $rating_earned, $video_id, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $address_id, $address_name, $address_flags) = $row;
			
				echo '<tr align="center"';
				if ($is_canceled || !$is_rating)
				{
					echo ' class="dark"';
				}
				echo '>';
				echo '<td>' . ++$num . '</td>';
			
				echo '<td align="left" style="padding-left:12px;">';
				if ($video_id != NULL)
				{
					echo '<table class="transp" width="100%"><tr><td>';
				}
				echo '<a href="view_game.php?id=' . $game_id . '&user_id=' . $this->id . '&bck=1"><b>' . get_label('Game #[0]', $game_id) . '</b><br>';
				if ($tournament_name != NULL)
				{
					echo $tournament_name . ': ';
				}
				echo $event_name . '<br>' . format_date($start, $timezone, true) . '</a>';
				if ($video_id != NULL)
				{
					echo '</td><td align="right"><a href="javascript:mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/video.png" width="40" height="40"></a>';
					echo '</td></tr></table>';
				}
				echo '</td>';
				
				echo '<td>';
				$club_pic->
					set($club_id, $club_name, $club_flags);
				$club_pic->show(ICONS_DIR, true, 48);
				echo '</td>';
				
				echo '<td>';
				$event_pic->set($event_id, $event_name, $event_flags);
				$event_pic->show(ICONS_DIR, true, 48);
				echo '</td>';
				
				echo '<td>';
				$tournament_pic->
					set($tournament_id, $tournament_name, $tournament_flags);
				$tournament_pic->show(ICONS_DIR, true, 48);
				echo '</td>';

				$win = 0;
				echo '<td>';
				switch ($role)
				{
					case 0: // civil;
						echo '<img src="images/civ.png" title="' . get_label('civil') . '" style="opacity: 0.5;">';
						$win = $game_result == GAME_RESULT_TOWN ? 1 : 2;
						break;
					case 1: // sherif;
						echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
						$win = $game_result == GAME_RESULT_TOWN ? 1 : 2;
						break;
					case 2: // mafia;
						echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
						$win = $game_result == GAME_RESULT_MAFIA ? 1 : 2;
						break;
					case 3: // don
						echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
						$win = $game_result == GAME_RESULT_MAFIA ? 1 : 2;
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
				if ($is_canceled)
				{
					echo '<td class="darker">' . get_label('Canceled');
					if (!$is_rating)
					{
						echo '<br>' . get_label('Non-rating');
					}
					echo '';
				}
				else if (!$is_rating)
				{
					echo '<td class="darker">' . get_label('Non-rating') . '';
				}
				else
				{
					echo '<td>';
					echo format_rating(USER_INITIAL_RATING + $rating_before);
					if ($rating_earned >= 0)
					{
						echo ' + ' . format_rating($rating_earned);
					}
					else
					{
						echo ' - ' . format_rating(-$rating_earned);
					}
					echo ' = ' . format_rating(USER_INITIAL_RATING + $rating_before + $rating_earned);
				}
				// echo '<td>' . format_rating($rating_earned);
				echo '</td></tr>';
			}
			echo '</table>';
			show_pages_navigation(PAGE_SIZE, $count);
		}
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({roles: $('#roles').val(), result: $('#result').val(), filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>