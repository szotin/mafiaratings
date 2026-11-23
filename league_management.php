<?php

require_once 'include/league.php';
require_once 'include/details_tab.php';
require_once 'include/club_cards.php';

define('LEAGUE_TAB_DETAILS', 0);
define('LEAGUE_TAB_RULES', 1);
define('LEAGUE_TAB_CLUBS', 2);

// The page a league is managed on, as steps (see include/details_tab.php): its fields, the ones of
// the dialog that edited it; the rules it allows; its clubs. Opened by the first item of its menu
// "≡". A league just made in create.php comes here on its rules, and goes on to its clubs.
class Page extends LeaguePageBase
{
	private $tab;

	protected function show_body()
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
		$this->tab = isset($_REQUEST['tab']) ? (int)$_REQUEST['tab'] : LEAGUE_TAB_DETAILS;
		if ($this->tab != LEAGUE_TAB_RULES && $this->tab != LEAGUE_TAB_CLUBS)
		{
			$this->tab = LEAGUE_TAB_DETAILS;
		}

		wizard_begin(array(
			array(details_tab_name(), 'goTo({tab:' . LEAGUE_TAB_DETAILS . '})'),
			array(get_label('Rules'), 'goTo({tab:' . LEAGUE_TAB_RULES . '})'),
			array(get_label('Clubs'), 'goTo({tab:' . LEAGUE_TAB_CLUBS . '})')), $this->tab);
		switch ($this->tab)
		{
			case LEAGUE_TAB_RULES:
				// The rules of a league are a filter: for each rule, the options a club of the league
				// may play by. Edited in place by the editor of the dialog that edits them
				// (form/league_rules_edit.php).
				echo '<p>' . get_label('Choose the rules the clubs of your league may play by. For every rule, tick the options that are allowed: a rule with one option left is the same in every club, a rule with several is for each club to choose. A club that joins the league can keep only what is allowed here.') . '</p>';
				show_details_form('form/league_rules_edit.php?league_id=' . $this->id);
				wizard_end(
					wizard_back_button('goTo({tab:' . LEAGUE_TAB_DETAILS . '})'),
					wizard_next_button(get_label('Save'), 'saveRules()', 'details-btn'),
					$this->summary());
				break;

			case LEAGUE_TAB_CLUBS:
				$this->showClubsTab();
				wizard_end(
					wizard_back_button('goTo({tab:' . LEAGUE_TAB_RULES . '})'),
					wizard_next_button(get_label('Done'), 'toLeague()', 'details-btn'),
					$this->summary());
				break;

			default:
				show_details_form('form/league_edit.php?id=' . $this->id);
				wizard_end('', wizard_next_button(get_label('Save'), 'save()', 'details-btn'), wizard_summary_live(get_label('League')));
				break;
		}
	}

	// The clubs of the league as the tiles of league_clubs.php, and the button that adds one. A club
	// added by the league is invited: it is in once its manager accepts.
	private function showClubsTab()
	{
		global $_lang;

		echo '<p>' . get_label('Add the clubs that play in your league. A club is invited: it joins once its managers accept. The series of the league are played by its clubs, and their game days and tournaments are played by its rules.') . '</p>';
		echo '<div class="toolbar"><a class="btn tb-create" href="#" onclick="mr.addLeagueClub(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add [0]', get_label('club')) . '</span></a></div>';

		$clubs = array();
		$query = new DbQuery(
			'SELECT c.id, nc.name, c.flags, l.flags, ni.name, (SELECT count(*) FROM games g WHERE g.club_id = c.id) as games FROM league_clubs l' .
				' JOIN clubs c ON l.club_id = c.id' .
				' JOIN names nc ON nc.id = c.name_id AND (nc.langs & '.$_lang.') <> 0' .
				' JOIN cities i ON c.city_id = i.id' .
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
				' WHERE l.league_id = ? AND (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0' .
				' ORDER BY l.flags DESC, games DESC, nc.name',
			$this->id);
		while ($row = $query->next())
		{
			list ($id, $name, $flags, $league_flags, $city_name, $games) = $row;
			$club = new stdClass();
			$club->id = $id;
			$club->name = $name;
			$club->flags = $flags;
			$club->city = $city_name;
			$club->games = $games;

			// What league_clubs.php offers its managers.
			$state = '';
			$buttons = '';
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
			if ($state != '')
			{
				$club->extra = '<span class="tile-meta"><span class="chip pending">' . $state . '</span></span>';
			}
			$club->buttons = $buttons;
			$clubs[] = $club;
		}
		club_tiles_show($clubs, get_label('No clubs yet.'));
	}

	// Beside the rules and the clubs: which league they are of.
	private function summary()
	{
		$pic = new Picture(LEAGUE_PICTURE);
		$pic->set($this->id, $this->name, $this->flags);
		$logo = PageBase::capture(function() use ($pic) { $pic->show(TNAILS_DIR, false); });
		return wizard_summary(get_label('League'), array(details_tab_name() => array(
			array(get_label('Name'), $this->name))), $logo);
	}

	protected function js()
	{
?>
		function save()
		{
			detailsCommit(function() { refr(); });
		}

		// Saved, the rules lead on to the clubs.
		function saveRules()
		{
			detailsCommit(function() { goTo({tab: <?php echo LEAGUE_TAB_CLUBS; ?>}); });
		}

		// The clubs are the last step: the league goes on to its page.
		function toLeague()
		{
			goTo('league_main.php?id=<?php echo $this->id; ?>');
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Management'));

?>
