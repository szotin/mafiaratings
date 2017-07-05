<?php

require_once 'include/view_game.php';

class Page extends ViewGamePageBase
{
	protected function show_body()
	{
		$round = ($this->gametime >> 1) - 1;
	
		$voting = NULL;
		$voting1 = NULL;
		foreach ($this->vg->gs->votings as $v)
		{
			if ($v->round == $round)
			{
				if ($v->voting_round == 0)
				{
					$voting = $v;
					if ($voting1 != NULL)
					{
						break;
					}
				}
				else
				{
					$voting1 = $v;
					if ($voting != NULL)
					{
						break;
					}
				}
			}
		}
		
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="th darker"><td width="30">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		if ($voting != NULL && !$voting->is_canceled())
		{
			echo '<td width="80" align="center">'.get_label('Nominated').'</td>';
			echo '<td width="80" align="center">'.get_label('Voted for').'</td>';
		}
		echo '<td width="80" align="center">'.get_label('Result').'</td>';
		echo '<td width="80" align="center">'.get_label('Role').'</td></tr>';
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->vg->gs->players[$i];
			$player_score = $this->vg->players[$i];
			if ($player->kill_round >= 0 && $player->kill_round < $round)
			{
				continue;
			}

			echo '<tr class="light">';
			echo '<td class="dark" align="center">' . ($i + 1) . '</td>';
			$this->show_player_name($player, $player_score);
			
			if ($voting != NULL && !$voting->is_canceled())
			{
				echo '<td align="center">';
				foreach ($voting->nominants as $nominant)
				{
					if ($nominant->nominated_by == $i)
					{
						echo ($nominant->player_num + 1);
						break;
					}
				}
				echo '&nbsp;</td>';
				
				echo '<td align="center">';
				if (($this->vg->gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT) == 0)
				{
					$voted_for = $voting->votes[$i];
					if ($voted_for >= 0)
					{
						echo ($voting->nominants[$voted_for]->player_num + 1);
					}
					if ($voting1 != NULL && !$voting1->is_canceled())
					{
						$voted_for = $voting1->votes[$i];
						if ($voted_for >= 0)
						{
							echo ', ' . ($voting1->nominants[$voted_for]->player_num + 1);
						}
					}
				}
				echo '&nbsp;</td>';
			}
			
			echo '<td align="center">';
			if ($player->kill_round == $round && $player->state == PLAYER_STATE_KILLED_DAY)
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
			$this->show_player_role($player);
			echo '</tr>';
		}
		echo '</table>';
		
		parent::show_body();
	}
}

$page = new Page();
$page->run(get_label('Game'), PERM_ALL);

?>