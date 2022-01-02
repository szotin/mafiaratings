<?php

require_once 'include/tournament.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

function score_title($points, $raw_points, $normalization)
{
	if ($normalization != 1 && $points != 0)
	{
		return ' title="' . format_score($raw_points) . ' * ' . format_coeff($normalization) . ' = ' . format_score($points) . '"';
	}
	return '';
}

class Page extends TournamentPageBase
{
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		
		if (isset($_REQUEST['sid']))
		{
			$this->scoring_id = (int)$_REQUEST['sid'];
			if (isset($_REQUEST['sver']))
			{
				$this->scoring_version = (int)$_REQUEST['sver'];
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
		
		$this->normalizer = '{}';
		if (isset($_REQUEST['nid']))
		{
			$this->normalizer_id = (int)$_REQUEST['nid'];
			$this->tournament_player_params .= '&nid=' . $this->normalizer_id;
			if ($this->normalizer_id > 0)
			{
				if (isset($_REQUEST['nver']))
				{
					$this->normalizer_version = (int)$_REQUEST['nver'];
					$this->tournament_player_params .= '&nver=' . $this->normalizer_version;
					list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
				}
				else
				{
					list($this->normalizer, $this->normalizer_version) = Db::record(get_label('normalizer'), 'SELECT normalizer, version FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $this->normalizer_id);
				}
			}
		}
		else if (!is_null($this->normalizer_id) && $this->normalizer_id > 0)
		{
			list($this->normalizer) =  Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $this->normalizer_id, $this->normalizer_version);
		}
		$this->normalizer = json_decode($this->normalizer);
		
		$this->scoring_options = json_decode($this->scoring_options);
		if (isset($_REQUEST['sops']))
		{
			$ops = json_decode($_REQUEST['sops']);
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
		
		if ($this->user_id > 0)
		{
			$data = tournament_scores($this->id, $this->flags, $this->user_id, SCORING_LOD_PER_POLICY | SCORING_LOD_PER_GAME | SCORING_LOD_NO_SORTING, $this->scoring, $this->normalizer, $this->scoring_options);
			if (isset($data[$this->user_id]))
			{
				$this->player = $data[$this->user_id];
			}
			else
			{
				$this->player = new stdClass();
				$this->player->id = $this->user_id;
				$this->player->games = array();
				$this->player->normalization = 1;
				$this->player->points = 0;
				$this->player->raw_points = 0;
				list ($this->player->name, $this->player->flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $this->user_id);
			}
		}
		else
		{
			$this->player = new stdClass();
			$this->player->id = $this->user_id;
			$this->player->games = array();
			$this->player->normalization = 1;
			$this->player->points = 0;
			$this->player->raw_points = 0;
			$this->player->name = '';
			$this->player->flags = 0;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_scoring_groups;
		
		if ($this->flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_OPTIONS;
		}
		else
		{
			$scoring_select_flags = SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION;
		}
		
		echo '<p>';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->club_id, $this->scoring_id, $this->scoring_version, $this->normalizer_id, $this->normalizer_version, $this->scoring_options, ' ', 'submitScoring', $scoring_select_flags);
		echo '</td><td align="right">';
		echo get_label('Select a player') . ': ';
		show_user_input('user_name', $this->player->name, 'tournament=' . $this->id, get_label('Select a player'), 'selectPlayer');
		echo '</td></tr></table></p>';
			
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td rowspan="2">';
		echo '<table class="transp" width="100%"><tr><td width="72">';
		$this->user_pic->set($this->player->id, $this->player->name, $this->player->flags);
		$this->user_pic->show(ICONS_DIR, true, 64);
		echo '</td><td>' . $this->player->name . '</td></tr>';
		if ($this->player->normalization != 1)
		{
			echo '<tr><td colspan="3" align="center"><button onclick="mr.showPlayerTournamentNorm(' . $this->player->id . ', ' . $this->id . ')" title="' . get_label('Click here to see how normalization rate is calculated for [0].', $this->player->name) . '">' . get_label('Normalization rate') . ': ' . format_coeff($this->player->normalization) . '</button></td></tr>';
		}
		echo '</table>';
		echo '</td>';
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
				echo get_scoring_group_label($group) . '</td>';
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
						echo '<td width="36" align="center" title="' . get_scoring_matter_label($policy, true) . '">' . $letter . '</td>';
						++$letter;
					}
				}
			}
		}
		echo '</tr>';
		
		foreach ($this->player->games as $game)
		{
			echo '<tr align="center"><td><table width="100%" class="transp"><tr><td><a href="view_game.php?user_id=' . $this->player->id . '&tournament_id=' . $this->id . '&id=' . $game->game_id . '&bck=1">' . get_label('Game #[0]', $game->game_id) . '</a></td>';
			echo '<td align="right">';
			if (isset($game->event_name) && is_string($game->event_name))
			{
				echo $game->event_name;
			}
			echo '</td><td align="right" width="50">';
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
					$raw_gname = 'raw_' . $group;
					$raw_rg2 = &$game->$raw_gname;
					$count = get_scoring_group_policies_count($group, $this->scoring);
					for ($i = 0; $i < $count; ++$i)
					{
						if (is_scoring_policy_on($gr1[$i], $this->scoring_options))
						{
							$show_zeroes = ($group == SCORING_GROUP_NIGHT1 && ($game->flags & SCORING_FLAG_KILLED_FIRST_NIGHT) != 0);
							echo '<td' . score_title($gr2[$i], $raw_rg2[$i], $this->player->normalization) . '>' . format_score($gr2[$i], $show_zeroes) . '</td>';
						}
					}
				}
			}
			echo '<td class="darker"' . score_title($game->points, $game->raw_points, $this->player->normalization) . '><b>' . format_score($game->points, false) . '</b></td>';
			echo '</tr>';
		}
		
		echo '<tr align="center" class="th darker"><td align="left">' . get_label('Total:') . '</td>';
		foreach ($_scoring_groups as $group)
		{
			$count = get_scoring_group_policies_count($group, $this->scoring, $this->scoring_options);
			if ($count > 0)
			{
				$gr1 = &$this->scoring->$group;
				$gr2 = &$this->player->$group;
				$raw_gname = 'raw_' . $group;
				$raw_rg2 = &$this->player->$raw_gname;
				$count = get_scoring_group_policies_count($group, $this->scoring);
				for ($i = 0; $i < $count; ++$i)
				{
					if (is_scoring_policy_on($gr1[$i], $this->scoring_options))
					{
						echo '<td' . score_title($gr2[$i], $raw_rg2[$i], $this->player->normalization) . '>' . format_score($gr2[$i], false) . '</td>';
					}
				}
			}
		}
		echo '<td class="darkest"' . score_title($this->player->points, $this->player->raw_points, $this->player->normalization) . '>' . format_score($this->player->points, false) . '</td>';
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
						echo '<b>' . $letter . ':</b> ' . get_label('points') . ' ' . get_scoring_matter_label($policy, true) . '<br>';
						++$letter;
					}
				}
			}
		}
		echo '</dl></p>';
		//print_json($this->player);
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
			goTo({ sid: s.sId, sver: s.sVer, nid: s.nId, nver: s.nVer, sops: s.ops });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('player details'));

?>
