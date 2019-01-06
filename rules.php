<?php

require_once 'include/general_page_base.php';
require_once 'include/rules.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		$view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL . ')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT . ')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo '</td></tr></table></p>';
		
		show_rules(NULL, $view);
	}
	
	protected function js()
	{
		parent::js();
?>
		function filter(view)
		{
			goTo({ view: view });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));

?>