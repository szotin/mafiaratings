<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/gaining.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends SeriesPageBase
{
	private $players, $_lang;
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_permissions(PERMISSION_LEAGUE_MANAGER, $this->league_id);
		
		list ($count) = Db::record(get_label('extra points'), 'SELECT count(*) FROM series_extra_points WHERE series_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="60">';
		echo '<button class="icon" onclick="addPoints(' . $this->id . ')"><img src="images/create.png" border="0"></button>';
		echo '</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="500" align="center"></td>';
		echo '<td width="80" align="center">' . get_label('Points') . '</td>';
		echo '</tr>';
		
		$timezone = get_timezone();
		
		$query = new DbQuery(
			'SELECT p.id, u.id, nu.name, u.flags, p.reason, p.details, p.points, p.time' . 
				' FROM series_extra_points p' . 
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE p.series_id = ?' .
				' ORDER BY p.time DESC, p.id', $this->id);
		while ($row = $query->next())
		{
			list($points_id, $user_id, $user_name, $user_flags, $reason, $details, $points, $time) = $row;
			
			echo '<tr>';
			echo '<td valign="center">';
			echo '<button class="icon" onclick="deletePoints(' . $points_id . ')"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="editPoints(' . $points_id . ')"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			echo '<td width="60" align="center">';
			$this->user_pic->set($user_id, $user_name, $user_flags);
			$this->user_pic->show(ICONS_DIR, true, 50);
			echo '</td><td>';
			echo '<a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td>';
			echo '<td><i>' . format_date($time, $timezone) . '</i>: <b>' . $reason . '</b></td>';
			echo '<td align="center">';
			echo format_gain($points);
			echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
		parent::js();
?>
		function addPoints(seriesId)
		{
			dlg.form("form/series_extra_points_add.php?series_id=" + seriesId, refr);
		}
		
		function deletePoints(pointsId)
		{
			dlg.yesNo("<?php echo get_label("Are you sure you want to delete extra points?"); ?>", null, null, function()
			{
				json.post("api/ops/series.php", { op: 'delete_extra_points', points_id: pointsId }, refr);
			});
		}
		
		function editPoints(pointsId)
		{
			dlg.form("form/series_extra_points_edit.php?points_id=" + pointsId, refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Extra points'));

?>