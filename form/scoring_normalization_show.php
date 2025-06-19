<?php

require_once '../include/session.php';
require_once '../include/scoring.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['pid']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('player')));
	}
	$user_id = (int)$_REQUEST['pid'];
	
	if (!isset($_REQUEST['tid']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('tournament')));
	}
	$tournament_id = (int)$_REQUEST['tid'];
	
	$normalizer_id = 0;
	$normalizer = NULL;
	if (isset($_REQUEST['nid']))
	{
		$normalizer_id = (int)$_REQUEST['nid'];
		if ($normalizer_id > 0)
		{
			if (isset($_REQUEST['nver']))
			{
				$normalizer_version = (int)$_REQUEST['nver'];
				list($normalizer) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer FROM normalizer_versions WHERE normalizer_id = ? AND version = ?', $normalizer_id, $normalizer_version);
			}
			else
			{
				list($normalizer, $normalizer_version) = Db::record(get_label('scoring normalizer'), 'SELECT normalizer, version FROM normalizer_versions WHERE normalizer_id = ? ORDER BY version DESC LIMIT 1', $normalizer_id);
			}
		}
	}
	if ($normalizer_id <= 0)
	{
		list($normalizer, $normalizer_id, $normalizer_version) = Db::record(get_label('tournament'), 
			'SELECT v.normalizer, t.normalizer_id, t.normalizer_version FROM tournaments t' .
			' JOIN normalizer_versions v ON v.normalizer_id = t.normalizer_id AND v.version = t.normalizer_version' .
			' WHERE t.id = ?', $tournament_id);
	}
	$normalizer = json_decode($normalizer);
	if (!isset($normalizer->policies))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('normalizer')));
	}
	
	list($user_name, $user_flags) = Db::record(get_label('player'), 
		'SELECT nu.name, u.flags'.
		' FROM users u'.
		' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
		' WHERE u.id = ?', $user_id);
	list($tournament_name, $tournament_flags) = Db::record(get_label('player'), 'SELECT name, flags FROM tournaments WHERE id = ?', $tournament_id);
	
	dialog_title(get_label('How normalization rate is calculated for [0] in [1].', $user_name, $tournament_name));
	
	list($games_played, $games_won, $rounds_played) = Db::record(get_label('player'),
		'SELECT COUNT(DISTINCT g.id), SUM(p.won), COUNT(DISTINCT g.event_id)'.
		' FROM players p'.
		' JOIN games g ON g.id = p.game_id'.
		' WHERE p.user_id = ? AND g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING, $user_id, $tournament_id);
	// $query = new DbQuery(
		// 'SELECT MAX(gp), MAX(rp)'.
		// ' FROM (SELECT p.user_id, COUNT(DISTINCT g.id) AS gp, COUNT(DISTINCT g.event_id) AS rp'.
			// ' FROM players p JOIN games g ON g.id = p.game_id'.
			// ' WHERE g.tournament_id = ? AND (g.flags & '.GAME_FLAG_RATING.') <> 0 AND (g.flags & '.GAME_FLAG_CANCELED.') = 0'.
			// ' GROUP BY p.user_id) AS players', $tournament_id);
	// echo $query->get_parsed_sql();
	list($max_games_played, $max_rounds_played) = Db::record(get_label('tournament'), 
		'SELECT MAX(gp), MAX(rp)'.
		' FROM (SELECT p.user_id, COUNT(DISTINCT g.id) AS gp, COUNT(DISTINCT g.event_id) AS rp'.
			' FROM players p JOIN games g ON g.id = p.game_id'.
			' WHERE g.tournament_id = ? AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.
			' GROUP BY p.user_id) AS players', $tournament_id);

	$total_normalization = 1;
	echo '<table class="bordered light" width="100%">';
	foreach ($normalizer->policies as $policy)
	{
		$cond_type = 0;
		$cond = NULL;
		$qualifies = true;
		$general_explanation = '';
		$player_explanation = '';
		$normalization = 1;
		if (isset($policy->games))
		{
			$cond = $policy->games;
			$cond_type = 1;
			if (isset($cond->min) && $cond->min > 0)
			{
				$qualifies = $qualifies && $games_played >= $cond->min;
				$general_explanation = get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label(' games');
				if (isset($cond->max))
				{
					$qualifies = $qualifies && $games_played < $cond->max;
					$general_explanation .= get_label(' and less than') . ' ' . $cond->max . get_label(' games');
				}
			}
			else if (isset($cond->max))
			{
				$qualifies = $qualifies && $games_played < $cond->max;
				$general_explanation = get_label('For players who played less than') . ' ' . $cond->max . get_label(' games');
			}
			else
			{
				$general_explanation = get_label('For all players');
			}
			$player_explanation = get_label('[1] played [2][0].', get_label(' games'), $user_name, $games_played);
		}
		else if (isset($policy->gamesPerc))
		{
			$cond = $policy->gamesPerc;
			$cond_type = 2;
			$games_perc = $max_games_played == 0 ? 0 : $games_played * 100 / $max_games_played;
			if (isset($cond->min) && $cond->min > 0)
			{
				$qualifies = $qualifies && $games_perc >= $cond->min;
				$general_explanation = get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label('% of the games');
				if (isset($cond->max) && $cond->max < 100)
				{
					$qualifies = $qualifies && $games_perc < $cond->max;
					$general_explanation .= get_label(' and less than') . ' ' . $cond->max . get_label('% of the games');
				}
			}
			else if (isset($cond->max) && $cond->max < 100)
			{
				$qualifies = $qualifies && $games_perc < $cond->max;
				$general_explanation = get_label('For players who played less than') . ' ' . $cond->max . get_label('% of the games');
			}
			else
			{
				$general_explanation = get_label('For all players');
			}
			$player_explanation = get_label('[1] played [2][0]. Maximum number of[0] played by a player in this tournament is [3]. So [1] played [4]%.', get_label(' games'), $user_name, $games_played, $max_games_played, round($games_perc));
		}
		else if (isset($policy->rounds))
		{
			$cond = $policy->rounds;
			$cond_type = 3;
			$qualifies = $qualifies && $rounds_played >= $cond->min;
			if (isset($cond->min) && $cond->min > 0)
			{
				$qualifies = $qualifies && $rounds_played >= $cond->min;
				$general_explanation = get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label(' rounds');
				if (isset($cond->max))
				{
					$qualifies = $qualifies && $rounds_played < $cond->max;
					$general_explanation .= get_label(' and less than') . ' ' . $cond->max . get_label(' rounds');
				}
			}
			else if (isset($cond->max))
			{
				$qualifies = $qualifies && $rounds_played < $cond->max;
				$general_explanation = get_label('For players who played less than') . ' ' . $cond->max . get_label(' rounds');
			}
			else
			{
				$general_explanation = get_label('For all players');
			}
			$player_explanation = get_label('[1] played [2][0].', get_label(' rounds'), $user_name, $rounds_played);
		}
		else if (isset($policy->roundsPerc))
		{
			$cond = $policy->roundsPerc;
			$cond_type = 4;
			$rounds_perc = $max_rounds_played == 0 ? 0 : $rounds_played * 100 / $max_rounds_played;
			if (isset($cond->min))
			{
				$qualifies = $qualifies && $rounds_perc >= $cond->min;
				$general_explanation = get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label('% of the rounds');
				if (isset($cond->max) && $cond->max < 100)
				{
					$qualifies = $qualifies && $rounds_perc < $cond->max;
					$general_explanation .= get_label(' and less than') . ' ' . $cond->max . get_label('% of the rounds');
				}
			}
			else if (isset($cond->max) && $cond->max < 100)
			{
				$qualifies = $qualifies && $rounds_perc < $cond->max;
				$general_explanation = get_label('For players who played less than') . ' ' . $cond->max . get_label('% of the rounds');
			}
			else
			{
				$general_explanation = get_label('For all players');
			}
			$player_explanation = get_label('[1] played [2][0]. Maximum number of[0] played by a player in this tournament is [3]. So [1] played [4]%.', get_label(' rounds'), $user_name, $rounds_played, $max_rounds_played, round($rounds_perc));
		}
		else if (isset($policy->winPerc))
		{
			$cond = $policy->winPerc;
			$cond_type = 5;
			$win_perc = $games_played == 0 ? 0 : $games_won * 100 / $games_played;
			if (isset($cond->min) && $cond->min > 0)
			{
				$general_explanation = get_label('For players who has greater than or equal to') . ' ' . $cond->min . get_label('% of the wins');
				$qualifies = $qualifies && $win_perc >= $cond->min;
				if (isset($cond->max) && $cond->max < 100)
				{
					$qualifies = $qualifies && $win_perc < $cond->max;
					$general_explanation .= get_label(' and lower than') . ' ' . $cond->max . get_label('% of the wins');
				}
			}
			else if (isset($cond->max) && $cond->max < 100)
			{
				$qualifies = $qualifies && $win_perc < $cond->max;
				$general_explanation = get_label('For players who has lower than') . ' ' . $cond->max . get_label('% of the wins');
			}
			else
			{
				$general_explanation = get_label('For all players');
			}
		}
		else
		{
			$general_explanation = get_label('For all players');
		}
		
		echo '<tr';
		if (!$qualifies)
		{
			echo ' class="dark"';
		}
		echo '><td valign="top" width="300"><p>' . $general_explanation . '</p><p>' . $player_explanation . '</p>';
		echo '</td><td valign="top"><p>';
		if ($qualifies)
		{
			if (isset($policy->multiply))
			{
				if (isset($policy->multiply->val))
				{
					if (isset($policy->multiply->max) && $cond != NULL && isset($cond->min) && isset($cond->max) && $cond->min < $cond->max)
					{
						echo get_label('The final score is multiplied by a value between [0] and [1] depending on ', $policy->multiply->val, $policy->multiply->max);
						switch ($cond_type)
						{
							case 1: // games
								$cond_val = $games_played;
								$normalization = $policy->multiply->val + ($policy->multiply->max - $policy->multiply->val) * ($cond_val - $cond->min) / ($cond->max - $cond->min);
								echo get_label(' the number of[0] played by a player. Player [1] played [2][0]. The final score is multiplied by [3]', get_label(' games'), $user_name, $cond_val, format_coeff($normalization));
								break;
							case 2: // gamesPerc
								$cond_val = $max_games_played == 0 ? 0 : $games_played * 100 / $max_games_played;
								$normalization = $policy->multiply->val + ($policy->multiply->max - $policy->multiply->val) * ($cond_val - $cond->min) / ($cond->max - $cond->min);
								echo get_label(' the number of[0] played by a player. Player [1] played [2][0]. The final score is multiplied by [3]', get_label('% of the games'), $user_name, $cond_val, format_coeff($normalization));
								break;
							case 3: // rounds
								$cond_val = $rounds_played;
								$normalization = $policy->multiply->val + ($policy->multiply->max - $policy->multiply->val) * ($cond_val - $cond->min) / ($cond->max - $cond->min);
								echo get_label(' the number of[0] played by a player. Player [1] played [2][0]. The final score is multiplied by [3]', get_label(' rounds'), $user_name, $cond_val, format_coeff($normalization));
								break;
							case 4: // roundsPerc
								$cond_val = $max_rounds_played == 0 ? 0 : $rounds_played * 100 / $max_rounds_played;
								$normalization = $policy->multiply->val + ($policy->multiply->max - $policy->multiply->val) * ($cond_val - $cond->min) / ($cond->max - $cond->min);
								echo get_label(' the number of[0] played by a player. Player [1] played [2][0]. The final score is multiplied by [3]', get_label('% of the rounds'), $user_name, $cond_val, format_coeff($normalization));
								break;
							case 5: // winPerc
								$cond_val = $games_played == 0 ? 0 : $games_won * 100 / $games_played;
								$normalization = $policy->multiply->val + ($policy->multiply->max - $policy->multiply->val) * ($cond_val - $cond->min) / ($cond->max - $cond->min);
								echo get_label(' a player winning rate. Player [1] won [2] out of [3] games - [4]%. The final score is multiplied by [5]', $user_name, $games_won, $games_played, $cond_val, format_coeff($normalization));
						}
					}
					else if ($policy->multiply->val != 1)
					{
						$normalization = $policy->multiply->val;
						echo get_label('The final score is multiplied by [0].', format_coeff($normalization));
					}
					else
					{
						echo get_label('Nothing is done.');
					}
				}
				else if (isset($policy->multiply->max) && $policy->multiply->max != 1)
				{
					$normalization = $policy->multiply->max;
					echo get_label('The final score is multiplied by [0].', format_coeff($normalization));
				}
				else
				{
					echo get_label('Nothing is done.');
				}
			}
			else if (isset($policy->gameAv))
			{
				if (isset($policy->gameAv->add))
				{
					$div = $games_played + $policy->gameAv->add;
					$normalization = $div == 0 ? 0 : 1 / $div;
					echo get_label('The final score is divided by the number of[0] played by a player plus [1].', get_label(' games'), $policy->gameAv->add) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [4] = 1 / ([2] + [5])', get_label(' games'), $user_name, $games_played, format_coeff($normalization), $div, $policy->gameAv->add);
				}
				else if (isset($policy->gameAv->min))
				{
					$div = max($games_played, $policy->gameAv->min);
					$normalization = $div == 0 ? 0 : 1 / $div;
					echo get_label('The final score is divided by the number of[0] played by a player. But if this number is lower than [1] it is divided by [1].', get_label(' games'), $policy->gameAv->min) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [4] = 1 / MAX([2], [5])', get_label(' games'), $user_name, $games_played, format_coeff($normalization), $div, $policy->gameAv->min);
				}
				else if (isset($policy->gameAv->minPercOfMax))
				{
					$div = max($games_played, round($max_games_played * $policy->gameAv->minPercOfMax / 100));
					$normalization = $div == 0 ? 0 : 1 / $div;
					echo get_label('The final score is divided by the number of games played by a player. But if it is lower than [0]% of the games played by the most active player, it is divided by [0]% of the most games played.', $policy->gameAv->minPercOfMax) . '</p><p>';
					echo get_label('Player [0] played [1] games. Maximum games played in the tournament by any player is [5]. The [4]% of [5] is [6] (rounded).<p>The final score is multiplied by 1 / MAX([1], [6]) = 1 / [3] = [2]</p>', $user_name, $games_played, format_coeff($normalization), $div, $policy->gameAv->minPercOfMax, $max_games_played, round($max_games_played * $policy->gameAv->minPercOfMax / 100));
				}
				else
				{
					$normalization = $games_played == 0 ? 0 : 1 / $games_played;
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' games')) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [2]', get_label(' games'), $user_name, $games_played, format_coeff($normalization));
				}
			}
			else if (isset($policy->roundAv))
			{
				if (isset($policy->roundAv->add))
				{
					$div = $rounds_played + $policy->roundAv->add;
					$normalization = $div == 0 ? 0 : 1 / $div;
					echo get_label('The final score is divided by the number of[0] played by a player plus [1].', get_label(' rounds'), $policy->roundAv->add) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [4] = 1 / ([2] + [5])', get_label(' rounds'), $user_name, $rounds_played, format_coeff($normalization), $div, $policy->roundAv->add);
				}
				else if (isset($policy->roundAv->min))
				{
					$div = max($rounds_played, $policy->roundAv->min);
					$normalization = $div == 0 ? 0 : 1 / $div;
					echo get_label('The final score is divided by the number of[0] played by a player plus [1].', get_label(' rounds'), $policy->roundAv->add) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [4] = 1 / MAX([2], [5])', get_label(' rounds'), $user_name, $rounds_played, format_coeff($normalization), $div, $policy->roundAv->add);
				}
				else
				{
					$normalization = $rounds_played == 0 ? 0 : 1 / $rounds_played;
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' rounds')) . '</p><p>';
					echo get_label('Player [1] played [2][0]. The final score is multiplied by [3] = 1 / [2]', get_label(' rounds'), $user_name, $rounds_played, format_coeff($normalization));
				}
			}
			else if (isset($policy->byWinRate))
			{
				$normalization = $games_played == 0 ? 0 : $games_won / $games_played;
				echo get_label('The final score is multiplied by winning rate.') . '</p><p>';
				echo get_label('Player [0] won [1] out of [2] games. The result is multiplied by [3] = [1] / [2]', $user_name, $games_won, $games_played, format_coeff($normalization));
			}
		}
		else
		{
			echo '<p>' . get_label('This policy is not applied to [0].', $user_name) . '</p>';
		}
		echo '</p></td><td width="48" align="center">';
		if ($qualifies)
		{
			echo format_coeff($normalization);
		}
		echo '</td></tr>';
		$total_normalization *= $normalization;
	}
	
	if (count($normalizer->policies) > 1)
	{
		echo '<tr class="darker"><td colspan="2"><p>' . get_label('Total') . ':</p></td><td align="center">' . format_coeff($total_normalization) . '</td></tr>';
	}
	
	echo '</table>';
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>