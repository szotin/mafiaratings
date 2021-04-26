<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/game.php';

define("PAGE_SIZE",15);

function compare_players($player1, $player2)
{
	return strcmp($player1->name, $player2->name);
}

class Page extends EventPageBase
{
	private $players;
	
	private function init_players()
	{
		$players = array();
		$query = new DbQuery('SELECT id, json, canceled FROM games WHERE event_id = ? AND result > 0', $this->event->id);
		while ($row = $query->next())
		{
			list ($id, $json, $is_canceled) = $row;
			if ($is_canceled)
			{
				continue;
			}
			
			$game = new Game($json);
			foreach ($game->data->players as $p)
			{
				if (isset($players[$p->id]))
				{
					$player = $players[$p->id];
				}
				else
				{
					$player = new stdClass();
					$player->id = $p->id;
					$player->name = $p->name;
					$player->games = 0;
					$players[$p->id] = $player;
				}
				++$player->games;
			}
		}
		
		$this->players = array();
		$this->users = '';
		$delim = '';
		foreach ($players as $id => $player)
		{
			if ($id > 0)
			{
				$this->users .= $delim . $id;
				$delim = ', ';
			}
			$this->players[] = $player;
		}
		
		if (!empty($this->users))
		{
			$query = new DbQuery('SELECT id, name, flags FROM users WHERE id IN (' . $this->users . ')');
			while ($row = $query->next())
			{
				list ($id, $name, $flags) = $row;
				$player = $players[$id];
				$player->user_name = $name;
				$player->flags = $flags;
			}
		}
		
		usort($this->players, "compare_players");
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		check_permissions(PERMISSION_CLUB_MANAGER, $this->event->club_id);
		$this->init_players();
		
		show_pages_navigation(PAGE_SIZE, sizeof($this->players));
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td width="30"></td>';
		echo '<td colspan="2">'.get_label('Player').'</td>';
		echo '<td width="80" align="center">'.get_label('Games played').'</td>';
		echo '</tr>';
		
		for ($i = 0, $j = $_page * PAGE_SIZE; $i < PAGE_SIZE && $j < sizeof($this->players); ++$i,++$j)
		{
			$player = $this->players[$j];
			if ($player->id == 0)
			{
				continue;
			}
			
			echo '<tr>';
			echo '<td valign="center">';
			echo '<button class="icon" onclick="changeEventPlayer(' . $this->event->id . ', ' . $player->id . ', \'' . $player->name . '\')"><img src="images/edit.png" border="0"></button>';
			echo '</td>';
			echo '<td width="60" align="center">';
			if ($player->id > 0)
			{
				$this->user_pic->set($player->id, $player->user_name, $player->flags);
				$this->user_pic->show(ICONS_DIR, true, 50);
			}
			else
			{
				echo '<img src="images/create_user.png" width="50">';
			}
			echo '</td><td>';
			if ($player->id > 0)
			{
				echo '<a href="user_info.php?id=' . $player->id . '&bck=1">';
				if ($player->name == $player->user_name)
				{
					echo $player->name;
				}
				else
				{
					echo $player->name . ' (' . $player->user_name . ')';
				}
				echo '</a></td>';
			}
			else
			{
				echo $player->name;
			}
			echo '<td align="center">' . $player->games . '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
		parent::js();
?>
		function changeEventPlayer(eventId, userId, nickname)
		{
			dlg.form("form/event_change_player.php?event_id=" + eventId + "&user_id=" + userId + "&nick=" + nickname, refr);
		}
<?php	
	}
}

$page = new Page();
$page->run(get_label('Players'));

?>