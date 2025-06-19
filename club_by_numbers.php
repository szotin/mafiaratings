<?php

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/scoring.php';
require_once 'include/checkbox_filter.php';
require_once 'include/datetime.php';

define('SORT_TYPE_BY_NUMBERS', 0);
define('SORT_TYPE_BY_GAMES', 1);
define('SORT_TYPE_BY_WIN', 2);
define('SORT_TYPE_BY_RATING', 3);
define('SORT_TYPE_BY_WARNINGS', 4);
define('SORT_TYPE_BY_SHERIFF_CHECK', 5);
define('SORT_TYPE_BY_DON_CHECK', 6);
define('SORT_TYPE_BY_KILLED_NIGHT', 7);
define('SORT_TYPE_BY_KILLED_FIRST_NIGHT', 8);

define('FLAG_FILTER_TOURNAMENT', 0x0001);
define('FLAG_FILTER_NO_TOURNAMENT', 0x0002);
define('FLAG_FILTER_RATING', 0x0004);
define('FLAG_FILTER_NO_RATING', 0x0008);

define('FLAG_FILTER_DEFAULT', 0);

$sort_type = SORT_TYPE_BY_WIN * 2 + 1;
function compare_numbers($row1, $row2)
{
	global $sort_type;
	list($number1, $games1, $won1, $rating1, $warnings1, $sheriff_check1, $don_check1, $killed_first1, $killed_night1) = $row1;
	list($number2, $games2, $won2, $rating2, $warnings2, $sheriff_check2, $don_check2, $killed_first2, $killed_night2) = $row2;
	$desc = (($sort_type & 1) << 1) - 1;
	switch ($sort_type >> 1)
	{
		case SORT_TYPE_BY_NUMBERS:
			return $desc * ($number2 - $number1);
			
		case SORT_TYPE_BY_GAMES:
			return $desc * ($games2 - $games1);
			
		case SORT_TYPE_BY_WIN:
			$percent1 = $won1 / $games1;
			$percent2 = $won2 / $games2;
			if ($percent1 < $percent2)
			{
				return $desc;
			}
			else if ($percent1 > $percent2)
			{
				return -$desc;
			}
			break;
		
		case SORT_TYPE_BY_RATING:
			$rating_per_game1 = $rating1 / $games1;
			$rating_per_game2 = $rating2 / $games2;
			if ($rating_per_game1 < $rating_per_game2)
			{
				return $desc;
			}
			else if ($rating_per_game1 > $rating_per_game2)
			{
				return -$desc;
			}
			break;
			
		case SORT_TYPE_BY_WARNINGS:
			$warn_per_game1 = $warnings1 / $games1;
			$warn_per_game2 = $warnings2 / $games2;
			if ($warn_per_game1 < $warn_per_game2)
			{
				return $desc;
			}
			else if ($warn_per_game1 > $warn_per_game2)
			{
				return -$desc;
			}
			break;
			
		case SORT_TYPE_BY_SHERIFF_CHECK:
			$sheriff_check_per_game1 = $sheriff_check1 / $games1;
			$sheriff_check_per_game2 = $sheriff_check2 / $games2;
			if ($sheriff_check_per_game1 < $sheriff_check_per_game2)
			{
				return $desc;
			}
			else if ($sheriff_check_per_game1 > $sheriff_check_per_game2)
			{
				return -$desc;
			}
			break;
			
		case SORT_TYPE_BY_DON_CHECK:
			$don_check_per_game1 = $don_check1 / $games1;
			$don_check_per_game2 = $don_check2 / $games2;
			if ($don_check_per_game1 < $don_check_per_game2)
			{
				return $desc;
			}
			else if ($don_check_per_game1 > $don_check_per_game2)
			{
				return -$desc;
			}
			break;
			
		case SORT_TYPE_BY_KILLED_NIGHT:
			$killed_night_per_game1 = $killed_night1 / $games1;
			$killed_night_per_game2 = $killed_night2 / $games2;
			if ($killed_night_per_game1 < $killed_night_per_game2)
			{
				return $desc;
			}
			else if ($killed_night_per_game1 > $killed_night_per_game2)
			{
				return -$desc;
			}
			break;
			
		case SORT_TYPE_BY_KILLED_FIRST_NIGHT:
			$killed_first_per_game1 = $killed_first1 / $games1;
			$killed_first_per_game2 = $killed_first2 / $games2;
			if ($killed_first_per_game1 < $killed_first_per_game2)
			{
				return $desc;
			}
			else if ($killed_first_per_game1 > $killed_first_per_game2)
			{
				return -$desc;
			}
			break;
	}
	return $desc * ($games2 - $games1);
}

function sorting_link($ref, $sort, $text)
{
	global $sort_type;
	$result = '<a href="' . $ref . '&sort=';
	if (($sort_type >> 1) == $sort)
	{
		if ($sort_type & 1)
		{
			$result = '▼ <a href="javascript:goTo({sort:' . ($sort * 2) . '})">';
		}
		else
		{
			$result = '▲ <a href="javascript:goTo({sort:' . ($sort * 2 + 1) . '})">';
		}
	}
	else if ($sort_type & 1)
	{
		$result = '<a href="javascript:goTo({sort:' . ($sort * 2 + 1) . '})">';
	}
	else
	{
		$result = '<a href="javascript:goTo({sort:' . ($sort * 2) . '})">';
	}
	$result .= $text . '</a>';
	return $result;
}

class Page extends ClubPageBase
{
	protected function show_body()
	{
		global $sort_type;
		if (isset($_REQUEST['sort']))
		{
			$sort_type = (int)$_REQUEST['sort'];
		}
		
		$roles = POINTS_ALL;
		if (isset($_REQUEST['roles']))
		{
			$roles = (int)$_REQUEST['roles'];
		}
		
		$filter = FLAG_FILTER_DEFAULT;
		if (isset($_REQUEST['filter']))
		{
			$filter = (int)$_REQUEST['filter'];
		}
		
		echo '<table class="transp" width="100%"><tr><td>';
		show_roles_select($roles, 'filterChanged()', get_label('Use stats of a specific role.'), ROLE_NAME_FLAG_SINGLE);
		echo '&emsp;&emsp;';
		show_date_filter();
		echo '&emsp;&emsp;';
		show_checkbox_filter(array(get_label('tournament games'), get_label('rating games')), $filter, 'filterChanged');
		echo '</td></tr></table>';

		$numbers = array();
		$query = new DbQuery(
			'SELECT p.number, COUNT(*) as games, SUM(p.won) as won, SUM(p.rating_earned) as rating, SUM(p.warns) as warnings, SUM(IF(p.checked_by_sheriff < 0, 0, 1)) as sheriff_check, SUM(IF(p.checked_by_don < 0, 0, 1)) as don_check, SUM(IF(p.kill_round = 1 AND p.kill_type = ' . KILL_TYPE_NIGHT . ', 1, 0)) as killed_first, SUM(IF(p.kill_type = ' . KILL_TYPE_NIGHT . ', 1, 0)) as killed_night' .
			' FROM players p JOIN games g ON p.game_id = g.id WHERE g.club_id = ? AND (g.flags & '.GAME_FLAG_CANCELED.') = 0', $this->id);
		$query->add(get_roles_condition($roles));
		if ($filter & FLAG_FILTER_TOURNAMENT)
		{
			$query->add(' AND g.tournament_id IS NOT NULL');
		}
		if ($filter & FLAG_FILTER_NO_TOURNAMENT)
		{
			$query->add(' AND g.tournament_id IS NULL');
		}
		if ($filter & FLAG_FILTER_RATING)
		{
			$query->add(' AND (g.flags & '.GAME_FLAG_RATING.') <> 0');
		}
		if ($filter & FLAG_FILTER_NO_RATING)
		{
			$query->add(' AND (g.flags & '.GAME_FLAG_RATING.') = 0');
		}
		if (isset($_REQUEST['from']) && !empty($_REQUEST['from']))
		{
			$query->add(' AND g.start_time >= ?', get_datetime($_REQUEST['from'])->getTimestamp());
		}
		if (isset($_REQUEST['to']) && !empty($_REQUEST['to']))
		{
			$query->add(' AND g.start_time < ?', get_datetime($_REQUEST['to'])->getTimestamp() + 86200);
		}
		$query->add(' GROUP BY p.number');
		while ($row = $query->next())
		{
			$numbers[] = $row;
		}
		usort($numbers, "compare_numbers");
			
		$ref = 'club_by_numbers.php?id=' . $this->id;
		if ($roles != POINTS_ALL)
		{
			$ref .= '&roles=' . $roles;
		}
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th-long darker"><td>' . sorting_link($ref, SORT_TYPE_BY_NUMBERS, get_label('Number')) . '</td>';
		echo '<td width="80" align="center">' . sorting_link($ref, SORT_TYPE_BY_GAMES, get_label('Games played')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_WIN, get_label('Wins (%)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_RATING, get_label('Rating (per game)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_WARNINGS, get_label('Warnings (per game)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_SHERIFF_CHECK, get_label('Checked by sheriff (%)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_DON_CHECK, get_label('Checked by don (%)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_KILLED_NIGHT, get_label('Killed at night (%)')) . '</td>';
		echo '<td width="100" align="center">' . sorting_link($ref, SORT_TYPE_BY_KILLED_FIRST_NIGHT, get_label('Killed first night (%)')) . '</td>';
		echo '</tr>';
		
		$sum_games = $sum_won = $sum_rating = $sum_warnings = $sum_sheriff_check = $sum_don_check = $sum_killed_first = $sum_killed_night = 0;
		foreach ($numbers as $row)
		{
			list($number, $games, $won, $rating, $warnings, $sheriff_check, $don_check, $killed_first, $killed_night) = $row;
			$sum_games += $games;
			$sum_won += $won;
			$sum_rating += $rating;
			$sum_warnings += $warnings;
			$sum_sheriff_check += $sheriff_check;
			$sum_don_check += $don_check;
			$sum_killed_first += $killed_first;
			$sum_killed_night += $killed_night;
			
			echo '<tr>';
			echo '<td>' . $number . '</td>';
			echo '<td>' . $games . '</td>';
			echo '<td>' . $won . ' (' . format_rating($won*100/$games) . '%)</td>';
			echo '<td>' . format_rating($rating) . ' (' . format_rating($rating/$games) . ')</td>';
			echo '<td>' . $warnings . ' (' . format_rating($warnings/$games) . ')</td>';
			echo '<td>' . $sheriff_check . ' (' . format_rating($sheriff_check*100/$games) . '%)</td>';
			echo '<td>' . $don_check . ' (' . format_rating($don_check*100/$games) . '%)</td>';
			echo '<td>' . $killed_night . ' (' . format_rating($killed_night*100/$games) . '%)</td>';
			echo '<td>' . $killed_first . ' (' . format_rating($killed_first*100/$games) . '%)</td>';
			echo '</tr>';
		}
		
		if ($sum_games > 0)
		{
			echo '<tr class="darker">';
			echo '<td>' . get_label('Total') . '</td>';
			echo '<td>' . $sum_games . '</td>';
			echo '<td>' . $sum_won . ' (' . format_rating($sum_won*100/$sum_games) . '%)</td>';
			echo '<td>' . format_rating($sum_rating) . ' (' . format_rating($sum_rating/$sum_games) . ')</td>';
			echo '<td>' . $sum_warnings . ' (' . format_rating($sum_warnings/$sum_games) . ')</td>';
			echo '<td>' . $sum_sheriff_check . ' (' . format_rating($sum_sheriff_check*100/$sum_games) . '%)</td>';
			echo '<td>' . $sum_don_check . ' (' . format_rating($sum_don_check*100/$sum_games) . '%)</td>';
			echo '<td>' . $sum_killed_night . ' (' . format_rating($sum_killed_night*100/$sum_games) . '%)</td>';
			echo '<td>' . $sum_killed_first . ' (' . format_rating($sum_killed_first*100/$sum_games) . '%)</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	protected function js()
	{
?>		
		function filterChanged()
		{
			goTo({ roles: $('#roles').val(), filter: checkboxFilterFlags() });
		}
<?php
	}
}

$page = new Page();
$page->run(get_label('Stats by Numbers'));

?>