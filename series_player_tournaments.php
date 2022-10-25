<?php

require_once 'include/series.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/tournament.php';

class Page extends SeriesPageBase
{
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		
		if (isset($_REQUEST['gaining_id']))
		{
			$this->gaining_id = (int)$_REQUEST['gaining_id'];
			if (isset($_REQUEST['gaining_version']))
			{
				$this->gaining_version = (int)$_REQUEST['gaining_version'];
				list($this->gaining) =  Db::record(get_label('gaining system'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $this->gaining_id, $this->gaining_version);
			}
			else
			{
				list($this->gaining, $this->gaining_version) = Db::record(get_label('gaining'), 'SELECT gaining, version FROM gaining_versions WHERE gaining_id = ? ORDER BY version DESC LIMIT 1', $this->gaining_id);
			}
		}
		else
		{
			list($this->gaining) =  Db::record(get_label('gaining'), 'SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = ?', $this->gaining_id, $this->gaining_version);
		}
		$this->gaining = json_decode($this->gaining);
		
		$this->user_id = 0;
		if (!isset($_REQUEST['user_id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$this->user_id = (int)$_REQUEST['user_id'];
		list($this->user_name, $this->user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $this->user_id);
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_scoring_groups;
		
		// echo '<p>';
		// echo '<table class="transp" width="100%">';
		// echo '<tr><td>';
		// show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'submitScoring', $scoring_select_flags);
		// echo '</td><td align="right">';
		// echo get_label('Select a player') . ': ';
		// show_user_input('user_name', $this->player->name, 'tournament=' . $this->id, get_label('Select a player'), 'selectPlayer');
		// if ($this->player->id > 0 && is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id))
		// {
			// echo '</td><td><button class="icon" onclick="changeTournamentPlayer(' . $this->id . ', ' . $this->player->id . ', \'' . $this->player->name . '\')" title="' . get_label('Replace [0] with someone else in [1].', $this->player->name, $this->name) . '">';
			// echo '<img src="images/user_change.png" border="0"></button>';
		// }
		// echo '</td></tr></table></p>';
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker" align="center"><td>';
		echo '<table class="transp" width="100%"><tr><td width="72">';
		echo '<a href="user_info.php?bck=1&id=' . $this->user_id . '">';
		$player_pic = new Picture(USER_PICTURE);
		$player_pic->set($this->user_id, $this->user_name, $this->user_flags);
		$player_pic->show(ICONS_DIR, false, 64);
		echo '</a>';
		echo '</td><td>' . $this->user_name . '</td></tr>';
		echo '</table>';
		echo '</td>';

		echo '<td width="80">Place</td>';
		echo '<td width="80">Points</td>';
		echo '<td width="80">Players participated</td>';
		echo '</tr>';
		
		$tournament_pic = new Picture(TOURNAMENT_PICTURE);
		$club_pic = new Picture(CLUB_PICTURE);
		
		$sum = 0;
		$query = new DbQuery('SELECT t.id, t.name, t.flags, c.id, c.name, c.flags, s.stars, p.place, (SELECT count(user_id) FROM tournament_places WHERE tournament_id = t.id) as players FROM tournament_places p JOIN tournaments t ON t.id = p.tournament_id JOIN series_tournaments s ON s.tournament_id = t.id AND s.series_id = ? JOIN clubs c ON c.id = t.club_id WHERE p.user_id = ? ORDER BY t.start_time DESC', $this->id, $this->user_id);
		while ($row = $query->next())
		{
			list($tournament_id, $tournament_name, $tournament_flags, $club_id, $club_name, $club_flags, $stars, $place, $players_count) = $row;
			echo '<tr align="center"><td>';
			echo '<table width="100%" class="transp"><tr><td width="58"><a href="tournament_player_games.php?user_id=' . $this->user_id . '&id=' . $tournament_id . '&bck=1">';
			$tournament_pic->set($tournament_id, $tournament_name, $tournament_flags);
			$tournament_pic->show(ICONS_DIR, false, 50);
			echo '</a></td><td><a href="tournament_player_games.php?user_id=' . $this->user_id . '&id=' . $tournament_id . '&bck=1">' . $tournament_name . '</a></td>';
			echo '<td align="right" width="120"><font style="color:#B8860B; font-size:20px;">' . tournament_stars_str($stars) . '</font></td>';
			echo '<td align="right" width="42">';
			$club_pic->set($club_id, $club_name, $club_flags);
			$club_pic->show(ICONS_DIR, false, 40);
			echo '</a></td>';
			echo '</tr></table></td>';
			
			$score = get_gaining_points($this->gaining, $stars, $players_count, $place);
			echo '<td><a href="javascript:showGaining(' . $players_count . ', ' . $stars . ', ' . $place . ')">' . $place . '</a></td>';
			echo '<td class="dark">' . format_score($score) . '</td>';
			echo '<td>' . $players_count . '</td>';
			echo '</tr>';
			$sum += $score;
		}
		echo '<tr class="darker" style="height:50px;"><td colspan="2"><b>' . get_label('Total') . ':</b></td><td align="center"><b>' . format_score($sum) . '</b></td><td></td></tr>';
		echo '</table>';
		
	}
	
	protected function js()
	{
		parent::js();
?>
	
		function showGaining(players, stars, place)
		{
			dlg.infoForm('form/gaining_show.php?id=<?php echo $this->gaining_id; ?>&version=<?php echo $this->gaining_version; ?>&players=' + players + '&stars=' + stars + '&place=' + place);
		}
		
		function selectPlayer(data)
		{
			goTo({ 'user_id': data.id });
		}
		
		function submitScoring(s)
		{
			goTo({ sid: s.sId, sver: s.sVer, nid: s.nId, nver: s.nVer, sops: s.ops });
		}
		
		function changeTournamentPlayer(tournamentId, userId, nickname)
		{
			dlg.form("form/tournament_change_player.php?tournament_id=" + tournamentId + "&user_id=" + userId + "&nick=" + nickname, function(r)
			{
				goTo({ 'user_id': r.user_id });
			});
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('player details'));

?>
