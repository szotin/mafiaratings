<?php

require_once __DIR__ . '/game_state.php';
require_once __DIR__ . '/rules.php';

define('GAME_FEATURE_FLAG_ARRANGEMENT',                 0x00000001);
define('GAME_FEATURE_FLAG_DON_CHECKS',                  0x00000002);
define('GAME_FEATURE_FLAG_SHERIFF_CHECKS',              0x00000004);
define('GAME_FEATURE_FLAG_DEATH',                       0x00000008);
define('GAME_FEATURE_FLAG_DEATH_ROUND',                 0x00000010);
define('GAME_FEATURE_FLAG_DEATH_TYPE',                  0x00000020);
define('GAME_FEATURE_FLAG_DEATH_TIME',                  0x00000040);
define('GAME_FEATURE_FLAG_LEGACY',                      0x00000080);
define('GAME_FEATURE_FLAG_SHOOTING',                    0x00000100);
define('GAME_FEATURE_FLAG_VOTING',                      0x00000200);
define('GAME_FEATURE_FLAG_VOTING_KILL_ALL',             0x00000400);
define('GAME_FEATURE_FLAG_NOMINATING',                  0x00000800);
define('GAME_FEATURE_FLAG_WARNINGS',                    0x00001000);
define('GAME_FEATURE_FLAG_WARNINGS_DETAILS',            0x00002000);

define('GAME_FEATURE_MASK_ALL',                         0x00003fff);
define('GAME_FEATURE_MASK_MAFIARATINGS',                0x00003bff); // ARRANGEMENT | DON_CHECKS | SHERIFF_CHECKS | DEATH | DEATH_ROUND | DEATH_TYPE | DEATH_TIME | LEGACY | SHOOTING | VOTING | NOMINATING | WARNINGS | WARNINGS_DETAILS

define('GAMETIME_START', 'start'); // night
define('GAMETIME_ARRANGEMENT', 'arrangement'); // night
define('GAMETIME_DAY_START', 'day start'); // day
define('GAMETIME_NIGHT_KILL_SPEAKING', 'night kill speaking'); // day
define('GAMETIME_SPEAKING', 'speaking'); // day
define('GAMETIME_VOTING', 'voting'); // day
define('GAMETIME_DAY_KILL_SPEAKING', 'day kill speaking'); // day
define('GAMETIME_SHOOTING', 'shooting'); // night
define('GAMETIME_DON', 'don'); // night
define('GAMETIME_SHERIFF', 'sheriff'); // night
define('GAMETIME_END', 'end'); // day

define('DEATH_TYPE_GIVE_UP', 'giveUp');
define('DEATH_TYPE_WARNINGS', 'warnings');
define('DEATH_TYPE_KICK_OUT', 'kickOut');
define('DEATH_TYPE_TEAM_KICK_OUT', 'teamKickOut'); // Kicked out with team loose
define('DEATH_TYPE_NIGHT', 'night');
define('DEATH_TYPE_DAY', 'day');

class Game
{
	public $data;
	
	function __construct($g, $feature_flags = GAME_FEATURE_MASK_MAFIARATINGS)
	{
		try
		{
			$this->data = NULL;
			$this->expected_flags = $feature_flags;
			if (is_numeric($g))
			{
				$row = Db::record(get_label('game'), 'SELECT json, feature_flags FROM games WHERE id = ?', (int)$g);
				$this->data = json_decode($row[0]);
				$this->expected_flags = (int)$row[1];
			}
			else if (is_string($g))
			{
				$this->data = json_decode($g);
			}
			else if ($g instanceof Game)
			{
				$this->data = clone $g->data;
			}
			else if ($g instanceof GameState)
			{
				$this->data = new stdClass();
				$this->data->id = $g->id;
				$this->data->clubId = $g->club_id;
				$this->data->eventId = $g->event_id;
				$this->data->startTime = $g->start_time;
				$this->data->endTime = $g->end_time;
				$this->data->language = get_lang_code($g->lang);
				$this->data->rules = $g->rules_code;
				if ($g->gamestate == GAME_MAFIA_WON)
				{
					$this->data->winner = 'maf';
				}
				else if ($g->gamestate == GAME_CIVIL_WON)
				{
					$this->data->winner = 'civ';
				}
				else
				{
					throw new Exc('The game ' . $g->id . ' is not finished yet.');
				}
				$this->data->moderator = new stdClass();
				$this->data->moderator->id = $g->moder_id;
				$this->data->players = array();
				for ($i = 0; $i < 10; ++$i)
				{
					$p = $g->players[$i];
					$player = new stdClass();
					$player->id = $p->id;
					$player->name = $p->nick;
					if ($p->arranged >= 0)
					{
						$player->arranged = $p->arranged + 1;
					}
					if ($p->don_check >= 0)
					{
						$player->don = $p->don_check + 1;
					}
					if ($p->sheriff_check >= 0)
					{
						$player->sheriff = $p->sheriff_check + 1;
					}
					if ($p->extra_points)
					{
						$player->extra_points = $p->extra_points;
					}
					if (!empty($p->comment))
					{
						$player->comment = $p->comment;
					}
					switch ($p->role)
					{
						case PLAYER_ROLE_SHERIFF:
							$player->role = 'sheriff';
							break;
						case PLAYER_ROLE_DON:
							$player->role = 'don';
							break;
						case PLAYER_ROLE_MAFIA:
							$player->role = 'maf';
							break;
						case PLAYER_ROLE_CIVILIAN: 
							// If role is not set - civ is assumed, so we are not setting role in this case. Although 'civ' can also be set.
							// $player->role = 'civ';
							break;
						default:
							throw new Exc('Invalid role for player ' . ($i + 1) . ': ' . $p->role);
					}
					if ($p->state != PLAYER_STATE_ALIVE)
					{
						$player->death = new stdClass();
						$player->death->round = $p->kill_round;
						if ($p->state == PLAYER_STATE_KILLED_NIGHT)
						{
							if ($p->kill_round == 0 && $g->guess3 != NULL)
							{
								foreach ($g->guess3 as $n)
								{
									if ($n >= 0 && $n < 10)
									{
										$player->legacy[] = $n + 1;
									}
								}
							}
							++$player->death->round;
						}
						switch ($p->kill_reason)
						{
							case KILL_REASON_GIVE_UP:
								$player->death->type = DEATH_TYPE_GIVE_UP;
								break;
							case KILL_REASON_WARNINGS:
								$player->death->type = DEATH_TYPE_WARNINGS;
								break;
							case KILL_REASON_KICK_OUT:
								$player->death->type = DEATH_TYPE_KICK_OUT;
								break;
							case KILL_REASON_NORMAL:
							default:
								if ($p->state == PLAYER_STATE_KILLED_NIGHT)
								{
									$player->death->type = DEATH_TYPE_NIGHT;
								}
								else
								{
									$player->death->type = DEATH_TYPE_DAY;
								}
								break;
						}
					}
					
					$this->data->players[] = $player;
				}
				
				foreach ($g->votings as $v)
				{
					if ($v->voting_round == 0)
					{
						foreach ($v->nominants as $n)
						{
							if ($n->nominated_by >= 0 && $n->nominated_by < 10)
							{
								if (!isset($this->data->players[$n->nominated_by]->nominating))
								{
									$this->data->players[$n->nominated_by]->nominating = array();
								}
								$nominating = &$this->data->players[$n->nominated_by]->nominating;
								
								for ($i = count($nominating) - 1; $i < $v->round; ++$i)
								{
									$nominating[] = NULL;
								}
								$nominating[$v->round] = $n->player_num + 1;
							}
						}
					}
					
					if (isset($v->votes) && $v->votes != NULL)
					{
						for ($i = 0; $i < 10; ++$i)
						{
							$pv = $v->votes[$i];
							if ($pv >= 0)
							{
								$player = $this->data->players[$i];
								if (!isset($player->voting))
								{
									$player->voting = array();
								}
								$voting = &$player->voting;
								
								for ($j = count($voting) - 1; $j < $v->round; ++$j)
								{
									$voting[] = NULL;
								}
								
								if ($v->round != 0 || count($v->nominants) > 1 || get_rule($this->get_rules(), RULES_FIRST_DAY_VOTING) == RULES_FIRST_DAY_VOTING_TO_TALK)
								{
									if (is_null($voting[$v->round]))
									{
										$voting[$v->round] = $v->nominants[$pv]->player_num + 1;
									}
									else if (is_array($voting))
									{
										if (!is_array($voting[$v->round]))
										{
											$voting[$v->round] = array($voting[$v->round]);
										}
										$voting[$v->round][] = $v->nominants[$pv]->player_num + 1;
									}
									else
									{
										$voting[$v->round] = array($voting[$v->round], $v->nominants[$pv]->player_num + 1);
									}
								}
							}
						}
					}
				}
				
				foreach ($g->log as $log)
				{
					switch ($log->type)
					{
						case LOGREC_WARNING:
							$player = $this->data->players[$log->player];
							$player->warnings[] = Game::get_gametime_info($g, $log);
							if (count($player->warnings) > 3)
							{
								$player->death->time = $player->warnings[3];
							}
							break;
						case LOGREC_GIVE_UP:
							$this->data->players[$log->player]->death->time = Game::get_gametime_info($g, $log);
							break;
						case LOGREC_KICK_OUT:
							$this->data->players[$log->player]->death->time = Game::get_gametime_info($g, $log);
							break;
						case LOGREC_NORMAL:
							if ($log->gamestate == GAME_DAY_PLAYER_SPEAKING && $log->current_nominant >= 0)
							{
								$player = $this->data->players[$log->player_speaking];
								if (!isset($player->nominating))
								{
									$player->nominating = array();
								}
								while ($log->round >= count($player->nominating))
								{
									$player->nominating[] = NULL;
								}
								$player->nominating[$log->round] = $log->current_nominant + 1;
							}
							break;
					}
				}
				
				foreach ($g->shooting as $shots)
				{
					foreach ($shots as $shoter => $shooted)
					{
						if ($shooted >= 0 && $shooted < 10)
						{
							$this->data->players[$shoter]->shooting[] = $shooted + 1;
						}
						else
						{
							$this->data->players[$shoter]->shooting[] = NULL;
						}
					}
				}
				
				if ($g->best_player >= 0)
				{
					$player = $this->data->players[$g->best_player];
					if (!isset($player->extra_points))
					{
						$player->extra_points = 'bestPlayer';
					}
					else if (is_array($player->extra_points))
					{
						$player->extra_points[] = 'bestPlayer';
					}
					else
					{
						$player->extra_points = array($player->extra_points, 'bestPlayer');
					}
				}
				
				if ($g->best_move >= 0)
				{
					$player = $this->data->players[$g->best_move];
					if (!isset($player->extra_points))
					{
						$player->extra_points = 'bestMove';
					}
					else if (is_array($player->extra_points))
					{
						$player->extra_points[] = 'bestMove';
					}
					else
					{
						$player->extra_points = array($player->extra_points, 'bestMove');
					}
				}
			}
			$this->flags = $feature_flags;
		}
		catch (Exception $e)
		{
			$this->data = NULL;
			if (isset($this->flags))
			{
				unset($this->flags);
			}
			throw $e;
		}
	}
	
	function get_rules()
	{
		if (isset($this->data->rules))
		{
			return $this->data->rules;
		}
		return default_rules_code();
	}
	
	private function set_issue($fix, $text, $fix_text)
	{
		if (empty($text))
		{
			return false;
		}
		
		$issue = $text;
		if ($fix)
		{
			$issue .= $fix_text;
		}
		
		if (isset($this->issues))
		{
			foreach ($this->issues as $p)
			{
				if ($p == $issue)
				{
					return $fix;
				}
			}
			$this->issues[] = $issue;
		}
		else
		{
			$this->issues = array($issue);
		}
		return $fix;
	}
	
	function init_flags()
	{
		$this->flags = 0;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->arranged))
			{
				$this->flags |= GAME_FEATURE_FLAG_ARRANGEMENT;
			}
			if (isset($player->don))
			{
				$this->flags |= GAME_FEATURE_FLAG_DON_CHECKS;
			}
			if (isset($player->sheriff))
			{
				$this->flags |= GAME_FEATURE_FLAG_SHERIFF_CHECKS;
			}
			if (isset($player->death))
			{
				if (is_bool($player->death))
				{
					$this->flags |= GAME_FEATURE_FLAG_DEATH;
				}
				if (is_string($player->death))
				{
					$this->flags |= GAME_FEATURE_FLAG_DEATH | GAME_FEATURE_FLAG_DEATH_TYPE;
				}
				else if (is_numeric($player->death))
				{
					$this->flags |= GAME_FEATURE_FLAG_DEATH | GAME_FEATURE_FLAG_DEATH_ROUND;
				}
				else if (is_object($player->death))
				{
					$this->flags |= GAME_FEATURE_FLAG_DEATH;
					if (isset($player->death->round))
					{
						$this->flags |= GAME_FEATURE_FLAG_DEATH_ROUND;
					}
					if (isset($player->death->type))
					{
						$this->flags |= GAME_FEATURE_FLAG_DEATH_TYPE;
					}
					if (isset($player->death->time))
					{
						$this->flags |= GAME_FEATURE_FLAG_DEATH_TIME;
					}
				}
			}
			
			if (isset($player->legacy))
			{
				$this->flags |= GAME_FEATURE_FLAG_LEGACY;
			}
			
			if (isset($player->shooting))
			{
				$this->flags |= GAME_FEATURE_FLAG_SHOOTING;
			}
			if (isset($player->voting))
			{
				$this->flags |= GAME_FEATURE_FLAG_VOTING;
				if (is_array($player->voting) && count($player->voting) > 0 && is_bool(end($player->voting)))
				{
					$this->flags |= GAME_FEATURE_FLAG_VOTING_KILL_ALL;
				}
			}
			if (isset($player->nominating))
			{
				$this->flags |= GAME_FEATURE_FLAG_NOMINATING;
			}
			if (isset($player->warnings))
			{
				$this->flags |= GAME_FEATURE_FLAG_WARNINGS;
				if (is_array($player->warnings))
				{
					$this->flags |= GAME_FEATURE_FLAG_WARNINGS_DETAILS;
				}
			}
		}
		$this->flags |= ($this->expected_flags & (GAME_FEATURE_FLAG_VOTING_KILL_ALL | GAME_FEATURE_FLAG_LEGACY | GAME_FEATURE_FLAG_DEATH_TIME));
	}
	
	function check_game_result($fix = false)
	{
		$civ_count = 0;
		$sheriff_count = 0;
		$maf_count = 0;
		$don_count = 0;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->role))
			{
				switch ($player->role)
				{
					case 'sheriff':
						++$sheriff_count;
						break;
					case 'don':
						++$don_count;
						break;
					case 'maf':
						++$maf_count;
						break;
					case 'civ':
						++$civ_count;
						break;
					default:
						throw new Exc('Player ' . ($i + 1) . ' has invalid role "' . $player->role .  '". Role must be one of: "civ", "sheriff", "maf", or "don".');
				}
			}
			else
			{
				++$civ_count;
			}
			
			if (isset($player->death))
			{
				$death_round = -1;
				if (is_numeric($player->death))
				{
					$death_round = $player->death;
				}
				else if (is_object($player->death))
				{
					if (isset($player->death->round))
					{
						$death_round = $player->death->round;
					}
					else if ($this->set_issue($fix, 'Death round is not set for player ' . ($i + 1) . '.', ' Death rounds for all players are removed.'))
					{
						$this->remove_flags(GAME_FEATURE_FLAG_DEATH_ROUND);
						return false;
					}
				}
				
				if ($death_round >= 0)
				{
					++$death_round;
					if (isset($player->nominating) && count($player->nominating) > $death_round)
					{
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is nominating someone after he/she is dead.', ' Excess data is cut.'))
						{
							$player->nominating = array_slice($player->nominating, 0, $death_round);
							return false;
						}
					}
					if (isset($player->voting) && count($player->voting) > $death_round)
					{
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is voting for someone after he/she is dead.', ' Excess data is cut.'))
						{
							$player->voting = array_slice($player->voting, 0, $death_round);
							return false;
						}
					}
					if (isset($player->shooting) && count($player->shooting) > $death_round)
					{
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is shooting someone after he/she is dead.', ' Excess data is cut.'))
						{
							$player->shooting = array_slice($player->shooting, 0, $death_round);
							return false;
						}
					}
				}
			}
			if (isset($player->legacy) && ($this->flags & (GAME_FEATURE_FLAG_DEATH | GAME_FEATURE_FLAG_DEATH_ROUND)) != 0)
			{
				if (isset($player->death))
				{
					$death_type = NULL;
					$death_round = -1;
					if (is_string($player->death))
					{
						$death_type = $player->death;
					}
					else if (is_numeric($player->death))
					{
						$death_round = $player->death;
					}
					else 
					{
						if (isset($player->death->type))
						{
							$death_type = $player->death->type;
						}
						if (isset($player->death->round))
						{
							$death_round = $player->death->round;
						}
					}
					
					if (
						$death_type != NULL && 
						$death_type != "night" && 
						$this->set_issue($fix, 'Player ' . ($i + 1) . ' was not killed in night. he/she can not leave legacy.', ' Player\'s legacy data is removed.'))
					{
						unset($player->legacy);
						return false;
					}
					
					if (
						$death_round > 1 && 
						$this->set_issue($fix, 'Player ' . ($i + 1) . ' was killed in round ' . $death_round . ' he/she can not leave legacy.', ' Player\'s legacy data is removed.'))
					{
						unset($player->legacy);
						return false;
						
					}
				}
				else if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' has to be dead to leave a legacy.', ' Player\'s legacy data is removed.'))
				{
					unset($player->legacy);
					return false;
				}
			}
			
			if (
				isset($player->shooting) &&
				(!isset($player->role) || $player->role == 'civ' || $player->role == 'sheriff') &&
				$this->set_issue($fix, 'Player ' . ($i + 1) . ' can not shoot because he/she is red.', ' Player\'s shooting data is removed.'))
			{
				unset($player->shooting);
				return false;
			}
		}
		if ($civ_count != 6)
		{
			throw new Exc('This game has ' . $civ_count . ' civilians. Must be 6.');
		}
		if ($sheriff_count != 1)
		{
			throw new Exc('This game has ' . $sheriff_count . ' sheriffs. Must be 1.');
		}
		if ($maf_count != 2)
		{
			throw new Exc('This game has ' . $maf_count . ' mafs. Must be 2.');
		}
		if ($don_count != 1)
		{
			throw new Exc('This game has ' . $don_count . ' dons. Must be 1.');
		}
		return true;
	}
	
	function check_deaths($fix = false)
	{
		if (($this->flags & GAME_FEATURE_FLAG_DEATH) == 0)
		{
			return true;
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->death))
			{
				$death_type = '';
				$death_round = -1;
				$death_time = NULL;
				
				if (is_string($player->death))
				{
					$death_type = $player->death;
				}
				else if (is_numeric($player->death))
				{
					$death_round = $player->death;
				}
				else if (is_object($player->death))
				{
					if (isset($player->death->type))
					{
						$death_type = $player->death->type;
					}
					if (isset($player->death->round))
					{
						$death_round = $player->death->round;
					}
					if (isset($player->death->time))
					{
						$death_time = $player->death->time;
					}
				}
				
				if (
					$death_type == NULL && 
					($this->flags & GAME_FEATURE_FLAG_DEATH_TYPE) != 0 &&
					$this->set_issue($fix, 'Death type is not set for player ' . ($i + 1) . '.', ' Death type info for all players is removed.'))
				{
					$this->remove_flags(GAME_FEATURE_FLAG_DEATH_TYPE);
					return false;
				}
				
				if (
					$death_round < 0 &&
					($this->flags & GAME_FEATURE_FLAG_DEATH_ROUND) != 0 &&
					$this->set_issue($fix, 'Death round is not set for player ' . ($i + 1) . '.', ' Death round info for all players is removed.'))
				{
					$this->remove_flags(GAME_FEATURE_FLAG_DEATH_ROUND);
					return false;
				}
				
				switch ($death_type)
				{
					case '';
						break;
					case DEATH_TYPE_GIVE_UP:
					case DEATH_TYPE_WARNINGS:
					case DEATH_TYPE_KICK_OUT:
					case DEATH_TYPE_TEAM_KICK_OUT:
						if ($death_time != NULL)
						{
							$issue = $this->check_gametime($death_time);
							if (
								$issue != NULL &&
								$this->set_issue($fix, 'Player ' . ($i + 1) . ' death time is incorect: ' . $issue, ' Kill time info for all players is removed.'))
							{
								$this->remove_flags(GAME_FEATURE_FLAG_DEATH_TIME);
								return false;
							}
						}
						else if ($this->flags & GAME_FEATURE_FLAG_DEATH_TIME)
						{
							if ($this->set_issue($fix, 'Death round is not set for player ' . ($i + 1) . '.', ' Kill time info for all players is removed.'))
							{
								$this->remove_flags(GAME_FEATURE_FLAG_DEATH_TIME);
								return false;
							}
						}
						break;
					case DEATH_TYPE_NIGHT:
						if ($death_round == 0)
						{
							if ($this->set_issue($fix, 'Player ' . ($j + 1) . ' was shot in night 0. This is not possible.', ' Death round info for all players is removed.'))
							{
								$this->remove_flags(GAME_FEATURE_FLAG_DEATH_ROUND);
								return false;
							}
						}
						else if ($death_round > 0 && ($this->flags & GAME_FEATURE_FLAG_SHOOTING) != 0)
						{
							$gametime = new stdClass();
							$gametime->time = GAMETIME_SHOOTING;
							$gametime->round = $death_round;
							for ($j = 0; $j < 10; ++$j)
							{
								$p = $this->data->players[$j];
								if (!isset($p->role))
								{
									continue;
								}
								
								if ($p->role != 'don' && $p->role != 'maf')
								{
									continue;
								}

								if ($this->compare_gametimes($gametime, $this->get_player_death_time($j + 1)) >= 0)
								{
									continue;
								}
								
								if (!isset($p->shooting))
								{
									if ($this->set_issue($fix, 'Shooting is not set for player ' . ($j + 1) . '.', ' Shooting is created.'))
									{
										$p->shooting = array();
										for ($k = 0; $k < $death_round; ++$k)
										{
											$p->shooting[] = NULL;
										}
										$p->shooting[$death_round - 1] = $i + 1;
										return false;
									}
								}
								else if (count($p->shooting) < $death_round)
								{
									if ($this->set_issue($fix, 'No shooting for player ' . ($j + 1) . ' in round ' . $death_round . '.', ' Shooting is created.'))
									{
										for ($k = count($p->shooting); $k < $death_round; ++$k)
										{
											$p->shooting[] = NULL;
										}
										$p->shooting[$death_round - 1] = $i + 1;
										return false;
									}
								}
								else if ($p->shooting[$death_round - 1] != $i + 1)
								{
									if ($this->set_issue($fix, 'Wrong shooting to player ' . ($i + 1) . ' in round ' . $death_round . ' for player ' . ($j + 1) . '. Because player ' . ($i + 1) . 'was shot in night ' . $death_round . '.', ' Shooting is set to the right value.'))
									{
										$p->shooting[$death_round - 1] = $i + 1;
										return false;
									}
								}
							}
						}
						break;
					case DEATH_TYPE_DAY:
						if ($death_round >= 0 && $death_round < count($this->votings))
						{
							$voting = $this->votings[$death_round];
							if ($voting != NULL)
							{
								if (isset($voting->killed) && $voting->killed)
								{
									if (isset($voting->winner))
									{
										if (is_array($voting->winner))
										{
											$killed = false;
											foreach ($voting->winner as $winner)
											{
												if ($i + 1 == $winner)
												{
													$killed = true;
													break;
												}
											}
										}
										else
										{
											$killed = ($i + 1 == $voting->winner);
										}
										
										if (!$killed)
										{
											if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is specified as voted out in round ' . $death_round . '. But accordiong to the voting info, another player was voted out.', ' All votings are removed.'))
											{
												$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
												return false;
											}
										}
									}
								}
								else if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is specified as voted out in round ' . $death_round . '. But accordiong to the voting info, no one was voted out in this round.', ' All votings are removed.'))
								{
									$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
									return false;
									
								}
							}
						}
						break;
					default:
						throw new Exc(
							'Player ' . ($i + 1) . ' has invalid death type "' . $death_type . '". Death type must be one of: "' . 
							DEATH_TYPE_DAY . '", "' . DEATH_TYPE_NIGHT . '", "' . DEATH_TYPE_WARNINGS . '", "' . 
							DEATH_TYPE_KICK_OUT . '", "' . DEATH_TYPE_TEAM_KICK_OUT . '", or "' . DEATH_TYPE_GIVE_UP . '".');
				}
			}
		}
		return true;
	}
	
	function check_nominations($fix = false)
	{
		if (($this->flags & GAME_FEATURE_FLAG_NOMINATING) == 0)
		{
			return true;
		}
		
		$speech_time = new stdClass();
		$speech_time->time = GAMETIME_SPEAKING;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (!isset($player->nominating))
			{
				continue;
			}
			$death_time = $this->get_player_death_time($i + 1);
			$speech_time->round = count($player->nominating) - 1;
			$speech_time->speaker = $i + 1;
			if ($death_time != NULL && $speech_time->round >= 0 && $this->compare_gametimes($death_time, $speech_time) < 0)
			{
				if (get_rule($this->get_rules(), RULES_KILLED_NOMINATE) != RULES_KILLED_NOMINATE_ALLOWED || $death_time->round != $speech_time->round || $death_time->time != GAMETIME_SHOOTING)
				{
					if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' was dead when they nominated player ' . $player->nominating[$speech_time->round] . ' in round ' . $speech_time->round . '.', ' Nomination is removed.'))
					{
						do
						{
							$player->nominating[$speech_time->round] = NULL;
							--$speech_time->round;
						}
						while ($speech_time->round >= 0 && $this->compare_gametimes($death_time, $speech_time) < 0);
						$player->nominating = Game::cut_ending_nulls($player->nominating);
						return false;
					}
				}
			}
			
			for ($speech_time->round = 0; $speech_time->round < count($player->nominating); ++$speech_time->round)
			{
				$nom = $player->nominating[$speech_time->round];
				if ($nom == NULL)
				{
					continue;
				}
				
				$nominated_player_death_time = $this->get_player_death_time($nom);
				if ($nominated_player_death_time != NULL && $this->compare_gametimes($nominated_player_death_time, $speech_time) < 0)
				{
					if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' nominated dead player ' . $nom . ' in round ' . $speech_time->round . '.', ' Nomination is removed.'))
					{
						$player->nominating[$speech_time->round] = NULL;
						$player->nominating = Game::cut_ending_nulls($player->nominating);
						return false;
					}
				}
			}
		}
		return true;
	}
	
	function check_shooting($fix = false)
	{
		if (($this->flags & GAME_FEATURE_FLAG_SHOOTING) == 0)
		{
			return true;
		}
		
		$shooting_time = new stdClass();
		$shooting_time->time = GAMETIME_SHOOTING;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (!isset($player->shooting))
			{
				continue;
			}
			
			if (!isset($player->role) || ($player->role != 'maf' && $player->role != 'don'))
			{
				if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' is not mafia but they were shooting .', ' Shots are removed.'))
				{
					unset($player->shooting);
					return false;
				}
			}
			
			$death_time = $this->get_player_death_time($i + 1);
			if ($death_time != NULL)
			{
				$shooting_time->round = count($player->shooting) - 1;
				if ($shooting_time->round >= 0 && $this->compare_gametimes($death_time, $shooting_time) < 0)
				{
					if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' was dead when they shot player ' . $player->shooting[$shooting_time->round] . ' in round ' . $shooting_time->round . '.', ' Shot is removed.'))
					{
						do
						{
							$player->shooting[$shooting_time->round] = NULL;
							--$shooting_time->round;
						}
						while ($shooting_time->round >= 0 && $this->compare_gametimes($death_time, $shooting_time) < 0);
						$player->shooting = Game::cut_ending_nulls($player->shooting);
						return false;
					}
				}
			}
		}
		return true;
	}
	
	private function check_vote($fix, $voter_num, $voter_death_time, $voted_num, $voting_time)
	{
		$voting_time->nominant = $voted_num;
		// Check that voter was alive during voting
		if ($voter_death_time != NULL && $this->compare_gametimes($voter_death_time, $voting_time) < 0)
		{
			if ($this->set_issue($fix, 'Player ' . $voter_num . ' was dead when they were voting for ' . $voting_time->nominant . ' in round ' . $voting_time->round . '.', ' Votes are removed.'))
			{
				$player = $this->data->players[$voter_num - 1];
				$v = $player->voting[$voting_time->round];
				if (is_array($v))
				{
					for ($i = 0; $i < count($v); ++$i)
					{
						if ($v[$i] == $voted_num)
						{
							break;
						}
					}
					if ($i == 0)
					{
						if ($voting_time->round == 0)
						{
							unset($player->voting);
						}
						else
						{
							$player->voting = array_slice($player->voting, 0, $voting_time->round);
						}
					}
					else if ($i < count($v))
					{
						$player->voting[$voting_time->round] = array_slice($v, 0, $i);
						if ($voting_time->round + 1 < count($v))
						{
							$player->voting = array_slice($player->voting, 0, $voting_time->round + 1);
						}
					}
				}
				else if ($voting_time->round == 0)
				{
					unset($player->voting);
				}
				else
				{
					$player->voting = array_slice($player->voting, 0, $voting_time->round);
				}
				return false;
			}
		}
		
		// Check that voted player was alive during voting
		$voted_player_death_time = $this->get_player_death_time($voting_time->nominant);
		if ($voted_player_death_time != NULL && $this->compare_gametimes($voted_player_death_time, $voting_time) < 0)
		{
			if ($this->set_issue($fix, 'Player ' . $voter_num . ' voted for dead player ' . $voting_time->nominant . ' in round ' . $voting_time->round . '.', ' All voting are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
		}
		
		// Check that voted player was nominated
		if ($this->flags & GAME_FEATURE_FLAG_NOMINATING)
		{
			$nominated = false;
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $this->data->players[$i];
				if (isset($player->nominating) && $voting_time->round < count($player->nominating) && $player->nominating[$voting_time->round] == $voting_time->nominant)
				{
					$nominated = true;
					break;
				}
			}
			if (!$nominated && $this->set_issue($fix, 'Player ' . $voter_num . ' voted for player ' . $voting_time->nominant . ', who is not nominated in round ' . $voting_time->round . '.', ' All nominations are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_NOMINATING);
				return false;
			}
		}
		return true;
	}
	
	function has_voting($round)
	{
		$voting = $this->votings[$round];
		if (isset($voting->canceled) && $voting->canceled)
		{
			// voting was canceled
			return false;
		}
		if (!isset($voting->nominants))
		{
			// no one nominated
			return false;
		}
		switch (count($voting->nominants))
		{
			case 0:
				// no one nominated
				return false;
			case 1:
				// only one nominated in round 0
				return $round > 0;
		}
		return true;
	}
	
	private function check_voted_out($fix, $player_num, $round)
	{
		$player = $this->data->players[$player_num - 1];
		if (!isset($player->death))
		{
			if ($this->set_issue($fix, 'Player was voted out in round ' . $round . ' but is not dead.', ' All votings are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
		}
		else if (is_numeric($player->death))
		{
			if (
				$player->death != $round &&
				$this->set_issue($fix, 'Player was voted out in round ' . $round . ' but was dead in round ' . $player->death . '.', ' All votings are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
		}
		else if (is_string($player->death))
		{
			if (
				$player->death != DEATH_TYPE_DAY &&
				$this->set_issue($fix, 'Player was voted out in round ' . $round . ' but was dead by ' . $player->death . '.', ' All votings are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
		}
		else
		{
			if (
				$player->death->round != $round &&
				$this->set_issue($fix, 'Player was voted out in round ' . $round . ' but was dead in round ' . $player->death->round . '.', ' All votings are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
			if (
				$player->death->type != DEATH_TYPE_DAY &&
				$this->set_issue($fix, 'Player was voted out in round ' . $round . ' but was dead by ' . $player->death->type . '.', ' All votings are removed.'))
			{
				$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
				return false;
			}
		}
		return true;
	}
	
	function check_voting($fix = false)
	{
		if (($this->flags & GAME_FEATURE_FLAG_VOTING) == 0)
		{
			return true;
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			$death_time = $this->get_player_death_time($i + 1, true);
			$voting_time = new stdClass();
			$voting_time->time = GAMETIME_VOTING;
			$voting_time->votingRound = 0;
			for ($voting_time->round = 0; $this->compare_gametimes($death_time, $voting_time) >= 0; ++$voting_time->round)
			{
				if (isset($player->voting) && $voting_time->round < count($player->voting) && $player->voting[$voting_time->round] != NULL)
				{
					// player was alive and voted in this round
					// check that all players they voted for were alive and nominated
					$v = $player->voting[$voting_time->round];
					$voting_time->votingRound = 0;
					if (is_array($v))
					{
						for (; $voting_time->votingRound < count($v); ++$voting_time->votingRound)
						{
							$vote = $v[$voting_time->votingRound];
							if (is_numeric($vote) && !$this->check_vote($fix, $i + 1, $death_time, $vote, $voting_time))
							{
								return false;
							}
						}
					}
					else if (!$this->check_vote($fix, $i + 1, $death_time, $v, $voting_time))
					{
						return false;
					}
				}
				else if (
					$voting_time->round < count($this->votings) && 
					$this->has_voting($voting_time->round) &&
					$this->set_issue($fix, 'Player ' . ($i + 1) . ' did not vote in round ' . $voting_time->round . '. Although they were alive and voting was not canceled.', ' All votings are removed.'))
				{
					// player was alive but did not vote in this round
					// Voting was not canceled. Player had to vote.
					$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
					return false;
				}
			}
		}
		
		// Check that all voted out players are dead
		if ($this->flags & GAME_FEATURE_FLAG_DEATH)
		{
			for ($round = 0; $round < count($this->votings); ++$round)
			{
				$voting = $this->votings[$round];
				if (!isset($voting->winner))
				{
					continue;
				}
				if (is_numeric($voting->winner))
				{
					if ($voting->killed)
					{
						if (!$this->check_voted_out($fix, $voting->winner, $round))
						{
							return false;
						}
					}
					else if ($round != 0 || get_rule($this->get_rules(), RULES_FIRST_DAY_VOTING) != RULES_FIRST_DAY_VOTING_TO_TALK)
					{
						if ($this->set_issue($fix, 'Player ' . $voting->winner . ' was voted out in round ' . $round . ' but did not die.', ' All votings are removed.'))
						{
							$this->remove_flags(GAME_FEATURE_FLAG_VOTING);
							return false;
						}
					}
				}
				else if ($voting->killed)
				{
					foreach ($voting->winner as $winner)
					{
						if (!$this->check_voted_out($fix, $winner, $round))
						{
							return false;
						}
					}
				}
			}
		}
		return true;
	}
	
	function check_warnings($fix = false)
	{
		if (($this->flags & GAME_FEATURE_FLAG_WARNINGS) == 0)
		{
			return true;
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			$warnings_count = 0;
			if (isset($player->warnings))
			{
				if ($this->flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS)
				{
					$issue = NULL;
					if (is_array($player->warnings))
					{
						if (
							count($player->warnings) > 4 &&
							$this->set_issue($fix, 'Player ' . ($i + 1) . ' has ' . count($player->warnings). ' warnings.', ' Warnings are cut to 4.'))
						{
							$player->warnings = array_slice($player->warnings, 0, 4);
							return false;
						}
						
						for ($j = 0; $j < count($player->warnings); ++$j)
						{
							$warning = $player->warnings[$j];
							$issue = $this->check_gametime($warning);
							if ($issue != NULL)
							{
								$issue = 'Player ' . ($i + 1) . ' warning ' . ($j + 1) . ' is incorect: ' . $issue;
								break;
							}
						}
					}
					else 
					{
						$issue = 'Player ' . ($i + 1) . ' warnings is not an array.';
					}
					
					if (
						$issue != NULL &&
						$this->set_issue($fix, $issue, ' Warning details are removed.'))
					{
						$this->remove_flags(GAME_FEATURE_FLAG_WARNINGS_DETAILS);
						return false;
					}
				}
				else
				{
					if (!is_numeric($player->warnings))
					{
						$warnings_count = min(max(is_array($player->warnings) ? count($player->warnings) : 0, 0), 4);
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' warnings is not a number.', ' Warnings are set to ' . $warnings_count . '.'))
						{
							if ($warnings_count > 0)
							{
								$player->warnings = $warnings_count;
							}
							else
							{
								unset($player->warnings);
							}
							return false;
						}
					}
					else if ($player->warnings < 0 || $player->warnings > 4)
					{
						$warnings_count = min(max($player->warnings, 0), 4);
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' warnings is set to incorect number ' . $player->warnings . '.', ' Warnings are set to ' . $warnings_count . '.'))
						{
							if ($warnings_count > 0)
							{
								$player->warnings = $warnings_count;
							}
							else
							{
								unset($player->warnings);
							}
							return false;
						}
					}
				}
			}
			
			if ($warnings_count == 4 && ($this->flags & GAME_FEATURE_FLAG_DEATH) != 0)
			{
				// player must be dead of warnings
				if (!isset($player->death))
				{
					if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' has 4 warnings but they are not dead.', ' Number of warnings is set to 3.'))
					{
						if ($this->flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS)
						{
							$player->warnings = array_slice($player->warnings, 0, 3);
						}
						else
						{
							$player->warnings = 3;
						}
						return false;
					}
				}
				else 
				{
					$death_type = NULL;
					$death_round = -1;
					$death_time = NULL;
					if (is_string($player->death))
					{
						$death_type = $player->death;
					}
					else if (is_numeric($player->death))
					{
					}
					else if (is_object($player->death))
					{
						if (isset($player->death->type))
						{
							$death_type = $player->death->type;
						}
						if (isset($player->death->round))
						{
							$death_round = $player->death->round;
						}
						if (isset($player->death->time))
						{
							$death_time = $player->death->time;
						}
					}
					if ($death_type != NULL && $death_type != DEATH_TYPE_WARNINGS)
					{
						if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' has 4 warnings but their death type is ' . $death_type . '.', ' Number of warnings is set to 3.'))
						{
							if ($this->flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS)
							{
								$player->warnings = array_slice($player->warnings, 0, 3);
							}
							else
							{
								$player->warnings = 3;
							}
							return false;
						}
					}
					else if ($this->flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS)
					{
						if ($death_round >= 0 && $death_round != $player->warnings[3]->round)
						{
							if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' has 4 warnings in round ' . $player->warnings[3]->round . '. But according to death info thery were dead in round '. $death_round . '.', ' Number of warnings is set to 3.'))
							{
								$player->warnings = array_slice($player->warnings, 0, 3);
								return false;
							}
						}
						else if ($death_time != NULL && $this->compare_gametimes($death_time, $player->warnings[3]) == 0)
						{
							if ($this->set_issue($fix, 'Player ' . ($i + 1) . ' has 4 warnings but fourth warning time does not match death time.', ' Number of warnings is set to 3.'))
							{
								$player->warnings = array_slice($player->warnings, 0, 3);
								return false;
							}
						}
					}
				}
			}
		}
		return true;
	}
	
	function fix()
	{
		$this->check(true);
	}
	
	function check($fix = false)
	{
		if (!isset($this->data->players))
		{
			throw new Exc(get_label('Field [0] is not set in the [1].', 'players', get_label('game')));
		}
		if (count($this->data->players) != 10)
		{
			throw new Exc(get_label('Field players must be an array of size 10 in the game.'));
		}
		
		if (isset($this->issues))
		{
			unset($this->issues);
		}
		$this->init_flags();
		
		// Maximum 10 attempts to fix
		$done = false;
		for ($i = 0; !$done && $i < 10; ++$i)
		{
			$done = true;
			$this->init_votings(true);
			$done = $done && $this->check_game_result($fix);
			$done = $done && $this->check_deaths($fix);
			$done = $done && $this->check_nominations($fix);
			$done = $done && $this->check_shooting($fix);
			$done = $done && $this->check_voting($fix);
			$done = $done && $this->check_warnings($fix);
		}
	}
	
	function to_json()
	{
		return json_encode($this->data);
	}
	
	static function cut_ending_nulls($array)
	{
		$j = count($array) - 1;
		$cut = false;
		while ($j >= 0)
		{
			if ($array[$j] != NULL)
			{
				break;
			}
			$cut = true;
			--$j;
		}
		if ($cut)
		{
			$array = array_slice($array, 0, $j + 1);
		}
		return $array;
	}

	private static function get_gametime_info($gs, $log)
	{
		$gametime = new stdClass();
		switch ($log->gamestate)
		{
			case GAME_NOT_STARTED:
			case GAME_NIGHT0_START:
				$gametime->round = 0;
				$gametime->time = GAMETIME_START;
				break;
			case GAME_NIGHT0_ARRANGE:
				$gametime->round = 0;
				$gametime->time = GAMETIME_ARRANGEMENT;
				break;
			case GAME_DAY_START:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_DAY_START;
				break;
			case GAME_DAY_KILLED_SPEAKING:
			case GAME_DAY_GUESS3:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_NIGHT_KILL_SPEAKING;
				break;
			case GAME_DAY_PLAYER_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_SPEAKING;
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case GAME_VOTING_START:
			case GAME_DAY_FREE_DISCUSSION:
				$gametime->round = $log->round;
				$gametime->votingRound = 0;
				$gametime->time = GAMETIME_VOTING;
				break;
			case GAME_VOTING_KILLED_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_DAY_KILL_SPEAKING;
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case GAME_VOTING:
				// check that the nominant field is correct
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_VOTING;
				$gametime->votingRound = 0; // how to find out voting round??
				foreach ($gs->votings as $voting)
				{
					if ($voting->round == $log->round)
					{
						$gametime->nominant = $voting->nominants[$log->current_nominant]->player_num + 1;
						break;
					}
				}
				break;
			case GAME_VOTING_MULTIPLE_WINNERS:
				$gametime->round = $log->round;
				$gametime->votingRound = 1;
				$gametime->time = GAMETIME_VOTING;
				break;
			case GAME_VOTING_NOMINANT_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_VOTING;
				$gametime->votingRound = 0; // how to find out voting round??
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case GAME_NIGHT_START:
			case GAME_NIGHT_SHOOTING:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_SHOOTING;
				break;
			case GAME_NIGHT_DON_CHECK:
			case GAME_NIGHT_DON_CHECK_END:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_DON;
				break;
			case GAME_NIGHT_SHERIFF_CHECK:
			case GAME_NIGHT_SHERIFF_CHECK_END:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_SHERIFF;
				break;
			case GAME_MAFIA_WON:
			case GAME_CIVIL_WON:
			case GAME_CHOOSE_BEST_PLAYER:
			case GAME_CHOOSE_BEST_MOVE:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_END;
				$pl = $gs->players[0];
				if ($pl->state != PLAYER_STATE_ALIVE && $pl->kill_reason == KILL_REASON_NORMAL)
				{
					$gametime->speaker = 1;
					$gametime->time = ($pl->state == PLAYER_STATE_KILLED_NIGHT ? GAMETIME_NIGHT_KILL_SPEAKING : GAMETIME_DAY_KILL_SPEAKING);
				}
				for ($i = 1; $i < 10; ++$i)
				{
					$p = $gs->players[$i];
					if ($p->state == PLAYER_STATE_ALIVE || $p->kill_reason != KILL_REASON_NORMAL)
					{
						continue;
					}
					if ($p->kill_round > $pl->kill_round || ($p->kill_round == $pl->kill_round && $p->state == PLAYER_STATE_KILLED_DAY))
					{
						$pl = p;
						$gametime->speaker = $i + 1;
						$gametime->time = ($p->state == PLAYER_STATE_KILLED_NIGHT ? GAMETIME_NIGHT_KILL_SPEAKING : GAMETIME_DAY_KILL_SPEAKING);
					}
				}
				break;
		}
		return $gametime;
	}

	private static function gametime_to_int($gametime)
	{
		switch ($gametime)
		{
			case GAMETIME_START:
				return 0;
			case GAMETIME_ARRANGEMENT:
				return 1;
			case GAMETIME_SHOOTING:
				return 2;
			case GAMETIME_DON:
				return 3;
			case GAMETIME_SHERIFF:
				return 4;
			case GAMETIME_DAY_START:
				return 5;
			case GAMETIME_NIGHT_KILL_SPEAKING:
				return 6;
			case GAMETIME_SPEAKING:
				return 7;
			case GAMETIME_VOTING:
				return 8;
			case GAMETIME_DAY_KILL_SPEAKING:
				return 9;
		}
		return 10;
	}
	
	function get_last_round()
	{
		$last_round = 0;
		foreach ($this->data->players as $player)
		{
			if (isset($player->nominating))
			{
				$last_round = max(count($player->nominating), $last_round);
			}
			if (isset($player->voting))
			{
				$last_round = max(count($player->voting), $last_round);
			}
			if (isset($player->shooting))
			{
				$last_round = max(count($player->shooting), $last_round);
			}
			if (isset($player->don))
			{
				$last_round = max($player->don, $last_round);
			}
			if (isset($player->sheriff))
			{
				$last_round = max($player->sheriff, $last_round);
			}
		}
		return $last_round;
	}
	
	// For players who were shot this is stooting time.
	// For players who were voted out this is voting to them time
	// For players who are not dead - end of the game
	// If unknown - null
	// $player_num - 1 to 10.
	// If $including_speech is true it returns final speech time instead of voting/shooting time.
	function get_player_death_time($player_num, $including_speech = false)
	{
		$death_time = NULL;
		$player = $this->data->players[$player_num - 1];
		if (!isset($player->death))
		{
			$death_time = new stdClass();
			$death_time->time = GAMETIME_END;
			$death_time->round = $this->get_last_round();
		}
		else if (isset($player->death->time))
		{
			$death_time = clone $player->death->time;
		}
		else if (isset($player->death->type))
		{
			switch ($player->death->type)
			{
				case DEATH_TYPE_NIGHT:
					$death_time = new stdClass();
					if (isset($player->death->round))
					{
						$death_time->round = $player->death->round;
					}
					if ($including_speech)
					{
						$death_time->time = GAMETIME_NIGHT_KILL_SPEAKING;
					}
					else
					{
						$death_time->time = GAMETIME_SHOOTING;
					}
					break;
				case DEATH_TYPE_DAY:
					$death_time = new stdClass();
					$death_time->round = $player->death->round;
					if ($including_speech)
					{
						$death_time->time = GAMETIME_DAY_KILL_SPEAKING;
						$death_time->speaker = $player_num;
					}
					else
					{
						$death_time->time = GAMETIME_VOTING;
						$death_time->nominant = $player_num;
						$death_time->votingRound = 0; // how to find out voting round??
						foreach ($this->data->players as $p)
						{
							if (isset($p->voting) && count($p->voting) > $death_time->round && is_array($p->voting[$death_time->round]))
							{
								$death_time->votingRound = count($p->voting[$death_time->round]);
								break;
							}
						}
					}
					break;
			}
		}
		return $death_time;
	}
		
	function who_speaks_first($round)
	{
		// todo: support mafclub rules
		$candidate = 1;
		if ($round > 0)
		{
			$candidate = $this->who_speaks_first($round - 1) + 1;
			if ($candidate > 10)
			{
				$candidate = 1;
			}
		}
		
		$day_start = new stdClass();
		$day_start->round = $round;
		$day_start->time = GAMETIME_NIGHT_KILL_SPEAKING;
		for ($i = 0; $i < 10; ++$i)
		{
			if ($this->compare_gametimes($this->get_player_death_time($candidate), $day_start) > 0)
			{
				break;
			}
			else if (++$candidate > 10)
			{
				$candidate = 1;
			}
		}
		if ($i >= 10)
		{
			return 0;
		}
		return $candidate;
	}
	
	function init_votings($force = true)
	{
		if (!$force && isset($this->votings))
		{
			return;
		}
		
		$rounds_count = 0;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->nominating))
			{
				$rounds_count = max($rounds_count, count($player->nominating));
			}
			if (isset($player->voting))
			{
				$rounds_count = max($rounds_count, count($player->voting));
			}
		}
		
		$this->votings = array();
		for ($round = 0; $round < $rounds_count; ++$round)
		{
			$voting = new stdClass();
			$first_speaker = $this->who_speaks_first($round);
			
			// nominations
			for ($i = $first_speaker; $i <= 10; ++$i)
			{
				$player = $this->data->players[$i-1];
				if (isset($player->nominating) && $round < count($player->nominating) && $player->nominating[$round] != NULL)
				{
					$nom = new stdClass();
					$nom->nominant = $player->nominating[$round];
					$nom->by = $i;
					if (!isset($voting->nominants))
					{
						$voting->nominants = array();
					}
					$voting->nominants[] = $nom;
				}
			}
			
			for ($i = 1; $i < $first_speaker; ++$i)
			{
				$player = $this->data->players[$i-1];
				if (isset($player->nominating) && $round < count($player->nominating) && $player->nominating[$round] != NULL)
				{
					$nom = new stdClass();
					$nom->nominant = $player->nominating[$round];
					$nom->by = $i;
					if (!isset($voting->nominants))
					{
						$voting->nominants = array();
					}
					$voting->nominants[] = $nom;
				}
			}
			
			// votings
			for ($i = 0; $i < 10; ++$i)
			{
				$player = $this->data->players[$i];
				if (isset($player->voting) && $round < count($player->voting) && $player->voting[$round] != NULL)
				{
					if (!isset($voting->nominants))
					{
						$voting->nominants = array();
					}
					
					$v = $player->voting[$round];
					if (is_array($v))
					{
						$voting_rounds = count($v);
						if ($voting_rounds > 0 && is_bool($v[$voting_rounds-1]))
						{
							--$voting_rounds;
							if (!isset($voting->voted_to_kill))
							{
								$voting->voted_to_kill = array();
							}
							if ($v[$voting_rounds])
							{
								$voting->voted_to_kill[] = $i + 1;
							}
						}
						
						for ($j = 0; $j < $voting_rounds; ++$j)
						{
							$vote = $v[$j];
							$nom = NULL;
							foreach ($voting->nominants as $n)
							{
								if ($n->nominant == $vote)
								{
									$nom = $n;
									break;
								}
							}
							
							if ($nom == NULL)
							{
								$nom = new stdClass();
								$nom->nominant = $vote;
								$voting->nominants[] = $nom;
							}
							
							if (!isset($nom->voting) || !is_array($nom->voting) || count($nom->voting) != $voting_rounds)
							{
								$nom->voting = array();
								for ($k = 0; $k < $voting_rounds; ++$k)
								{
									$nom->voting[] = array();
								}
							}
							$nom->voting[$j][] = $i + 1;
						}
					}
					else
					{
						$nom = NULL;
						foreach ($voting->nominants as $n)
						{
							if ($n->nominant == $v)
							{
								$nom = $n;
								break;
							}
						}
						
						if ($nom == NULL)
						{
							$nom = new stdClass();
							$nom->nominant = $v;
							$voting->nominants[] = $nom;
						}
						
						if (!isset($nom->voting))
						{
							$nom->voting = array();
						}
						$nom->voting[] = $i + 1;
					}
				}
			}
			
			// winners
			if (isset($voting->nominants))
			{
				$max_votes = 0;
				$num_voters = 0;
				foreach ($voting->nominants as $nom)
				{
					if (isset($nom->voting))
					{
						$count = count($nom->voting);
						if ($count > 0)
						{
							$last = $nom->voting[$count-1];
							if (is_array($last))
							{
								$count = count($last);
							}
							$max_votes = max($max_votes, $count);
							$num_voters += $count;
						}
					}
				}
				if ($num_voters > 0)
				{
					$voting->voters = $num_voters;
				}
				
				foreach ($voting->nominants as $nom)
				{
					if (isset($nom->voting))
					{
						$count = count($nom->voting);
						if ($count > 0)
						{
							$last = $nom->voting[$count-1];
							if (is_array($last))
							{
								$count = count($last);
							}
							if ($count == $max_votes)
							{
								if (!isset($voting->winner))
								{
									$voting->winner = $nom->nominant;
								}
								else if (is_array($voting->winner))
								{
									$voting->winner[] = $nom->nominant;
								}
								else
								{
									$voting->winner = array($voting->winner);
									$voting->winner[] = $nom->nominant;
								}
							}
						}
					}
				}
				
				// are the winners killed
				if (isset($voting->voted_to_kill))
				{
					$voting->killed = (count($voting->voted_to_kill) * 2 > $num_voters);
				}
				else if (isset($voting->winner))
				{
					if (is_numeric($voting->winner))
					{
						$player = $this->data->players[$voting->winner-1];
						$voting->killed = (isset($player->death) && $player->death->round == $round && $player->death->type == DEATH_TYPE_DAY);
					}
					else if (count($voting->winner))
					{
						$player = $this->data->players[$voting->winner[0]-1];
						$voting->killed = (isset($player->death) && $player->death->round == $round && $player->death->type == DEATH_TYPE_DAY);
					}
				}
			}
			$this->votings[] = $voting;
		}
		
		// Was voting canceled
		if (($this->flags & (GAME_FEATURE_FLAG_DEATH_TYPE | GAME_FEATURE_FLAG_DEATH_ROUND)) == (GAME_FEATURE_FLAG_DEATH_TYPE | GAME_FEATURE_FLAG_DEATH_ROUND))
		{
			// todo check how antimonster rule is set: RULES_ANTIMONSTER - RULES_ANTIMONSTER_NO_VOTING, RULES_ANTIMONSTER_NO, RULES_ANTIMONSTER_NOMINATED, RULES_ANTIMONSTER_PARTICIPATION
			// Note that player can be shot or voted out and killed by warnings at the same time. In this case voting is not canceled even if RULES_ANTIMONSTER_NO_VOTING is set.
			// How to support it in data?
			foreach ($this->data->players as $player)
			{
				if (
					isset($player->death) && 
					$player->death->type != DEATH_TYPE_NIGHT &&
					$player->death->type != DEATH_TYPE_DAY &&
					$player->death->round < count($this->votings))
				{
					$this->votings[$player->death->round]->canceled = true;
				}
			}
		}
	}
	
	function reset_voting_info()
	{
		if (isset($this->voting))
		{
			unset($this->voting);
		}
	}
	
	function check_gametime($gt)
	{
		if (!isset($gt->round))
		{
			return 'round must be set.';
		}
		if (!is_numeric($gt->round) || $gt->round < 0)
		{
			return 'incorrect round "' + $gt->round + '" - it must a number greater than 0.';
		}
		if (!isset($gt->time))
		{
			return 'time must be set.';
		}
		switch ($gt->time)
		{
			case GAMETIME_START:
			case GAMETIME_ARRANGEMENT:
			case GAMETIME_DAY_START:
			case GAMETIME_NIGHT_KILL_SPEAKING:
			case GAMETIME_SHOOTING:
			case GAMETIME_DON:
			case GAMETIME_SHERIFF:
			case GAMETIME_END:
				break;
			case GAMETIME_VOTING:
				if (!isset($gt->votingRound))
				{
					return 'votingRound must be set.';
				}
				if (!is_numeric($gt->votingRound) || $gt->votingRound < 0)
				{
					return 'incorrect votingRound "' + $gt->votingRound + '" - it must a number greater than 0.';
				}
				if (isset($gt->speaker))
				{
					if (!is_numeric($gt->speaker) || $gt->speaker < 1 || $gt->speaker > 10)
					{
						return 'incorect speaker "' . $gt->speaker . '" - it must be a number from 1 to 10.';
					}
				}
				if (isset($gt->nominant))
				{
					if (!is_numeric($gt->nominant) || $gt->nominant < 1 || $gt->nominant > 10)
					{
						return 'incorect nominant "' . $gt->nominant . '" - it must be a number from 1 to 10.';
					}
				}
				break;
			case GAMETIME_SPEAKING:
			case GAMETIME_DAY_KILL_SPEAKING:
				if (!isset($gt->speaker))
				{
					return 'speaker must be set when time is "' . GAMETIME_SPEAKING . '".';
				}
				if (!is_numeric($gt->speaker) || $gt->speaker < 1 || $gt->speaker > 10)
				{
					return 'incorect speaker "' . $gt->speaker . '" - it must be a number from 1 to 10.';
				}
				break;
			default:
				return 'incorrect time "' + $gt->time + '". Time must be one of: "' .
					GAMETIME_START . '", "' . GAMETIME_ARRANGEMENT . '", "' . GAMETIME_DAY_START . '", "' . GAMETIME_NIGHT_KILL_SPEAKING . '", "' . GAMETIME_SPEAKING . '", "' . GAMETIME_VOTING . '", "' . 
					GAMETIME_DAY_KILL_SPEAKING . '", "' . GAMETIME_SHOOTING . '", "' . GAMETIME_DON . '", "' . GAMETIME_SHERIFF . '", or "' . GAMETIME_END . '".';
		}
		return NULL;
	}
	
	// returns <0 if $gt1 < $gt2; >0 if $gt1 > $gt2; 0 if $gt1 == $gt2
	function compare_gametimes($gt1, $gt2)
	{
		$round1 = isset($gt1->round) ? $gt1->round : 0;
		$round2 = isset($gt2->round) ? $gt2->round : 0;
		if ($round1 != $round2)
		{
			return $round1 - $round2;
		}
		
		$time1 = isset($gt1->time) ? $gt1->time : GAMETIME_START;
		$time2 = isset($gt2->time) ? $gt2->time : GAMETIME_START;
		if ($time1 != $time2)
		{
			return Game::gametime_to_int($time1) - Game::gametime_to_int($time2);
		}
		switch ($time1)
		{
			case GAMETIME_SPEAKING:
				$speaks_first = $this->who_speaks_first($gt1->round);
				$speaker1 = ($gt1->speaker < $speaks_first ? 10 + $gt1->speaker : $gt1->speaker);
				$speaker2 = ($gt2->speaker < $speaks_first ? 10 + $gt2->speaker : $gt2->speaker);
				return $speaker1 - $speaker2;

			case GAMETIME_VOTING:
				if ($gt1->votingRound != $gt2->votingRound)
				{
					return $gt1->votingRound - $gt2->votingRound;
				}
				
				if (isset($gt1->nominant))
				{
					if (!isset($gt2->nominant))
					{
						return isset($gt2->speaker) ? -1 : 1;
					}
					
					if ($gt1->nominant != $gt2->nominant && $gt1->round < count($this->votings))
					{
						$voting = $this->votings[$gt1->round];
						foreach ($voting->nominants as $nom)
						{
							if ($nom->nominant == $gt1->nominant)
							{
								return -1;
							}
							else if ($nom->nominant == $gt2->nominant)
							{
								return 1;
							}
						}
					}
					return 0;
				}
				
				if (isset($gt1->speaker))
				{
					if (!isset($gt2->speaker))
					{
						return 1;
					}
					
					if ($gt1->speaker != $gt2->speaker && $gt1->round < count($this->votings))
					{
						$voting = $this->votings[$gt1->round];
						foreach ($voting as $nom)
						{
							if ($nom->nominant == $gt1->speaker)
							{
								return -1;
							}
							else if ($nom->nominant == $gt2->speaker)
							{
								return 1;
							}
						}
					}
					return 0;
				}
				
				return isset($gt2->nominant) || isset($gt2->speaker) ? -1 : 0;
				
			case GAMETIME_DAY_KILL_SPEAKING:
				if ($gt1->speaker != $gt2->speaker && $gt1->round < count($this->votings))
				{
						$voting = $this->votings[$gt1->round];
					foreach ($voting as $nom)
					{
						if ($nom->nominant == $gt1->speaker)
						{
							return -1;
						}
						else if ($nom->nominant == $gt2->speaker)
						{
							return 1;
						}
					}
				}
				return 0;
		}
		return 0;
	}
	
	function remove_flags($flags)
	{
		if (($this->flags & $flags) == 0)
		{
			return;
		}
		
		$reinit_votings = false;
		while ($flags != 0)
		{
			$next_flags = ($flags - 1) & $flags;
			$flag = ($flags - $next_flags) & $this->flags;
			switch ($flag)
			{
				case 0:
					break;
				case GAME_FEATURE_FLAG_ARRANGEMENT:
					foreach ($this->data->players as $player)
					{
						if (isset($player->arranged))
						{
							unset($player->arranged);
						}
					}
					break;
				case GAME_FEATURE_FLAG_DON_CHECKS:
					foreach ($this->data->players as $player)
					{
						if (isset($player->don))
						{
							unset($player->don);
						}
					}
					break;
				case GAME_FEATURE_FLAG_SHERIFF_CHECKS:
					foreach ($this->data->players as $player)
					{
						if (isset($player->sheriff))
						{
							unset($player->sheriff);
						}
					}
					break;
				case GAME_FEATURE_FLAG_DEATH:
					foreach ($this->data->players as $player)
					{
						if (isset($player->death))
						{
							unset($player->death);
						}
					}
					$flag &= ~(GAME_FEATURE_FLAG_DEATH_TYPE | GAME_FEATURE_FLAG_DEATH_ROUND | GAME_FEATURE_FLAG_DEATH_TIME);
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_DEATH_ROUND:
					foreach ($this->data->players as $player)
					{
						if (isset($player->death))
						{
							if (is_numeric($player->death))
							{
								$player->death = true;
							}
							else if (is_object($player->death))
							{
								if ($this->flags & GAME_FEATURE_FLAG_DEATH_TIME)
								{
									if (isset($player->death->round))
									{
										unset($player->death->round);
									}
								}
								else if (($this->flags & GAME_FEATURE_FLAG_DEATH_TYPE) != 0 && isset($player->death->type) && is_string($player->death->type))
								{
									$player->death = $player->death->type;
								}
								else
								{
									$player->death = true;
								}
							}
						}
					}
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_DEATH_TYPE:
					foreach ($this->data->players as $player)
					{
						if (isset($player->death))
						{
							if (is_string($player->death))
							{
								$player->death = true;
							}
							else if (is_object($player->death))
							{
								if ($this->flags & GAME_FEATURE_FLAG_DEATH_TIME)
								{
									if (isset($player->death->type))
									{
										unset($player->death->type);
									}
								}
								else if (($this->flags & GAME_FEATURE_FLAG_DEATH_ROUND) != 0 && isset($player->death->round) && is_numeric($player->death->round))
								{
									$player->death = $player->death->round;
								}
								else
								{
									$player->death = true;
								}
							}
						}
					}
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_DEATH_TIME:
					foreach ($this->data->players as $player)
					{
						if (isset($player->death) && is_object($player->death))
						{
							if ($this->flags & GAME_FEATURE_FLAG_DEATH_ROUND)
							{
								if ($this->flags & GAME_FEATURE_FLAG_DEATH_TYPE)
								{
									if (isset($player->death->time))
									{
										unset($player->death->time);
									}
								}
								else if (isset($player->death->round) && is_numeric($player->death->round))
								{
									$player->death = $player->death->round;
								}
								else
								{
									$player->death = true;
								}
							}
							else if ($this->flags & GAME_FEATURE_FLAG_DEATH_TYPE)
							{
								if (isset($player->death->type) && is_string($player->death->type))
								{
									$player->death = $player->death->type;
								}
								else
								{
									$player->death = true;
								}
							}
							else
							{
								$player->death = true;
							}
						}
					}
					break;
				case GAME_FEATURE_FLAG_LEGACY:
					foreach ($this->data->players as $player)
					{
						if (isset($player->legacy))
						{
							unset($player->legacy);
						}
					}
					break;
				case GAME_FEATURE_FLAG_SHOOTING:
					foreach ($this->data->players as $player)
					{
						if (isset($player->shooting))
						{
							unset($player->shooting);
						}
					}
					break;
				case GAME_FEATURE_FLAG_VOTING:
					foreach ($this->data->players as $player)
					{
						if (isset($player->voting))
						{
							unset($player->voting);
						}
					}
					$flag |= GAME_FEATURE_FLAG_VOTING_KILL_ALL;
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_VOTING_KILL_ALL:
					foreach ($this->data->players as $player)
					{
						if (isset($player->voting))
						{
							foreach ($player->voting as $vote)
							{
								if (is_array($vote) && count($vote) > 0 && is_bool($vote[count($vote) - 1]))
								{
									array_pop($vote);
								}
							}
						}
					}
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_NOMINATING:
					foreach ($this->data->players as $player)
					{
						if (isset($player->nominating))
						{
							unset($player->nominating);
						}
					}
					$reinit_votings = true;
					break;
				case GAME_FEATURE_FLAG_WARNINGS:
					foreach ($this->data->players as $player)
					{
						if (isset($player->warnings))
						{
							unset($player->warnings);
						}
					}
					$flag &= ~GAME_FEATURE_FLAG_WARNINGS_DETAILS;
					break;
				case GAME_FEATURE_FLAG_WARNINGS_DETAILS:
					foreach ($this->data->players as $player)
					{
						if (isset($player->warnings))
						{
							$player->warnings = count($player->warnings);
						}
					}
					break;
			}
			$this->flags &= ~$flag;
			$flags = $next_flags;
		}
		
		if (isset($this->votings) && $reinit_votings)
		{
			$this->init_votings();
		}
	}
}

?>