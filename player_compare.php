<?php

require_once 'include/user.php';
require_once 'include/player_stats.php';
require_once 'include/game_player.php';

function row($title, $value1, $value2, $count1, $count2, $value1_str = NULL, $value2_str = NULL)
{
//	$title = get_label($title);
	if ($value1_str == NULL)
	{
		$value1_str = $value1;
	}

	if ($value2_str == NULL)
	{
		$value2_str = $value2;
	}
	
	if ($count1 > 0)
	{
		if ($count2 > 0)
		{
			if ($value1 > $value2)
			{
				$marking = 1;
			}
			else if ($value1 < $value2)
			{
				$marking = 2;
			}
			else
			{
				$marking = 0;
			}
		}
		else
		{
			$marking = 1;
		}
	}
	else if ($count2 > 0)
	{
		$marking = 2;
	}
	else
	{
		$marking = 0;
	}
	
	echo '<tr><td class="dark">' . $title . ':</td>';
	switch ($marking)
	{
		case 1:
			echo '<td class="lighter">' . $value1_str . '</td><td class="light">' . $value2_str;
			break;
		case 2:
			echo '<td class="light">' . $value1_str . '</td><td class="lighter">' . $value2_str;
			break;
		default:
			echo '<td class="light">' . $value1_str . '</td><td class="light">' . $value2_str;
			break;
	}
	echo '</td></tr>';
}

function format_int($id, $value)
{
	if ($id <= 0)
	{
		return number_format($value, 2);
	}
	return $value;
}

function role_select($form_name, $role_name, $role, $together = false)
{
	echo '<select name="' . $role_name . '" onChange = "document.' . $form_name . '.submit()">';
	show_option(0, $role, get_label('Any role'));
	show_option(1, $role, get_label('Red'));
	show_option(2, $role, get_label('Black'));
	show_option(3, $role, get_label('Civilian'));
	show_option(4, $role, get_label('Sheriff'));
	show_option(5, $role, get_label('Mafiosi'));
	show_option(6, $role, get_label('Don'));
	if ($together)
	{
		show_option(7, $role, get_label('Played together'));
	}
	echo '</select>';
}

function get_player_info($id)
{
	if ($id > 0)
	{
		return Db::record(get_label('user'), 'SELECT name, flags FROM users WHERE id = ?', $id);
	}
	
	if ($id < 0)
	{
		list ($name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', -$id);
		$name = get_label('Average player of [0]', $name);
	}
	else
	{
		$name = get_label('Average player');
	}
	return array ($name, 0);
}

class Page extends UserPageBase
{
	private $id2;
	private $name2;
	private $flags2;

	private function standard_compare($role)
	{
		$mafia_role = ($role == (ROLE_MAFIA | ROLE_DON) ? -1 : 1);
		$stats1 = new PlayerStats($this->id, -1, $role);
		$stats2 = new PlayerStats($this->id2, -1, $role);
		
		$winning_percentage1 = 0;
		$rating_per_game1 = 0;
		$best_player_pecent1 = 0;
		$voted_civil1 = 0;
		$voted_mafia1 = 0;
		$voted_sheriff1 = 0;
		$voted_by1 = 0;
		$nominated_civil1 = 0;
		$nominated_mafia1 = 0;
		$nominated_sheriff1 = 0;
		$nominated_by1 = 0;
		$checked_by_sheriff1 = 0;
		$survived1 = 0;
		if ($stats1->games_played > 0)
		{
			$winning_percentage1 = $stats1->games_won * 100.0 / $stats1->games_played;
			$rating_per_game1 = $stats1->rating / $stats1->games_played;
			$best_player_pecent1 = $stats1->best_player * 100.0 / $stats1->games_played;
			$voted_civil1 = $stats1->voted_civil / $stats1->games_played;
			$voted_mafia1 = $stats1->voted_mafia / $stats1->games_played;
			$voted_sheriff1 = $stats1->voted_sheriff / $stats1->games_played;
			$voted_by1 = ($stats1->voted_by_civil + $stats1->voted_by_mafia + $stats1->voted_by_sheriff) / $stats1->games_played;
			$nominated_civil1 = $stats1->nominated_civil / $stats1->games_played;
			$nominated_mafia1 = $stats1->nominated_mafia / $stats1->games_played;
			$nominated_sheriff1 = $stats1->nominated_sheriff / $stats1->games_played;
			$nominated_by1 = ($stats1->nominated_by_civil + $stats1->nominated_by_mafia + $stats1->nominated_by_sheriff) / $stats1->games_played;
			$checked_by_sheriff1 = $stats1->checked_by_sheriff / $stats1->games_played;
			if (count($stats1->surviving) > 0)
			{
				$survived1 = $stats1->surviving[0]->count * 100.0 / $stats1->games_played;
			}
		}
		
		$winning_percentage2 = 0;
		$rating_per_game2 = 0;
		$best_player_pecent2 = 0;
		$voted_civil2 = 0;
		$voted_mafia2 = 0;
		$voted_sheriff2 = 0;
		$voted_by2 = 0;
		$nominated_civil2 = 0;
		$nominated_mafia2 = 0;
		$nominated_sheriff2 = 0;
		$nominated_by2 = 0;
		$checked_by_sheriff2 = 0;
		$survived2 = 0;
		if ($stats2->games_played > 0)
		{
			$winning_percentage2 = $stats2->games_won * 100.0 / $stats2->games_played;
			$rating_per_game2 = $stats2->rating / $stats2->games_played;
			$best_player_pecent2 = $stats2->best_player * 100.0 / $stats2->games_played;
			$voted_civil2 = $stats2->voted_civil / $stats2->games_played;
			$voted_mafia2 = $stats2->voted_mafia / $stats2->games_played;
			$voted_sheriff2 = $stats2->voted_sheriff / $stats2->games_played;
			$voted_by2 = ($stats2->voted_by_civil + $stats2->voted_by_mafia + $stats2->voted_by_sheriff) / $stats2->games_played;
			$nominated_civil2 = $stats2->nominated_civil / $stats2->games_played;
			$nominated_mafia2 = $stats2->nominated_mafia / $stats2->games_played;
			$nominated_sheriff2 = $stats2->nominated_sheriff / $stats2->games_played;
			$nominated_by2 = ($stats2->nominated_by_civil + $stats2->nominated_by_mafia + $stats2->nominated_by_sheriff) / $stats2->games_played;
			$checked_by_sheriff2 = $stats2->checked_by_sheriff / $stats2->games_played;
			if (count($stats2->surviving) > 0)
			{
				$survived2 = $stats2->surviving[0]->count * 100.0 / $stats2->games_played;
			}
		}
		
		row(get_label('Games played'), $stats1->games_played, $stats2->games_played, $stats1->games_played, $stats2->games_played, format_int($this->id, $stats1->games_played), format_int($this->id2, $stats2->games_played, 0));
		row(get_label('Best player'), $best_player_pecent1, $best_player_pecent2, $stats1->games_played, $stats2->games_played, number_format($best_player_pecent1, 1) . '%', number_format($best_player_pecent2, 1) . '%');
		row(get_label('Rating'), $stats1->rating, $stats2->rating, $stats1->games_played, $stats2->games_played, format_int($this->id, $stats1->rating), format_int($this->id2, $stats2->rating));
		row(get_label('Rating per game'), $rating_per_game1, $rating_per_game2, $stats1->games_played, $stats2->games_played, number_format($rating_per_game1, 2), number_format($rating_per_game2, 2));
		row(get_label('Winning rate'), $winning_percentage1, $winning_percentage2, $stats1->games_played, $stats2->games_played, number_format($winning_percentage1, 1) . '%', number_format($winning_percentage2, 1) . '%');
		row(get_label('Surviving rate'), $survived1, $survived2, $stats1->games_played, $stats2->games_played, number_format($survived1, 1) . '%', number_format($survived2, 1) . '%');
		if ($role != ROLE_ANY)
		{
			row(get_label('Votes against mafia'), $voted_mafia1 * $mafia_role, $voted_mafia2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($voted_mafia1, 2) . get_label(' per game'), number_format($voted_mafia2, 2) . get_label(' per game'));
			row(get_label('Votes against civilians'), -$voted_civil1 * $mafia_role, -$voted_civil2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($voted_civil1, 2) . get_label(' per game'), number_format($voted_civil2, 2) . get_label(' per game'));
			row(get_label('Votes against sheriff'), -$voted_sheriff1 * $mafia_role, -$voted_sheriff2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($voted_sheriff1, 2) . get_label(' per game'), number_format($voted_sheriff2, 2) . get_label(' per game'));
		}
		row(get_label('Voted by others'), -$voted_by1, -$voted_by2, $stats1->games_played, $stats2->games_played, number_format($voted_by1, 2) . get_label(' per game'), number_format($voted_by2, 2) . get_label(' per game'));
		if ($role != ROLE_ANY)
		{
			row(get_label('Nominates mafia'), $nominated_mafia1 * $mafia_role, $nominated_mafia2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($nominated_mafia1, 2) . get_label(' per game'), number_format($nominated_mafia2, 2) . get_label(' per game'));
			row(get_label('Nominates civilians'), -$nominated_civil1 * $mafia_role, -$nominated_civil2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($nominated_civil1, 2) . get_label(' per game'), number_format($nominated_civil2, 2) . get_label(' per game'));
			row(get_label('Nominates sheriff'), -$nominated_sheriff1 * $mafia_role, -$nominated_sheriff2 * $mafia_role, $stats1->games_played, $stats2->games_played, number_format($nominated_sheriff1, 2) . get_label(' per game'), number_format($nominated_sheriff2, 2) . get_label(' per game'));
		}
		row(get_label('Nominated by others'), -$nominated_by1, -$nominated_by2, $stats1->games_played, $stats2->games_played, number_format($nominated_by1, 2) . get_label(' per game'), number_format($nominated_by2, 2) . get_label(' per game'));
		if ($role != ROLE_SHERIFF)
		{
			row(get_label('Checked by sheriff'), -$checked_by_sheriff1, -$checked_by_sheriff2, $stats1->games_played, $stats2->games_played, number_format($checked_by_sheriff1, 2) . get_label(' per game'), number_format($checked_by_sheriff2, 2) . get_label(' per game'));
		}
	}

	private function mafia_compare($role)
	{
		$stats1 = new MafiaStats($this->id, -1, $role);
		$stats2 = new MafiaStats($this->id2, -1, $role);
		
		$shots_count1 = $stats1->shots1_ok + $stats1->shots1_miss + $stats1->shots2_ok + $stats1->shots2_miss + $stats1->shots3_ok + $stats1->shots3_miss;
		$shots_percent1 = 0;
		if ($shots_count1 > 0)
		{
			$shots_percent1 = ($stats1->shots1_ok + $stats1->shots2_ok + $stats1->shots3_ok) * 100.0 / $shots_count1;
		}
		$shots_per_game1 = 0;
		if ($stats1->games_played > 0)
		{
			$shots_per_game1 = $shots_count1 / $stats1->games_played;
		}
		
		$shots1_count1 = $stats1->shots1_ok + $stats1->shots1_miss;
		$shots1_percent1 = 0;
		if ($shots1_count1 > 0)
		{
			$shots1_percent1 = $stats1->shots1_ok * 100.0 / $shots1_count1;
		}
		$shots1_per_game1 = 0;
		if ($stats1->games_played > 0)
		{
			$shots1_per_game1 = $shots1_count1 / $stats1->games_played;
		}
		
		$shots2_count1 = $stats1->shots2_ok + $stats1->shots2_miss;
		$shots2_percent1 = 0;
		if ($shots2_count1 > 0)
		{
			$shots2_percent1 = $stats1->shots2_ok * 100.0 / $shots2_count1;
		}
		$shots2_per_game1 = 0;
		if ($stats1->games_played > 0)
		{
			$shots2_per_game1 = $shots2_count1 / $stats1->games_played;
		}
		
		$shots3_count1 = $stats1->shots3_ok + $stats1->shots3_miss;
		$shots3_percent1 = 0;
		if ($shots3_count1 > 0)
		{
			$shots3_percent1 = $stats1->shots3_ok * 100.0 / $shots3_count1;
		}
		$shots3_per_game1 = 0;
		if ($stats1->games_played > 0)
		{
			$shots3_per_game1 = $shots3_count1 / $stats1->games_played;
		}
		
		$shots_count2 = $stats2->shots1_ok + $stats2->shots1_miss + $stats2->shots2_ok + $stats2->shots2_miss + $stats2->shots3_ok + $stats2->shots3_miss;
		$shots_percent2 = 0;
		if ($shots_count2 > 0)
		{
			$shots_percent2 = ($stats2->shots1_ok + $stats2->shots2_ok + $stats2->shots3_ok) * 100.0 / $shots_count2;
		}
		$shots_per_game2 = 0;
		if ($stats2->games_played > 0)
		{
			$shots_per_game2 = $shots_count2 / $stats2->games_played;
		}
		
		$shots1_count2 = $stats2->shots1_ok + $stats2->shots1_miss;
		$shots1_percent2 = 0;
		if ($shots1_count2 > 0)
		{
			$shots1_percent2 = $stats2->shots1_ok * 100.0 / $shots1_count2;
		}
		$shots1_per_game2 = 0;
		if ($stats2->games_played > 0)
		{
			$shots1_per_game2 = $shots1_count2 / $stats2->games_played;
		}
		
		$shots2_count2 = $stats2->shots2_ok + $stats2->shots2_miss;
		$shots2_percent2 = 0;
		if ($shots2_count2 > 0)
		{
			$shots2_percent2 = $stats2->shots2_ok * 100.0 / $shots2_count2;
		}
		$shots2_per_game2 = 0;
		if ($stats2->games_played > 0)
		{
			$shots2_per_game2 = $shots2_count2 / $stats2->games_played;
		}
		
		$shots3_count2 = $stats2->shots3_ok + $stats2->shots3_miss;
		$shots3_percent2 = 0;
		if ($shots3_count2 > 0)
		{
			$shots3_percent2 = $stats2->shots3_ok * 100.0 / $shots3_count2;
		}
		$shots3_per_game2 = 0;
		if ($stats2->games_played > 0)
		{
			$shots3_per_game2 = $shots3_count2 / $stats2->games_played;
		}
		
		row(get_label('Mafia shooting'), $shots_per_game1, $shots_per_game2, $stats1->games_played, $stats2->games_played, number_format($shots_per_game1, 2) . get_label(' per game'), number_format($shots_per_game2, 2) . get_label(' per game'));
		row(get_label('Mafia shooting success'), $shots_percent1, $shots_percent2, $shots_count1, $shots_count2, number_format($shots_percent1, 1) . '%', number_format($shots_percent2, 1) . '%');
		row(get_label('1 mafia shooter'), $shots1_per_game1, $shots1_per_game2, $stats1->games_played, $stats2->games_played, number_format($shots1_per_game1, 2) . get_label(' per game'), number_format($shots1_per_game2, 2) . get_label(' per game'));
		row(get_label('1 mafia shooter success'), $shots1_percent1, $shots1_percent2, $shots1_count1, $shots1_count2, number_format($shots1_percent1, 1) . '%', number_format($shots1_percent2, 1) . '%');
		row(get_label('2 mafia shooters'), $shots2_per_game1, $shots2_per_game2, $stats1->games_played, $stats2->games_played, number_format($shots2_per_game1, 2) . get_label(' per game'), number_format($shots2_per_game2, 2) . get_label(' per game'));
		row(get_label('2 mafia shooters success'), $shots2_percent1, $shots2_percent2, $shots2_count1, $shots2_count2, number_format($shots2_percent1, 1) . '%', number_format($shots2_percent2, 1) . '%');
		row(get_label('3 mafia shooters'), $shots3_per_game1, $shots3_per_game2, $stats1->games_played, $stats2->games_played, number_format($shots3_per_game1, 2) . get_label(' per game'), number_format($shots3_per_game2, 2) . get_label(' per game'));
		row(get_label('3 mafia shooters success'), $shots3_percent1, $shots3_percent2, $shots3_count1, $shots3_count2, number_format($shots3_percent1, 1) . '%', number_format($shots3_percent2, 1) . '%');
	}

	private function sheriff_compare()
	{
		$stats1 = new SheriffStats($this->id, -1);
		$stats2 = new SheriffStats($this->id2, -1);
		
		$checks_count1 = $stats1->civil_found + $stats1->mafia_found;
		$checks_per_game1 = 0;
		if ($stats1->games_played)
		{
			$checks_per_game1 = $checks_count1 / $stats1->games_played;
		}
		$black_checks1 = 0;
		if ($checks_count1 > 0)
		{
			$black_checks1 = $stats1->mafia_found * 100.0 / $checks_count1;
		}
		
		$checks_count2 = $stats2->civil_found + $stats2->mafia_found;
		$checks_per_game2 = 0;
		if ($stats2->games_played)
		{
			$checks_per_game2 = $checks_count2 / $stats2->games_played;
		}
		$black_checks2 = 0;
		if ($checks_count2 > 0)
		{
			$black_checks2 = $stats2->mafia_found * 100.0 / $checks_count2;
		}
		
		row(get_label('Checks'), $checks_per_game1, $checks_per_game2, $stats1->games_played, $stats2->games_played, number_format($checks_per_game1, 2) . get_label(' per game'), number_format($checks_per_game2, 2) . get_label(' per game'));
		row(get_label('Mafia checks'), $black_checks1, $black_checks2, $checks_count1, $checks_count2, number_format($black_checks1, 1) . '%', number_format($black_checks2, 1) . '%');
	}

	private function pvp_compare()
	{
		global $_REQUEST;

		$role1 = -1;
		if (isset($_REQUEST['role1']))
		{
			$role1 = $_REQUEST['role1'];
		}

		$role2 = -1;
		if (isset($_REQUEST['role2']))
		{
			$role2 = $_REQUEST['role2'];
		}
		
		$query = new DbQuery(
			'SELECT count(*),' .
			' SUM(p1.rating_earned), SUM(p2.rating_earned),' .
			' SUM(IF(p1.rating_earned > 0, 1, 0)), SUM(IF(p2.rating_earned > 0, 1, 0)),' .
			' SUM(IF(p1.kill_type = 0, 1, 0)), SUM(IF(p2.kill_type = 0, 1, 0)),' .
			' SUM(p1.voted_by_civil + p1.voted_by_mafia + p1.voted_by_sheriff), SUM(p2.voted_by_civil + p2.voted_by_mafia + p2.voted_by_sheriff),' .
			' SUM(p1.nominated_by_civil + p1.nominated_by_mafia + p1.nominated_by_sheriff), SUM(p2.nominated_by_civil + p2.nominated_by_mafia + p2.nominated_by_sheriff),' .
			' SUM(IF(p1.checked_by_sheriff >= 0, 1, 0)), SUM(IF(p2.checked_by_sheriff >= 0, 1, 0)),' .
			' SUM(IF(g.best_player_id = p1.user_id, 1, 0)), SUM(IF(g.best_player_id = p2.user_id, 1, 0))' .
			' FROM players p1, players p2, games g' .
			' WHERE g.id = p1.game_id AND g.id = p2.game_id AND p1.user_id = ? AND p2.user_id = ?', $this->id, $this->id2);
			
		switch ($role1)
		{
			case 1: // Red player
				$query->add(' AND p1.role <= ' . PLAYER_ROLE_SHERIFF);
				break;
			case 2: // Dark player
				$query->add(' AND p1.role >= ' . PLAYER_ROLE_MAFIA);
				break;
			case 3: // Civilian
				$query->add(' AND p1.role = ' . PLAYER_ROLE_CIVILIAN);
				break;
			case 4: // Sheriff
				$query->add(' AND p1.role = ' . PLAYER_ROLE_SHERIFF);
				break;
			case 5: // Mafiosi
				$query->add(' AND p1.role = ' . PLAYER_ROLE_MAFIA);
				break;
			case 6: // Don
				$query->add(' AND p1.role = ' . PLAYER_ROLE_DON);
				break;
		}
			
		switch ($role2)
		{
			case 1: // Red player
				$query->add(' AND p2.role <= ' . PLAYER_ROLE_SHERIFF);
				break;
			case 2: // Dark player
				$query->add(' AND p2.role >= ' . PLAYER_ROLE_MAFIA);
				break;
			case 3: // Civilian
				$query->add(' AND p2.role = ' . PLAYER_ROLE_CIVILIAN);
				break;
			case 4: // Sheriff
				$query->add(' AND p2.role = ' . PLAYER_ROLE_SHERIFF);
				break;
			case 5: // Mafiosi
				$query->add(' AND p2.role = ' . PLAYER_ROLE_MAFIA);
				break;
			case 6: // Don
				$query->add(' AND p2.role = ' . PLAYER_ROLE_DON);
				break;
		}
		
		list (
			$games_played, $rating1, $rating2, $won1, $won2,
			$survived1, $survived2, $voted_by1, $voted_by2,
			$nominated_by1, $nominated_by2, $sheriff_check1, $sheriff_check2,
			$best_player1, $best_player2) = $query->record(get_label('player'));
	
		echo '<form method="get" name="roleform" action="player_compare.php">';
		echo '<input type="hidden" name="id1" value="' . $this->id . '">';
		echo '<input type="hidden" name="id2" value="' . $this->id2 . '">';
		echo '<input type="hidden" name="view" value="7">';
		
		echo '<tr class="darker"><td>&nbsp</td><td width="280">' . cut_long_name($this->name, 40) . '&nbsp&nbsp';
		role_select('roleform', 'role1', $role1);
		echo '</td><td width="280">' . cut_long_name($this->name2, 40) . '&nbsp&nbsp';
		role_select('roleform', 'role2', $role2);
		echo '</td></tr></form>';
		
		echo '<tr class="darker"><td>'.get_label('Games played').':</td><td colspan="2" align="center">' . $games_played . '</td></tr>';
		if ($games_played > 0)
		{
			row(get_label('Victories'), $won1, $won2, $games_played, $games_played);
			row(get_label('Rating'), $rating1, $rating2, $games_played, $games_played);
			row(get_label('Best player'), $best_player1, $best_player2, $games_played, $games_played);
			row(get_label('Survived'), $survived1, $survived2, $games_played, $games_played);
			row(get_label('Voted by others'), -$voted_by1, -$voted_by2, $games_played, $games_played, $voted_by1, $voted_by2);
			row(get_label('Nominated by others'), -$nominated_by1, -$nominated_by2, $games_played, $games_played, $nominated_by1, $nominated_by2);
			row(get_label('Checked by sheriff'), -$sheriff_check1, -$sheriff_check2, $games_played, $games_played, $sheriff_check1, $sheriff_check2);
		}
	}

	//-----------------------------------------------------------------------------------------------
	// Page Implementation
	//-----------------------------------------------------------------------------------------------
	protected function prepare()
	{
		$this->id = 0;
		if (isset($_REQUEST['id1']))
		{
			$this->id = $_REQUEST['id1'];
		}

		$this->id2 = 0;
		if (isset($_REQUEST['id2']))
		{
			$this->id2 = $_REQUEST['id2'];
		}

		list ($this->name, $this->flags) = get_player_info($this->id);
		list ($this->name2, $this->flags2) = get_player_info($this->id2);
	
		$this->_title = get_label('Comparing [0] with [1]', cut_long_name($this->name, 20), cut_long_name($this->name2, 20));
	}
	
	protected function show_body()
	{
		$view = 0;
		if (isset($_REQUEST['view']))
		{
			$view = $_REQUEST['view'];
		}
		
		echo '<form method="get" name="form" action="player_compare.php">';
		echo '<table class="transp" width="100%"><tr><td>';
		echo '<input type="hidden" name="id1" value="' . $this->id . '">';
		echo '<input type="hidden" name="id2" value="' . $this->id2 . '">';
		role_select('form', 'view', $view, $this->id > 0 && $this->id2 > 0);
		echo '</td><td align="right"><a href="player_compare_select.php?id=' . $this->id . '">'.get_label('Compare with another player').'</a></td></tr></table></form>';

		switch ($view)
		{
			case 1: // red
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_CIVIL | ROLE_SHERIFF);
				echo '</table>';
				break;
			case 2: // dark
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_MAFIA | ROLE_DON);
				$this->mafia_compare(ROLE_MAFIA | ROLE_DON);
				echo '</table>';
				break;
			case 3: // civil
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_CIVIL);
				echo '</table>';
				break;
			case 4: // sheriff
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_SHERIFF);
				$this->sheriff_compare();
				echo '</table>';
				break;
			case 5: // mafia
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_MAFIA);
				$this->mafia_compare(ROLE_MAFIA);
				echo '</table>';
				break;
			case 6: // don
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_DON);
				$this->mafia_compare(ROLE_DON);
				echo '</table>';
				break;
			case 7: // player vs player
				if ($this->id > 0 && $this->id2 > 0)
				{
					echo '<table class="bordered" width="100%">';
					$this->pvp_compare();
					echo '</table>';
					break;
				}
				
			case 0:
			default:
				
				echo '<table class="bordered" width="100%">';
				echo '<tr class="darker"><td>&nbsp;</td><td width="280">' . cut_long_name($this->name, 40) . '</td><td width="280">' . cut_long_name($this->name2, 40) . '</td></tr>';
				$this->standard_compare(ROLE_ANY);
				echo '</table>';
				break;
		}
	}
}

$page = new Page();
$page->run(get_label('Compare players'), PERM_ALL);

?>