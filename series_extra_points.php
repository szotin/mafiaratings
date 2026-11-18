<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/gaining.php';
require_once 'include/cards.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends SeriesPageBase
{
	private $players, $_lang;
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		check_series_permissions($this->owner, $this->id);
		
		list ($count) = Db::record(get_label('extra points'), 'SELECT count(*) FROM series_extra_points WHERE series_id = ?', $this->id);

		// The list of the v3 design, after ratings.php: the button that adds points in the toolbar,
		// the table in a sheet.
		echo '<div class="toolbar"><span class="count">' . get_label('[0] records', number_format($count)) . '</span>';
		echo '<a class="btn tb-create" href="#" onclick="addPoints(' . $this->id . '); return false;">' . PageBase::icon('plus') . '<span>' . get_label('Add extra points') . '</span></a></div>';
		show_pages_navigation(PAGE_SIZE, $count);

		echo '<div class="sheet"><table class="tbl">';
		echo '<thead><tr><th scope="col"></th>';
		echo '<th scope="col">' . get_label('Player') . '</th>';
		echo '<th scope="col">' . get_label('Reason') . '</th>';
		if ($this->stages > 1)
		{
			echo '<th class="p2" scope="col">' . get_label('Stage') . '</th>';
		}
		echo '<th class="num" scope="col">' . get_label('Points') . '</th>';
		echo '</tr></thead><tbody>';
		
		$timezone = get_timezone();
		
		$query = new DbQuery(
			'SELECT p.id, u.id, nu.name, u.flags, p.reason, p.details, p.points, p.time, p.stage' . 
				' FROM series_extra_points p' . 
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE p.series_id = ?' .
				' ORDER BY p.time DESC, p.id', $this->id);
		while ($row = $query->next())
		{
			list($points_id, $user_id, $user_name, $user_flags, $reason, $details, $points, $time, $stage) = $row;
			
			echo '<tr>';
			echo '<td class="tb-btns">';
			echo '<button class="icon" onclick="deletePoints(' . $points_id . ')"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="editPoints(' . $points_id . ')"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			echo '<td><a class="who" href="user_info.php?id=' . $user_id . '&bck=1">' . card_avatar($this->user_pic, $user_id, $user_name, $user_flags, '') .
				'<span class="who-txt"><span class="who-name">' . $user_name . '</span></span></a></td>';
			// The reason, with the day the points were given under it.
			echo '<td><span class="who-name">' . $reason . '</span><span class="who-sub">' . format_date($time, $timezone) . '</span></td>';
			if ($this->stages > 1)
			{
				echo '<td class="p2 dim">' . get_series_stage_name($this->type, $this->stages, $stage) . '</td>';
			}
			echo '<td class="num strong">' . format_gain($points) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
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