<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	protected function show_body()
	{
		global $_profile;

		$timezone = get_timezone();
		echo "<center><h1>" . format_date(time(), $timezone, true) . '</h1><p>' . $timezone . '</p></center>';
	}
}

$page = new Page();
$page->run('Time');

?>