<?php

require_once '../include/session.php';
require_once '../include/security.php';
require_once '../include/picture.php';

define('COLUMN_COUNT', 4);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

initiate_session();

try
{
	dialog_title(get_label('Select club'));
	check_permissions(PERMISSION_USER);
	
	$now = isset($_REQUEST['now']) && $_REQUEST['now'] ? 'true' : 'false';
	
	$club_count = 0;
	$column_count = 0;
		
	if (is_permitted(PERMISSION_ADMIN))
	{
		$query = new DbQuery('SELECT id, name, flags FROM clubs WHERE (flags & ' . CLUB_FLAG_RETIRED . ') = 0 ORDER BY name');
	}
	else
	{
		$query = new DbQuery('SELECT c.id, c.name, c.flags FROM club_users cu JOIN clubs c ON c.id = cu.club_id WHERE cu.user_id = ? AND (c.flags & ' . CLUB_FLAG_RETIRED . ') = 0 AND (cu.flags & ' . USER_PERM_REFEREE . ') <> 0 ORDER BY c.name', $_profile->user_id);
	}				
	
	echo '<table class="dialog_form" width="100%"><tr>';
	
	$club_pic = new Picture(CLUB_PICTURE);
	while ($row = $query->next())
	{
		list ($club_id, $club_name, $club_flags) = $row;
		if ($column_count == 0)
		{
			if ($club_count == 0)
			{
				echo '<table class="bordered light" width="100%">';
			}
			else
			{
				echo '</tr>';
			}
			echo '<tr>';
		}
		
		echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
		
		echo '<table class="transp" width="100%">';
		
		echo '<tr class="darker"><td align="center"><p><b>' . $club_name . '</b></p></td></tr><tr><td align="center"><p><a href="#" onclick="clubSelected(' . $club_id . ')">';
		$club_pic->set($club_id, $club_name, $club_flags);
		$club_pic->show(ICONS_DIR, false);
		echo '</a></p></td></tr></table>';
		
		echo '</td>';
		
		++$club_count;
		++$column_count;
		if ($column_count >= COLUMN_COUNT)
		{
			$column_count = 0;
		}
	}
	if ($club_count > 0)
	{
		if ($column_count > 0)
		{
			echo '<td colspan="' . (COLUMN_COUNT - $column_count) . '">&nbsp;</td>';
		}
		echo '</tr></table>';
	}
	else
	{
		throw new Exc(get_label('You are not allowed to create events in any of the clubs.'));
	}
	
?>	
	<script>
	function clubSelected(clubId)
	{
		dlg.close();
		mr.createEvent(clubId, <?php echo $now; ?>);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>