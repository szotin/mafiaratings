<?php 

require_once 'include/general_page_base.php';
require_once 'include/pages.php';
require_once 'include/game.php';

define('PAGE_SIZE', GAMES_PAGE_SIZE);

class Page extends GeneralPageBase
{
	protected function show_body()
	{
		global $_page;
	
		check_permissions(PERMISSION_ADMIN);
		
		list ($count) = Db::record(get_label('game issue'), 'SELECT count(*) FROM game_issues');
		show_pages_navigation(PAGE_SIZE, $count);
		$event_pic = new Picture(EVENT_PICTURE);


		$query = new DbQuery('SELECT g.id, c.id, c.name, c.flags, e.id, e.name, e.flags, i.issues, i.feature_flags, i.new_feature_flags FROM game_issues i JOIN games g ON g.id = i.game_id JOIN events e ON e.id = g.event_id JOIN clubs c ON c.id = g.club_id ORDER BY i.game_id DESC LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE);
	
		$count = 0;
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td width="32"></td><td width="84"></td><td width="48">'.get_label('Club').'</td><td width="48">'.get_label('Event').'</td><td width="90">' . get_label('Game') . '</td><td width="90">' . get_label('Features') . '</td><td width="90">' . get_label('Removed features') . '</td><td>' . get_label('Issues') . '</td></tr>';
		while ($row = $query->next())
		{
			list ($game_id, $club_id, $club_name, $club_flags, $event_id, $event_name, $event_flags, $issues, $feature_flags, $new_feature_flags) = $row;
			$removed_feature_flags = (((int)$feature_flags ^ (int)$new_feature_flags) & (int)$feature_flags);
		
			echo '<tr>';
			
			echo '<td align="center">' . ++$count . '</td>';
			
			
			echo '<td valign="top">';
			echo '<button class="icon" onclick="rawEditGame(' . $game_id . ', ' . $feature_flags . ')" title="' . get_label('Edit game json [0]', $game_id) . '"><img src="images/edit.png" border="0"></button>';
			echo '<button class="icon" onclick="deleteGameIssue(' . $game_id .  ', ' . $feature_flags . ')" title="' . get_label('Not an issue.') . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="reapply(' . $game_id .  ')" title="' . get_label('Try to apply it again.') . '"><img src="images/refresh.png" border="0"></button>';
			echo '</td>';
			
			echo '<td>';
			$this->club_pic->set($club_id, $club_name, $club_flags);
			$this->club_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td>';
			$event_pic->set($event_id, $event_name, $event_flags);
			$event_pic->show(ICONS_DIR, true, 48);
			echo '</td>';
			echo '<td align="center"><a href="view_game.php?id=' . $game_id . '&bck=1">' . get_label('Game #[0]', $game_id) . '</a></td>';
			echo '<td align="center">' . Game::feature_flags_to_leters($feature_flags) . '</td>';
			echo '<td align="center">' . Game::feature_flags_to_leters($removed_feature_flags) . '</td>';
			echo '<td>' . $issues . '</td>';
			echo '</tr>';
		}
		echo '</table>';
		show_pages_navigation(PAGE_SIZE, $count);
	}
	
	protected function js()
	{
?>		
		function rawEditGame(gameId, featureFlags)
		{
			dlg.form("form/game_raw_edit.php?game_id=" + gameId + "&features=" + featureFlags + "&issue", refr, 1200);
		}
		
		function deleteGameIssue(gameId, featureFlags)
		{
			dlg.yesNo("<?php echo get_label('Are you sure you want to delete game issue?'); ?>", null, null, function()
			{
				json.post("api/ops/game.php", { op: "delete_issue", game_id: gameId, features: featureFlags }, function()
				{
					dlg.info("<?php echo get_label('Game is accepted as is. Issue record is deleted.'); ?>", "<?php echo get_label('Game'); ?>" + gameId, undefined, refr);
				});
			});
		}
		
		function reapply(gameId)
		{
			json.post("api/ops/game.php", { op: "reapply_issue", game_id: gameId }, refr);
		}
<?php
	}
}

$page = new Page();
$page->run('Games with issues');

?>
