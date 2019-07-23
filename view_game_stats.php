<?php

require_once 'include/view_game.php';
require_once 'include/image.php';
require_once 'include/user.php';

class Page extends PageBase
{
	private $vg;
	private $stats;
	private $user_id;
	private $user_name;
	private $user_flags;

	protected function prepare()
	{
		global $_profile;
		if (!isset($_REQUEST['num']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('player')));
		}
		
		$id = -1;
		if (isset($_REQUEST['id']))
		{
			$id = (int)$_REQUEST['id'];
		}
		if ($id <= 0)
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('game')));
		}
		
		$this->vg = new ViewGame($id);
		$this->stats = new GamePlayerStats($this->vg->gs, $_REQUEST['num']);
		
		$this->_title = $this->stats->get_title();
		$player = $this->stats->gs->players[$this->stats->player_num];
		$this->user_id = $player->id;
		if ($this->user_id > 0)
		{
			list($this->user_name, $this->user_flags) = Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $this->user_id);
			if ($this->user_name != $player->nick)
			{
				$this->_title .= ' (' . $this->user_name . ')';
			}
		}
		else
		{
			$this->user_name = '';
			$this->user_flags = 0;
		}
	}
	
	protected function show_title()
	{
		echo '<table class="head" width="100%"><tr>';
		if ($this->user_id > 0)
		{
			echo '<td width="1"><a href="user_info.php?id=' . $this->user_id . '&bck=1">';
			$this->user_pic->set($this->user_id, $this->user_name, $this->user_flags);
			$this->user_pic->show(ICONS_DIR);
			echo '</td>';
		}
		echo '<td valign="top">' . $this->standard_title() . '</td><td valign="top" align="right">';
		show_back_button();
		echo '</td></tr></table>';
	}
	
	private function output_row($civil, $mafia, $sheriff, $title, $for_word)
	{
		$count = $civil + $mafia + $sheriff;
		$delimiter = ': ';
		echo '<tr class="light"><td width="200" class="dark">' . $title . '</td><td>';
		switch ($count)
		{
			case 0:
				echo '&nbsp;</td></tr>';
				return;
			case 1:
				echo get_label('1 time') . ':';
				if ($mafia > 0)
				{
					echo $for_word . get_label('mafia');
				}
				else if ($civil > 0)
				{
					echo $for_word . get_label('civilian');
				}
				else if ($sheriff > 0)
				{
					echo $for_word . get_label('sheriff');
				}
				echo '</td></tr>';
				break;
			default:
				echo $count . ' '.get_label('times');
				if ($mafia > 0)
				{
					echo $delimiter . $mafia . $for_word . ' '.get_label('mafia');
					$delimiter = '; ';
				}
				
				switch ($civil)
				{
					case 0:
						break;
					case 1:
						echo $delimiter . $civil . $for_word . ' '.get_label('civilian');
						$delimiter = '; ';
						break;
					default:
						echo $delimiter . $civil . $for_word . ' '.get_label('civilians');
						$delimiter = '; ';
						break;
				}
				
				if ($sheriff > 0)
				{
					echo $delimiter . $sheriff . $for_word . ' '.get_label('sheriff');
				}
				echo '</td></tr>';
				break;
		}
	}
	
	protected function show_body()
	{
		$stats = $this->stats;
		$gs = $stats->gs;
		$player = $gs->players[$stats->player_num];
		$player_score = $this->vg->players[$stats->player_num];

		echo '<p><table class="bordered" width="100%" id="players">';
		echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('General') . '</b></td></tr>';
		echo '<tr class="light"><td class="dark" width="200">'.get_label('Role').':</td><td>' . $player->role_text(true) . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Rating earned').':</td><td>' . $stats->rating_earned . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Warnings').':</td><td>' . $player->warnings_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Killed').':</td><td>' . $player->killed_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Was arranged by mafia at').':</td><td>' . $player->arranged_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Checked by the Don').':</td><td>' . $player->don_check_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Checked by the Sheriff').':</td><td>' . $player->sheriff_check_text() . '</td></tr>';
		echo '</table></p>';
		
		echo '<p><table class="bordered" width="100%" id="Table1">';
		echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('Voting and nominating') . '</b></td></tr>';
		$this->output_row($stats->voted_civil, $stats->voted_mafia, $stats->voted_sheriff, get_label('Voted') . ':', ' '.get_label('for').' ');
		$this->output_row($stats->voted_by_civil, $stats->voted_by_mafia, $stats->voted_by_sheriff, get_label('Was voted') . ':', ' '.get_label('by').' ');
		$this->output_row($stats->nominated_civil, $stats->nominated_mafia, $stats->nominated_sheriff, get_label('Nominated') . ':', ' ');
		$this->output_row($stats->nominated_by_civil, $stats->nominated_by_mafia, $stats->nominated_by_sheriff, get_label('Was nominated') . ':', ' '.get_label('by').' ');
		echo '</table></p>';

		if ($player->role == PLAYER_ROLE_SHERIFF)
		{
			echo '<p><table class="bordered" width="100%" id="Table2">';
			echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('The Sheriff checking') . '</b></td></tr>';
			$count = $stats->civil_found + $stats->mafia_found;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Civilians found').':</td><td>' . $stats->civil_found . ' (' . number_format($stats->civil_found*100.0/$count, 1) . '%)</td></tr>';
				echo '<tr class="light"><td class="dark">'.get_label('Mafiosos found').':</td><td>' . $stats->mafia_found . ' (' . number_format($stats->mafia_found*100.0/$count, 1) . '%)</td></tr>';
			}
			else
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Civilians found').':</td><td>&nbsp;</td></tr>';
				echo '<tr class="light"><td class="dark">'.get_label('Mafiosos found').':</td><td>&nbsp;</td></tr>';
			}
			echo '</table><p>';
		}

		if ($player->role == PLAYER_ROLE_MAFIA || $player->role == PLAYER_ROLE_DON)
		{
			echo '<p><table class="bordered" width="100%" id="Table3">';
			echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('Mafia shooting') . '</b></td></tr>';
			$count = $stats->shots1_ok + $stats->shots2_ok + $stats->shots3_ok + $stats->shots1_miss + $stats->shots2_miss + $stats->shots3_miss;
			if ($count > 0)
			{
				$shots_ok = $stats->shots1_ok + $stats->shots2_ok + $stats->shots3_ok;
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Shooting').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $shots_ok . ' '.get_label('successful').' (' . number_format($shots_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $stats->shots3_ok + $stats->shots3_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('3 shooters').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $stats->shots3_ok . ' '.get_label('successful').' (' . number_format($stats->shots3_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $stats->shots2_ok + $stats->shots2_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('2 shooters').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $stats->shots2_ok . ' '.get_label('successful').' (' . number_format($stats->shots2_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $stats->shots1_ok + $stats->shots1_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('1 shooter').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $stats->shots1_ok . ' '.get_label('successful').' (' . number_format($stats->shots1_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			echo '</table></p>';

			if ($player->role == PLAYER_ROLE_DON)
			{
				echo '<p><table class="bordered" width="100%" id="Table4">';
				echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('The Don\'s game') . '</b></td></tr>';
				if ($stats->sheriff_found >= 0)
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff found').':</td><td>' . ($stats->sheriff_found + 1) . ' '.get_label('night').'</td></tr>';
				}
				else
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff found').':</td><td>'.get_label('no').'</td></tr>';
				}
				if ($stats->sheriff_arranged >= 0)
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff arranged').':</td><td>' . ($stats->sheriff_arranged + 1) . ' '.get_label('night').'</td></tr>';
				}
				else
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff arranged').':</td><td>'.get_label('no').'</td></tr>';
				}
				echo '</table></p>';
			}
		}
	}
}

$page = new Page();
$page->run(get_label('Game statistics'));

?>