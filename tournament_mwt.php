<?php

require_once 'include/tournament.php';
require_once 'include/user.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/mwt.php';

define('VIEW_MWT_ID', 0);
define('VIEW_SEATING', 1);
define('VIEW_GAMES', 2);
define('VIEW_COUNT', 3);

function compare_mwt_players($player1, $player2)
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
		$this->available_view = VIEW_MWT_ID;
		if (!is_null($this->mwt_id) && $this->mwt_id > 0)
		{
			list($games_count) = Db::record(get_label('game'), 'SELECT COUNT(*) FROM games WHERE tournament_id = ? AND result > 0 AND is_canceled = FALSE AND is_rating <> 0', $this->id);
			if ($games_count > 0)
			{
				$this->available_view = VIEW_GAMES;
			}
			else
			{
				$this->available_view = VIEW_SEATING;
			}
		}
		
		$this->view = $this->available_view;
		if (isset($_REQUEST['view']))
		{
			$this->view = (int)$_REQUEST['view'];
			if ($this->view < 0 || $this->view >= VIEW_COUNT)
			{
				$this->view = $this->available_view;
			}
		}
		
		$this->seating = NULL;
		$this->round_num = 0;
		$this->round_id = 0;
		if (isset($_REQUEST['round_id']))
		{
			$this->round_id = (int)$_REQUEST['round_id'];
		}
		
		$this->token = '';
		if (isset($_SESSION['mwt_token']))
		{
			$this->token = $_SESSION['mwt_token'];
		}
	}
	
	private function show_mwt_id()
	{
		echo '<p>'.get_label('Please ented MWT ID of this tournament').': <input id="mwt_id" ';
		if (!is_null($this->mwt_id) && $this->mwt_id > 0)
		{
			echo ' value="' . $this->mwt_id . '"';
		}
		echo '></p>';
		
		echo '<button onclick="mwtId()">'.get_label('Done').'</button>';
	}
	
	private function has_unfinished_import()
	{
		$query = new DbQuery('SELECT id, seating, languages FROM events WHERE tournament_id = ?', $this->id);
		while ($row = $query->next())
		{
			list($event_id, $event_seating, $event_langs) = $row;
			if (is_null($event_seating))
			{
				continue;
			}
			
			$event_seating = json_decode($event_seating);
			if (!isset($event_seating->mwt_schema) || !isset($event_seating->seating))
			{
				continue;
			}
			
			foreach ($event_seating->seating as $table)
			{
				if (is_null($table))
				{
					return true;
				}
				foreach ($table as $game)
				{
					if (is_null($game))
					{
						return true;
					}
				}
			}
		}
		return false;
	}
	
	private function show_seating()
	{
		global $_lang,$_mwt_site;
		
		
		echo '<p><button onclick="mwt(importSchema)">'.get_label('Import seating').'</button>';
		if ($this->has_unfinished_import())
		{
			echo ' <button onclick="mwt(importSeating)">'.get_label('Continue importing').'</button>';
		}
		echo '</p>';
		echo '<div id="progress"></div>';
		
		echo '<div class="tab">';
		$query = new DbQuery('SELECT id, round, seating FROM events WHERE tournament_id = ? ORDER BY round', $this->id);
		$tmp_rounds = array();
		$rounds = array();
		while ($row = $query->next())
		{
			if ($row[1] == 0)
			{
				$rounds[] = $row;
			}
			else
			{
				$tmp_rounds[] = $row;
			}
		}
		for ($i = count($tmp_rounds) - 1; $i >= 0; --$i)
		{
			$rounds[] = $tmp_rounds[$i];
		}
		
		foreach ($rounds as $row)
		{
			list($event_id, $round_num, $seating) = $row;
			if ($this->round_id <= 0)
			{
				$this->round_id = $event_id;
			}
			
			$disabled = ' disabled';
			if (!is_null($seating))
			{
				$seating = json_decode($seating);
				if (isset($seating->mwt_schema))
				{
					$disabled = '';
				}
			}
			
			$active = '';
			if ($event_id == $this->round_id)
			{
				$this->seating = $seating;
				$this->round_num = $round_num;
				$active = ' class="active"';
			}
			
			echo '<button' . $active . ' onclick="goTo({round_id:' . $event_id . ',page:undefined})"' . $disabled . '>';
			echo get_round_name($round_num);
			echo '</button>';
		}
		echo '</div>';
		
		if (!is_null($this->seating))
		{
			$round_view = 0;
			if (isset($_REQUEST['rview']))
			{
				$round_view = (int)$_REQUEST['rview'];
			}
			else if (isset($this->seating->mwt_players))
			{
				foreach ($this->seating->mwt_players as $player)
				{
					if ($player->id < 0 && !is_null($player->mwt_id))
					{
						$round_view = 1;
						break;
					}
				}
			}
			echo '<p><div class="tab">';
			echo '<button ' . ($round_view == 0 ? 'class="active" ' : '') . 'onclick="goTo({rview:0,page:undefined})"' . (isset($this->seating->seating) ? '' : ' disabled') . '>' . get_label('Seating') . '</button>';
			echo '<button ' . ($round_view == 1 ? 'class="active" ' : '') . 'onclick="goTo({rview:1,page:undefined})"' . (isset($this->seating->mwt_players) ? '' : ' disabled') . '>' . get_label('Players mapping') . '</button>';
			echo '</div></p>';
			
			$players_list = '';
			$delim = '';
			if (isset($this->seating->mwt_players))
			{
				foreach ($this->seating->mwt_players as $player)
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
			$user_pic = new Picture(USER_PICTURE);
				
			if ($round_view == 1)
			{
				if (isset($this->seating->mwt_players))
				{
					usort($this->seating->mwt_players, "compare_mwt_players");
					echo '<p><table class="bordered light" width="100%">';
					foreach ($this->seating->mwt_players as $player)
					{
						if (is_null($player->mwt_id))
						{
							continue;
						}
						echo '<tr><td width="420"><a href="'.$_mwt_site.'/user/'.$player->mwt_id.'/show" target="_blank">'.$player->name.'</a></td>';
						echo '<td width="40" align="center"><button class="big_icon" onclick="mapMwtPlayer('.$player->id.')"><img src="images/right.png" width="32"></button>';
						if ($player->id > 0)
						{
							list($user_id, $user_name, $user_flags, $user_city) = $users[$player->id];
							
							echo '<td><table class="transp" width="100%"><tr>';
							echo '<td width="52">';
							$user_pic->set($user_id, $user_name, $user_flags);
							$user_pic->show(ICONS_DIR, true, 48);
							echo '</td><td><a href="user_info.php?id='.$player->id.'&bck=1">' . $user_name . ', ' . $user_city . '</a></td></tr></table></td>';
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
			else if (isset($this->seating->seating))
			{
				for ($i = 0; $i < count($this->seating->seating); ++$i)
				{
					$table = $this->seating->seating[$i];
					echo '<p><center><h2>' . get_label('Table [0]', chr(65 + $i)) . '</h2></center></p>';
					echo '<table class="bordered light" width="100%">';
					echo '<tr class="darker"><td width="8%"></td>';
					for ($k = 0; $k < 10; ++$k)
					{
						echo '<td width="9.2%" align="center"><b>'.($k+1).'</b></td>';
					}
					echo '</tr>';
					for ($j = 0; $j < count($table); ++$j)
					{
						$game = $table[$j];
						echo '<tr><td align="center" class="darker"><b>' . get_label('Round [0]', $j+1) . '</b></td>';
						for ($k = 0; $k < count($game) && $k < 10; ++$k)
						{
							if ($game[$k] > 0)
							{
								list($user_id, $user_name, $user_flags, $user_city) = $users[$game[$k]];
								echo '<td><table class="transp" width="100%"><tr><td align="center">';
								$user_pic->set($user_id, $user_name, $user_flags);
								$user_pic->show(ICONS_DIR, false, 48);
								echo '</td><tr><tr><td align="center">' . $user_name;
								echo '</td></tr></table></td>';
							}
							else
							{
								$player = NULL;
								foreach ($this->seating->mwt_players as $p)
								{
									if ($p->id == $game[$k])
									{
										$player = $p;
										break;
									}
								}
								echo '<td class="dark" align="center">';
								echo $p->name;
								echo '</td>';
							}
						}
						echo '</tr>';
					}
					echo '</table>';
				}
			}
			//echo '<pre>' . formatted_json($this->seating) . '</pre>';
		}
	}

	private function show_games()
	{
	}
	
	protected function show_body()
	{
		echo '<div class="tab">';
		echo '<button ' . ($this->view == VIEW_MWT_ID ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_MWT_ID . ',page:undefined})">' . get_label('MWT ID') . '</button>';
		echo '<button ' . ($this->view == VIEW_SEATING ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_SEATING . ',page:undefined})"' . ($this->available_view >= VIEW_SEATING ? '' : ' disabled') . '>' . get_label('Import seating') . '</button>';
		echo '<button ' . ($this->view == VIEW_GAMES ? 'class="active" ' : '') . 'onclick="goTo({view:' . VIEW_GAMES . ',page:undefined})"' . ($this->available_view >= VIEW_GAMES ? '' : ' disabled') . '>' . get_label('Export games') . '</button>';
		echo '</div>';
		
		switch ($this->view)
		{
			case VIEW_MWT_ID:
				$this->show_mwt_id();
				break;
			case VIEW_SEATING:
				$this->show_seating();
				break;
			case VIEW_GAMES:
				$this->show_games();
				break;
		}
	}
	
	private function mwt_id_js()
	{
?>
		function mwtId()
		{
			var params =
			{
				op: "change",
				tournament_id: <?php echo $this->id; ?>,
				mwt_id: $("#mwt_id").val(),
			};
			json.post("api/ops/tournament.php", params, function()
			{
				goTo({view:undefined});
			});
		}
<?php
	}
	
	private function seating_js()
	{
?>
		function mapMwtPlayer(playerId)
		{
			dlg.form("form/mwt_map_player.php?player_id=" + playerId + "&tournament_id=<?php echo $this->id; ?>", refr, 480);
		}

		function importSchema()
		{
			var params =
			{
				op: 'import_schema',
				tournament_id: <?php echo $this->id; ?>
			};
			
			json.post("api/ops/mwt.php", params, function(data)
			{
				if (data.login_needed)
				{
					mwtLogin(importSchema);
				}
				else
				{
					importSeating();
				}
			});
			
		}
		
		function importSeating()
		{
			var params =
			{
				op: 'import_seating',
				tournament_id: <?php echo $this->id; ?>
			};
			
			json.post("api/ops/mwt.php", params, function(data)
			{
				if (data.login_needed)
					mwtLogin(importSeating);
				else
				{
					var phtml = data.progress + ' / ' + data.total;
					if (data.total > 0)
					{
						var redWidth = Math.round(data.progress * <?php echo CONTENT_WIDTH; ?> / data.total);
						phtml += '<br><img src="images/red_dot.png" width="' + redWidth + '" height="20">' + 
							'<img src="images/black_dot.png" width="' + (<?php echo CONTENT_WIDTH; ?>-redWidth) + '" height="20">';
					}
					$('#progress').html(phtml);
					if (data.progress < data.total)
					{
						importSeating();
					}
					else
					{
						refr();
					}
				}
			});
		}
		
<?php
	}

	private function games_js()
	{
?>
<?php
	}
	
	protected function js()
	{
		global $_profile;
		
?>
		function mwtLogin(action)
		{
			var email = '<?php echo isset($_profile->user_email) ? $_profile->user_email : ''; ?>';
			var html = '<p><?php echo get_label('Please login to the MWT site'); ?></p>' +
				'<table class="dialog_form" width="100%">' +
				'<tr><td width="140"><?php echo get_label('Email'); ?>:</td><td><input id="lf-name" value="' + email + '"></td></tr>' +
				'<tr><td><?php echo get_label('Password'); ?>:</td><td><input type="password" id="lf-pwd"></td></tr>' +
				'</table>';
			
			var d = dlg.okCancel(html, "<?php echo get_label('Login'); ?>", null, function()
			{
				var params =
				{
					op: 'sign_on',
					email: $('#lf-name').val(),
					password: $('#lf-pwd').val()
				};
				
				json.post("api/ops/mwt.php", params, function(data)
				{
					token = true;
					action();
				});
			});
		}

		var token = <?php echo empty($this->token) ? 'false' : 'true'; ?>;
		function mwt(action)
		{
			if (token)
			{
				action();
			}
			else
			{
				mwtLogin(action);
			}
		}
	
<?php
		switch ($this->view)
		{
			case VIEW_MWT_ID:
				$this->mwt_id_js();
				break;
			case VIEW_SEATING:
				$this->seating_js();
				break;
			case VIEW_GAMES:
				$this->games_js();
				break;
		}
	}
}

$page = new Page();
$page->run(get_label('MWT integration'));

?>