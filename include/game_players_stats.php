<?php

require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/scoring.php';

class GamePlayersStats
{
    public $game;
	public $players;
	
	private function calculate_scoring_flags()
	{
		$data = $this->game->data;
		$maf_day_kills = 0;
		$civ_day_kills = 0;
		$black_checks = 0;
		$red_checks = 0;
		$shared_scoring_flags = SCORING_FLAG_PLAY;
		foreach ($data->players as $p)
		{
			if (isset($p->sheriff))
			{
				if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
				{
					++$black_checks;
				}
				else
				{
					++$red_checks;
				}
			}
			
			if (isset($p->death))
			{
				$death_round = -1;
				$death_type = '';
				if (is_string($p->death))
				{
					$death_type = $p->death;
				}
				else if (is_object($p->death) && isset($p->death->type))
				{
					$death_type = $p->death->type;
					if (isset($p->death->round))
					{
						$death_round = $p->death->round;
					}
				}
				else
				{
					// we don't know, so we set both to 1 and clean win does not count for this game
					$maf_day_kills = 1;
					$civ_day_kills = 1;
				}
				
				if ($death_type == DEATH_TYPE_NIGHT)
				{
					if (isset($p->role) && $p->role == 'sheriff')
					{
						if (isset($p->don))
						{
							if ($death_round == $p->don + 1 && (!isset($p->arranged) || $death_round != $p->arranged))
							{
								$shared_scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING;
							}
							
							if ($p->don == 1)
							{
								$shared_scoring_flags |= SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT;
							}
						}
						
						if ($death_round == 1)
						{
							$shared_scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT;
						}
					}
				}
				else if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
				{
					++$maf_day_kills;
				}
				else
				{
					++$civ_day_kills;
				}
			}
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$game_player = $data->players[$i];
			$player = $this->players[$i];
			$player->scoring_flags = $shared_scoring_flags;
			
			if (isset($game_player->role) && ($game_player->role == 'maf' || $game_player->role == 'don'))
			{
				if ($data->winner == 'maf')
				{
					$player->scoring_flags |= SCORING_FLAG_WIN;
					if ($maf_day_kills == 0)
					{
						$player->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
					}
					if (!isset($game_player->death))
					{
						$player->scoring_flags |= SCORING_FLAG_SURVIVE;
					}
				}
				else if ($data->winner == 'civ')
				{
					$player->scoring_flags |= SCORING_FLAG_LOSE;
					if ($civ_day_kills == 0)
					{
						$player->scoring_flags |= SCORING_FLAG_CLEAR_LOSE;
					}
				}
				else
				{
					$player->scoring_flags |= SCORING_FLAG_TIE;
				}
			}
			else if ($data->winner == 'civ')
			{
				$player->scoring_flags |= SCORING_FLAG_WIN;
				if ($civ_day_kills == 0)
				{
					$player->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
				}
				if (!isset($game_player->death))
				{
					$player->scoring_flags |= SCORING_FLAG_SURVIVE;
				}
			}
			else if ($data->winner == 'maf')
			{
				$player->scoring_flags |= SCORING_FLAG_LOSE;
				if ($maf_day_kills == 0)
				{
					$player->scoring_flags |= SCORING_FLAG_CLEAR_LOSE;
				}
			}
			else
			{
				$player->scoring_flags |= SCORING_FLAG_TIE;
			}
			
			if (isset($game_player->bonus))
			{
				if (is_array($game_player->bonus))
				{
					foreach ($game_player->bonus as $bonus)
					{
						if ($bonus == 'bestPlayer')
						{
							$player->scoring_flags |= SCORING_FLAG_BEST_PLAYER;
						}
						if ($bonus == 'bestMove')
						{
							$player->scoring_flags |= SCORING_FLAG_BEST_MOVE;
						}
						if ($bonus == 'worstMove')
						{
							$player->scoring_flags |= SCORING_FLAG_WORST_MOVE;
						}
					}
				}
				else
				{
					if ($game_player->bonus == 'bestPlayer')
					{
						$player->scoring_flags |= SCORING_FLAG_BEST_PLAYER;
					}
					if ($game_player->bonus == 'bestMove')
					{
						$player->scoring_flags |= SCORING_FLAG_BEST_MOVE;
					}
					if ($game_player->bonus == 'worstMove')
					{
						$player->scoring_flags |= SCORING_FLAG_WORST_MOVE;
					}
				}
			}
			
			if (isset($game_player->death))
			{
				$death_type = NULL;
				$death_round = -1;
				if (is_string($game_player->death))
				{
					$death_type = $game_player->death;
				}
				else if (is_object($game_player->death) && isset($game_player->death->type))
				{
					$death_type = $game_player->death->type;
				}
				if ($death_type == DEATH_TYPE_NIGHT)
				{
					if (isset($game_player->death->round) && $game_player->death->round == 1)
					{
						$player->scoring_flags |= SCORING_FLAG_KILLED_FIRST_NIGHT;
					}
					$player->scoring_flags |= SCORING_FLAG_KILLED_NIGHT;
				}
				else if ($death_type == DEATH_TYPE_GIVE_UP)
				{
					$player->scoring_flags |= SCORING_FLAG_SURRENDERED;
				}
				else if ($death_type == DEATH_TYPE_WARNINGS || $death_type == DEATH_TYPE_TECH_FOULS) // Note that warnings and tech fouls share the same scoring flag. The should be split in the future.
				{
					$player->scoring_flags |= SCORING_FLAG_WARNINGS_4;
				}
				else if ($death_type == DEATH_TYPE_KICK_OUT)
				{
					$player->scoring_flags |= SCORING_FLAG_KICK_OUT;
				}
				else if ($death_type == DEATH_TYPE_TEAM_KICK_OUT)
				{
					$player->scoring_flags |= SCORING_FLAG_TEAM_KICK_OUT;
				}
			}
			
			// Note that we set flags no matter which day the legacy was made.
			// Currently legacy can be left only in the first day. In the future we will support other legacies. In this case scoring system should check death type and round in addition to legacy flags.
			// Most likely SCORING_FLAG_FIRST_LEGACY_1 and SCORING_FLAG_FIRST_LEGACY_0 will also be needed.
			if (isset($game_player->legacy))
			{
				$mafs_guessed = 0;
				if (is_array($game_player->legacy))
				{
					foreach ($game_player->legacy as $legacy)
					{
						$p = $data->players[$legacy - 1];
						if (isset($p->role) && ($p->role == 'maf' || $p->role == 'don'))
						{
							++$mafs_guessed;
						}
					}
				}
				else
				{
					$mafs_guessed = (int)$game_player->legacy;
				}
				
				if ($mafs_guessed >= 3)
				{
					$player->scoring_flags |= SCORING_FLAG_FIRST_LEGACY_3;
				}
				else if ($mafs_guessed >= 2)
				{
					$player->scoring_flags |= SCORING_FLAG_FIRST_LEGACY_2;
				}
				else if ($mafs_guessed >= 1)
				{
					$player->scoring_flags |= SCORING_FLAG_FIRST_LEGACY_1;
				}
			}
			
			if ($player->voted_civil + $player->voted_sheriff == 0 && $player->voted_mafia >= 3)
			{
				$player->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_MAF;
			}
			
			if ($player->voted_mafia == 0 && $player->voted_civil + $player->voted_sheriff >= 3)
			{
				$player->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_CIV;
			}
			
			if ($black_checks >= 3)
			{
				$player->scoring_flags |= SCORING_FLAG_BLACK_CHECKS;
			}
			else if ($red_checks >= 3)
			{
				$player->scoring_flags |= SCORING_FLAG_RED_CHECKS;
			}
			
			if (isset($game_player->bonus))
			{
				$player->scoring_flags |= SCORING_FLAG_EXTRA_POINTS;
			}
		}
	}
		
	function __construct($game)
    {
        $this->game = $game;
		$this->players = array();
		$mafs = array();
		$don = NULL;
		$sheriff = NULL;
		$data = $game->data;
		
		// mr points removed
		//$this->mr_points = $game->get_mafiaratings_points();
		
		// Init roles and ids
		for ($i = 0; $i < 10; ++$i)
		{
			$game_player = $data->players[$i];
			$player = new stdClass();
			$player->number = $i + 1;
			if (isset($game_player->id) && $game_player->id > 0)
			{
				$player->id = $game_player->id;
			}
			
			if (!isset($game_player->role) || $game_player->role == 'civ')
			{
				$player->role = ROLE_CIVILIAN;
				$player->won = $data->winner == 'civ' ? 1 : 0;
			}
			else if ($game_player->role == 'sheriff')
			{
				$player->role = ROLE_SHERIFF;
				$player->won = $data->winner == 'civ' ? 1 : 0;
				$sheriff = $player;
				$sheriff->civil_found = 0;
				$sheriff->mafia_found = 0;
			}
			else if ($game_player->role == 'maf')
			{
				$player->role = ROLE_MAFIA;
				$player->won = $data->winner == 'maf' ? 1 : 0;
				$mafs[] = $i;
				
				$player->shots1_ok = 0;
				$player->shots1_miss = 0;
				$player->shots2_ok = 0;        // 2 mafia players are alive: successful shots
				$player->shots2_miss = 0;      // 2 mafia players are alive: missed shot
				$player->shots2_blank = 0;     // 2 mafia players are alive: this player didn't shoot
				$player->shots2_rearrange = 0; // 2 mafia players are alive: killed a player who was not arranged
				$player->shots3_ok = 0;        // 3 mafia players are alive: successful shots
				$player->shots3_miss = 0;      // 3 mafia players are alive: missed shot
				$player->shots3_blank = 0;     // 3 mafia players are alive: this player didn't shoot
				$player->shots3_fail = 0;      // 3 mafia players are alive: missed because of this player (others shoot the same person)
				$player->shots3_rearrange = 0; // 3 mafia players are alive: killed a player who was not arranged
			}
			else if ($game_player->role == 'don')
			{
				$player->role = ROLE_DON;
				$player->won = $data->winner == 'maf' ? 1 : 0;
				$mafs[] = $i;
				$don = $player;
				
				$player->shots1_ok = 0;
				$player->shots1_miss = 0;
				$player->shots2_ok = 0;        // 2 mafia players are alive: successful shots
				$player->shots2_miss = 0;      // 2 mafia players are alive: missed shot
				$player->shots2_blank = 0;     // 2 mafia players are alive: this player didn't shoot
				$player->shots2_rearrange = 0; // 2 mafia players are alive: killed a player who was not arranged
				$player->shots3_ok = 0;        // 3 mafia players are alive: successful shots
				$player->shots3_miss = 0;      // 3 mafia players are alive: missed shot
				$player->shots3_blank = 0;     // 3 mafia players are alive: this player didn't shoot
				$player->shots3_fail = 0;      // 3 mafia players are alive: missed because of this player (others shoot the same person)
				$player->shots3_rearrange = 0; // 3 mafia players are alive: killed a player who was not arranged
				$don->sheriff_found = -1;
				$don->sheriff_arranged = -1;
			}
			else
			{
				// Should never reach, but for the case someone specifies role="town" or something like that.
				$player->role = ROLE_CIVILIAN;
				$player->won = $data->winner == 'civ' ? 1 : 0;
			}

			// mr points removed
			// $player->mr_points = 0;
			// for ($j = 0; $j < 10; ++$j)
			// {
				// $player->mr_points += $this->mr_points->pos_points[$i][$j] + $this->mr_points->neg_points[$i][$j];
			// }
			
			$death_type = '';
			$player->kill_round = -1;
			if (isset($game_player->death))
			{
				if (is_string($game_player->death))
				{
					$death_type = $game_player->death;
				}
				else if (is_numeric($game_player->death))
				{
					$player->kill_round = (int)$game_player->death;
				}
				else 
				{
					if (isset($game_player->death->type))
					{
						$death_type = $game_player->death->type;
					}
					if (isset($game_player->death->round))
					{
						$player->kill_round = (int)$game_player->death->round;
					}
				}
			}
			switch ($death_type)
			{
				case DEATH_TYPE_GIVE_UP:
					$player->kill_type = KILL_TYPE_GIVE_UP;
					break;
				case DEATH_TYPE_WARNINGS:
				case DEATH_TYPE_TECH_FOULS: // Note that warnings and tech fouls share the same stats counter. They should be split in the future.
					$player->kill_type = KILL_TYPE_WARNINGS;
					break;
				case DEATH_TYPE_KICK_OUT:
					$player->kill_type = KILL_TYPE_KICK_OUT;
					break;
				case DEATH_TYPE_TEAM_KICK_OUT:
					$player->kill_type = KILL_TYPE_TEAM_KICK_OUT;
					break;
				case DEATH_TYPE_NIGHT:
					$player->kill_type = KILL_TYPE_NIGHT;
					break;
				case DEATH_TYPE_DAY:
					$player->kill_type = KILL_TYPE_DAY;
					break;
				default:
					$player->kill_type = KILL_TYPE_SURVIVED;
					break;
			}
			
			if (isset($game_player->comment) && !empty($game_player->comment))
			{
				$player->comment = $game_player->comment;
			}
			else
			{
				$player->comment = NULL;
			}
			
			$player->warnings = 0;
			if (isset($game_player->warnings))
			{
				if (is_array($game_player->warnings))
				{
					$player->warnings = count($game_player->warnings);
				}
				else
				{
					$player->warnings = (int)$game_player->warnings;
				}
			}
			
			// not used yet
			$player->techFouls = 0;
			if (isset($game_player->techFouls))
			{
				if (is_array($game_player->techFouls))
				{
					$player->techFouls = count($game_player->techFouls);
				}
				else
				{
					$player->techFouls = (int)$game_player->techFouls;
				}
			}
			
			$player->arranged = -1;
			if (isset($game_player->arranged))
			{
				$player->arranged = $game_player->arranged;
			}
			
			$player->don_check = -1;
			if (isset($game_player->don))
			{
				$player->don_check = $game_player->don;
			}
			
			$player->sheriff_check = -1;
			if (isset($game_player->sheriff))
			{
				$player->sheriff_check = $game_player->sheriff;
			}
			
			$player->extra_points = 0;
			if (isset($game_player->bonus))
			{
				if (is_numeric($game_player->bonus))
				{
					$player->extra_points = $game_player->bonus;
				}
				else if (is_array($game_player->bonus))
				{
					foreach ($game_player->bonus as $bonus)
					{
						if (is_numeric($bonus))
						{
							$player->extra_points += $bonus;
						}
					}
				}
			}
			
			$player->voted_civil = 0;
			$player->voted_mafia = 0;
			$player->voted_sheriff = 0;
			$player->nominated_civil = 0;
			$player->nominated_mafia = 0;
			$player->nominated_sheriff = 0;
			$player->nominated_by_mafia = 0;
			$player->nominated_by_civil = 0;
			$player->nominated_by_sheriff = 0;
			$player->voted_by_mafia = 0;
			$player->voted_by_civil = 0;
			$player->voted_by_sheriff = 0;

			$this->players[] = $player;
		}
		
		// Votings and nominations
		for ($i = 0; $i < 10; ++$i)
		{
			$game_player = $data->players[$i];
			$player = $this->players[$i];
			
			if (isset($game_player->nominating))
			{
				foreach ($game_player->nominating as $n)
				{
					if (is_null($n))
					{
						continue;
					}
					$p = $this->players[$n-1];
					switch ($p->role)
					{
						case ROLE_CIVILIAN:
							++$player->nominated_civil;
							break;
						case ROLE_SHERIFF:
							++$player->nominated_sheriff;
							break;
						case ROLE_MAFIA:
						case ROLE_DON:
							++$player->nominated_mafia;
							break;
					}
					switch ($player->role)
					{
						case ROLE_CIVILIAN:
							++$p->nominated_by_civil;
							break;
						case ROLE_SHERIFF:
							++$p->nominated_by_sheriff;
							break;
						case ROLE_MAFIA:
						case ROLE_DON:
							++$p->nominated_by_mafia;
							break;
					}
				}
			}
			
			if (isset($game_player->voting))
			{
				// We exclude voting in round 0 because this is mostly splitting. 
				for ($j = 1; $j < count($game_player->voting); ++$j)
				{
					$v = $game_player->voting[$j];
					if (is_null($v))
					{
						continue;
					}
					
					if (is_array($v))
					{
						foreach ($v as $v1)
						{
							if (is_bool($v1))
							{
								break; // this is voting for killing all
							}
							$p = $this->players[$v1-1];
							switch ($p->role)
							{
								case ROLE_CIVILIAN:
									++$player->voted_civil;
									break;
								case ROLE_SHERIFF:
									++$player->voted_sheriff;
									break;
								case ROLE_MAFIA:
								case ROLE_DON:
									++$player->voted_mafia;
									break;
							}
							switch ($player->role)
							{
								case ROLE_CIVILIAN:
									++$p->voted_by_civil;
									break;
								case ROLE_SHERIFF:
									++$p->voted_by_sheriff;
									break;
								case ROLE_MAFIA:
								case ROLE_DON:
									++$p->voted_by_mafia;
									break;
							}
						}
					}
					else
					{
						$p = $this->players[$v-1];
						switch ($p->role)
						{
							case ROLE_CIVILIAN:
								++$player->voted_civil;
								break;
							case ROLE_SHERIFF:
								++$player->voted_sheriff;
								break;
							case ROLE_MAFIA:
							case ROLE_DON:
								++$player->voted_mafia;
								break;
						}
						switch ($player->role)
						{
							case ROLE_CIVILIAN:
								++$p->voted_by_civil;
								break;
							case ROLE_SHERIFF:
								++$p->voted_by_sheriff;
								break;
							case ROLE_MAFIA:
							case ROLE_DON:
								++$p->voted_by_mafia;
								break;
						}
					}
				}
			}
			
			if (isset($game_player->sheriff))
			{
				if ($player->role <= ROLE_SHERIFF)
				{
					++$sheriff->civil_found;
				}
				else
				{
					++$sheriff->mafia_found;
				}
			}
			
			if (isset($game_player->don) && $player->role == ROLE_SHERIFF)
			{
				$don->sheriff_found = $game_player->don;
			}
			
			if (isset($game_player->arranged) && $player->role == ROLE_SHERIFF)
			{
				$don->sheriff_arranged = $game_player->arranged;
			}
		}
		
		$shooting_time = new stdClass();
		$shooting_time->time = GAMETIME_SHOOTING;
		$shooting_time->round = 1;
		$last_gametime = $game->get_last_gametime(true);
		while (true)
		{
			if ($game->compare_gametimes($last_gametime, $shooting_time) < 0)
			{
				break;
			}
			
			for ($j = 0; $j < count($mafs); )
			{
				$n = $mafs[$j];
				$m = $data->players[$n];
				if ($game->compare_gametimes($game->get_player_death_time($n + 1), $shooting_time) < 0)
				{
					array_splice($mafs, $j, 1);
				}
				else
				{
					++$j;
				}
			}
			
			if ($j <= 0)
			{
				break;
			}
			
			switch ($j)
			{
				case 1:
					if (!isset($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots1_miss;
					}
					else
					{
						++$this->players[$mafs[0]]->shots1_ok;
					}
					break;
				case 2:
					if (!isset($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots2_blank;
						if (!isset($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]))
						{
							++$this->players[$mafs[1]]->shots2_blank;
						}
						else
						{
							++$this->players[$mafs[1]]->shots2_miss;
						}
					}
					else if (!isset($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots2_miss;
						++$this->players[$mafs[1]]->shots2_blank;
					}
					else if ($data->players[$mafs[0]]->shooting[$shooting_time->round - 1] != $data->players[$mafs[1]]->shooting[$shooting_time->round - 1])
					{
						++$this->players[$mafs[0]]->shots2_miss;
						++$this->players[$mafs[1]]->shots2_miss;
					}
					else
					{
						++$this->players[$mafs[0]]->shots2_ok;
						++$this->players[$mafs[1]]->shots2_ok;
						$victim_num = $data->players[$mafs[0]]->shooting[$shooting_time->round - 1];
						$victim = $data->players[$victim_num-1];
						if (!isset($victim->arranged) || $victim->arranged != $victim_num)
						{
							++$this->players[$mafs[0]]->shots2_rearrange;
							++$this->players[$mafs[1]]->shots2_rearrange;
						}
					}
					break;
					
				case 3:
					if (!isset($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[0]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots3_blank;
						if (!isset($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]))
						{
							++$this->players[$mafs[1]]->shots3_blank;
							if (!isset($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]))
							{
								++$this->players[$mafs[2]]->shots3_blank;
							}
							else
							{
								++$this->players[$mafs[2]]->shots3_miss;
							}
						}
						else if (!isset($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]))
						{
							++$this->players[$mafs[1]]->shots3_miss;
							++$this->players[$mafs[2]]->shots3_blank;
						}
						else
						{
							++$this->players[$mafs[1]]->shots3_miss;
							++$this->players[$mafs[2]]->shots3_miss;
						}
					}
					else if (!isset($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[1]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots3_miss;
						++$this->players[$mafs[1]]->shots3_blank;
						if (!isset($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]))
						{
							++$this->players[$mafs[2]]->shots3_blank;
						}
						else
						{
							++$this->players[$mafs[2]]->shots3_miss;
						}
					}
					else if (!isset($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]) || is_null($data->players[$mafs[2]]->shooting[$shooting_time->round - 1]))
					{
						++$this->players[$mafs[0]]->shots3_miss;
						++$this->players[$mafs[1]]->shots3_miss;
						++$this->players[$mafs[2]]->shots3_blank;
					}
					else if ($data->players[$mafs[0]]->shooting[$shooting_time->round - 1] != $data->players[$mafs[1]]->shooting[$shooting_time->round - 1])
					{
						if ($data->players[$mafs[0]]->shooting[$shooting_time->round - 1] == $data->players[$mafs[2]]->shooting[$shooting_time->round - 1])
						{
							++$this->players[$mafs[0]]->shots3_miss;
							++$this->players[$mafs[1]]->shots3_fail;
						}
						else if ($data->players[$mafs[1]]->shooting[$shooting_time->round - 1] == $data->players[$mafs[2]]->shooting[$shooting_time->round - 1])
						{
							++$this->players[$mafs[0]]->shots3_fail;
							++$this->players[$mafs[1]]->shots3_miss;
						}
						else
						{
							++$this->players[$mafs[0]]->shots3_miss;
							++$this->players[$mafs[1]]->shots3_miss;
						}
						++$this->players[$mafs[2]]->shots3_miss;
					}
					else if ($data->players[$mafs[0]]->shooting[$shooting_time->round - 1] != $data->players[$mafs[2]]->shooting[$shooting_time->round - 1])
					{
						++$this->players[$mafs[0]]->shots3_miss;
						++$this->players[$mafs[1]]->shots3_miss;
						++$this->players[$mafs[2]]->shots3_fail;
					}
					else
					{
						++$this->players[$mafs[0]]->shots3_ok;
						++$this->players[$mafs[1]]->shots3_ok;
						++$this->players[$mafs[2]]->shots3_ok;
						$victim_num = $data->players[$mafs[0]]->shooting[$shooting_time->round - 1];
						$victim = $data->players[$victim_num-1];
						if (!isset($victim->arranged) || $victim->arranged != $shooting_time->round)
						{
							++$this->players[$mafs[0]]->shots3_rearrange;
							++$this->players[$mafs[1]]->shots3_rearrange;
							++$this->players[$mafs[2]]->shots3_rearrange;
						}
					}
					break;
			}
			++$shooting_time->round;
		}
			
		$this->calculate_scoring_flags();
    }
	
    function save()
    {
		$data = $this->game->data;
		$is_rating = isset($data->rating) && !$data->rating ? 0 : 1;

		// mr points removed
		// Db::exec(get_label('stats'), 
			// 'DELETE FROM mr_bonus_stats WHERE game_id = ?', $data->id);
		// if ($is_rating && isset($data->tournamentId))
		// {
			// list ($scoring_functions) = Db::record(get_label('tournament'), 'SELECT s.functions FROM tournaments t JOIN scoring_versions s ON s.scoring_id = t.scoring_id AND s.version = t.scoring_version WHERE t.id = ?', $data->tournamentId);
			// if ($scoring_functions & SCORING_FUNCTION_MR_POINTS)
			// {
				// $red_stats = $this->mr_points->red_stats;
				// $black_stats = $this->mr_points->black_stats;
				// Db::exec(get_label('stats'), 
					// 'INSERT INTO mr_bonus_stats(game_id, time, red_num, red_mean, red_variance, black_num, black_mean, black_variance) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
					// $data->id, $data->endTime, $red_stats->n, $red_stats->mean, $red_stats->variance, $black_stats->n, $black_stats->mean, $black_stats->variance);
			// }
		// }
		
		for ($i = 0; $i < 10; ++$i)
		{
			$game_player = $data->players[$i];
			$player = $this->players[$i];
			if (!isset($player->id))
			{
				continue;
			}
			
			// mr points removed
			$player->mr_points = 0;
			
			Db::exec(
				get_label('player'), 
				'INSERT INTO players (game_id, user_id, nick_name, number, role, flags, ' .
					'voted_civil, voted_mafia, voted_sheriff, voted_by_civil, voted_by_mafia, voted_by_sheriff, ' .
					'nominated_civil, nominated_mafia, nominated_sheriff, nominated_by_civil, nominated_by_mafia, nominated_by_sheriff, ' .
					'kill_round, kill_type, warns, was_arranged, checked_by_don, checked_by_sheriff, won, extra_points, extra_points_reason, game_end_time, is_rating, mr_points) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$data->id, $player->id, $game_player->name, $player->number, $player->role, $player->scoring_flags,
				$player->voted_civil, $player->voted_mafia, $player->voted_sheriff, $player->voted_by_civil, $player->voted_by_mafia, $player->voted_by_sheriff,
				$player->nominated_civil, $player->nominated_mafia, $player->nominated_sheriff, $player->nominated_by_civil, $player->nominated_by_mafia, $player->nominated_by_sheriff,
				$player->kill_round, $player->kill_type, $player->warnings, $player->arranged, $player->don_check, $player->sheriff_check, $player->won, $player->extra_points, $player->comment, $data->endTime, $is_rating, $player->mr_points);
				
			switch ($player->role)
			{
				case ROLE_CIVILIAN:
					break;
				case ROLE_SHERIFF:
					Db::exec(
						get_label('sheriff'), 
						'INSERT INTO sheriffs VALUES (?, ?, ?, ?)',
						$data->id, $player->id, $player->civil_found, $player->mafia_found);
					break;
				case ROLE_DON:
					Db::exec(
						get_label('mafioso'), 
						'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == ROLE_DON ? 'true' : 'false') . ')',
						$data->id, $player->id, $player->shots1_ok, $player->shots1_miss, $player->shots2_ok,
						$player->shots2_miss, $player->shots2_blank, $player->shots2_rearrange, $player->shots3_ok, $player->shots3_miss,
						$player->shots3_blank, $player->shots3_fail, $player->shots3_rearrange);
					Db::exec(
						get_label('don'), 
						'INSERT INTO dons VALUES (?, ?, ?, ?)',
						$data->id, $player->id, $player->sheriff_found, $player->sheriff_arranged);
					break;
				case ROLE_MAFIA:
					Db::exec(
						get_label('mafioso'), 
						'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == ROLE_DON ? 'true' : 'false') . ')',
						$data->id, $player->id, $player->shots1_ok, $player->shots1_miss, $player->shots2_ok,
						$player->shots2_miss, $player->shots2_blank, $player->shots2_rearrange, $player->shots3_ok, $player->shots3_miss,
						$player->shots3_blank, $player->shots3_fail, $player->shots3_rearrange);
					break;
			}
		}
    }
}

?>