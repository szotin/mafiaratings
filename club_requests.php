<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile, $_lang_code, $_page;
		
		check_permissions(PERMISSION_ADMIN);
		echo '<table class="bordered" width="100%">';
		echo '<tr class="darker"><td width="52">&nbsp;</td>';
		echo '<td>'.get_label('Club name').'</td><td width="120">'.get_label('User').'</td><td width="120">'.get_label('Country').'</td><td width="120">'.get_label('City').'</td></tr>';
		
		$query = new DbQuery('SELECT r.id, r.name, o.name_' . $_lang_code . ', i.name_' . $_lang_code . ', u.name FROM club_requests r JOIN users u ON r.user_id = u.id JOIN cities i ON r.city_id = i.id JOIN countries o ON i.country_id = o.id');
		while ($row = $query->next())
		{
			list($id, $name, $country, $city, $user) = $row;
			echo '<tr><td>';
			echo '<a href="#" onclick="mr.declineClub(' . $id . ')" title="' .get_label('Decline club request') . '"><img src="images/delete.png" border="0"></a>';
			echo ' <a href="#" onclick="mr.acceptClub(' . $id . ')" title="' .get_label('Accept club request') . '"><img src="images/accept.png" border="0"></a>';
			echo '</td><td>' . $name . '</td><td>' . $user . '</td><td>' . $country . '</td><td>' . $city . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Club requests'));

?>