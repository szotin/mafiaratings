<?php
require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/image.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/event.php';

define("PAGE_SIZE", 20);

class Page extends AddressPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->_title = get_label('[0]: games', $this->name);
	}
	
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
		
		echo '<form method="get" name="form" action="address_games.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<select name="results" onChange="document.form.submit()">';
		show_option(-1, $result_filter, get_label('All games'));
		show_option(1, $result_filter, get_label('Town victories'));
		show_option(2, $result_filter, get_label('Mafia victories'));
		show_option(3, $result_filter, get_label('Games with video'));
		if ($this->is_manager)
		{
			show_option(0, $result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
		echo '</td></tr></table></form>';

		$condition = new SQL(' WHERE e.address_id = ?', $this->id);
		if ($result_filter < 0)
		{
			$condition->add(' AND g.result <> 0');
		}
		else if ($result_filter == 3)
		{
			$condition->add(' AND g.video IS NOT NULL');
		}
		else
		{
			$condition->add(' AND g.result = ?', $result_filter);
		}
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN events e ON e.id = g.event_id', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td';
		if ($this->is_manager)
		{
			echo ' colspan="2"';
		}
		echo '>&nbsp;</td><td width="48">'.get_label('Event').'</td><td width="48">'.get_label('Moderator').'</td><td align="left">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td><td width="60">'.get_label('Video').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result, g.video, e.id, e.name, e.flags FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' LEFT OUTER JOIN users m ON m.id = g.moderator_id',
			$condition);
		$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $moder_id, $moder_name, $moder_flags, $start, $duration, $game_result, $video, $event_id, $event_name, $event_flags) = $row;
			
			echo '<tr align="center">';
			if ($this->is_manager)
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
			show_event_pic($event_id, $event_name, $event_flags, $this->id, $this->name, $this->flags, ICONS_DIR, 48, 48);
			echo '</td><td>';
			show_user_pic($moder_id, $moder_name, $moder_flags, ICONS_DIR, 32, 32, ' style="opacity: 0.8;"');
			echo '</td>';
			echo '<td align="left">' . format_date('M j Y, H:i', $start, $this->timezone) . '</td>';
			echo '<td>' . format_time($duration) . '</td>';
			
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
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Address games'), PERM_ALL);

?>