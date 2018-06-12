<?php

require_once 'include/view_game.php';

class Page extends ViewGamePageBase
{
	protected function show_body()
	{
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="th darkest"><td width="30">&nbsp;</td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Arranged').'</td>';
		echo '<td width="80" align="center">'.get_label('Role').'</td></tr>';
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->vg->gs->players[$i];
			$player_score = $this->vg->players[$i];
			
			echo '<tr class="dark">';
			echo '<td width="20" class="darker" align="center">' . ($i + 1) . '</td>';
			$this->show_player_name($player, $player_score);
			echo '<td align="center">';
			if ($player->arranged >= 0)
			{
				echo get_label('Night').' ' . ($player->arranged + 1);
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
$page->run(get_label('Game'));

?>