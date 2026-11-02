<?php

require_once 'include/general_page_base.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything there is to follow anywhere on the site: what is on the air right now at every club
// that is playing, and the whole history of what has been streamed. The page a tournament shows,
// with nothing narrowing it down - see show_broadcasts() in include/broadcast.php.
class Page extends GeneralPageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_site_scope());
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
