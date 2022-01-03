<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/game.php';

define('PAGE_SIZE', DEFAULT_PAGE_SIZE);

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
		$query = new DbQuery('SELECT id, json, is_canceled FROM games WHERE event_id = ? AND result > 0', $this->event->id);
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
				if (!isset($p->id))
				{
					continue;
				}
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
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, eu.nickname, eu.flags, tu.flags, cu.flags' . 
					' FROM users u' . 
					' LEFT OUTER JOIN event_users eu ON eu.event_id = ? AND eu.user_id = u.id' .
					' LEFT OUTER JOIN tournament_users tu ON tu.tournament_id = ? AND tu.user_id = u.id' .
					' LEFT OUTER JOIN club_users cu ON cu.club_id = ? AND cu.user_id = u.id' .
					' WHERE u.id IN (' . $this->users . ')',
					$this->event->id, $this->event->tournament_id, $this->event->club_id);
			while ($row = $query->next())
			{
				list ($id, $name, $flags, $nickname, $event_flags, $tournament_flags, $club_flags) = $row;
				$player = $players[$id];
				$player->user_name = $name;
				$player->flags = $flags;
				$player->nickname = $nickname;
				$player->event_flags = $event_flags;
				$player->tournament_flags = $tournament_flags;
				$player->club_flags = $club_flags;
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
		
		$event_user_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			$this->user_pic)));

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
				$event_user_pic->
					set($player->id, $player->nickname, $player->event_flags, 'e' . $this->event->id)->
					set($player->id, $player->user_name, $player->tournament_flags, 't' . $this->event->tournament_id)->
					set($player->id, $player->user_name, $player->club_flags, 'c' . $this->event->club_id)->
					set($player->id, $player->user_name, $player->flags);
				$event_user_pic->show(ICONS_DIR, true, 50);
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