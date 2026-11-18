<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/tournament.php';
require_once 'include/address.php';
require_once 'include/tournament_cards.php';

define('ROW_COUNT', DEFAULT_ROW_COUNT);
define('COLUMN_COUNT', DEFAULT_COLUMN_COUNT);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_page, $_lang, $_profile;
		
		$is_manager = is_permitted(PERMISSION_CLUB_MANAGER, $this->id);
		$page_size = ROW_COUNT * COLUMN_COUNT;
		
		$condition = new SQL('t.club_id = ? AND UNIX_TIMESTAMP() <= t.start_time + t.duration', $this->id);
		list ($count) = Db::record(get_label('tournament'), 'SELECT count(*) FROM tournaments t WHERE ', $condition);
		
		// The toolbar of tournaments.php: here only the button that makes a new tournament.
		if ($is_manager)
		{
			echo '<div class="toolbar"><div class="tb-line tb-start">';
			echo '<a class="btn tb-create" href="#" onclick="mr.createTournament(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Create [0]', get_label('tournament')) . '</span></a>';
			echo '</div></div>';
		}
		show_pages_navigation($page_size, $count);
		
		$query = new DbQuery('SELECT t.id, nt.name, t.start_time, t.duration, t.flags, t.num_players, nct.name, ct.timezone, a.address, na.name FROM tournaments t');
		$query->add(
			' JOIN names nt ON nt.id = t.name_id AND (nt.langs & '.$_lang.') <> 0' .
			' JOIN addresses a ON t.address_id = a.id' .
			' JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0' .
			' JOIN cities ct ON a.city_id = ct.id' .
			' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0 WHERE ',
			$condition);
		$query->add(' ORDER BY t.start_time LIMIT ' . ($_page * $page_size) . ',' . $page_size);
		
		$tournaments = array();
		while ($row = $query->next())
		{
			$tournament = new stdClass();
			list ($tournament->id, $tournament->name, $tournament->time, $tournament->duration, $tournament->flags, $tournament->num_players, $tournament->city, $tournament->timezone, $addr, $addr_name) = $row;
			if ($tournament->name == $addr_name)
			{
				$tournament->name = $addr;
			}
			$tournament->club_id = $this->id;
			$tournament->club_name = $this->name;
			$tournament->club_flags = $this->flags;
			$tournament->games_count = 0;
			$tournament->videos_count = 0;
			if ($_profile != NULL)
			{
				$id = $tournament->id;
				$start_time = $tournament->time;
				$duration = $tournament->duration;
				$flags = $tournament->flags;
				$club_id = $this->id;
				$club_flags = $this->flags;
				$tournament->buttons = PageBase::capture(function() use ($id, $start_time, $duration, $flags, $club_id, $club_flags)
				{
					show_tournament_buttons($id, $start_time, $duration, $flags, $club_id, $club_flags);
				});
			}
			$tournaments[] = $tournament;
		}
		
		// The cards of tournaments.php, the future ones: see include/tournament_cards.php.
		tournament_cards_load($tournaments, true);
		tournament_cards_show($tournaments, true, get_label('No tournaments found.'));
		show_pages_navigation($page_size, $count);
	}
}
$page = new Page();
$page->run(get_label('Upcoming tournaments'));

?>