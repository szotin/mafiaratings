<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_TOURNAMENT', 0x0004);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0008);
define('FLAG_FILTER_RATING', 0x0010);
define('FLAG_FILTER_NO_RATING', 0x0020);
define('FLAG_FILTER_CANCELED', 0x0040);
define('FLAG_FILTER_NO_CANCELED', 0x0080);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page;
		
		$result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$result_filter = (int)$_REQUEST['results'];
			if ($result_filter == 0 && !$this->is_manager)
			{
				$result_filter = -1;
			}
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$event_pic = new Picture(EVENT_PICTURE);
		$club_user_pic = new Picture(USER_CLUB_PICTURE, $this->user_pic);
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo '<select id="results" onChange="filterChanged()">';
		show_option(-1, $result_filter, get_label('All games'));
		show_option(1, $result_filter, get_label('Town wins'));
		show_option(2, $result_filter, get_label('Mafia wins'));
		if ($this->is_manager)
		{
			show_option(0, $result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
		show_checkbox_filter(array(get_label('with video'), get_label('tournament games'), get_label('rating games'), get_label('canceled games')), $filter, 'filterChanged');
		echo '</td></tr></table></form></p>';

		$condition = new SQL(' WHERE g.club_id = ?', $this->id);
		if ($result_filter < 0)
		{
			$condition->add(' AND g.result <> 0');
		}
		else
		{
			$condition->add(' AND g.result = ?', $result_filter);
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
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$is_user = is_permitted(PERMISSION_USER);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td';
		if ($is_user)
		{
			echo ' colspan="3"';
		}
		else
		{
			echo ' colspan="2"';
		}
		echo '>&nbsp;</td><td width="48">'.get_label('Event').'</td><td width="48">'.get_label('Tournament').'</td><td width="48">'.get_label('Moderator').'</td><td width="48">'.get_label('Result').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, c.timezone, m.id, m.name, m.flags, cu.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.is_rating, g.is_canceled, e.id, e.name, e.flags, t.id, t.name, t.flags, a.id, a.name, a.flags, l.id, l.name, l.flags FROM games g' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN leagues l ON l.id = t.league_id' .
				' LEFT OUTER JOIN club_users cu ON cu.club_id = g.club_id AND cu.user_id = m.id' .
				' JOIN cities c ON c.id = a.city_id',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $timezone, $moder_id, $moder_name, $moder_flags, $club_moder_flags, $start, $duration, $game_result, $video_id, $is_rating, $is_canceled, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $address_id, $address_name, $address_flags, $league_id, $league_name, $league_flags) = $row;
			
			echo '<tr align="center"';
			if ($is_canceled || !$is_rating)
			{
				echo ' class="dark"';
			}
			echo '>';
			
			if ($this->is_manager)
			{
				echo '<td class="dark" width="90">';
				echo '<button class="icon" onclick="mr.deleteGame(' . $game_id . ', \'' . get_label('Are you sure you want to delete the game [0]?', $game_id) . '\')" title="' . get_label('Delete game [0]', $game_id) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editGame(' . $game_id . ')" title="' . get_label('Edit game [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
				if ($video_id == NULL)
				{
					echo '<button class="icon" onclick="mr.setGameVideo(' . $game_id . ')" title="' . get_label('Add game [0] video', $game_id) . '"><img src="images/film-add.png" border="0"></button>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.deleteVideo(' . $video_id . ', \'' . get_label('Are you sure you want to remove video from the game [0]?', $game_id) . '\')" title="' . get_label('Remove game [0] video', $game_id) . '"><img src="images/film-delete.png" border="0"></button>';
				}
				echo '</td>';
			}
			else if ($is_user)
			{
				echo '<td class="dark" width="30">';
				echo '</td>';
			}
			
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
			echo '<a href="view_game.php?id=' . $game_id . '&club_id=' . $this->id . '&bck=1"><b>' . get_label('Game #[0]', $game_id) . '</b><br>';
			if ($tournament_name != NULL)
			{
				echo $tournament_name . ': ';
			}
			echo $event_name . '<br>' . format_date('F d Y, H:i', $start, $timezone) . '</a>';
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
				echo '</td><td width="100" class="darker"><b>' . get_label('Non-rating') . '</b></td>';
			}
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
			$club_user_pic->set($moder_id, $moder_name, $club_moder_flags, 'c' . $this->id)->set($moder_id, $moder_name, $moder_flags);
			$club_user_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			
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
			echo '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>
		function filterChanged()
		{
			goTo({results: $('#results').val(), filter: checkboxFilterFlags(), page: 0 });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Games'));

?>