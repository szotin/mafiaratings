<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
		global $_profile;

		$timezone = get_timezone();
		echo "<center><h1>" . format_date('l, F d, Y H:i', time(), $timezone) . '</h1><p>' . $timezone . '</p></center>';
	}
}

$page = new Page();
$page->run('Time', PERM_ALL);

?>