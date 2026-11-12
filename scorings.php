<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/rules_management.php';

// The rules and the scoring systems of the site - see include/rules_management.php.
class Page extends GeneralPageBase
{
	protected function show_body()
	{
		check_permissions(PERMISSION_ADMIN);
		$this->management = new RulesManagement(NULL, NULL);
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
