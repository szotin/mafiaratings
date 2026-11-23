<?php

require_once 'include/address.php';
require_once 'include/details_tab.php';

// The page an address is managed on: for now its fields, the ones of the dialog that edited it (see
// include/details_tab.php). Opened by the first item of its menu "≡".
class Page extends AddressPageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, $this->club_id);
		wizard_begin(array(array(details_tab_name(), NULL)), 0);
		show_details_form('form/address_edit.php?id=' . $this->id);
		wizard_end('', wizard_next_button(get_label('Save'), 'save()', 'details-btn'), wizard_summary_live(get_label('Address')));
	}

	protected function js()
	{
?>
		function save()
		{
			detailsCommit(function(obj)
			{
				// A changed address is looked up on the map again, as the dialog did.
				if (isSet(obj.changed) && obj.changed)
				{
					dlg.form('form/address_geo.php?id=<?php echo $this->id; ?>', refr, 400, refr);
				}
				else
				{
					refr();
				}
			});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Management'));

?>