<?php

require_once 'include/series.php';
require_once 'include/broadcast.php';
require_once 'include/pages.php';

// Everything there is to follow in this series, its subseries counted as its own the way its
// standings and its games count them.
class Page extends SeriesPageBase
{
	protected function show_body()
	{
		show_broadcasts(broadcast_series_scope($this->id));
	}
}

$page = new Page();
$page->run(get_label('Broadcasts'));

?>
