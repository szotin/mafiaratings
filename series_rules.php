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
		
		// The editor behind this button is the league rules filter editor. A club series carries
		// its club's rules code rather than a filter, so there is nothing for it to edit.
		if ($this->owner->is_league() && is_series_manager($this->owner, $this->id))
		{
			echo '</td><td align="right"><button class="icon" onclick="mr.editLeagueRules(' . $this->id . ')"><img src="images/edit.png" border="0"></button>';
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