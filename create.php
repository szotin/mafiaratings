<?php

require_once 'include/general_page_base.php';
require_once 'include/details_tab.php';

// Making something on the site, reached by "Create" in the header. Without ?type= it asks what:
// a game day, a tournament, a series, a club, a league, an address - those this user may make.
// With it, it is the first tab of the page the thing is then prepared or managed on
// (event_preparation.php, tournament_preparation.php...): the fields of the dialog that used to
// create it, and the names of the tabs that come after, greyed out. The thing is created by the
// button under the fields and the page it has from then on opens on its next tab; leaving here
// without pressing it creates nothing. See include/details_tab.php.
//
// ?owner= is whose it is to be, where that is a question: "c12" a club, "l3" a league. The pages
// of a club or a league send it, so that "Create a tournament" there makes one of that club.
class Page extends GeneralPageBase
{
	private $types;
	private $type;
	private $owners;
	private $owner;

	protected function prepare()
	{
		global $_profile, $_lang;

		parent::prepare();
		check_permissions(PERMISSION_USER);

		// Whose a thing can be: the clubs where this user may make it, and for a series the leagues -
		// the same lists the buttons that lead here are shown by (PageBase::create_button()).
		$referee_clubs = PageBase::create_owners('event');
		$manager_clubs = PageBase::create_owners('tournament');
		$series_owners = PageBase::create_owners('series');

		// Each kind: its name, what it is, its icon, whose it can be (NULL - nobody's), and the tabs
		// it has after this one.
		$this->types = array(
			'event' => array(
				get_label('Game day'),
				get_label('The regular games of a club: one evening, or the same day every week.'),
				'cal', $referee_clubs,
				array(get_label('Rules'), get_label('Mailing'), get_label('Scheme'), get_label('Registrations'), get_label('Streams'), get_label('Seating'))),
			'tournament' => array(
				get_label('Tournament'),
				get_label('Rounds, standings and a winner. Individual, team or a season-long championship.'),
				'trophy', $manager_clubs,
				array(get_label('Series'), get_label('Scheme'), get_label('Rules'), get_label('Hiding the table'), get_label('Registrations'), get_label('Streams'), get_label('Pairs'), get_label('Seating'))),
			'series' => array(
				get_label('Series~singular'),
				get_label('A season or a cup adding up the results of several tournaments.'),
				'layers', $series_owners,
				array()),
			'club' => array(
				get_label('Club'),
				get_label('A place where people play: its players, game days and tournaments.'),
				'users', NULL,
				array(get_label('Rules'), get_label('Address'))),
			'league' => array(
				get_label('League'),
				get_label('Clubs joined by common rules, running series across them.'),
				'globe', NULL,
				array(get_label('Rules'), get_label('Clubs'))),
			'address' => array(
				get_label('Address'),
				get_label('A venue of a club, to be chosen for its game days and tournaments.'),
				'pin', $referee_clubs,
				array()),
		);
		// What has nobody to belong to cannot be made.
		foreach ($this->types as $key => $t)
		{
			if (!is_null($t[3]) && count($t[3]) == 0)
			{
				unset($this->types[$key]);
			}
		}

		$this->type = isset($_REQUEST['type']) ? $_REQUEST['type'] : '';
		if (!isset($this->types[$this->type]))
		{
			$this->type = '';
			return;
		}
		$this->owners = $this->types[$this->type][3];
		$this->owner = NULL;
		if (!is_null($this->owners))
		{
			$this->owner = isset($_REQUEST['owner']) ? $_REQUEST['owner'] : '';
			if (!isset($this->owners[$this->owner]))
			{
				// The club the user plays in first, if it is one of them; otherwise the first.
				$this->owner = 'c' . $_profile->user_club_id;
				if (!isset($this->owners[$this->owner]))
				{
					reset($this->owners);
					$this->owner = key($this->owners);
				}
			}
		}
	}

	protected function has_admin_menu()
	{
		return false;
	}

	protected function standard_title()
	{
		if ($this->type == '')
		{
			return '<h1>' . get_label('Create') . '</h1><p class="lede">' . get_label('Choose what to create. It is made step by step, and everything can be changed later.') . '</p>';
		}
		return '<h1>' . get_label('Create') . ': ' . mb_strtolower($this->types[$this->type][0], 'UTF-8') . '</h1>';
	}

	protected function show_body()
	{
		if ($this->type == '')
		{
			$this->show_types();
			return;
		}

		list($name, $desc, $icon, $owners, $ahead) = $this->types[$this->type];
		// The steps: what it is - chosen already, a click goes back to the choice - this one, and the
		// tabs of its page, which open once it is there.
		$back = 'create.php' . (isset($_REQUEST['owner']) ? '?owner=' . urlencode($_REQUEST['owner']) : '');
		$steps = array(array(get_label('Type'), 'goTo(\'' . $back . '\')'), array(details_tab_name(), NULL));
		foreach ($ahead as $tab_name)
		{
			$steps[] = array($tab_name, NULL);
		}
		wizard_begin($steps, 1);

		if (!is_null($owners))
		{
			$owner_label = ($this->type == 'series') ? get_label('Club') . ' / ' . get_label('League') : get_label('Club');
			echo '<div class="wz-owner"><label for="create-owner">' . $owner_label . '</label><select id="create-owner" onchange="goTo({owner: this.value})">';
			foreach ($owners as $key => $owner_name)
			{
				echo '<option value="' . $key . '"' . ($key == $this->owner ? ' selected' : '') . '>' . $owner_name . '</option>';
			}
			echo '</select></div>';
		}

		$owner_id = is_null($this->owner) ? 0 : (int)substr($this->owner, 1);
		switch ($this->type)
		{
			case 'event':
				$form = 'form/event_create.php?club_id=' . $owner_id;
				break;
			case 'tournament':
				$form = 'form/tournament_create.php?club_id=' . $owner_id;
				break;
			case 'series':
				$form = 'form/series_create.php?' . (substr($this->owner, 0, 1) == 'l' ? 'league_id=' : 'club_id=') . $owner_id;
				break;
			case 'club':
				$form = 'form/club_create.php';
				break;
			case 'league':
				$form = 'form/league_create.php';
				break;
			case 'address':
				$form = 'form/address_create.php?club=' . $owner_id;
				break;
		}
		// Filled in from where the button that led here was: the series of the list of its tournaments,
		// the address of the list of its game days, the league of the list of its clubs.
		$prefill = array('tournament' => array('series_id', 'address_id'), 'event' => array('address_id'), 'club' => array('league_id'));
		if (isset($prefill[$this->type]))
		{
			foreach ($prefill[$this->type] as $param)
			{
				if (isset($_REQUEST[$param]) && (int)$_REQUEST[$param] > 0)
				{
					$form .= (strpos($form, '?') === false ? '?' : '&') . $param . '=' . (int)$_REQUEST[$param];
				}
			}
		}
		show_details_form($form, NULL, $this->type == 'tournament' ? '#form-series' : NULL);
		wizard_end(
			wizard_back_button('createBack()', 'details-back'),
			wizard_next_button(count($ahead) > 0 ? get_label('Next') : get_label('Create'), 'create()', 'details-btn'),
			// Everything but a series shows the logo chosen for it - see logoChosen() in the form/*_create.php
			// of each.
			wizard_summary_live($name, $this->type != 'series' ? '<img id="wz-sum-logo-img" src="images/' . ICONS_DIR . $this->type . '.png">' : ''));
	}

	private function show_types()
	{
		if (count($this->types) == 0)
		{
			echo '<p class="empty">' . get_label('There is nothing you can create.') . '</p>';
			return;
		}
		echo '<div class="create-types">';
		foreach ($this->types as $key => $t)
		{
			list($name, $desc, $icon) = $t;
			$url = 'create.php?type=' . $key;
			if (isset($_REQUEST['owner']))
			{
				$url .= '&owner=' . urlencode($_REQUEST['owner']);
			}
			echo '<a class="create-type" href="' . $url . '"><span class="ct-ic">' . PageBase::icon($icon) . '</span>';
			echo '<span class="ct-text"><b>' . $name . '</b><span>' . $desc . '</span></span></a>';
		}
		echo '</div>';
	}

	protected function js()
	{
		if ($this->type == '')
		{
			return;
		}
?>
		function createBack()
		{
			goTo(<?php echo json_encode('create.php' . (isset($_REQUEST['owner']) ? '?owner=' . urlencode($_REQUEST['owner']) : '')); ?>);
		}

		// The thing is made here, by the form's own commit(); the page it has from now on opens on
		// the tab after this one.
		function create()
		{
			detailsCommit(function(obj)
			{
<?php
		switch ($this->type)
		{
			case 'event':
?>
				// A weekly game day is several of them: the page of the first is opened, and the
				// invitation is offered for all of them, as the dialog offered it.
				var url = 'event_preparation.php?id=' + obj.events[0] + '&tab=0';
				var next = function() { goTo(url); };
				if (isSet(obj.mailing))
				{
					dlg.form('form/event_mailing_create.php?events=' + obj.events + '&type=' + obj.mailing, next, 640, next);
				}
				else
				{
					next();
				}
<?php
				break;
			case 'tournament':
?>
				// Made with its basics; its series are the next tab of its preparation.
				goTo('tournament_preparation.php?id=' + obj.tournament_id + '&tab=<?php echo PREP_TAB_SERIES; ?>');
<?php
				break;
			case 'series':
?>
				goTo('series_info.php?id=' + obj.series_id);
<?php
				break;
			case 'club':
?>
				// Made with its basics; its rules are the next step, on the page it is managed on.
				goTo('club_management.php?id=' + obj.club_id + '&tab=1');
<?php
				break;
			case 'league':
?>
				// Made with its basics; the rules it allows are the next step, on the page it is
				// managed on.
				goTo('league_management.php?id=' + obj.league_id + '&tab=1');
<?php
				break;
			case 'address':
?>
				goTo('address_info.php?id=' + obj.address_id);
<?php
				break;
		}
?>
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Create'));

?>
