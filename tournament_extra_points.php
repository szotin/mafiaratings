<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

class Page extends TournamentPageBase
{
	private $players;
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(
			PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER |
			PERMISSION_CLUB_MODERATOR | PERMISSION_TOURNAMENT_MODERATOR,
			$this->club_id, $this->id);
		
		list ($count) = Db::record(get_label('extra points'), 'SELECT count(*) FROM event_extra_points p JOIN events e ON p.event_id = e.id WHERE e.tournament_id = ?', $this->id);
		show_pages_navigation(PAGE_SIZE, $count);
		
		$tournament_user_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));

		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="60">';
		echo '<button class="icon" onclick="addPoints(' . $this->id . ')"><img src="images/create.png" border="0"></button>';
		echo '</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="100" align="center">' . get_label('Round') . '</td>';
		echo '<td width="180" align="center">' . get_label('Reason') . '</td>';
		echo '<td width="80" align="center">' . get_label('Points') . '</td>';
		echo '</tr>';
		
		$query = new DbQuery(
			'SELECT p.id, e.id, e.name, e.flags, u.id, u.name, u.flags, p.reason, p.details, p.points, eu.nickname, eu.flags, tu.flags, cu.flags' . 
				' FROM event_extra_points p' . 
				' JOIN users u ON u.id = p.user_id' . 
				' JOIN events e ON e.id = p.event_id' . 
				' LEFT OUTER JOIN event_users eu ON eu.event_id = e.id AND eu.user_id = u.id' .
				' LEFT OUTER JOIN tournament_users tu ON tu.tournament_id = e.tournament_id AND tu.user_id = u.id' .
				' LEFT OUTER JOIN club_users cu ON cu.club_id = e.club_id AND cu.user_id = u.id' .
				' WHERE e.tournament_id = ?' .
				' ORDER BY e.start_time, e.id, p.id', $this->id);
		while ($row = $query->next())
		{
			list($points_id, $event_id, $event_name, $event_flags, $user_id, $user_name, $user_flags, $reason, $details, $points, $user_nickname, $event_user_flags, $tournament_user_flags, $club_user_flags) = $row;
			
			echo '<tr>';
			echo '<td valign="center">';
			echo '<button class="icon" onclick="deletePoints(' . $points_id . ')"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="editPoints(' . $points_id . ')"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			echo '<td width="60" align="center">';
			$this->user_pic->set($user_id, $user_name, $user_flags);
			$tournament_user_pic->
				set($user_id, $user_name, $tournament_user_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_user_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			$tournament_user_pic->show(ICONS_DIR, true, 50);
			echo '</td><td>';
			echo '<a href="user_info.php?id=' . $user_id . '&bck=1">' . $user_name . '</a></td>';
			echo '<td align="center">' . $event_name . '</td>';
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
		function addPoints(tournamentId)
		{
			dlg.form("form/extra_points_add.php?tournament_id=" + tournamentId, refr);
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
			dlg.form("form/extra_points_edit.php?points_id=" + pointsId, refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Extra points'));

?>