<?php

require_once 'include/series.php';
require_once 'include/details_tab.php';

// Getting a series ready: for now its fields, the ones of the dialog that edited it (see
// include/details_tab.php). Opened by the first item of its menu "≡".
class Page extends SeriesPageBase
{
	protected function show_body()
	{
		check_series_permissions($this->owner, $this->id);
		wizard_begin(array(array(details_tab_name(), NULL)), 0);
		show_details_form('form/series_edit.php?id=' . $this->id);
		wizard_end('', wizard_next_button(get_label('Save'), 'save()', 'details-btn'), wizard_summary_live(get_label('Series~singular')));
	}

	protected function js()
	{
?>
		function save()
		{
			detailsCommit(function() { refr(); });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Preparation'));

?>