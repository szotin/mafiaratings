<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 20);

class Page extends EventPageBase
{
	private $result_filter;

	protected function prepare()
	{
		parent::prepare();
		$this->result_filter = -1;
		if (isset($_REQUEST['results']))
		{
			$this->result_filter = (int)$_REQUEST['results'];
			if ($this->result_filter == 0 && !$this->is_manager)
			{
				$this->result_filter = -1;
			}
		}
		$this->_title = get_label('[0]: games', $this->event->name);
	}
	
	protected function show_body()
	{
		global $_page;
	
		$condition = new SQL(' WHERE g.event_id = ?', $this->event->id);
		if ($this->result_filter >= 0)
		{
			$condition->add(' AND g.result = ?', $this->result_filter);
		}
		else
		{
			$condition->add(' AND g.result <> 0');
		}
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<form method="get" name="form" action="event_games.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<select name="results" onChange="document.form.submit()">';
		show_option(-1, $this->result_filter, get_label('All games'));
		show_option(1, $this->result_filter, get_label('Games won by town'));
		show_option(2, $this->result_filter, get_label('Games won by mafia'));
		if ($this->is_manager)
		{
			show_option(0, $this->result_filter, get_label('Unfinished games'));
		}
		echo '</select>';
		echo '</td></tr></table></form>';
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td';
		if ($this->is_manager)
		{
			echo ' colspan="2"';
		}
		echo '>&nbsp;</td><td width="48">'.get_label('Moderator').'</td><td>'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="60">'.get_label('Result').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, ct.timezone, m.id, m.name, m.flags, g.start_time, g.end_time - g.start_time, g.result FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN cities ct ON ct.id = c.city_id',
			$condition);
		$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $timezone, $moder_id, $moder_name, $moder_flags, $start, $duration, $game_result) = $row;
			
			if ($this->is_manager)
			{
				echo '<td class="dark" width="60">';
				echo '<button class="icon" onclick="mr.deleteGame(' . $game_id . ', \'' . get_label('Are you sure you want to delete the game [0]?', $game_id) . '\')" title="' . get_label('Delete game [0]', $game_id) . '"><img src="images/delete.png" border="0"></button>';
				echo '<button class="icon" onclick="mr.editGame(' . $game_id . ')" title="' . get_label('Edit game [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
				echo '</td>';
			}
			
			echo '<td class="dark" width="90" align="center"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
			echo '<td align="center">';
			show_user_pic($moder_id, $moder_name, $moder_flags, ICONS_DIR, 32, 32, ' style="opacity: 0.8;"');
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
			echo '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Event games'), PERM_ALL);

?>