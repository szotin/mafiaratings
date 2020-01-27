<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/user.php';
require_once 'include/scoring.php';

class Page extends EventPageBase
{
	protected function prepare()
	{
		global $_page;
		
		parent::prepare();
		
		if (isset($_REQUEST['scoring_id']))
		{
			$this->event->scoring_id = (int)$_REQUEST['scoring_id'];
			if (isset($_REQUEST['scoring_version']))
			{
				$this->event->scoring_version = (int)$_REQUEST['scoring_version'];
				list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->event->scoring_id, $this->event->scoring_version);
			}
			else
			{
				list($this->scoring, $this->event->scoring_version) = Db::record(get_label('scoring'), 'SELECT scoring, version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $this->event->scoring_id);
			}
		}
		else
		{
			list($this->scoring) =  Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? AND version = ?', $this->event->scoring_id, $this->event->scoring_version);
		}
		$this->scoring = json_decode($this->scoring);
		
		$this->scoring_options = json_decode($this->event->scoring_options);
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
		
		$this->user_name = '';
		if ($this->user_id > 0)
		{
			$data = event_scores($this->event->id, $this->user_id, SCORING_LOD_PER_POLICY | SCORING_LOD_PER_GAME | SCORING_LOD_NO_SORTING, $this->scoring, $this->scoring_options);
			$this->player = $data[$this->user_id];
			$this->user_name = $this->player->name;
		}
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_scoring_groups;
		
		echo '<p>';
		echo '<table class="transp" width="100%">';
		echo '<tr><td>';
		show_scoring_select($this->event->club_id, $this->event->scoring_id, $this->event->scoring_version, $this->scoring_options, ' ', 'submitScoring', SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION);
		echo '</td><td align="right">';
		echo get_label('Select a player') . ': ';
		show_user_input('user_name', $this->user_name, 'event=' . $this->event->id, get_label('Select a player'), 'selectPlayer');
		echo '</td></tr></table></p>';
			
		if ($this->user_id <= 0)
		{
			return;
		}
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td rowspan="2">';
		echo '<table class="transp"><tr><td width="72"><a href="user_info.php?id=' . $this->player->id . '&bck=1">';
		$this->user_pic->set($this->player->id, $this->player->name, $this->player->flags);
		$this->user_pic->show(ICONS_DIR, 64);
		echo '</a></td><td>' . $this->player->name . '</td></tr></table>';
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
			echo '<tr align="center"><td><table width="100%" class="transp"><tr><td><a href="view_game.php?user_id=' . $this->player->id . '&event_id=' . $this->event->id . '&id=' . $game->game_id . '&bck=1">' . get_label('Game #[0]', $game->game_id) . '</a></td>';
			echo '<td align="right">';
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
							echo '<td>' . format_score($gr2[$i], false) . '</td>';
						}
					}
				}
			}
			echo '<td class="darker"><b>' . format_score($game->points, false) . '</b></td>';
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
		
		function submitScoring(scoringId, scoringVer, scoringOps)
		{
			goTo({ scoring_id: scoringId, scoring_version: scoringVer, scoring_ops: scoringOps });
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('player details'));

?>
