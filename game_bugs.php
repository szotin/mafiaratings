<?php 

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/game.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page, $_lang;
	
		check_permissions(PERMISSION_ADMIN);
		
		list ($count) = Db::record(get_label('bug report'), 'SELECT count(*) FROM bug_reports');
		show_pages_navigation(PAGE_SIZE, $count);
		$event_pic = new Picture(EVENT_PICTURE);
		$user_pic = new Picture(USER_PICTURE);

		$query = new DbQuery(
			'SELECT b.id, c.id, c.name, c.flags, e.id, e.name, e.flags, b.table_num, b.round_num, u.id, nu.name, u.flags, comment'.
			' FROM bug_reports b'.
			' JOIN events e ON e.id = b.event_id'.
			' JOIN clubs c ON c.id = e.club_id'.
			' JOIN users u ON u.id = b.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' ORDER BY b.id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
	
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="28"></td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Event').'</td><td width="48">'.get_label('User').'</td><td width="90">' . get_label('Game') . '</td><td>Report</td></tr>';
		while ($row = $query->next())
		{
			list ($bug_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $table, $round, $user_id, $user_name, $user_flags, $comment) = $row;
		
			echo '<tr align="center">';
			echo '<td valign="middle">';
			echo '<button class="icon" onclick="deleteBug(' . $bug_id .  ')" title="Resolved"><img src="images/delete.png" border="0"></button>';
			echo '</td>';
			
			echo '<td align="center">';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td align="center">';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td>';
			$user_pic->set($user_id, $user_name, $user_flags);
			$user_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td align="center"><a href="game.php?bug_id=' . $bug_id . '" target="_blank">' . get_label('Table [0] / Game [1]', $table, $round) . '</a></td>';
			echo '<td><pre>' . $comment . '</pre></td>';
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>		
		function deleteBug(bugId)
		{
			dlg.yesNo("Are you sure the bug is resolved?", null, null, function()
			{
				json.post("api/ops/game.php", { op: "bug_resolved", bug_id: bugId }, refr);
			});
		}
<?php
	}
}

$page = new Page();
$page->run('Bug reports');

?>
