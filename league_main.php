<?php

require_once 'include/league.php';
require_once 'include/player_stats.php';
require_once 'include/pages.php';
require_once 'include/address.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/event.php';

define('COLUMN_COUNT', 5);
define('ROW_COUNT', 2);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));
define('MANAGER_COLUMNS', 5);
define('MANAGER_COLUMN_WIDTH', 100 / MANAGER_COLUMNS);
define('RATING_POSITIONS', 15);

class Page extends LeaguePageBase
{
	protected function show_body()
	{
	}
}

$page = new Page();
$page->run(get_label('Main Page'));

?>