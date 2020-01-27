<?php

require_once __DIR__ . '/game_state.php';
require_once __DIR__ . '/localization.php';
require_once __DIR__ . '/scoring.php';

class GamePlayerStats
{
    public $gs;
    public $player_num;
	public $won;
	public $scoring_flags;
	
    public $rating_before;
    public $rating_earned;
   
    public $voted_civil;
    public $voted_mafia;
    public $voted_sheriff;
    public $nominated_civil;
    public $nominated_mafia;
    public $nominated_sheriff;
    public $nominated_by_mafia;
    public $nominated_by_civil;
    public $nominated_by_sheriff;
    public $voted_by_mafia;
    public $voted_by_civil;
    public $voted_by_sheriff;
    public $kill_type;

    // Sheriff only
    public $civil_found;
    public $mafia_found;

    // Mafia only
    public $shots1_ok;
    public $shots1_miss;
    public $shots2_ok;        // 2 mafia players are alive: successful shots
    public $shots2_miss;      // 2 mafia players are alive: missed shot
    public $shots2_blank;     // 2 mafia players are alive: this player didn't shoot
    public $shots2_rearrange; // 2 mafia players are alive: killed a player who was not arranged
    public $shots3_ok;        // 3 mafia players are alive: successful shots
    public $shots3_miss;      // 3 mafia players are alive: missed shot
    public $shots3_blank;     // 3 mafia players are alive: this player didn't shoot
    public $shots3_fail;      // 3 mafia players are alive: missed because of this player (others shoot the same person)
    public $shots3_rearrange; // 3 mafia players are alive: killed a player who was not arranged

    // Don only
    public $sheriff_found;
    public $sheriff_arranged;
	
	private $timestamp;
	
	private function calculate_scoring_flags()
	{
		$gs = $this->gs;
        $player = $gs->players[$this->player_num];
		$this->scoring_flags = SCORING_FLAG_PLAY;
		
		$maf_day_kills = 0;
		$civ_day_kills = 0;
		foreach ($gs->players as $p)
		{
			if ($p->state == PLAYER_STATE_KILLED_DAY)
			{
				if ($p->role == PLAYER_ROLE_MAFIA || $p->role == PLAYER_ROLE_DON)
				{
					++$maf_day_kills;
				}
				else
				{
					++$civ_day_kills;
				}
			}
		}
		
		if ($player->role >= PLAYER_ROLE_MAFIA)
		{
			if ($gs->gamestate == GAME_CIVIL_WON)
			{
				$this->scoring_flags |= SCORING_FLAG_LOSE;
				if ($civ_day_kills == 0)
				{
					$this->scoring_flags |= SCORING_FLAG_CLEAR_LOSE;
				}
			}
			else
			{
				$this->scoring_flags |= SCORING_FLAG_WIN;
				if ($maf_day_kills == 0)
				{
					$this->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
				}
				if ($player->state == PLAYER_STATE_ALIVE)
				{
					$this->scoring_flags |= SCORING_FLAG_SURVIVE;
				}
			}
		}
		else if ($gs->gamestate == GAME_CIVIL_WON)
		{
			$this->scoring_flags |= SCORING_FLAG_WIN;
			if ($civ_day_kills == 0)
			{
				$this->scoring_flags |= SCORING_FLAG_CLEAR_WIN;
			}
			if ($player->state == PLAYER_STATE_ALIVE)
			{
				$this->scoring_flags |= SCORING_FLAG_SURVIVE;
			}
		}
		else
		{
			$this->scoring_flags |= SCORING_FLAG_LOSE;
			if ($maf_day_kills == 0)
			{
				$this->scoring_flags |= SCORING_FLAG_CLEAR_LOSE;
			}
		}
		
		if ($gs->best_player == $this->player_num)
		{
			$this->scoring_flags |= SCORING_FLAG_BEST_PLAYER;
		}
		if ($gs->best_move == $this->player_num)
		{
			$this->scoring_flags |= SCORING_FLAG_BEST_MOVE;
		}
		
		if ($player->state == PLAYER_STATE_KILLED_NIGHT)
		{
			if ($player->kill_round == 0)
			{
				$this->scoring_flags |= SCORING_FLAG_KILLED_FIRST_NIGHT;
			}
			$this->scoring_flags |= SCORING_FLAG_KILLED_NIGHT;
		}
		
		$mafs_guessed = $gs->mafs_guessed($this->player_num);
		if ($mafs_guessed >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_FIRST_LEGACY_3;
		}
		else if ($mafs_guessed >= 2)
		{
			$this->scoring_flags |= SCORING_FLAG_FIRST_LEGACY_2;
		}
		
        switch ($player->kill_reason)
        {
            case KILL_REASON_SUICIDE:
				$this->scoring_flags |= SCORING_FLAG_SURRENDERED;
                break;
            case KILL_REASON_WARNINGS:
				$this->scoring_flags |= SCORING_FLAG_WARNINGS_4;
                break;
            case KILL_REASON_KICK_OUT:
				$this->scoring_flags |= SCORING_FLAG_KICK_OUT;
                break;
        }
		
		if ($this->voted_civil + $this->voted_sheriff == 0 && $this->voted_mafia >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_MAF;
		}
		
		if ($this->voted_mafia == 0 && $this->voted_civil + $this->voted_sheriff >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_ALL_VOTES_VS_CIV;
		}
		
		foreach ($gs->players as $p)
		{
			if ($p->role == PLAYER_ROLE_SHERIFF)
			{
				if ($p->state == PLAYER_STATE_KILLED_NIGHT)
				{
					if ($p->kill_round == $p->don_check + 1 && $p->arranged != $p->kill_round)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_AFTER_FINDING;
					}
					
					if ($p->kill_round == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_KILLED_FIRST_NIGHT;
					}
					
					if ($p->don_check == 0)
					{
						$this->scoring_flags |= SCORING_FLAG_SHERIFF_FOUND_FIRST_NIGHT;
					}
				}
				break;
			}
		}
		
		$black_checks = 0;
		$red_checks = 0;
		foreach ($gs->players as $p)
		{
			if ($p->sheriff_check < 0 || $p->sheriff_check > 2)
			{
				continue;
			}
			
			if ($p->role >= PLAYER_ROLE_MAFIA)
			{
				++$black_checks;
			}
			else
			{
				++$red_checks;
			}
		}
		if ($black_checks >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_BLACK_CHECKS;
		}
		else if ($red_checks >= 3)
		{
			$this->scoring_flags |= SCORING_FLAG_RED_CHECKS;
		}
		
		if ($player->extra_points != 0)
		{
			$this->scoring_flags |= SCORING_FLAG_EXTRA_POINTS;
		}
	}

	function __construct($gs, $player_num)
    {
		$player_num = min(max((int)$player_num, 0), 9);
		$this->timestamp = time();
	
        $player = $gs->players[$player_num];

        $this->gs = $gs;
        $this->player_num = $player_num;
		
		$this->won = 0;
		if ($gs->gamestate == GAME_CIVIL_WON)
		{
			if ($player->role <= PLAYER_ROLE_SHERIFF)
			{
				$this->won = 1;
			}
		}
		else if ($player->role > PLAYER_ROLE_SHERIFF)
		{
			$this->won = 1;
		}

        $this->nominated_civil = 0;
        $this->nominated_mafia = 0;
        $this->nominated_sheriff = 0;
        $this->nominated_by_mafia = 0;
        $this->nominated_by_civil = 0;
        $this->nominated_by_sheriff = 0;
        $this->voted_civil = 0;
        $this->voted_mafia = 0;
        $this->voted_sheriff = 0;
        $this->voted_by_mafia = 0;
        $this->voted_by_civil = 0;
        $this->voted_by_sheriff = 0;
		
        foreach ($gs->votings as $voting)
        {
            if ($voting->round <= 0 || $voting->is_canceled())
            {
                continue;
            }

			$nominant_num = -1;
			$count = count($voting->nominants);
			for ($i = 0; $i < $count; ++$i)
            {
				$nominant = $voting->nominants[$i];
			
                if ($nominant->player_num == $player_num)
                {
					$nominant_num = $i;
					if ($nominant->nominated_by >= 0)
					{
						switch ($gs->players[$nominant->nominated_by]->role)
						{
							case PLAYER_ROLE_CIVILIAN:
								++$this->nominated_by_civil;
								break;
							case PLAYER_ROLE_SHERIFF:
								++$this->nominated_by_sheriff;
								break;
							case PLAYER_ROLE_MAFIA:
							case PLAYER_ROLE_DON:
								++$this->nominated_by_mafia;
								break;
						}
					}
                }

                if ($nominant->nominated_by == $player_num)
                {
                    switch ($gs->players[$nominant->player_num]->role)
                    {
                        case PLAYER_ROLE_CIVILIAN:
                            ++$this->nominated_civil;
                            break;
                        case PLAYER_ROLE_SHERIFF:
                            ++$this->nominated_sheriff;
                            break;
                        case PLAYER_ROLE_MAFIA:
                        case PLAYER_ROLE_DON:
                            ++$this->nominated_mafia;
                            break;
                    }
                }
            }

			if (($gs->flags & GAME_FLAG_SIMPLIFIED_CLIENT) == 0)
			{
				$nominant = $voting->votes[$player_num];
				if ($nominant >= 0 )
				{
					switch ($gs->players[$voting->nominants[$nominant]->player_num]->role)
					{
						case PLAYER_ROLE_CIVILIAN:
							++$this->voted_civil;
							break;
						case PLAYER_ROLE_SHERIFF:
							++$this->voted_sheriff;
							break;
						case PLAYER_ROLE_MAFIA:
						case PLAYER_ROLE_DON:
							++$this->voted_mafia;
							break;
					}
				}
			
				if ($nominant_num >= 0)
				{
					for ($i = 0; $i < 10; ++$i)
					{
						if ($voting->votes[$i] == $nominant_num)
						{
							switch ($gs->players[$i]->role)
							{
								case PLAYER_ROLE_CIVILIAN:
									++$this->voted_by_civil;
									break;
								case PLAYER_ROLE_SHERIFF:
									++$this->voted_by_sheriff;
									break;
								case PLAYER_ROLE_MAFIA:
								case PLAYER_ROLE_DON:
									++$this->voted_by_mafia;
									break;
							}
						}
					}
				}
			}
        }

        switch ($player->kill_reason)
        {
            case KILL_REASON_NORMAL:
                if ($player->state == PLAYER_STATE_KILLED_NIGHT)
                {
                    $this->kill_type = 2;
                }
                else if ($player->state == PLAYER_STATE_KILLED_DAY)
                {
                    $this->kill_type = 1;
                }
                break;
            case KILL_REASON_SUICIDE:
                $this->kill_type = 4;
                break;
            case KILL_REASON_WARNINGS:
                $this->kill_type = 3;
                break;
            case KILL_REASON_KICK_OUT:
                $this->kill_type = 5;
                break;
            default:
                $this->kill_type = 0;
                break;
        }

        // Sheriff
        $this->civil_found = 0;
        $this->mafia_found = 0;
        if ($player->role == PLAYER_ROLE_SHERIFF)
        {
            foreach ($gs->players as $player)
            {
                if ($player->sheriff_check >= 0)
                {
                    switch ($player->role)
                    {
                        case PLAYER_ROLE_CIVILIAN:
                            ++$this->civil_found;
                            break;
                        case PLAYER_ROLE_MAFIA:
                        case PLAYER_ROLE_DON:
                            ++$this->mafia_found;
                            break;
                    }
                }
            }
        }

        // Mafia
        $this->shots1_ok = 0;
        $this->shots1_miss = 0;
        $this->shots2_ok = 0;
        $this->shots2_miss = 0;
        $this->shots2_blank = 0;
        $this->shots2_rearrange = 0;
        $this->shots3_ok = 0;
        $this->shots3_miss = 0;
        $this->shots3_blank = 0;
        $this->shots3_fail = 0;
        $this->shots3_rearrange = 0;
        if ($player->role == PLAYER_ROLE_MAFIA || $player->role == PLAYER_ROLE_DON)
        {
            $partner1 = -1;
            $partner2 = -1;
            for ($i = 0; $i < 10; ++$i)
            {
                if ($i != $player_num)
                {
                    $p = $gs->players[$i];
                    if ($p->role == PLAYER_ROLE_MAFIA || $p->role == PLAYER_ROLE_DON)
                    {
                        if ($partner1 < 0)
                        {
                            $partner1 = $i;
                        }
                        else
                        {
                            $partner2 = $i;
                            break;
                        }
                    }
                }
            }

            $shooting_count = count($gs->shooting);
            for ($i = 0; $i < $shooting_count; ++$i)
            {
                $shooting = $gs->shooting[$i];
                if (!isset($shooting[$player_num]))
                {
                    continue;
                }

                switch (count($shooting))
                {
                    case 1:
                        if ($shooting[$player_num] >= 0)
                        {
                            ++$this->shots1_ok;
                        }
                        else
                        {
                            ++$this->shots1_miss;
                        }
                        break;

                    case 2:
                        $shot = $shooting[$player_num];
                        $partner_shot = -1;
                        if (isset($shooting[$partner1]))
                        {
                            $partner_shot = $shooting[$partner1];
                        }
                        else
                        {
                            $partner_shot = $shooting[$partner2];
                        }

                        if ($shot < 0)
                        {
                            ++$this->shots2_miss;
                            ++$this->shots2_blank;
                        }
                        else if ($shot != $partner_shot)
                        {
                            ++$this->shots2_miss;
                        }
                        else
                        {
                            ++$this->shots2_ok;
                            if ($gs->players[$partner_shot]->arranged != $i)
                            {
                                ++$this->shots2_rearrange;
                            }
                        }
                        break;

                    case 3:
                        $shot = $shooting[$player_num];
                        $partner_shot1 = $shooting[$partner1];
                        $partner_shot2 = $shooting[$partner2];
                        if ($shot < 0)
                        {
                            ++$this->shots3_blank;
                        }

                        if ($partner_shot1 == $partner_shot2 && $partner_shot1 >= 0)
                        {
                            if ($shot == $partner_shot1)
                            {
                                ++$this->shots3_ok;
                                if ($gs->players[$shot]->arranged != $i)
                                {
                                    ++$this->shots3_rearrange;
                                }
                            }
                            else
                            {
                                ++$this->shots3_miss;
                                ++$this->shots3_fail;
                            }
                        }
                        else
                        {
                            ++$this->shots3_miss;
                        }
                        break;
                }
            }
        }

        // Don
        $this->sheriff_found = -1;
        $this->sheriff_arranged = -1;
        if ($player->role == PLAYER_ROLE_DON)
        {
            for ($i = 0; $i < 10; ++$i)
            {
                $p = $gs->players[$i];
                if ($p->role == PLAYER_ROLE_SHERIFF)
                {
                    $this->sheriff_arranged = $p->arranged;
                    $this->sheriff_found = $p->don_check;
                    break;
                }
            }
        }
		
		$this->calculate_scoring_flags();

		// init points and ratings
		$this->rating_before = 0;
		$this->rating_earned = 0;
        $player = $gs->players[$this->player_num];
		if ($player->id <= 0)
		{
			return;
		}
		
		$query = new DbQuery('SELECT p.rating_before + p.rating_earned FROM players p JOIN games g ON p.game_id = g.id WHERE g.canceled = FALSE AND (g.start_time < ? OR (g.start_time = ? AND g.id < ?)) AND p.user_id = ? ORDER BY g.end_time DESC, g.id DESC LIMIT 1', $gs->end_time, $gs->end_time, $gs->id, $player->id);
		if ($row = $query->next())
		{
			list($this->rating_before) = $row;
		}
		else
		{
			$this->rating_before = USER_INITIAL_RATING;
		}
		
		$query = new DbQuery('SELECT p.rating_earned FROM players p JOIN games g ON p.game_id = g.id WHERE g.id = ? AND p.user_id = ?', $gs->id, $player->id);
		if ($row = $query->next())
		{
			list($this->rating_earned) = $row;
		}
    }
	
	function calculate_rating($civ_odds)
	{
        $gs = $this->gs;
        $player = $gs->players[$this->player_num];
		if ($player->id <= 0)
		{
			return;
		}

		$WINNING_K = 20;
		$LOOSING_K = 15;
		switch ($player->role)
		{
			case PLAYER_ROLE_CIVILIAN:
			case PLAYER_ROLE_SHERIFF:
				if ($gs->gamestate == GAME_CIVIL_WON)
				{
					$this->rating_earned = $WINNING_K * (1 - $civ_odds);
				}
				else
				{
					$this->rating_earned = - $LOOSING_K * $civ_odds;
				}
				break;
			case PLAYER_ROLE_MAFIA:
			case PLAYER_ROLE_DON:
				if ($gs->gamestate == GAME_CIVIL_WON)
				{
					$this->rating_earned = $LOOSING_K * ($civ_odds - 1);
				}
				else
				{
					$this->rating_earned = $WINNING_K * $civ_odds;
				}
				break;
		}
		
		// $this->rating_earned += 1;
		if ($this->rating_before + $this->rating_earned < USER_INITIAL_RATING)
		{
			$this->rating_earned = USER_INITIAL_RATING - $this->rating_before;
		}
	}
	
    function save()
    {
        $gs = $this->gs;
        $player = $gs->players[$this->player_num];
		if ($player->id <= 0)
		{
			return NULL;
		}
		
		$reason = NULL;
		if (!empty($player->extra_points_reason))
		{
			$reason = $player->extra_points_reason;
		}
		
        Db::exec(
			get_label('player'), 
            'INSERT INTO players (game_id, user_id, nick_name, number, role, rating_before, rating_earned, flags, ' .
				'voted_civil, voted_mafia, voted_sheriff, voted_by_civil, voted_by_mafia, voted_by_sheriff, ' .
				'nominated_civil, nominated_mafia, nominated_sheriff, nominated_by_civil, nominated_by_mafia, nominated_by_sheriff, ' .
				'kill_round, kill_type, warns, was_arranged, checked_by_don, checked_by_sheriff, won, extra_points, extra_points_reason) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$gs->id, $player->id, $player->nick, $this->player_num + 1, $player->role, $this->rating_before, $this->rating_earned, $this->scoring_flags,
			$this->voted_civil, $this->voted_mafia, $this->voted_sheriff, $this->voted_by_civil, $this->voted_by_mafia, $this->voted_by_sheriff,
			$this->nominated_civil, $this->nominated_mafia, $this->nominated_sheriff, $this->nominated_by_civil, $this->nominated_by_mafia, $this->nominated_by_sheriff,
			$player->kill_round, $this->kill_type, $player->warnings, $player->arranged, $player->don_check, $player->sheriff_check, $this->won, $player->extra_points, $reason);
			
		switch ($player->role)
		{
			case PLAYER_ROLE_CIVILIAN:
				break;
			case PLAYER_ROLE_SHERIFF:
				Db::exec(
					get_label('sheriff'), 
					'INSERT INTO sheriffs VALUES (?, ?, ?, ?)',
					$gs->id, $player->id, $this->civil_found, $this->mafia_found);
				break;
			case PLAYER_ROLE_DON:
				Db::exec(
					get_label('mafioso'), 
					'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == PLAYER_ROLE_DON ? 'true' : 'false') . ')',
					$gs->id, $player->id, $this->shots1_ok, $this->shots1_miss, $this->shots2_ok,
					$this->shots2_miss, $this->shots2_blank, $this->shots2_rearrange, $this->shots3_ok, $this->shots3_miss,
					$this->shots3_blank, $this->shots3_fail, $this->shots3_rearrange);
				Db::exec(
					get_label('don'), 
					'INSERT INTO dons VALUES (?, ?, ?, ?)',
					$gs->id, $player->id, $this->sheriff_found, $this->sheriff_arranged);
				break;
			case PLAYER_ROLE_MAFIA:
				Db::exec(
					get_label('mafioso'), 
					'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == PLAYER_ROLE_DON ? 'true' : 'false') . ')',
					$gs->id, $player->id, $this->shots1_ok, $this->shots1_miss, $this->shots2_ok,
					$this->shots2_miss, $this->shots2_blank, $this->shots2_rearrange, $this->shots3_ok, $this->shots3_miss,
					$this->shots3_blank, $this->shots3_fail, $this->shots3_rearrange);
				break;
		}
		
		if (!$gs->is_canceled)
		{
			$query = new DbQuery('UPDATE users SET rating = ?, games = games + 1, games_won = games_won + ?', $this->rating_before + $this->rating_earned, $this->won);
			if ($player->kill_round == 0 && $player->state == PLAYER_STATE_KILLED_NIGHT)
			{
				$query->add(', flags = (flags | ' . USER_FLAG_IMMUNITY . ')');
			}
			else
			{
				$query->add(', flags = (flags & ' . ~USER_FLAG_IMMUNITY . ')');
			}
			$query->add(' WHERE id = ?', $player->id);
			Db::exec(get_label('user'), $query);
		}
    }
	
	public function get_title()
	{
		$gs = $this->gs;
        $player = $gs->players[$this->player_num];
		return get_label('Game [0], player [1] - [2]', $gs->id, $this->player_num + 1, cut_long_name($player->nick, 66));
	}
}

function save_game_results($gs)
{
	if ($gs->id <= 0)
	{
		return NULL;
	}

	$update_stats = true;
    $game_result = 0;
    switch ($gs->gamestate)
    {
        case GAME_MAFIA_WON:
            $game_result = 2;
            break;

        case GAME_CIVIL_WON:
            $game_result = 1;
            break;

        default:
            throw new Exc(get_label('The game [0] is not finished yet.', $gs->id));
    }

	try
	{
		Db::begin();
		
		$best_player_id = NULL;
		if ($gs->best_player >= 0 && $gs->best_player < 10)
		{
			$best_player_id = $gs->players[$gs->best_player]->id;
			if ($best_player_id <= 0)
			{
				$best_player_id = NULL;
			}
		}
		
		if ($update_stats)
		{
			Db::exec(get_label('user'), 'UPDATE users u, games g SET u.games_moderated = u.games_moderated + 1 WHERE u.id = g.moderator_id AND g.id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $gs->id);
			$stats = array();
			for ($i = 0; $i < 10; ++$i)
			{
				$stats[] = new GamePlayerStats($gs, $i);
			}
			
			// calculate ratings
			$maf_sum = 0;
			$maf_count = 0;
			$civ_sum = 0;
			$civ_count = 0;
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $gs->players[$i];
				if ($player->id > 0)
				{
					switch ($player->role)
					{
						case PLAYER_ROLE_CIVILIAN:
						case PLAYER_ROLE_SHERIFF:
							$civ_sum += $stats[$i]->rating_before;
							++$civ_count;
							break;
						case PLAYER_ROLE_MAFIA:
						case PLAYER_ROLE_DON:
							$maf_sum += $stats[$i]->rating_before;
							++$maf_count;
							break;
					}
				}
			}
			
			$civ_odds = NULL;
			if ($maf_count > 0 && $civ_count > 0)
			{
				$civ_odds = 1.0 / (1.0 + pow(10.0, ($maf_sum / $maf_count - $civ_sum / $civ_count) / 400));
				for ($i = 0; $i < 10; ++$i)
				{
					$stats[$i]->calculate_rating($civ_odds);
				}
			}
			
			// save stats
			for ($i = 0; $i < 10; ++$i)
			{
				$stats[$i]->save();
			}
			Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ?, flags = ?, civ_odds = ? WHERE id = ?', $game_result, $best_player_id, $gs->flags, $civ_odds, $gs->id);
		}
		else
		{
			Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ?, flags = ?, WHERE id = ?', $game_result, $best_player_id, $gs->flags, $gs->id);
		}
		Db::commit();
	}
	catch (FatalExc $e)
	{
		Db::rollback();
		throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $gs->id), $e->get_details());
	}
	catch (Exception $e)
	{
		Db::rollback();
		throw new Exc(get_label('Unable to save game [2] stats for player #[0]: [1]', $i + 1, $e->getMessage(), $gs->id));
	}
}

function rebuild_game_stats($gs)
{
	if ($gs->id <= 0)
    {
		return;
	}

	Db::begin();
	Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated - 1 WHERE id = (SELECT moderator_id FROM games WHERE id = ?)', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $gs->id);
	
	$gs->save();
	save_game_results($gs);
	db_log(LOG_OBJECT_GAME, 'stats rebuilt', NULL, $gs->id, $gs->club_id);
	Db::commit();
}

?>