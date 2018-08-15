<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';

define("PAGE_SIZE",15);

define('ETYPE_TOURNAMENT', 0);
define('ETYPE_ALL', 1);

class Page extends UserPageBase
{
	private $ccc_filter;
	private $events_type;

	protected function prepare()
	{
		parent::prepare();
		$this->ccc_filter = new CCCFilter('ccc', CCCF_CLUB . CCCF_ALL);
		$this->events_type = ETYPE_ALL;
		if (isset($_REQUEST['etype']))
		{
			$this->events_type = (int)$_REQUEST['etype'];
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<table class="transp" width="100%"><tr><td>';
		$this->ccc_filter->show('onCCC', get_label('Filter events by club, city, or country.'));
		echo ' <select id="etype" onchange="filter()">';
		show_option(ETYPE_TOURNAMENT, $this->events_type, get_label('Tournaments'));
		show_option(ETYPE_ALL, $this->events_type, get_label('Events'));
		echo '</select>';
		echo '</td></tr></table>';
		
		$condition = new SQL(
			' FROM events e' .
			' JOIN games g ON g.event_id = e.id' .
			' JOIN players p ON p.game_id = g.id' .
			' JOIN addresses a ON e.address_id = a.id' .
			' JOIN clubs c ON e.club_id = c.id' . 
			' JOIN cities ct ON ct.id = c.city_id' .
			' WHERE p.user_id = ?', $this->id);
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
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		switch ($this->events_type)
		{
			case ETYPE_TOURNAMENT:
				$condition->add(' AND (e.flags & ' . EVENT_FLAG_TOURNAMENT . ') <> 0');
				break;
			default:
				break;
		}
			
		list ($count) = Db::record(get_label('event'), 'SELECT count(DISTINCT e.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT e.id, e.name, e.flags, e.start_time, ct.timezone, c.id, c.name, c.flags, e.languages, a.id, a.address, a.flags, SUM(p.rating_earned), COUNT(g.id), SUM(p.won)',
			$condition);
		$query->add(' GROUP BY e.id ORDER BY e.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="2">' . get_label('Event') . '</td>';
		echo '<td width="60" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Victories').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td></tr>';
		
		while ($row = $query->next())
		{
			list ($event_id, $event_name, $event_flags, $event_time, $timezone, $club_id, $club_name, $club_flags, $languages, $address_id, $address, $address_flags, $rating, $games_played, $games_won) = $row;
			
			echo '<tr>';
			
			echo '<td width="50" class="dark"><a href="event_standings.php?bck=1&id=' . $event_id . '">';
			show_event_pic($event_id, $event_name, $event_flags, $club_id, $club_name, $club_flags, ICONS_DIR, 50, 50, false);
			echo '</a></td>';
			echo '<td>' . $event_name . '<br><b>' . format_date('l, F d, Y', $event_time, $timezone) . '</b></td>';
			
			echo '<td align="center" class="dark">' . number_format($rating) . '</td>';
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
			var loc = "?id=<?php echo $this->id; ?>&ccc=" + code + "&etype=" + $("#etype").val();
			window.location.replace(loc);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Events'));

?>