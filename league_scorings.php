<?php

require_once 'include/league.php';
require_once 'include/pages.php';
require_once 'include/rules_management.php';

// The rules and the scoring systems of a league - see include/rules_management.php.
class Page extends LeaguePageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->id);
		$this->management = new RulesManagement(NULL, $this->id);
		$this->management->show();
	}

	protected function js()
	{
		parent::js();
		if (isset($this->management))
		{
			$this->management->js();
		}
	}
}

$page = new Page();
$page->run(get_label('Rules'));

?>
