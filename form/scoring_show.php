<?php

require_once '../include/session.php';
require_once '../include/scoring.php';
require_once '../include/security.php';

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$scoring_id = (int)$_REQUEST['id'];
	
	if (isset($_REQUEST['version']))
	{
		$scoring_version = (int)$_REQUEST['version'];
		list($scoring, $name, $club_id, $league_id) = Db::record(get_label('scoring'), 'SELECT v.scoring, s.name, s.club_id, s.league_id FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id WHERE v.scoring_id = ? AND v.version = ?', $scoring_id, $scoring_version);
	}
	else
	{
		list($scoring, $name, $scoring_version, $club_id, $league_id) = Db::record(get_label('scoring'), 'SELECT v.scoring, s.name, v.version, s.club_id, s.league_id FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id WHERE v.scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
		$scoring_version = (int)$scoring_version;
	}
	$scoring = json_decode($scoring);
	
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
				list($normalizer, $name, $club_id, $league_id) = Db::record(get_label('scoring normalizer'), 'SELECT v.normalizer, s.name, s.club_id, s.league_id FROM normalizer_versions v JOIN normalizers s ON s.id = v.normalizer_id WHERE v.normalizer_id = ? AND v.version = ?', $normalizer_id, $normalizer_version);
			}
			else
			{
				list($normalizer, $name, $normalizer_version, $club_id, $league_id) = Db::record(get_label('scoring normalizer'), 'SELECT v.normalizer, s.name, v.version, s.club_id, s.league_id FROM normalizer_versions v JOIN normalizers s ON s.id = v.normalizer_id WHERE v.normalizer_id = ? ORDER BY version DESC LIMIT 1', $normalizer_id);
				$normalizer_version = (int)$normalizer_version;
			}
			$normalizer = json_decode($normalizer);
		}
	}
	
	$opt_flags = 0;
	if (isset($_REQUEST['ops_flags']))
	{
		$opt_flags = (int)$_REQUEST['ops_flags'];
	}
	
	dialog_title(get_label('Scoring system [0]. Version [1].', $name, $scoring_version));
	
	echo '<table class="bordered light" width="100%">';
	foreach ($_scoring_groups as $group)
	{
		if (!isset($scoring->$group))
		{
			continue;
		}
		
		$group_title_shown = false;
		foreach ($scoring->$group as $policy)
		{
			$text = NULL;
			$roles = isset($policy->roles) ? $policy->roles : SCORING_ROLE_FLAGS_ALL;
			$points = isset($policy->points) ? $policy->points : 0;
			if (is_string($points))
			{
				$text = 
					get_label('[0] get points according to the formula:', get_scoring_roles_label($roles)).
				 	'<p><button class="small_icon" onclick="mr.functionHelp()"><img src="images/function.png" width="12"></button> '.
					'<code>' . $points . ' </code></p>';
			}
			else if ($points == 1)
			{
				$text = get_label('[0] get 1 point.', get_scoring_roles_label($roles));
			}
			else if ($points != 0)
			{
				$text = get_label('[0] get [1] points.', get_scoring_roles_label($roles), $points);
			}
			
			if (!is_null($text))
			{
				if (!$group_title_shown)
				{
					echo '<tr class="darker"><td colspan="2"><h4>' . get_scoring_group_label($group) . '</h4></td></tr>';
					$group_title_shown = true;
				}
				echo '<tr><td width="300">' . get_scoring_matter_label($policy) . '</td><td>' . $text . '</td></tr>';
			}
		}
	}
	
	if (isset($scoring->counters) && count($scoring->counters) > 0)
	{
		echo '<tr class="darker"><td colspan="2"><h4>' . get_label('Counters') . '</h4></td></tr>';
		foreach ($scoring->counters as $counter)
		{
			$roles = isset($counter->roles) ? $counter->roles : SCORING_ROLE_FLAGS_ALL;
			echo '<tr><td width="300">' . get_scoring_matter_label($counter) . '</td><td>' . get_scoring_roles_label($roles) . '</td></tr>';
		}
	}
	
	$sorting = SCORING_DEFAULT_SORTING;
	if (isset($scoring->sorting))
	{
		$sorting = $scoring->sorting;
	}
	
	if (!is_null($normalizer) && isset($normalizer->policies))
	{
		echo '<tr class="darker"><td colspan="2"><h4>' . get_label('Scoring normalization to make players with different number of games comparable.') . '</h4></td></tr>';
		
		foreach ($normalizer->policies as $policy)
		{
			echo '<tr><td valign="top"><p>';
			$cond_type = 0;
			$cond = NULL;
			if (isset($policy->games))
			{
				$cond = $policy->games;
				$cond_type = 1;
				if (isset($cond->min) && $cond->min > 0)
				{
					echo get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label(' games');
					if (isset($cond->max))
					{
						echo get_label(' and less than') . ' ' . $cond->max . get_label(' games');
					}
				}
				else if (isset($cond->max))
				{
					echo get_label('For players who played less than') . ' ' . $cond->max . get_label(' games');
				}
				else
				{
					echo get_label('For all players');
				}
			}
			else if (isset($policy->gamesPerc))
			{
				$cond = $policy->gamesPerc;
				$cond_type = 2;
				if (isset($cond->min) && $cond->min > 0)
				{
					echo get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label('% of the games');
					if (isset($cond->max) && $cond->max < 100)
					{
						echo get_label(' and less than') . ' ' . $cond->max . get_label('% of the games');
					}
				}
				else if (isset($cond->max) && $cond->max < 100)
				{
					echo get_label('For players who played less than') . ' ' . $cond->max . get_label('% of the games');
				}
				else
				{
					echo get_label('For all players');
				}
			}
			else if (isset($policy->rounds))
			{
				$cond = $policy->rounds;
				$cond_type = 3;
				if (isset($cond->min) && $cond->min > 0)
				{
					echo get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label(' rounds');
					if (isset($cond->max))
					{
						echo get_label(' and less than') . ' ' . $cond->max . get_label(' rounds');
					}
				}
				else if (isset($cond->max))
				{
					echo get_label('For players who played less than') . ' ' . $cond->max . get_label(' rounds');
				}
				else
				{
					echo get_label('For all players');
				}
			}
			else if (isset($policy->roundsPerc))
			{
				$cond = $policy->roundsPerc;
				$cond_type = 4;
				if (isset($cond->min))
				{
					echo get_label('For players who played more than or equal to') . ' ' . $cond->min . get_label('% of the rounds');
					if (isset($cond->max) && $cond->max < 100)
					{
						echo get_label(' and less than') . ' ' . $cond->max . get_label('% of the rounds');
					}
				}
				else if (isset($cond->max) && $cond->max < 100)
				{
					echo get_label('For players who played less than') . ' ' . $cond->max . get_label('% of the rounds');
				}
				else
				{
					echo get_label('For all players');
				}
			}
			else if (isset($policy->winPerc))
			{
				$cond = $policy->winPerc;
				$cond_type = 5;
				if (isset($cond->min) && $cond->min > 0)
				{
					echo get_label('For players who has greater than or equal to') . ' ' . $cond->min . get_label('% of the wins');
					if (isset($cond->max) && $cond->max < 100)
					{
						echo get_label(' and lower than') . ' ' . $cond->max . get_label('% of the wins');
					}
				}
				else if (isset($cond->max) && $cond->max < 100)
				{
					echo get_label('For players who has lower than') . ' ' . $cond->max . get_label('% of the wins');
				}
				else
				{
					echo get_label('For all players');
				}
			}
			else
			{
				echo get_label('For all players');
			}
			
			echo '</p></td><td valign="top"><p>';
			if (isset($policy->multiply))
			{
				if (isset($policy->multiply->val))
				{
					if (isset($policy->multiply->max) && $cond != NULL && isset($cond->min) && isset($cond->max) && $cond->min < $cond->max)
					{
						echo get_label('The final score is multiplied by a value between [0] and [1] depending on ', $policy->multiply->val, $policy->multiply->max);
						$cond_sample_val = round(($cond->min + $cond->max) / 2);
						$sample_result = format_score(($policy->multiply->val * ($cond->max - $cond_sample_val) + $policy->multiply->max * ($cond_sample_val - $cond->min)) / ($cond->max - $cond->min));
						switch ($cond_type)
						{
							case 1: // games
								echo get_label('the number of[0] played by a player: by [1] for [2][0]; by [3] for [4][0]; by [5] for [6][0]; etc.', get_label(' games'), $policy->multiply->val, $cond->min, $policy->multiply->max, $cond->max, $sample_result, $cond_sample_val);
								break;
							case 2: // gamesPerc
								echo get_label('the number of[0] played by a player: by [1] for [2][0]; by [3] for [4][0]; by [5] for [6][0]; etc.', get_label('% of the games'), $policy->multiply->val, $cond->min, $policy->multiply->max, $cond->max, $sample_result, $cond_sample_val);
								break;
							case 3: // rounds
								echo get_label('the number of[0] played by a player: by [1] for [2][0]; by [3] for [4][0]; by [5] for [6][0]; etc.', get_label(' rounds'), $policy->multiply->val, $cond->min, $policy->multiply->max, $cond->max, $sample_result, $cond_sample_val);
								break;
							case 4: // roundsPerc
								echo get_label('the number of[0] played by a player: by [1] for [2][0]; by [3] for [4][0]; by [5] for [6][0]; etc.', get_label('% of the rounds'), $policy->multiply->val, $cond->min, $policy->multiply->max, $cond->max, $sample_result, $cond_sample_val);
								break;
							case 5: // winPerc
								echo get_label('a player winning rate: by [1] for [2][0]; by [3] for [4][0]; by [5] for [6][0]; etc.', get_label('% of the wins'), $policy->multiply->val, $cond->min, $policy->multiply->max, $cond->max, $sample_result, $cond_sample_val);
								break;
						}
					}
					else if ($policy->multiply->val != 1)
					{
						echo get_label('The final score is multiplied by [0].', $policy->multiply->val);
					}
					else
					{
						echo get_label('Nothing is done.');
					}
				}
				else if (isset($policy->multiply->max) && $policy->multiply->max != 1)
				{
					echo get_label('The final score is multiplied by [0].', $policy->multiply->max);
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
					echo get_label('The final score is divided by the number of[0] played by a player plus [1].', get_label(' games'), $policy->gameAv->add) . '</p><p>* ';
					echo get_label('For example if a player played 9[0] and scored 7.2 points, the final score is 7.2 / (9 + [1]) = [2].', get_label(' games'), $policy->gameAv->add, format_score(7.2 / (9 + $policy->gameAv->add)));
				}
				else if (isset($policy->gameAv->min))
				{
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' games')) . get_label(' If the number of games is lower than [0] it is still divided by [0].', $policy->gameAv->min) . '</p><p>* ';
					echo get_label('For example if a player played 2[0] and scored 2.4 points, the final score is 2.4 / max(2, [1]) = [2].', get_label(' games'), $policy->gameAv->add, format_score(2.4 / max(2, $policy->gameAv->add)));
				}
				else
				{
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' games')) . '</p><p>* ';
					echo get_label('For example if a player played 9[0] and scored 7.2 points, the final score is 7.2 / 9 = 0.8.', get_label(' games'));
				}
			}
			else if (isset($policy->roundAv))
			{
				if (isset($policy->roundAv->add))
				{
					echo get_label('The final score is divided by the number of[0] played by a player plus [1].', get_label(' rounds'), $policy->roundAv->add) . '</p><p>* ';
					echo get_label('For example if a player played 9[0] and scored 7.2 points, the final score is 7.2 / (9 + [1]) = [2].', get_label(' rounds'), $policy->roundAv->add, format_score(7.2 / (9 + $policy->roundAv->add)));
				}
				else if (isset($policy->roundAv->min))
				{
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' rounds')) . get_label(' If the number of rounds is lower than [0] it is still divided by [0].', $policy->roundAv->min) . '</p><p>* ';
					echo get_label('For example if a player played 2[0] and scored 2.4 points, the final score is 2.4 / max(2, [1]) = [2].', get_label(' rounds'), $policy->roundAv->add, format_score(2.4 / max(2, $policy->roundAv->add)));
				}
				else
				{
					echo get_label('The final score is divided by the number of[0] played by a player.', get_label(' rounds')) . '</p><p>* ';
					echo get_label('For example if a player played 9[0] and scored 7.2 points, the final score is 7.2 / 9 = 0.8.', get_label(' rounds'));
				}
			}
			else if (isset($policy->byWinRate))
			{
				echo get_label('The final score is multiplied by winning rate.') . '</p><p>* ' . get_label('For example if a player played 82 games, won 44 of them, and scored 46.6 points, the final result will be calculated as 46.6 * win_rate = 46.6 * 44 / 82 = 25.0');
			}
			echo '</p></td></tr>';
		}
	}
	
	echo '<tr class="darker"><td colspan="2"><h4>' . get_label('When the scores are the same') . '</h4></td></tr>';
	$inside_brackets = false;
	$sorting_text = '';
	$compare_text = get_label('higher');
	$delimiter_text = '';
	$first = true;
	for ($i = 0; $i < strlen($sorting); ++$i)
	{
		$ch = $sorting[$i];
		switch ($ch)
		{
			case SCORING_SORTING_MAIN_POINTS:
				$sorting_text .= $delimiter_text . get_label('main points');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_LEGACY_POINTS:
				$sorting_text .= $delimiter_text . get_label('legacy points');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_EXTRA_POINTS:
				$sorting_text .= $delimiter_text . get_label('extra points');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_PENALTY_POINTS:
				$sorting_text .= $delimiter_text . get_label('penalty points');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_NIGHT1_POINTS:
				$sorting_text .= $delimiter_text . get_label('points for being killed first night');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_WIN:
				$sorting_text .= $delimiter_text . get_label('number of wins');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_SPECIAL_ROLE_WIN:
				$sorting_text .= $delimiter_text . get_label('number of special role wins');
				$delimiter_text = get_label(' plus ');
				break;
			case SCORING_SORTING_KILLED_FIRST_NIGHT:
				$sorting_text .= $delimiter_text . get_label('times being killed first night');
				$delimiter_text = get_label(' plus ');
				break;
			case '-':
				$compare_text = get_label('lower');
				break;
			case '(';
				$inside_brackets = true;
				$delimiter_text = get_label('sum of: ');
				break;
			case ')';
				$inside_brackets = false;
				break;
		}
		
		if (!$inside_brackets && !empty($sorting_text))
		{
			echo '<tr><td colspan="2">';
			if ($first)
			{
				echo get_label('The winner is the one who has [0] [1].', $compare_text, $sorting_text);
			}
			else
			{
				echo get_label('If still equal, the winner is the one who has [0] [1].', $compare_text, $sorting_text);
			}
			echo '</td></tr>';
			$sorting_text = '';
			$compare_text = get_label('higher');
			$delimiter_text = '';
			$first = false;
		}
	}
	echo '<tr><td colspan="2">';
	if ($first)
	{
		echo get_label('The winner is the one who registered with [0] first.', PRODUCT_NAME);
	}
	else
	{
		echo get_label('If still equal, the winner is the one who registered with [0] first.', PRODUCT_NAME);
	}
	echo '</td></tr>';
	
	
	echo '</table>';
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>