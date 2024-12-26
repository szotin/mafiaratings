<?php

require_once '../include/session.php';

initiate_session();

class BonusInfo
{
	function __construct($player)
	{
		$this->points = 0;
		$this->best_player = false;
		$this->best_move = false;
		$this->worst_move = false;
		if (isset($player->bonus))
		{
			if (is_array($player->bonus))
			{
				foreach ($player->bonus as $b)
				{
					$this->update($b);
				}
			}
			else
			{
				$this->update($player->bonus);
			}
		}
	}
	
	private function update($bonus)
	{
		if (is_numeric($bonus))
		{
			$this->points = $bonus;
		}
		else switch ($bonus)
		{
		case 'bestPlayer':
			$this->best_player = true;
			break;
		case 'bestMove':
			$this->best_move = true;
			break;
		case 'worstMove':
			$this->worst_move = true;
			break;
		}
	}
}

try
{
	if (!isset($_REQUEST['game_id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('game')));
	}
	$game_id = (int)$_REQUEST['game_id'];
	
	if (!isset($_REQUEST['player_num']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('player')));
	}
	$player_num = max(min((int)$_REQUEST['player_num'], 10), 1);
	
	list($game) = Db::record(get_label('game'), 'SELECT json FROM games WHERE id = ?', $game_id);
	$game = json_decode($game);
	if (!isset($game->players))
	{
		throw new Exc(get_label('Invalid [0]', get_label('game')));
	}
	$player = $game->players[$player_num - 1];
		
	dialog_title(get_label('[0]. Bonus points', $player->name));
	
	$bonus_info = new BonusInfo($player);
		
	echo '<table class="dialog_form" width="100%">';
	echo '<tr><td>' . get_label('Bonus points') . ':</td><td><table width="100%" class="transp"><tr><td><input type="number" style="width: 45px;" step="0.1" id="dlg-points"';
	if ($bonus_info->points != 0)
	{
		echo ' value="' . $bonus_info->points . '"';
	}
	echo '></td><td align="right"><button id="dlg-bp" class="best" onclick="bestClicked(0)"';
	if ($bonus_info->best_player)
	{
		echo ' checked';
	}
	echo '><img src="images/best_player.png" width="24" title="' . get_label('best player') . '"></button><button id="dlg-bm" class="best" onclick="bestClicked(1)"';
	if ($bonus_info->best_move)
	{
		echo ' checked';
	}
	echo '><img src="images/best_move.png" width="24" title="' . get_label('best move') . '"></button><button id="dlg-wm" class="best" onclick="bestClicked(2)"';
	if ($bonus_info->worst_move)
	{
		echo ' checked';
	}
	echo '><img src="images/worst_move.png" width="24" title="' . get_label('worst move') . '"></button></td></tr></table></td></tr>';
	echo '<tr><td valign="top">' . get_label('Comment') . ':</td><td><textarea id="dlg-comment" placeholder="' . get_label('You must enter comment if you give a player extra points or best player/move title.') . '" cols="50" rows="8">';
	if (isset($player->comment))
	{
		echo $player->comment;
	}
	echo '</textarea></td></tr>';
	echo '</table>';
	
?>	
	<script>
	function bestClicked(type)
	{
		switch (type)
		{
			case 0:
				$('#dlg-bp').attr("checked", !$('#dlg-bp').attr("checked"));
				$('#dlg-bm').attr("checked", false);
				$('#dlg-wm').attr("checked", false);
				break;
			case 1:
				$('#dlg-bp').attr("checked", false);
				$('#dlg-bm').attr("checked", !$('#dlg-bm').attr("checked"));
				$('#dlg-wm').attr("checked", false);
				break;
			case 2:
				$('#dlg-bp').attr("checked", false);
				$('#dlg-bm').attr("checked", false);
				$('#dlg-wm').attr("checked", !$('#dlg-wm').attr("checked"));
				break;
		}
	}
	
	function commit(onSuccess)
	{
		json.post("api/ops/game.php",
		{
			op: 'set_bonus'
			, game_id: <?php echo $game_id; ?>
			, player_num: <?php echo $player_num; ?>
			, points: $("#dlg-points").val()
			, best_player: $("#dlg-bp").attr("checked") ? 1 : 0
			, best_move: $("#dlg-bm").attr("checked") ? 1 : 0
			, worst_move: $("#dlg-wm").attr("checked") ? 1 : 0
			, comment: $('#dlg-comment').val()
		},
		onSuccess);
	}
	</script>
<?php
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>