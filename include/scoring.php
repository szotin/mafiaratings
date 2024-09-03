<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/names.php';
require_once __DIR__ . '/constants.php';

define('SCORING_DEFAULT_ID', 19); // Default scoring system is hardcoded here to ФИИМ (FIIM)
define('NORMALIZER_DEFAULT_ID', NULL); // Default normalizer is hardcoded here to no-normalizer.
define('GAINING_DEFAULT_ID', 1); // Default gaining system is hardcoded here to MWT (Mafia World Tour)

define('SCORING_TRACK_ROLE', -1); //ROLE_CIVILIAN);

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
define('SCORING_FLAG_EXTRA_POINTS', 0x400000); // 4194304: Matter 22 - Extra points are assigned manually
define('SCORING_FLAG_FIRST_LEGACY_1', 0x800000); // 8388608: Matter 23 - Guessed 1 mafia after being killed first night
define('SCORING_FLAG_WORST_MOVE', 0x1000000); // 16777216: Matter 24 - Worst move
define('SCORING_FLAG_TEAM_KICK_OUT', 0x2000000); // 33554432: Matter 25 - Team kicked out (opposite team wins)
define('SCORING_FLAG_END', 0x4000000); // 67108864: Just marks the end of flags for loops

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
define('SCORING_LOD_PER_ROLE', 64); // adds scores per role

define('SCORING_OPTION_NO_NIGHT_KILLS', 1); // Do not use policies dependent on the night kills
define('SCORING_OPTION_NO_GAME_DIFFICULTY', 2); // Do not use policies dependent on the game difficulty

$_scoring_groups = array(SCORING_GROUP_MAIN, SCORING_GROUP_EXTRA, SCORING_GROUP_LEGACY, SCORING_GROUP_PENALTY, SCORING_GROUP_NIGHT1);

function is_hiding_bonus_needed($tournament_flags, $round_num)
{
	if ($tournament_flags & TOURNAMENT_FLAG_FINISHED)
	{
		return false;
	}
	
	switch (($tournament_flags & TOURNAMENT_HIDE_BONUS_MASK) >> TOURNAMENT_HIDE_BONUS_MASK_OFFSET)
	{
		case 1:
			return true;
		case 2:
			return $round_num == 1;
		case 3:
			return $round_num == 1 || $round_num == 2;
	}
	return false;
}

function compare_role_scores($role, $player1, $player2)
{
	if (is_null($player1))
	{
		return -1;
	}
	if (is_null($player2))
	{
		return 1;
	}

	switch ($role)
	{
		case ROLE_CIVILIAN:
			$games_count1 = $player1->roles[ROLE_CIVILIAN]->games_count + $player1->roles[ROLE_SHERIFF]->games_count;
			$bonus1 = $player1->roles[ROLE_CIVILIAN]->bonus + $player1->roles[ROLE_SHERIFF]->bonus;
			$points1 = $player1->roles[ROLE_CIVILIAN]->points + $player1->roles[ROLE_SHERIFF]->points;
			$games_count2 = $player2->roles[ROLE_CIVILIAN]->games_count + $player2->roles[ROLE_SHERIFF]->games_count;
			$bonus2 = $player2->roles[ROLE_CIVILIAN]->bonus + $player2->roles[ROLE_SHERIFF]->bonus;
			$points2 = $player2->roles[ROLE_CIVILIAN]->points + $player2->roles[ROLE_SHERIFF]->points;
			break;
		case ROLE_MAFIA:
			$games_count1 = $player1->roles[ROLE_MAFIA]->games_count + $player1->roles[ROLE_DON]->games_count;
			$bonus1 = $player1->roles[ROLE_MAFIA]->bonus + $player1->roles[ROLE_DON]->bonus;
			$points1 = $player1->roles[ROLE_MAFIA]->points + $player1->roles[ROLE_DON]->points;
			$games_count2 = $player2->roles[ROLE_MAFIA]->games_count + $player2->roles[ROLE_DON]->games_count;
			$bonus2 = $player2->roles[ROLE_MAFIA]->bonus + $player2->roles[ROLE_DON]->bonus;
			$points2 = $player2->roles[ROLE_MAFIA]->points + $player2->roles[ROLE_DON]->points;
			break;
		default:
			$games_count1 = $player1->roles[$role]->games_count;
			$bonus1 = $player1->roles[$role]->bonus;
			$points1 = $player1->roles[$role]->points;
			$games_count2 = $player2->roles[$role]->games_count;
			$bonus2 = $player2->roles[$role]->bonus;
			$points2 = $player2->roles[$role]->points;
			break;
	}
	
	if ($role == SCORING_TRACK_ROLE)
	{
		echo $player1->name . ' - ' . $bonus1 . '(' . $games_count1 . ') : ' . $player2->name . ' - ' . $bonus2 . '(' . $games_count2 . ')';
	}
	
	if ($games_count1 <= 0)
	{
		if ($games_count2 <= 0)
		{
			return 0;
		}
		return -1;
	}
	if ($games_count2 <= 0)
	{
		return 1;
	}
	
	if (abs($bonus1 - $bonus2) > 0.001)
	{
		return $bonus1 - $bonus2;
	}
	
	if (abs($points1 - $points2) > 0.001)
	{
		return $points1 - $points2;
	}
	
	return $games_count2 - $games_count1;
}

function format_coeff($coeff, $sign_digits = 3)
{
	return round($coeff, $sign_digits - floor(log10($coeff)) - 1);
}

function format_score($score, $zeroes = false)
{
	if ($score == 0)
	{
		if ($zeroes)
		{
			return 0;
		}
		return '';
	}
	
	$int_score = (int)($score * 1000);
	if ($int_score % 10 >= 5)
	{
		$int_score /= 10;
		$int_score += 1;
	}
	else
	{
		$int_score /= 10;
	}
	
	$result = number_format($score, 3);
	$pos = -1;
	for ($i = strlen($result) - 1; $i >= 0 && $result[$i] == '0'; --$i)
	{
		$pos = $i;
	}
	if ($pos >= 0)
	{
		if ($result[$i] == '.')
		{
			--$pos;
		}
		$result = substr($result, 0, $pos);
	}
	return $result;
	
	// if (($int_score % 10) != 0)
	// {
		// return number_format($score, 3);
	// }
	// else if (($int_score % 100) != 0)
	// {
		// return number_format($score, 2);
	// }
	// else if (($int_score % 1000) != 0)
	// {
		// return number_format($score, 1);
	// }
	// return number_format($score);
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
	$player->weighted_games_count = 0;
	
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
	
	if ($lod_flags & SCORING_LOD_PER_ROLE)
	{
		$player->roles = array();
		for ($i = 0; $i < 4; ++$i)
		{
			$role_points = new stdClass();
			$role_points->points = 0;
			$role_points->games_count = 0;
			$role_points->weighted_games_count = 0;
			if ($lod_flags & SCORING_LOD_PER_GROUP)
			{
				foreach ($_scoring_groups as $group)
				{
					$g = $group . '_points';
					$role_points->$g = 0;
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
					$role_points->$group = $a;
				}
				$role_points->extra[] = 0;
			}
			$player->roles[] = $role_points;
		}
	}
}

function add_player_score($player, $player_stats, $scoring, $game_id, $game_end_time, $game_flags, $game_role, $extra_pts, $red_win_rate, $lod_flags, $options, $event_name = NULL)
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
	$player->weighted_games_count += $weight;
	
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
					$points = isset($policy->min_points) ? $policy->min_points : 0;
				}
				else
				{
					$min_night1 = isset($policy->min_night1) ? $policy->min_night1 : 0;
					$max_night1 = isset($policy->max_night1) ? $policy->max_night1 : 1;
					$min_points = isset($policy->min_points) ? $policy->min_points : 0;
					$max_points = isset($policy->max_points) ? $policy->max_points : 0;
					$lost_only = isset($policy->lost_only) ? $policy->lost_only : false;
					$percent = isset($policy->percent) ? $policy->percent : true;
					$rate = 0;
					
					// if ($player->id == 4055)
					// {
						// echo 'Games count: ' . $player_stats->games_count . '<br>';
						// echo 'Killed first: ' . $player_stats->killed_first_count . '<br>';
						// echo 'Killed first and lost: ' . $player_stats->killed_first_lost_count . '<br>';
						// echo 'Min night 1: ' . $min_night1 . '<br>';
						// echo 'Max night 1: ' . $max_night1 . '<br>';
						// echo 'Min points: ' . $min_points . '<br>';
						// echo 'Max points: ' . $max_points . '<br>';
						// echo 'Lost only: ' . $lost_only . '<br>';
						// echo 'Percent: ' . $percent . '<br>';
						// echo '---------------------<br>';
					// }
					
					if ($player_stats->games_count > 0)
					{
						$killed_first_count = $lost_only ? $player_stats->killed_first_lost_count : $player_stats->killed_first_count;
						if ($percent)
						{
							$rate = max(min($killed_first_count / $player_stats->games_count, 1), 0);
						}
						else
						{
							$rate = $killed_first_count;
						}
					}
					
					if ($rate <= $min_night1)
					{
						$points = $min_points;
					}
					else if ($rate >= $policy->max_night1)
					{
						$points = $max_points;
					}
					else
					{
						$points = ($max_points - $min_points) * $rate;
						$points += $min_points * $max_night1 - $max_points * $min_night1;
						$points /= $max_night1 - $min_night1;
					}
				}
			}
			else if (isset($policy->fiim_first_night_score))
			{
				if (($options_flags & SCORING_OPTION_NO_NIGHT_KILLS) == 0)
				{
					$game_percentage = isset($policy->percent) ? $policy->percent : 0.4;
					$points = round($player_stats->games_count * $game_percentage);
					if ($points != 0)
					{
						$points = max(min($player_stats->killed_first_count * $policy->fiim_first_night_score / $points, $policy->fiim_first_night_score), 0);
					}
					else
					{
						$points = $policy->fiim_first_night_score;
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
	
	if ($lod_flags & SCORING_LOD_PER_ROLE)
	{
		$r = $player->roles[$game_role];
		$r->points += $total_points;
		++$r->games_count;
		$r->weighted_games_count += $weight;
        if ($lod_flags & SCORING_LOD_PER_GROUP)
        {
            foreach ($_scoring_groups as $group)
            {
                $g = $group . '_points';
                $r->$g += $$g;
            }
        }
        if ($lod_flags & SCORING_LOD_PER_POLICY)
        {
			foreach ($_scoring_groups as $group)
			{
				$pg_array = &$r->$group;
				$ppg_array = &$per_policy->$group;
				for ($i = 0; $i < count($ppg_array); ++$i)
				{
					$pg_array[$i] += $ppg_array[$i];
				}
			}
        }
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
		if (!$in_brackets)
		{
			$value1 = 0;
			$value2 = 0;
		}
		
		//'legacy', 'penalty', 'night1'
		switch ($char)
		{
			case '-':
				$sign = -1;
				continue;
			case '(':
				$in_brackets = true;
				continue;
			case ')':
				$in_brackets = false;
				break;
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
    
function event_scores($event_id, $players_list, $lod_flags, $scoring, $options, $tournament_flags, $round_num)
{
	global $_scoring_groups, $_lang;

	if (($tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0)
	{
		switch (($tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
		{
			case 1:
				return array();
			case 2:
				if ($round_num == 1)
				{
					return array();
				}
				break;
			case 3:
				if ($round_num == 1 || $round_num == 2)
				{
					return array();
				}
				break;
		}
	}

	// todo: replace user name with name id
	if (!isset($_lang))
	{
		$_lang = 1;
	}
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
		'SELECT u.id, nu.name, u.flags, u.languages, c.id, c.name, c.flags, COUNT(g.id)' .
		', SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2, 1, 0))' .
		', SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2 AND p.won = 0, 1, 0))' .
		', SUM(p.won)' .
		', SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))' .
		', eu.nickname, eu.flags, tu.flags, cu.flags' .
			' FROM players p' .
			' JOIN games g ON g.id = p.game_id' .
			' JOIN users u ON u.id = p.user_id' .
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
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
		$player->killed_first_lost_count = (int)$row[9];
		$player->wins = (int)$row[10];
		$player->special_role_wins = (int)$row[11];
		$player->nickname = $row[12];
		$player->event_user_flags = (int)$row[13];
		$player->tournament_user_flags = (int)$row[14];
		$player->club_user_flags = (int)$row[15];

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
		if (is_hiding_bonus_needed($tournament_flags, $round_num))
		{
			$extra_points = 0;
			$flags &= ~(SCORING_FLAG_BEST_PLAYER | SCORING_FLAG_BEST_MOVE | SCORING_FLAG_EXTRA_POINTS | SCORING_FLAG_WORST_MOVE);
		}
		$player = $players[$player_id];
		add_player_score($player, $player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags, $options);
	}
	
	// Add event extra points
	$query = new DbQuery('SELECT user_id, points, scoring_group, scoring_matter FROM event_extra_points WHERE event_id = ?', $event_id);
	while ($row = $query->next())
	{
		list ($player_id, $points, $group, $matter) = $row;
		if (isset($players[$player_id]))
		{
			if (isset($options->weight))
			{
				$points *= $options->weight;
			}
			
			$player = $players[$player_id];
			$g = $group . '_points';
			if (isset($player->$g))
			{
				$player->$g += $points;
			}
			if (isset($player->$group) && is_array($player->$group))
			{
				$a = &$player->scoring->$group;
				for ($i = 0; $i < count($a); ++$i)
				{
					if ($a[$i]->matter & $matter)
					{
						$sg = &$player->$group;
						$sg[$i] += $points;
						break;
					}
				}
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
	global $_lang;
	
	// todo: replace user name with user name id
	if (!isset($_lang))
	{
		$_lang = 1;
	}
	if (is_null($normalizer))
	{
		$normalizer = new stdClass();
	}
	
	$hide_table_condition = new SQL();
	if (($tournament_flags & TOURNAMENT_FLAG_FINISHED) == 0)
	{
		switch (($tournament_flags & TOURNAMENT_HIDE_TABLE_MASK) >> TOURNAMENT_HIDE_TABLE_MASK_OFFSET)
		{
			case 1:
				return array();
			case 2:
				$hide_table_condition = new SQL(' AND e.round <> 1');
				break;
			case 3:
				$hide_table_condition = new SQL(' AND e.round NOT IN (1,2)');
				break;
		}
	}
	
	$event_scorings = NULL;
	if (($tournament_flags & TOURNAMENT_FLAG_LONG_TERM) == 0)
	{
		$event_scorings = array();
	}
	$stat_flags = get_scoring_stat_flags($scoring, $options);
    
    $condition = get_players_condition($players_list);
	$condition->add($hide_table_condition);

	// Calculate first night kill rates and games count per player
	$max_games_played = 0;
	$max_rounds_played = 0;
	if (!$condition->is_empty())
	{
		$query = new DbQuery('SELECT p.user_id, COUNT(DISTINCT g.id), COUNT(DISTINCT g.event_id) FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $hide_table_condition);
		$query->add(' GROUP BY p.user_id');
		while ($row = $query->next())
		{
			list($uid, $games_played, $rounds_played) = $row;
			$max_games_played = max($max_games_played, $games_played);
			$max_rounds_played = max($max_rounds_played, $rounds_played);
		}
	}
	
    $players = array();
	$query = new DbQuery(
		'SELECT u.id, nu.name, u.flags, u.languages, c.id, c.name, c.flags,' . 
		' COUNT(g.id), COUNT(DISTINCT g.event_id),' . 
		' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2, 1, 0)),' . 
		' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2 AND p.won = 0, 1, 0)),' .
		' SUM(p.won), SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0)),' . 
		' tu.flags, cu.flags' .
			' FROM players p' . 
			' JOIN games g ON g.id = p.game_id' . 
			' JOIN events e ON e.id = g.event_id' . 
			' JOIN users u ON u.id = p.user_id' . 
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
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
		$player->killed_first_lost_count = (int)$row[10];
		$player->wins = (int)$row[11];
		$player->special_role_wins = (int)$row[12];
		$player->normalizer = $normalizer;
		$player->tournament_user_flags = (int)$row[13];
		$player->club_user_flags = (int)$row[14];
		
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
            list ($count, $red_wins) = Db::record(get_label('tournament'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $hide_table_condition);
            if ($count > 0)
            {
                $red_win_rate = max(min((float)($red_wins / $count), 1), 0);
            }
        }
        
        // echo '<pre>';
        // print_r($scoring);
        // echo '</pre><br>';
        
        // Calculate scores
        $query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time, e.name, e.round FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $condition);
        $query->add(' ORDER BY g.end_time');
        while ($row = $query->next())
        {
            list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time, $event_name, $round_num) = $row;
			if (is_hiding_bonus_needed($tournament_flags, $round_num))
			{
				$extra_points = 0;
				$flags &= ~(SCORING_FLAG_BEST_PLAYER | SCORING_FLAG_BEST_MOVE | SCORING_FLAG_EXTRA_POINTS | SCORING_FLAG_WORST_MOVE);
			}
			if (isset($players[$player_id])) // It happens that it is not set but I have no idea why. To be investigated.
			{
				$player = $players[$player_id];
				add_player_score($player, $player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags, $options, $event_name);
			}
        }
    }
    else
    {
		// prepare scorings per event
		$query = new DbQuery('SELECT e.id, e.name, e.scoring_options FROM events e WHERE e.tournament_id = ?', $tournament_id, $hide_table_condition);
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
				if (isset($event_scoring_options->group))
				{
					$group->options->group = $event_scoring_options->group;
				}
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
				new DbQuery('SELECT p.user_id, COUNT(g.id),'.
				' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2, 1, 0)),'.
				' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2 AND p.won = 0, 1, 0)),' .
				' SUM(p.won),'.
				' SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
				' FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN events e ON e.id = g.event_id' .
				' WHERE g.event_id IN(' . $group->events . ') AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $condition);
            $query->add(' GROUP BY p.user_id');
            while ($row = $query->next())
            {
				$player = new stdClass();
				$player->id = (int)$row[0];
				$player->games_count = (int)$row[1];
				$player->killed_first_count = (int)$row[2];
				$player->killed_first_lost_count = (int)$row[3];
				$player->wins = (int)$row[4];
				$player->special_role_wins = (int)$row[5];
                $group->players[$player->id] = $player;
            }
        }
		
		// Calculate red win rate for non-tournament event games
		$no_event_red_win_rate = 0;
		if ($stat_flags & SCORING_STAT_FLAG_GAME_DIFFICULTY)
		{
			list ($count, $red_wins) = Db::record(get_label('event'), 'SELECT count(g.id), SUM(IF(g.result = 1, 1, 0)) FROM games g JOIN events e ON e.id = g.event_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0 AND e.tournament_id <> g.tournament_id', $tournament_id, $hide_table_condition);
			if ($count > 0)
			{
				$no_event_red_win_rate = max(min((float)($red_wins / $count), 1), 0);
			}
		}
		
		// Calculate first night kill rates and games count per player for non-tournament event games
		$no_event_players = array();
		$query = 
			new DbQuery(
				'SELECT p.user_id, COUNT(g.id),'.
				' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2, 1, 0)),'.
				' SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ' AND p.role < 2 AND p.won = 0, 1, 0)),' .
				' SUM(p.won),'.
				' SUM(IF(p.won > 0 AND (p.role = 1 OR p.role = 3), 1, 0))'.
				' FROM players p' .
				' JOIN games g ON g.id = p.game_id' .
				' JOIN events e ON e.id = g.event_id' .
				' WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0 AND e.tournament_id != g.tournament_id', $tournament_id, $condition);
		$query->add(' GROUP BY p.user_id');
		while ($row = $query->next())
		{
			$player = new stdClass();
			$player->id = (int)$row[0];
			$player->games_count = (int)$row[1];
			$player->killed_first_count = (int)$row[2];
			$player->killed_first_lost_count = (int)$row[3];
			$player->wins = (int)$row[4];
			$player->special_role_wins = (int)$row[5];
			init_player_score($player, $scoring, $lod_flags);
			$no_event_players[$player->id] = $player;
		}
		
		// Calculate scores
		$query = new DbQuery('SELECT p.user_id, p.flags, p.role, p.extra_points, g.id, g.end_time, g.event_id, e.round FROM players p JOIN games g ON g.id = p.game_id JOIN events e ON e.id = g.event_id JOIN users u ON u.id = p.user_id LEFT OUTER JOIN clubs c ON c.id = u.club_id WHERE g.tournament_id = ? AND g.result > 0 AND g.is_canceled = 0 AND g.is_rating <> 0', $tournament_id, $condition);
		$query->add(' ORDER BY g.end_time');
		//echo $query->get_parsed_sql();
		while ($row = $query->next())
		{
			list ($player_id, $flags, $role, $extra_points, $game_id, $game_end_time, $event_id, $round_num) = $row;
			if (is_hiding_bonus_needed($tournament_flags, $round_num))
			{
				$extra_points = 0;
				$flags &= ~(SCORING_FLAG_BEST_PLAYER | SCORING_FLAG_BEST_MOVE | SCORING_FLAG_EXTRA_POINTS | SCORING_FLAG_WORST_MOVE);
			}
			
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
			add_player_score($players[$player_id], $player, $scoring, $game_id, $game_end_time, $flags, $role, $extra_points, $red_win_rate, $lod_flags, $op, $event_name);
		}
    }
	
	// Add event extra points
	$query = new DbQuery('SELECT p.event_id, p.user_id, p.points, p.scoring_group, p.scoring_matter FROM event_extra_points p JOIN events e ON e.id = p.event_id WHERE e.tournament_id = ?', $tournament_id, $hide_table_condition);
	while ($row = $query->next())
	{
		list ($event_id, $player_id, $points, $group, $matter) = $row;
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
			
			
			$g = $group . '_points';
			if (isset($player->$g))
			{
				$player->$g += $points;
			}
			if (isset($player->$group) && is_array($player->$group))
			{
				$a = &$player->scoring->$group;
				for ($i = 0; $i < count($a); ++$i)
				{
					if ($a[$i]->matter & $matter)
					{
						$sg = &$player->$group;
						$sg[$i] += $points;
						break;
					}
				}
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

function add_tournament_nominants($tournament_id, $players)
{
	$players_count = count($players);
	if ($players_count <= 0)
	{
		return;
	}
	
	// Calculate constant points that have to be removed from bonus (remove auto-bonus)
	$remove_from_bonus = 0;
	$scoring = $players[0]->scoring;
	if (isset($scoring->extra))
	{
		foreach ($scoring->extra as $scoring_case)
		{
			if ($scoring_case->matter == SCORING_FLAG_PLAY)
			{
				$remove_from_bonus += $scoring_case->points;
			}
		}
	}
	if (isset($scoring->legacy))
	{
		foreach ($scoring->legacy as $scoring_case)
		{
			if ($scoring_case->matter == SCORING_FLAG_PLAY)
			{
				$remove_from_bonus += $scoring_case->points;
			}
		}
	}
	if (isset($scoring->penalty))
	{
		foreach ($scoring->penalty as $scoring_case)
		{
			if ($scoring_case->matter == SCORING_FLAG_PLAY)
			{
				$remove_from_bonus += $scoring_case->points;
			}
		}
	}
	
	// The tournament counts for a player only if they played more than 50% of maximum games count. 
	$roles = array(NULL, NULL, NULL, NULL);
	$roles_winners_count = array(0, 0, 0, 0);
	$mvp = NULL;
	$mvp_winner_count = 0;
	foreach ($players as $player)
	{
		$player->bonus = $player->extra_points + $player->legacy_points + $player->penalty_points - $player->weighted_games_count * $remove_from_bonus;
		$player->nom_flags = 0;
		if ($mvp == NULL)
		{
			$mvp = $player;
			$mvp_winner_count = 1;
		}
		else if (abs($player->bonus - $mvp->bonus) < 0.001)
		{
			$mvp = $player; // the one with the lower place wins
			++$mvp_winner_count;
		}
		else if ($player->bonus > $mvp->bonus)
		{
			$mvp = $player;
			$mvp_winner_count = 1;
		}
			
		for ($i = 0; $i < 4; ++$i)
		{
			$r = $player->roles[$i];
			if ($r->games_count <= 0)
			{
				$r->bonus = 0;
				continue;
			}
			$r->bonus = $r->extra_points + $r->legacy_points + $r->penalty_points - $r->weighted_games_count * $remove_from_bonus;
		}
		
		for ($i = 0; $i < 4; ++$i)
		{
			$cmp = compare_role_scores($i, $player, $roles[$i]);
			if ($i == SCORING_TRACK_ROLE)
			{
				if ($cmp > 0)
				{
					echo ' winner - ' . $player->name . '<br>';
				}
				else if ($cmp == 0)
				{
					echo ' tie - ' . $player->name . '<br>';
				}
				else
				{
					echo ' winner - ' . $roles[$i]->name . '<br>';
				}
			}
			
			if ($cmp > 0)
			{
				$roles[$i] = $player;
				$roles_winners_count[$i] = 1;
			}
			else if ($cmp == 0)
			{
				$roles[$i] = $player; // the player with a lower place wins
				++$roles_winners_count[$i];
			}
		}
	}
	
//	print_json($players);
	
	$flags = COMPETITION_MVP;
	if ($mvp && $mvp_winner_count <= 2) // we give a win by lower place only when there are 2 or less pretenders
	{
		$mvp->nom_flags |= $flags;
	}
	for ($i = 0; $i < 4; ++$i)
	{
		$flags <<= 1;
		if ($roles[$i] != NULL && $roles_winners_count[$i] <= 2) // we give a win by lower place only when there are 2 or less pretenders
		{
			$roles[$i]->nom_flags |= $flags;
		}
	}
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
	if (is_int($policy))
	{
		$matter = $policy;
	}
	else if (isset($policy->matter))
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
			case SCORING_FLAG_WORST_MOVE:
				$l = get_label('removed auto-bonus');
				break;
			case SCORING_FLAG_TEAM_KICK_OUT:
				$l = get_label('making the opposite team win');
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
		case SCORING_GROUP_MAIN:
			return get_label('Main points');
		case SCORING_GROUP_LEGACY:
			return get_label('Legacy points');
		case SCORING_GROUP_EXTRA:
			return get_label('Extra points');
		case SCORING_GROUP_PENALTY:
			return get_label('Penalty points');
		case SCORING_GROUP_NIGHT1:
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

function get_gaining_points($table, $place, $score)
{
	--$place; // switch to 0 based index
	if ($place < 0 || $place >= $table->players)
	{
		return 0;
	}
	
	if (isset($table->asIs))
	{
		return $score * $table->asIs;
	}
	
	$min_points = 0;
	if (isset($table->minPoints))
	{
		$min_points = (float)$table->minPoints;
	}
	
	$share_points = 0;
	if (isset($table->pointsPool) && $table->scoreSum > 0)
	{
		if ($table->tournamentScorePower > 0)
		{
			$share_points = $table->pointsPool * min(max($score / $table->scoreSum, 0), 1);
		}
		else
		{
			$share_points = $table->pointsPool / $table->players;
		}
	}
		
	if (isset($table->points))
	{
		$points_count = count($table->points);
		if ($place < $points_count)
		{
			return $share_points + $table->points[$place]; 
		}
		
		$formula = 0;
		if (isset($table->formula))
		{
			$formula = (int)$table->formula;
		}
		
		if ($formula > 0 && $points_count > 0)
		{
			return $share_points + $min_points + ($table->points[$points_count - 1] - $min_points) * pow(($table->players - $place - 1) / ($table->players - $points_count), $formula);
		}
	}
	return $share_points + $min_points;
}

function _gaining_tables_regression($table1, $table2, $stars, $players)
{
	if ($table1->stars == $stars && $table2->stars == $stars)
	{
		if ($table1->players == $players)
		{
			return $table1;
		}
		if ($table2->players == $players)
		{
			return $table2;
		}
		
		if ($table1->players == $table2->players)
		{
			throw Exc('Regression is impossible between stars ' . $table1->stars . '=>' . $stars . '=>' . $table2->stars . ' and players ' . $table1->players . '=>' . $players . '=>' . $table2->players);
		}
		
		$points1 = 0;
		if (isset($table1->points))
		{
			$points1 = count($table1->points);
		}
		
		$points2 = 0;
		if (isset($table2->points))
		{
			$points2 = count($table2->points);
		}
		
		$table = new stdClass();
		$table->stars = $stars;
		$table->players = $players;
		
		$min_points1 = 0;
		if (isset($table1->minPoints))
		{
			$min_points1 = $table1->minPoints;
		}
		$min_points2 = 0;
		if (isset($table2->minPoints))
		{
			$min_points2 = $table2->minPoints;
		}
		$min_points = ($min_points2 * ($players - $table1->players) + $min_points1 * ($table2->players - $players)) / ($table2->players - $table1->players);
		if ($min_points != 0)
		{
			$table->minPoints = $min_points;
		}
		
		$ppp1 = 0;
		if (isset($table1->pointsPerPlayer))
		{
			$ppp1 = $table1->pointsPerPlayer;
		}
		$ppp2 = 0;
		if (isset($table2->pointsPerPlayer))
		{
			$ppp2 = $table2->pointsPerPlayer;
		}
		$ppp = ($ppp2 * ($players - $table1->players) + $ppp1 * ($table2->players - $players)) / ($table2->players - $table1->players);
		if ($ppp != 0)
		{
			$table->pointsPerPlayer = $ppp;
		}
		
		if (isset($table1->formula))
		{
			$table->formula = $table1->formula;
		}
		$table->points = array();
		$i = 0;
		while (true)
		{
			$keep_going = false;
			if ($i < $points1)
			{
				$keep_going = true;
				$p1 = $table1->points[$i];
			}
			else
			{
				$p1 = get_gaining_points($table1, $i + 1, 0);
			}
			if ($i < $points2)
			{
				$keep_going = true;
				$p2 = $table2->points[$i];
			}
			else
			{
				$p2 = get_gaining_points($table2, $i + 1, 0);
			}
			
			if ($keep_going)
			{
				$table->points[$i] = ($p2 * ($players - $table1->players) + $p1 * ($table2->players - $players)) / ($table2->players - $table1->players);
				++$i;
			}
			else
			{
				break;
			}
		}
		return $table;
	}
	else if ($table1->players == $players && $table2->players == $players)
	{
		if ($table1->stars == $stars)
		{
			return $table1;
		}
		if ($table2->stars == $stars)
		{
			return $table2;
		}
		
		if ($table1->stars == $table2->stars)
		{
			throw Exc('Regression is impossible between stars ' . $table1->stars . '=>' . $stars . '=>' . $table2->stars . ' and players ' . $table1->players . '=>' . $players . '=>' . $table2->players);
		}
		
		$points1 = 0;
		if (isset($table1->points))
		{
			$points1 = count($table1->points);
		}
		
		$points2 = 0;
		if (isset($table2->points))
		{
			$points2 = count($table2->points);
		}
		
		$table = new stdClass();
		$table->stars = $stars;
		$table->players = $players;
		
		$min_points1 = 0;
		if (isset($table1->minPoints))
		{
			$min_points1 = $table1->minPoints;
		}
		$min_points2 = 0;
		if (isset($table2->minPoints))
		{
			$min_points2 = $table2->minPoints;
		}
		$min_points = ($min_points2 * ($stars - $table1->stars) + $min_points1 * ($table2->stars - $stars)) / ($table2->stars - $table1->stars);
		if ($min_points != 0)
		{
			$table->minPoints = $min_points;
		}
		
		$ppp1 = 0;
		if (isset($table1->pointsPerPlayer))
		{
			$ppp1 = $table1->pointsPerPlayer;
		}
		$ppp2 = 0;
		if (isset($table2->pointsPerPlayer))
		{
			$ppp2 = $table2->pointsPerPlayer;
		}
		$ppp = ($ppp2 * ($stars - $table1->stars) + $ppp1 * ($table2->stars - $stars)) / ($table2->stars - $table1->stars);
		if ($ppp != 0)
		{
			$table->pointsPerPlayer = $ppp;
		}
		
		if (isset($table1->formula))
		{
			$table->formula = $table1->formula;
		}
		$table->points = array();
		$i = 0;
		while (true)
		{
			$keep_going = false;
			if ($i < $points1)
			{
				$keep_going = true;
				$p1 = $table1->points[$i];
			}
			else
			{
				$p1 = get_gaining_points($table1, $i + 1, 0);
			}
			if ($i < $points2)
			{
				$keep_going = true;
				$p2 = $table2->points[$i];
			}
			else
			{
				$p2 = get_gaining_points($table2, $i + 1, 0);
			}
			
			if ($keep_going)
			{
				$table->points[$i] = ($p2 * ($stars - $table1->stars) + $p1 * ($table2->stars - $stars)) / ($table2->stars - $table1->stars);
				++$i;
			}
			else
			{
				break;
			}
		}
		return $table;
	}
	throw Exc('Regression is impossible between stars ' . $table1->stars . '=>' . $stars . '=>' . $table2->stars . ' and players ' . $table1->players . '=>' . $players . '=>' . $table2->players);
}

function _create_zero_gaining_table($stars, $players)
{
	$table = new stdClass();
	$table->stars = $stars;
	$table->players = $players;
	return $table;
}

function _create_gaining_table_for_players($gaining, $stars_obj, $players_obj, $players)
{
	$stars = 1;
	if (isset($stars_obj->stars))
	{
		$stars = $stars_obj->stars;
	}
	
	$table = new stdClass();
	$table->stars = isset($stars_obj->stars) ? $stars_obj->stars : 1;
	$table->players = $players;
	if (isset($players_obj->points))
	{
		$table->points = $players_obj->points;
	}
	
	if (isset($players_obj->pointsPerPlayer))
	{
		$table->pointsPerPlayer = $players_obj->pointsPerPlayer;
	}
	
	if (isset($stars_obj->minPoints))
	{
		$table->minPoints = $stars_obj->minPoints;
	}
	
	if (isset($stars_obj->formula))
	{
		$table->formula = $stars_obj->formula;
	}
	else if (isset($gaining->formula))
	{
		$table->formula = $gaining->formula;
	}
	return $table;
}

function _create_gaining_table_for_stars($gaining, $stars_obj, $players)
{
	$table = NULL;
	$stars = 1;
	if (isset($stars_obj->stars))
	{
		$stars = $stars_obj->stars;
	}
	
	if (isset($stars_obj->points))
	{
		$players_obj1 = $players_obj2 = NULL;
		$delta1 = $delta2 = 100000000;
		for ($i = 0; $i < count($stars_obj->points); ++$i)
		{
			$players_obj = $stars_obj->points[$i];
			$p = isset($players_obj->players) ? $players_obj->players : 10;
			if ($players == $p)
			{
				$players_obj1 = $players_obj;
				$players_obj2 = NULL;
				break;
			}
			
			$delta = abs($p - $players);
			if ($delta < $delta1 && $delta2 <= $delta1)
			{
				$players_obj1 = $players_obj;
				$delta1 = $delta;
			}
			else if ($delta < $delta2)
			{
				$players_obj2 = $players_obj;
				$delta2 = $delta;
			}
		}
		
		if ($players_obj1 != NULL)
		{
			if ($players_obj2 != NULL)
			{
				$players1 = isset($players_obj1->players) ? $players_obj1->players : 1;
				$players2 = isset($players_obj2->players) ? $players_obj2->players : 1;
				if ($players1 > $players2)
				{
					$players_obj = $players_obj2;
					$players_obj2 = $players_obj1;
					$players_obj1 = $players_obj;
					
					$p = $players2;
					$players2 = $players1;
					$players1 = $p;
				}
				
				if ($players < $players1)
				{
					if (!isset($gaining->lessPlayers) || $gaining->lessPlayers == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->lessPlayers == 'closest')
					{
						$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players);
					}
					else if ($gaining->lessPlayers != 'extrapolate')
					{
						throw new Exc('Invalid lessPlayers value "' . $gaining->lessPlayers . '". Valid values are: "no", "closest", and "extrapolate".');
					}
				}
				else if ($players < $players2)
				{
					if (!isset($gaining->midPlayers) || $gaining->midPlayers == 'min')
					{
						$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players);
					}
					else if ($gaining->midPlayers == 'max')
					{
						$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj2, $players);
					}
					else if ($gaining->midPlayers == 'closest')
					{
						if (abs($players1 - $players) < abs($players2 - $players))
						{
							$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players);
						}
						else
						{
							$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj2, $players);
						}
					}
					else if ($gaining->midPlayers == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->midPlayers != 'interpolate')
					{
						throw new Exc('Invalid midPlayers value "' . $gaining->midPlayers . '". Valid values are: "no", "min", "max", "closest", and "interpolate".');
					}
				}
				else
				{
					if (!isset($gaining->morePlayers) || $gaining->morePlayers == 'closest')
					{
						$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players);
					}
					else if ($gaining->morePlayers == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->morePlayers != 'extrapolate')
					{
						throw new Exc('Invalid morePlayers value "' . $gaining->morePlayers . '". Valid values are: "no", "closest", and "extrapolate".');
					}
				}
				
				if (is_null($table))
				{
					$result = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players1);
					if ($players1 != $players2)
					{
						$result2 = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj2, $players2);
						$result = _gaining_tables_regression($result, $result2, $stars, $players);
					}
					$table = $result;
				}
			}
			else
			{
				$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj1, $players);
			}
		}
		else if ($players_obj2 != NULL)
		{
			$table = _create_gaining_table_for_players($gaining, $stars_obj, $players_obj2, $players);
		}
	}
	
	if (is_null($table))
	{
		$table = _create_zero_gaining_table($stars, $players);

	}
	return $table;
}

function copy_gaining($dst, $src)
{
	foreach ($src as $key => $value)
	{
		if ($key == 'tournaments' || $key == 'series')
		{
			continue;
		}
		$dst->$key = $value;
	}
}

function prepare_gaining($gaining, $is_series)
{
	if ($is_series)
	{
		if (isset($gaining->series))
		{
			$result = new stdClass();
			copy_gaining($result, $gaining);
			copy_gaining($result, $gaining->series);
			$gaining = $result;
		}
	}
	else if (isset($gaining->tournaments))
	{
		$result = new stdClass();
		copy_gaining($result, $gaining);
		copy_gaining($result, $gaining->tournaments);
		$gaining = $result;
	}
	return $gaining;
}

function get_gainig_sum_power($gaining, $is_series)
{
	$gaining = prepare_gaining($gaining, $is_series);
	if (isset($gaining->useTournamentScore))
	{
		return max((int)$gaining->useTournamentScore, 0);
	}
	if (isset($gaining->asIs))
	{
		return 1;
	}
	return 0;
}

function create_gaining_table($gaining, $stars, $players, $score_sum, $is_series)
{
	$table = NULL;
	$gaining = prepare_gaining($gaining, $is_series);
	if (isset($gaining->points))
	{
		$stars_array = $gaining->points;
		$stars_obj1 = $stars_obj2 = NULL;
		$delta1 = $delta2 = 100000000;
		for ($i = 0; $i < count($stars_array); ++$i)
		{
			$stars_obj = $stars_array[$i];
			$s = isset($stars_obj->stars) ? $stars_obj->stars : 1;
			if ($s == $stars)
			{
				$stars_obj1 = $stars_obj;
				$stars_obj2 = NULL;
				break;
			}
			
			$delta = abs($s - $stars);
			if ($delta < $delta1 && $delta2 <= $delta1)
			{
				$stars_obj1 = $stars_obj;
				$delta1 = $delta;
			}
			else if ($delta < $delta2)
			{
				$stars_obj2 = $stars_obj;
				$delta2 = $delta;
			}
		}
		
		// print_json($stars_obj1);
		// print_json($stars_obj2);
		
		if ($stars_obj1 != NULL)
		{
			if ($stars_obj2 != NULL)
			{
				$stars1 = isset($stars_obj1->stars) ? $stars_obj1->stars : 1;
				$stars2 = isset($stars_obj2->stars) ? $stars_obj2->stars : 1;
				if ($stars1 > $stars2)
				{
					$stars_obj = $stars_obj2;
					$stars_obj2 = $stars_obj1;
					$stars_obj1 = $stars_obj;
					
					$s = $stars2;
					$stars2 = $stars1;
					$stars1 = $s;
				}
				
				if ($stars < $stars1)
				{
					if (!isset($gaining->lessStars) || $gaining->lessStars == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->lessStars == 'closest')
					{
						$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
					}
					else if ($gaining->lessStars != 'extrapolate')
					{
						throw new Exc('Invalid lessStars value "' . $gaining->lessStars . '". Valid values are: "no", "closest", and "extrapolate".');
					}
				}
				else if ($stars < $stars2)
				{
					if (!isset($gaining->midStars) || $gaining->midStars == 'closest')
					{
						if (abs($stars1 - $stars) < abs($stars2 - $stars))
						{
							$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
						}
						else
						{
							$table = _create_gaining_table_for_stars($gaining, $stars_obj2, $players);
						}
					}
					else if ($gaining->midStars == 'min')
					{
						$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
					}
					else if ($gaining->midStars == 'max')
					{
						$table = _create_gaining_table_for_stars($gaining, $stars_obj2, $players);
					}
					else if ($gaining->midStars == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->midStars != 'interpolate')
					{
						throw new Exc('Invalid midStars value "' . $gaining->midStars . '". Valid values are: "no", "min", "max", "closest", and "interpolate".');
					}
				}
				else
				{
					if (!isset($gaining->moreStars) || $gaining->moreStars == 'no')
					{
						$table = _create_zero_gaining_table($stars, $players);
					}
					else if ($gaining->moreStars == 'closest')
					{
						$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
					}
					else if ($gaining->moreStars != 'extrapolate')
					{
						throw new Exc('Invalid moreStars value "' . $gaining->moreStars . '". Valid values are: "no", "closest", and "extrapolate".');
					}
				}
				
				if (is_null($table))
				{
					$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
					if ($stars1 != $stars2)
					{
						$table2 = _create_gaining_table_for_stars($gaining, $stars_obj2, $players);
						$table = _gaining_tables_regression($table, $table2, $stars, $players);
					}
				}
			}
			else
			{
				$table = _create_gaining_table_for_stars($gaining, $stars_obj1, $players);
			}
		}
		else if ($stars_obj2 != NULL)
		{
			$table = _create_gaining_table_for_stars($gaining, $stars_obj2, $players);
		}
	}
	
	if (is_null($table))
	{
		$table = _create_zero_gaining_table($stars, $players);
	}
	
	$points_pool = 0;
	if (isset($table->pointsPerPlayer))
	{
		// calculate sum of the points from the table
		$sum = 0;
		if (isset($table->points))
		{
			for ($i = 1; $i <= $players; ++$i)
			{
				$sum += get_gaining_points($table, $i, 0);
			}
		}
		$points_pool = max($table->pointsPerPlayer * $players - $sum, 0); // the remaining points to distribute between players based on their score
	}
	
	if ($points_pool > 0 && isset($gaining->useTournamentScore))
	{
		$table->pointsPool = $points_pool;
		$table->tournamentScorePower = max((int)$gaining->useTournamentScore, 0);
		$table->scoreSum = $score_sum;
	}
	
	if (isset($gaining->asIs))
	{
		$table->asIs = $gaining->asIs;
	}
	
	//echo formatted_json($table);
	return $table;
}

function api_scoring_help($param)
{
	$param->sub_param('Help on scoring json structure is not implemented yet.', '', '-');
}

function api_normalizer_help($param)
{
	$param->sub_param('Help on normalizer json structure is not implemented yet.', '', '-');
}

function api_gaining_help($param)
{
	$param->sub_param('Help on gaining json structure is not implemented yet.', '', '-');
}

?>
