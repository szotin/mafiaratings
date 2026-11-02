<?php

require_once 'include/address.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything that has been broadcast from this hall. It is the round's own address that counts
// rather than the tournament's: a tournament is entered with one address and its rounds can be
// played at others, and the question here is what was streamed from this room.
class Page extends AddressPageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_address_scope($this->id));
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
