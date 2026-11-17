<?php

require_once __DIR__ . '/page_base.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/club.php';
require_once __DIR__ . '/ccc_filter.php';

define('CCCS_MY', 0);
define('CCCS_ALL', 1);
define('CCCS_NO', 2);

class GeneralPageBase extends PageBase
{
	// The menu of the site is in the header now; the band at the top of the page carries the title.
	protected function show_title()
	{
		echo '<div class="page-hd"><div>' . $this->standard_title() . '</div><div class="page-hd-side">';
		show_back_button();
		echo '</div></div>';
	}
}

?>