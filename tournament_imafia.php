<?php

require_once 'include/tournament.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
//require_once 'include/imafia.php';

define('VIEW_IMAFIA_ID', 0);
define('VIEW_GAME_IMPORT', 1);
define('VIEW_COUNT', 2);

function compare_imafia_players($player1, $player2)
{
	if ($player1->id > 0)
	{
		if ($player2->id > 0)
		{
			return strcmp($player1->name, $player2->name);
		}
		return 1;
	}
	
	if ($player2->id > 0)
	{
		return -1;
	}
	return strcmp($player1->name, $player2->name);
}

class Page extends TournamentPageBase
{
	protected function prepare()
	{
		parent::prepare();
		
		$this->has_imafia = !is_null($this->imafia_id) && $this->imafia_id > 0;
		
		$this->players = NULL;
		list ($tournament_misc) = Db::record(get_label('tournament'), 'SELECT misc FROM tournaments WHERE id = ?', $this->id);
		if (!is_null($tournament_misc))
		{
			$tournament_misc = json_decode($tournament_misc);
			if (isset($tournament_misc->imafia))
			{
				if (isset($tournament_misc->imafia->players))
				{
					$this->players = $tournament_misc->imafia->players;
				}
			}
		}
		list($this->games_count) = Db::record(get_label('game'), 'SELECT COUNT(*) FROM games WHERE tournament_id = ?', $this->id);
		
		$this->misc = NULL;
		$this->round_num = 0;
		$this->round_id = 0;
		if (isset($_REQUEST['round_id']))
		{
			$this->round_id = (int)$_REQUEST['round_id'];
		}
		
		$this->token = '';
		if (isset($_SESSION['imafia_token']))
		{
			$this->token = $_SESSION['imafia_token'];
		}
	}
	
	private function get_users()
	{
		global $_lang;
		
		$players_list = '';
		$delim = '';
		if (!is_null($this->imafia_players))
		{
			foreach ($this->imafia_players as $player)
			{
				if ($player->id > 0)
				{
					$players_list .= $delim . $player->id;
					$delim = ',';
				}
			}
		}
			
		$users = array();
		if (!empty($players_list))
		{
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, ni.name'.
				' FROM users u'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' JOIN cities i ON i.id = u.city_id'.
				' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0'.
				' WHERE u.id IN ('.$players_list.')');
			while ($row = $query->next())
			{
				$users[$row[0]] = $row;
			}
		}
		return $users;
	}
	
	protected function show_body()
	{
		echo '<p>'.get_label('Please ented [0] ID of this tournament', 'iMafia').': <input id="imafia_id" ';
		if (!is_null($this->imafia_id) && $this->imafia_id > 0)
		{
			echo ' value="' . $this->imafia_id . '"';
		}
		echo '></p>';
		
		echo '<button onclick="importGames()">'.get_label('Import games').'</button>';
		if ($this->games_count > 0)
		{
			echo '&emsp;&emsp;&emsp;&emsp;&emsp;&emsp;<input id="overwrite" type="checkbox"> ' . get_label('overwrite existing games');
		}
		echo '<p></p>';
		
		if ($this->games_count > 0)
		{
			echo '<h2>' . get_label('Successfuly imported [0] games', $this->games_count) . '</h2>';
		}
		
		$user_pic = new Picture(USER_PICTURE);
		if (!is_null($this->players))
		{
			echo '<p><h3>' . get_label('Players') . '</h3>';
			echo '<table class="bordered light" width="100%">';
			foreach ($this->players as $player)
			{
				echo '<tr><td width="420">';
				echo '<table class="transp" width="100%"><tr><td width="32"><button class="icon" onclick="createUser(\'' . $player->imafia_name . '\', ' . $player->imafia_id . ')" title="' . get_label('Create new user') . '"><img src="images/create.png"></button></td><td>';
				if (is_null($player->imafia_id))
				{
					echo $player->name;
				}
				else
				{
					echo '<a href="https://imafia.org/u/'.$player->imafia_id.'" target="_blank">'.$player->imafia_name.'</a>';
				}
				echo '</td></tr></table>';
				echo '</td>';
				
				echo '<td width="40" align="center"><button class="big_icon" onclick="mapPlayer('.$player->imafia_id.')"><img src="images/right.png" width="32"></button>';
				if (isset($player->id))
				{
					echo '<td><table class="transp" width="100%"><tr>';
					echo '<td width="52">';
					$user_pic->set($player->id, $player->name, $player->flags);
					$user_pic->show(ICONS_DIR, true, 48);
					echo '</td><td><a href="user_info.php?id='.$player->id.'&bck=1">' . $player->name . '</a></td></tr></table></td>';
				}
				else
				{
					echo '<td></td>';
				}
				echo '</td></tr>';
			}
			echo '</table></p>';
		}
	}
	
	protected function js()
	{
		global $_profile;		
?>
		function createUser(name, imafiaId)
		{
			dlg.form("form/create_user.php?name=" + name + "&club_id=<?php echo $this->club_id; ?>", 
				function(data)
				{
					json.post("api/ops/imafia.php",
					{
						op: "map_player"
						, user_id: data.id
						, imafia_id: imafiaId
						, tournament_id: <?php echo $this->id; ?>
					}, refr);
					
				}, 480);
		}

		function _doImport(overwrite)
		{
			let params =
			{
				op: "import_games",
				tournament_id: <?php echo $this->id; ?>,
				imafia_id: $("#imafia_id").val(),
				overwrite: overwrite
			};
			json.post("api/ops/imafia.php", params, refr);
		}

		function importGames()
		{
<?php
			if ($this->games_count > 0)
			{
?>
				var overwrite = ($('#overwrite').length > 0 && $("#overwrite").attr("checked")) ? 1 : 0;
				if (overwrite)
					dlg.yesNo("<?php echo get_label('The tournament already contains games. They all will be deleted and replaced with the imported ones.<p>Are you sure you want to continue?</p>'); ?>", null, null, function(){_doImport(overwrite);});
				else
					_doImport(false);

<?php
			}
			else
			{
?>
				_doImport(false);
<?php
			}
?>
		}

		function mapPlayer(imafiaId)
		{
			dlg.form("form/imafia_map_player.php?imafia_id=" + imafiaId + "&tournament_id=<?php echo $this->id; ?>", refr, 480);
		}
		
<?php
	}
}

$page = new Page();
$page->run(get_label('iMafia integration'));

?>