<?php

require_once 'include/view_game.php';

class Page extends ViewGamePageBase
{
	protected function show_body()
	{
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="th darkest"><td width="30">&nbsp;</td>';
		echo '<td>'.get_label('Player').'</td>';
		echo '<td width="80">'.get_label('Arranged').'</td>';
		echo '<td width="60">'.get_label('Role').'</td></tr>';
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->vg->gs->players[$i];
			if ($player->id == $this->vg->mark_player)
			{
				echo '<tr class="light">';
			}
			else
			{
				echo '<tr class="dark">';
			}
			
			echo '<td width="20" class="darker" align="center">' . ($i + 1) . '</td>';
			echo '<td><a href="view_game_stats.php?num=' . $i . '&bck=1">' . cut_long_name($player->nick, 85) . '</a></td>';
			echo '<td width="80">';
			if ($player->arranged >= 0)
			{
				echo get_label('Night').' ' . ($player->arranged + 1);
			}
			echo '&nbsp;</td>';
			echo '<td width="60">' . $player->role_text(false) . '</td>';
			
			echo '</tr>';
		}
		echo '</table>';
		
		parent::show_body();
	}
}

$page = new Page();
$page->run(get_label('Game'), PERM_ALL);

?>