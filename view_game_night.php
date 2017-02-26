<?php

require_once 'include/view_game.php';

class Page extends ViewGamePageBase
{
	protected function show_body()
	{
		$round = ($this->gametime >> 1) - 1;
		
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="th darkest"><td width="30">&nbsp;</td>';
		echo '<td>'.get_label('Player').'</td>';
		echo '<td width="60">'.get_label('Shooting').'</td>';
		echo '<td width="100">'.get_label('Checked by').'</td>';
		echo '<td width="100">'.get_label('Result').'</td>';
		echo '<td width="60">'.get_label('Role').'</td></tr>';
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->vg->gs->players[$i];
			if ($player->kill_round >= 0 && $player->kill_round < $round)
			{
				continue;
			}
			
			if ($player->kill_round == $round && $player->state == PLAYER_STATE_KILLED_DAY)
			{
				continue;
			}

			if ($player->id == $this->vg->mark_player)
			{
				echo '<tr class="light">';
			}
			else
			{
				echo '<tr class="dark">';
			}
			
			echo '<td class="darker" align="center">' . ($i + 1) . '</td>';
			echo '<td><a href="view_game_stats.php?num=' . $i . '&bck=1">' . cut_long_name($player->nick, 55) . '</a></td>';
			
			echo '<td>';
			if ($player->role == PLAYER_ROLE_MAFIA || $player->role == PLAYER_ROLE_DON)
			{
				foreach ($this->vg->gs->shooting[$round] as $shooter => $victim)
				{
					if ($shooter == $i)
					{
						echo ($victim + 1);
					}
				}
			}
			echo '&nbsp;</td>';
			
			echo '<td>';
			if ($player->sheriff_check == $round)
			{
				echo get_label('sheriff');
				if ($player->don_check == $round)
				{
					echo ', '.get_label('don');
				}
			}
			else if ($player->don_check == $round)
			{
				echo get_label('don');
			}
			echo '&nbsp;</td>';
			
			echo '<td>';
			if ($player->kill_round == $round && $player->state == PLAYER_STATE_KILLED_NIGHT)
			{
				switch ($player->kill_reason)
				{
					case KILL_REASON_SUICIDE:
						echo get_label('suicide');
						break;
					case KILL_REASON_WARNINGS:
						echo get_label('4 warnings');
						break;
					case KILL_REASON_KICK_OUT:
						echo get_label('kicked out');
						break;
					case KILL_REASON_NORMAL:
						echo get_label('killed');
						break;
				}
			}
			echo '&nbsp;</td>';
			
			echo '<td>' . $player->role_text(false) . '</td>';
			
			echo '</tr>';
		}
		echo '</table>';
		
		parent::show_body();
	}
}

$page = new Page();
$page->run(get_label('Game'), PERM_ALL);

?>