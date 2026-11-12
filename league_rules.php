<?php

require_once 'include/league.php';
require_once 'include/rules.php';

// The rulebook of a league is its filter: what the tournaments of the league may be played by.
// A league has no rules code of its own - a club that picks the rules of the league plays its
// own default rules put through this filter, see get_available_rules().
class Page extends LeaguePageBase
{
	protected function prepare()
	{
		parent::prepare();
		
		$this->view = RULES_VIEW_FULL;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
		}
	}
	
	protected function show_body()
	{
		echo '<p><table class="transp" width="100%"><tr><td>';
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_FULL . '})"' . ($this->view <= RULES_VIEW_FULL ? ' checked' : '') . '> ' . get_label('detailed');
		echo ' <input type="radio" onclick="goTo({view:' . RULES_VIEW_SHORT . '})"' . ($this->view == RULES_VIEW_SHORT ? ' checked' : '') . '> ' . get_label('shorter');
		echo '</td></tr></table></p>';
		
		show_rules($this->rules_filter, $this->view);
	}
}

$page = new Page();
$page->run(get_label('Game Rules'));
