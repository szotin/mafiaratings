<?php
require_once __DIR__ . '/game.php';
require_once __DIR__ . '/pdf/tfpdf.php';

// define('A4_MAX_X', 297);
// define('A4_MAX_Y', 210);

class FiimForm
{
	private $pdf;
	
	public function __construct()
	{
		$this->pdf = new tFPDF();
		$this->pdf->AddFont('Timesbd','','timesbd.ttf',true);
		$this->pdf->AddFont('Times','','times.ttf',true);
		$this->pdf->AddFont('Arial','','arial.ttf', true);
		$this->pdf->SetAutoPageBreak(false);
	}
	
	public function output()
	{
		$this->pdf->Output();
	}

	public function add($game, $event_name, $tournament_name, $moder_name, $timezone)
	{
		$round_name = '';
		if (!is_null($tournament_name))
		{
			$round_name = $event_name;
			$event_name = $tournament_name;
		}
		
		// First page template
		$this->pdf->AddPage('L', 'A4');
		$this->pdf->SetFont('Timesbd', '', 12);

		$this->pdf->SetXY(0, 0);
		$this->pdf->Cell(297, 210, '', 1, 2);

		$this->pdf->SetXY(18.0, 17.4);
		$this->pdf->Cell(127, 7.7, 'ТУРНИР', 1, 2);
		$this->pdf->Cell(127, 7.7, 'СТАДИЯ ТУРНИРА', 1, 2);
		$this->pdf->Cell(13.5, 7.7, 'ДАТА', 'LTB'); 
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(50.5, 7.7, '____ / _____ / _________', 'RTB');
		$this->pdf->SetTextColor(0);
		$this->pdf->Cell(18.5, 7.7, 'СТОЛ №', 'TB');
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(13.5, 7.7, '____', 'RTB');
		$this->pdf->SetTextColor(0);
		$this->pdf->Cell(17.5, 7.7, 'ИГРА №', 'TB');
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(13.5, 7.7, '____', 'RTB');
		$this->pdf->SetTextColor(0);

		$this->pdf->SetFont('Times', '', 12);
		$this->pdf->SetXY(18.0, 45.3);
		$this->pdf->Cell(61.9, 6.9, 'Игрок', 1, 0, 'C');
		$this->pdf->SetFont('Times', '', 8);
		$this->pdf->Cell(10.1, 6.9, 'Роль', 1, 0, 'C');
		$this->pdf->SetFont('Times', '', 12);
		$this->pdf->Cell(29.9, 6.9, 'Фолы', 1, 0, 'C');
		$this->pdf->SetFont('Times', '', 8);
		$this->pdf->Cell(11.9, 6.9, 'Баллы', 1, 0, 'C');
		$this->pdf->MultiCell(13.2, 3.45, "Доп. баллы", 1, 'C');

		$this->pdf->SetFont('Timesbd', '', 10);
		$y = 52.2;
		for ($i = 1; $i <= 10; ++$i, $y += 10.1)
		{
			$this->pdf->SetXY(18.0, $y);
			$this->pdf->Cell(7.9, 10.1, '' . $i, 1, 0, 'C');
			$this->pdf->Cell(54.0, 10.1, '', 1);
			$this->pdf->Cell(10.1, 10.1, '', 1);
			$this->pdf->Cell(7.475, 10.1, '', 1);
			$this->pdf->Cell(7.475, 10.1, '', 1);
			$this->pdf->Cell(7.475, 10.1, '', 1);
			$this->pdf->Cell(7.475, 10.1, '', 1);
			$this->pdf->Cell(11.9, 10.1, '', 1);
			$this->pdf->Cell(13.2, 10.1, '', 1);
		}

		$this->pdf->SetFont('Timesbd', '', 12);
		$this->pdf->SetXY(18.0, 157.8);
		$this->pdf->Cell(127, 7.4, 'ПОБЕДИВШАЯ КОМАНДА', 1, 2);

		$this->pdf->Cell(34.4, 10.1, 'ЛУЧШИЙ ХОД', 'LTB');
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(55.1, 10.1, '____ , ____ , ____', 'TB');
		$this->pdf->SetTextColor(0);
		$this->pdf->Cell(19.9, 10.1, 'Игрок №', 'TB');
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(17.6, 10.1, '_____', 'RTB', 2);
		$this->pdf->SetTextColor(0);
		$this->pdf->SetX(18.0);

		$this->pdf->Cell(127, 7.1, 'ПРОТЕСТ', 1, 2);
		$this->pdf->Cell(16.4, 10.1, 'СУДЬЯ', 'LTB');
		$this->pdf->SetTextColor(128);
		$this->pdf->Cell(110.6, 10.1, '_______________________________ / _________________', 'RTB', 2);
		$this->pdf->SetTextColor(0);

		$y = 17.5;
		for ($i = 1; $i < 8; ++$i, $y += 15.0)
		{
			$x = 157.2;
			$this->pdf->SetFont('Times', '', 12);
			$this->pdf->SetXY(157.2, $y);
			$this->pdf->Cell(122.6, 5.0, 'Голосование ' . $i, 1, 2, 'C');
			$y += 5.0;
			
			$this->pdf->SetFont('Times', '', 8);
			$this->pdf->Cell(10.4, 5.0, 'Игрок', 1, 2, 'C');
			$this->pdf->Cell(10.4, 5.0, 'Голоса', 1, 2, 'C');
			$this->pdf->MultiCell(10.4, 5.0, "Пере-\nголос.", 1, 'C');
			$x += 10.4;
			
			for ($j = 0; $j < 11; ++$j, $x += 10.2)
			{
				$this->pdf->SetXY($x, $y);
				for ($k = 0; $k < 4; ++$k)
				{
					$this->pdf->Cell(10.2, 5.0, '', 1, 2, 'C');
				}
			}
			
			$y += 5.0;
		}
		
		// First page data
		if (!is_null($game))
		{
			$data = $game->data;
			date_default_timezone_set($timezone);
			$day = date('d', $data->startTime);
			$month = date('m', $data->startTime);
			$year = date('Y', $data->startTime);
			
			$this->pdf->SetTextColor(0, 0, 128);
			$this->pdf->SetFont('Arial', '', 12);
			$this->pdf->SetXY(43.0, 17.4);
			$this->pdf->Cell(102, 7.7, $event_name, 0, 0, 'C');
			$this->pdf->SetXY(65.0, 25.1);
			$this->pdf->Cell(80, 7.7, $round_name, 0, 0, 'C');
			$this->pdf->SetXY(33.0, 32.8);
			$this->pdf->Cell(8.5, 7.7, $day, 0); 
			$this->pdf->SetXY(45.0, 32.8);
			$this->pdf->Cell(8.5, 7.7, $month, 0); 
			$this->pdf->SetXY(59.0, 32.8);
			$this->pdf->Cell(18.5, 7.7, $year, 0);
			
			$this->pdf->SetXY(132.5, 32.8);
			$this->pdf->Cell(12.5, 7.7, '' . $data->id, 0);
			
			$killed_first = 0;
			$y = 52.2;
			$extra_point_comments = '';
			for ($i = 0; $i < 10; ++$i, $y += 10.1)
			{
				$player = $data->players[$i];
				$role = '';
				$points = 0;
				$extra_points = 0;
				if (isset($player->bonus))
				{
					if (is_numeric($player->bonus))
					{
						$extra_points = $player->bonus;
					}
					else if (is_array($player->bonus))
					{
						foreach ($player->bonus as $bonus)
						{
							if (is_numeric($bonus))
							{
								$extra_points += $bonus;
							}
						}
					}
				}
				
				if (!isset($player->role) || $player->role == "civ")
				{
					$points = ($data->winner == "civ" ? 1 : 0);
				}
				else if ($player->role == "sheriff")
				{
					$points = ($data->winner == "civ" ? 1 : 0);
					$role = 'Ш';
				}
				else if ($player->role == "maf")
				{
					$points = ($data->winner == "maf" ? 1 : 0);
					$role = 'М';
				}
				else if ($player->role == "don")
				{
					$points = ($data->winner == "maf" ? 1 : 0);
					$role = 'Д';
				}
				
				if (isset($player->legacy))
				{
					$killed_first = $i + 1;
					$guess_right = 0;
					foreach ($player->legacy as $num)
					{
						$p = $data->players[$num-1];
						if (isset($p->role) && ($p->role == "maf" || $p->role == "don"))
						{
							++$guess_right;
						}
					}
					if ($guess_right >= 3)
					{
						$extra_points += 0.5;
					}
					else if ($guess_right >= 2)
					{
						$extra_points += 0.3;
					}
				}
				
				if (isset($player->death))
				{
					$dt = NULL;
					if (is_string($player->death))
					{
						$dt = $player->death;
					}
					else if ($player->death->type)
					{
						$dt = $player->death->type;
					}
					
					if ($dt == DEATH_TYPE_WARNINGS)
					{
						$extra_points -= 0.5;
					}
					else if ($dt == DEATH_TYPE_GIVE_UP || $dt == DEATH_TYPE_KICK_OUT)
					{
						$extra_points -= 0.5;
						$this->pdf->SetXY(112.425, $y);
						$this->pdf->Cell(7.475, 10.1, '!', 0, 0, 'C'); // √
					}
					else if ($dt == DEATH_TYPE_TEAM_KICK_OUT) 
					{
						$extra_points -= 0.7;
						$this->pdf->SetXY(112.425, $y);
						$this->pdf->Cell(7.475, 10.1, '!', 0, 0, 'C'); // √
					}
						
				}
				
				if (isset($player->death))
				{
					$this->pdf->SetXY(14.0, $y);
					$this->pdf->Cell(4, 10.1, '*', 0);
				}
				$this->pdf->SetXY(25.9, $y);
				$this->pdf->Cell(54.0, 10.1, $player->name, 0);
				$this->pdf->Cell(10.1, 10.1, $role, 0, 0, 'C');
				$warinings_count = 0;
				if (isset($player->warnings))
				{
					$warinings_count = is_numeric($player->warnings) ? $player->warnings : count($player->warnings);
				}
				for ($j = 0; $j < $warinings_count; ++$j)
				{
					$this->pdf->Cell(7.475, 10.1, '*', 0, 0, 'C'); // √
				}
				$this->pdf->SetX(119.9);
				$this->pdf->Cell(11.9, 10.1, '' . $points, 0, 0, 'C');
				if ($extra_points != 0)
				{
					$this->pdf->Cell(13.2, 10.1, '' . $extra_points, 0, 0, 'C');
				}
				
				if (isset($player->comment) && !empty($player->comment))
				{
					if (!empty($extra_point_comments))
					{
						$extra_point_comments .= "\n";
					}
					$extra_point_comments .= 'Игрок ' . ($i + 1) . ': ' . $player->comment;
				}
			}
			
			$this->pdf->SetXY(80, 157.8);
			if ($data->winner == "civ")
			{
				$this->pdf->Cell(65, 7.4, 'красные', 0, 0, 'C');
			}
			else if ($data->winner == "maf")
			{
				$this->pdf->Cell(65, 7.4, 'черные', 0, 0, 'C');
			}

			if ($killed_first > 0)
			{
				$legacy = $data->players[$killed_first-1]->legacy;
				$this->pdf->SetXY(128.5, 165.2);
				$this->pdf->Cell(10.5, 10.1, '' . $killed_first, 0, 0, 'C');
				
				$x = 54.0;
				for ($i = 0; $i < count($legacy) && $i < 3; ++$i, $x += 11.5)
				{
					$this->pdf->SetXY($x, 165.2);
					$this->pdf->Cell(8.0, 10.1, '' . $legacy[$i], 0, 0, 'C');
				}
			}
			
			$this->pdf->SetXY(35.0, 182.4);
			$this->pdf->Cell(66, 10.1, $moder_name, 0, 0, 'C');
			
			$objections = '';
			$query = new DbQuery('SELECT o.message, o.accept, u.id, u.name FROM objections o JOIN users u ON u.id = o.user_id WHERE o.game_id = ? ORDER BY timestamp', $data->id);
			while ($row = $query->next())
			{
				list ($message, $accept, $user_id, $user_name) = $row;
				if (empty($objections))
				{
					$complainer = $user_name;
					for ($i = 0; $i < 10; ++$i, $y += 10.1)
					{
						$player = $data->players[$i];
						if ($player->id == $user_id)
						{
							$complainer = 'Игрок ' . ($i + 1) . ' (' . $user_name . ')';
							break;
						}
					}
					
					$this->pdf->SetXY(42.0, 175.3);
					$this->pdf->Cell(103.0, 7.1, $complainer, 0, 0, 'C');
				}
				
				$objections .= $user_name . ': ' . $message;
				if ($accept > 0)
				{
					$objections .= ' Протест принят.';
				}
				else if ($accept < 0)
				{
					$objections .= ' Протест отклонен.';
				}
				$objections .= "\n";
			}
			
			$this->pdf->SetFont('Arial', '', 10);
			$game->init_votings();
			$round_count = min(7, count($game->votings));
			for ($round = 0; $round < $round_count; ++$round)
			{
				$voting = $game->votings[$round];
				if (!isset($voting->nominants))
				{
					continue;
				}
				
				// if (isset($voting->canceled) && $voting->canceled)
				// {
					// continue;
				// }
				
				$x = 167.6;
				for ($i = 0; $i < count($voting->nominants); ++$i)
				{
					$nominant = $voting->nominants[$i];
					$nom_start = '';
					$nom_end = '';
					$p = $data->players[$nominant->nominant - 1]; 
					if (isset($voting->winner) && isset($voting->killed) && $voting->killed)
					{
						if (is_array($voting->winner))
						{
							foreach ($voting->winner as $w)
							{
								if ($w == $nominant->nominant)
								{
									$nom_start = '(';
									$nom_end = ')';
									break;
								}
							}
						}
						else if ($voting->winner == $nominant->nominant)
						{
							$nom_start = '(';
							$nom_end = ')';
						}
					}
					
					$this->pdf->SetXY($x, 22.5 + 25.0 * $round);
					$this->pdf->Cell(10.2, 5.0, $nom_start . $nominant->nominant . $nom_end, 0, 2, 'C');
					if (isset($nominant->voting) && is_array($nominant->voting) && count($nominant->voting) > 0)
					{
						if (is_array($nominant->voting[0]))
						{
							for ($votingRound = 0; $votingRound < count($nominant->voting) && $votingRound < 2; ++$votingRound)
							{
								$this->pdf->Cell(10.2, 5.0, '' . count($nominant->voting[$votingRound]), 0, 2, 'C');
							}
						}
						else
						{
							$this->pdf->Cell(10.2, 5.0, '' . count($nominant->voting), 0, 2, 'C');
						}
					}
					$x += 10.2;
				}
				// $y = 17.5;
				// for ($i = 1; $i < 8; ++$i, $y += 15.0)
				// {
					// $x = 157.2;
					// $this->pdf->SetFont('Times', '', 12);
					// $this->pdf->SetXY(157.2, $y);
					// $this->pdf->Cell(122.6, 5.0, 'Голосование ' . $i, 1, 2, 'C');
					// $y += 5.0;
			}
			
			$this->pdf->SetTextColor(0);
		}

		// Second page template
		$this->pdf->AddPage('L', 'A4');

		$y = 25.2;
		for ($i = 0; $i < 14; ++$i, $y += 8)
		{
			$this->pdf->Line(9.9, $y, 288.7, $y);
		}

		$y = 148.7;
		for ($i = 0; $i < 7; ++$i, $y += 8)
		{
			$this->pdf->Line(9.9, $y, 288.7, $y);
		}

		$this->pdf->SetFont('Times', '', 12);
		$this->pdf->SetXY(9.9, 7.5);
		$this->pdf->Cell(278.8, 16, 'ПОЯСНЕНИЯ К ДОПОЛНИТЕЛЬНЫМ БАЛЛАМ И ШТРАФАМ', 0, 0, 'C');
		$this->pdf->SetXY(9.9, 129.0);
		$this->pdf->Cell(278.8, 16, 'КОММЕНТАРИИ К ПРОТЕСТУ', 0, 0, 'C');
		
		if (!is_null($game))
		{
			$this->pdf->SetTextColor(0, 0, 128);
			$this->pdf->SetFont('Arial', '', 10);
			if (!empty($extra_point_comments))
			{
				$this->pdf->SetXY(10.9, 18.5);
				$this->pdf->MultiCell(276.8, 8, $extra_point_comments);
			}
			
			if (!empty($objections))
			{
				$this->pdf->SetXY(10.9, 142.0);
				$this->pdf->MultiCell(276.8, 8, $objections);
			}
		}
	}
}

?>
