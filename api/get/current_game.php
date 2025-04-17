<?php

require_once '../../include/api.php';
require_once '../../include/game.php';
require_once '../../include/datetime.php';

define('CURRENT_VERSION', 0);

class ApiPage extends GetApiPageBase
{
	private function new_game()
	{
		global $_lang;
		
		$g = $this->game->data;
		$server_url = get_server_url();
		$game = new stdClass();
		
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$game->club = new stdClass();
		$game->club->name = $this->club_name;
		$game->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
		$game->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
		$game->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
		$game->club->hasPhoto = $this->club_pic->has_image();
		
		$game->city = $this->city;
		$game->country = $this->country;
		
		if (!is_null($this->tournament_id))
		{
			$this->tournament_pic->set($this->tournament_id, $this->tournament_name, $this->tournament_flags);
			$game->tournament = new stdClass();
			$game->tournament->name = $this->tournament_name;
			$game->tournament->photoUrl = $server_url . '/' . $this->tournament_pic->url(SOURCE_DIR);
			$game->tournament->tnailUrl = $server_url . '/' . $this->tournament_pic->url(TNAILS_DIR);
			$game->tournament->iconUrl = $server_url . '/' . $this->tournament_pic->url(ICONS_DIR);
			$game->tournament->hasPhoto = $this->tournament_pic->has_image();
			
			$game->stage = (int)$this->stage;
		}
		
		if (isset($g->id))
		{
			$game->id = $g->id;
		}
		if (isset($g->round))
		{
			$game->tour = $g->round;
		}
		if (isset($g->table))
		{
			$game->table = $g->table;
		}
		
		if (isset($game->tour))
		{
			if (isset($game->table))
			{
				$game->name = get_label('Table [0] / Game [1]', $game->table, $game->tour);
			}
			else
			{
				$game->name = get_label('Game [0]', $game->tour);
			}
		}
		else if (isset($game->id))
		{
			$game->name = get_label('Game #[0]', $this->gs->id);
		}
		
		$speaker = -1;
		if (isset($g->time) && isset($g->time->speaker))
		{
			$speaker = $g->time->speaker - 1;
		}
		
		$game->players = array();
		for ($i = 0; $i < count($g->players); ++$i)
		{
			$p = $g->players[$i];
			$player = new stdClass();
			if (isset($p->id))
			{
				$player->id = (int)$p->id;
			}
			if (isset($p->name))
			{
				$player->name = $p->name;
			}
			$player->number = $i + 1;
			$player->isSpeaking = ($i == $speaker);
			
			if (isset($player->id) && $player->id > 0)
			{
				list($event_user_flags, $tournament_user_flags, $club_user_flags, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $player->city, $player->country) = Db::record(get_label('user'), 
					'SELECT eu.flags, tu.flags, cu.flags, u.flags, c.id, c.name, c.flags, ni.name, no.name FROM users u' .
					' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
					' JOIN cities i ON i.id = u.city_id' .
					' JOIN countries o ON o.id = i.country_id' .
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
					' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' .
					' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
					' WHERE u.id = ?', 
					$this->event_id, $this->tournament_id, $this->club_id, $player->id);
				if ($user_flags & USER_FLAG_MALE)
				{
					$player->gender = 'male';
				}
				else
				{
					$player->gender = 'female';
				}
				
				if (!is_null($user_club_id))
				{
					$this->club_pic->set($user_club_id, $user_club_name, $user_club_flags);
					$player->club = new stdClass();
					$player->club->name = $user_club_name;
					$player->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
					$player->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
					$player->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
					$player->club->hasPhoto = $this->club_pic->has_image();
				}
			}
			else
			{
				$event_user_flags = $tournament_user_flags = $club_user_flags = $user_flags = 0;
			}
			$this->user_pic->
				set($player->id, $player->name, $event_user_flags, 'e' . $this->event_id)->
				set($player->id, $player->name, $tournament_user_flags, 't' . $this->tournament_id)->
				set($player->id, $player->name, $club_user_flags, 'c' . $this->club_id)->
				set($player->id, $player->name, $user_flags);
			$player->photoUrl = $server_url . '/' . $this->user_pic->url(SOURCE_DIR);
			$player->tnailUrl = $server_url . '/' . $this->user_pic->url(TNAILS_DIR);
			$player->iconUrl = $server_url . '/' . $this->user_pic->url(ICONS_DIR);
			$player->hasPhoto = $this->user_pic->has_image();
			
			if (!isset($p->role) || $p->role == 'civ')
			{
				$player->role = 'town';
			}
			else
			{
				$player->role = $p->role;
			}

			if (isset($p->warnings))
			{
				if (is_array($p->warnings))
				{
					$player->warnings = count($p->warnings);
				}
				else
				{
					$player->warnings = $p->warnings;
				}
			}
			else
			{
				$player->warnings = 0;
			}
			if (isset($p->don))
			{
				$player->checkedByDon = $p->don;
			}
			if (isset($p->sheriff))
			{
				$player->checkedBySheriff = $p->sheriff;
			}
			
			if (!isset($p->death))
			{
				$player->state = 'alive';
			}
			else
			{
				$player->state = 'dead';
				$player->deathRound = $p->death->round;
				switch ($p->death->type)
				{
				case DEATH_TYPE_GIVE_UP:
					$player->deathType = 'giveUp';
					break;
				case DEATH_TYPE_WARNINGS:
					$player->deathType = 'warnings';
					break;
				case DEATH_TYPE_KICK_OUT:
					$player->deathType = 'kickOut';
					break;
				case DEATH_TYPE_TEAM_KICK_OUT:
					$player->deathType = 'oppositeTeamWins';
					break;
				case DEATH_TYPE_NIGHT:
					$player->deathType = 'shooting';
					break;
				case DEATH_TYPE_DAY:
					$player->deathType = 'voting';
					break;
				}
			}
			
			if (isset($p->legacy))
			{
				$game->legacy = $p->legacy;
			}
			
			$game->players[] = $player;
		}
		
		if (isset($g->moderator))
		{
			$game->moderator = new stdClass();
			$game->moderator->id = (int)$g->moderator->id;
			if ($game->moderator->id > 0)
			{
				list($game->moderator->name, $event_user_flags, $tournament_user_flags, $club_user_flags, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $game->moderator->city, $game->moderator->country) = Db::record(get_label('user'), 
					'SELECT eu.nickname, eu.flags, tu.flags, cu.flags, nu.name, u.flags, c.id, c.name, c.flags, ni.name, no.name' .
					' FROM users u' .
					' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
					' JOIN cities i ON i.id = u.city_id' .
					' JOIN countries o ON o.id = i.country_id' .
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' .
					' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
					' WHERE u.id = ?', 
					$this->event_id, $this->tournament_id, $this->club_id, $game->moderator->id);
				if (is_null($game->moderator->name))
				{
					$game->moderator->name = $user_name;
				}
				
				if ($user_flags & USER_FLAG_MALE)
				{
					$game->moderator->gender = 'male';
				}
				else
				{
					$game->moderator->gender = 'female';
				}
			}
			else
			{
				$game->moderator->name = '';
				$event_user_flags = $tournament_user_flags = $club_user_flags = $user_flags = 0;
			}
			$this->user_pic->
				set($game->moderator->id, $game->moderator->name, $event_user_flags, 'e' . $this->event_id)->
				set($game->moderator->id, $game->moderator->name, $tournament_user_flags, 't' . $this->tournament_id)->
				set($game->moderator->id, $game->moderator->name, $club_user_flags, 'c' . $this->club_id)->
				set($game->moderator->id, $game->moderator->name, $user_flags);
			$game->moderator->photoUrl = $server_url . '/' . $this->user_pic->url(SOURCE_DIR);
			$game->moderator->tnailUrl = $server_url . '/' . $this->user_pic->url(TNAILS_DIR);
			$game->moderator->iconUrl = $server_url . '/' . $this->user_pic->url(ICONS_DIR);
			$game->moderator->hasPhoto = $this->user_pic->has_image();
			
			if (!is_null($user_club_id))
			{
				$this->club_pic->set($user_club_id, $user_club_name, $user_club_flags);
				$game->moderator->club = new stdClass();
				$game->moderator->club->name = $user_club_name;
				$game->moderator->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
				$game->moderator->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
				$game->moderator->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
				$game->moderator->club->hasPhoto = $this->club_pic->has_image();
			}
		}

		if (isset($g->time))
		{
			$game->round = $g->time->round;
			switch ($g->time->time)
			{
			case GAMETIME_START:
				$game->phase = 'night';
				$game->state = 'starting';
				break;
			case GAMETIME_ARRANGEMENT:
			case GAMETIME_RELAXED_SITTING:
				$game->phase = 'night';
				$game->state = 'arranging';
				break;
			case GAMETIME_DAY_START:
				$game->phase = 'day';
				$game->state = 'starting';
				break;
			case GAMETIME_NIGHT_KILL_SPEAKING:
				$game->phase = 'day';
				$game->state = 'nightKillSpeaking';
				break;
			case GAMETIME_SPEAKING:
				$game->phase = 'day';
				$game->state = 'speaking';
				break;
			case GAMETIME_VOTING_START:
			case GAMETIME_VOTING_KILL_ALL:
				$game->phase = 'day';
				$game->state = 'voting';
				break;
			case GAMETIME_VOTING:
				$game->phase = 'day';
				if (isset($g->time->speaker))
				{
					$game->state = 'nomineeSpeaking';
				}
				else
				{
					$game->state = 'voting';
				}
				break;
			case GAMETIME_DAY_KILL_SPEAKING:
				$game->phase = 'day';
				$game->state = 'dayKillSpeaking';
				break;
			case GAMETIME_NIGHT_START:
				$game->phase = 'night';
				$game->state = 'starting';
				break;
			case GAMETIME_SHOOTING:
				$game->phase = 'night';
				$game->state = 'shooting';
				break;
			case GAMETIME_DON:
				$game->phase = 'night';
				$game->state = 'donChecking';
				break;
			case GAMETIME_SHERIFF:
				$game->phase = 'night';
				$game->state = 'sheriffChecking';
				break;
			case GAMETIME_END:
				$game->phase = 'day';
				$game->state = 'end';
				if (isset($g->winner))
				{
					switch ($g->winner)
					{
					case 'maf':
						$game->state = 'mafiaWon';
						break;
					case 'civ':
						$game->state = 'townWon';
						break;
					case 'tie':
						$game->state = 'tie';
						break;
					}
				}
				break;
			default:
				$game->phase = 'day';
				$game->state = 'unknown';
				break;
			}
		}
		else
		{
			$game->round = 0;
			$game->phase = 'night';
			$game->state = 'notStarted';
		}

			
		if ($game->phase != 'night' && isset($g->time))
		{
			$game->nominees = array();
			$i = $first = $this->game->who_speaks_first($g->time->round);
			do
			{
				$p = $g->players[$i - 1];
				if (isset($p->nominating) && $g->time->round < count($p->nominating))
				{
					$game->nominees[] = $p->nominating[$g->time->round];
				}
				if (++$i > 10)
				{
					$i = 1;
				}					
			} while ($i != $first);
			
			$game->votingCanceled = $this->game->is_voting_canceled($g->time->round);
		}
		return $game;
	}
	
	private function old_game()
	{
		global $_lang;
		
		$server_url = get_server_url();
		$game = new stdClass();
		
		$this->club_pic->set($this->club_id, $this->club_name, $this->club_flags);
		$game->club = new stdClass();
		$game->club->name = $this->club_name;
		$game->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
		$game->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
		$game->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
		$game->club->hasPhoto = $this->club_pic->has_image();
		
		$game->city = $this->city;
		$game->country = $this->country;
		
		if (!is_null($this->tournament_id))
		{
			$this->tournament_pic->set($this->tournament_id, $this->tournament_name, $this->tournament_flags);
			$game->tournament = new stdClass();
			$game->tournament->name = $this->tournament_name;
			$game->tournament->photoUrl = $server_url . '/' . $this->tournament_pic->url(SOURCE_DIR);
			$game->tournament->tnailUrl = $server_url . '/' . $this->tournament_pic->url(TNAILS_DIR);
			$game->tournament->iconUrl = $server_url . '/' . $this->tournament_pic->url(ICONS_DIR);
			$game->tournament->hasPhoto = $this->tournament_pic->has_image();
			
			$game->stage = (int)$this->stage;
		}
		
		if (isset($this->gs->id))
		{
			$game->id = $this->gs->id;
			if (isset($this->gs->number))
			{
				$game->tour = $this->gs->number + 1;
				if (isset($this->gs->table))
				{
					$game->table = $this->gs->table + 1;
					$game->name = get_label('Table [0] / Game [1]', $game->table, $game->tour);
				}
				else
				{
					$game->name = get_label('Game [0]', $game->tour);
				}
			}
			else
			{
				$game->name = get_label('Game #[0]', $this->gs->id);
			}
		}
		switch ($this->gs->gamestate)
		{
			case /*GAME_STATE_NOT_STARTED*/0:
			case /*GAME_STATE_VOTING*/8:
			case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
				$speachPossible = false;
				break;
			default:
				$speachPossible  = true;
				break;
		}
		if (isset($this->gs->players))
		{
			$game->players = array();
			foreach ($this->gs->players as $p)
			{
				$player = new stdClass();
				$player->id = (int)$p->id;
				$player->name = $p->nick;
				$player->number = $p->number + 1;
				$player->isSpeaking = ($speachPossible && $p->number == $this->gs->player_speaking);
				
				if ($player->id > 0)
				{
					list($event_user_flags, $tournament_user_flags, $club_user_flags, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $player->city, $player->country) = Db::record(get_label('user'), 
						'SELECT eu.flags, tu.flags, cu.flags, u.flags, c.id, c.name, c.flags, ni.name, no.name FROM users u' .
						' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
						' JOIN cities i ON i.id = u.city_id' .
						' JOIN countries o ON o.id = i.country_id' .
						' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
						' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
						' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' .
						' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
						' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
						' WHERE u.id = ?', 
						$this->event_id, $this->tournament_id, $this->club_id, $player->id);
					if ($p->is_male)
					{
						$player->gender = 'male';
					}
					else
					{
						$player->gender = 'female';
					}
					
					if (!is_null($user_club_id))
					{
						$this->club_pic->set($user_club_id, $user_club_name, $user_club_flags);
						$player->club = new stdClass();
						$player->club->name = $user_club_name;
						$player->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
						$player->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
						$player->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
						$player->club->hasPhoto = $this->club_pic->has_image();
					}
				}
				else
				{
					$event_user_flags = $tournament_user_flags = $club_user_flags = $user_flags = 0;
				}
				$this->user_pic->
					set($player->id, $player->name, $event_user_flags, 'e' . $this->event_id)->
					set($player->id, $player->name, $tournament_user_flags, 't' . $this->tournament_id)->
					set($player->id, $player->name, $club_user_flags, 'c' . $this->club_id)->
					set($player->id, $player->name, $user_flags);
				$player->photoUrl = $server_url . '/' . $this->user_pic->url(SOURCE_DIR);
				$player->tnailUrl = $server_url . '/' . $this->user_pic->url(TNAILS_DIR);
				$player->iconUrl = $server_url . '/' . $this->user_pic->url(ICONS_DIR);
				$player->hasPhoto = $this->user_pic->has_image();
				
				switch ($p->role)
				{
					case 0:
						$player->role = 'town';
						break;
					case 1:
						$player->role = 'sheriff';
						break;
					case 2:
						$player->role = 'maf';
						break;
					case 3:
						$player->role = 'don';
						break;
				}
				$player->warnings = $p->warnings;
				if ($p->don_check >= 0)
				{
					$player->checkedByDon = $p->don_check + 1;
				}
				if ($p->sheriff_check >= 0)
				{
					$player->checkedBySheriff = $p->sheriff_check + 1;
				}
				
				if ($p->state == 0 /*PLAYER_STATE_ALIVE*/)
				{
					$player->state = 'alive';
				}
				else
				{
					$player->state = 'dead';
					$player->deathRound = $p->kill_round;
					switch ($p->kill_reason)
					{
						case 1 /*KILL_REASON_GIVE_UP*/:
							$player->deathType = 'giveUp';
							break;
						case 2 /*KILL_REASON_WARNINGS*/:
							$player->deathType = 'warnings';
							break;
						case 3 /*KILL_REASON_KICK_OUT*/:
							$player->deathType = 'kickOut';
							break;
						case 4 /*KILL_REASON_TEAM_KICK_OUT*/:
							$player->deathType = 'oppositeTeamWins';
							break;
						case 0 /*KILL_REASON_NORMAL*/:
						default:
							if ($p->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/)
							{
								$player->deathType = 'shooting';
							}
							else
							{
								$player->deathType = 'voting';
							}
							break;
					}
				}
				
				$game->players[] = $player;
			}
			
			$game->moderator = new stdClass();
			$game->moderator->id = (int)$this->gs->moder_id;
			if ($this->gs->moder_id > 0)
			{
				list($game->moderator->name, $event_user_flags, $tournament_user_flags, $club_user_flags, $user_name, $user_flags, $user_club_id, $user_club_name, $user_club_flags, $game->moderator->city, $game->moderator->country) = Db::record(get_label('user'), 
					'SELECT eu.nickname, eu.flags, tu.flags, cu.flags, nu.name, u.flags, c.id, c.name, c.flags, ni.name, no.name' .
					' FROM users u' .
					' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
					' JOIN cities i ON i.id = u.city_id' .
					' JOIN countries o ON o.id = i.country_id' .
					' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
					' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
					' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
					' LEFT OUTER JOIN event_users eu ON eu.user_id = u.id AND eu.event_id = ?' .
					' LEFT OUTER JOIN tournament_users tu ON tu.user_id = u.id AND tu.tournament_id = ?' .
					' LEFT OUTER JOIN club_users cu ON cu.user_id = u.id AND cu.club_id = ?' .
					' WHERE u.id = ?', 
					$this->event_id, $this->tournament_id, $this->club_id, $this->gs->moder_id);
				if (is_null($game->moderator->name))
				{
					$game->moderator->name = $user_name;
				}
				
				if ($user_flags & USER_FLAG_MALE)
				{
					$game->moderator->gender = 'male';
				}
				else
				{
					$game->moderator->gender = 'female';
				}
			}
			else
			{
				$game->moderator->name = '';
				$event_user_flags = $tournament_user_flags = $club_user_flags = $user_flags = 0;
			}
			$this->user_pic->
				set($this->gs->moder_id, $game->moderator->name, $event_user_flags, 'e' . $this->event_id)->
				set($this->gs->moder_id, $game->moderator->name, $tournament_user_flags, 't' . $this->tournament_id)->
				set($this->gs->moder_id, $game->moderator->name, $club_user_flags, 'c' . $this->club_id)->
				set($this->gs->moder_id, $game->moderator->name, $user_flags);
			$game->moderator->photoUrl = $server_url . '/' . $this->user_pic->url(SOURCE_DIR);
			$game->moderator->tnailUrl = $server_url . '/' . $this->user_pic->url(TNAILS_DIR);
			$game->moderator->iconUrl = $server_url . '/' . $this->user_pic->url(ICONS_DIR);
			$game->moderator->hasPhoto = $this->user_pic->has_image();
			
			if (!is_null($user_club_id))
			{
				$this->club_pic->set($user_club_id, $user_club_name, $user_club_flags);
				$game->moderator->club = new stdClass();
				$game->moderator->club->name = $user_club_name;
				$game->moderator->club->photoUrl = $server_url . '/' . $this->club_pic->url(SOURCE_DIR);
				$game->moderator->club->tnailUrl = $server_url . '/' . $this->club_pic->url(TNAILS_DIR);
				$game->moderator->club->iconUrl = $server_url . '/' . $this->club_pic->url(ICONS_DIR);
				$game->moderator->club->hasPhoto = $this->club_pic->has_image();
			}
			
			switch ($this->gs->gamestate)
			{
				case /*GAME_STATE_NOT_STARTED*/0:
					$game->phase = 'night';
					$game->state = 'notStarted';
					$game->round = 0;
					break;
				case /*GAME_STATE_NIGHT0_START*/1:
					$game->phase = 'night';
					$game->state = 'starting';
					$game->round = 0;
					break;
				case /*GAME_STATE_NIGHT0_ARRANGE*/2:
					$game->phase = 'night';
					$game->state = 'arranging';
					$game->round = 0;
					break;
				case /*GAME_STATE_DAY_START*/3:
					$game->phase = 'day';
					$game->state = 'starting';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_DAY_PLAYER_SPEAKING*/5:
					$game->phase = 'day';
					$game->state = 'speaking';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_VOTING_KILLED_SPEAKING*/7:
					$game->phase = 'day';
					$game->state = 'nightKillSpeaking';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_VOTING*/8:
				case /*GAME_STATE_VOTING_MULTIPLE_WINNERS*/9:
					$game->phase = 'day';
					$game->state = 'voting';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_VOTING_NOMINEE_SPEAKING*/10:
					$game->phase = 'day';
					$game->state = 'nomineeSpeaking';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_NIGHT_START*/11:
					$game->phase = 'night';
					$game->state = 'starting';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_NIGHT_SHOOTING*/12:
					$game->phase = 'night';
					$game->state = 'shooting';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_NIGHT_DON_CHECK*/13:
					$game->phase = 'night';
					$game->state = 'donChecking';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_NIGHT_SHERIFF_CHECK*/15:
					$game->phase = 'night';
					$game->state = 'sheriffChecking';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_MAFIA_WON*/17:
					$game->phase = 'day';
					$game->state = 'mafiaWon';
					$game->round = $this->gs->round;
					break;
				case /*GAME_STATE_CIVIL_WON*/18:
					$game->phase = 'day';
					$game->state = 'townWon';
					$game->round = $this->gs->round;
					break;
				default:
					$game->phase = 'day';
					$game->state = 'unknown';
					$game->round = $this->gs->round;
					break;
			}
			
			if ($game->phase != 'night')
			{
				$voting = NULL;
				foreach ($this->gs->votings as $v)
				{
					if ($v->round == $this->gs->round && $v->voting_round == 0)
					{
						$voting = $v;
						break;
					}
				}
				if ($voting != NULL)
				{
					$game->votingCanceled = ($voting->canceled != 0);
					$nominees = array();
					foreach ($voting->nominees as $n)
					{
						$nominees[] = $n->player_num + 1;
					}
					if ($this->gs->gamestate == 5 /*GAME_DAY_PLAYER_SPEAKING*/ && $this->gs->current_nominee >= 0)
					{
						$nominees[] = $this->gs->current_nominee + 1;
					}
					$game->nominees = $nominees;
				}
			}
		}
		if ($this->gs->guess3 != NULL)
		{
			$game->legacy = array();
			foreach ($this->gs->guess3 as $n)
			{
				if ($n >= 0 && $n < 10)
				{
					$game->legacy[] = $n + 1;
				}
			}
			sort($game->legacy);
		}
		return $game;
	}
	
	protected function prepare_response()
	{
		global $_lang;
		
		$this->user_pic =
			new Picture(USER_EVENT_PICTURE, 
			new Picture(USER_TOURNAMENT_PICTURE,
			new Picture(USER_CLUB_PICTURE,
			new Picture(USER_PICTURE))));
		$this->club_pic = new Picture(CLUB_PICTURE);
		$this->tournament_pic = new Picture(TOURNAMENT_PICTURE);
		
		$token = get_required_param('token');
		$game_id = (int)get_optional_param('game_id', 0);
		$user_id = (int)get_optional_param('user_id', 0);
		$moderator_id = (int)get_optional_param('moderator_id', 0);
		$event_id = (int)get_optional_param('event_id', 0);
		$tournament_id = (int)get_optional_param('tournament_id', 0);
		$table = (int)get_optional_param('table', -1);
		$number = (int)get_optional_param('number', -1);
		
		$query = new DbQuery(
			'SELECT g.game, e.security_token, t.security_token, e.id, e.name, e.flags, t.id, t.name, t.flags, e.round, c.id, c.name, c.flags, ni.name, no.name'.
			' FROM current_games g'.
			' JOIN events e ON e.id = g.event_id'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' JOIN clubs c ON c.id = e.club_id'.
			' JOIN addresses a ON a.id = e.address_id'.
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0');
		if ($user_id > 0)
		{
			$query->add(' AND g.user_id = ?', $user_id);
		}
		if ($event_id > 0)
		{
			$query->add(' AND g.event_id = ?', $event_id); 
		}
		if ($tournament_id > 0)
		{
			$query->add(' AND e.tournament_id = ?', $tournament_id); 
		}
		if ($table >= 0)
		{
			$query->add(' AND g.table_num = ?', $table); 
		}
		if ($number >= 0)
		{
			$query->add(' AND g.round_num = ?', $number); 
		}
		
		while ($row = $query->next())
		{
			list($game, $event_token, $tournament_token, $this->event_id, $this->event_name, $this->event_flags, $this->tournament_id, $this->tournament_name, $this->tournament_flags, $this->stage, $this->club_id, $this->club_name, $this->club_flags, $this->city, $this->country) = $row;
			if ((!is_null($event_token) && $event_token === $token) || (!is_null($tournament_token) && $tournament_token === $token))
			{
				$this->game = new Game($game);
				break;
			}
		}
		if (isset($this->game))
		{
			$this->response['game'] = $this->new_game();
			return;
		}
		
		$query = new DbQuery(
			'SELECT g.log, e.security_token, t.security_token, e.id, e.name, e.flags, t.id, t.name, t.flags, e.round, c.id, c.name, c.flags, ni.name, no.name'.
			' FROM games g'.
			' JOIN events e ON e.id = g.event_id'.
			' LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id'.
			' JOIN clubs c ON c.id = e.club_id'.
			' JOIN addresses a ON a.id = e.address_id'.
			' JOIN cities i ON i.id = a.city_id' .
			' JOIN countries o ON o.id = i.country_id' .
			' JOIN names ni ON ni.id = i.name_id AND (ni.langs & '.$_lang.') <> 0' .
			' JOIN names no ON no.id = o.name_id AND (no.langs & '.$_lang.') <> 0' .
			' WHERE g.result = 0');
		if ($game_id > 0)
		{
			$query->add(' AND g.id = ?', $game_id);
		}
		if ($moderator_id  > 0)
		{
			$query->add(' AND g.moderator_id = ?', $moderator_id);
		}
		if ($user_id  > 0)
		{
			$query->add(' AND g.user_id = ?', $user_id);
		}
		$query->add(' ORDER BY g.id');
		
		while ($row = $query->next())
		{
			list($log, $event_token, $tournament_token, $this->event_id, $this->event_name, $this->event_flags, $this->tournament_id, $this->tournament_name, $this->tournament_flags, $this->stage, $this->club_id, $this->club_name, $this->club_flags, $this->city, $this->country) = $row;
			if ((!is_null($event_token) && $event_token === $token) || (!is_null($tournament_token) && $tournament_token === $token))
			{
				$this->gs = json_decode($log);
				break;
			}
		}
		
		if (isset($this->gs))
		{
			$this->response['game'] = $this->old_game();
		}
	}
	
	protected function get_help()
	{
		$help = new ApiHelp(PERMISSION_EVERYONE);
		$help->request_param('token', 'Event or tournament security token. Token can be found by club manager by clicking on token button with this <img scr="../../images/obs.png" width="32" height="32"> for club\'s event/tournament.');
		$help->request_param('game_id', 'Return the specified game.', 'user_id or moderator_id must be set');
		$help->request_param('user_id', 'Return incomplete game for the specified user account.', 'game_id or moderator_id must be set');
		$help->request_param('moderator_id', 'Return incomplete game of the specified moderator.', 'user_id or game_id must be set');
		$param = $help->response_param('game', 'Game state.');
			$param->sub_param('id', 'Game id.');
			$tournament = $param->sub_param('tournament', 'Tournament information.', 'this is not a tournament game.');
				$tournament->sub_param('name', 'Tournament name.');
				$tournament->sub_param('name', 'Tournament name.');
				$tournament->sub_param('photoUrl', 'URL of the tournament logo as it was uploaded.');
				$tournament->sub_param('tnailUrl', 'URL of the tournament logo thumbnail (280x160 px).');
				$tournament->sub_param('iconUrl', 'URL of the tournament logo icon (70x70 px).');
				$tournament->sub_param('hasPhoto', 'True - if the tournament has custom photo.');
			$club = $param->sub_param('club', 'club information.');
				$club->sub_param('name', 'Club name.');
				$club->sub_param('name', 'Club name.');
				$club->sub_param('photoUrl', 'URL of the club logo as it was uploaded.');
				$club->sub_param('tnailUrl', 'URL of the club logo thumbnail (280x160 px).');
				$club->sub_param('iconUrl', 'URL of the club logo icon (70x70 px).');
				$club->sub_param('hasPhoto', 'True - if the club has custom photo.');
			$param->sub_param('city', 'City name where the game is played.');
			$param->sub_param('country', 'Country name where the game is played.');
			$param->sub_param('name', 'Game name.');
			$param->sub_param('table', 'Table number staring from 1.', 'the table number is unknown.');
			$param->sub_param('tour', 'Game number (we call it a tour number) in the tournament staring from 1.', 'the tour is unknown.');
			$param->sub_param('stage', 'Tournament stage. 0 for the main stage; 1 - for the finals; 2 - for the semi-finals; 3 - quater-finals; etc');
			$players = $param->sub_param('players', 'Players.');
				$players->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				$players->sub_param('name', 'Player nickname.');
				$club = $players->sub_param('club', 'Player\'s club.', 'player has no club.');				
					$club->sub_param('name', 'Club name.');
					$club->sub_param('name', 'Club name.');
					$club->sub_param('photoUrl', 'URL of the club logo as it was uploaded.');
					$club->sub_param('tnailUrl', 'URL of the club logo thumbnail (280x160 px).');
					$club->sub_param('iconUrl', 'URL of the club logo icon (70x70 px).');
					$club->sub_param('hasPhoto', 'True - if the club has custom photo.');
				$players->sub_param('city', 'Player\'s city.');
				$players->sub_param('country', 'Player\'s country.');
				$players->sub_param('number', 'Number in the game.');
				$players->sub_param('photoUrl', 'A link to the user photo as it was uploaded. If user is missing - a link to a transparent image.');
				$players->sub_param('tnailUrl', 'URL of the user logo thumbnail. If user is missing - a link to a transparent image. (280x160 px).');
				$players->sub_param('iconUrl', 'URL of the user logo icon. If user is missing - a link to a transparent image. (70x70 px).');
				$players->sub_param('hasPhoto', 'True - if a player has custom photo. False - when player did not upload photo, or when id<=0, which means there is no player.');
				$players->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
				$players->sub_param('role', 'One of: "town", "sheriff", "maf", or "don".');
				$players->sub_param('warnings', 'Number of warnings.');
				$players->sub_param('isSpeaking', 'A boolean which is true when the player is speaking.');
				$players->sub_param('state', 'Player state - "dead" or "alive".');
				$players->sub_param('deathRound', 'If player state is "dead" it is set to the round number when they died.');
				$players->sub_param('deathType', 'If player state is "dead" it is set to the type of their death. One of: "voting", "shooting", "warnings", "giveUp", or "kickOut".');
				$players->sub_param('checkedByDon', 'If a player was checked by the don it contains the round number when it happened.');
				$players->sub_param('checkedBySheriff', 'If a player was checked by the sheriff it contains the round number when it happened.');
			$moderator = $param->sub_param('moderator', 'Moderator.');
				$moderator->sub_param('id', 'User id. If 0 or lower - the player is unknown.');
				$moderator->sub_param('name', 'Moderator nickname.');
				$club = $players->sub_param('club', 'Moderator\'s club.', 'player has no club.');				
					$club->sub_param('name', 'Club name.');
					$club->sub_param('name', 'Club name.');
					$club->sub_param('photoUrl', 'URL of the club logo as it was uploaded.');
					$club->sub_param('tnailUrl', 'URL of the club logo thumbnail (280x160 px).');
					$club->sub_param('iconUrl', 'URL of the club logo icon (70x70 px).');
					$club->sub_param('hasPhoto', 'True - if the club has custom photo.');
				$players->sub_param('city', 'Moderator\'s city.');
				$players->sub_param('country', 'Moderator\'s country.');
				$moderator->sub_param('photoUrl', 'A link to the moderator photo as it was uploaded. If user is missing - a link to a transparent image.');
				$moderator->sub_param('tnailUrl', 'URL of the moderator logo thumbnail. If user is missing - a link to a transparent image. (280x160 px).');
				$moderator->sub_param('iconUrl', 'URL of the moderator logo icon. If user is missing - a link to a transparent image. (70x70 px).');
				$moderator->sub_param('hasPhoto', 'True - if a moderator has custom photo. False - when moderator did not upload photo, or when id<=0, which means there is no moderator yet.');
				$moderator->sub_param('gender', 'Either "mail" or "female".', 'the gender is unknown.');
 			$param->sub_param('phase', 'Current game phase - "day" or "night".');
 			$param->sub_param('state', 'Contains more detailed information about the game phase - which part of the day or night. One of:<ul><li>"notStarted" - when the game is not started yet.</li><li>"starting" - night before shooting, or day before any speaches.</li><li>"arranging" - mafia is arranging in night 0.</li><li>"speaking" - normal day speaches.</li><li>"nightKillSpeaking" - a player gives their last speach after being night-shooted.</li><li>"voting" - voting phase.</li><li>"nomineeSpeaking" - 30-sec speach after splitting the table.</li><li>"shooting" - mafia is shooting.</li><li>"donChecking" - don is checking.</li><li>"sheriffChecking" - sheriff is checking.</li><li>"mafiaWon" - game over mafia won.</li><li>"townWon" - game over town won.</li><li>"unknown" - something strange happening.</li></ul>');
 			$param->sub_param('round', 'Current round number. Game starts with night-0; then day-0; then night-1; day-1; etc.');
 			$param->sub_param('nominees', 'Array of players currently nominated. It is set only in the day phase.');
 			$param->sub_param('votingCanceled', 'Boolean which is true when votings were canceled (most likely because someone was mod-killed). It is set only in the day phase.');
				
		return $help;
	}
}

$page = new ApiPage();
$page->run('Get Currently Played Game', CURRENT_VERSION);

?>