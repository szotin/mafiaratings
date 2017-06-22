<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';

define("PAGE_SIZE",15);

class Page extends UserPageBase
{
	private $ccc_filter;

	protected function prepare()
	{
		parent::prepare();
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$this->_title = get_label('[0] events', $this->title);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		$show_empty = isset($_REQUEST['emp']);
		
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		$this->ccc_filter->show('onCCC');
		echo '</td><td align="right">';
		echo '<input type="checkbox" id="emp"';
		if ($show_empty)
		{
			echo ' checked';
		}
		echo ' onclick="filter()"> ' . get_label('Show events with no games');
		echo '</td></tr></table>';
		
		$condition = new SQL(
			' FROM registrations r' .
			' JOIN events e ON r.event_id = e.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' . 
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE r.user_id = ? AND e.start_time < UNIX_TIMESTAMP()',
			$this->id);
			
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND e.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND e.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR near_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
			
		if (!$show_empty)
		{
			$condition->add(' AND EXISTS (SELECT g.id FROM games g WHERE g.event_id = e.id)');
		}
		
		list ($count) = Db::record(get_label('event'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, e.languages, a.id, a.address, a.flags,' .
			' (SELECT count(*) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = r.user_id AND g.event_id = e.id), ' .
			' (SELECT SUM(p.rating) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = r.user_id AND g.event_id = e.id), ' .
			' (SELECT SUM(p.won) FROM players p JOIN games g ON p.game_id = g.id WHERE p.user_id = r.user_id AND g.event_id = e.id)', 
			$condition);
		$query->add(' ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td>' . get_label('Address') . '</td>';
		echo '<td width="60" align="center">'.get_label('Rating').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Games won').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td></tr>';
		
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $club_id, $club_name, $club_flags, $languages, $address_id, $address, $address_flags, $games_played, $rating, $games_won) = $row;
			
			echo '<tr>';
			
			echo '<td width="50" class="dark"><a href="event_standings.php?bck=1&id=' . $event_id . '">';
			show_event_pic($event_id, $event_flags, $club_id, $club_flags, ICONS_DIR, 50, 50, false);
			echo '</a></td>';
			echo '<td width="180">' . $event_name . '<br><b>' . format_date('l, F d, Y', $event_time, $timezone) . '</b></td>';
			
			echo '<td>' . $address . '</td>';
			
			echo '<td align="center" class="dark">' . $rating . '</td>';
			echo '<td align="center">' . $games_played . '</td>';
			echo '<td align="center">' . $games_won . '</td>';
			if ($games_played != 0)
			{
				echo '<td align="center">' . number_format(($games_won*100.0)/$games_played, 1) . '%</td>';
				echo '<td align="center">' . number_format($rating/$games_played, 2) . '</td>';
			}
			else
			{
				echo '<td align="center">&nbsp;</td><td width="60">&nbsp;</td>';
			}
			
			echo '</tr>';
		}
		echo '</table>';
	}
	
	function js()
	{
?>
		var code = "<?php echo $this->ccc_filter->get_code(); ?>";
		function onCCC(_code)
		{
			code = _code;
			filter();
		}

		function filter()
		{
			var loc = "?id=<?php echo $this->id; ?>&ccc=" + code;
			if ($("#emp").attr('checked'))
			{
				loc += "&emp=";
			}
			window.location.replace(loc);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Events history'), PERM_ALL);

?>