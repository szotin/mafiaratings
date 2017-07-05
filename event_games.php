<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 20);

define('FILTER_CIVIL_WON', 1);
define('FILTER_MAFIA_WON', 2);
define('FILTER_TERMINATED', 4);
define('FILTER_PLAYING', 8);

class Page extends EventPageBase
{
	private $filter;

	protected function prepare()
	{
		parent::prepare();
		$this->filter = FILTER_CIVIL_WON | FILTER_MAFIA_WON;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = $_REQUEST['filter'];
			if (!is_numeric($this->filter))
			{
				$this->filter = 0;
				$this->filter |= isset($_REQUEST['civil']) ? FILTER_CIVIL_WON : 0;
				$this->filter |= isset($_REQUEST['mafia']) ? FILTER_MAFIA_WON : 0;
				$this->filter |= isset($_REQUEST['terminated']) ? FILTER_TERMINATED : 0;
				$this->filter |= isset($_REQUEST['playing']) ? FILTER_PLAYING : 0;
			}
		}
		$this->_title = get_label('[0]: games', $this->event->name);
	}
	
	protected function show_body()
	{
		global $_page;
	
		$condition = new SQL('g.event_id = ? AND g.result IN', $this->event->id);
		$delim = '(';
		if (($this->filter & FILTER_CIVIL_WON) != 0)
		{
			$condition->add($delim . '1');
			$delim = ', ';
		}
		if (($this->filter & FILTER_MAFIA_WON) != 0)
		{
			$condition->add($delim . '2');
			$delim = ', ';
		}
		if (($this->filter & FILTER_TERMINATED) != 0)
		{
			$condition->add($delim . '3');
			$delim = ', ';
		}
		if (($this->filter & FILTER_PLAYING) != 0)
		{
			$condition->add($delim . '0');
			$delim = ', ';
		}
		if ($delim == '(')
		{
			$condition->add($delim . '-1');
		}
		$condition->add(')');
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<form method="get" name="form" action="event_games.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<input type="hidden" name="filter" value="">';
		echo '<input type="hidden" name="id" value="' . $this->event->id . '">';
		echo '<input type="checkbox" name="civil" onClick="document.form.submit()"';
		if (($this->filter & FILTER_CIVIL_WON) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('civilians won').' ';
		echo '<input type="checkbox" name="mafia" onClick="document.form.submit()"';
		if (($this->filter & FILTER_MAFIA_WON) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('mafia won').' ';
		echo '<input type="checkbox" name="terminated" onClick="document.form.submit()"';
		if (($this->filter & FILTER_TERMINATED) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('terminated').' ';
		echo '<input type="checkbox" name="playing" onClick="document.form.submit()"';
		if (($this->filter & FILTER_PLAYING) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('still playing');
		echo '</td></tr></table></form>';
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="90">&nbsp;</td><td>'.get_label('Club name').'</td><td width="100">'.get_label('Moderator').'</td><td width="140">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="100">'.get_label('Result').'</td></tr>';
		$query = new DbQuery(
			'SELECT g.id, c.id, c.name, ct.timezone, m.id, m.name, g.start_time, g.end_time - g.start_time, g.result FROM games g' .
				' JOIN clubs c ON c.id = g.club_id' .
				' LEFT OUTER JOIN users m ON m.id = g.moderator_id' .
				' JOIN cities ct ON ct.id = c.city_id' .
				' WHERE ',
			$condition);
		$query->add(' ORDER BY g.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $timezone, $moder_id, $moder_name, $start, $duration, $game_result) = $row;
			
			echo '<tr><td class="dark"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
			echo '<td><a href="club_main.php?id=' . $club_id . '&bck=1">' . $club_name . '</a></td>';
			echo '<td><a href="user_info.php?id=' . $moder_id . '&bck=1">' . cut_long_name($moder_name, 40) . '</td>';
			echo '<td>' . format_date('M j Y, H:i', $start, $timezone) . '</td>';
			echo '<td>' . format_time($duration) . '</td>';
			
			echo '<td>';
			switch ($game_result)
			{
				case 0:
					echo get_label('still playing');
					break;
				case 1:
					echo get_label('civilians won');
					break;
				case 2:
					echo get_label('mafia won');
					break;
				case 3:
					echo get_label('terminated');
					break;
				default:
					echo get_label('invalid');
					break;
			}
			echo '</td>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Event games'), PERM_ALL);

?>