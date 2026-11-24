<?php

require_once 'include/league.php';
require_once 'include/club.php';
require_once 'include/city.php';
require_once 'include/country.php';
require_once 'include/image.php';
require_once 'include/user_location.php';
require_once 'include/checkbox_filter.php';
require_once 'include/club_cards.php';

define('FLAG_FILTER_CLOSED', 0x0001);
define('FLAG_FILTER_NOT_CLOSED', 0x0002);

class Page extends LeaguePageBase
{
	// Making a club that joins the league, beside its menu "≡". See PageBase::create_button().
	protected function create_action()
	{
		return PageBase::create_button('club', NULL, array('league_id' => $this->id));
	}

	private $filter;
	
	protected function prepare()
	{
		parent::prepare();
		
		// Closed clubs as a filter of three states, as clubs.php has it: all of them, only the
		// closed ones, or none of them - the last is where the list starts. The old address with
		// "flags" in it (1 - the active ones, 2 - the closed ones) still says the same.
		$this->filter = FLAG_FILTER_NOT_CLOSED;
		if (isset($_REQUEST['filter']))
		{
			$this->filter = (int)$_REQUEST['filter'];
		}
		else if (isset($_REQUEST['flags']))
		{
			$flags = (int)$_REQUEST['flags'];
			if (($flags & 3) == 3)
			{
				$this->filter = 0;
			}
			else if ($flags & 2)
			{
				$this->filter = FLAG_FILTER_CLOSED;
			}
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_lang;
		
		$condition = new SQL();
		if ($this->filter & FLAG_FILTER_CLOSED)
		{
			$condition->add(' AND (c.flags & ' . CLUB_FLAG_CLOSED . ') <> 0');
		}
		if ($this->filter & FLAG_FILTER_NOT_CLOSED)
		{
			$condition->add(' AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0');
		}
		
		$managed_clubs_not_in_league_count = 0;
		if ($_profile != NULL)
		{
			foreach ($_profile->clubs as $club)
			{
				if (($club->flags & USER_PERM_MANAGER) != 0 && ($club->club_flags & CLUB_FLAG_CLOSED) == 0)
				{
					++$managed_clubs_not_in_league_count;
				}
			}
		}
		
		$rows = array();
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, c.web_site, l.flags, ni.name, (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM league_clubs l' .
				' JOIN clubs c ON l.club_id = c.id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN cities i ON c.city_id = i.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' WHERE l.league_id = ?',
			$this->id, $condition);
		$query->add(' ORDER BY l.flags DESC, games DESC, nc.name');
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $url, $league_flags, $city_name) = $row;
			$is_club_manager = is_permitted(PERMISSION_CLUB_MANAGER, $id);
			if ($is_club_manager)
			{
				--$managed_clubs_not_in_league_count;
				$rows[] = $row;
			}
			else if ($this->is_manager || $league_flags == 0)
			{
				$rows[] = $row;
			}
		}
		$can_add = ($this->is_manager || $managed_clubs_not_in_league_count > 0) && ($this->filter & FLAG_FILTER_CLOSED) == 0;
		
		echo '<div class="toolbar"><div class="tb-line tb-start">';
		show_checkbox_filter(array(get_label('closed~plural')), $this->filter, 'filterClosed');
		if ($can_add)
		{
			echo '<a class="btn tb-create" href="#" onclick="mr.addLeagueClub(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add [0]', get_label('club')) . '</span></a>';
		}
		echo '</div></div>';
		
		// The tiles of clubs.php (include/club_cards.php). The state of a club in the league - it
		// wants to join, it is invited - is a chip under its city, and the buttons that answer it
		// are under that.
		$clubs = array();
		foreach ($rows as $row)
		{
			list ($id, $name, $flags, $url, $league_flags, $city_name, $games) = $row;
			$club = new stdClass();
			$club->id = $id;
			$club->name = $name;
			$club->flags = $flags;
			$club->city = $city_name;
			$club->games = $games;
			
			$state = '';
			$buttons = '';
			if ($this->is_manager)
			{
				if ($league_flags & LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED)
				{
					$buttons .= '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Decline request') . '"><img src="images/delete.png" border="0"></button>';
					$buttons .= '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Approve request') . '"><img src="images/accept.png" border="0"></button>';
					$state = get_label('Wants to join');
				}
				else
				{
					$buttons .= '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
					if ($league_flags & LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED)
					{
						if (is_permitted(PERMISSION_CLUB_MANAGER, $id))
						{
							$buttons .= '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Accept invitation') . '"><img src="images/accept.png" border="0"></button>';
						}
						$state = get_label('Invited');
					}
				}
			}
			else if (is_permitted(PERMISSION_CLUB_MANAGER, $id))
			{
				$buttons .= '<button class="icon" onclick="mr.removeLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Remove [0] from [1]', $name, $this->name) . '"><img src="images/delete.png" border="0"></button>';
				if ($league_flags & LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED)
				{
					$buttons .= '<button class="icon" onclick="mr.acceptLeagueClub(' . $this->id . ', ' . $id . ')" title="' . get_label('Accept invitation') . '"><img src="images/accept.png" border="0"></button>';
					$state = get_label('Invited');
				}
				else if ($league_flags & LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED)
				{
					$state = get_label('Wants to join');
				}
			}
			if ($state != '')
			{
				$club->extra = '<span class="tile-meta"><span class="chip pending">' . $state . '</span></span>';
			}
			$club->buttons = $buttons;
			$clubs[] = $club;
		}
		club_tiles_show($clubs, get_label('No clubs found.'));
	}
	
	protected function js()
	{
		parent::js();
?>
		function filterClosed()
		{
			goTo({ filter: checkboxFilterFlags(), flags: undefined, page: undefined });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('League clubs'));

?>