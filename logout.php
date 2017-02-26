<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function prepare()
	{
		logout();
		throw new RedirectExc('index.php');
	}
}

$page = new Page();
$page->run(get_label('Logout'), PERM_USER);

?>