<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/player_stats.php';

define('VIEW_GAMES', 0);
define('VIEW_STATS', 1);
define('VIEW_COUNT', 2);

class Page extends EventPageBase
{
	protected function prepare()
	{
		global $_lang, $_profile;
		
		parent::prepare();
		
		$this->view = VIEW_GAMES;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < 0 || $this->view >= VIEW_COUNT)
			{
				$this->view = VIEW_GAMES;
			}
		}
		
		if (isset($_REQUEST['scoring_id']))
		{
			$this->scoring_id = (int)$_REQUEST['scoring_id'];
			if (isset($_REQUEST['scoring_version']))
			{
				$this->scoring_version = (int)$_REQUEST['scoring_version'];
				list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
			}
			else
			{
				list($this->scoring, $this->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->scoring_id);
			}
		}
		else
		{
			list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->scoring_id, $this->scoring_version);
		}
		$this->scoring = json_decode($this->scoring);
		
		$this->scoring_options = json_decode($this->scoring_options);
		if (isset($_REQUEST['scoring_ops']))
		{
			$ops = json_decode($_REQUEST['scoring_ops']);
			foreach($ops as $key => $value) 
			{
				$this->scoring_options->$key = $value;
			}
		}
		
		$this->user_id = 0;
		if (isset($_REQUEST['user_id']))
		{
			$this->user_id = (int)$_REQUEST['user_id'];
		}
		$this->is_me = ($_profile != NULL && $_profile->user_id == $this->user_id);
		
		if ($this->user_id > 0)
		{
			if ($this->is_me)
			{
				$this->tournament_flags &= ~(TOURNAMENT_HIDE_TABLE_MASK | TOURNAMENT_HIDE_BONUS_MASK);
			}
			$data = event_scores($this->id, $this->user_id, SCORING_LOD_PER_POLICY | SCORING_LOD_PER_GAME | SCORING_LOD_NO_SORTING, $this->scoring, $this->scoring_options, $this->tournament_flags, $this->round_num);
			if (isset($data[$this->user_id]))
			{
				$this->player = $data[$this->user_id];
			}
			else
			{
				$this->player = new stdClass();
				$this->player->id = $this->user_id;
				$this->player->games = array();
				$this->player->points = 0;
				list ($this->player->name, $this->player->flags, $this->player->nickname, $this->player->event_user_flags, $this->player->tournament_user_flags, $this->player->club_user_flags) = 
					Db::record(get_label('user'), 
						'SELECT nu.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags FROM users u' .
						' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
						' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' .
						' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
						' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
						' WHERE u.id = ?', $this->id, $this->tournament_id, $this->club_id, $this->user_id);
			}
		}
		else
		{
			$this->player = new stdClass();
			$this->player->id = $this->user_id;
			$this->player->games = array();
			$this->player->points = 0;
			$this->player->nickname = $this->player->name = '';
			$this->player->flags = $this->player->event_user_flags = $this->player->tournament_user_flags = $this->player->club_user_flags = 0;
		}
		
		
		$this->player_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
		$this->player_pic->
			set($this->player->id, $this->player->nickname, $this->player->event_user_flags, 'e' . $this->id)->
			set($this->player->id, $this->player->name, $this->player->tournament_user_flags, 't' . $this->tournament_id)->
			set($this->player->id, $this->player->name, $this->player->club_user_flags, 'c' . $this->club_id)->
			set($this->player->id, $this->player->name, $this->player->flags);
	}
	
	protected function show_body()
	{
		if (!$this->is_me && !$this->show_hidden_table_message())
		{
			return;
		}
		
		echo '<table class="transp" width="100%">';
		echo '<tr><td align="right">';
		echo get_label('Select a player') . ': ';
		show_user_input('user_name', '', 'must&event=' . $this->id, get_label('Select a player'), 'selectPlayer');
		if ($this->player->id > 0 && is_permitted(PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $this->club_id, $this->id, $this->tournament_id))
		{
			echo '</td><td align="right" width="20"><button class="icon" onclick="changeEventPlayer(' . $this->id . ', ' . $this->player->id . ', \'' . $this->player->name . '\')" title="' . get_label('Replace [0] with someone else in [1].', $this->player->name, $this->name) . '">';
			echo '<img src="images/user_change.png" border="0"></button>';
		}
		echo '</td></tr></table>';
		
		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_GAMES ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_GAMES . ',page:undefined})">' . get_label('Games') . '</button>';
		echo '<button ' . ($this->view == VIEW_STATS ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_STATS . ',page:undefined})">' . get_label('Stats') . '</button>';
		echo '</div>';

		switch ($this->view)
		{
			case VIEW_GAMES:
				$this->show_games();
				break;
			case VIEW_STATS:
				$this->show_stats();
				break;
		}
	}
	
	private function show_games()
	{
		global $_profile, $_scoring_groups;
		
		echo '<p>';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, 0, 0, $this->scoring_options, ' ', 'submitScoring', SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION | SCORING_SELECT_FLAG_NO_NORMALIZER);
		echo '</td></tr></table></p>';
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td rowspan="2">';
		echo '<table class="transp"><tr><td width="72">';
		if ($this->player->id > 0)
		{
			echo '<a href="user_info.php?bck=1&id=' . $this->player->id . '">';
			$this->player_pic->show(ICONS_DIR, false, 64);
			echo '</a>';
		}
		else
		{
			$this->player_pic->show(ICONS_DIR, false, 64);
		}
		echo '</td><td>';
			
		if (empty($this->player->nickname))
		{
			echo $this->player->name;
		}
		else
		{
			echo $this->player->nickname;
			if (!empty($this->player->name) && $this->player->name != $this->player->nickname)
			{
				echo ' (' . $this->player->name . ')';
			}
		}
		echo '</td></tr></table></td>';
		foreach ($_scoring_groups as $group)
		{
			$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			if ($count > 0)
			{
				if ($count == 1)
				{
					echo '<td width="36" align="center" rowspan="2">';
				}
				else
				{
					echo '<td width="36" align="center" colspan="' . $count . '">';
				}
				echo get_scoring_group_label($this->scoring, $group) . '</td>';
			}
		}
		echo '<td align="center" rowspan="2" class="darkest" width="36">∑</td>';
		echo '</tr>';
		
		echo '<tr class="th darker">';
		$letter = 'A';
		foreach ($_scoring_groups as $group)
		{
			$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			if ($count > 1)
			{
				$g = &$this->scoring->$group;
				for ($i = 0; $i < count($g); ++$i)
				{
					$policy = $g[$i];
					if (is_scoring_policy_on($policy, $this->scoring_options))
					{
						echo '<td width="36" align="center" title="' . get_scoring_policy_label($policy, true) . '">' . $letter . '</td>';
						++$letter;
					}
				}
			}
		}
		echo '</tr>';
		
		foreach ($this->player->games as $game)
		{
			echo '<tr align="center"><td><table width="100%" class="transp"><tr><td><a href="view_game.php?user_id=' . $this->player->id . '&event_id=' . $this->id . '&id=' . $game->game_id . $this->show_all . '&bck=1">' . get_label('Game #[0]', $game->game_id) . '</a></td>';
			echo '<td align="right" width="50">';
			switch ($game->role)
			{
				case 0: // civil;
					echo '<img src="images/civ.png" title="' . get_label('civil') . '" style="opacity: 0.5;">';
					break;
				case 1: // sherif;
					echo '<img src="images/sheriff.png" title="' . get_label('sheriff') . '" style="opacity: 0.5;">';
					break;
				case 2: // mafia;
					echo '<img src="images/maf.png" title="' . get_label('mafia') . '" style="opacity: 0.5;">';
					break;
				case 3: // don
					echo '<img src="images/don.png" title="' . get_label('don') . '" style="opacity: 0.5;">';
					break;
			}
			if ($game->won)
			{
				echo '<img src="images/won.png" title="' . get_label('win') . '" style="opacity: 0.8;">';
			}
			else
			{
				echo '<img src="images/lost.png" title="' . get_label('loss') . '" style="opacity: 0.8;">';
			}
			echo '</td></tr></table></td>';
			foreach ($_scoring_groups as $group)
			{
				$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
				if ($count > 0)
				{
					$gr1 = &$this->scoring->$group;
					$gr2 = &$game->$group;
					$count = get_scoring_group_policies_count($group, $this->scoring);
					for ($i = 0; $i < $count; ++$i)
					{
						if (is_scoring_policy_on($gr1[$i], $this->scoring_options))
						{
							$show_zeroes = ($group == SCORING_GROUP_NIGHT1 && ($game->flags & SCORING_FLAG_KILLED_FIRST_NIGHT) != 0);
							echo '<td>' . format_score($gr2[$i], $show_zeroes) . '</td>';
						}
					}
				}
			}
			echo '<td class="darker"><b>' . format_score($game->points, false) . '</b></td>';
			echo '</tr>';
		}
		
		$query = new DbQuery('SELECT points, reason, mvp FROM event_extra_points WHERE event_id = ? AND user_id = ?', $this->id, $this->user_id);
		$output_needed = true;
		while ($row = $query->next())
		{
			list($extra_points, $extra_reason) = $row;
			if (isset($this->scoring_options->weight))
			{
				$extra_points *= $this->scoring_options->weight;
			}
			
			$count = 1;
			foreach ($_scoring_groups as $group)
			{
				$count += get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			}
			
			echo '<tr align="center" class="dark" style="height:32px;"><td align="left" colspan="' . $count . '">' . $extra_reason . '</td>';
			echo '<td class="darker"><b>' . format_score($extra_points, false) . '</b></td>';
		}
		
		echo '<tr align="center" class="th darker"><td align="left">' . get_label('Total:') . '</td>';
		foreach ($_scoring_groups as $group)
		{
			$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			if ($count > 0)
			{
				$gr1 = &$this->scoring->$group;
				$gr2 = &$this->player->$group;
				$count = get_scoring_group_policies_count($group, $this->scoring);
				for ($i = 0; $i < $count; ++$i)
				{
					if (is_scoring_policy_on($gr1[$i], $this->scoring_options))
					{
						echo '<td>' . format_score($gr2[$i], false) . '</td>';
					}
				}
			}
		}
		echo '<td class="darkest">' . format_score($this->player->points, false) . '</td>';
		echo '</tr>';
		
		echo '</table>';
		
		$letter = 'A';
		echo '<p><dl>';
		foreach ($_scoring_groups as $group)
		{
			$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			if ($count > 1)
			{
				$gr = &$this->scoring->$group;
				$count = get_scoring_group_policies_count($group, $this->scoring);
				for ($i = 0; $i < $count; ++$i)
				{
					$policy = $gr[$i];
					if (is_scoring_policy_on($policy, $this->scoring_options))
					{
						echo '<b>' . $letter . ':</b> ' . get_scoring_policy_label($policy, true) . '<br>';
						++$letter;
					}
				}
			}
		}
		echo '</dl></p>';
		//print_json($this->player);
	}
	
	private function show_stats()
	{
		if (LOCK_DATE != NULL && !is_permitted(PERMISSION_ADMIN))
		{
			$dt = new DateTime(LOCK_DATE, new DateTimeZone(get_timezone()));
			if (time() < $dt->getTimestamp())
			{
				throw new Exc(get_label('Page is temporarily inavalable until [0].', LOCK_DATE));
			}
		}
			
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		echo '<p><table class="transp" width="100%"><tr><td>';
		show_roles_select($roles, 'rolesChanged()', get_label('Use stats of a specific role.'), ROLE_NAME_FLAG_SINGLE);
		echo '</td></tr></table></p>';
		
		$condition = new SQL(' AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.' AND g.event_id = ?', $this->id);
		$stats = new PlayerStats($this->user_id, $roles, $condition);
		$mafs_in_legacy = $stats->guess3maf * 3 + $stats->guess2maf * 2 + $stats->guess1maf;
		
		echo '<p><table class="bordered light" width="100%">';
		echo '<tr class="th-short darker"><td colspan="2">';
		
		echo '<table class="transp" width="100%"><tr><td width="72">';
		echo '<a href="user_info.php?bck=1&id=' . $this->user_id . '">';
		$this->player_pic->show(ICONS_DIR, false, 64);
		echo '</a>';
		echo '</td><td>' . $this->player->name . '</td></tr>';
		echo '</table>';
		
		echo '</td></tr>';
		
		echo '<tr><td class="dark" width="300">'.get_label('Games played').':</td><td>' . $stats->games_played . '</td></tr>';
		if ($stats->games_played > 0)
		{
			echo '<tr><td class="dark" width="300">'.get_label('Wins').':</td><td>' . $stats->games_won . ' (' . number_format($stats->games_won*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Earned rating').':</td><td>' . get_label('[0] ([1] per game)', number_format($stats->rating, 2), number_format($stats->rating/$stats->games_played, 3)) . '</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best player').':</td><td>' . $stats->best_player . ' (' . number_format($stats->best_player*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Best move').':</td><td>' . $stats->best_move . ' (' . number_format($stats->best_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Auto-bonus removed').':</td><td>' . $stats->worst_move . ' (' . number_format($stats->worst_move*100.0/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark">'.get_label('Bonus points').':</td><td>' . number_format($stats->bonus, 2) . ' (' . number_format($stats->bonus/$stats->games_played, 3) . ' ' . get_label('per game') . ')</td></tr>';
			echo '<tr><td class="dark">'.get_label('Killed first night').':</td><td>' . $stats->killed_first_night . ' (' . number_format($stats->killed_first_night*100.0/$stats->games_played, 1) . '%)</td></tr>';
			if ($stats->killed_first_night > 0)
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (' . number_format($stats->guess3maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (' . number_format($stats->guess2maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (' . number_format($stats->guess1maf*100.0/$stats->killed_first_night, 1) . '%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (' . number_format($mafs_in_legacy*100.0/($stats->killed_first_night * 3), 1) . '%)</td></tr>';
			}
			else
			{
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 3).':</td><td>' . $stats->guess3maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 2).':</td><td>' . $stats->guess2maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Guessed [0] mafia', 1).':</td><td>' . $stats->guess1maf . ' (0.0%)</td></tr>';
				echo '<tr><td class="dark">'.get_label('Mafia in legacy', 1).':</td><td>' . $mafs_in_legacy . ' (0.0%)</td></tr>';
			}
			echo '</table>';
		
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Voting') . '</td></tr>';
			
			$count = $stats->voted_civil + $stats->voted_mafia + $stats->voted_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Voted against civilians').':</td><td>';
			if ($stats->voted_civil > 0)
			{
				echo $stats->voted_civil . ' (' . number_format($stats->voted_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against mafia').':</td><td>';
			if ($stats->voted_mafia > 0)
			{
				echo $stats->voted_mafia . ' (' . number_format($stats->voted_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Voted against sheriff').':</td><td>';
			if ($stats->voted_sheriff > 0)
			{
				echo $stats->voted_sheriff . ' (' . number_format($stats->voted_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->voted_by_civil + $stats->voted_by_mafia + $stats->voted_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by civilians').':</td><td>';
			if ($stats->voted_by_civil > 0)
			{
				echo $stats->voted_by_civil . ' (' . number_format($stats->voted_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by mafia').':</td><td>';
			if ($stats->voted_by_mafia > 0)
			{
				echo $stats->voted_by_mafia . ' (' . number_format($stats->voted_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was voted by sheriff').':</td><td>';
			if ($stats->voted_by_sheriff > 0)
			{
				echo $stats->voted_by_sheriff . ' (' . number_format($stats->voted_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Nominating') . '</td></tr>';
			
			$count = $stats->nominated_civil + $stats->nominated_mafia + $stats->nominated_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Nominated civilians').':</td><td>';
			if ($stats->nominated_civil > 0)
			{
				echo $stats->nominated_civil . ' (' . number_format($stats->nominated_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated mafia').':</td><td>';
			if ($stats->nominated_mafia > 0)
			{
				echo $stats->nominated_mafia . ' (' . number_format($stats->nominated_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Nominated sheriff').':</td><td>';
			if ($stats->nominated_sheriff > 0)
			{
				echo $stats->nominated_sheriff . ' (' . number_format($stats->nominated_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			
			$count = $stats->nominated_by_civil + $stats->nominated_by_mafia + $stats->nominated_by_sheriff;
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by civilians').':</td><td>';
			if ($stats->nominated_by_civil > 0)
			{
				echo $stats->nominated_by_civil . ' (' . number_format($stats->nominated_by_civil*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by mafia').':</td><td>';
			if ($stats->nominated_by_mafia > 0)
			{
				echo $stats->nominated_by_mafia . ' (' . number_format($stats->nominated_by_mafia*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Was nominated by sheriff').':</td><td>';
			if ($stats->nominated_by_sheriff > 0)
			{
				echo $stats->nominated_by_sheriff . ' (' . number_format($stats->nominated_by_sheriff*100.0/$count, 1) . '%)';
			}
			echo '&nbsp;</td></tr>';
			echo '</table></p>';
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Surviving') . '</td></tr>';
			foreach ($stats->surviving as $surviving)
			{
				switch ($surviving->type)
				{
					case KILL_TYPE_SURVIVED:
						echo '<tr><td class="dark" width="300">'.get_label('Survived').':</td><td>';
						break;
					case KILL_TYPE_DAY:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in day').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_NIGHT:
						echo '<tr><td class="dark" width="300">'.get_label('Killed in night').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_WARNINGS:
						echo '<tr><td class="dark" width="300">'.get_label('Killed by warnings in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_GIVE_UP:
						echo '<tr><td class="dark" width="300">'.get_label('Left the game in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Kicked out in round').' ' . $surviving->round . ':</td><td>';
						break;
					case KILL_TYPE_TEAM_KICK_OUT:
						echo '<tr><td class="dark" width="300">'.get_label('Made the opposite team win').' ' . $surviving->round . ':</td><td>';
						break;
					default:
						echo '<tr><td class="dark" width="300">'.get_label('Round').' ' . $surviving->round . ':</td><td>';
						break;
				}
				echo $surviving->count . ' (' . number_format($surviving->count*100.0/$stats->games_played, 2) . '%)</td></tr>';
			}
			echo '</table></p>';
			
			if ($roles == POINTS_BLACK || $roles == POINTS_MAFIA || $roles == POINTS_DON)
			{
				$mafia_stats = new MafiaStats($this->user_id, $roles, $condition);
				echo '<p><table class="bordered light" width="100%">';
				echo '<tr class="th-short darker"><td colspan="2">' . get_label('Mafia shooting') . '</td></tr>';
				
				$count = $mafia_stats->shots3_ok + $mafia_stats->shots3_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('3 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots3_ok . ' '.get_label('success;').' ' . $mafia_stats->shots3_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots3_ok*100/$count, 1) . get_label('% success rate.');
					if ($mafia_stats->shots3_fail > 0)
					{
						echo $mafia_stats->shots3_fail . ' '.get_label('times guilty in misses.');
					}
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots2_ok + $mafia_stats->shots2_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('2 mafia shooters').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots2_ok . ' '.get_label('success;').' ' . $mafia_stats->shots2_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots2_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				
				$count = $mafia_stats->shots1_ok + $mafia_stats->shots1_miss;
				if ($count > 0)
				{
					echo '<tr><td class="dark" width="300">'.get_label('Single shooter').':</td><td>' . $count . ' '.get_label('nights').': ';
					echo $mafia_stats->shots1_ok . ' '.get_label('success;').' ' . $mafia_stats->shots1_miss . ' '.get_label('fail.').' ';
					echo number_format($mafia_stats->shots1_ok*100/$count, 1) . get_label('% success rate.');
					echo '</td></tr>';
				}
				echo '</table></p>';
			}
			
			if ($roles == POINTS_SHERIFF)
			{
				$sheriff_stats = new SheriffStats($this->user_id, $condition);
				$count = $sheriff_stats->civil_found + $sheriff_stats->mafia_found;
				if ($count > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Sheriff stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Red checks').':</td><td>' . $sheriff_stats->civil_found . ' (' . number_format($sheriff_stats->civil_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->civil_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Black checks').':</td><td>' . $sheriff_stats->mafia_found . ' (' . number_format($sheriff_stats->mafia_found*100/$count, 1) . '%) - ' . number_format($sheriff_stats->mafia_found/$sheriff_stats->games_played, 2) . ' '.get_label('per game').'</td></tr>';
					echo '</table></p>';
				}
			}
			
			if ($roles == POINTS_DON)
			{
				$don_stats = new DonStats($this->user_id, $condition);
				if ($don_stats->games_played > 0)
				{
					echo '<p><table class="bordered light" width="100%">';
					echo '<tr class="th-short darker"><td colspan="2">' . get_label('Don stats') . '</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found').':</td><td>' . $don_stats->sheriff_found . ' ' . $don_stats->games_played . '(' . number_format($don_stats->sheriff_found*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff arranged').':</td><td>' . $don_stats->sheriff_arranged . ' (' . number_format($don_stats->sheriff_arranged*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff found first night').':</td><td>' . $stats->sheriff_found_first_night . ' (' . number_format($stats->sheriff_found_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '<tr><td class="dark" width="300">'.get_label('Sheriff killed first night').':</td><td>' . $stats->sheriff_killed_first_night . ' (' . number_format($stats->sheriff_killed_first_night*100/$don_stats->games_played, 1) . '%)</td></tr>';
					echo '</table></p>';
				}
			}
			
			echo '<p><table class="bordered light" width="100%">';
			echo '<tr class="th-short darker"><td colspan="2">' . get_label('Miscellaneous') . '</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Warnings').':</td><td>' . $stats->warnings . ' (' . number_format($stats->warnings/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Arranged by mafia').':</td><td>' . $stats->arranged . ' (' . number_format($stats->arranged/$stats->games_played, 2) . ' '.get_label('per game').')</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by don').':</td><td>' . $stats->checked_by_don . ' (' . number_format($stats->checked_by_don*100/$stats->games_played, 1) . '%)</td></tr>';
			echo '<tr><td class="dark" width="300">'.get_label('Checked by sheriff').':</td><td>' . $stats->checked_by_sheriff . ' (' . number_format($stats->checked_by_sheriff*100/$stats->games_played, 1) . '%)</td></tr>';
		}
		echo '</table></p>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function selectPlayer(data)
		{
			goTo({ 'user_id': data.id });
		}
		
		function submitScoring(s)
		{
			goTo({ scoring_id: s.sId, scoring_version: s.sVer, scoring_ops: s.ops });
		}
		
		function changeEventPlayer(eventId, userId, nickname)
		{
			dlg.form("form/event_change_player.php?event_id=" + eventId + "&user_id=" + userId + "&nick=" + nickname, function(r)
			{
				goTo({ 'user_id': r.user_id });
			});
		}
		
		function rolesChanged()
		{
			goTo({roles: $('#roles').val(), page: undefined});
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('player details'));

?>
