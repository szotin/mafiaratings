<?php

require_once 'include/tournament.php';
require_once 'include/rules.php';

class Page extends TournamentPageBase
{
	protected function show_body()
	{
		$view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$view = (int)$_REQUEST['view'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_FULL .')"' . ($view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORT .')"' . ($view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo ' <input type="radio" onclick="filter(' . RULES_VIEW_SHORTEST .')"' . ($view >= RULES_VIEW_SHORTEST ? ' checked' : '') . '> ' . get_label('shortest');
		echo '</td></tr></table></p>';
		
		show_rules($this->rules_code, $view);
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