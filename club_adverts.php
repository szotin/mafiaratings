<?php

require_once 'include/pages.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/club.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->id);
		$expired = isset($_REQUEST['expired']);
		
		$condition = new SQL(' WHERE n.club_id = ?', $this->id);
		if (!$expired)
		{
			$condition->add(' AND n.expires >= UNIX_TIMESTAMP()');
		}
		
		$query = new DbQuery('SELECT ct.timezone, n.id, n.timestamp, n.expires, n.message FROM news n JOIN clubs c ON c.id = n.club_id JOIN cities ct ON ct.id = c.city_id', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
		
		list ($count) = Db::record(get_label('advert'), 'SELECT count(*) FROM news n', $condition);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<form method="get" name="form">';
		echo '<input type="hidden" name="id" value="' . $this->id . '">';
		echo '<input type="checkbox" name="expired"';
		if ($expired)
		{
			echo ' checked';
		}
		echo ' onClick="document.form.submit()"> ' . get_label('show expired adverts') . '</form>';
		
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><th width="56">';
		echo '<button class="icon" onclick="mr.createAdvert(' . $this->id . ')" title="' . get_label('Create [0]', get_label('advert')) . '"><img src="images/create.png" border="0"></button></th><th>&nbsp;</th>';
		while ($row = $query->next())
		{
			list ($timezone, $id, $start, $end, $message) = $row;
			echo '<tr class="light">';
			echo '<td width="56" valign="top" align="center">';
			echo '<button class="icon" onclick="mr.editAdvert(' . $id . ')" title="' . get_label('Edit [0]', get_label('advert')) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="mr.deleteAdvert(' . $id . ', \'' . get_label('Are you sure you want to delete the advert?') . '\')" title="' . get_label('Delete [0]', get_label('advert')) . '"><img src="images/delete.png" border="0"></button>';
			echo '</td>';
			echo '<td><b>' . format_date('l, F d, Y', $start, $timezone) . ' - ' . format_date('l, F d, Y', $end, $timezone) . ':</b><p>' . $message . '</p></td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Adverts'));

?>