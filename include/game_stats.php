<?php

require_once 'include/game_state.php';
require_once 'include/localization.php';
require_once 'include/rating_system.php';

class GamePlayerStats
{
    public $gs;
    public $player_num;
    public $rating;
	public $won;
	public $flags;
   
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
	
	private function calculate_flags()
	{
		$gs = $this->gs;
        $player = $gs->players[$this->player_num];
		$this->flags = 0;
		if ($gs->best_player == $this->player_num)
		{
			$this->flags |= RATING_BEST_PLAYER;
		}
		if ($gs->best_move == $this->player_num)
		{
			$this->flags |= RATING_BEST_MOVE;
		}
		if ($gs->is_good_guesser($this->player_num))
		{
			$this->flags |= RATING_GUESS_ALL_MAF;
		}
		
		switch ($player->role)
		{
		case PLAYER_ROLE_CIVILIAN:
			if ($gs->gamestate == GAME_CIVIL_WON)
			{
				$this->flags |= RATING_WIN_CIV;
			}
			else
			{
				$this->flags |= RATING_LOS_CIV;
			}
			
			if ($this->voted_civil + $this->voted_sheriff == 0 && $this->voted_mafia >= 3)
			{
				$this->flags |= RATING_NO_VOTE_FOR_RED;
			}
			break;
			
		case PLAYER_ROLE_SHERIFF:
			if ($gs->gamestate == GAME_CIVIL_WON)
			{
				$this->flags |= RATING_WIN_SRF;
			}
			else
			{
				$this->flags |= RATING_LOS_SRF;
			}
			
			if ($this->mafia_found == 3)
			{
				$three_dark_checks = true;
				foreach ($gs->players as $player)
				{
					if ($player->role == PLAYER_ROLE_MAFIA || $player->role == PLAYER_ROLE_DON)
					{
						if ($player->sheriff_check < 0 || $player->sheriff_check > 2)
						{
							$three_dark_checks = false;
							break;
						}
					}
				}
				if ($three_dark_checks)
				{
					$this->flags |= RATING_THREE_DARK_CHECKS;
				}
			}
			break;
			
		case PLAYER_ROLE_MAFIA:
			if ($gs->gamestate == GAME_CIVIL_WON)
			{
				$this->flags |= RATING_LOS_MAF;
			}
			else
			{
				$this->flags |= RATING_WIN_MAF;
			}
			for ($i = 0; $i < 10; ++$i)
			{
				$p = $gs->players[$i];
				if ($p->role == PLAYER_ROLE_SHERIFF && $p->kill_round == 1 && $p->state == PLAYER_STATE_KILLED_NIGHT && $p->don_check == 0 && $p->arranged != 1)
				{
					$this->flags |= RATING_FIND_AND_KILL_SRF_MAF;
					break;
				}
			}
			break;
			
		case PLAYER_ROLE_DON:
			if ($gs->gamestate == GAME_CIVIL_WON)
			{
				$this->flags |= RATING_LOS_DON;
			}
			else
			{
				$this->flags |= RATING_WIN_DON;
			}
			
			for ($i = 0; $i < 10; ++$i)
			{
				$p = $gs->players[$i];
				if ($p->role == PLAYER_ROLE_SHERIFF)
				{
					if ($p->arranged == 0)
					{
						$this->flags |= RATING_ARRANGED_SRF;
					}
					if ($p->don_check == 0)
					{
						$this->flags |= RATING_FIND_SRF;
						if ($p->role == PLAYER_ROLE_SHERIFF && $p->kill_round == 1 && $p->state == PLAYER_STATE_KILLED_NIGHT && $p->arranged != 1)
						{
							$this->flags |= RATING_FIND_AND_KILL_SRF_DON;
						}
					}
					break;
				}
			}
			break;
		}
	}

	function __construct($gs, $player_num)
    {
		$this->timestamp = time();
	
        $player = $gs->players[$player_num];

        $this->gs = $gs;
        $this->player_num = $player_num;
        $this->rating = $gs->get_rating($player_num);
		
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
		
		$this->calculate_flags();
    }
	
	function add_rating($user_id, $role)
	{
		$query = new DbQuery('SELECT id, span FROM rating_types');
		while ($row = $query->next())
		{
			list($type_id, $type_span) = $row;
			if ($type_span > 0 && $this->gs->start_time <= $this->timestamp - $type_span)
			{
				continue;
			}
			
			Db::exec(
				get_label('rating'), 
				'UPDATE ratings SET rating = rating + ?, games = games + 1, games_won = games_won + ? WHERE user_id = ? AND type_id = ? AND role = ?',
				$this->rating, $this->won, $user_id, $type_id, $role);
			if (Db::affected_rows() <= 0)
			{
				Db::exec(
					get_label('rating'), 
					'INSERT INTO ratings (user_id, type_id, role, rating, games, games_won) VALUES (?, ?, ?, ?, 1, ?)',
					$user_id, $type_id, $role, $this->rating, $this->won);
			}
			
			Db::exec(
				get_label('rating'), 
				'UPDATE club_ratings SET rating = rating + ?, games = games + 1, games_won = games_won + ? WHERE club_id = ? AND user_id = ? AND type_id = ? AND role = ?',
				$this->rating, $this->won, $this->gs->club_id, $user_id, $type_id, $role);
			if (Db::affected_rows() <= 0)
			{
				Db::exec(
					get_label('rating'), 
					'INSERT INTO club_ratings (club_id, user_id, type_id, role, rating, games, games_won) VALUES (?, ?, ?, ?, ?, 1, ?)',
					$this->gs->club_id, $user_id, $type_id, $role, $this->rating, $this->won);
			}
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
		
        Db::exec(
			get_label('player'), 
            'INSERT INTO players (game_id, user_id, nick_name, number, role, rating, flags, ' .
				'voted_civil, voted_mafia, voted_sheriff, voted_by_civil, voted_by_mafia, voted_by_sheriff, ' .
				'nominated_civil, nominated_mafia, nominated_sheriff, nominated_by_civil, nominated_by_mafia, nominated_by_sheriff, ' .
				'kill_round, kill_type, warns, was_arranged, checked_by_don, checked_by_sheriff, won) ' .
				'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$gs->id, $player->id, $player->nick, $this->player_num + 1, $player->role, $this->rating, $this->flags,
			$this->voted_civil, $this->voted_mafia, $this->voted_sheriff, $this->voted_by_civil, $this->voted_by_mafia, $this->voted_by_sheriff,
			$this->nominated_civil, $this->nominated_mafia, $this->nominated_sheriff, $this->nominated_by_civil, $this->nominated_by_mafia, $this->nominated_by_sheriff,
			$player->kill_round, $this->kill_type, $player->warnings, $player->arranged, $player->don_check, $player->sheriff_check, $this->won);

        if ($player->role == PLAYER_ROLE_CIVILIAN)
        {
			$this->add_rating($player->id, RATING_ALL);
			$this->add_rating($player->id, RATING_CIVIL);
			$this->add_rating($player->id, RATING_RED);
        }
        else if ($player->role == PLAYER_ROLE_SHERIFF)
        {
            Db::exec(
				get_label('sheriff'), 
                'INSERT INTO sheriffs VALUES (?, ?, ?, ?)',
				$gs->id, $player->id, $this->civil_found, $this->mafia_found);
			
			$this->add_rating($player->id, RATING_ALL);
			$this->add_rating($player->id, RATING_SHERIFF);
			$this->add_rating($player->id, RATING_RED);
        }
        else
        {
            Db::exec(
				get_label('mafioso'), 
                'INSERT INTO mafiosos VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ' . ($player->role == PLAYER_ROLE_DON ? 'true' : 'false') . ')',
				$gs->id, $player->id, $this->shots1_ok, $this->shots1_miss, $this->shots2_ok,
				$this->shots2_miss, $this->shots2_blank, $this->shots2_rearrange, $this->shots3_ok, $this->shots3_miss,
				$this->shots3_blank, $this->shots3_fail, $this->shots3_rearrange);
			
			$this->add_rating($player->id, RATING_ALL);
			$this->add_rating($player->id, RATING_DARK);

            if ($player->role == PLAYER_ROLE_MAFIA)
            {
				$this->add_rating($player->id, RATING_MAFIA);
            }
            else // DON
            {
                Db::exec(
					get_label('don'), 
                    'INSERT INTO dons VALUES (?, ?, ?, ?)',
					$gs->id, $player->id, $this->sheriff_found, $this->sheriff_arranged);

				$this->add_rating($player->id, RATING_DON);
            }
        }
		
		if ($player->kill_round == 0 && $player->state == PLAYER_STATE_KILLED_NIGHT)
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags | ' . U_FLAG_IMMUNITY . ') WHERE id = ?', $player->id);
		}
		else
		{
			Db::exec(get_label('user'), 'UPDATE users SET flags = (flags & ' . ~U_FLAG_IMMUNITY . ') WHERE id = ?', $player->id);
		}
    }
	
	public function get_title()
	{
		$gs = $this->gs;
        $player = $gs->players[$this->player_num];
		return get_label('Game [0], player [1] - [2]', $gs->id, $this->player_num + 1, cut_long_name($player->nick, 66));
	}
	
	private function output_row($civil, $mafia, $sheriff, $title, $for_word)
	{
		$count = $civil + $mafia + $sheriff;
		$delimiter = ': ';
		echo '<tr class="light"><td width="200" class="dark">' . $title . '</td><td>';
		switch ($count)
		{
			case 0:
				echo '&nbsp;</td></tr>';
				return;
			case 1:
				echo get_label('1 time') . ':';
				if ($mafia > 0)
				{
					echo $for_word . get_label('mafia');
				}
				else if ($civil > 0)
				{
					echo $for_word . get_label('civilian');
				}
				else if ($sheriff > 0)
				{
					echo $for_word . get_label('sheriff');
				}
				echo '</td></tr>';
				break;
			default:
				echo $count . ' '.get_label('times');
				if ($mafia > 0)
				{
					echo $delimiter . $mafia . $for_word . ' '.get_label('mafia');
					$delimiter = '; ';
				}
				
				switch ($civil)
				{
					case 0:
						break;
					case 1:
						echo $delimiter . $civil . $for_word . ' '.get_label('civilian');
						$delimiter = '; ';
						break;
					default:
						echo $delimiter . $civil . $for_word . ' '.get_label('civilians');
						$delimiter = '; ';
						break;
				}
				
				if ($sheriff > 0)
				{
					echo $delimiter . $sheriff . $for_word . ' '.get_label('sheriff');
				}
				echo '</td></tr>';
				break;
		}
	}
	
	public function output()
	{
		$gs = $this->gs;
		
		$player = $gs->players[$this->player_num];

		echo '<p><table class="bordered" width="100%" id="players">';
		echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('General') . '</b></td></tr>';
		echo '<tr class="light"><td class="dark" width="200">'.get_label('Role').':</td><td>' . $player->role_text(true) . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Rating points earned').':</td><td>' . $this->rating . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Warnings').':</td><td>' . $player->warnings_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Killed').':</td><td>' . $player->killed_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Was arranged by mafia at').':</td><td>' . $player->arranged_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Checked by the Don').':</td><td>' . $player->don_check_text() . '</td></tr>';
		echo '<tr class="light"><td class="dark">'.get_label('Checked by the Sheriff').':</td><td>' . $player->sheriff_check_text() . '</td></tr>';
		echo '</table></p>';
		
		echo '<p><table class="bordered" width="100%" id="Table1">';
		echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('Voting and nominating') . '</b></td></tr>';
		$this->output_row($this->voted_civil, $this->voted_mafia, $this->voted_sheriff, get_label('Voted') . ':', ' '.get_label('for').' ');
		$this->output_row($this->voted_by_civil, $this->voted_by_mafia, $this->voted_by_sheriff, get_label('Was voted') . ':', ' '.get_label('by').' ');
		$this->output_row($this->nominated_civil, $this->nominated_mafia, $this->nominated_sheriff, get_label('Nominated') . ':', ' ');
		$this->output_row($this->nominated_by_civil, $this->nominated_by_mafia, $this->nominated_by_sheriff, get_label('Was nominated') . ':', ' '.get_label('by').' ');
		echo '</table></p>';

		if ($player->role == PLAYER_ROLE_SHERIFF)
		{
			echo '<p><table class="bordered" width="100%" id="Table2">';
			echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('The Sheriff checking') . '</b></td></tr>';
			$count = $this->civil_found + $this->mafia_found;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Civilians found').':</td><td>' . $this->civil_found . ' (' . number_format($this->civil_found*100.0/$count, 1) . '%)</td></tr>';
				echo '<tr class="light"><td class="dark">'.get_label('Mafiosos found').':</td><td>' . $this->mafia_found . ' (' . number_format($this->mafia_found*100.0/$count, 1) . '%)</td></tr>';
			}
			else
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Civilians found').':</td><td>&nbsp;</td></tr>';
				echo '<tr class="light"><td class="dark">'.get_label('Mafiosos found').':</td><td>&nbsp;</td></tr>';
			}
			echo '</table><p>';
		}

		if ($player->role == PLAYER_ROLE_MAFIA || $player->role == PLAYER_ROLE_DON)
		{
			echo '<p><table class="bordered" width="100%" id="Table3">';
			echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('Mafia shooting') . '</b></td></tr>';
			$count = $this->shots1_ok + $this->shots2_ok + $this->shots3_ok + $this->shots1_miss + $this->shots2_miss + $this->shots3_miss;
			if ($count > 0)
			{
				$shots_ok = $this->shots1_ok + $this->shots2_ok + $this->shots3_ok;
				echo '<tr class="light"><td class="dark" width="200">'.get_label('Shooting').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $shots_ok . ' '.get_label('successful').' (' . number_format($shots_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $this->shots3_ok + $this->shots3_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('3 shooters').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $this->shots3_ok . ' '.get_label('successful').' (' . number_format($this->shots3_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $this->shots2_ok + $this->shots2_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('2 shooters').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $this->shots2_ok . ' '.get_label('successful').' (' . number_format($this->shots2_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			$count = $this->shots1_ok + $this->shots1_miss;
			if ($count > 0)
			{
				echo '<tr class="light"><td class="dark" width="200">'.get_label('1 shooter').':</td><td>' . $count . ' '.get_label('shot');
				if ($count > 1)
				{
					echo get_label('s');
				}
				echo '; ' . $this->shots1_ok . ' '.get_label('successful').' (' . number_format($this->shots1_ok*100.0/$count, 1) . '%)</td></tr>';
			}

			echo '</table></p>';

			if ($player->role == PLAYER_ROLE_DON)
			{
				echo '<p><table class="bordered" width="100%" id="Table4">';
				echo '<tr class="th-short darker"><td colspan="2"><b>' . get_label('The Don\'s game') . '</b></td></tr>';
				if ($this->sheriff_found >= 0)
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff found').':</td><td>' . ($this->sheriff_found + 1) . ' '.get_label('night').'</td></tr>';
				}
				else
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff found').':</td><td>'.get_label('no').'</td></tr>';
				}
				if ($this->sheriff_arranged >= 0)
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff arranged').':</td><td>' . ($this->sheriff_arranged + 1) . ' '.get_label('night').'</td></tr>';
				}
				else
				{
					echo '<tr class="light"><td class="dark" width="200">'.get_label('Sheriff arranged').':</td><td>'.get_label('no').'</td></tr>';
				}
				echo '</table></p>';
			}
		}
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

        case GAME_TERMINATED:
			$update_stats = false;
            $game_result = 3;
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
		
		Db::exec(get_label('game'), 'UPDATE games SET result = ?, best_player_id = ?, flags = ? WHERE id = ?', $game_result, $best_player_id, $gs->flags, $gs->id);
		if ($update_stats)
		{
			Db::exec(get_label('user'), 'UPDATE users u, games g SET u.games_moderated = u.games_moderated + 1 WHERE u.id = g.moderator_id AND g.id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $gs->id);
			Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $gs->id);
			for ($i = 0; $i < 10; ++$i)
			{
				$stats = new GamePlayerStats($gs, $i);
				$stats->save();
			}
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
	
/*	player.role    rating.role
	0         0, 1, 3
	1         0, 1, 4
	2         0, 2, 5
	3         0, 2, 6
	
	C: r.role == 0 || r.role == p.role + 3 || r.role == p.role / 2 + 1;
	SQL: (r.role = 0 OR r.role = p.role + 3 OR r.role = (p.role DIV 2 + 1));
	
	PLAYER_ROLE_CIVILIAN = 0
	PLAYER_ROLE_SHERIFF = 1
	PLAYER_ROLE_MAFIA = 2
	PLAYER_ROLE_DON = 3

	RATING_ALL = 0
	RATING_RED = 1
	RATING_DARK = 2
	RATING_CIVIL = 3
	RATING_SHERIFF = 4
	RATING_MAFIA = 5
	RATING_DON = 6*/
	Db::exec(get_label('rating'),
		'UPDATE ratings r, players p, rating_types t, games g SET r.rating = r.rating - p.rating, r.games = r.games - 1, r.games_won = r.games_won - p.won WHERE p.game_id = g.id AND r.type_id = t.id ' .
		' AND g.id = ?' . 
		' AND (t.span = 0 OR g.start_time > t.renew_time - t.span) ' .
		' AND r.user_id = p.user_id' .
		' AND (r.role = 0 OR r.role = p.role + 3 OR r.role = (p.role DIV 2 + 1))',
		$gs->id);
	
	Db::exec(get_label('rating'), 
		'UPDATE club_ratings r, players p, rating_types t, games g SET r.rating = r.rating - p.rating, r.games = r.games - 1, r.games_won = r.games_won - p.won WHERE p.game_id = g.id AND r.type_id = t.id ' .
		' AND g.id = ?' . 
		' AND r.club_id = g.club_id' .
		' AND (t.span = 0 OR g.start_time > t.renew_time - t.span)' .
		' AND r.user_id = p.user_id' .
		' AND (r.role = 0 OR r.role = p.role + 3 OR r.role = (p.role DIV 2 + 1))',
		$gs->id);
	
	Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $gs->id);
	Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $gs->id);
	
	$gs->full_save();
	save_game_results($gs);
	db_log('game', 'Stats rebuilt', NULL, $gs->id, $gs->club_id);
	Db::commit();
}

?>