<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

class Page extends TournamentPageBase
{
	protected function prepare()
	{
		parent::prepare();
		check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id);
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		$tournament_reg_pic =
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic));
		
		if (($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
			echo '<p><big>' . get_label('Warning! This tournament scoring is auto-calculated from the games results. Changing scoring will turn this auto-calculation off.') . '</big></p>';
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center">';
		echo '<td width="80">';
		echo '<button class="icon" onclick="setScore()" title="' . get_label('Create [0]', get_label('score')) . '"><img src="images/create.png" border="0"></button>';
		echo '</td>';
		echo '<td width="40"></td>';
		echo '<td colspan="3" align="left">'.get_label('Player').'</td>';
		echo '<td width="72">' . get_label('Sum') . '</td>';
		echo '<td width="72">' . get_label('Main') . '</td>';
		echo '<td width="72">' . get_label('Bonus') . '</td>';
		echo '<td width="72">' . get_label('FK') . '</td>';
		echo '<td width="72">' . get_label('Games played') . '</td>';
		echo '</tr>';
		
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, c.id, c.name, c.flags, p.place, p.main_points, p.bonus_points, p.shot_points, p.games_count, tu.flags, cu.flags FROM tournament_places p' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN tournament_regs tu ON tu.tournament_id = p.tournament_id AND tu.user_id = p.user_id' .
			' LEFT OUTER JOIN club_regs cu ON cu.club_id = ? AND cu.user_id = p.user_id' .
			' WHERE p.tournament_id = ? ORDER BY p.place', $this->club_id, $this->id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_flags, $club_id, $club_name, $club_flags, $place, $main_points, $bonus_points, $shot_points, $games_count, $tournament_reg_flags, $club_reg_flags) = $row;
			$sum = $main_points;
			if (!is_null($bonus_points))
			{
				$sum += $bonus_points;
			}
			if (!is_null($shot_points))
			{
				$sum += $shot_points;
			}
			
			echo '<tr>';
			echo '<td align="center">';
			echo '<button class="icon" onclick="removeScore(' . $user_id . ')" title="' . get_label('Remove [0]', get_label('score')) . '"><img src="images/delete.png" border="0"></button>';
			echo '<button class="icon" onclick="setScore(' . $user_id . ')" title="' . get_label('Edit [0]', get_label('score')) . '"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			
			echo '<td align="center" class="dark">' . $place . '</td>';
			
			echo '<td width="50"><a href="tournament_player.php?user_id=' . $user_id . '&id=' . $this->id . '&bck=1">';
			$tournament_reg_pic->
				set($user_id, $user_name, $tournament_reg_flags, 't' . $this->id)->
				set($user_id, $user_name, $club_reg_flags, 'c' . $this->club_id)->
				set($user_id, $user_name, $user_flags);
			$tournament_reg_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="tournament_player.php?user_id=' . $user_id . '&id=' . $this->id . '&bck=1">' . $user_name . '</a></td>';
			echo '<td width="50" align="center">';
			if (!is_null($club_id) && $club_id > 0)
			{
				$this->club_pic->set($club_id, $club_name, $club_flags);
				$this->club_pic->show(ICONS_DIR, true, 40);
			}
			
			echo '</td>';
			echo '<td align="center" class="dark">' . format_score($sum) . '</td>';
			echo '<td align="center">' . format_score($main_points) . '</td>';
			echo '<td align="center">' . (is_null($bonus_points) ? '' : format_score($bonus_points)) . '</td>';
			echo '<td align="center">' . (is_null($shot_points) ? '' : format_score($shot_points)) . '</td>';
			echo '<td align="center">' . (is_null($games_count) ? '' : $games_count) . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		if (($this->flags & TOURNAMENT_FLAG_MANUAL_SCORE) == 0)
		{
?>
			function prompt(callback)
			{
				dlg.yesNo(
					'<?php echo get_label('<p>Attention! Adding/deleting scores will turn this tournament to manually edited. Do it only for the tournaments where some games are missing from the games list.</p><p>Do you want to edit the results of this tournament manually?</p>'); ?>', 
					null,
					null,
					function()
					{
						json.post(
							"api/ops/tournament.php", 
							{ 
								op: "change", 
								tournament_id: <?php echo $this->id; ?>, 
								flags: <?php echo $this->flags | TOURNAMENT_FLAG_MANUAL_SCORE; ?> 
							}, callback);
					});
			}
<?php
		}
		else
		{
?>
			function prompt(callback, param)
			{
				callback(param);
			}
<?php
		}
?>

		function setScore(userId)
		{
			prompt(function()
			{
				let url = "form/tournament_score_set.php?tournament_id=" + <?php echo $this->id; ?>;
				if (userId)
					url += "&user_id=" + userId;
				dlg.form(url, refr, 400, refr);
			});
		}
		
		function editScore(userId)
		{
			prompt(function()
			{
				dlg.form("form/tournament_score_set.php?user_id=" + userId + "&tournament_id=" + <?php echo $this->id; ?>, refr, 600, refr);
			});
		}
		
		function removeScore(userId)
		{
			dlg.yesNo(
				'<?php echo get_label('Are you sure you want to remove the score?'); ?>', 
				null,
				null,
				function()
				{
					prompt(function()
					{
						json.post(
							"api/ops/tournament.php",
							{ 
								op: "remove_score", 
								tournament_id: <?php echo $this->id; ?>, 
								user_id: userId
							}, 
							refr);
					});
				});
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Edit standings'));

?>
