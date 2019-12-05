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

define('SCORING_CATEGORY_MAIN', 0);
define('SCORING_CATEGORY_ADDITIONAL', 1);
define('SCORING_CATEGORY_COUNT', 2);

define('SCORING_MATTER_PLAY', 0); // Flag 0x1 / 1: Played the game
define('SCORING_MATTER_WIN', 1); // Flag 0x2 / 2: Player wins
define('SCORING_MATTER_LOOSE', 2); // Flag 0x4 / 4: Player looses
define('SCORING_MATTER_CLEAR_WIN', 3); // Flag 0x8 / 8: All players killed in a daytime were from another team
define('SCORING_MATTER_CLEAR_LOOSE', 4); // Flag 0x10 / 16: All players killed in a daytime were from player's team
define('SCORING_MATTER_BEST_PLAYER', 5); // Flag 0x20 / 32: Best player
define('SCORING_MATTER_BEST_MOVE', 6); // Flag 0x40 / 64: Best move
define('SCORING_MATTER_SURVIVE', 7); // Flag 0x80 / 128: Survived in the game
define('SCORING_MATTER_KILLED_FIRST_NIGHT', 8); // Flag 0x100 / 256: Killed in the first night
define('SCORING_MATTER_KILLED_NIGHT', 9); // Flag 0x200 / 512: Killed in the night
define('SCORING_MATTER_PRIMA_NOCTA_3', 10); // Flag 0x400 / 1024: Guessed 3 mafia
define('SCORING_MATTER_PRIMA_NOCTA_2', 11); // Flag 0x800 / 2048: Guessed 2 mafia
define('SCORING_MATTER_WARNINGS_4', 12); // Flag 0x1000 / 4096: Killed by warnings
define('SCORING_MATTER_KICK_OUT', 13); // Flag 0x2000 / 8192: Kicked out
define('SCORING_MATTER_SURRENDERED', 14); // Flag 0x4000 / 16384: Surrendered
define('SCORING_MATTER_ALL_VOTES_VS_MAF', 15); // Flag 0x8000 / 32768: All votes vs mafia (>3 votings)
define('SCORING_MATTER_ALL_VOTES_VS_CIV', 16); // Flag 0x10000 / 65536: All votes vs civs (>3 votings)
define('SCORING_MATTER_SHERIFF_KILLED_AFTER_FINDING', 17); // Flag 0x20000 / 131072: Killed sheriff next day after finding
define('SCORING_MATTER_SHERIFF_FOUND_FIRST_NIGHT', 18); // Flag 0x40000 / 262144: Sheriff was found first night
define('SCORING_MATTER_SHERIFF_KILLED_FIRST_NIGHT', 19); // Flag 0x80000 / 524288: Sheriff was killed the first night
define('SCORING_MATTER_BLACK_CHECKS', 20); // Flag 0x100000 / 1048576: All first three sheriff's checks were black
define('SCORING_MATTER_RED_CHECKS', 21); // Flag 0x200000 / 2097152: All first three sheriff's checks were red
define('SCORING_MATTER_COUNT', 22);

define('SCORING_FLAG_PLAY', 0x1); // 1: Matter 0 - Played the game
define('SCORING_FLAG_WIN', 0x2); //  2: Matter 1 - Player wins
define('SCORING_FLAG_LOOSE', 0x4); // 4: Matter 2 - Player looses
define('SCORING_FLAG_CLEAR_WIN', 0x8); // 8: Matter 3 - All players killed in a daytime were from another team
define('SCORING_FLAG_CLEAR_LOOSE', 0x10); // 16: Matter 4 - All players killed in a daytime were from player's team
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

define('SCORING_POLICY_STATIC', 0); // just add a certain number of points
define('SCORING_POLICY_GAME_DIFFICULTY', 1); // depending on town winning percentage
define('SCORING_POLICY_FIRST_NIGHT_KILLING', 2); // depending on how often the player is killed the first night
define('SCORING_POLICY_FIRST_NIGHT_KILLING_FIGM', 3); // depending on how often the player is killed the first night FIGM rules
define('SCORING_POLICY_COUNT', 4);

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

define('SCORING_GROUP_MAIN', 'main'); // points for wins/looses
define('SCORING_GROUP_PRIMA_NOCTA', 'prima_nocta'); // points for guessing 3 mafs by first night victim.
define('SCORING_GROUP_EXTRA', 'extra'); // extra points assigned by moderator, or earned by custom actions
define('SCORING_GROUP_PENALTY', 'penalty'); // points (most likely negative) for taking warnings and other discipline offences
define('SCORING_GROUP_NIGHT1', 'night1'); // points for being killed first night

define('SCORING_LOD_PER_GROUP', 1); // scoring returns points per group in $player->main, $player->prima_nocta, $player->extra, $player->penalty, and $player->night1 fields.
define('SCORING_LOD_PER_POLICY', 2); // scoring returns points per policy for each group in $player->main_policies, $player->prima_nocta_policies, $player->extra_policies, $player->penalty_policies, and $player->night1_policies fields.
define('SCORING_LOD_HISTORY', 4); // scoring returns player history in $player->history field. It contains an array of points with timestamp and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_PER_GAME', 8); // scoring returns scores for every game a player played in $player->games field. It contains an array of games with timestamp, game_id, and scores according to SCORING_LOD_PER_GROUP, and SCORING_LOD_PER_POLICY flags.
define('SCORING_LOD_NO_SORTING', 16); // When set sorting returns associative array player_id => player. When not set scoring returns array of players sorted by total score.

$_groups = array(SCORING_GROUP_MAIN, SCORING_GROUP_PRIMA_NOCTA, SCORING_GROUP_EXTRA, SCORING_GROUP_PENALTY, SCORING_GROUP_NIGHT1);

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

function show_scoring_select($club_id, $scoring_id, $on_change, $title, $name = NULL, $show_prefix = true)
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
	
	if ($show_prefix)
	{
		echo '<a href="#" onclick="mr.showScoring(' . $scoring_id . ')" title="' . get_label('Show [0] scoring rules.', $scoring_name) . '">' . get_label('Scoring system') . ':</a> ';
	}
	echo '<select name="' . $name . '" id="' . $name . '" onChange="' . $on_change . '" title="' . $title . '">';
	$query = new DbQuery('SELECT id, name FROM scorings WHERE club_id = ? OR club_id IS NULL ORDER BY name', $club_id);
	foreach ($scorings as $row)
	{
		list ($sid, $sname) = $row;
		show_option($sid, $scoring_id, $sname);
	}
	echo '</select>';
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
					return get_label('Sheriffs');
				case POINTS_MAFIA:
					return get_label('Mafiosi');
				case POINTS_DON:
					return get_label('Dons');
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
					return get_label('sheriffs');
				case POINTS_MAFIA:
					return get_label('mafiosi');
				case POINTS_DON:
					return get_label('dons');
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

function prepare_scoring($scoring, $options)
{
	global $_groups;
	// check flags and options
	$stat_flags = 0;
	foreach ($_groups as $group)
	{
		if (!isset($scoring->$group))
		{
			continue;
		}
		
		foreach ($scoring->$group as $policy)
		{
			if (isset($policy->active))
			{
				$option_name = $group . $policy->matter;
				if (isset($options->$option_name))
				{
					$policy->active = $options->$option_name;
				}
			}
			else
			{
				$policy->active = true;
			}
			
			if (!$policy->active)
			{
				continue;
			}
			
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
				$stat_flags |= SCORING_STAT_FLAG_GAME_DIFFICULTY;
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
				$stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING;
			}
			else if (isset($policy->figm_first_night_score))
			{
				$stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING_FIGM;
			}
		}
	}
	return $stat_flags;
}
    
function init_player_score($player, $scoring, $lod_flags)
{
    global $_groups;
    
    $player->points = 0;
	
    if ($lod_flags & SCORING_LOD_PER_GROUP)
    {
        foreach ($_groups as $group)
        {
            $g = $group . '_points';
            $player->$g = 0;
        }
    }
    
    if ($lod_flags & SCORING_LOD_PER_POLICY)
    {
        foreach ($_groups as $group)
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

function add_player_score($player, $scoring, $game_id, $game_end_time, $game_flags, $game_role, $extra_pts, $red_win_rate, $lod_flags, $weight)
{
	global $_groups;

	$total_points = $extra_pts;
	if ($lod_flags & SCORING_LOD_PER_GROUP)
	{
		foreach ($_groups as $group)
		{
            $g = $group . '_points';
			$$g = 0;
		}
		$extra_points = $extra_pts;
	}
	if ($lod_flags & SCORING_LOD_PER_POLICY)
	{
		foreach ($_groups as $group)
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
	foreach ($_groups as $group_name)
	{
		if (!isset($scoring->$group_name))
		{
			continue;
		}
		 
		$group = $scoring->$group_name;
		for ($i = 0; $i < count($group); ++$i)
		{
			$policy = $group[$i];
			if (!$policy->active)
			{
				continue;
			}
			
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
			else if (isset($policy->min_night1) || isset($policy->max_night1))
			{
				$rate = 0;
				if ($player->games_count > 0)
				{
					$rate = max(min($player->killed_first_count / $player->games_count, 1), 0);
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
			else if (isset($policy->figm_first_night_score))
			{
				$points = 
					max(min($player->killed_first_count * $policy->figm_first_night_score / round($player->games_count * $policy->figm_first_night_score), $policy->figm_first_night_score), 0);
			}
			
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
        foreach ($_groups as $group)
        {
            $g = $group . '_points';
            $player->$g += $$g;
        }
    }
    if ($lod_flags & SCORING_LOD_PER_POLICY)
    {
        foreach ($_groups as $group)
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
            foreach ($_groups as $group)
            {
                $g = $group . '_points';
                $history_point->$g = $player->$g;
            }
        }
        if ($lod_flags & SCORING_LOD_PER_POLICY)
        {
            foreach ($_groups as $group)
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
            foreach ($_groups as $group)
            {
                $g = $group . '_points';
                $game->$g = $$g;
            }
        }
        if ($lod_flags & SCORING_LOD_PER_POLICY)
        {
            foreach ($_groups as $group)
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

function event_scores($event_id, $players_list, $lod_flags, $scoring = NULL, $options = NULL, $weight = 0)
{
	global $_groups;
	
	if (is_null($scoring))
	{
		list($scoring, $options, $weight) = Db::record(get_label('event'), 'SELECT s.scoring, e.scoring_options, e.scoring_weight FROM events e JOIN scoring_versions s ON s.scoring_id = e.scoring_id AND s.version = e.scoring_version WHERE e.id = ?', $event_id);
		$scoring = json_decode($scoring);
	}
	else if (is_string($scoring))
	{
		$scoring = json_decode($scoring);
	}
	
	if (is_null($options))
	{
		$options = new stdClass();
	}
	else if (is_string($options))
	{
		$options = json_decode($scoring_json);
	}
	
	if ($weight <= 0)
	{
		list($weight) = Db::record(get_label('event'), 'SELECT scoring_weight FROM events WHERE id = ?', $event_id);
	}
	
	$players = array();
	$stat_flags = prepare_scoring($scoring, $options);
    
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
		$player->scoring = $scoring;
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
		add_player_score($players[$player_id], $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags, $weight);
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

function club_scores($club_id, $start_time, $end_time, $players_list, $lod_flags, $scoring_json = NULL, $options = 0)
{
    global $_groups;
    
    if ($scoring_json == NULL)
    {
        list($scoring_id, $options) = Db::record(get_label('club'), 'SELECT scoring_id, scoring_options FROM clubs WHERE id = ?', $club_id);
        list($scoring_json) = Db::record(get_label('scoring'), 'SELECT scoring FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $scoring_id);
    }
    $scoring = json_decode($scoring_json);
    $players = array();
    
    $stat_flags = prepare_scoring($scoring, $options);
    
    // prepare additional filter
    $condition = get_players_condition($players_list);
    $time_condition = new SQL();
    if ($start_time > 0)
    {
        $time_condition->add(' AND g.end_time >= ?', $startTime);
    }
    if ($end_time > 0)
    {
        $time_condition->add(' AND g.end_time < ?', $endTime);
    }
    $condition->add($time_condition);
    
    // Calculate game difficulty rates
    $red_win_rate = 0;
    if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
    {
        list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.club_id = ? AND g.result > 0 AND g.canceled = 0', $club_id, $time_condition);
        if ($count > 0)
        {
            $red_win_rate = max(min((float)($red_wins / $count), 1), 0);
        }
    }
    
    // Calculate first night kill rates and games count per player
    $query = new DbQuery('SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2 AND p.role < 2, 1, 0)), SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0)) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.club_id = ? AND g.result > 0 AND g.canceled = 0', $club_id, $condition);
    $query->add(' GROUP BY u.id');
    while ($row = $query->next())
    {
        $player = new stdClass();
        list ($player->id, $player->name, $player->flags, $player->langs, $player->club_id, $player->club_name, $player->club_flags, $player->games_count, $player->killed_first_count, $player->wins, $player->special_role_wins) = $row;
        $player->scoring = $scoring;
        init_player_score($player, $scoring, $lod_flags);
        $players[$player->id] = $player;
    }
    
    // echo '<pre>';
    // print_r($scoring);
    // echo '</pre><br>';
    
    // Calculate scores
    $query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.club_id = ? AND g.result > 0 AND g.canceled = 0', $club_id, $condition);
    $query->add(' ORDER BY g.end_time');
    while ($row = $query->next())
    {
        list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time) = $row;
        add_player_score($players[$player_id], $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags);
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

function tournament_scores($tournament_id, $players_list, $lod_flags, $scoring_json = NULL, $options = 0)
{
    global $_groups;
    
    if ($scoring_json == NULL)
    {
        list($options, $scoring_json) = Db::record(get_label('club'), 'SELECT c.scoring_options, s.scoring FROM events e JOIN scoring_versions s ON s.scoring_id = e.scoring_id AND s.version = e.scoring_version WHERE e.id = ?', $event_id);
    }
    $scoring = json_decode($scoring_json);
    $players = array();
    
    $stat_flags = prepare_scoring($scoring, $options);
    
    // prepare additional filter
    $condition = get_players_condition($players_list);
    $time_condition = new SQL();
    if ($start_time > 0)
    {
        $time_condition->add(' AND g.end_time >= ?', $startTime);
    }
    if ($end_time > 0)
    {
        $time_condition->add(' AND g.end_time < ?', $endTime);
    }
    $condition->add($time_condition);
    
    // Calculate game difficulty rates
    $red_win_rate = 0;
    if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
    {
        list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE g.event_id = ? AND g.result > 0 AND g.canceled = 0', $event_id, $time_condition);
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
        $player->scoring = $scoring;
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
        add_player_score($players[$player_id], $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags);
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
    
// class Scores
// {
	// private $players;
	// private $events;
	// private $systems;
	
	// function __construct()
	// {
		// $this->players = array();
		// $this->events = array();
		// $this->systems = array();
	// }
	
	// private function is_group_on($group, $competition, $options)
	// {
		// if (isset($group->comp))
		// {
			// $comp_flags = $group->comp >> ($competition * 2);
			// if (($comp_flags & 2) != 0 && $options != NULL && isset($options->$group->name))
			// {
				// $comp_flags = $options->$group->name >> ($competition * 2);
			// }
			// return ($comp_flags & 1) != 0;
		// }
		// return true;
	// }
	
	// static private function get_event($event_id)
	// {
		// if (isset($this->events[$event_id]))
		// {
			// return $this->events[$event_id];
		// }
		
		// $event = new stdClass();
		// list($scoring_id, $options) = Db::record(get_label('event'), 'SELECT s.scoring, e.scoring_options FROM events e JOIN scoring_versions s ON s.id = e.scoring_version_id WHERE e.id = ?', $event_id);
		// $event->options = json_decode($options);
		
		// if (!isset($this->systems[$scoring_id]))
		// {
			// list($system) = Db::record(get_label('scoring system'), 'SELECT scoring FROM scoring_versions WHERE id = ?', $scoring_system);
			// $this->systems[$scoring_id] = json_decode($system);
		// }
		// $event->system = $this->systems[$scoring_id];
	// }
	
	// function add_event($event_id)
	// {
		// // Make syre scoring_system and scoring_options are valid
		// if (is_null($scoring_system))
		// {
			// list($scoring_system, $options) = Db::record(get_label('event'), 'SELECT s.scoring, e.scoring_options FROM events e JOIN scoring_versions s ON s.id = e.scoring_version_id WHERE e.id = ?', $event_id);
			// if (is_null($scoring_options))
			// {
				// $scoring_options = $options;
			// }
		// }
		// else if (is_null($scoring_options))
		// {
			// list($options) = Db::record(get_label('event'), 'SELECT scoring_options FROM events WHERE id = ?', $event_id);
		// }
		
		// $scoring_system = Scores::get_scoring_system($scoring_system);
		// $scoring_options = Scores::get_scoring_options($scoring_options);
		
		// // Are there any policies dependent on stats
		// $stat_flags = 0;
		// foreach ($scoring->groups as $group)
		// {
			// if (!$this->is_group_on($group, $competition, $options))
			// {
				// continue;
			// }
			
			// foreach ($group->policies as $policy)
			// {
				// if (isset($policy->min_difficulty) || isset($policy->max_difficulty))
				// {
					// $stat_flags |= SCORING_STAT_FLAG_GAME_DIFFICULTY;
				// }
				
				// if (isset($policy->min_night1) || isset($policy->max_night1))
				// {
					// $stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING;
				// }
			// }
		// }
		
		// // Calculate game difficulty rates
		// if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		// {
			// list ($count, $black_wins) = Db::record(get_label('event'), 'SELECT count(id), SUM(IF(result = 2, 1, 0)) FROM games WHERE result > 0 AND event_id = ?', $event_id);
			// if ($count > 0)
			// {
				// $scoring_options->red_wining_rate = (float)($black_wins / $count);
			// }
		// }
		
		// // Calculate first night killed rates
	// }
// }

// class ScoringRule
// {
	// public $category;
	// public $matter;
	// public $role_flags;
	// public $policy;
	// public $min_dependency;
	// public $min_points;
	// public $max_dependency;
	// public $max_points;
	
	// function __construct($row = NULL)
	// {
		// if ($row != NULL)
		// {
			// list ($this->category, $this->matter, $this->policy, $this->role_flags, $this->min_dependency, $this->min_points, $this->max_dependency, $this->max_points) = $row;
			// $this->category = (int)$this->category;
			// $this->matter = (int)$this->matter;
			// $this->policy = (int)$this->policy;
			// $this->role_flags = (int)$this->role_flags;
			// $this->min_dependency = (float)$this->min_dependency;
			// $this->min_points = (float)$this->min_points;
			// if ($this->policy == SCORING_POLICY_STATIC)
			// {
				// $this->max_dependency = $this->min_dependency;
				// $this->max_points = $this->min_points;
			// }
			// else
			// {
				// $this->max_dependency = (float)$this->max_dependency;
				// $this->max_points = (float)$this->max_points;
			// }
		// }
		// else
		// {
			// $this->matter = SCORING_MATTER_WIN;
			// $this->role_flags = SCORING_ROLE_FLAGS_ALL;
			// $this->policy = SCORING_POLICY_STATIC;
			// $this->min_dependency = 0.0;
			// $this->min_points = 1.0;
			// $this->max_dependency = 0.0;
			// $this->max_points = 1.0;
		// }
	// }
	
	// function merge($rule)
	// {
		// if ($this->matter != $rule->matter || $this->category != $rule->category)
		// {
			// return;
		// }
		
		// if (
			// $this->policy == $rule->policy &&
			// $this->min_dependency == $rule->min_dependency &&
			// $this->min_points == $rule->min_points &&
			// $this->max_dependency == $rule->max_dependency &&
			// $this->max_points == $rule->max_points )
		// {
			// $this->role_flags |= $rule->role_flags;
			// $rule->role_flags = 0;
			// return;
		// }
		
		// if ($this->role_flags == $rule->role_flags)
		// {
			// if ($rule->policy == SCORING_POLICY_STATIC)
			// {
				// $this->min_points += $rule->min_points;
				// $this->max_points += $rule->max_points;
				// $rule->role_flags = 0;
				// return;
			// }
			
			// if ($this->policy == SCORING_POLICY_STATIC)
			// {
				// $this->policy = $rule->policy;
				// $this->min_dependency = $rule->min_dependency;
				// $this->max_dependency = $rule->max_dependency;
				// $this->min_points += $rule->min_points;
				// $this->max_points += $rule->max_points;
				// $rule->role_flags = 0;
				// return;
			// }
		// }
	// }
	
	// function calculate_points($dependency)
	// {
		// if ($dependency <= $this->min_dependency)
		// {
			// return $this->min_points;
		// }
		
		// if ($dependency >= $this->max_dependency)
		// {
			// return $this->max_points;
		// }

		// $result = ($this->max_points - $this->min_points) * $dependency;
		// $result += $this->min_points * $this->max_dependency - $this->max_points * $this->min_dependency;
		// $result /= $this->max_dependency - $this->min_dependency;
		// return $result;
	// }
	
	// static function roles_label($role_flags)
	// {
		// switch ($role_flags & SCORING_ROLE_FLAGS_ALL)
		// {
			// case SCORING_ROLE_FLAGS_CIV:
				// return get_label('Civilians');
			// case SCORING_ROLE_FLAGS_SHERIFF:
				// return get_label('Sheriffs');
			// case SCORING_ROLE_FLAGS_RED:
				// return get_label('Red players');
			// case SCORING_ROLE_FLAGS_MAF:
				// return get_label('Mafs');
			// case SCORING_ROLE_FLAGS_CIV_MAF:
				// return get_label('Mafs and civilians');
			// case SCORING_ROLE_FLAGS_SHERIFF_MAF:
				// return get_label('Mafs and sheriffs');
			// case SCORING_ROLE_FLAGS_EXCEPT_DON:
				// return get_label('All players except dons');
			// case SCORING_ROLE_FLAGS_DON:
				// return get_label('Dons');
			// case SCORING_ROLE_FLAGS_CIV_DON:
				// return get_label('Cvivilians and dons');
			// case SCORING_ROLE_FLAGS_SHERIFF_DON:
				// return get_label('Sheriffs and dons');
			// case SCORING_ROLE_FLAGS_EXCEPT_MAF:
				// return get_label('All players except ordinary mafs');
			// case SCORING_ROLE_FLAGS_BLACK:
				// return get_label('Black players');
			// case SCORING_ROLE_FLAGS_EXCEPT_SHERIFF:
				// return get_label('All players except sheriffs');
			// case SCORING_ROLE_FLAGS_EXCEPT_CIV:
				// return get_label('All players except ordinary civilians');
			// case SCORING_ROLE_FLAGS_ALL:
				// return get_label('All players');
		// }
		// return get_label('No players');
	// }
	
	// function get_roles_label()
	// {
		// return ScoringRule::roles_label($this->role_flags);
	// }
	
	// static function matter_label($matter)
	// {
		// switch ($matter)
		// {
			// case SCORING_MATTER_PLAY:
				// return get_label('For playing the game');
			// case SCORING_MATTER_WIN:
				// return get_label('For winning');
			// case SCORING_MATTER_LOOSE:
				// return get_label('For loosing');
			// case SCORING_MATTER_CLEAR_WIN:
				// return get_label('For clear winning (all day-kills were from the opposite team)');
			// case SCORING_MATTER_CLEAR_LOOSE:
				// return get_label('For clear loosing (all day-kills were from the player\'s team)');
			// case SCORING_MATTER_BEST_PLAYER:
				// return get_label('For being the best player');
			// case SCORING_MATTER_BEST_MOVE:
				// return get_label('For the best move');
			// case SCORING_MATTER_SURVIVE:
				// return get_label('For surviving the game');
			// case SCORING_MATTER_KILLED_FIRST_NIGHT:
				// return get_label('For being killed the first night');
			// case SCORING_MATTER_KILLED_NIGHT:
				// return get_label('For being killed in the night');
			// case SCORING_MATTER_PRIMA_NOCTA_3:
				// return get_label('For guessing [0] mafia (after being killed the first night)', 3);
			// case SCORING_MATTER_PRIMA_NOCTA_2:
				// return get_label('For guessing [0] mafia (after being killed the first night)', 2);
			// case SCORING_MATTER_WARNINGS_4:
				// return get_label('For getting 4 warnigs');
			// case SCORING_MATTER_KICK_OUT:
				// return get_label('For beign kicked out from the game');
			// case SCORING_MATTER_SURRENDERED:
				// return get_label('For surrender (leaving the game by accepting the loss)');
			// case SCORING_MATTER_ALL_VOTES_VS_MAF:
				// return get_label('For voting against mafia only (should participate in at least 3 votings)');
			// case SCORING_MATTER_ALL_VOTES_VS_CIV:
				// return get_label('For voting against civilians only (should participate in at least 3 votings)');
			// case SCORING_MATTER_SHERIFF_KILLED_AFTER_FINDING:
				// return get_label('When sheriff was killed the next day after don found him/her');
			// case SCORING_MATTER_SHERIFF_FOUND_FIRST_NIGHT:
				// return get_label('When sheriff was found by don the first night');
			// case SCORING_MATTER_SHERIFF_KILLED_FIRST_NIGHT:
				// return get_label('When sheriff was killed the first night');
			// case SCORING_MATTER_BLACK_CHECKS:
				// return get_label('When the first three checks of the sheriff where black');
			// case SCORING_MATTER_RED_CHECKS:
				// return get_label('When the first three checks of the sheriff where red');
		// }
		// return get_label('Unknown');
	// }
	
	// function get_matter_label()
	// {
		// return ScoringRule::matter_label($this->matter);
	// }
	
	// static function policy_label($policy)
	// {
		// switch ($policy & SCORING_ROLE_FLAGS_ALL)
		// {
			// case SCORING_POLICY_STATIC:
				// return get_label('Static points');
			// case SCORING_POLICY_GAME_DIFFICULTY:
				// return get_label('Points depending on game difficulty (i.e. who wins more often civs or mafia)');
			// case SCORING_POLICY_FIRST_NIGHT_KILLING:
				// return get_label('Points depending on how often the player was killed the first night');
		// }
		// return get_label('Unknown');
	// }
	
	// function get_policy_label()
	// {
		// return ScoringRule::policy_label($this->policy);
	// }
	
	// static function category_label($category)
	// {
		// switch ($category & SCORING_ROLE_FLAGS_ALL)
		// {
			// case SCORING_CATEGORY_MAIN:
				// return get_label('Main points');
			// case SCORING_CATEGORY_ADDITIONAL:
				// return get_label('Additional points');
		// }
		// return get_label('Unknown');
	// }
	
	// function get_category_label()
	// {
		// return ScoringRule::category_label($this->category);
	// }
// }

// class ScoringSystem
// {
    // public $id;
	// public $club_id;
    // public $name;
	// public $rules;
	// public $stat_flags;
	// public $sorting;
	
	// function __construct($id, $club_id = -1)
	// {
		// $this->rules = array();
		// $this->stat_flags = 0;
		// if ($id <= 0)
		// {
			// $this->id = -1;
			// $this->club_id = $club_id;
			// $this->name = '';
			// $this->sorting = SCORING_DEFAULT_SORTING;
		// }
		// else
		// {
			// list ($this->id, $this->club_id, $this->name, $this->sorting) =
				// Db::record(get_label('scoring system'), 'SELECT id, club_id, name, sorting FROM scorings WHERE id = ?', $id);
			// $query = new DbQuery('SELECT category, matter, policy, roles, min_dependency, min_points, max_dependency, max_points FROM scoring_rules WHERE scoring_id = ? ORDER BY category, matter', $id);
			// while ($row = $query->next())
			// {
				// $rule = new ScoringRule($row);
				// switch ($rule->policy)
				// {
					// case SCORING_POLICY_GAME_DIFFICULTY:
						// $this->stat_flags |= SCORING_STAT_FLAG_GAME_DIFFICULTY;
						// break;
					// case SCORING_POLICY_FIRST_NIGHT_KILLING:
						// $this->stat_flags |= SCORING_STAT_FLAG_FIRST_NIGHT_KILLING;
						// break;
				// }
				
				// for ($i = count($this->rules) - 1; $i >= 0; --$i)
				// {
					// $rule1 = $this->rules[$i];
					// if ($rule1->matter != $rule->matter)
					// {
						// break;
					// }
					// $rule1->merge($rule);
				// }
				
				// if ($rule->role_flags != 0)
				// {
					// $this->rules[] = $rule;
				// }
			// }
		// }
	// }
	
	// static function get_sorting_item_label($item, $desc = false)
	// {
		// if ($desc)
		// {
			// $desc = get_label('less');
		// }
		// else
		// {
			// $desc = get_label('more');
		// }
		
		// switch ($item)
		// {
			// case SCORING_SORTING_EXTRA_POINTS:
				// return get_label('The one who has [0] additional points', $desc);
			// case SCORING_SORTING_MAIN_POINTS:
				// return get_label('The one who has [0] main points', $desc);
			// case SCORING_SORTING_WIN:
				// return get_label('The one who has [0] victories', $desc);
			// case SCORING_SORTING_LOOSE:
				// return get_label('The one who has [0] defeats', $desc);
			// case SCORING_SORTING_CLEAR_WIN:
				// return get_label('The one who has [0] clear victories', $desc);
			// case SCORING_SORTING_CLEAR_LOOSE:
				// return get_label('The one who has [0] clear defeats', $desc);
			// case SCORING_SORTING_SPECIAL_ROLE_WIN:
				// return get_label('The one who has [0] special role victories', $desc);
			// case SCORING_SORTING_BEST_PLAYER:
				// return get_label('The one who has [0] best player titles', $desc);
			// case SCORING_SORTING_BEST_MOVE:
				// return get_label('The one who has [0] best move titles', $desc);
			// case SCORING_SORTING_SURVIVE:
				// return get_label('The one who has [0] games survived', $desc);
			// case SCORING_SORTING_KILLED_FIRST_NIGHT:
				// return get_label('The one who has [0] first night deaths', $desc);
			// case SCORING_SORTING_KILLED_NIGHT:
				// return get_label('The one who has [0] night deaths', $desc);
			// case SCORING_SORTING_PRIMA_NOCTA_3:
				// return get_label('The one who has [0] 3 mafia guesses', $desc);
			// case SCORING_SORTING_PRIMA_NOCTA_2:
				// return get_label('The one who has [0] 2 mafia guesses', $desc);
			// case SCORING_SORTING_WARNINGS_4:
				// return get_label('The one who has [0] 4 warinings', $desc);
			// case SCORING_SORTING_KICK_OUT:
				// return get_label('The one who has [0] kick outs', $desc);
			// case SCORING_SORTING_SURRENDERED:
				// return get_label('The one who has [0] surrenders', $desc);
		// }
		// return get_label('Unknown');
	// }
	
	// function show_rules($edit = false)
	// {
		// global $_profile;
		// if ($edit)
		// {
			// if ($_profile == NULL)
			// {
				// $edit = false;
			// }
			// else if ($this->club_id == NULL)
			// {
				// $edit = $_profile->is_admin();
			// }
			// else if (!$_profile->is_club_manager($this->club_id))
			// {
				// $edit = $_profile->is_club_manager($this->club_id);
			// }
		// }
		
		// echo '<table class="bordered light" width="100%">';
		
		// $last_matter = -1;
		// $current_category = -1;
		// foreach ($this->rules as $rule)
		// {
			// if ($rule->category != $current_category)
			// {
				// $current_category = $rule->category;
				// $last_matter = -1;
				// if ($current_category >= 0)
				// {
					// echo '</td></tr>';
				// }
				// echo '<tr class="darker">';
				// if ($edit)
				// {
					// echo '<td width="32" align="center">';
					// echo '<a href="#" onclick="mr.createScoringRule(' . $this->id . ', ' . $current_category . ')" title="' . get_label('New scoring rule') . '">';
					// echo '<img src="images/create.png" border="0"></a>';
					// echo '</td>';
				// }
				// echo '<td colspan="2"><h4>' . $rule->get_category_label() . '</h4></td></tr>';
			// }
			
			// if ($rule->matter != $last_matter)
			// {
				// $last_matter = $rule->matter;
				// echo '</td></tr><tr>';
				// if ($edit)
				// {
					// echo '<td class="dark" align="center"><a href="#" onclick="mr.deleteScoringRule(' . $this->id . ', ' . $rule->category . ', ' . $rule->matter . ', \'' . get_label('Are you sure you want to delete [0]?', get_label('the rule')) . '\')" title="' . get_label('Delete [0]', get_label('the rule')) . '"><img src="images/delete.png" border="0"></a></td>';
				// }
				// echo '<td width="300"><p>';
				// echo $rule->get_matter_label();
				// echo '</td><td>';
			// }
			
			// $min_d = $rule->min_dependency * 100;
			// $max_d = $rule->max_dependency * 100;
			// switch ($rule->policy)
			// {
				// case SCORING_POLICY_STATIC:
					// if ($rule->min_points == 1)
					// {
						// echo get_label('[0] get 1 point.', $rule->get_roles_label());
					// }
					// else
					// {
						// echo get_label('[0] get [1] points.', $rule->get_roles_label(), $rule->min_points);
					// }
					// break;
				// case SCORING_POLICY_GAME_DIFFICULTY:
					// if ($rule->min_dependency <= 0)
					// {
						// $lower_text = get_label('when the game difficulty is 0%');
					// }
					// else
					// {
						// $lower_text = get_label('when the game difficulty is lower than [0]%', $min_d);
					// }
					
					// if ($rule->max_dependency >= 1)
					// {
						// $higher_text = get_label('when the game difficulty is 100%');
					// }
					// else
					// {
						// $higher_text = get_label('when the game difficulty is higher than [0]%', $max_d);
					// }
					
					// echo get_label('[0] get from [1] ([3]) to [2] ([4]) points.', 
						// $rule->get_roles_label(),
						// $rule->min_points,
						// $rule->max_points,
						// $lower_text,
						// $higher_text);
					// break;
					
				// case SCORING_POLICY_FIRST_NIGHT_KILLING:
					// if ($rule->min_dependency <= 0)
					// {
						// $lower_text = get_label('when player\'s first-night-killed rate is 0%');
					// }
					// else
					// {
						// $lower_text = get_label('when player\'s first-night-killed rate is lower than [0]%', $min_d);
					// }
					
					// if ($rule->max_dependency >= 1)
					// {
						// $higher_text = get_label('when first-night-killed rate is 100%');
					// }
					// else
					// {
						// $higher_text = get_label('when first-night-killed rate is higher than [0]%', $max_d);
					// }
					
					// echo get_label('[0] get from [1] ([3]) to [2] ([4]) points.', 
						// $rule->get_roles_label(),
						// $rule->min_points,
						// $rule->max_points,
						// $lower_text,
						// $higher_text);
					// break;
			// }
			// echo '<br>';
			
		// }
		// if ($edit && $current_category < 0)
		// {
			// echo '<tr class="darker">';
			// echo '<td width="32" align="center">';
			// echo '<a href="#" onclick="mr.createScoringRule(' . $this->id . ', ' . SCORING_CATEGORY_MAIN . ')" title="' . get_label('New scoring rule') . '">';
			// echo '<img src="images/create.png" border="0"></a>';
			// echo '</td>';
			// echo '<td colspan="2"><h4>' . get_label('Scoring rules') . '</h4></td></tr>';
		// }
		// echo '</td></tr>';
		
		// echo '<tr class="darker">';
		// if ($edit)
		// {
			// echo '<td align="center"><a href="#" onclick="mr.editScoringSorting(' . $this->id . ')" title="' . get_label('Edit sorting') . '">';
			// echo '<img src="images/edit.png" border="0"></a></td>';
		// }
		// echo '<td colspan="2"><h4>' . get_label('When the points are equal the next player wins:') . '</h4></td></tr>';
		// $number = 1;
		// $desc = false;
		// for ($i = 0; $i < strlen($this->sorting); ++$i)
		// {
			// $char = $this->sorting[$i];
			// if ($char == '-')
			// {
				// $desc = true;
				// continue;
			// }
			
			// echo '<tr><td colspan="3">' . $number . '. ' . ScoringSystem::get_sorting_item_label($char, $desc) . '</td></tr>';
			// ++$number;
			// $desc = false;
		// }
		// echo '<tr><td colspan="3">' . $number . '. ' . get_label('The one who created [0] account first', PRODUCT_NAME) . '</td></tr>';
		// echo '</table>';
	// }
// }

// class PlayerHistoryPoint
// {
	// public $timestamp;
	// public $points;
	// public $additional_points; // convert points to an array by category later. Now there are only 2 categories, so we'd rather keep it in a separate var.
	// public $extra_points;
	
	// function __construct($timestamp, $points, $additional_points, $extra_points)
	// {
		// $this->timestamp = $timestamp;
		// $this->points = $points;
		// $this->additional_points = $additional_points;
		// $this->extra_points = $extra_points;
	// }
// }

// class PlayerScore
// {
	// public $id;
	// public $name;
	// public $flags;
	// public $langs;
	// public $club_id;
	// public $club_name;
	// public $club_flags;
	// public $points;
	// public $additional_points; // convert points to an array by category later. Now there are only 2 categories, so we'd rather keep it in a separate var.
	// public $extra_points;
	// public $counters;
	// public $games_played;
	// public $games_won;
	// public $scores;
	// public $history;
	// public $timestamp;
	
	// function __construct($scores, $start_time)
	// {
		// $this->points = 0.0;
		// $this->additional_points = 0.0;
		// $this->extra_points = 0.0;
		// $this->counters = array_fill(0, SCORING_MATTER_COUNT * 4, 0);
		// $this->scores = $scores;
		// $this->games_played = 0;
		// $this->games_won = 0;
		// if ($start_time > 0)
		// {
			// $this->history = array();
			// $this->timestamp = $start_time;
		// }
		// else
		// {
			// $this->history = NULL;
			// $this->timestamp = 0;
		// }
	// }
	
	// function add_counters($scoring_flags, $extra_points, $player_role, $timestamp, $weight)
	// {
		// if (is_array($this->history) && $this->timestamp != $timestamp)
		// {
			// $this->calculate_points();
			// $this->history[] = new PlayerHistoryPoint($this->timestamp, $this->points, $this->additional_points, $this->extra_points);
			// $this->timestamp = $timestamp;
		// }
		
		// ++$this->games_played;
		// if ($scoring_flags & SCORING_FLAG_WIN)
		// {
			// ++$this->games_won;
		// }
		
		// $offset = $player_role * SCORING_MATTER_COUNT;
		// $flag = 1;
		// for ($i = 0; $i < SCORING_MATTER_COUNT; ++$i)
		// {
			// if ($scoring_flags & $flag)
			// {
				// $this->counters[$offset] += $weight;
			// }
			// $flag <<= 1;
			// ++$offset;
		// }
		// $this->extra_points += $extra_points;
	// }
	
	// function finalize_points($timestamp)
	// {
		// $this->calculate_points();
		// if ($this->history != NULL)
		// {
			// if ($this->timestamp != $timestamp)
			// {
				// $this->history[] = new PlayerHistoryPoint($this->timestamp, $this->points, $this->additional_points, $this->extra_points);
				// $this->timestamp = $timestamp;
			// }
			// $this->history[] = new PlayerHistoryPoint($this->timestamp, $this->points, $this->additional_points, $this->extra_points);
		// }
	// }
	
	// function calculate_points()
	// {
		// $scoring_system = $this->scores->scoring_system;
		// $stats = $this->scores->stats;
		
		// $this->points = $this->extra_points;
		// $this->additional_points = 0.0;
		// foreach ($scoring_system->rules as $rule)
		// {
			// $role_flag = 1;
			// $index = $rule->matter;
			// $difficulty = NULL;
			// for ($i = 0; $i < 4; ++$i)
			// {
				// if ($role_flag & $rule->role_flags)
				// {
					// $count = $this->counters[$index];
					// $points = 0;
					// if ($count > 0)
					// {
						// switch ($rule->policy)
						// {
							// case SCORING_POLICY_STATIC:
								// $points = $count * $rule->min_points;
								// break;
								
							// case SCORING_POLICY_GAME_DIFFICULTY:
								// $difficulty = (float)$stats[SCORING_STAT_FLAG_GAME_DIFFICULTY];
								// if ($role_flag & SCORING_ROLE_FLAGS_RED)
								// {
									// $difficulty = 1.0 - $difficulty;
								// }
								// // echo $this->name . ': matter=' . $rule->matter . '; role=' . $role_flag . '; count=' . $count . '; difficulty=' . $difficulty . '; points=' . $rule->calculate_points($difficulty) . '<br>';
								// $points = $count * $rule->calculate_points($difficulty);
								// break;
							
							// case SCORING_POLICY_FIRST_NIGHT_KILLING:
								// $points = $count * $rule->calculate_points($this->first_night_kill_rate);
								// break;
						// }
					// }
					// $this->points += $points;
					// if ($rule->category == SCORING_CATEGORY_ADDITIONAL)
					// {
						// $this->additional_points += $points;
					// }
				// }
				// $role_flag <<= 1;
				// $index += SCORING_MATTER_COUNT;
			// }
		// }
	// }
	
	// function points_str()
	// {
		// return format_score($this->points);
	// }
	
	// function points_per_game_str()
	// {
		// if ($this->games_played <= 0)
		// {
			// return 0;
		// }
		// return format_score($this->points / $this->games_played);
	// }
	
	// function get_count($matter, $role_flags = SCORING_ROLE_FLAGS_ALL)
	// {
		// $flag = 1;
		// $count = 0;
		// for ($i = 0; $i < 4; ++$i)
		// {
			// if ($flag & $role_flags)
			// {
				// $count += $this->counters[$matter];
			// }
			// $matter += SCORING_MATTER_COUNT;
			// $flag <<= 1;
		// }
		// return $count;
	// }
// }

// class Scores
// {
	// public $players;
	// public $scoring_system;
	// public $stats;
	
	// // 
	// // $condition - query condition that limits the scope of the scores with a club, or event, or address, or country. 
	// //                    The statistical parameters needed to calculate score will be calculated using scope condition. 
	// //                    Players table can not be used here.
	// // $scope_condition - query condition that limits the players/games we are interested in. For example 
	// //                      'p.user_id IN (SELECT id FROM users WHERE club_id = 1)' calculates only the scores for players
	// //                      of a specific club. It can also filter games - for example 'p.role = 1' will calculate only sherifs points for players.
	// // $history - integer. If history is greater than 0, then all timeline is divided to the appropriate quantity of time intervals, and points earned in each interval are saved into the player's history member.
	// // Examples:
	// // new Scores($system, new SQL('AND g.event_id = 10')); // scores for the event 10
	// // new Scores($system, new SQL('AND g.event_id = 10'), new SQL('AND g.id = 982')); // what scores for the event 10 were earned in the game 982
	// // new Scores($system, new SQL('AND g.club_id = 1'), new SQL('AND g.id = 982')); // what scores for the club 1 were earned in the game 982
	// function __construct($scoring_system, $condition, $scope_condition = NULL, $history = 0)
	// {
		// $this->scoring_system = $scoring_system;
		// $start_time = 0;
		// $end_time = 0;
		// $interval = 0;
		// if ($history > 0)
		// {
			// if ($scope_condition != NULL)
			// {
				// list ($start_time, $end_time) = Db::record(get_label('game'), 'SELECT MIN(g.start_time), MAX(g.end_time) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id', $condition, $scope_condition);
			// }
			// else
			// {
				// list ($start_time, $end_time) = Db::record(get_label('game'), 'SELECT MIN(g.start_time), MAX(g.end_time) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id', $condition);
			// }
			// $interval = ($end_time - $start_time) / $history;
		// }
		
		// $players = array();
		// $this->stats = array();
		// if ($scoring_system->stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		// {
			// $query = new DbQuery('SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g WHERE 1', $condition);
			// $difficulty = 0.5;
			// if ($row = $query->next())
			// {
				// list ($count, $civ_wins) = $row;
				// if ($count > 0)
				// {
					// $difficulty = $civ_wins / $count;
				// }
			// }
			// $this->stats[SCORING_STAT_FLAG_GAME_DIFFICULTY] = (float)$difficulty;
		// }
		
		// if ($scoring_system->stat_flags & SCORING_STAT_FLAG_FIRST_NIGHT_KILLING)
		// {
			// $query = new DbQuery('SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id), SUM(IF(p.kill_round = 0 AND p.kill_type = 2, 1, 0)) FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE p.role <= 1', $condition);
			// $query->add(' GROUP BY u.id');
			// while ($row = $query->next())
			// {
				// list ($user_id, $user_name, $user_flags, $user_langs, $club_id, $club_name, $club_flags, $games_count, $first_night_kill_count) = $row;
				// $player_score = new PlayerScore($this, $start_time);
				// $player_score->id = (int)$user_id;
				// $player_score->name = $user_name;
				// $player_score->flags = (int)$user_flags;
				// $player_score->langs = (int)$user_langs;
				// $player_score->club_id = (int)$club_id;
				// $player_score->club_name = $club_name;
				// $player_score->club_flags = (int)$club_flags;
				// // 7 because statistically every red player should be killed once in 7 days.
				// // So we use it to make sure a player does not get maximum ammount for being killed in one game.
				// if ($games_count < 7)
				// {
					// $games_count = 7;
				// }
				// $player_score->first_night_kill_rate = $first_night_kill_count / $games_count;
				// $players[$user_id] = $player_score;
			// }
		// }
		
		// $query = new DbQuery('SELECT u.id, u.name, u.flags, u.languages, c.id, c.name, c.flags, p.flags, p.role, p.extra_points, g.end_time FROM players p JOIN games g ON g.id = p.game_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE 1', $condition);
		// if ($scope_condition != NULL)
		// {
			// $query->add($scope_condition);
		// }
		// if ($history > 0)
		// {
			// $query->add(' ORDER BY g.end_time');
		// }
		// // echo $query->get_parsed_sql();
		// while ($row = $query->next())
		// {
			// list ($user_id, $user_name, $user_flags, $user_langs, $club_id, $club_name, $club_flags, $scoring_flags, $player_role, $player_extra_points, $timestamp) = $row;
			// $weight = 1;
			
			// if (isset($players[$user_id]))
			// {
				// $player_score = $players[$user_id];
			// }
			// else
			// {
				// $player_score = new PlayerScore($this, $start_time);
				// $player_score->id = (int)$user_id;
				// $player_score->name = $user_name;
				// $player_score->flags = (int)$user_flags;
				// $player_score->langs = (int)$user_langs;
				// $player_score->club_id = (int)$club_id;
				// $player_score->club_name = $club_name;
				// $player_score->club_flags = (int)$club_flags;
				
				// $players[$user_id] = $player_score;
			// }
			// if ($interval > 0)
			// {
				// $timestamp = $start_time + round(ceil(($timestamp - $start_time) / $interval) * $interval);
			// }
			// $player_score->add_counters($scoring_flags, $player_extra_points, $player_role, $timestamp, $weight);
		// }
		
		// $this->players = array();
		// foreach ($players as $user_id => $player)
		// {
			// // echo '<pre>';
			// // print_r($player);
			// // echo '</pre><br>';
			// if ($player->get_count(SCORING_MATTER_PLAY) > 0)
			// {
				// $player->finalize_points($end_time);
				// $this->players[] = $player;
			// }
		// }
		
		// if ($history <= 0)
		// {
			// usort($this->players, 'compare_scores');
		// }
	// }
	
	// function get_user_page($user_id, $page_size)
	// {
		// $count = count($this->players);
		// for ($i = 0; $i < $count; ++$i)
		// {
			// if ($this->players[$i]->id == $user_id)
			// {
				// return floor($i / $page_size);
			// }
		// }
		// return -1;
	// }
// }

function api_scoring_help($param)
{
}

?>
