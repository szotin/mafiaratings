<?php

require_once 'include/tournament.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything there is to follow at this tournament: the streams of each of its rounds, and the
// tables that can be read in words while it is on. Both are drawn by show_broadcasts(), which
// the page of a round calls as well - see include/broadcast.php.
class Page extends TournamentPageBase
{
	protected function show_body()
	{
		show_broadcasts(stream_tournament($this->id), $this->id, 0, $this->text_broadcasts);
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
