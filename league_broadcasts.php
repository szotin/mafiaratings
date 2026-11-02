<?php

require_once 'include/league.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything there is to follow in this league. A league holds its tournaments through its
// series, which is how its own pages of tournaments and of games find them too.
class Page extends LeaguePageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_league_scope($this->id));
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
