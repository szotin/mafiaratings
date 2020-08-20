<?php

require_once '../include/session.php';
require_once '../include/scoring.php';

initiate_session();

// function get_scoring_policy_label($policy)
// {
	// switch ($policy & SCORING_ROLE_FLAGS_ALL)
	// {
		// case SCORING_POLICY_STATIC:
			// return get_label('Static points');
		// case SCORING_POLICY_GAME_DIFFICULTY:
			// return get_label('Points depending on game difficulty (i.e. who wins more often civs or mafia)');
		// case SCORING_POLICY_FIRST_NIGHT_KILLING:
			// return get_label('Points depending on how often the player was killed the first night');
		// case SCORING_POLICY_FIRST_NIGHT_KILLING_FIGM:
			// return get_label('Points depending on how often the player was killed the first night by FIGM rules');
	// }
	// return get_label('Unknown');
// }


try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('scoring system')));
	}
	$id = (int)$_REQUEST['id'];
	
	if (isset($_REQUEST['version']))
	{
		$version = (int)$_REQUEST['version'];
		list($scoring, $name) = Db::record(get_label('scoring'), 'SELECT v.scoring, s.name FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id WHERE v.scoring_id = ? AND v.version = ?', $id, $version);
	}
	else
	{
		list($scoring, $name, $version) = Db::record(get_label('scoring'), 'SELECT v.scoring, s.name, v.version FROM scoring_versions v JOIN scorings s ON s.id = v.scoring_id WHERE v.scoring_id = ? ORDER BY version DESC LIMIT 1', $id);
		$version = (int)$version;
	}
	$scoring = json_decode($scoring);
	
	dialog_title(get_label('Scoring system [0]. Version [1].', $name, $version));
	
	echo '<table class="bordered light" width="100%">';
	foreach ($_scoring_groups as $group)
	{
		if (!isset($scoring->$group))
		{
			continue;
		}
		
		echo '<tr class="darker"><td colspan="2"><h4>' . get_scoring_group_label($group) . '</h4></td></tr>';
		foreach ($scoring->$group as $policy)
		{
			echo '<tr><td width="300">';
			echo get_scoring_matter_label($policy);
			echo '</td><td>';
			$roles = isset($policy->roles) ? $policy->roles : SCORING_ROLE_FLAGS_ALL;
			if (isset($policy->min_difficulty) || isset($policy->max_difficulty))
			{
				$min_difficulty = isset($policy->min_difficulty) ? $policy->min_difficulty : 0;
				$max_difficulty = isset($policy->max_difficulty) ? $policy->max_difficulty : 1;
				$min_points = isset($policy->min_points) ? $policy->min_points : 0;
				$max_points = isset($policy->max_points) ? $policy->max_points : 0;
				
				if ($min_difficulty <= 0)
				{
					$lower_text = get_label('when the game difficulty is 0%');
				}
				else
				{
					$lower_text = get_label('when the game difficulty is lower than [0]%', $min_difficulty * 100);
				}
				
				if ($max_difficulty >= 1)
				{
					$higher_text = get_label('when the game difficulty is 100%');
				}
				else
				{
					$higher_text = get_label('when the game difficulty is higher than [0]%', $max_difficulty * 100);
				}
				
				echo get_label('[0] get from [1] ([3]) to [2] ([4]) points.', 
					get_scoring_roles_label($roles),
					$min_points,
					$max_points,
					$lower_text,
					$higher_text);
			}
			else if (isset($policy->min_night1) || isset($policy->max_night1))
			{
				$min_night1 = isset($policy->min_night1) ? $policy->min_night1 : 0;
				$max_night1 = isset($policy->max_night1) ? $policy->max_night1 : 1;
				$min_points = isset($policy->min_points) ? $policy->min_points : 0;
				$max_points = isset($policy->max_points) ? $policy->max_points : 0;
				
				if ($min_night1 <= 0)
				{
					$lower_text = get_label('when player\'s first-night-killed rate is 0%');
				}
				else
				{
					$lower_text = get_label('when player\'s first-night-killed rate is lower than [0]%', $min_night1);
				}
				
				if ($max_night1 >= 1)
				{
					$higher_text = get_label('when first-night-killed rate is 100%');
				}
				else
				{
					$higher_text = get_label('when first-night-killed rate is higher than [0]%', $max_night1);
				}
				
				echo get_label('[0] get from [1] ([3]) to [2] ([4]) points.', 
					get_scoring_roles_label($roles),
					$min_points,
					$max_points,
					$lower_text,
					$higher_text);
			}
			else if (isset($policy->figm_first_night_score))
			{
				echo get_label('[0] get points depending on kill rate using FIGM rules.', get_scoring_roles_label($roles));
			}
			else
			{
				$points = isset($policy->points) ? $policy->points : 0;
				if ($points == 1)
				{
					echo get_label('[0] get 1 point.', get_scoring_roles_label($roles));
				}
				else
				{
					echo get_label('[0] get [1] points.', get_scoring_roles_label($roles), $points);
				}
			}
			echo '</td></tr>';
		}
	}
	
	$sorting = SCORING_DEFAULT_SORTING;
	if (isset($scoring->sorting))
	{
		$sorting = $scoring->sorting;
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