<?php

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/club.php';

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_profile, $_lang, $_page;
		
		$club_id = 0;
		if (isset($_REQUEST['club_id']))
		{
			$club_id = (int)$_REQUEST['club_id'];
		}
		
		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker"><td width="52">&nbsp;</td>';
		echo '<td width="360">'.get_label('Action').'</td><td>'.get_label('Club name').'</td><td width="120">'.get_label('User').'</td><td width="120">'.get_label('Country').'</td><td width="120">'.get_label('City').'</td></tr>';
		
		$condition = new SQL();
		if ($club_id > 0)
		{
			$condition->add(' WHERE r.parent_id = ?', $club_id);
		}
		$query = new DbQuery(
			'SELECT r.id, r.name, r.club_id, r.parent_id, no.name, ni.name, u.name, cl.name FROM club_requests r ' . 
			'JOIN users u ON r.user_id = u.id ' . 
			'LEFT OUTER JOIN cities i ON r.city_id = i.id ' . 
			'LEFT OUTER JOIN countries o ON i.country_id = o.id ' .
			'JOIN names ni ON ni.id = i.name_id AND (ni.langs & ?) <> 0 ' .
			'JOIN names no ON no.id = o.name_id AND (no.langs & ?) <> 0 ' .
			'LEFT OUTER JOIN clubs cl ON r.club_id = cl.id ',
			$_lang, $_lang, $condition);
		while ($row = $query->next())
		{
			list($id, $name, $request_club_id, $parent_id, $country, $city, $user, $request_club_name) = $row;
			echo '<tr><td>';
			echo '<a href="#" onclick="mr.declineClub(' . $id . ')" title="' .get_label('Decline club request') . '"><img src="images/delete.png" border="0"></a>';
			echo ' <a href="#" onclick="mr.acceptClub(' . $id . ')" title="' .get_label('Accept club request') . '"><img src="images/accept.png" border="0"></a>';
			echo '</td><td>';
			if ($request_club_id != NULL)
			{
				if ($club_id > 0)
				{
					echo get_label('Move [0] to [1] club system', $request_club_name, $_profile->clubs[$club_id]->name);
				}
				else
				{
					echo get_label('Make [0] a top level club', $request_club_name);
				}
			}
			else if ($club_id > 0)
			{
				echo get_label('Create a club [0] in the [1] club system', $name, $_profile->clubs[$club_id]->name);
			}
			else
			{
				echo get_label('Create a top level club [0]', $name);
			}
			echo '</td><td>' . $name . '</td><td>' . $user . '</td><td>' . $country . '</td><td>' . $city . '</td></tr>';
		}
		echo '</table>';
	}
}

$page = new Page();
$page->run(get_label('Club requests'));

?>