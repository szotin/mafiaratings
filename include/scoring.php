<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/constants.php';

define('SCORING_DEFAULT_ID', 19); // Default scoring system is hardcoded here to ФИИМ (FIIM)
define('NORMALIZER_DEFAULT_ID', NULL); // Default normalizer is hardcoded here to no-normalizer.

define('SCORING_ROLE_FLAGS_CIV', 1);
define('SCORING_ROLE_FLAGS_SHERIFF', 2);
define('SCORING_ROLE_FLAGS_RED', 3);
define('SCORING_ROLE_FLAGS_MAF', 4);
define('SCORING_ROLE_FLAGS_CIV_MAF', 5);
define('SCORING_ROLE_FLAGS_SHERIFF_MAF', 6);
define('SCORING_ROLE_FLAGS_EXCEPT_DON', 7);
define('SCORING_ROLE_FLAGS_DON', 8);
define('SCORING_ROLE_FLAGS_CIV_DON', 9);
define('SCORING_ROLE_FLAGS_SHERIFF_DON', 10);
define('SCORING_ROLE_FLAGS_EXCEPT_MAF', 11);
define('SCORING_ROLE_FLAGS_BLACK', 12);
define('SCORING_ROLE_FLAGS_EXCEPT_SHERIFF', 13);
define('SCORING_ROLE_FLAGS_EXCEPT_CIV', 14);
define('SCORING_ROLE_FLAGS_ALL', 15);

define('SCORING_FLAG_PLAY', 0x1); // 1: Matter 0 - Played the game
define('SCORING_FLAG_WIN', 0x2); //  2: Matter 1 - Player wins
define('SCORING_FLAG_LOSE', 0x4); // 4: Matter 2 - Player loses
define('SCORING_FLAG_CLEAR_WIN', 0x8); // 8: Matter 3 - All players killed in a daytime were from another team
define('SCORING_FLAG_CLEAR_LOSE', 0x10); // 16: Matter 4 - All players killed in a daytime were from player's team
define('SCORING_FLAG_BEST_PLAYER', 0x20); // 32: Matter 5 - Best player
define('SCORING_FLAG_BEST_MOVE', 0x40); // 64: Matter 6 - Best move
define('SCORING_FLAG_SURVIVE', 0x80); // 128: Matter 7 - Survived in the game
define('SCORING_FLAG_KILLED_FIRST_NIGHT', 0x100); // 256: Matter 8 - Killed in the first night
define('SCORING_FLAG_KILLED_NIGHT', 0x200); // 512: Matter 9 - Killed in the night
define('SCORING_FLAG_FIRST_LEGACY_3', 0x400); // 1024: Matter 10 - Guessed 3 mafia after being killed first night
define('SCORING_FLAG_FIRST_LEGACY_2', 0x800); // 2048: Matter 11 - Guessed 2 mafia after being killed first night
define('SCORING_FLAG_WARNINGS_4', 0x1000); // 4096: Matter 12 - Killed by warnings
define('SCORING_FLAG_KICK_OUT', 0x2000); // 8192: Matter 13 - Kicked out
define('SCORING_FLAG_SURRENDERED', 0x4000); // 16384: Matter 14 - Surrendered
define('SCORING_FLAG_ALL_VOTES_VS_MAF', 0x8000); // 32768: Matter 15 - All votes vs mafia (>3 votings)
define('SCORING_FLAG_ALL_VOTES_VS_CIV', 0x10000); // 65536: Matter 16 - All votes vs civs (>3 votings)
define('SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING', 0x20000); // 131072: Matter 17 - Killed sheriff next day after finding
define('SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT', 0x40000); // 262144: Matter 18 - Sheriff was found first night
define('SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT', 0x80000); // 524288: Matter 19 - Sheriff was killed the first night
define('SCORING_FLAG_BLACK_CHECKS', 0x100000); // 1048576: Matter 20 - Sheriff did three black checks in a row
define('SCORING_FLAG_RED_CHECKS', 0x200000); // 2097152: Matter 21 - All sheriff checks are red
define('SCORING_FLAG_EXTRA_POINTS', 0x400000); // 4194304: Matter 22 - Player has manually assigned extra points
define('SCORING_FLAG_FIRST_LEGACY_1', 0x800000); // 8388608: Matter 23 - Guessed 1 mafia after being killed first night

define('SCORING_STAT_FLAG_GAME_DIFFICULTY', 0x1);
define('SCORING_STAT_FLAG_FIRST_NIGHT_KILLING', 0x2);
define('SCORING_STAT_FLAG_FIRST_NIGHT_KILLING_FIIM', 0x4);

define('SCORING_SORTING_MAIN_POINTS', 'm');
define('SCORING_SORTING_LEGACY_POINTS', 'g');
define('SCORING_SORTING_EXTRA_POINTS', 'e');
define('SCORING_SORTING_PENALTY_POINTS', 'p');
define('SCORING_SORTING_NIGHT1_POINTS', 'n');
define('SCORING_SORTING_WIN', 'w');
define('SCORING_SORTING_SPECIAL_ROLE_WIN', 's');
define('SCORING_SORTING_KILLED_FIRST_NIGHT', 'k');

define('SCORING_DEFAULT_SORTING', '(epg)wsk');

define('COMPETITION_EVENT', 0);
define('COMPETITION_TOURNAMENT', 1);
define('COMPETITION_CLUB', 2);
define('COMPETITION_LEAGUE', 3);

define('COMPETITION_FLAG_EVENT', 0x01);
define('COMPETITION_FLAG_EVENT_OPT', 0x02);
define('COMPETITION_FLAG_TOURNAMENT', 0x04);
define('COMPETITION_FLAG_TOURNAMENT_OPT', 0x08);
define('COMPETITION_FLAG_CLUB', 0x10);
define('COMPETITION_FLAG_CLUB_OPT', 0x20);
define('COMPETITION_FLAG_LEAGUE', 0x40);
define('COMPETITION_FLAG_LEAGUE_OPT', 0x80);

define('SCORING_GROUP_MAIN', 'main'); // points for wins/loses
define('SCORING_GROUP_LEGACY', 'legacy'); // points for guessing 3 mafs by first night victim.
define('SCORING_GROUP_EXTRA', 'extra'); // extra points assigned by moderator, or earned by custom actions
define('SCORING_GROUP_PENALTY', 'penalty'); // points (most likely negative) for taking warnings and other discipline offences
define('SCORING_GROUP_NIGHT1', 'night1'); // points for being killed first night

define('SCORING_LOD_PER_GROUP', 1); // scoring returns points per group in $player->main, $player->legacy, $player->extra, $player->penalty, and $player->night1 fields.
define('SCORING_LOD_PER_POLICY', 2); // scoring returns points per policy for each group in $player->main_points, $player->legacy_points, $player->extra_points, $player->penalty_points, and $player->night1_policies fields.
define('SCORING_LOD_HISTORY', 4); // scoring returns player history in $player->history field. It contains an array of points with timestamp and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_PER_GAME', 8); // scoring returns scores for every game a player played in $player->games field. It contains an array of games with timestamp, game_id, and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_NO_SORTING', 16); // When set sorting returns associative array player_id => player. When not set scoring returns array of players sorted by total score.
define('SCORING_LOD_TEAMS', 32); // Outputs team scores instead of player scores. Works for team tournaments only.

define('SCORING_OPTION_NO_NIGHT_KILLS', 1); // Do not use policies dependent on the night kills
define('SCORING_OPTION_NO_GAME_DIFFICULTY', 2); // Do not use policies dependent on the game difficulty

$_scoring_groups = array(SCORING_GROUP_MAIN, SCORING_GROUP_EXTRA, SCORING_GROUP_LEGACY, SCORING_GROUP_PENALTY, SCORING_GROUP_NIGHT1);

function format_coeff($coeff, $sign_digits = 3)
{
	return round($coeff, $sign_digits - floor(log10($coeff)) - 1);
}

function format_score($score, $zeroes = true)
{
	if ($score == 0 && !$zeroes)
	{
		return '';
	}
	
	$int_score = (int)($score * 100);
	if (($int_score % 10) != 0)
	{
		return number_format($score, 2);
	}
	else if (($int_score % 100) != 0)
	{
		return number_format($score, 1);
	}
	return number_format($score);
}

function format_rating($rating)
{
	$fraction = 100;
	$rat = abs($rating);
	$digits = 0;
	if ($rat > 0.0001)
	{
		while ($rat < $fraction)
		{
			$fraction /= 10;
			++$digits;
		}
	}
	return number_format($rating, $digits);
}

define('SCORING_SELECT_FLAG_NO_PREFIX', 1);
define('SCORING_SELECT_FLAG_NO_VERSION', 2);
define('SCORING_SELECT_FLAG_NO_FLAGS_OPTION', 8);
define('SCORING_SELECT_FLAG_NO_WEIGHT_OPTION', 16);
define('SCORING_SELECT_FLAG_NO_GROUP_OPTION', 32);
define('SCORING_SELECT_FLAG_NO_NORMALIZER', 64);
define('SCORING_SELECT_FLAG_NO_OPTIONS', SCORING_SELECT_FLAG_NO_FLAGS_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION);

function show_scoring_select($club_id, $scoring_id, $scoring_version, $normalizer_id, $normalizer_version, $options, $options_separator, $on_change, $flags, $name = NULL)
{
	if ($name == NULL)
	{
		$name = 'scoring';
	}
	
	if (($flags & SCORING_SELECT_FLAG_NO_PREFIX) == 0)
	{
		echo '<a href="#" onclick="mr.showScoring(\'' . $name . '\')" title="' . get_label('Show scoring rules.') . '">' . get_label('Scoring system') . ':</a> ';
	}
	echo '<select id="' . $name . '-sel" name="' . $name . '_id" onChange="mr.onChangeScoring(\'' . $name . '\', 0, ' . $on_change . ')" title="' . get_label('Scoring system') . '">';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		list ($sid, $sname) = $row;
		show_option($sid, $scoring_id, $sname);
	}
	echo '</select>';
	if (($flags & SCORING_SELECT_FLAG_NO_VERSION) == 0)
	{
		echo ' ' . get_label('version') . ': <select id="' . $name . '-ver" name="' . $name . '_ver" onchange="mr.onChangeScoringVersion(\'' . $name . '\', null, ' . $on_change . ')"></select><span id="' . $name . '-opt"></span>';
	}
	
	if (($flags & SCORING_SELECT_FLAG_NO_OPTIONS) != SCORING_SELECT_FLAG_NO_OPTIONS)
	{
		echo $options_separator;
		if (($flags & SCORING_SELECT_FLAG_NO_WEIGHT_OPTION) == 0)
		{
			$options_weight = 1;
			if (isset($options->weight))
			{
				$options_weight = $options->weight;
			}
			echo $options_separator . get_label('Points weight') . ': <input type="number" style="width: 40px;" step="0.1" min="0.1" id="' . $name . '-weight" value="' . $options_weight . '" onchange="optionChanged()">';
		}
		if (($flags & SCORING_SELECT_FLAG_NO_FLAGS_OPTION) == 0)
		{
			$options_flags = 0;
			if (isset($options->flags))
			{
				$options_flags = $options->flags;
			}
		
			echo $options_separator . '<input type="checkbox" id="' . $name . '-night1" onclick="optionChanged()"';
			if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('use first night kill rate factor');
			
			echo $options_separator . '<input type="checkbox" id="' . $name . '-difficulty" onclick="optionChanged()"';
			if (($options_flags & SCORING_OPTION_NO_GAME_DIFFICULTY) == 0)
			{
				echo ' checked';
			}
			echo '> ' . get_label('use game difficulty factor');
		}
		if (($flags & SCORING_SELECT_FLAG_NO_GROUP_OPTION) == 0)
		{
			$options_group = '';
			if (isset($options->group))
			{
				$options_group = $options->group;
			}
			echo $options_separator . '<div id="' . $name . '-group-div">' . get_label('Tournament group') . ': <select id="' . $name . '-group" onchange="optionChanged()" title="' . get_label('Tournament rounds can be grouped to calculate stats required for scoring seperately. For example, compensation for being shot first night (Ci) can be calculated in the finals separately. In this case main round and semi-finals can belong to \'main\' group, and finals to \'final\' group.') . '">';
			show_option('', $options_group, '');
			show_option('pre', $options_group, get_label('preliminary rounds'));
			show_option('main', $options_group, get_label('main rounds'));
			show_option('final', $options_group, get_label('final rounds'));
			echo '</select></div>';
		}
	}
	
	if (($flags & SCORING_SELECT_FLAG_NO_NORMALIZER) == 0)
	{
		echo '<p>';
		if (is_null($normalizer_id) || $normalizer_id < 0)
		{
			$normalizer_id = 0;
		}
		
		echo get_label('Scoring normalizer') . ': ';
		echo '<select id="' . $name . '-norm-sel" name="' . $name . '-norm-sel" onChange="mr.onChangeNormalizer(\'' . $name . '\', 0, ' . $on_change . ')" title="' . get_label('Scoring normalizer') . '">';
		show_option(0, $normalizer_id, get_label('No scoring normalization'));
		$query = new DbQuery('SELECT id, name FROM normalizers WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
		while ($row = $query->next())
		{
			list ($nid, $nname) = $row;
			show_option($nid, $normalizer_id, $nname);
		}
		echo '</select>';
		echo '<span id="' . $name . '-norm-version"> ' . get_label('version') . ': <select id="' . $name . '-norm-ver" name="' . $name . '-norm-ver" onChange="mr.onChangeNormalizerVersion(\'' . $name . '\', ' . $on_change . ')"></select></span></p>';
	}
	
	echo '<script>';
	echo 'function optionChanged() { mr.onChangeScoringOptions(\'' . $name . '\', ' . $on_change . '); } ';
	echo 'mr.onChangeScoring("' . $name . '", ' . $scoring_version . '); ';
	if (($flags & SCORING_SELECT_FLAG_NO_NORMALIZER) == 0)
	{
		echo 'mr.onChangeNormalizer("' . $name . '", ' . $normalizer_version . '); ';
	}
	echo '</script>';
}

define('ROLE_NAME_FLAG_LOWERCASE', 1);
define('ROLE_NAME_FLAG_SINGLE', 2);
define('ROLE_NAME_MASK_ALL', 3);

function get_role_name($role, $flags = 0)
{
	switch ($flags & ROLE_NAME_MASK_ALL)
	{
		case 0:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('All roles');
				case POINTS_RED:
					return get_label('Reds');
				case POINTS_DARK:
					return get_label('Blacks');
				case POINTS_CIVIL:
					return get_label('Civilians');
				case POINTS_SHERIFF:
					return get_label('Sheriff');
				case POINTS_MAFIA:
					return get_label('Mafiosi');
				case POINTS_DON:
					return get_label('Don');
			}
			break;
			
		case ROLE_NAME_FLAG_LOWERCASE:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('all roles');
				case POINTS_RED:
					return get_label('reds');
				case POINTS_DARK:
					return get_label('blacks');
				case POINTS_CIVIL:
					return get_label('civilians');
				case POINTS_SHERIFF:
					return get_label('sheriff');
				case POINTS_MAFIA:
					return get_label('mafiosi');
				case POINTS_DON:
					return get_label('don');
			}
			break;
			
		case ROLE_NAME_FLAG_SINGLE:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('Any role');
				case POINTS_RED:
					return get_label('Red');
				case POINTS_DARK:
					return get_label('Black');
				case POINTS_CIVIL:
					return get_label('Civilian');
				case POINTS_SHERIFF:
					return get_label('Sheriff');
				case POINTS_MAFIA:
					return get_label('Mafiosi');
				case POINTS_DON:
					return get_label('Don');
			}
			break;
			
		case ROLE_NAME_MASK_ALL:
			switch ($role)
			{
				case POINTS_ALL:
					return get_label('any role');
				case POINTS_RED:
					return get_label('red');
				case POINTS_DARK:
					return get_label('black');
				case POINTS_CIVIL:
					return get_label('civilian');
				case POINTS_SHERIFF:
					return get_label('sheriff');
				case POINTS_MAFIA:
					return get_label('mafiosi');
				case POINTS_DON:
					return get_label('don');
			}
			break;
	}
	return '';
}

function show_roles_select($roles, $on_change, $title, $flags = 0)
{
	echo '<select name="roles" id="roles" onChange="' . $on_change . '" title="' . $title . '">';
	show_option(POINTS_ALL, $roles, get_role_name(POINTS_ALL, $flags));
	show_option(POINTS_RED, $roles, get_role_name(POINTS_RED, $flags));
	show_option(POINTS_DARK, $roles, get_role_name(POINTS_DARK, $flags));
	show_option(POINTS_CIVIL, $roles, get_role_name(POINTS_CIVIL, $flags));
	show_option(POINTS_SHERIFF, $roles, get_role_name(POINTS_SHERIFF, $flags));
	show_option(POINTS_MAFIA, $roles, get_role_name(POINTS_MAFIA, $flags));
	show_option(POINTS_DON, $roles, get_role_name(POINTS_DON, $flags));
	echo '</select>';
}

function get_roles_condition($roles)
{
	$role_condition = new SQL();
	switch ($roles)
	{
	case POINTS_RED:
		$role_condition->add(' AND p.role < 2');
		break;
	case POINTS_DARK:
		$role_condition->add(' AND p.role > 1');
		break;
	case POINTS_CIVIL:
		$role_condition->add(' AND p.role = 0');
		break;
	case POINTS_SHERIFF:
		$role_condition->add(' AND p.role = 1');
		break;
	case POINTS_MAFIA:
		$role_condition->add(' AND p.role = 2');
		break;
	case POINTS_DON:
		$role_condition->add(' AND p.role = 3');
		break;
	}
	return $role_condition;
}

function get_scoring_stat_flags($scoring, $options)
{
	global $_scoring_groups;
	$options_flags = 0;
	if (isset($options->flags))
	{
		$options_flags = $options->flags;
	}
	
	if (($options_flags & (SCORING_OPTION_NO_GAME_DIFFICULTY + SCORING_OPTION_NO_NIGHT_KILLS)) == SCORING_OPTION_NO_GAME_DIFFICULTY + SCORING_OPTION_NO_NIGHT_KILLS)
	{
		return 0;
	}
	
	// check flags and options
	$stat_flags = 0;
	foreach ($_scoring_groups as $group_name)
	{
		if (!isset($scoring->$group_name))
		{
			continue;
		}
		
		$group = $scoring->$group_name;
		for ($i = 0; $i < count($group); ++$i)
		{
			$policy = $group[$i];
			if (isset($policy->min_difficulty) || isset($policy->max_difficulty))
			{
				if (!isset($policy->min_difficulty))
				{
					$policy->min_difficulty = 0;
				}
				if (!isset($policy->max_difficulty))
				{
					$policy->max_difficulty = 1;
				}
				if (($options_flags & SCORING_OPTION_NO_GAME_DIFFICULTY) == 0)
				{
					$stat_flags |= SCORING_STAT_FLAG_GAME_DIFFICULTY;
				}
			}
			else if (isset($policy->min_night1) || isset($policy->max_night1))
			{
				if (!isset($policy->min_night1))
				{
					$policy->min_night1 = 0;
				}
				if (!isset($policy->max_night1))
				{
					$policy->max_night1 = 1;
				}
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING;
				}
			}
			else if (isset($policy->fiim_first_night_score))
			{
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING_FIIM;
				}
			}
		}
	}
	return $stat_flags;
}
    
function init_player_score($player, $scoring, $lod_flags)
{
    global $_scoring_groups;
    
	$player->scoring = $scoring;
    $player->points = 0;
	
    if ($lod_flags & SCORING_LOD_PER_GROUP)
    {
        foreach ($_scoring_groups as $group)
        {
            $g = $group . '_points';
            $player->$g = 0;
        }
    }
    
    if ($lod_flags & SCORING_LOD_PER_POLICY)
    {
        foreach ($_scoring_groups as $group)
        {
            $a = array();
            if (isset($scoring->$group))
            {
                foreach ($scoring->$group as $policy)
                {
                    $a[] = 0;
                }
            }
            $player->$group = $a;
        }
		$player->extra[] = 0;
    }
    
    if ($lod_flags & SCORING_LOD_HISTORY)
    {
        $player->history = array();
    }
    
    if ($lod_flags & SCORING_LOD_PER_GAME)
    {
        $player->games = array();
    }
}

function add_player_score($player, $scoring, $game_id, $game_end_time, $game_flags, $game_role, $extra_pts, $red_win_rate, $games_count, $killed_first_count, $lod_flags, $options, $event_name = NULL)
{
	global $_scoring_groups;
	$options_flags = 0;
	if (isset($options->flags))
	{
		$options_flags = $options->flags;
	}
	
	$weight = 1;
	if (isset($options->weight))
	{
		$weight = $options->weight;
	}
	
	$total_points = 0;
	if ($lod_flags & SCORING_LOD_PER_GROUP)
	{
		foreach ($_scoring_groups as $group)
		{
            $g = $group . '_points';
			$$g = 0;
		}
	}
	if ($lod_flags & SCORING_LOD_PER_POLICY)
	{
		$per_policy = new stdClass();
		foreach ($_scoring_groups as $group)
		{
			$a = array();
			if (isset($scoring->$group))
			{
				foreach ($scoring->$group as $policy)
				{
					$a[] = 0;
				}
			}
			$per_policy->$group = $a;
		}
	}
	
	$role = 1 << $game_role;
	foreach ($_scoring_groups as $group_name)
	{
		if (!isset($scoring->$group_name))
		{
			continue;
		}
		 
		$group = $scoring->$group_name;
		for ($i = 0; $i < count($group); ++$i)
		{
			$policy = $group[$i];
			if (($policy->matter & $game_flags) != $policy->matter)
			{
				continue;
			}
			
			if (isset($policy->roles) && ($policy->roles & $role) == 0)
			{
				continue;
			}
			
			$points = 0;
			if (isset($policy->points))
			{
				$points = $policy->points;
			}
			else if (isset($policy->min_difficulty))
			{
				if ($options_flags & SCORING_OPTION_NO_GAME_DIFFICULTY)
				{
					$points = $policy->min_points;
				}
				else
				{
					$difficulty = $red_win_rate;
					if ($role & SCORING_ROLE_FLAGS_RED)
					{
						$difficulty = max(min(1 - $difficulty, 1), 0);
					}
					
					if ($difficulty <= $policy->min_difficulty)
					{
						$points = $policy->min_points;
					}
					else if ($difficulty >= $policy->max_difficulty)
					{
						$points = $policy->max_points;
					}
					else
					{
						$points = ($policy->max_points - $policy->min_points) * $difficulty;
						$points += $policy->min_points * $policy->max_difficulty - $policy->max_points * $policy->min_difficulty;
						$points /= $policy->max_difficulty - $policy->min_difficulty;
					}
				}
			}
			else if (isset($policy->min_night1) || isset($policy->max_night1))
			{
				if ($options_flags & SCORING_OPTION_NO_NIGHT_KILLS)
				{
					$points = $policy->min_points;
				}
				else
				{
					$rate = 0;
					if ($games_count > 0)
					{
						$rate = max(min($killed_first_count / $games_count, 1), 0);
					}
					
					if ($rate <= $policy->min_night1)
					{
						$points = $policy->min_points;
					}
					else if ($rate >= $policy->max_night1)
					{
						$points = $policy->max_points;
					}
					else
					{
						$points = ($policy->max_points - $policy->min_points) * $rate;
						$points += $policy->min_points * $policy->max_night1 - $policy->max_points * $policy->min_night1;
						$points /= $policy->max_night1 - $policy->min_night1;
					}
				}
			}
			else if (isset($policy->fiim_first_night_score))
			{
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$points = round($games_count * $policy->fiim_first_night_score);
					if ($points != 0)
					{
						$points = max(min($killed_first_count * $policy->fiim_first_night_score / $points, $policy->fiim_first_night_score), 0);
					}
				}
			}
			else if (isset($policy->extra_points_weight))
			{
				$points = $policy->extra_points_weight * $extra_pts;
			}
			
			// if ($player->id == 25)
			// {
				// echo 'game_id: ' . $game_id . '; matter: ' . $policy->matter . '; points: ' . $points;
				// if ($weight != 1)
				// {
					// echo ' * ' . $weight . ' = ' . ($points * $weight);
				// }
				// echo '<br>';
			// } 
            $points *= $weight;
			$total_points += $points;
			if ($lod_flags & SCORING_LOD_PER_GROUP)
			{
                $g = $group_name . '_points';
				$$g += $points;
			}
			if ($lod_flags & SCORING_LOD_PER_POLICY)
			{
				$g_array = &$per_policy->$group_name;
				$g_array[$i] += $points;
			}
		}
	}
	
	$player->points += $total_points;
    if ($lod_flags & SCORING_LOD_PER_GROUP)
    {
        foreach ($_scoring_groups as $group)
        {
            $g = $group . '_points';
            $player->$g += $$g;
        }
    }
    if ($lod_flags & SCORING_LOD_PER_POLICY)
    {
        foreach ($_scoring_groups as $group)
        {
			$pg_array = &$player->$group;
			$ppg_array = &$per_policy->$group;
            for ($i = 0; $i < count($ppg_array); ++$i)
            {
				$pg_array[$i] += $ppg_array[$i];
            }
        }
    }
    
    if ($lod_flags & SCORING_LOD_HISTORY)
    {
        $history_point = new stdClass();
        $history_point->game_id = (int)$game_id;
        $history_point->time = (int)$game_end_time;
        $history_point->points = $player->points;
        if ($lod_flags & SCORING_LOD_PER_GROUP)
        {
            foreach ($_scoring_groups as $group)
            {
                $g = $group . '_points';
                $history_point->$g = $player->$g;
            }
        }
        if ($lod_flags & SCORING_LOD_PER_POLICY)
        {
            foreach ($_scoring_groups as $group)
            {
                $history_point->$group = $player->$group; // arrays are copied by value in php
            }
        }
        $player->history[] = $history_point;
    }
    
    if ($lod_flags & SCORING_LOD_PER_GAME)
    {
        $game = new stdClass();
        $game->game_id = (int)$game_id;
		$game->flags = (int)$game_flags;
        $game->time = (int)$game_end_time;
        $game->points = $total_points;
		$game->role = (int)$game_role;
		$game->won = ($game_flags & SCORING_FLAG_WIN) ? true : false;
		if (!is_null($event_name))
		{
			$game->event_name = $event_name;
		}
        if ($lod_flags & SCORING_LOD_PER_GROUP)
        {
            foreach ($_scoring_groups as $group)
            {
                $g = $group . '_points';
                $game->$g = $$g;
            }
        }
        if ($lod_flags & SCORING_LOD_PER_POLICY)
        {
            foreach ($_scoring_groups as $group)
            {
                $game->$group = $per_policy->$group;
            }
        }
        $player->games[] = $game;
    }
}

function compare_scores($player1, $player2)
{
	if ($player2->points > $player1->points + 0.00001)
	{
		return 1;
	}
	else if ($player2->points < $player1->points - 0.00001)
	{
		return -1;
	}
	
	$sorting = SCORING_DEFAULT_SORTING;
	if (isset($player1->scoring->sorting))
	{
		$sorting = $player1->scoring->sorting;
	}
	
	$in_brackets = false;
	$value1 = 0;
	$value2 = 0;
	$sign = 1;
	for ($i = 0; $i < strlen($sorting); ++$i)
	{
		$char = $sorting[$i];
		if ($char == '-')
		{
			$sign = -1;
			continue;
		}
		else if ($char == '(')
		{
			$in_brackets = true;
			$value1 = 0;
			$value2 = 0;
			continue;
		}
		else if ($char == ')')
		{
			$in_brackets = false;
			continue;
		}
		
		if (!$in_brackets)
		{
			$value1 = 0;
			$value2 = 0;
		}
		
		//'legacy', 'penalty', 'night1'
		switch ($char)
		{
			case SCORING_SORTING_MAIN_POINTS:
				if (isset($player1->main_points))
				{
					$value1 += $player1->main_points * $sign;
				}
				if (isset($player2->main))
				{
					$value2 += $player2->main_points * $sign;
				}
				break;
			case SCORING_SORTING_LEGACY_POINTS:
				if (isset($player1->legacy_points))
				{
					$value1 += $player1->legacy_points * $sign;
				}
				if (isset($player2->legacy_points))
				{
					$value2 += $player2->legacy_points * $sign;
				}
				break;
			case SCORING_SORTING_EXTRA_POINTS:
				if (isset($player1->extra_points))
				{
					$value1 += $player1->extra_points * $sign;
				}
				if (isset($player2->extra_points))
				{
					$value2 += $player2->extra_points * $sign;
				}
				break;
			case SCORING_SORTING_PENALTY_POINTS:
				if (isset($player1->penalty_points))
				{
					$value1 += $player1->penalty_points * $sign;
				}
				if (isset($player2->penalty_points))
				{
					$value2 += $player2->penalty_points * $sign;
				}
				break;
			case SCORING_SORTING_NIGHT1_POINTS:
				if (isset($player1->night1_points))
				{
					$value1 += $player1->night1_points * $sign;
				}
				if (isset($player2->night1_points))
				{
					$value2 += $player2->night1_points * $sign;
				}
				break;
			case SCORING_SORTING_WIN:
				$value1 += $player1->wins * $sign;
				$value2 += $player2->wins * $sign;
				break;
			case SCORING_SORTING_SPECIAL_ROLE_WIN:
				$value1 += $player1->special_role_wins * $sign;
				$value2 += $player2->special_role_wins * $sign;
				break;
			case SCORING_SORTING_KILLED_FIRST_NIGHT:
				$value1 += $player1->killed_first_count * $sign;
				$value2 += $player2->killed_first_count * $sign;
				break;
		}
		
		if (!$in_brackets)
		{
			if ($value2 > $value1 + 0.00001)
			{
				return 1;
			}
			else if ($value2 < $value1 - 0.00001)
			{
				return -1;
			}
		}
		$sign = 1;
	}
	
	if ($player1->id > $player2->id)
	{
		return 1;
	}
	else if ($player1->id < $player2->id)
	{
		return -1;
	}
	return 0;
}

function get_user_page($players, $user_id, $page_size)
{
	$count = count($players);
	for ($i = 0; $i < $count; ++$i)
	{
		if ($players[$i]->id == $user_id)
		{
			return floor($i / $page_size);
		}
	}
	return -1;
}
    
function get_players_condition($players_list)
{
    $players_condition_str = '';
    if (is_array($players_list) && count($players_list) > 0)
    {
        $delimiter = ' AND p.user_id IN (';
        foreach ($players_list as $player_id)
        {
            if (is_object($player_id))
            {
                $player_id = $player_id->id;
            }
            if (is_numeric($player_id))
            {
                $players_condition_str .= $delimiter . $player_id;
                $delimiter = ', ';
            }
        }
        $players_condition_str .= ')';
    }
	else if (is_numeric($players_list))
	{
		$players_condition_str = ' AND p.user_id = ' . $players_list;
	}
    return new SQL($players_condition_str);
}
    
function event_scores($event_id, $players_list, $lod_flags, $scoring, $options)
{
	global $_scoring_groups;
	
	$players = array();
	$stat_flags = get_scoring_stat_flags($scoring, $options);
    
    // prepare additional filter
    $condition = get_players_condition($players_list);
	
	// Calculate game difficulty rates
	$red_win_rate = 0;
	if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
	{
		list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(id), SUM(IF(result = 1, 1, 0)) FROM games WHERE event_id = ? AND result > 0 AND is_canceled = 0 AND is_rating <> 0', $event_id);
		if ($count > 0)
		{
			$red_win_rate = max(min((float)($red_wins / $count), 1), 0);
		}
	}
	
	// Calculate first night kill rates and games count per player
	$query = new DbQuery(
		'SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id)' .
		', SUM(IF(p.kill_round = 1 AND p.kill_type = 2 AND p.role < 2, 1, 0))' .
		', SUM(p.won)' .
		', SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))' .
		', eu.nickname, eu.flags, tu.flags, cu.flags' .
			' FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN events e ON e.id = g.event_id' .
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
			' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = e.id' .
			' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = e.tournament_id' .
			' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = e.club_id' .
			' WHERE g.event_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $event_id, $condition);
    $query->add(' GROUP BY u.id');
	while ($row = $query->next())
	{
		$player = new stdClass();
		$player->id = (int)$row[0];
		$player->name = $row[1];
		$player->flags = (int)$row[2];
		$player->langs = (int)$row[3];
		$player->club_id = (int)$row[4];
		$player->club_name = $row[5];
		$player->club_flags = (int)$row[6];
		$player->games_count = (int)$row[7];
		$player->killed_first_count = (int)$row[8];
		$player->wins = (int)$row[9];
		$player->special_role_wins = (int)$row[10];
		$player->nickname = $row[11];
		$player->event_user_flags = (int)$row[12];
		$player->tournament_user_flags = (int)$row[13];
		$player->club_user_flags = (int)$row[14];

        init_player_score($player, $scoring, $lod_flags);
        $players[$player->id] = $player;
	}
	
	// echo '<pre>';
	// print_r($scoring);
	// echo '</pre><br>';
	
	// Calculate scores
	$query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.event_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $event_id, $condition);
    $query->add(' ORDER BY g.end_time');
	while ($row = $query->next())
	{
		list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time) = $row;
		$player = $players[$player_id];
		add_player_score($player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, $options);
	}
	
	// Add event extra points
	$query = new DbQuery('SELECT user_id, points FROM event_extra_points WHERE event_id = ?', $event_id);
	while ($row = $query->next())
	{
		list ($player_id, $points) = $row;
		if (isset($players[$player_id]))
		{
			if (isset($options->weight))
			{
				$points *= $options->weight;
			}
			
			$player = $players[$player_id];
			if (isset($player->extra_points))
			{
				$player->extra_points += $points;
			}
			if (isset($player->extra) && is_array($player->extra) && isset($player->extra[0]))
			{
				$player->extra[0] += $points;
			}
			$player->points += $points;
		}
	}
	
	
	// Prepare and sort scores
    if ($lod_flags & SCORING_LOD_NO_SORTING)
    {
        return $players;
    }

	$scores = array();
	foreach ($players as $user_id => $player)
	{
		// echo '<pre>';
		// print_r($player);
		// echo '</pre><br>';
		if ($player->games_count > 0)
		{
			$scores[] = $player;
		}
	}
    usort($scores, 'compare_scores');
	
	return $scores;
}

function is_same_scoring_options_group($options1, $options2)
{
	if (isset($options1->group))
	{
		if (isset($options2->group))
		{
			return $options1->group == $options2->group;
		}
		return false;
	}
	return !isset($options2->group);
	
}

function set_player_normalization($player, $max_games_played, $max_rounds_played)
{
	global $_scoring_groups;
	
	$normalization = 1;
	if (isset($player->normalizer) && $player->normalizer != NULL)
	{
		$normalizer = $player->normalizer;
		if (isset($normalizer->policies))
		{
			foreach ($normalizer->policies as $policy)
			{
				$cond = NULL;
				if (isset($policy->games))
				{
					$cond_value = $player->games_count;
					$cond = $policy->games;
				}
				else if (isset($policy->gamesPerc))
				{
					$cond_value = $max_games_played == 0 ? 0 : ($player->games_count * 100) / $max_games_played;
					// echo '......<br>';
					// echo 'cond_value: ' . $cond_value . '%<br>';
					// echo 'games_count: ' . $player->games_count . '<br>';
					// echo 'max_games_played: ' . $max_games_played . '<br>';
					// echo 'max_rounds_played: ' . $max_rounds_played . '<br>';
					$cond = $policy->gamesPerc;
				}
				else if (isset($policy->rounds))
				{
					$cond_value = $player->events_count;
					$cond = $policy->rounds;
				}
				else if (isset($policy->roundsPerc))
				{
					$cond_value = $max_rounds_played == 0 ? 0 : $player->events_count * 100 / $max_rounds_played;
					$cond = $policy->roundsPerc;
				}
				else if (isset($policy->winPerc))
				{
					$cond_value = $player->games_count == 0 ? 0 : $player->wins * 100 / $player->games_count;
					$cond = $policy->winPerc;
				}
				
				if ($cond != NULL)
				{
					if (isset($cond->min) && $cond_value < $cond->min)
					{
						continue;
					}
					
					if (isset($cond->max) && $cond_value >= $cond->max)
					{
						continue;
					}
				}
				
				$multiplier = 1;
				if (isset($policy->multiply))
				{
					if (isset($policy->multiply->val))
					{
						if (isset($policy->multiply->max) && $cond != NULL && isset($cond->min) && isset($cond->max) && $cond->min < $cond->max)
						{
							$multiplier = ($policy->multiply->val * ($cond->max - $cond_value) + $policy->multiply->max * ($cond_value - $cond->min)) / ($cond->max - $cond->min);
						}
						else
						{
							$multiplier = $policy->multiply->val;
						}
					}
					else if (isset($policy->multiply->max))
					{
						$multiplier = $policy->multiply->max;
					}
				}
				else if (isset($policy->gameAv))
				{
					$add = isset($policy->gameAv->add) ? $policy->gameAv->add : 0;
					$min = isset($policy->gameAv->min) ? $policy->gameAv->min : 0;
					$games_count = max($player->games_count + $add, $min);
					if ($games_count > 0)
					{
						$multiplier = 1 / $games_count;
					}
				}
				else if (isset($policy->roundAv))
				{
					$add = isset($policy->roundAv->add) ? $policy->roundAv->add : 0;
					$min = isset($policy->roundAv->min) ? $policy->roundAv->min : 0;
					$rounds_count = max($player->events_count + $add, $min);
					if ($rounds_count > 0)
					{
						$multiplier = 1 / $rounds_count;
					}
				}
				else if (isset($policy->byWinRate))
				{
					if ($player->games_count > 0)
					{
						$multiplier = $player->wins / $player->games_count;
					}
				}
				$normalization *= $multiplier;
			}
		}
	}
	
	$player->normalization = $normalization;
	$player->raw_points = $player->points;
	$player->points *= $normalization;
	foreach ($_scoring_groups as $group)
	{
		$g = $group . '_points';
		if (isset($player->$g))
		{
			$rg = 'raw_' . $g;
			$player->$rg = $player->$g;
			$player->$g *= $normalization;
		}
		if (isset($player->$group))
		{
			$rg = 'raw_' . $group;
			$player->$rg = $player->$group;
			foreach($player->$group as &$p) 
			{
				$p *= $normalization;
			}			
		}
	}
	if (isset($player->games))
	{
		foreach($player->games as $game) 
		{
			$game->raw_points = $game->points;
			$game->points *= $normalization;
			foreach ($_scoring_groups as $group)
			{
				if (isset($game->$group))
				{
					$rg = 'raw_' . $group;
					$game->$rg = $game->$group;
					foreach($game->$group as &$p) 
					{
						$p *= $normalization;
					}			
				}
			}
		}			
	}
	if (isset($player->history))
	{
		foreach($player->history as $hp)
		{
			$hp->raw_points = $hp->points;
			$hp->points *= $normalization;
		}
	}
}

function team_add_field($team, $player, $field_name)
{
	if (isset($player->$field_name))
	{
		if (isset($team->$field_name))
		{
			$team->$field_name += $player->$field_name;
		}
		else
		{
			$team->$field_name = $player->$field_name;
		}
	}
}

function team_max_field($team, $player, $field_name)
{
	if (isset($player->$field_name))
	{
		if (isset($team->$field_name))
		{
			$team->$field_name = max($team->$field_name, $player->$field_name);
		}
		else
		{
			$team->$field_name = $player->$field_name;
		}
	}
}

function team_set_field($team, $player, $field_name)
{
	if (isset($player->$field_name))
	{
		$team->$field_name = $player->$field_name;
	}
}

function team_add_player($team, $player)
{
	$team->players[] = $player;
	team_add_field($team, $player, 'games_count');
	team_max_field($team, $player, 'events_count');
	team_add_field($team, $player, 'killed_first_count');
	team_add_field($team, $player, 'wins');
	team_add_field($team, $player, 'special_role_wins');
	
	team_add_field($team, $player, 'points');
	team_add_field($team, $player, 'main_points');
	team_add_field($team, $player, 'extra_points');
	team_add_field($team, $player, 'legacy_points');
	team_add_field($team, $player, 'penalty_points');
	team_add_field($team, $player, 'night1_points');
	
    team_add_field($team, $player, 'raw_points');
    team_add_field($team, $player, 'raw_main_points');
    team_add_field($team, $player, 'raw_extra_points');
    team_add_field($team, $player, 'raw_legacy_points');
    team_add_field($team, $player, 'raw_penalty_points');
    team_add_field($team, $player, 'raw_night1_points');
}

function tournament_scores($tournament_id, $tournament_flags, $players_list, $lod_flags, $scoring, $normalizer, $options)
{
	if (is_null($normalizer))
	{
		$normalizer = new stdClass();
	}
	
	$event_scorings = NULL;
	if ($tournament_flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
	{
		$event_scorings = array();
	}
	$stat_flags = get_scoring_stat_flags($scoring, $options);
    
    $condition = get_players_condition($players_list);

	// Calculate first night kill rates and games count per player
	$max_games_played = 0;
	$max_rounds_played = 0;
	if (!$condition->is_empty())
	{
		$query = new DbQuery('SELECT p.user_id, COUNT(DISTINCT g.id), COUNT(DISTINCT g.event_id) FROM players p JOIN games g ON g.id = p.game_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0 GROUP BY p.user_id', $tournament_id);
		while ($row = $query->next())
		{
			list($uid, $games_played, $rounds_played) = $row;
			$max_games_played = max($max_games_played, $games_played);
			$max_rounds_played = max($max_rounds_played, $rounds_played);
		}
	}
	
    $players = array();
	$query = new DbQuery(
		'SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags,' . 
		' COUNT(g.id), COUNT(DISTINCT g.event_id),' . 
		' SUM(IF(p.kill_round = 1 AND p.kill_type = 2 AND p.role < 2, 1, 0)),' . 
		' SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0)),' . 
		' tu.flags, cu.flags' .
			' FROM players p' . 
			' JOIN games g ON g.id = p.game_id' . 
			' JOIN users u ON u.id = p.user_id' . 
			' LEFT OUTER JOIN clubs c ON c.id = u.club_id' . 
			' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = g.tournament_id' .
			' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = g.club_id' .
			' WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $condition);
	$query->add(' GROUP BY u.id');
	while ($row = $query->next())
	{
		$player = new stdClass();
		$player->id = (int)$row[0];
		$player->name = $row[1];
		$player->flags = (int)$row[2];
		$player->langs = (int)$row[3];
		$player->club_id = (int)$row[4];
		$player->club_name = $row[5];
		$player->club_flags = (int)$row[6];
		$player->games_count = (int)$row[7];
		$player->events_count = (int)$row[8];
		$player->killed_first_count = (int)$row[9];
		$player->wins = (int)$row[10];
		$player->special_role_wins = (int)$row[11];
		$player->normalizer = $normalizer;
		$player->tournament_user_flags = (int)$row[12];
		$player->club_user_flags = (int)$row[13];
		
		$max_games_played = max($max_games_played, $player->games_count);
		$max_rounds_played = max($max_rounds_played, $player->events_count);
		
		init_player_score($player, $scoring, $lod_flags);
		$players[$player->id] = $player;
	}
        
    if (is_null($event_scorings))
    {
		$red_win_rate = 0;
		if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		{
            list ($count, $red_wins) = Db::record(get_label('tournament'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id);
            if ($count > 0)
            {
                $red_win_rate = max(min((float)($red_wins / $count), 1), 0);
            }
        }
        
        // echo '<pre>';
        // print_r($scoring);
        // echo '</pre><br>';
        
        // Calculate scores
        $query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time, e.name FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $condition);
        $query->add(' ORDER BY g.end_time');
        while ($row = $query->next())
        {
            list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time, $event_name) = $row;
			$player = $players[$player_id];
            add_player_score($player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, $options, $event_name);
        }
    }
    else
    {
		// prepare scorings per event
		$query = new DbQuery('SELECT id, name, scoring_options FROM events WHERE tournament_id = ?', $tournament_id);
        while ($row = $query->next())
        {
            list($event_id, $event_name, $event_scoring_options) = $row;
			$event_scoring_options = json_decode($event_scoring_options);
			$scoring_info = new stdClass();
			$scoring_info->event_name = $event_name;
			$scoring_info->group = NULL;
			$scoring_info->options = $event_scoring_options;
			foreach ($event_scorings as $e_id => $s_info)
            {
				$group = $s_info->group;
				if (is_same_scoring_options_group($group->options, $event_scoring_options))
				{
					$group->events .= ', ' . $event_id;
					if (isset($event_scoring_options->flags))
					{
						$group->options->flags |= ~$event_scoring_options->flags;
					}
					$scoring_info->group = $group;
					break;
				}
            }
			
			if (is_null($scoring_info->group))
			{
				$group = new stdClass();
				$group->options = new stdClass();
				if (isset($event_scoring_options->flags))
				{
					$group->options->flags = ~$event_scoring_options->flags;
				}
				else
				{
					$group->options->flags = ~0;
				}
				$group->events = '' . $event_id;
				$scoring_info->group = $group;
			}
			$event_scorings[$event_id] = $scoring_info;
        }
		
        // calculate stats per scoring group
        foreach ($event_scorings as $event_id => $scoring_info)
        {
			$group = $scoring_info->group;
			if (!isset($group->stat_flags))
			{
				$group->options->flags = ~$group->options->flags;
				$group->stat_flags = get_scoring_stat_flags($scoring, $group->options);
			}
			
            if ($group->stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
            {
                list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.event_id IN(' . $group->events . ') AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0');
				$group->red_win_rate = 0;
                if ($count > 0)
                {
                    $group->red_win_rate = max(min((float)($red_wins / $count), 1), 0);
                }
            }

            // Calculate first night kill rates and games count per player
			$group->players = array();
            $query = 
				new DbQuery('SELECT p.user_id, COUNT(g.id), SUM(IF(p.kill_round = 1 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
				' FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' WHERE g.event_id IN(' . $group->events . ') AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $condition);
            $query->add(' GROUP BY p.user_id');
            while ($row = $query->next())
            {
				$player = new stdClass();
				$player->id = (int)$row[0];
				$player->games_count = (int)$row[1];
				$player->killed_first_count = (int)$row[2];
				$player->wins = (int)$row[3];
				$player->special_role_wins = (int)$row[4];
                $group->players[$player->id] = $player;
            }
        }
		
		// Calculate red win rate for non-tournament event games
		$no_event_red_win_rate = 0;
		if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		{
			list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0 AND e.tournament_id <> g.tournament_id', $tournament_id);
			if ($count > 0)
			{
				$no_event_red_win_rate = max(min((float)($red_wins / $count), 1), 0);
			}
		}
		
		// Calculate first night kill rates and games count per player for non-tournament event games
		$no_event_players = array();
		$query = 
			new DbQuery('SELECT p.user_id, COUNT(g.id), SUM(IF(p.kill_round = 1 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
			' FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN events e ON e.id = g.event_id' .
			' WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0 AND e.tournament_id != g.tournament_id', $tournament_id, $condition);
		$query->add(' GROUP BY p.user_id');
		while ($row = $query->next())
		{
			$player = new stdClass();
			list ($player->id, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
			$player->id = (int)$row[0];
			$player->games_count = (int)$row[1];
			$player->killed_first_count = (int)$row[2];
			$player->wins = (int)$row[3];
			$player->special_role_wins = (int)$row[4];
			init_player_score($player, $scoring, $lod_flags);
			$no_event_players[$player->id] = $player;
		}
		
		//print_json($event_scorings);
		
		// Calculate scores
		$query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time, g.event_id FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $condition);
		$query->add(' ORDER BY g.end_time');
		while ($row = $query->next())
		{
			list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time, $event_id) = $row;
			if (isset($event_scorings[$event_id]))
			{
				$s = $event_scorings[$event_id];
				$g = $s->group;
				$player = $g->players[$player_id];
				$op = $s->options;
				if (isset($g->red_win_rate))
				{
					$red_win_rate = $g->red_win_rate;
				}
				else
				{
					$red_win_rate = 0;
				}
				$event_name = $s->event_name;
			}
			else
			{
				$player = $no_event_players[$player_id];
				$op = $options;
				$red_win_rate = $no_event_red_win_rate;
				$event_name = NULL;
			}
			add_player_score($players[$player_id], $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, $op, $event_name);
		}
    }
	
	// Add event extra points
	$query = new DbQuery('SELECT p.event_id, p.user_id, p.points FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE e.tournament_id = ?', $tournament_id);
	while ($row = $query->next())
	{
		list ($event_id, $player_id, $points) = $row;
		if (isset($players[$player_id]))
		{
			if (isset($event_scorings[$event_id]))
			{
				$op = $event_scorings[$event_id]->options;
				if (isset($op->weight))
				{
					$points *= $op->weight;
				}
			}
			$player = $players[$player_id];
			if (isset($player->extra_points))
			{
				$player->extra_points += $points;
			}
			if (isset($player->extra) && is_array($player->extra) && isset($player->extra[0]))
			{
				$player->extra[0] += $points;
			}
			$player->points += $points;
		}
	}
	
	// Normalize scores
	foreach ($players as $user_id => $player)
	{
		set_player_normalization($player, $max_games_played, $max_rounds_played);
	}
	
	if (($tournament_flags & TOURNAMENT_FLAG_TEAM) != 0 && ($lod_flags & SCORING_LOD_TEAMS) != 0)
	{
		// Prepare teams
		$scores = array();
		$query = new DbQuery('SELECT u.user_id, u.team_id, t.name FROM tournament_users u JOIN tournament_teams t ON t.id = u.team_id WHERE u.tournament_id = ?', $tournament_id);
		while ($row = $query->next())
		{
			list($user_id, $team_id, $team_name) = $row;
			if (!isset($players[$user_id]))
			{
				continue;
			}
			
			if (isset($scores[$team_id]))
			{
				$team = $scores[$team_id];
			}
			else
			{
				$scores[$team_id] = $team = new stdClass();
				$team->id = $team_id;
				$team->name = $team_name;
				$team->scoring = $players[$user_id]->scoring;
				$team->players = array();
			}
			team_add_player($team, $players[$user_id]);
		}
		
		// Sort scores
		if ($lod_flags & SCORING_LOD_NO_SORTING)
		{
			return $scores;
		}
		
		foreach ($scores as $team)
		{
			usort($team->players, 'compare_scores');
		}
	}
    else
	{
		// Prepare and sort scores
		if ($lod_flags & SCORING_LOD_NO_SORTING)
		{
			return $players;
		}
		
		$scores = array();
		foreach ($players as $user_id => $player)
		{
			if ($player->games_count > 0)
			{
				$scores[] = $player;
			}
		}
    }
	usort($scores, 'compare_scores');
    return $scores;
}
    
function get_scoring_roles_label($role_flags)
{
	switch ($role_flags & SCORING_ROLE_FLAGS_ALL)
	{
		case SCORING_ROLE_FLAGS_CIV:
			return get_label('Civilians');
		case SCORING_ROLE_FLAGS_SHERIFF:
			return get_label('Sheriff');
		case SCORING_ROLE_FLAGS_RED:
			return get_label('Red players');
		case SCORING_ROLE_FLAGS_MAF:
			return get_label('Mafs');
		case SCORING_ROLE_FLAGS_CIV_MAF:
			return get_label('Mafs and civilians');
		case SCORING_ROLE_FLAGS_SHERIFF_MAF:
			return get_label('Mafs and sheriff');
		case SCORING_ROLE_FLAGS_EXCEPT_DON:
			return get_label('All players except don');
		case SCORING_ROLE_FLAGS_DON:
			return get_label('Don');
		case SCORING_ROLE_FLAGS_CIV_DON:
			return get_label('Civilians and don');
		case SCORING_ROLE_FLAGS_SHERIFF_DON:
			return get_label('Sheriff and don');
		case SCORING_ROLE_FLAGS_EXCEPT_MAF:
			return get_label('All players except ordinary mafs');
		case SCORING_ROLE_FLAGS_BLACK:
			return get_label('Black players');
		case SCORING_ROLE_FLAGS_EXCEPT_SHERIFF:
			return get_label('All players except sheriff');
		case SCORING_ROLE_FLAGS_EXCEPT_CIV:
			return get_label('All players except ordinary civilians');
		case SCORING_ROLE_FLAGS_ALL:
			return get_label('All players');
	}
	return get_label('No players');
}

function get_scoring_matter_label($policy, $include_roles = false)
{
	$matter = 0;
	if (isset($policy->matter))
	{
		$matter = $policy->matter;
	}
	
	$label = '';
	$delim = NULL;
	while ($matter)
	{
		$new_matter = ($matter - 1) & $matter;
		$l = '?';
		switch ($new_matter ^ $matter)
		{
			case SCORING_FLAG_PLAY:
				$l = get_label('playing the game');
				break;
			case SCORING_FLAG_WIN:
				$l = get_label('winning');
				break;
			case SCORING_FLAG_LOSE:
				$l = get_label('loosing');
				break;
			case SCORING_FLAG_CLEAR_WIN:
				$l = get_label('clear winning (all day-kills were from the opposite team)');
				break;
			case SCORING_FLAG_CLEAR_LOSE:
				$l = get_label('clear loosing (all day-kills were from the player\'s team)');
				break;
			case SCORING_FLAG_BEST_PLAYER:
				$l = get_label('being the best player');
				break;
			case SCORING_FLAG_BEST_MOVE:
				$l = get_label('the best move');
				break;
			case SCORING_FLAG_SURVIVE:
				$l = get_label('surviving the game');
				break;
			case SCORING_FLAG_KILLED_FIRST_NIGHT:
				$l = get_label('being killed the first night');
				break;
			case SCORING_FLAG_KILLED_NIGHT:
				$l = get_label('being killed in the night');
				break;
			case SCORING_FLAG_FIRST_LEGACY_3:
				$l = get_label('guessing [0] mafia (after being killed the first night)', 3);
				break;
			case SCORING_FLAG_FIRST_LEGACY_2:
				$l = get_label('guessing [0] mafia (after being killed the first night)', 2);
				break;
			case SCORING_FLAG_WARNINGS_4:
				$l = get_label('getting 4 warnigs');
				break;
			case SCORING_FLAG_KICK_OUT:
				$l = get_label('beign kicked out from the game');
				break;
			case SCORING_FLAG_SURRENDERED:
				$l = get_label('surrender (leaving the game by accepting the loss)');
				break;
			case SCORING_FLAG_ALL_VOTES_VS_MAF:
				$l = get_label('voting against mafia only (should participate in at least 3 votings)');
				break;
			case SCORING_FLAG_ALL_VOTES_VS_CIV:
				$l = get_label('voting against civilians only (should participate in at least 3 votings)');
				break;
			case SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING:
				$l = get_label('sheriff being killed the next day after don found them');
				break;
			case SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT:
				$l = get_label('sheriff being found by don the first night');
				break;
			case SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT:
				$l = get_label('sheriff being killed the first night');
				break;
			case SCORING_FLAG_BLACK_CHECKS:
				$l = get_label('the first three checks of the sheriff being black');
				break;
			case SCORING_FLAG_RED_CHECKS:
				$l = get_label('the first three checks of the sheriff being red');
				break;
			case SCORING_FLAG_EXTRA_POINTS:
				$l = get_label('actions in the game rated by the referee');
				break;
			case SCORING_FLAG_FIRST_LEGACY_1:
				$l = get_label('guessing [0] mafia (after being killed the first night)', 1);
				break;
		}
		if ($delim == NULL)
		{
			$delim = get_label(' and ');
			$label .= get_label('for ');
		}
		else
		{
			$label .= $delim;
		}
		$label .= $l;
		$matter = $new_matter;
	}
	
	if ($include_roles && isset($policy->roles) && ($policy->roles & SCORING_ROLE_FLAGS_ALL) != SCORING_ROLE_FLAGS_ALL)
	{
		$label .= ' ';
		switch ($policy->roles)
		{
		case SCORING_ROLE_FLAGS_CIV:
			$label .= get_label('as a civilian');
			break;
		case SCORING_ROLE_FLAGS_SHERIFF:
			$label .= get_label('as the sheriff');
			break;
		case SCORING_ROLE_FLAGS_RED:
			$label .= get_label('as a red player');
			break;
		case SCORING_ROLE_FLAGS_MAF:
			$label .= get_label('as an ordinary mafia player');
			break;
		case SCORING_ROLE_FLAGS_CIV_MAF:
			$label .= get_label('as the don');
			break;
		case SCORING_ROLE_FLAGS_SHERIFF_MAF:
			$label .= get_label('as an ordinary mafia player or the sheriff');
			break;
		case SCORING_ROLE_FLAGS_EXCEPT_DON:
			$label .= get_label('as any player except the don');
			break;
		case SCORING_ROLE_FLAGS_DON:
			$label .= get_label('as the don');
			break;
		case SCORING_ROLE_FLAGS_CIV_DON:
			$label .= get_label('as an ordinary civilian or the don');
			break;
		case SCORING_ROLE_FLAGS_SHERIFF_DON:
			$label .= get_label('as the sheriff or the don');
			break;
		case SCORING_ROLE_FLAGS_EXCEPT_MAF:
			$label .= get_label('as any player except an ordinary maf');
			break;
		case SCORING_ROLE_FLAGS_BLACK:
			$label .= get_label('as a black player');
			break;
		case SCORING_ROLE_FLAGS_EXCEPT_SHERIFF:
			$label .= get_label('as any player except the sheriff');
			break;
		case SCORING_ROLE_FLAGS_EXCEPT_CIV:
			$label .= get_label('as any player except an ordinary civilian');
			break;
		}
	}
	return $label;
}

function get_scoring_group_label($group)
{
	switch ($group)
	{
		case 'main':
			return get_label('Main points');
		case 'legacy':
			return get_label('Legacy points');
		case 'extra':
			return get_label('Extra points');
		case 'penalty':
			return get_label('Penalty points');
		case 'night1':
			return get_label('Points for being killed first night');
	}
	return get_label('Unknown');
}

function is_scoring_policy_on($policy, $options)
{
	if (isset($options->flags) && $options->flags != 0)
	{
		if (isset($policy->min_night1) || isset($policy->max_night1))
		{
			if (!isset($policy->min_points) || $policy->min_points == 0)
			{
				return ($options->flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0;
			}
		}
		if (isset($policy->min_difficulty) || isset($policy->max_difficulty))
		{
			if (!isset($policy->min_points) || $policy->min_points == 0)
			{
				return ($options->flags & SCORING_OPTION_NO_GAME_DIFFICULTY) == 0;
			}
		}
	}
	return true;
}

function get_scoring_group_policies_count($group, $scoring, $options = NULL)
{
	$count = 0;
	if (isset($scoring->$group))
	{
		if ($options != NULL && isset($options->flags) && $options->flags != 0)
		{
			for ($i = 0; $i < count($scoring->$group); ++$i)
			{
				$g = &$scoring->$group;
				if (is_scoring_policy_on($g[$i], $options))
				{
					++$count;
				}
			}
		}
		else
		{
			$count = count($scoring->$group);
		}
	}
	return $count;
}

function api_scoring_help($param)
{
	$param->sub_param('Help on scoring json structure is not implemented yet.', '', '-');
}

function api_normalizer_help($param)
{
	$param->sub_param('Help on normalizer json structure is not implemented yet.', '', '-');
}

?>
