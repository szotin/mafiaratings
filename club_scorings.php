<?php

require_once 'include/club.php';
require_once 'include/rules_management.php';

// The rules and the scoring systems of a club - see include/rules_management.php.
class Page extends ClubPageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		$this->management = new RulesManagement($this->id, NULL);
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
