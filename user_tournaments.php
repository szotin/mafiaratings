<?php

require_once 'include/user.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/pages.php';
require_once 'include/event.php';
require_once 'include/ccc_filter.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);
define('ETYPE_ALL', 0);

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
		
		echo '<table class="transp" width="100%"><tr><td>';
		$this->ccc_filter->show('onCCC', get_label('Filter events by club, city, or country.'));
		echo '</td></tr></table>';
		
		$condition = new SQL(
			' FROM tournaments t' .
			' JOIN games g ON g.tournament_id = t.id' .
			' JOIN players p ON p.game_id = g.id' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN clubs c ON t.club_id = c.id' .
			' JOIN cities ct ON ct.id = a.city_id' . 
			' WHERE p.user_id = ? AND g.canceled = FALSE AND g.result > 0 AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0', $this->id);
		$ccc_id = $this->ccc_filter->get_id();
		switch($this->ccc_filter->get_type())
		{
		case CCCF_CLUB:
			if ($ccc_id > 0)
			{
				$condition->add(' AND t.club_id = ?', $ccc_id);
			}
			else if ($ccc_id == 0 && $_profile != NULL)
			{
				$condition->add(' AND t.club_id IN (' . $_profile->get_comma_sep_clubs() . ')');
			}
			break;
		case CCCF_CITY:
			$condition->add(' AND a.city_id IN (SELECT id FROM cities WHERE id = ? OR area_id = ?)', $ccc_id, $ccc_id);
			break;
		case CCCF_COUNTRY:
			$condition->add(' AND ct.country_id = ?', $ccc_id);
			break;
		}
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(DISTINCT t.id)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$query = new DbQuery(
			'SELECT t.id, t.name, t.flags, t.start_time, ct.timezone, c.id, c.name, c.flags, t.langs, a.id, a.address, a.flags, SUM(p.rating_earned), COUNT(g.id), SUM(p.won)',
			$condition);
		$query->add(' GROUP BY t.id ORDER BY t.start_time DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker">';
		echo '<td colspan="3">' . get_label('Tournament') . '</td>';
		echo '<td width="60" align="center">'.get_label('Rating earned').'</td>';
		echo '<td width="60" align="center">'.get_label('Games played').'</td>';
		echo '<td width="60" align="center">'.get_label('Wins').'</td>';
		echo '<td width="60" align="center">'.get_label('Winning %').'</td>';
		echo '<td width="60" align="center">'.get_label('Rating per game').'</td></tr>';

		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		while ($row = $query->next())
		{
			list ($tournament_id, $tournament_name, $tournament_flags, $tournament_time, $timezone, $club_id, $club_name, $club_flags, $languages, $address_id, $address, $address_flags, $rating, $games_played, $games_won) = $row;
			
			echo '<tr>';
			
			echo '<td width="50" class="dark">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, true, 50);
			echo '</td>';
			echo '<td width="50" class="dark" align="center">';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, true, 40);
			echo '</td>';
			echo '<td>' . $tournament_name . '<br><b>' . format_date('l, F d, Y', $tournament_time, $timezone) . '</b></td>';
			
			echo '<td align="center" class="dark">' . number_format($rating, 2) . '</td>';
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
		var code = "<?php echo is_null($this->ccc_filter) ? '' : $this->ccc_filter->get_code(); ?>";
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
$page->run(get_label('Tournaments'));

?>