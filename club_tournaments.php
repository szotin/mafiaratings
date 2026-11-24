<?php

require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/tournament.php';
require_once 'include/series.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';
require_once 'include/tournament_cards.php';

define("CUT_NAME",45);
define('PAGE_SIZE', TOURNAMENTS_PAGE_SIZE);

define('FLAG_FILTER_VIDEOS', 0x0001);
define('FLAG_FILTER_NO_VIDEOS', 0x0002);
define('FLAG_FILTER_EMPTY', 0x0004);
define('FLAG_FILTER_NOT_EMPTY', 0x0008);
define('FLAG_FILTER_CANCELED', 0x0010);
define('FLAG_FILTER_NOT_CANCELED', 0x0020);

define('FLAG_FILTER_DEFAULT', FLAG_FILTER_NOT_CANCELED | FLAG_FILTER_NOT_EMPTY);

class Page extends ClubPageBase
{
	// Making one of the club, beside its menu "≡". See PageBase::create_button().
	protected function create_action()
	{
		return PageBase::create_button('tournament', 'c' . $this->id);
	}

	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		$future = false;
		if (isset($_REQUEST['future']))
		{
			$future = ((int)$_REQUEST['future'] > 0);
		}
		
		$condition = new SQL(
			' FROM tournaments t ' .
				' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
				' JOIN addresses a ON t.address_id = a.id' .
				' JOIN cities ct ON ct.id = a.city_id' .
				' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0' .
				' WHERE t.club_id = ?',
			$this->id);
		if ($future)
		{
			$condition->add(' AND t.start_time + t.duration >= UNIX_TIMESTAMP()');
		}
		else
		{
			$condition->add(' AND t.start_time < UNIX_TIMESTAMP()');
			
			if ($filter & FLAG_FILTER_VIDEOS)
			{
				$condition->add(' AND EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_NO_VIDEOS)
			{
				$condition->add(' AND NOT EXISTS (SELECT v.id FROM videos v WHERE v.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_EMPTY)
			{
				$condition->add(' AND NOT EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) AND NOT EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id)');
			}
			if ($filter & FLAG_FILTER_NOT_EMPTY)
			{
				$condition->add(' AND (EXISTS (SELECT tp.user_id FROM tournament_places tp WHERE tp.tournament_id = t.id) OR EXISTS (SELECT g.id FROM games g WHERE g.tournament_id = t.id))');
			}
			if ($filter & FLAG_FILTER_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') <> 0');
			}
			if ($filter & FLAG_FILTER_NOT_CANCELED)
			{
				$condition->add(' AND (t.flags & ' . TOURNAMENT_FLAG_CANCELED . ') = 0');
			}
		}
		
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$condition->add(' AND t.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$condition->add(' AND t.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
			
		// The toolbar and the cards of tournaments.php: see include/tournament_cards.php.
		echo '<div class="toolbar">';
		echo card_seg(array(
			array(card_url(array('future' => NULL, 'page' => NULL)), get_label('Past'), !$future),
			array(card_url(array('future' => 1, 'page' => NULL)), get_label('Future'), $future)), get_label('Tournaments'));
		if (!$future)
		{
			echo '<div class="tb-dates">';
			show_date_filter();
			echo '</div>';
			show_checkbox_filter(array(get_label('with video'), get_label('unplayed'), get_label('canceled')), $filter);
		}
		echo '</div>';
		
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*)', $condition);
		show_pages_navigation(PAGE_SIZE, $count);

		if ($future)
		{
			$order_by = ' ORDER BY t.start_time + t.duration, t.id';
		}
		else
		{
			$order_by = ' ORDER BY t.start_time DESC, t.id DESC';
		}
		$query = new DbQuery(
			'SELECT t.id, nt.name, t.flags, t.start_time, t.duration, ct.timezone, t.num_players, nct.name,' .
			' (SELECT count(*) FROM games _g JOIN events _e ON _e.id = _g.event_id WHERE _e.tournament_id = t.id AND (_g.flags & '.GAME_FLAG_CANCELED.') = 0) as games,' .
			' (SELECT count(*) FROM videos WHERE tournament_id = t.id) as videos',
			$condition);
		$query->add($order_by);
		$query->add(' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		$tournaments = array();
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list (
				$tournament->id, $tournament->name, $tournament->flags, $tournament->time, $tournament->duration, $tournament->timezone, 
				$tournament->num_players, $tournament->city, $tournament->games_count, $tournament->videos_count) = $row;
			$tournament->club_id = $this->id;
			$tournament->club_name = $this->name;
			$tournament->club_flags = $this->flags;
			$tournaments[] = $tournament;
		}
		tournament_cards_load($tournaments, $future);
		tournament_cards_show($tournaments, $future, get_label('No tournaments found.'));
		show_pages_navigation(PAGE_SIZE, $count);
	}
}

$page = new Page();
$page->run(get_label('Tournaments'));

?>