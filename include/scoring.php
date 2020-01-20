<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/game_player.php';

define('SCORING_DEFAULT_ID', 18); // Default scoring system is hardcoded here to ФИИМ (FIGM)

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
define('SCORING_FLAG_PRIMA_NOCTA_3', 0x400); // 1024: Matter 10 - Guessed 3 mafia
define('SCORING_FLAG_PRIMA_NOCTA_2', 0x800); // 2048: Matter 11 - Guessed 2 mafia
define('SCORING_FLAG_WARNINGS_4', 0x1000); // 4096: Matter 12 - Killed by warnings
define('SCORING_FLAG_KICK_OUT', 0x2000); // 8192: Matter 13 - Kicked out
define('SCORING_FLAG_SURRENDERED', 0x4000); // 16384: Matter 14 - Surrendered
define('SCORING_FLAG_ALL_VOTES_VS_MAF', 0x8000); // 32768: Matter 15 - All votes vs mafia (>3 votings)
define('SCORING_FLAG_ALL_VOTES_VS_CIV', 0x10000); // 65536: Matter 16 - All votes vs civs (>3 votings)
define('SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING', 0x20000); // 131072: Matter 17 - Killed sheriff next day after finding
define('SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT', 0x40000); // 262144: Matter 18 - Sheriff was found first night
define('SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT', 0x80000); // 524288: Matter 19 - Sheriff was killed the first night
define('SCORING_FLAG_BLACK_CHECKS', 0x100000); // 1048576: Matter 20 - Sheriff did three black checks in a row
define('SCORING_FLAG_RED_CHECKS', 0x100000); // 2097152: Matter 21 - All sheriff checks are red

define('SCORING_STAT_FLAG_GAME_DIFFICULTY', 0x1);
define('SCORING_STAT_FLAG_FIRST_NIGHT_KILLING', 0x2);
define('SCORING_STAT_FLAG_FIRST_NIGHT_KILLING_FIGM', 0x4);

define('SCORING_SORTING_MAIN_POINTS', 'm');
define('SCORING_SORTING_PRIMA_NOCTA_POINTS', 'g');
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
define('SCORING_GROUP_PRIMA_NOCTA', 'prima_nocta'); // points for guessing 3 mafs by first night victim.
define('SCORING_GROUP_EXTRA', 'extra'); // extra points assigned by moderator, or earned by custom actions
define('SCORING_GROUP_PENALTY', 'penalty'); // points (most likely negative) for taking warnings and other discipline offences
define('SCORING_GROUP_NIGHT1', 'night1'); // points for being killed first night

define('SCORING_LOD_PER_GROUP', 1); // scoring returns points per group in $player->main, $player->prima_nocta, $player->extra, $player->penalty, and $player->night1 fields.
define('SCORING_LOD_PER_POLICY', 2); // scoring returns points per policy for each group in $player->main_policies, $player->prima_nocta_policies, $player->extra_policies, $player->penalty_policies, and $player->night1_policies fields.
define('SCORING_LOD_HISTORY', 4); // scoring returns player history in $player->history field. It contains an array of points with timestamp and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_PER_GAME', 8); // scoring returns scores for every game a player played in $player->games field. It contains an array of games with timestamp, game_id, and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_NO_SORTING', 16); // When set sorting returns associative array player_id => player. When not set scoring returns array of players sorted by total score.

define('SCORING_OPTION_NO_NIGHT_KILLS', 1); // Do not use policies dependent on the night kills
define('SCORING_OPTION_NO_GAME_DIFFICULTY', 2); // Do not use policies dependent on the game difficulty

$_scoring_groups = array(SCORING_GROUP_MAIN, SCORING_GROUP_PRIMA_NOCTA, SCORING_GROUP_EXTRA, SCORING_GROUP_PENALTY, SCORING_GROUP_NIGHT1);

function format_score($score)
{
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
define('SCORING_SELECT_FLAG_NO_OPTIONS', SCORING_SELECT_FLAG_NO_FLAGS_OPTION | SCORING_SELECT_FLAG_NO_WEIGHT_OPTION | SCORING_SELECT_FLAG_NO_GROUP_OPTION);

function show_scoring_select($club_id, $scoring_id, $version, $options, $options_separator, $on_change, $flags = 0, $name = NULL)
{
	if ($name == NULL)
	{
		$name = 'scoring';
	}
	
	$scorings = array();
	$scoring_name = '';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	while ($row = $query->next())
	{
		$scorings[] = $row;
		list ($sid, $sname) = $row;
		if ($sid == $scoring_id)
		{
			$scoring_name = $sname;
		}
	}
	
	if (($flags & SCORING_SELECT_FLAG_NO_PREFIX) == 0)
	{
		echo '<a href="#" onclick="mr.showScoring(\'' . $name . '\')" title="' . get_label('Show [0] scoring rules.', $scoring_name) . '">' . get_label('Scoring system') . ':</a> ';
	}
	echo '<select id="' . $name . '-sel" name="' . $name . '_id" onChange="mr.onChangeScoring(\'' . $name . '\', 0, ' . $on_change . ')" title="' . get_label('Scoring system') . '">';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	foreach ($scorings as $row)
	{
		list ($sid, $sname) = $row;
		show_option($sid, $scoring_id, $sname);
	}
	echo '</select>';
	if (($flags & SCORING_SELECT_FLAG_NO_VERSION) == 0)
	{
		echo ' ' . get_label('version') . ': <select id="' . $name . '-ver" name="' . $name . '_version" onchange="mr.onChangeScoringVersion(\'' . $name . '\', null, ' . $on_change . ')"></select><span id="' . $name . '-opt"></span>';
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
			echo $options_separator . get_label('Points weight') . ': <input id="' . $name . '-weight" value="' . $options_weight . '">';
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
	
	echo '<script>';
	echo 'function optionChanged() { mr.onChangeScoringOptions(\'' . $name . '\', ' . $on_change . '); } ';
	if (($flags & SCORING_SELECT_FLAG_NO_WEIGHT_OPTION) == 0)
	{
		echo '$("#' . $name . '-weight").spinner({ step:0.1, min:0.1, change: optionChanged, stop: optionChanged }).width(40); ';
	}
	echo 'mr.onChangeScoring("' . $name . '", ' . $version . ');';
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
			else if (isset($policy->figm_first_night_score))
			{
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING_FIGM;
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
    }
    
    if ($lod_flags & SCORING_LOD_HISTORY)
    {
        $player->history = array();
    }
    
    if ($lod_flags & SCORING_LOD_PER_GAME)
    {
        $player->games[] = array();
    }
}

function add_player_score($player, $scoring, $game_id, $game_end_time, $game_flags, $game_role, $extra_pts, $red_win_rate, $games_count, $killed_first_count, $lod_flags, $options)
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
	$extra_pts *= $weight;
	
	$total_points = $extra_pts;
	if ($lod_flags & SCORING_LOD_PER_GROUP)
	{
		foreach ($_scoring_groups as $group)
		{
            $g = $group . '_points';
			$$g = 0;
		}
		$extra_points = $extra_pts;
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
			$$group = $a;
		}
		$extra[] = $extra_pts;
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
			else if (isset($policy->figm_first_night_score))
			{
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$points = round($games_count * $policy->figm_first_night_score);
					if ($points != 0)
					{
						$points = max(min($killed_first_count * $policy->figm_first_night_score / $points, $policy->figm_first_night_score), 0);
					}
				}
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
				$$group[$i] += $points;
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
            for ($i = 0; $i < count($group); ++$i)
            {
                $player->$group[$i] += $$group[$i];
            }
        }
    }
    
    if ($lod_flags & SCORING_LOD_HISTORY)
    {
        $history_point = new stdClass();
        $history_point->game_id = $game_id;
        $history_point->time = $game_end_time;
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
        $game->game_id = $game_id;
        $game->time = $game_end_time;
        $game->points = $total_points;
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
                $game->$group = $$group;
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
		
		//'prima_nocta', 'penalty', 'night1'
		switch ($char)
		{
			case SCORING_SORTING_MAIN_POINTS:
				if (isset($player1->main))
				{
					$value1 += $player1->main * $sign;
				}
				if (isset($player2->main))
				{
					$value2 += $player2->main * $sign;
				}
				break;
			case SCORING_SORTING_PRIMA_NOCTA_POINTS:
				if (isset($player1->prima_nocta))
				{
					$value1 += $player1->prima_nocta * $sign;
				}
				if (isset($player2->prima_nocta))
				{
					$value2 += $player2->prima_nocta * $sign;
				}
				break;
			case SCORING_SORTING_EXTRA_POINTS:
				if (isset($player1->extra))
				{
					$value1 += $player1->extra * $sign;
				}
				if (isset($player2->extra))
				{
					$value2 += $player2->extra * $sign;
				}
				break;
			case SCORING_SORTING_PENALTY_POINTS:
				if (isset($player1->penalty))
				{
					$value1 += $player1->penalty * $sign;
				}
				if (isset($player2->penalty))
				{
					$value2 += $player2->penalty * $sign;
				}
				break;
			case SCORING_SORTING_NIGHT1_POINTS:
				if (isset($player1->night1))
				{
					$value1 += $player1->night1 * $sign;
				}
				if (isset($player2->night1))
				{
					$value2 += $player2->night1 * $sign;
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
		list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(id), SUM(IF(result = 1, 1, 0)) FROM games WHERE event_id = ? AND result > 0 AND canceled = 0', $event_id);
		if ($count > 0)
		{
			$red_win_rate = max(min((float)($red_wins / $count), 1), 0);
		}
	}
	
	// Calculate first night kill rates and games count per player
	$query = new DbQuery('SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0)) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.event_id = ? AND g.result > 0 AND g.canceled = 0', $event_id, $condition);
    $query->add(' GROUP BY u.id');
	while ($row = $query->next())
	{
		$player = new stdClass();
		list ($player->id, $player->name, $player->flags, $player->langs, $player->club_id, $player->club_name, $player->club_flags, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
        init_player_score($player, $scoring, $lod_flags);
        $players[$player->id] = $player;
	}
	
	// echo '<pre>';
	// print_r($scoring);
	// echo '</pre><br>';
	
	// Calculate scores
	$query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.event_id = ? AND g.result > 0 AND g.canceled = 0', $event_id, $condition);
    $query->add(' ORDER BY g.end_time');
	while ($row = $query->next())
	{
		list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time) = $row;
		$player = $players[$player_id];
		add_player_score($player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, $options);
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

function tournament_scores($tournament_id, $tournament_flags, $players_list, $lod_flags, $scoring, $options)
{
	$event_scorings = NULL;
	if ($tournament_flags & TOURNAMENT_FLAG_USE_ROUNDS_SCORING)
	{
		$event_scorings = array();
	}
	$stat_flags = get_scoring_stat_flags($scoring, $options);
    
    $condition = get_players_condition($players_list);

	// Calculate first night kill rates and games count per player
    $players = array();
	$query = new DbQuery('SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0)) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0', $tournament_id, $condition);
	$query->add(' GROUP BY u.id');
	while ($row = $query->next())
	{
		$player = new stdClass();
		list ($player->id, $player->name, $player->flags, $player->langs, $player->club_id, $player->club_name, $player->club_flags, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
		init_player_score($player, $scoring, $lod_flags);
		$players[$player->id] = $player;
	}
        
    if (is_null($event_scorings))
    {
		$red_win_rate = 0;
		if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		{
            list ($count, $red_wins) = Db::record(get_label('tournament'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0', $tournament_id);
            if ($count > 0)
            {
                $red_win_rate = max(min((float)($red_wins / $count), 1), 0);
            }
        }
        
        // echo '<pre>';
        // print_r($scoring);
        // echo '</pre><br>';
        
        // Calculate scores
        $query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0', $tournament_id, $condition);
        $query->add(' ORDER BY g.end_time');
        while ($row = $query->next())
        {
            list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time) = $row;
			$player = $players[$player_id];
            add_player_score($player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, 1, $options);
        }
    }
    else
    {
		// prepare scorings per event
		$query = new DbQuery('SELECT id, scoring_options FROM events WHERE tournament_id = ?', $tournament_id);
        while ($row = $query->next())
        {
            list($event_id, $event_scoring_options) = $row;
			$event_scoring_options = json_decode($event_scoring_options);
			$scoring_info = new stdClass();
			$scoring_info->shared = NULL;
			foreach ($event_scorings as $e_id => $s_info)
            {
				$shared = $s_info->shared;
				if (is_same_scoring_options_group($shared->options, $event_scoring_options))
				{
					$shared->events .= ', ' . $event_id;
					$scoring_info->shared = $shared;
					break;
				}
            }
			
			if (is_null($scoring_info->shared))
			{
				$shared = new stdClass();
				$shared->options = $event_scoring_options;
				$shared->stat_flags = get_scoring_stat_flags($scoring, $shared->options);
				$shared->events = '' . $event_id;
				$scoring_info->shared = $shared;
			}
			$event_scorings[$event_id] = $scoring_info;
        }
		
        // calculate stats per scoring
        foreach ($event_scorings as $event_id => $scoring_info)
        {
			$shared = $scoring_info->shared;
			
            if ($shared->stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
            {
                list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.event_id IN(' . $shared->events . ') AND g.result > 0 AND g.canceled = 0');
				$shared->red_win_rate = 0;
                if ($count > 0)
                {
                    $shared->red_win_rate = max(min((float)($red_wins / $count), 1), 0);
                }
            }

            // Calculate first night kill rates and games count per player
			$shared->players = array();
            $query = 
				new DbQuery('SELECT p.user_id, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
				' FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' WHERE g.event_id IN(' . $shared->events . ') AND g.result > 0 AND g.canceled = 0', $condition);
            $query->add(' GROUP BY p.user_id');
            while ($row = $query->next())
            {
				$player = new stdClass();
                list ($player->id, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
                $shared->players[$player->id] = $player;
            }
        }
		
		// Calculate red win rate for non-tournament event games
		$no_event_red_win_rate = 0;
		if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		{
			list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0 AND e.tournament_id <> g.tournament_id', $tournament_id);
			if ($count > 0)
			{
				$no_event_red_win_rate = max(min((float)($red_wins / $count), 1), 0);
			}
		}
		
		// Calculate first night kill rates and games count per player for non-tournament event games
		$no_event_players = array();
		$query = 
			new DbQuery('SELECT p.user_id, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
			' FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN events e ON e.id = g.event_id' .
			' WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0 AND e.tournament_id != g.tournament_id', $tournament_id, $condition);
		$query->add(' GROUP BY p.user_id');
		while ($row = $query->next())
		{
			$player = new stdClass();
			list ($player->id, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
			init_player_score($player, $scoring, $lod_flags);
			$no_event_players[$player->id] = $player;
		}
		
		// Calculate scores
		$query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time, g.event_id FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.canceled = 0', $tournament_id, $condition);
		$query->add(' ORDER BY g.end_time');
		while ($row = $query->next())
		{
			list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time, $event_id) = $row;
			if (isset($event_scorings[$event_id]))
			{
				$s = $event_scorings[$event_id];
				$sh = $s->shared;
				$player = $sh->players[$player_id];
				$op = $sh->options;
				if (isset($sh->red_win_rate))
				{
					$red_win_rate = $sh->red_win_rate;
				}
				else
				{
					$red_win_rate = 0;
				}
			}
			else
			{
				$player = $no_event_players[$player_id];
				$op = $options;
				$red_win_rate = $no_event_red_win_rate;
			}
			add_player_score($players[$player_id], $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $player->games_count, $player->killed_first_count, $lod_flags, $options);
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

function get_scoring_matter_label($matter)
{
	switch ($matter)
	{
		case SCORING_FLAG_PLAY:
			return get_label('For playing the game');
		case SCORING_FLAG_WIN:
			return get_label('For winning');
		case SCORING_FLAG_LOSE:
			return get_label('For loosing');
		case SCORING_FLAG_CLEAR_WIN:
			return get_label('For clear winning (all day-kills were from the opposite team)');
		case SCORING_FLAG_CLEAR_LOSE:
			return get_label('For clear loosing (all day-kills were from the player\'s team)');
		case SCORING_FLAG_BEST_PLAYER:
			return get_label('For being the best player');
		case SCORING_FLAG_BEST_MOVE:
			return get_label('For the best move');
		case SCORING_FLAG_SURVIVE:
			return get_label('For surviving the game');
		case SCORING_FLAG_KILLED_FIRST_NIGHT:
			return get_label('For being killed the first night');
		case SCORING_FLAG_KILLED_NIGHT:
			return get_label('For being killed in the night');
		case SCORING_FLAG_PRIMA_NOCTA_3:
			return get_label('For guessing [0] mafia (after being killed the first night)', 3);
		case SCORING_FLAG_PRIMA_NOCTA_2:
			return get_label('For guessing [0] mafia (after being killed the first night)', 2);
		case SCORING_FLAG_WARNINGS_4:
			return get_label('For getting 4 warnigs');
		case SCORING_FLAG_KICK_OUT:
			return get_label('For beign kicked out from the game');
		case SCORING_FLAG_SURRENDERED:
			return get_label('For surrender (leaving the game by accepting the loss)');
		case SCORING_FLAG_ALL_VOTES_VS_MAF:
			return get_label('For voting against mafia only (should participate in at least 3 votings)');
		case SCORING_FLAG_ALL_VOTES_VS_CIV:
			return get_label('For voting against civilians only (should participate in at least 3 votings)');
		case SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING:
			return get_label('When sheriff was killed the next day after don found him/her');
		case SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT:
			return get_label('When sheriff was found by don the first night');
		case SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT:
			return get_label('When sheriff was killed the first night');
		case SCORING_FLAG_BLACK_CHECKS:
			return get_label('When the first three checks of the sheriff where black');
		case SCORING_FLAG_RED_CHECKS:
			return get_label('When the first three checks of the sheriff where red');
	}
	return get_label('Unknown');
}

function get_scoring_group_label($group)
{
	switch ($group)
	{
		case 'main':
			return get_label('Main points');
		case 'prima_nocta':
			return get_label('Prima nocta points');
		case 'extra':
			return get_label('Extra points');
		case 'penalty':
			return get_label('Penalty points');
		case 'night1':
			return get_label('Points for being killed first night');
	}
	return get_label('Unknown');
}

function api_scoring_help($param)
{
	$param->sub_param('flags', 'Bit flag of: 1 - turn off points for being shot first night; 2 - turn off points for game difficulty.', '0 is used.');
	$param->sub_param('weight', 'Scoring weight. All scores are multiplied to this weight when scores are calculated.', '1 is used.');
}

?>
