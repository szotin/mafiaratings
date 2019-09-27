<?php

require_once 'include/general_page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';

define("PAGE_SIZE", 20);

class Page extends GeneralPageBase
{
	private $result_filter;
	private $with_video;
	private $is_admin;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
		$this->ccc_title = get_label('Filter games by club, city, or country.');
		$this->is_admin = ($_profile != NULL && $_profile->is_admin());
		$this->result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$this->result_filter = (int)$_REQUEST['results'];
			if ($this->result_filter == 0 && !$this->is_admin)
			{
				$this->result_filter = -1;
			}
		}
		
		$this->with_video = isset($_REQUEST['video']);
	}

	protected function show_body()
	{
		global $_page, $_profile;
		
		$condition = new SQL();
		if ($this->result_filter < 0)
		{
			$condition->add(' WHERE g.result <> 0');
		}
		else
		{
			$condition->add(' WHERE g.result = ?', $this->result_filter);
		}
		
		if ($this->with_video)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
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
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$event_pic = new Picture(EVENT_PICTURE, new Picture(TOURNAMENT_PICTURE, $this->club_pic));
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
		echo '>&nbsp;</td><td width="48">'.get_label('Event').'</td><td width="120">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td><td width="60">'.get_label('Video').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, c.id, c.name, c.flags, e.id, e.name, e.flags, t.id, t.name, t.flags, ct.timezone, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.canceled FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $tour_id, $tour_name, $tour_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, $game_result, $video_id, $is_canceled) = $row;
			
			echo '<tr align="center"';
			if ($is_canceled)
			{
				echo ' class="dark"';
			}
			echo '>';
			
			if ($this->is_admin)
			{
				echo '<td class="dark" width="120">';
				echo '<button class="icon" onclick="mr.gotoObjections(' . $game_id . ')" title="' . get_label('File an objection to the game [0] results.', $game_id) . '"><img src="images/objection.png" border="0"></button>';
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
				echo '<button class="icon" onclick="mr.gotoObjections(' . $game_id . ')" title="' . get_label('File an objection to the game [0] results.', $game_id) . '"><img src="images/objection.png" border="0"></button>';
				echo '</td>';
			}
			
			if ($is_canceled)
			{
				echo '<td align="left"><s>';
			}
			else
			{
				echo '<td align="left" colspan="2">';
			}
			echo '<a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a>';
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
			if ($video_id != NULL)
			{
				echo '<button class="icon" onclick="mr.watchGameVideo(' . $game_id . ')" title="' . get_label('Watch game [0] video', $game_id) . '"><img src="images/film.png" border="0"></button>';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}
	
	protected function show_filter_fields()
	{
		echo '<select id="results" onChange="filter()" title="' . get_label('Filter games by result.') . '">';
		show_option(-1, $this->result_filter, get_label('All games'));
		show_option(1, $this->result_filter, get_label('Town wins'));
		show_option(2, $this->result_filter, get_label('Mafia wins'));
		if ($this->is_admin)
		{
			show_option(0, $this->result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
		echo ' <input type="checkbox" id="video" onclick="filter()"';
		if ($this->with_video)
		{
			echo ' checked';
		}
		echo '> ' . get_label('show only games with video');
	}
	
	protected function get_filter_js()
	{
		return '+ "&results=" + $("#results").val() + ($("#video").attr("checked") ? "&video" : "")';
	}
}

$page = new Page();
$page->run(get_label('Games list'));

?>