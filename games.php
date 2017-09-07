<?php

require_once 'include/general_page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/ccc_filter.php';

define("PAGE_SIZE", 20);

define('FILTER_CIVIL_WON', 1);
define('FILTER_MAFIA_WON', 2);

class Page extends GeneralPageBase
{
	private $filter;

	protected function prepare()
	{
		parent::prepare();
		
		$this->filter = FILTER_CIVIL_WON | FILTER_MAFIA_WON;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = $_REQUEST['filter'];
		}
	}

	protected function show_body()
	{
		global $_page, $_profile;
		
		$condition = new SQL('g.result IN');
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
		if ($delim == '(')
		{
			$condition->add($delim . '-1');
		}
		$condition->add(')');
		
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
		
		list ($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g WHERE ', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="90">&nbsp;</td><td>'.get_label('Club').'</td><td width="100">'.get_label('Moderator').'</td><td width="140">'.get_label('Time').'</td><td width="60">'.get_label('Duration').'</td><td width="100">'.get_label('Result').'</td></tr>';
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
				default:
					echo get_label('invalid');
					break;
			}
			echo '</td>';
		}
		echo '</table>';
	}
	
	protected function show_filter_fields()
	{
		echo '<input type="checkbox" id="civil" onClick="filter()"';
		if (($this->filter & FILTER_CIVIL_WON) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('civilians won').' ';
		echo '<input type="checkbox" id="mafia" onClick="filter()"';
		if (($this->filter & FILTER_MAFIA_WON) != 0)
		{
			echo ' checked';
		}
		echo '>'.get_label('mafia won');
	}
	
	protected function get_filter_js()
	{
		return '+ "&filter=" + getFilter()';
	}
	
	protected function js()
	{
		parent::js();
?>
		function getFilter()
		{
			var filter = 0;
			if ($('#civil').attr('checked')) filter |= <?php echo FILTER_CIVIL_WON; ?>;
			if ($('#mafia').attr('checked')) filter |= <?php echo FILTER_MAFIA_WON; ?>;
			return filter;
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Games list'), PERM_ALL);

?>