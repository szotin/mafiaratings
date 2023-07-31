<?php

require_once 'include/general_page_base.php';

define('ODDS_WIDTH', 200);

class Page extends GeneralPageBase
{
	protected function prepare()
	{
		parent::prepare();
		$this->url = get_server_url();
		if (isset($_REQUEST['token']))
		{
			$token = $_REQUEST['token'];
			$this->url .= '/api/get/current_game.php?token=' . $token;
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', 'token'));
		}
		
		$game_known = false;
		if (isset($_REQUEST['game_id']))
		{
			$this->url .= '&game_id=' . (int)$_REQUEST['game_id'];
			$game_known = true;
		}
		
		if (isset($_REQUEST['user_id']))
		{
			$this->url .= '&user_id=' . (int)$_REQUEST['user_id'];
			$game_known = true;
		}
		
		if (isset($_REQUEST['moderator_id']))
		{
			$this->url .= '&moderator_id=' . (int)$_REQUEST['moderator_id'];
			$game_known = true;
		}
		
		if (!$game_known)
		{
			throw new Exc(get_label('Unknown [0]', get_label('game')));
		}
		
		$json = file_get_contents($this->url);
		if (!$json)
		{
			throw new Exc(get_label('Unknown [0]', get_label('game')));
		}
		$this->game = json_decode($json);
		
		$this->_title = isset($this->game->game->name) ? $this->game->game->name : get_label('No game');
	}

	protected function show_body()
	{
		global $_profile, $_page;
		$game = $this->game->game;
		
		if (isset($_REQUEST['raw']))
		{
			echo '<p><a href="' . $this->url . '">' . $this->url . '</a></p>';
			print_json($game);
		}
		else
		{
			$maf_sum = 0.0;
			$maf_count = 0;
			$civ_sum = 0.0;
			$civ_count = 0;
			foreach ($game->players as $player)
			{
				if (!isset($player->role))
				{
					continue;
				}
				
				$rating = 0;
				if (isset($player->id) && $player->id > 0)
				{
					list($rating) = Db::record(get_label('player'), 'SELECT rating FROM users WHERE id = ?', $player->id);
				}
				switch ($player->role)
				{
					case 'town':
					case 'sheriff':
						$civ_sum += (double)$rating;
						++$civ_count;
						break;
					case 'maf':
					case 'don':
						$maf_sum += (double)$rating;
						++$maf_count;
						break;
				}
			}
			
			$odds_text = '';
			if ($maf_count > 0 && $civ_count > 0)
			{
				$odds = 1.0 / (1.0 + pow(10.0, ($maf_sum / $maf_count - $civ_sum / $civ_count) / 400));
				$odds_text = number_format($odds * 100, 1) . '%';
				$odds_text = number_format($odds * 100, 1) . '%';
				$text = get_label('The chances to win for the town estimated by [0] before the game were [1].', PRODUCT_NAME, $odds_text);
				$red_width = round(ODDS_WIDTH * $odds);
			}
			
			echo '<table class="bordered light" width="100%">';
			echo '<tr><td width="100" class="darker"><b>' . get_label('Civs odds') . '</b></td><td class="dark">' . $odds_text;
			if (!empty($odds_text))
			{
				echo ' <img src="images/red_dot.png" width="' . $red_width . '" height="12" title="' . $text . '"><img src="images/black_dot.png" width="' . (ODDS_WIDTH - $red_width) . '" height="12" title="' . $text . '">';
			}
			echo '</td></tr>';
			
			echo '<tr><td class="darker"><b>' . get_label('Game phase') . '</b></td><td class="dark">';
			$separator = '';
			if (isset($game->round))
			{
				echo get_label('Round [0].', $game->round);
				$separator = ' ';
			}
			if (isset($game->phase))
			{
				echo $separator . $game->phase;
				$separator = ': ';
			}
			if (isset($game->state))
			{
				echo $separator . $game->state;
			}
			echo '</td></tr>';
			
			echo '<tr><td class="darker"><b>' . get_label('Referee') . '</b></td><td class="dark">';
			echo '<table class="transp" widtth="100%"><tr><td width="60"><img src="' . $game->moderator->iconUrl . '" width="52"></td><td>' . $game->moderator->name . '</td></tr></table>';
			echo '</td></tr>';
			
			foreach ($game->players as $player)
			{
				echo '<tr><td class="dark" align="center"><b>' . $player->number . '</b></td><td>';
				echo '<table class="transp" widtth="100%"><tr><td width="60"><img src="' . $player->iconUrl . '" width="52"></td><td>' . $player->name . '</td></tr></table>';
				echo '</td></tr>';
			}
			
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run(get_label('Current game'));

?>