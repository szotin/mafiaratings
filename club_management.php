<?php

require_once 'include/club.php';
require_once 'include/details_tab.php';

define('CLUB_TAB_DETAILS', 0);
define('CLUB_TAB_RULES', 1);
define('CLUB_TAB_ADDRESS', 2);

// The page a club is managed on, as steps (see include/details_tab.php): its fields, the ones of the
// dialog that edited it; its rules; and, while the club has no address, its first address. Opened by
// the first item of its menu "≡". A club just made in create.php comes here on its rules, so that
// whoever made it sees what its game days will be played by and changes what the club does
// differently, and then on to the place it plays at.
class Page extends ClubPageBase
{
	private $tab;
	private $has_address;

	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		$this->tab = isset($_REQUEST['tab']) ? (int)$_REQUEST['tab'] : CLUB_TAB_DETAILS;
		// The step of the address is there only for a club that has none in use: one that has, adds
		// more on its list of addresses.
		list($count) = Db::record(get_label('address'), 'SELECT COUNT(*) FROM addresses WHERE club_id = ? AND (flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0', $this->id);
		$this->has_address = ($count > 0);
		if ($this->tab != CLUB_TAB_RULES && ($this->tab != CLUB_TAB_ADDRESS || $this->has_address))
		{
			$this->tab = CLUB_TAB_DETAILS;
		}

		$steps = array(
			array(details_tab_name(), 'goTo({tab:' . CLUB_TAB_DETAILS . '})'),
			array(get_label('Rules'), 'goTo({tab:' . CLUB_TAB_RULES . '})'));
		if (!$this->has_address)
		{
			$steps[] = array(get_label('Address'), 'goTo({tab:' . CLUB_TAB_ADDRESS . '})');
		}
		wizard_begin($steps, $this->tab);
		if ($this->tab == CLUB_TAB_ADDRESS)
		{
			$this->showAddressTab();
		}
		else if ($this->tab == CLUB_TAB_RULES)
		{
			// The rules the club was given when it was made - those of its league, or of the site -
			// edited in place by the editor of the dialog that edits them (form/rules_edit.php).
			echo '<p>' . get_label('These are the rules the game days of your club are played by. They came with the club; look through them and change what your club does differently. Tournaments and game days can still choose other rules.') . '</p>';
			show_details_form('form/rules_edit.php?club_id=' . $this->id);
			wizard_end(
				wizard_back_button('goTo({tab:' . CLUB_TAB_DETAILS . '})'),
				wizard_next_button(get_label('Save'), 'saveRules()', 'details-btn'),
				$this->summary());
		}
		else
		{
			show_details_form('form/club_edit.php?id=' . $this->id);
			wizard_end('', wizard_next_button(get_label('Save'), 'save()', 'details-btn'), wizard_summary_live(get_label('Club')));
		}
	}

	// What an address is for, and the fields of the first one - the form of the dialog that creates it
	// (form/address_create.php). It can be left for later.
	private function showAddressTab()
	{
		echo '<p>' . get_label('An address is a place where your club plays - a hall, a cafe, a room. Every game day and tournament is played at one of the addresses of its club: players see on its page where to come and find it on the map, and the games and the statistics of every place are kept apart. Add the place where the club plays most of all; the others can be added later.') . '</p>';
		show_details_form('form/address_create.php?club=' . $this->id);
		wizard_end(
			wizard_back_button('goTo({tab:' . CLUB_TAB_RULES . '})'),
			'<span class="wz-btns"><button type="button" class="wz-back" onclick="toClub()"><span>' . get_label('Later') . '</span></button>' .
				wizard_next_button(get_label('Create address'), 'createAddress()', 'details-btn') . '</span>',
			// With the logo chosen for it - see logoChosen() in form/address_create.php.
			wizard_summary_live(get_label('Address'), '<img id="wz-sum-logo-img" src="images/' . ICONS_DIR . 'address.png">'));
	}

	// Beside the rules: which club they are of.
	private function summary()
	{
		$pic = new Picture(CLUB_PICTURE);
		$pic->set($this->id, $this->name, $this->flags);
		$logo = PageBase::capture(function() use ($pic) { $pic->show(TNAILS_DIR, false); });
		return wizard_summary(get_label('Club'), array(details_tab_name() => array(
			array(get_label('Name'), $this->name),
			array(get_label('City'), $this->city . ', ' . $this->country))), $logo);
	}

	protected function js()
	{
?>
		function save()
		{
			detailsCommit(function() { refr(); });
		}

		// Saved, the rules lead on to the address - or, for a club that has one, to the club.
		function saveRules()
		{
<?php if ($this->has_address): ?>
			detailsCommit(toClub);
<?php else: ?>
			detailsCommit(function() { goTo({tab: <?php echo CLUB_TAB_ADDRESS; ?>}); });
<?php endif; ?>
		}

		function toClub()
		{
			goTo('club_main.php?id=<?php echo $this->id; ?>');
		}

		// The address is the last step: made, the club goes on to its page.
		function createAddress()
		{
			detailsCommit(toClub);
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Management'));

?>
