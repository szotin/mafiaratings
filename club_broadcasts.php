<?php

require_once 'include/club.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything there is to follow at this club: the streams of every round it has ever played, and
// the tables of whatever it is playing right now that can be read in words. The page itself is
// the one a tournament and a round show - see show_broadcasts() in include/broadcast.php.
class Page extends ClubPageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_club_scope($this->id));
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
