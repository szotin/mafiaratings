<?php

require_once 'include/event.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// The same page as the tournament's, for one round of it or for an event played on its own. The
// tables belong to the tournament where there is one, which the scope takes care of: a text
// broadcast of this round is still read at the tournament's tables.
class Page extends EventPageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_event_scope($this->id));
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
