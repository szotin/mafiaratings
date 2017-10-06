<?php

require_once 'include/general_page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/ccc_filter.php';
require_once 'include/user.php';

define("PAGE_SIZE", 20);

class Page extends GeneralPageBase
{
	private $result_filter;
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
	}

	protected function show_body()
	{
		global $_page, $_profile;
		
		$condition = new SQL();
		if ($this->result_filter < 0)
		{
			$condition->add(' WHERE g.result <> 0');
		}
		else if ($this->result_filter == 3)
		{
			$condition->add(' WHERE g.video IS NOT NULL');
		}
		else
		{
			$condition->add(' WHERE g.result = ?', $this->result_filter);
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
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td'; 
		if ($this->is_admin)
		{
			echo ' colspan="2"';
		}
		echo '>&nbsp;</td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Moderator').'</td><td align="left">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td><td width="60">'.get_label('Video').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, c.id, c.name, c.flags, ct.timezone, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $club_flags, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, $game_result, $video) = $row;
			echo '<tr align="center">';
			if ($this->is_admin)
			{
				echo '<td class="dark" width="90">';
				echo '<button class="icon" onclick="mr.deleteGame(' . $game_id . ', \'' . get_label('Are you sure you want to delete the game [0]?', $game_id) . '\')" title="' . get_label('Delete game [0]', $game_id) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editGame(' . $game_id . ')" title="' . get_label('Edit game [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
				if ($video == NULL)
				{
					echo '<button class="icon" onclick="mr.setGameVideo(' . $game_id . ')" title="' . get_label('Add game [0] video', $game_id) . '"><img src="images/film-add.png" border="0"></button>';
				}
				else
				{
					echo '<button class="icon" onclick="mr.removeGameVideo(' . $game_id . ')" title="' . get_label('Remove game [0] video', $game_id) . '"><img src="images/film-delete.png" border="0"></button>';
				}
				echo '</td>';
			}
			
			echo '<td class="dark" width="90"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
			echo '<td>';
			show_club_pic($club_id, $club_name, $club_flags, ICONS_DIR, 48, 48);
			echo '</td>';
			echo '<td>';
			show_user_pic($moder_id, $moder_name, $moder_flags, ICONS_DIR, 32, 32, ' style="opacity: 0.8;"');
			echo '</td>';
			echo '<td align="left">' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
			echo '<td>' . format_time($duration) . '</td>';
			
			echo '<td>';
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
			echo '</td><td>';
			if ($video != NULL)
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
		show_option(1, $this->result_filter, get_label('Games won by town'));
		show_option(2, $this->result_filter, get_label('Games won by mafia'));
		show_option(3, $this->result_filter, get_label('Games with video'));
		if ($this->is_admin)
		{
			show_option(0, $this->result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
	}
	
	protected function get_filter_js()
	{
		return '+ "&results=" + $("#results").val()';
	}
}

$page = new Page();
$page->run(get_label('Games list'), PERM_ALL);

?>