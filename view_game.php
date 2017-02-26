<?php

require_once 'include/view_game.php';

class Page extends ViewGamePageBase
{
	protected function show_body()
	{
		echo '<table class="bordered" width="100%" id="players">';
		echo '<tr class="th darker"><td width="30">&nbsp;</td>';
		echo '<td>'.get_label('Player').'</td>';
		echo '<td width="60">'.get_label('Rating points').'</td>';
		echo '<td width="60">'.get_label('The Sheriff\'s check').'</td>';
		echo '<td width="60">'.get_label('The Don\'s check').'</td>';
		echo '<td width="60">'.get_label('Mafia arrangement').'</td>';
		echo '<td width="140">'.get_label('Killed').'</td>';
		echo '<td width="80">'.get_label('Warnings').'</td>';
		echo '<td width="60">'.get_label('Role').'</td></tr>';
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->vg->gs->players[$i];
			if ($player->id == $this->vg->mark_player)
			{
				echo '<tr class="lighter">';
			}
			else
			{
				echo '<tr class="light">';
			}
			echo '<td class="dark" align="center">' . ($i + 1) . '</td>';
			echo '<td><a href="view_game_stats.php?num=' . $i . '&bck=1">' . cut_long_name($player->nick, 20) . '</a></td>';
			echo '<td align="right">' . $this->vg->gs->get_rating($i) . '</td>';
			echo '<td>' . $player->sheriff_check_text() . '</td>';
			echo '<td>' . $player->don_check_text() . '</td>';
			echo '<td>' . $player->arranged_text() . '</td>';
			echo '<td>' . $player->killed_text() . '</td>';
			echo '<td>' . $player->warnings_text() . '</td>';
			echo '<td>' . $player->role_text(false) . '</td></tr>';
		}
		echo '</table>';
		
		parent::show_body();
	}
}

$page = new Page();
$page->run(get_label('Game'), PERM_ALL);

?>