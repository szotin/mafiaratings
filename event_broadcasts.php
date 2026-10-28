<?php

require_once 'include/event.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// The same page as the tournament's, for one round of it or for an event played on its own.
// The tables belong to the tournament where there is one, which is what stream_owner() is for:
// a text broadcast of this round is still read at the tournament's tables.
class Page extends EventPageBase
{
	protected function show_body()
	{
		show_broadcasts(stream_owner($this->tournament_id, $this->id), 0, $this->id, $this->text_broadcasts);
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
