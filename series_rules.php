<?php

require_once 'include/series.php';
require_once 'include/rules.php';

class Page extends SeriesPageBase
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
		
		// A series keeps a rules filter of its own, copied from its owner when it was made and
		// edited here since. A club series carries its club's rules code instead, and a code is
		// picked rather than filtered, so there is nothing here for this button to edit.
		if ($this->owner->is_league() && is_series_manager($this->owner, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="mr.editSeriesRules(' . $this->id . ')"><img src="images/edit.png" border="0"></button>';
		}
		echo '</td></tr></table></p>';
		$rules = json_decode($this->rules);
		show_rules($rules, $view);
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