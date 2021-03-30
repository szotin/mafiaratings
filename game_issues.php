<?php 

require_once 'include/general_page_base.php';
require_once 'include/pages.php';

define("PAGE_SIZE", 50);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		
		list ($count) = Db::record(get_label('game issue'), 'SELECT count(*) FROM game_issues');
		show_pages_navigation(PAGE_SIZE, $count);
		$event_pic = new Picture(EVENT_PICTURE);


		$query = new DbQuery('SELECT g.id, c.id, c.name, c.flags, e.id, e.name, e.flags, i.issues FROM game_issues i JOIN games g ON g.id = i.game_id JOIN events e ON e.id = g.event_id JOIN clubs c ON c.id = g.club_id ORDER BY i.game_id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
	
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td width="56"></td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Event').'</td><td>' . get_label('Game') . '</td><td>' . get_label('Issues') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $issues) = $row;
		
			echo '<tr>';
			
			echo '<td valign="top">';
			echo '<button class="icon" onclick="rawEditGame(' . $game_id . ')" title="' . get_label('Edit game json [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="deleteGameIssue(' . $game_id .  ')" title="' . get_label('Mark as no issue') . '"><img src="images/accept.png" border="0"></button>';
			echo '</td>';
			
			echo '<td>';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td>';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td align="center" width="90"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
			echo '<td>' . $issues . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>		
		function rawEditGame(gameId)
		{
			dlg.form("form/game_raw_edit.php?game_id=" + gameId, refr, 1200);
		}
		
		function deleteGameIssue(gameId)
		{
			json.post("api/ops/game.php", { op: "delete_issue", game_id: gameId }, function()
			{
				dlg.info("<?php echo get_label('Game is accepted as is. Issue record is deleted.'); ?>", "<?php echo get_label('Game'); ?>" + gameId, undefined, refr);
			});
		}
<?php
	}
}

$page = new Page();
$page->set_ccc(CCCS_NO);
$page->run('Games with issues');

?>
