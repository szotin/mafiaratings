<?php

require_once 'include/series.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';
require_once 'include/checkbox_filter.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

define('FLAG_FILTER_VIDEO', 0x0001);
define('FLAG_FILTER_NO_VIDEO', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NO_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NO_CANCELED);

class Page extends SeriesPageBase
{
	private $result_filter;
	private $is_admin;

	protected function prepare()
	{
		global $_profile;
		
		parent::prepare();
		
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
		
		$this->flag_filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$this->flag_filter = (int)$_REQUEST['filter'];
		}
	}

	protected function show_body()
	{
		global $_page, $_profile, $_lang;
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		
		$subseries_csv = get_subseries_csv($this->id);
		
		$condition = new SQL(' WHERE st.series_id IN ('.$subseries_csv.')');
		if ($this->result_filter < 0)
		{
			$condition->add(' AND g.result <> 0');
		}
		else
		{
			$condition->add(' AND g.result = ?', $this->result_filter);
		}
		
		if ($this->flag_filter & FLAG_FILTER_VIDEO)
		{
			$condition->add(' AND g.video_id IS NOT NULL');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_VIDEO)
		{
			$condition->add(' AND g.video_id IS NULL');
		}
		if ($this->flag_filter & FLAG_FILTER_RATING)
		{
			$condition->add(' AND g.is_rating <> 0');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_RATING)
		{
			$condition->add(' AND g.is_rating = 0');
		}
		if ($this->flag_filter & FLAG_FILTER_CANCELED)
		{
			$condition->add(' AND g.is_canceled <> 0');
		}
		if ($this->flag_filter & FLAG_FILTER_NO_CANCELED)
		{
			$condition->add(' AND g.is_canceled = 0');
		}
		
		echo '<p><table class="transp" width="100%">';
		echo '<tr><td>';
		$ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$ccc_filter->show(get_label('Filter [0] by club/city/country.', get_label('games')));
		echo ' <select id="results" onChange="filterResults()" title="' . get_label('Filter games by result.') . '">';
		show_option(-1, $this->result_filter, get_label('All games'));
		show_option(1, $this->result_filter, get_label('Town wins'));
		show_option(2, $this->result_filter, get_label('Mafia wins'));
		if ($this->is_admin)
		{
			show_option(0, $this->result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
		show_checkbox_filter(array(get_label('with video'), get_label('rating games'), get_label('canceled games')), $this->flag_filter);
		echo '</td></tr></table></p>';
		
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
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN series_tournaments st ON st.tournament_id = g.tournament_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$is_user = is_permitted(PERMISSION_USER);
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="48"></td><td'; 
		if ($is_user)
		{
			echo ' colspan="3"';
		}
		else
		{
			echo ' colspan="2"';
		}
		echo '>&nbsp;</td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Tournament').'</td><td width="48">'.get_label('Referee').'</td><td width="48">'.get_label('Result').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, c.id, c.name, c.flags, e.id, e.name, e.flags, t.id, t.name, t.flags, ct.timezone, m.id, nm.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video_id, g.is_rating, g.is_canceled, a.id, a.name, a.flags FROM games g' .
				' JOIN series_tournaments st ON st.tournament_id = g.tournament_id' .
				' JOIN clubs c ON c.id = g.club_id' .
				' JOIN events e ON e.id = g.event_id' .
				' JOIN addresses a ON a.id = e.address_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN names nm ON nm.id = m.name_id AND (nm.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN tournaments t ON t.id = g.tournament_id' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.end_time DESC, g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		$num = $_page * PAGE_SIZE;
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $tournament_id, $tournament_name, $tournament_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, $game_result, $video_id, $is_rating, $is_canceled, $address_id, $address_name, $address_flags) = $row;
			
			echo '<tr align="center"';
			if ($is_canceled || !$is_rating)
			{
				echo ' class="dark"';
			}
			echo '>';
			echo '<td>' . ++$num . '</td>';
			
			if ($this->is_admin)
			{
				echo '<td class="dark" width="90">';
				echo '<button class="icon" onclick="mr.deleteGame(' . $game_id . ', \'' . get_label('Are you sure you want to delete the game [0]?', $game_id) . '\')" title="' . get_label('Delete game [0]', $game_id) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editGame(' . $game_id . ')" title="' . get_label('Edit game json [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
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
			echo '<a href="view_game.php?id=' . $game_id . '&bck=1"><b>' . get_label('Game #[0]', $game_id) . '</b><br>';
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
			$club_pic->
				set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			
			echo '<td>';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			
			echo '<td>';
			$this->user_pic->set($moder_id, $moder_name, $moder_flags);
			$this->user_pic->show(ICONS_DIR, true, 48);
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
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterResults()
		{
			goTo({results: $("#results").val()});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Games list'));

?>