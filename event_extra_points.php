<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/game_state.php';

define("PAGE_SIZE",15);

class Page extends EventPageBase
{
	private $players;
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->event->club_id);
		
		list ($count) = Db::record(get_label('extra points'), 'SELECT count(*) FROM event_extra_points WHERE event_id = ?', $this->event->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="60">';
		echo '<button class="icon" onclick="addPoints(' . $this->event->id . ')"><img src="images/create.png" border="0"></button>';
		echo '</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="180" align="center">' . get_label('Reason') . '</td>';
		echo '<td width="80" align="center">' . get_label('Points') . '</td>';
		echo '</tr>';
		
		$query = new DbQuery('SELECT p.id, u.id, u.name, u.flags, p.reason, p.details, p.points FROM event_extra_points p JOIN users u ON u.id = p.user_id WHERE p.event_id = ?', $this->event->id);
		while ($row = $query->next())
		{
			list($points_id, $user_id, $user_name, $user_flags, $reason, $details, $points) = $row;
			
			echo '<tr>';
			echo '<td valign="center">';
			echo '<button class="icon" onclick="deletePoints(' . $points_id . ')"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="editPoints(' . $points_id . ')"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			echo '<td width="60" align="center">';
			echo '<a href="user_info.php?id=' . $user_id . '&bck=1">';
			show_user_pic($user_id, $user_name, $user_flags, ICONS_DIR, 50, 50);
			echo '</a>';
			echo '</td><td>';
			echo '<a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td>';
			echo '<td>' . $reason . '</td>';
			echo '<td align="center">';
			if ($points == 0)
			{
				echo get_label('Average');
			}
			else
			{
				echo format_score($points);
			}
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function addPoints(eventId)
		{
			dlg.form("event_extra_points_add.php?event_id=" + eventId, refr);
		}
		
		function deletePoints(pointsId)
		{
			dlg.yesNo("<?php echo get_label("Are you sure you want to delete extra points?"); ?>", null, null, function()
			{
				json.post("api/ops/event.php", { op: 'delete_extra_points', points_id: pointsId }, refr);
			});
		}
		
		function editPoints(pointsId)
		{
			dlg.form("event_extra_points_edit.php?points_id=" + pointsId, refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Extra points'));

?>