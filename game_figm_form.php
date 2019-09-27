<?php
require_once 'include/session.php';
require_once 'include/game_state.php';
require_once 'include/pdf/tfpdf.php';

// define('A4_MAX_X', 297);
// define('A4_MAX_Y', 210);

initiate_session();

try
{
	$pdf = new tFPDF();
	$pdf->AddFont('Timesbd','','timesbd.ttf',true);
	$pdf->AddFont('Times','','times.ttf',true);
	$pdf->AddFont('Arial','','arial.ttf', true);
	$pdf->SetAutoPageBreak(false);

	$game_id = 0;
	if (isset($_REQUEST['game_id']))
	{
		$game_id = (int)$_REQUEST['game_id'];
	}
	
	if ($game_id > 0)
	{
		list ($round_name, $event_name, $game_log, $start_time, $is_canceled, $timezone, $moder_name) = Db::record(get_label('game'), 
			'SELECT r.name, e.name, g.log, g.start_time, g.canceled, c.timezone, u.name FROM games g' .
			' JOIN events e ON e.id = g.event_id' .
			' JOIN addresses a ON a.id = e.address_id' .
			' JOIN cities c ON c.id = a.city_id' .
			' JOIN users u ON u.id = g.moderator_id' .
			' LEFT OUTER JOIN rounds r ON r.event_id = g.event_id AND r.num = g.round_num' .
			' WHERE g.id = ?', $game_id);
		
		$gs = new GameState();
		$gs->init_existing($game_id, $game_log, $is_canceled);
	}
	
	// First page template
	$pdf->AddPage('L', 'A4');
	$pdf->SetFont('Timesbd', '', 12);

	$pdf->SetXY(0, 0);
	$pdf->Cell(297, 210, '', 1, 2);

	$pdf->SetXY(18.0, 17.4);
	$pdf->Cell(127, 7.7, 'ТУРНИР', 1, 2);
	$pdf->Cell(127, 7.7, 'СТАДИЯ ТУРНИРА', 1, 2);
	$pdf->Cell(13.5, 7.7, 'ДАТА', 'LTB'); 
	$pdf->SetTextColor(128);
	$pdf->Cell(50.5, 7.7, '____ / _____ / _________', 'RTB');
	$pdf->SetTextColor(0);
	$pdf->Cell(18.5, 7.7, 'СТОЛ №', 'TB');
	$pdf->SetTextColor(128);
	$pdf->Cell(13.5, 7.7, '____', 'RTB');
	$pdf->SetTextColor(0);
	$pdf->Cell(17.5, 7.7, 'ИГРА №', 'TB');
	$pdf->SetTextColor(128);
	$pdf->Cell(13.5, 7.7, '____', 'RTB');
	$pdf->SetTextColor(0);

	$pdf->SetFont('Times', '', 12);
	$pdf->SetXY(18.0, 45.3);
	$pdf->Cell(61.9, 6.9, 'Игрок', 1, 0, 'C');
	$pdf->SetFont('Times', '', 8);
	$pdf->Cell(10.1, 6.9, 'Роль', 1, 0, 'C');
	$pdf->SetFont('Times', '', 12);
	$pdf->Cell(29.9, 6.9, 'Фолы', 1, 0, 'C');
	$pdf->SetFont('Times', '', 8);
	$pdf->Cell(11.9, 6.9, 'Баллы', 1, 0, 'C');
	$pdf->MultiCell(13.2, 3.45, "Доп. баллы", 1, 'C');

	$pdf->SetFont('Timesbd', '', 10);
	$y = 52.2;
	for ($i = 1; $i <= 10; ++$i, $y += 10.1)
	{
		$pdf->SetXY(18.0, $y);
		$pdf->Cell(7.9, 10.1, '' . $i, 1, 0, 'C');
		$pdf->Cell(54.0, 10.1, '', 1);
		$pdf->Cell(10.1, 10.1, '', 1);
		$pdf->Cell(7.475, 10.1, '', 1);
		$pdf->Cell(7.475, 10.1, '', 1);
		$pdf->Cell(7.475, 10.1, '', 1);
		$pdf->Cell(7.475, 10.1, '', 1);
		$pdf->Cell(11.9, 10.1, '', 1);
		$pdf->Cell(13.2, 10.1, '', 1);
	}

	$pdf->SetFont('Timesbd', '', 12);
	$pdf->SetXY(18.0, 157.8);
	$pdf->Cell(127, 7.4, 'ПОБЕДИВШАЯ КОМАНДА', 1, 2);

	$pdf->Cell(34.4, 10.1, 'ЛУЧШИЙ ХОД', 'LTB');
	$pdf->SetTextColor(128);
	$pdf->Cell(55.1, 10.1, '____ , ____ , ____', 'TB');
	$pdf->SetTextColor(0);
	$pdf->Cell(19.9, 10.1, 'Игрок №', 'TB');
	$pdf->SetTextColor(128);
	$pdf->Cell(17.6, 10.1, '_____', 'RTB', 2);
	$pdf->SetTextColor(0);
	$pdf->SetX(18.0);

	$pdf->Cell(127, 7.1, 'ПРОТЕСТ', 1, 2);
	$pdf->Cell(16.4, 10.1, 'СУДЬЯ', 'LTB');
	$pdf->SetTextColor(128);
	$pdf->Cell(110.6, 10.1, '_______________________________ / _________________', 'RTB', 2);
	$pdf->SetTextColor(0);

	$y = 17.5;
	for ($i = 1; $i < 8; ++$i, $y += 15.0)
	{
		$x = 157.2;
		$pdf->SetFont('Times', '', 12);
		$pdf->SetXY(157.2, $y);
		$pdf->Cell(122.6, 5.0, 'Голосование ' . $i, 1, 2, 'C');
		$y += 5.0;
		
		$pdf->SetFont('Times', '', 8);
		$pdf->Cell(10.4, 5.0, 'Игрок', 1, 2, 'C');
		$pdf->Cell(10.4, 5.0, 'Голоса', 1, 2, 'C');
		$pdf->MultiCell(10.4, 5.0, "Пере-\nголос.", 1, 'C');
		$x += 10.4;
		
		for ($j = 0; $j < 11; ++$j, $x += 10.2)
		{
			$pdf->SetXY($x, $y);
			for ($k = 0; $k < 4; ++$k)
			{
				$pdf->Cell(10.2, 5.0, '', 1, 2, 'C');
			}
		}
		
		$y += 5.0;
	}
	
	// First page data
	if ($game_id > 0)
	{
		$day = date('d', $start_time);
		$month = date('m', $start_time);
		$year = date('Y', $start_time);
		
		$pdf->SetTextColor(0, 0, 128);
		$pdf->SetFont('Arial', '', 12);
		$pdf->SetXY(43.0, 17.4);
		$pdf->Cell(102, 7.7, $event_name, 0, 0, 'C');
		if (!is_null($round_name))
		{
			$pdf->SetXY(65.0, 25.1);
			$pdf->Cell(80, 7.7, $round_name, 0, 0, 'C');
		}
		$pdf->SetXY(33.0, 32.8);
		$pdf->Cell(8.5, 7.7, $day, 0); 
		$pdf->SetXY(45.0, 32.8);
		$pdf->Cell(8.5, 7.7, $month, 0); 
		$pdf->SetXY(59.0, 32.8);
		$pdf->Cell(18.5, 7.7, $year, 0);
		
		$killed_first = 0;
		$y = 52.2;
		$extra_point_comments = '';
		for ($i = 0; $i < 10; ++$i, $y += 10.1)
		{
			$player = $gs->players[$i];
			$role = '';
			$points = 0;
			$extra_points = $player->extra_points;
			switch ($player->role)
			{
				case PLAYER_ROLE_CIVILIAN:
					if ($gs->gamestate == GAME_CIVIL_WON)
					{
						$points = 1;
					}
					break;
				case PLAYER_ROLE_SHERIFF:
					if ($gs->gamestate == GAME_CIVIL_WON)
					{
						$points = 1;
					}
					$role = 'Ш';
					break;
				case PLAYER_ROLE_MAFIA:
					if ($gs->gamestate == GAME_MAFIA_WON)
					{
						$points = 1;
					}
					$role = 'М';
					break;
				case PLAYER_ROLE_DON:
					if ($gs->gamestate == GAME_MAFIA_WON)
					{
						$points = 1;
					}
					$role = 'Д';
					break;
			}
			
			if ($player->state == PLAYER_STATE_KILLED_NIGHT && $player->kill_reason == KILL_REASON_NORMAL && $player->kill_round == 0)
			{
				$killed_first = $i + 1;
				$guess_right = 0;
				if ($gs->guess3 != NULL)
				{
					foreach ($gs->guess3 as $num)
					{
						if ($gs->players[$num]->role >= PLAYER_ROLE_MAFIA)
						{
							++$guess_right;
						}
					}
				}
				if ($guess_right >= 3)
				{
					$extra_points += 0.4;
				}
				else if ($guess_right >= 2)
				{
					$extra_points += 0.25;
				}
			}
			
			switch ($player->kill_reason)
			{
				case KILL_REASON_WARNINGS:
					$extra_points -= 0.5;
					break;
				case KILL_REASON_KICK_OUT:
					$extra_points -= 0.5;
					$pdf->SetXY(112.425, $y);
					$pdf->Cell(7.475, 10.1, '!', 0, 0, 'C'); // √
					break;
			}
			
			if ($player->state != PLAYER_STATE_ALIVE)
			{
				$pdf->SetXY(14.0, $y);
				$pdf->Cell(4, 10.1, '*', 0);
			}
			$pdf->SetXY(25.9, $y);
			$pdf->Cell(54.0, 10.1, $player->nick, 0);
			$pdf->Cell(10.1, 10.1, $role, 0, 0, 'C');
			for ($j = 0; $j < $player->warnings; ++$j)
			{
				$pdf->Cell(7.475, 10.1, '*', 0, 0, 'C'); // √
			}
			$pdf->SetX(119.9);
			$pdf->Cell(11.9, 10.1, '' . $points, 0, 0, 'C');
			if ($extra_points != 0)
			{
				$pdf->Cell(13.2, 10.1, '' . $extra_points, 0, 0, 'C');
			}
			
			if (!empty($player->extra_points_reason))
			{
				if (!empty($extra_point_comments))
				{
					$extra_point_comments .= "\n";
				}
				$extra_point_comments .= 'Игрок ' . ($i + 1) . ': ' . $player->extra_points_reason;
			}
		}
		
		$pdf->SetXY(80, 157.8);
		switch ($gs->gamestate)
		{
			case GAME_MAFIA_WON:
				$pdf->Cell(65, 7.4, 'черные', 0, 0, 'C');
				break;
			case GAME_CIVIL_WON:
				$pdf->Cell(65, 7.4, 'красные', 0, 0, 'C');
				break;
		}

		if ($killed_first > 0)
		{
			$pdf->SetXY(128.5, 165.2);
			$pdf->Cell(10.5, 10.1, '' . $killed_first, 0, 0, 'C');
			if ($gs->guess3 != NULL)
			{
				$x = 54.0;
				for ($i = 0; $i < count($gs->guess3) && $i < 3; ++$i, $x += 11.5)
				{
					$pdf->SetXY($x, 165.2);
					$pdf->Cell(8.0, 10.1, '' . ($gs->guess3[$i] + 1), 0, 0, 'C');
				}
			}
		}
		
		$pdf->SetXY(35.0, 182.4);
		$pdf->Cell(66, 10.1, $moder_name, 0, 0, 'C');
		
		$pdf->SetFont('Arial', '', 10);
        foreach ($gs->votings as $voting)
        {
			if ($voting->round >= 7 || $voting->is_canceled())
			{
				continue;
			}
			
			$x = 167.6;
			for ($i = 0; $i < count($voting->nominants); ++$i)
			{
				$nominant = $voting->nominants[$i];
				$count = 0;
				foreach ($voting->votes as $vote)
				{
					if ($vote == $i)
					{
						++$count;
					}
				}
				
				$nom_start = '';
				$nom_end = '';
				$p = $gs->players[$nominant->player_num]; 
				if ($p->kill_round == $voting->round && $p->kill_reason == KILL_REASON_NORMAL && $p->state == PLAYER_STATE_KILLED_DAY)
				{
					$nom_start = '(';
					$nom_end = ')';
				}
				
				$pdf->SetXY($x, 22.5 + 10.0 * $voting->voting_round + 25.0 * $voting->round);
				$pdf->Cell(10.2, 5.0, $nom_start . ($nominant->player_num + 1) . $nom_end, 0, 2, 'C');
				if ($voting->round != 0 || count($voting->nominants) > 1)
				{
					$pdf->Cell(10.2, 5.0, '' . $count, 0, 2, 'C');
				}
				$x += 10.2;
			}
        	// $y = 17.5;
			// for ($i = 1; $i < 8; ++$i, $y += 15.0)
			// {
				// $x = 157.2;
				// $pdf->SetFont('Times', '', 12);
				// $pdf->SetXY(157.2, $y);
				// $pdf->Cell(122.6, 5.0, 'Голосование ' . $i, 1, 2, 'C');
				// $y += 5.0;
		}
		
		$pdf->SetTextColor(0);
	}

	// Second page template
	$pdf->AddPage('L', 'A4');

	$y = 25.2;
	for ($i = 0; $i < 14; ++$i, $y += 8)
	{
		$pdf->Line(9.9, $y, 288.7, $y);
	}

	$y = 148.7;
	for ($i = 0; $i < 7; ++$i, $y += 8)
	{
		$pdf->Line(9.9, $y, 288.7, $y);
	}

	$pdf->SetFont('Times', '', 12);
	$pdf->SetXY(9.9, 7.5);
	$pdf->Cell(278.8, 16, 'ПОЯСНЕНИЯ К ДОПОЛНИТЕЛЬНЫМ БАЛЛАМ И ШТРАФАМ', 0, 0, 'C');
	$pdf->SetXY(9.9, 129.0);
	$pdf->Cell(278.8, 16, 'КОММЕНТАРИИ К ПРОТЕСТУ', 0, 0, 'C');
	
	if ($game_id > 0)
	{
		if (!empty($extra_point_comments))
		{
			$pdf->SetTextColor(0, 0, 128);
			$pdf->SetFont('Arial', '', 10);
			$pdf->SetXY(10.9, 18.5);
			$pdf->MultiCell(276.8, 8, $extra_point_comments);
		}
	}

	$pdf->Output();
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<h1>' . get_label('Error') . '</h1><p>' . $e->getMessage() . '</p>';
}


?>
