<?php

require_once __DIR__ . '/rules.php';
require_once __DIR__ . '/datetime.php';
require_once __DIR__ . '/event.php';
require_once __DIR__ . '/game_ratings.php';
require_once __DIR__ . '/game_players_stats.php';

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

define('GAME_ACTION_ARRANGEMENT', 'arrangement');
define('GAME_ACTION_LEAVING', 'leaving');
define('GAME_ACTION_WARNING', 'warning');
define('GAME_ACTION_DON', 'don');
define('GAME_ACTION_SHERIFF', 'sheriff');
define('GAME_ACTION_LEGACY', 'legacy');
define('GAME_ACTION_NOMINATING', 'nominating');
define('GAME_ACTION_VOTING', 'voting');
define('GAME_ACTION_SHOOTING', 'shooting');

define('GAME_TO_EVENT_MAX_DISTANCE', 10800);

class Game
{
	public $data;
	
	function __construct($g, $feature_flags = GAME_FEATURE_MASK_MAFIARATINGS)
	{
		try
		{
			$this->data = NULL;
			if (is_numeric($g))
			{
				$row = Db::record(get_label('game'), 'SELECT json, feature_flags FROM games WHERE id = ?', (int)$g);
				$this->data = json_decode($row[0]);
				$feature_flags = (int)$row[1];
				if (isset($this->data->features))
				{
					$feature_flags = Game::leters_to_feature_flags($this->data->features);
				}
			}
			else if (is_string($g))
			{
				$this->data = json_decode($g);
				if (isset($this->data->features))
				{
					$feature_flags = Game::leters_to_feature_flags($this->data->features);
				}
			}
			else if ($g instanceof Game)
			{
				$this->data = clone $g->data;
			}
			else
			{
				$this->data = new stdClass();
				$this->data->id = (int)$g->id;
				$this->data->clubId = (int)$g->club_id;
				$this->data->eventId = (int)$g->event_id;
				$this->data->startTime = (int)$g->start_time;
				$this->data->endTime = (int)$g->end_time;
				$this->data->language = get_lang_code($g->lang);
				$this->data->rules = $g->rules_code;
				$this->data->features = Game::feature_flags_to_leters($feature_flags);
				if (!is_null($g->tournament_id) && $g->tournament_id > 0)
				{
					$this->data->tournamentId = (int)$g->tournament_id;
				}
				if ($g->flags & 1) // Used to be named constant, but its removed now. This code will also be removed soon, so hardcoding it to 1.
				{
					$this->data->rating = false;
				}
				
				if ($g->gamestate == 17 /*GAME_MAFIA_WON*/)
				{
					$this->data->winner = 'maf';
				}
				else if ($g->gamestate == 18 /*GAME_CIVIL_WON*/)
				{
					$this->data->winner = 'civ';
				}
				else
				{
					throw new Exc(get_label('The game [0] is not finished yet.', $g->id));
				}
				$this->data->moderator = new stdClass();
				$this->data->moderator->id = $g->moder_id;
				$this->data->players = array();
				for ($i = 0; $i < 10; ++$i)
				{
					$p = $g->players[$i];
					$player = new stdClass();
					if ($p->id != 0)
					{
						$player->id = $p->id;
					}
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
					if (isset($p->extra_points) && $p->extra_points)
					{
						$player->bonus = $p->extra_points;
					}
					if (isset($p->bonus))
					{
						if (!isset($player->bonus))
						{
							$player->bonus = $p->bonus;
						}
						else if (is_array($player->bonus))
						{
							$player->bonus[] = $p->bonus;
						}
						else
						{
							$player->bonus = array($player->bonus, $p->bonus);
						}
					}
					if (!empty($p->comment))
					{
						$player->comment = $p->comment;
					}
					switch ($p->role)
					{
						case ROLE_SHERIFF:
							$player->role = 'sheriff';
							break;
						case ROLE_DON:
							$player->role = 'don';
							break;
						case ROLE_MAFIA:
							$player->role = 'maf';
							break;
						case ROLE_CIVILIAN: 
							// If role is not set - civ is assumed, so we are not setting role in this case. Although 'civ' can also be set.
							// $player->role = 'civ';
							break;
						default:
							throw new Exc('Invalid role for player ' . ($i + 1) . ': ' . $p->role);
					}
					if ($p->state != 0 /*PLAYER_STATE_ALIVE*/)
					{
						$player->death = new stdClass();
						$player->death->round = $p->kill_round;
						if ($p->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/ && $p->kill_reason == 0 /*KILL_REASON_NORMAL*/)
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
							case 1 /*KILL_REASON_GIVE_UP*/:
								$player->death->type = DEATH_TYPE_GIVE_UP;
								break;
							case 2 /*KILL_REASON_WARNINGS*/:
								$player->death->type = DEATH_TYPE_WARNINGS;
								break;
							case 3 /*KILL_REASON_KICK_OUT*/:
								$player->death->type = DEATH_TYPE_KICK_OUT;
								break;
							case 4 /*KILL_REASON_TEAM_KICK_OUT*/:
								$player->death->type = DEATH_TYPE_TEAM_KICK_OUT;
								break;
							case 0 /*KILL_REASON_NORMAL*/:
							default:
								if ($p->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/)
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
						case 2 /*LOGREC_WARNING*/:
							$player = $this->data->players[$log->player];
							$player->warnings[] = Game::get_gametime_info($g, $log);
							if (count($player->warnings) > 3)
							{
								$player->death->time = $player->warnings[3];
							}
							break;
						case 3 /*LOGREC_GIVE_UP*/:
						case 4 /*LOGREC_KICK_OUT*/:
						case 8 /*LOGREC_TEAM_KICK_OUT*/:
							$this->data->players[$log->player]->death->time = Game::get_gametime_info($g, $log);
							break;
						case 0 /*LOGREC_NORMAL*/:
							if ($log->gamestate == 5 /*GAME_DAY_PLAYER_SPEAKING*/ && $log->current_nominant >= 0)
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
			}
			$this->expected_flags = $this->flags = $feature_flags;
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
		$this->data->features = Game::feature_flags_to_leters($this->flags);
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
								else if (
									($this->flags & GAME_FEATURE_FLAG_VOTING) != 0 &&
									$this->set_issue($fix, 'Player ' . ($i + 1) . ' is specified as voted out in round ' . $death_round . '. But accordiong to the voting info, no one was voted out in this round.', ' All votings are removed.'))
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
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (!isset($player->nominating))
			{
				continue;
			}
			$death_time = $this->get_player_death_time($i + 1, true);
			$speech_time->round = count($player->nominating) - 1;
			if (get_rule($this->get_rules(), RULES_KILLED_NOMINATE) == RULES_KILLED_NOMINATE_ALLOWED && $death_time->round == $speech_time->round && $death_time->time == GAMETIME_NIGHT_KILL_SPEAKING)
			{
				// player was shot in the night time and he is allowed to nominate
				$speech_time->time = GAMETIME_NIGHT_KILL_SPEAKING;
			}
			else
			{
				$speech_time->time = GAMETIME_SPEAKING;
				$speech_time->speaker = $i + 1;
			}
			if ($death_time != NULL && $speech_time->round >= 0 && $this->compare_gametimes($death_time, $speech_time) < 0)
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
				if (get_rule($this->get_rules(), RULES_FIRST_DAY_VOTING) != RULES_FIRST_DAY_VOTING_TO_TALK)
				{
					return $round > 0;
				}
				break;
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
				if (isset($voting->canceled) && $voting->canceled)
				{
					continue;
				}
				else if (!isset($voting->winner))
				{
					continue;
				}
				else if (is_numeric($voting->winner))
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
			case 0: // GAME_NOT_STARTED:
			case 1: // GAME_NIGHT0_START:
				$gametime->round = 0;
				$gametime->time = GAMETIME_START;
				break;
			case 2: // GAME_NIGHT0_ARRANGE:
				$gametime->round = 0;
				$gametime->time = GAMETIME_ARRANGEMENT;
				break;
			case 3: // GAME_DAY_START:
				$gametime->round = $log->round;
				if ($log->player_speaking >= 0)
				{
					$gametime->time = GAMETIME_NIGHT_KILL_SPEAKING;
				}
				else
				{
					$gametime->time = GAMETIME_DAY_START;
				}
				break;
			case 4: // GAME_DAY_KILLED_SPEAKING:
			case 21: // GAME_DAY_GUESS3:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_NIGHT_KILL_SPEAKING;
				break;
			case 5: // GAME_DAY_PLAYER_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_SPEAKING;
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case 6: // GAME_VOTING_START:
			case 20: // GAME_DAY_FREE_DISCUSSION:
				$gametime->round = $log->round;
				$gametime->votingRound = 0;
				$gametime->time = GAMETIME_VOTING;
				break;
			case 7: // GAME_VOTING_KILLED_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_DAY_KILL_SPEAKING;
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case 8: // GAME_VOTING:
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
			case 9: // GAME_VOTING_MULTIPLE_WINNERS:
				$gametime->round = $log->round;
				$gametime->votingRound = 1;
				$gametime->time = GAMETIME_VOTING;
				break;
			case 10: // GAME_VOTING_NOMINANT_SPEAKING:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_VOTING;
				$gametime->votingRound = 0; // how to find out voting round??
				$gametime->speaker = $log->player_speaking + 1;
				break;
			case 11: // GAME_NIGHT_START:
			case 12: // GAME_NIGHT_SHOOTING:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_SHOOTING;
				break;
			case 13: // GAME_NIGHT_DON_CHECK:
			case 14: // GAME_NIGHT_DON_CHECK_END:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_DON;
				break;
			case 15: // GAME_NIGHT_SHERIFF_CHECK:
			case 16: // GAME_NIGHT_SHERIFF_CHECK_END:
				$gametime->round = $log->round + 1;
				$gametime->time = GAMETIME_SHERIFF;
				break;
			case 17: // GAME_MAFIA_WON:
			case 18: // GAME_CIVIL_WON:
			case 22: // GAME_CHOOSE_BEST_PLAYER:
			case 23: // GAME_CHOOSE_BEST_MOVE:
				$gametime->round = $log->round;
				$gametime->time = GAMETIME_END;
				$pl = $gs->players[0];
				if ($pl->state != 0 /*PLAYER_STATE_ALIVE*/ && $pl->kill_reason == 0 /*KILL_REASON_NORMAL*/)
				{
					$gametime->speaker = 1;
					$gametime->time = ($pl->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/ ? GAMETIME_NIGHT_KILL_SPEAKING : GAMETIME_DAY_KILL_SPEAKING);
				}
				for ($i = 1; $i < 10; ++$i)
				{
					$p = $gs->players[$i];
					if ($p->state == 0 /*PLAYER_STATE_ALIVE*/ || $p->kill_reason != 0 /*KILL_REASON_NORMAL*/)
					{
						continue;
					}
					if ($p->kill_round > $pl->kill_round || ($p->kill_round == $pl->kill_round && $p->state == 2 /*PLAYER_STATE_KILLED_DAY*/))
					{
						$pl = p;
						$gametime->speaker = $i + 1;
						$gametime->time = ($p->state == 1 /*PLAYER_STATE_KILLED_NIGHT*/ ? GAMETIME_NIGHT_KILL_SPEAKING : GAMETIME_DAY_KILL_SPEAKING);
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
	
	static function is_night($gametime)
	{
		if (isset($gametime->time))
		{
			switch ($gametime->time)
			{
				case GAMETIME_START:
				case GAMETIME_ARRANGEMENT:
				case GAMETIME_SHOOTING:
				case GAMETIME_DON:
				case GAMETIME_SHERIFF:
					return true;
			}
		}
		return false;
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
	
	function get_last_gametime($including_speech = false)
	{
		$gametime = NULL;
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->death))
			{
				$g = $this->get_player_death_time($i + 1, $including_speech);
				if ($gametime == NULL || $this->compare_gametimes($gametime, $g) < 0)
				{
					$gametime = $g;
				}
			}
			if (isset($player->warnings) && is_array($player->warnings))
			{
				foreach ($player->warnings as $warning)
				{
					if ($gametime == NULL || $this->compare_gametimes($gametime, $warning) < 0)
					{
						$gametime = $warning;
					}
				}
			}
		}
		return $gametime;
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
	
	function init_votings($force = false)
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
		
		$prev_voting_end_time = NULL;
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
			$total_voting_rounds = 1;
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
						$total_voting_rounds = max($total_voting_rounds, $voting_rounds);
						
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
							
							if (!isset($nom->voting) || !is_array($nom->voting))
							{
								$nom->voting = array();
							}
							if (count($nom->voting) < $j)
							{
								for ($k = 0; $k < $j; ++$k)
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
							$nom->voting = array($i + 1);
						}
						else if (is_array($nom->voting[0]))
						{
							// There were multiple voting rounds. This player participated in the first round only. Apparently they was kicked off before the next rounds.
							$nom->voting[0][] = $i + 1;
						}
						else
						{
							$nom->voting[] = $i + 1;
						}
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
			
			// Was voting canceled
			if ($this->flags & GAME_FEATURE_FLAG_DEATH_TIME)
			{
				// todo check how antimonster rule is set: RULES_ANTIMONSTER - RULES_ANTIMONSTER_NO_VOTING, RULES_ANTIMONSTER_NO, RULES_ANTIMONSTER_NOMINATED, RULES_ANTIMONSTER_PARTICIPATION
				// Note that player can be shot or voted out and killed by warnings at the same time. In this case voting is not canceled even if RULES_ANTIMONSTER_NO_VOTING is set.
				// How to support it in data?
				$voting_end_time = new stdClass();
				if (isset($voting->winner))
				{
					$voting_end_time->round = $round;
					$voting_end_time->time = GAMETIME_VOTING;
					$voting_end_time->votingRound = $total_voting_rounds - 1;
					if (is_numeric($voting->winner))
					{
						$voting_end_time->nominant = $voting->winner;
					}
					else
					{
						$voting_end_time->nominant = $voting->winner[count($voting->winner)-1];
					}
				}
				else
				{
					$voting_end_time->round = $round + 1;
					$voting_end_time->time = GAMETIME_SHOOTING;
				}
				// echo 'round: ' . $round . '<br>';
				// print_json($voting_end_time);
				
				foreach ($this->data->players as $player)
				{
					// todo check how antimonster rule is set: RULES_ANTIMONSTER - RULES_ANTIMONSTER_NO_VOTING, RULES_ANTIMONSTER_NO, RULES_ANTIMONSTER_NOMINATED, RULES_ANTIMONSTER_PARTICIPATION
					// Note that player can be shot or voted out and killed by warnings at the same time. In this case voting is not canceled even if RULES_ANTIMONSTER_NO_VOTING is set.
					// How to support it in data?
					if (
						isset($player->death) && 
						isset($player->death->time) &&
						$player->death->type != DEATH_TYPE_NIGHT && 
						$player->death->type != DEATH_TYPE_DAY &&
						$this->compare_gametimes($player->death->time, $voting_end_time) < 0 &&
						($prev_voting_end_time == NULL || $this->compare_gametimes($prev_voting_end_time, $player->death->time) <= 0))
					{
						$voting->canceled = true;
					}
				}
				$prev_voting_end_time = $voting_end_time;
			}
			
			$this->votings[] = $voting;
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
					
					$this->init_votings(); // we probably should not use votings in compare_gametimes, but this is the easiest fix
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
					
					$this->init_votings(); // we probably should not use votings in compare_gametimes, but this is the easiest fix
					if ($gt1->speaker != $gt2->speaker && $gt1->round < count($this->votings))
					{
						$voting = $this->votings[$gt1->round];
						foreach ($voting->nominants as $nom)
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
				$this->init_votings(); // we probably should not use votings in compare_gametimes, but this is the easiest fix
				if ($gt1->speaker != $gt2->speaker && $gt1->round < count($this->votings))
				{
					$voting = $this->votings[$gt1->round];
					foreach ($voting->nominants as $nom)
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
		$flags &= $this->flags;
		if ($flags == 0)
		{
			return;
		}
		
		$reinit_votings = false;
		do
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
		while ($flags != 0);
		
		if (isset($this->votings) && $reinit_votings)
		{
			$this->init_votings(true);
		}
		$this->data->features = Game::feature_flags_to_leters($this->flags);
	}
	
	static function feature_flags_to_leters($flags)
	{
		$letters = '';
		if ($flags & GAME_FEATURE_FLAG_ARRANGEMENT)
		{
			$letters .= 'a';
		}
		if ($flags & GAME_FEATURE_FLAG_DON_CHECKS)
		{
			$letters .= 'g';
		}
		if ($flags & GAME_FEATURE_FLAG_SHERIFF_CHECKS)
		{
			$letters .= 's';
		}
		if ($flags & GAME_FEATURE_FLAG_DEATH)
		{
			$letters .= 'd';
		}
		if ($flags & GAME_FEATURE_FLAG_DEATH_ROUND)
		{
			$letters .= 'u';
		}
		if ($flags & GAME_FEATURE_FLAG_DEATH_TYPE)
		{
			$letters .= 't';
		}
		if ($flags & GAME_FEATURE_FLAG_DEATH_TIME)
		{
			$letters .= 'c';
		}
		if ($flags & GAME_FEATURE_FLAG_LEGACY)
		{
			$letters .= 'l';
		}
		if ($flags & GAME_FEATURE_FLAG_SHOOTING)
		{
			$letters .= 'h';
		}
		if ($flags & GAME_FEATURE_FLAG_VOTING)
		{
			$letters .= 'v';
		}
		if ($flags & GAME_FEATURE_FLAG_VOTING_KILL_ALL)
		{
			$letters .= 'k';
		}
		if ($flags & GAME_FEATURE_FLAG_NOMINATING)
		{
			$letters .= 'n';
		}
		if ($flags & GAME_FEATURE_FLAG_WARNINGS)
		{
			$letters .= 'w';
		}
		if ($flags & GAME_FEATURE_FLAG_WARNINGS_DETAILS)
		{
			$letters .= 'r';
		}
		return $letters;
	}
	
	static function leters_to_feature_flags($letters)
	{
		$flags = 0;
		for ($i = 0; $i < strlen($letters); ++$i)
		{
			switch ($letters[$i])
			{
				case 'a':
					$flags |= GAME_FEATURE_FLAG_ARRANGEMENT;
					break;
				case 'g':
					$flags |= GAME_FEATURE_FLAG_DON_CHECKS;
					break;
				case 's':
					$flags |= GAME_FEATURE_FLAG_SHERIFF_CHECKS;
					break;
				case 'd':
					$flags |= GAME_FEATURE_FLAG_DEATH;
					break;
				case 'u':
					$flags |= GAME_FEATURE_FLAG_DEATH_ROUND;
					break;
				case 't':
					$flags |= GAME_FEATURE_FLAG_DEATH_TYPE;
					break;
				case 'c':
					$flags |= GAME_FEATURE_FLAG_DEATH_TIME;
					break;
				case 'l':
					$flags |= GAME_FEATURE_FLAG_LEGACY;
					break;
				case 'h':
					$flags |= GAME_FEATURE_FLAG_SHOOTING;
					break;
				case 'v':
					$flags |= GAME_FEATURE_FLAG_VOTING;
					break;
				case 'k':
					$flags |= GAME_FEATURE_FLAG_VOTING_KILL_ALL;
					break;
				case 'n':
					$flags |= GAME_FEATURE_FLAG_NOMINATING;
					break;
				case 'w':
					$flags |= GAME_FEATURE_FLAG_WARNINGS;
					break;
				case 'r':
					$flags |= GAME_FEATURE_FLAG_WARNINGS_DETAILS;
					break;
			}
		}
		return $flags;
	}
	
	function get_actions()
	{
		$actions = array();
		$arrangement = NULL;
		$shooting = array();
		for($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			if (isset($player->arranged))
			{
				if ($arrangement == NULL)
				{
					$arrangement = new stdClass();
					$arrangement->round = 0;
					$arrangement->time = GAMETIME_ARRANGEMENT;
					$arrangement->action = GAME_ACTION_ARRANGEMENT;
					$arrangement->players = array();
					$actions[] = $arrangement;
				}
				for ($j = count($arrangement->players); $j < $player->arranged - 1; ++$j)
				{
					$arrangement->players[$j] = NULL;
				}
				$arrangement->players[$player->arranged - 1] = $i + 1;
			}
			if (isset($player->death))
			{
				$action = $this->get_player_death_time($i + 1, true);
				if ($action == NULL)
				{
					$action = new stdClass();
				}
				$action->action = GAME_ACTION_LEAVING;
				$action->player = $i + 1;
				$actions[] = $action;
			}
			if (isset($player->warnings) && is_array($player->warnings))
			{
				foreach ($player->warnings as $warning)
				{
					$action = clone $warning;
					$action->action = GAME_ACTION_WARNING;
					$action->player = $i + 1;
					$actions[] = $action;
				}
			}
			if (isset($player->don))
			{
				$action = new stdClass();
				$action->round = $player->don;
				$action->time = GAMETIME_DON;
				$action->action = GAME_ACTION_DON;
				$action->player = $i + 1;
				$actions[] = $action;
			}
			if (isset($player->sheriff))
			{
				$action = new stdClass();
				$action->round = $player->sheriff;
				$action->time = GAMETIME_SHERIFF;
				$action->action = GAME_ACTION_SHERIFF;
				$action->player = $i + 1;
				$actions[] = $action;
			}
			if (isset($player->legacy))
			{
				$action = new stdClass();
				$action->round = 1;
				if (isset($player->dead))
				{
					if (is_numeric($player->dead))
					{
						$action->round = $player->dead;
					}
					else if(isset($player->dead->round))
					{
						$action->round = $player->dead->round;
					}
				}
				$action->time = GAMETIME_NIGHT_KILL_SPEAKING;
				$action->action = GAME_ACTION_LEGACY;
				$action->player = $i + 1;
				$action->legacy = $player->legacy;
				$actions[] = $action;
			}
			if (isset($player->shooting))
			{
				for ($round = count($shooting); $round < count($player->shooting); ++$round)
				{
					$action = new stdClass();
					$action->round = $round + 1;
					$action->time = GAMETIME_SHOOTING;
					$action->action = GAME_ACTION_SHOOTING;
					$action->shooting = array();
					$actions[] = $action;
					$shooting[] = $action;
				}
				
				for ($round = 0; $round < count($player->shooting); ++$round)
				{
					$shooting[$round]->shooting[$player->shooting[$round]][] = $i+1;
				}
			}
		}
		
		$this->init_votings();
		for ($round = 0; $round < count($this->votings); ++$round)
		{
			$voting = $this->votings[$round];
			if (!isset($voting->nominants))
			{
				continue;
			}
			foreach ($voting->nominants as $nominant)
			{
				if (isset($nominant->by))
				{
					$action = new stdClass();
					$action->round = $round;
					$action->time = GAMETIME_SPEAKING;
					$action->action = GAME_ACTION_NOMINATING;
					$action->speaker = $nominant->by;
					$action->nominant = $nominant->nominant;
					$actions[] = $action;
				}
				if (isset($nominant->voting) && count($nominant->voting) > 0)
				{
					if (is_array($nominant->voting[0]))
					{
						for ($votingRound = 0; $votingRound < count($nominant->voting); ++$votingRound)
						{
							$action = new stdClass();
							$action->round = $round;
							$action->time = GAMETIME_VOTING;
							$action->votingRound = $votingRound;
							$action->nominant = $nominant->nominant;
							$action->action = GAME_ACTION_VOTING;
							$action->votes = $nominant->voting[$votingRound];
							$actions[] = $action;
						}
					}
					else
					{
						$action = new stdClass();
						$action->round = $round;
						$action->time = GAMETIME_VOTING;
						$action->votingRound = 0;
						$action->nominant = $nominant->nominant;
						$action->action = GAME_ACTION_VOTING;
						$action->votes = $nominant->voting;
						$actions[] = $action;
					}
				}
			}
		}
		usort($actions, array($this, 'compare_gametimes'));
		return $actions;
	}
	
	private static function add_time_help($param, $event)
	{
		$param->sub_param('round', 'round number when ' . $event . ' starting from 0');
		$param->sub_param('time', 'what was happening in the game when ' . $event . '. Possible values are:<ul>
			<li>"start": the game just started.</li>
			<li>"arrangement": mafia is arranging.</li>
			<li>"day start": a day just started.</li>
			<li>"night kill speaking": a player killed in night is speaking.</li>
			<li>"speaking": a player is speaking their normal day-speach.</li>
			<li>"voting": players are voting</li>
			<li>"day kill speaking": a voted-out player is speaking.</li>
			<li>"shooting": mafia is shooting.</li>
			<li>"don": don is checking.</li>
			<li>"sheriff": sheriff is checking.</li>
			<li>"end": the game just ended.</li>
		</ul>');
		$param->sub_param('speaker', 'an additional parameter specifying who was speaking when ' . $event . '. A number from 1 to 10. It must be set when time is one of: "speaking", "day kill speaking", or "night kill speaking". It can be set when time is "voting". When it is set for "voting" phase, this means that ' . $event . ' when one of the split players was speaking.', 'it is not applicable.');
		$param->sub_param('votingRound', 'voting round number. It can be set when the type is "voting". For example we have 9 players: 1-2-3 voted for 4; 4-5-6 voted for 7; 7-8-9 voted for 1. We call it votingRound 0. Then: 1-2-3-5 voted for 4; 4-6-8-9 voted for 7; 7 voted for 1. We call it votingRound 1. Etc. So for example if our structure is { "time": "voting", "speaker":1, "votingRound":1 }, this means that ' . $event . ' when player 1 was speaking after the second split. If votingRound was 0 or missing, this would mean that 1 was speaking after the first split.', 'either voting round is 0, or type is not "voting"');
		$param->sub_param('nominant', 'who were players voting for when ' . $event . '. It can only be set when time is "voting". For example: { "time":"voting", "nominant":1, "votingRound":1 } means that ' . $event . ' when town was voting for 1 in the second split (voting round 1).', $event . ' not in the voting phase.');
	}
	
	function get_gametime_text($gametime, $output_player_function = 'get_player_number_html')
	{
		if (!isset($gametime->time))
		{
			return get_label('at unknown time');
		}
		
		switch ($gametime->time)
		{
			case GAMETIME_START:
				return get_label('at the beginning of the game');
			case GAMETIME_ARRANGEMENT:
				return get_label('when mafia arranges');
			case GAMETIME_DAY_START:
				return get_label('at the beginning of the day');
			case GAMETIME_NIGHT_KILL_SPEAKING:
				for ($i = 0; $i < 10; ++$i)
				{
					$player = $this->data->players[$i];
					if (isset($player->death) && isset($player->death->type) && isset($player->death->round) && $player->death->type == DEATH_TYPE_NIGHT && $player->death->round == $gametime->round)
					{
						return get_label('during [0]\'s last speech', call_user_func($output_player_function, $this, $i+1));
					}
				}
				return get_label('during night kill last speech');
			case GAMETIME_SPEAKING:
				return get_label('during [0]\'s speech', call_user_func($output_player_function, $this, $gametime->speaker));
			case GAMETIME_VOTING:
				if (isset($gametime->nominant))
				{
					return get_label('during voting for [0]', call_user_func($output_player_function, $this, $gametime->nominant));
				}
				else if (isset($gametime->speaker))
				{
					return get_label('when [0] gives their 30 second speech on split', call_user_func($output_player_function, $this, $gametime->speaker));
				}
				return get_label('during votings');
			case GAMETIME_DAY_KILL_SPEAKING:
				return get_label('during [0]\'s last speech', call_user_func($output_player_function, $this, $gametime->speaker));
				break;
			case GAMETIME_SHOOTING:
				return get_label('when the mafia shoots');
			case GAMETIME_DON:
				return get_label('when the don checks');
			case GAMETIME_SHERIFF:
				return get_label('when the sheriff checks');
			case GAMETIME_END:
				return get_label('at the end of the game');
		}
		return '';
	}
	
	function is_participant($user_id)
	{
		if ($this->data->moderator->id == $user_id)
		{
			return true;
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			if (isset($this->data->players[$i]->id) && $this->data->players[$i]->id == $user_id)
			{
				return true;
			}
		}
		return false;
	}
	
	function change_user($user_id, $new_user_id, $nickname = NULL)
	{
		if ($user_id == 0 || !$this->is_participant($user_id))
		{
			return false;
		}
		
		$data = $this->data;
		if ($new_user_id != $user_id && $new_user_id > 0 && $this->is_participant($new_user_id))
		{
			throw new Exc(get_label('Unable to change one user to another in the game [0] because they both participated in it.', $data->id));
		}
		
		if ($data->moderator->id == $user_id)
		{
			if ($new_user_id <= 0)
			{
				throw new Exc(get_label('Unable to delete user from the game [0] because they refereed it. Try to merge them with someone instead.', $data->id));
			}
			$data->moderator->id = $new_user_id;
		}
		else for ($i = 0; $i < 10; ++$i)
		{
			$player = $data->players[$i];
			if (isset($player->id) && $player->id == $user_id)
			{
				if ($new_user_id != 0)
				{
					$player->id = $new_user_id;
				}
				else
				{
					unset($player->id);
				}
				if ($nickname != NULL)
				{
					$player->name = $nickname;
				}
				break;
			}
		}
		return true;
	}
	
	function setup_event()
	{
		global $_profile;
		
		$data = $this->data;
		list($timezone, $club_rules, $club_scoring_id, $club_langs, $fee, $currency_id) = Db::record(get_label('club'), 'SELECT ct.timezone, c.rules, c.scoring_id, c.langs, c.fee, c.currency_id FROM clubs c JOIN cities ct ON c.city_id = ct.id WHERE c.id = ?', $data->clubId);
		
		if (!isset($data->rules))
		{
			$data->rules = $club_rules;
		}
		
		$tournament_id = NULL;
		if (isset($data->eventId))
		{
			list($tournament_id, $timezone, $event_start, $event_duration) = Db::record(get_label('event'), 'SELECT e.tournament_id, c.timezone, e.start_time, e.duration FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE e.id = ?', $data->eventId);
		}
		else
		{
			$start_time = $data->startTime;
			if (!is_numeric($start_time))
			{
				$start_time = get_datetime($start_time, $timezone);
			}
			
			$end_time = $data->endTime;
			if (!is_numeric($end_time))
			{
				$end_time = get_datetime($end_time, $timezone);
			}
			
			$events = array();
			$query = new DbQuery('SELECT e.id, c.timezone, e.start_time, e.duration, e.tournament_id FROM events e JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE e.club_id = ? AND e.start_time + e.duration + ' . GAME_TO_EVENT_MAX_DISTANCE . ' >= ? AND e.start_time - ' . GAME_TO_EVENT_MAX_DISTANCE . ' <= ?', $data->clubId, $start_time, $end_time);
			while ($row = $query->next())
			{
				$events[] = $row;
			}
			
			switch (count($events))
			{
				case 0:
					// create event
					list($address_id, $address_name, $timezone, $address_count) = Db::record(get_label('address'), 'SELECT a.id, a.name, c.timezone, (SELECT count(*) FROM events e WHERE e.address_id = a.id) cnt FROM addresses a JOIN cities c ON c.id = a.city_id WHERE a.club_id = ? AND (a.flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY cnt DESC, a.id DESC LIMIT 1', $data->clubId);
					list($scoring_version) = Db::record(get_label('scoring'), 'SELECT version FROM scoring_versions WHERE scoring_id = ? ORDER BY version DESC LIMIT 1', $club_scoring_id);
					
					$event_name = get_label('Regular Event');
					$scoring_options = '{}';
					
					if (!is_numeric($data->startTime))
					{
						$data->startTime = $start_time = get_datetime($data->startTime, $timezone);
					}
					if (!is_numeric($data->endTime))
					{
						$data->endTime = $end_time = get_datetime($data->endTime, $timezone);
					}
					$event_start = $start_time;
					$event_duration = $end_time - $start_time;
					
					Db::exec(
						get_label('event'), 
						'INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, rules, scoring_id, scoring_version, scoring_options, fee, currency_id) ' .
						'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$name, $address_id, $data->clubId, $event_start, $event_duration,
						EVENT_FLAG_ALL_CAN_REFEREE, $club_langs, $data->rules, 
						$club_scoring_id, $scoring_version, $scoring_options, $fee, $currency_id);
					list ($event_id) = Db::record(get_label('event'), 'SELECT LAST_INSERT_ID()');
				
					$log_details = new stdClass();
					$log_details->name = $event_name;
					$log_details->address_name = $address_name;
					$log_details->address_id = $address_id;
					$log_details->start = format_date('d/m/y H:i', $event_start, $timezone);
					$log_details->duration = $event_duration;
					$log_details->flags = EVENT_FLAG_ALL_CAN_REFEREE;
					$log_details->langs = $club_langs;
					$log_details->rules_code = $data->rules;
					$log_details->scoring_id = $club_scoring_id;
					$log_details->scoring_version = $scoring_version;
					db_log(LOG_OBJECT_EVENT, 'created', $log_details, $event_id, $data->clubId);
					break;
					
				case 1:
					list($event_id, $timezone, $event_start, $event_duration, $tournament_id) = $events[0];
					break;
					
				default:
					// find the closest event
					$event = $events[0];
					$distance = GAME_TO_EVENT_MAX_DISTANCE;
					foreach ($events as $row)
					{
						list($event_id, $event_timezone, $event_start, $event_duration, $tournament_id) = $row;
						$event_distance = max($event_start - $end_time, $start_time - $event_start - $event_duration);
						if ($event_distance < $distance)
						{
							$event = $event_id;
							$distance = $event_distance;
						}
					}
					list($event_id, $event_timezone, $event_start, $event_duration, $tournament_id) = $event;
					break;
			}
			$data->eventId = (int)$event_id;
		}
		
		// Change times to timestamps using event timezone if needed
		if (!is_numeric($data->startTime))
		{
			$data->startTime = get_datetime($data->startTime, $timezone);
		}
		if (!is_numeric($data->endTime))
		{
			$data->endTime = get_datetime($data->endTime, $timezone);
		}
		
		// Update event if needed
		$update_event = false;
		if ($event_start > $data->startTime)
		{
			$update_event = true;
			$event_start = $data->startTime;
		}
		if ($event_start + $event_duration < $data->endTime)
		{
			$update_event = true;
			$event_duration = $data->endTime - $event_start;
		}
		if ($update_event)
		{
			Db::exec(get_label('event'), 'UPDATE events SET start_time = ?, duration = ? WHERE id = ?', $event_start, $event_duration, $data->eventId);
		}
		
		// Now make sure tournament field is ok
		if (isset($data->tournamentId) && $data->tournamentId != $tournament_id)
		{
			list($tournament_name, $tournament_flags) = Db::record(get_label('tournament'), 'SELECT name, flags FROM tournaments WHERE id = ?', $data->tournamentId);
			if (($tournament_flags | TOURNAMENT_FLAG_SINGLE_GAME) == 0)
			{
				throw new Exc(get_label('Game [0] can not be played in the tournament [1]', $this->id, $tournament_name));
			}
		}
		return $timezone;
	}
	
	private function is_players_result_changed()
	{
		$db_players = array(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);
		$query = new DbQuery('SELECT number, user_id, won FROM players WHERE game_id = ?', $this->data->id);
		while ($row = $query->next())
		{
			$db_players[(int)$row[0]-1] = $row;
		}
		
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $this->data->players[$i];
			$db_player = $db_players[$i];
			if ($db_player != NULL)
			{
				list($number, $user_id, $won) = $db_player;
				if (!isset($player->id) || $player->id != $user_id)
				{
					return true;
				}
				else if ($this->data->winner == 'civ')
				{
					if (isset($player->role) && ($player->role == 'maf' || $player->role == 'don'))
					{
						if ($won)
						{
							return true;
						}
					}
					else if (!$won)
					{
						return true;
					}
				}
				else if (isset($player->role) && ($player->role == 'maf' || $player->role == 'don'))
				{
					if (!$won)
					{
						return true;
					}
				}
				else if ($won)
				{
					return true;
				}
			}
			else if (isset($player->id) && $player->id > 0)
			{
				return true;
			}
		}
		return false;
	}
	
	// returns true if the ratings are to be rebuild as the result of the update
	function update()
	{
		$this->check(false);
		$data = $this->data;
		$json = $this->to_json();
		$feature_flags = Game::leters_to_feature_flags($data->features);
		$is_data_rating = !isset($data->rating) || $data->rating;
		
		if (!isset($data->id))
		{
			throw new Exc(get_label('Game number is not set.'));
		}
		list($is_canceled, $is_rating) = Db::record(get_label('game'), 'SELECT is_canceled, is_rating FROM games WHERE id = ?', $data->id);
				
		if (!isset($data->clubId))
		{
			throw new Exc(get_label('Club id is not set.'));
		}
		
		if (!isset($data->startTime))
		{
			throw new Exc(get_label('Game start time is not set.'));
		}
		
		if (!isset($data->endTime))
		{
			throw new Exc(get_label('Game end time is not set.'));
		}
		
		if (!isset($data->language))
		{
			$data->language = 'ru';
		}
		$language = get_lang_by_code($data->language);
		
		if (!isset($data->rules))
		{
			$data->rules = default_rules_code();
		}
		
		// Fix json if needed and save original json to game_issues table
		if (isset($this->issues))
		{
			$this->check(true);
			$new_json = $this->to_json();
			$new_feature_flags = Game::leters_to_feature_flags($data->features);
			$issues = '<ul>';
			foreach ($this->issues as $issue)
			{
				$issues .= '<li>' . $issue . '</li>';
			}
			$issues .= '</ul>';
			Db::exec(get_label('game issue'), 'INSERT INTO game_issues (game_id, json, issues, feature_flags, new_feature_flags) VALUES (?, ?, ?, ?, ?)', $data->id, $json, $issues, $feature_flags, $new_feature_flags);
			$json = $new_json;
			$feature_flags = $new_feature_flags;
		}
		
		$rebuild_ratings = false;
		if (!$is_canceled)
		{
			if ($is_data_rating || $is_rating)
			{
				list($games_after_count) = Db::record(get_label('game'), 'SELECT count(*) FROM games g JOIN players p ON g.id = p.game_id JOIN players p1 ON p.user_id = p1.user_id JOIN games g1 ON g1.id = p1.game_id WHERE g.id = ? AND g1.is_rating <> 0 AND g1.is_canceled = 0 AND (g1.end_time > g.end_time OR (g1.end_time = g.end_time AND g1.id > g.id))', $data->id);
				if ($games_after_count > 0)
				{
					if ($is_data_rating && $is_rating)
					{
						$rebuild_ratings = $this->is_players_result_changed();
					}
					else
					{
						$rebuild_ratings = true;
					}
				}
			}
			
			// clean up stats
			Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated - 1 WHERE id = (SELECT moderator_id FROM games WHERE id = ?)', $data->id);
			Db::exec(get_label('user'), 'UPDATE players p JOIN users u ON u.id = p.user_id SET u.games = u.games - 1, u.games_won = u.games_won - p.won, u.rating = u.rating - p.rating_earned WHERE p.game_id = ?', $data->id);
			Db::exec(get_label('player'), 'DELETE FROM dons WHERE game_id = ?', $data->id);
			Db::exec(get_label('player'), 'DELETE FROM sheriffs WHERE game_id = ?', $data->id);
			Db::exec(get_label('player'), 'DELETE FROM mafiosos WHERE game_id = ?', $data->id);
			Db::exec(get_label('player'), 'DELETE FROM players WHERE game_id = ?', $data->id);
			Db::exec(get_label('game issue'), 'DELETE FROM game_issues WHERE game_id = ? AND feature_flags = ?', $data->id, $feature_flags);
		}
		
		// Save game json and feature flags
		$timezone = $this->setup_event();
		
		$tournament_id = isset($data->tournamentId) ? $data->tournamentId : NULL;
		if ($data->winner == 'maf')
		{
			$game_result = 2;
		}
		else if ($data->winner == 'civ')
		{
			$game_result = 1;
		}
		else
		{
			$game_result = 3;
		}
		
		Db::exec(get_label('game'),
			'UPDATE games SET json = ?, feature_flags = ?, club_id = ?, event_id = ?, tournament_id = ?, moderator_id = ?, ' .
				'language = ?, start_time = ?, end_time = ?, result = ?, ' .
				'rules = ?, is_rating = ? WHERE id = ?',
			$json, $feature_flags, $data->clubId, $data->eventId, $tournament_id, $data->moderator->id,
			$language, $data->startTime, $data->endTime, $game_result,
			$data->rules, $is_data_rating, $data->id);
		
		if (!$is_canceled)
		{
			$stats = new GamePlayersStats($this);
			$stats->save();
			
			// calculate ratings
			update_game_ratings($data->id);
			
			Db::exec(get_label('user'), 'UPDATE players p JOIN users u ON u.id = p.user_id SET u.games = u.games + 1, u.games_won = u.games_won + p.won, u.rating = u.rating + p.rating_earned WHERE p.game_id = ?', $data->id);
			Db::exec(get_label('user'), 'UPDATE users SET games_moderated = games_moderated + 1 WHERE id = ?', $data->moderator->id);
		}
		
		if ($rebuild_ratings)
		{
			Game::rebuild_ratings($data->id, $data->endTime);
		}
		
		Db::exec(get_label('event'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $data->eventId);
		Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
		
		db_log(LOG_OBJECT_GAME, 'updated', NULL, $data->id, $data->clubId);
		return $rebuild_ratings;
	}
	
	static function rebuild_ratings($game_id, $end_time)
	{
		$query = new DbQuery('SELECT r.id, r.game_id, g.end_time FROM rebuild_ratings r LEFT OUTER JOIN games g ON g.id = r.game_id WHERE r.start_time = 0');
		if ($row = $query->next())
		{
			list($rebuild_id, $old_game_id, $old_game_end_time) = $row;
			if (is_null($game_id) || (!is_null($old_game_id) && ($end_time < $old_game_end_time || ($end_time == $old_game_end_time && $game_id < $old_game_id))))
			{
				Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE id = ?', $game_id, $rebuild_id);
			}
		}
		else
		{
			Db::exec(get_label('game'), 'INSERT INTO rebuild_ratings (game_id) VALUES (?)', $game_id);
		}
	}
	
	private static function feature_flags_help($param, $text)
	{
		$text .= '<ul>';
		$text .= '<li><b>a</b> - the game contains static arrangement information. Players who were staticaly arranged have "arrangement" field with round number that they were arranged for.</li>';
		$text .= '<li><b>g</b> - the game contains don checks. Players checked by don have "don" field with round number when they were checked.</li>';
		$text .= '<li><b>s</b> - the game contains sheriff checks. Players checked by sheriff have "sheriff" field with round number when they were checked.</li>';
		$text .= '<li><b>d</b> - the game contains death information. Players who died during the game have "death" field that contains the details about their death. If u, t, and c are not set it is just a true/false boolean.</li>';
		$text .= '<li><b>u</b> - the game contains death round information (<b>d</b> must be set when <b>u</b> is set). If <b>t</b> and <b>c</b> are not set - "death" is an integer, which means round number. If at least one of them is set, "death" is an object where property "round" contains round number.</li>';
		$text .= '<li><b>t</b> - the game contains death type information (<b>d</b> must be set when <b>t</b> is set). If u and c are not set - "death" is a string containing death type. If at least one of them is set, "death" is an object where property "type" contains death type.';
		$text .= '<li><b>c</b> - the game contains death time information (<b>d</b> must be set when <b>c</b> is set). If a player died of warnings, or was kicked out, or gave up, death.time field is set specifying when in the game it happened.</li>';
		$text .= '<li><b>l</b> - the game contains player legacy. When player dies the first night (or any other night by some rules) they can leave a legacy (aka best move, or prima nota). In this case a player will have a field "legacy" with an array of integers.</li>';
		$text .= '<li><b>h</b> - the game contains mafia shooting details. Mafia players have field "shooting" containing shooting details.</li>';
		$text .= '<li><b>v</b> - the game contains voting details. Players have "voting" field containg the details about their voting.</li>';
		$text .= '<li><b>k</b> - the game contains how players voted for killing all when the table was split (<b>v</b> must be set when <b>k</b> is set). The last value in "voting" array for a specific round is boolean flagging if a player voted for killin all.</li>';
		$text .= '<li><b>n</b> - the game contains nominating details. Players have "nominating" field containg the details about their voting.</li>';
		$text .= '<li><b>w</b> - the game contains warnings information. Players have "warnings" field containing what warnings each of them has.</li>';
		$text .= '<li><b>r</b> - the game contains warnings details (<b>v</b> must be set when <b>r</b> is set). Player\'s "warnings" field contains an array with times in the game when warning was received.</li>';
		$text .= '<ul>';
		$param->sub_param('features', $text);
	}
	
	static function api_help($param, $for_update)
	{
		$param->add_description('<p>Round numbering works this way. <ul><li>Round 0</li><ul><li>Night 0: mafia is arranging.</li><li>Day 0: town is speaking, may be splitting, normally <u>not</u> killing.</li></ul></li><li>Round 1<ul><li>Night 1: mafia is shooting. Don and sheriff are checking.</li><li>Day 1: town is speaking, may be splitting, normally killing.</li></ul><li>Etc.</li></ul>');
	
		$param->sub_param('id', 'Game id. Unique game identifier.');
		Game::feature_flags_help($param, 'what features this game record contains. This is a combination of letters. For example "gsdutclhnwr". Every letter means that the game contains some information:');
		$param->sub_param('clubId', 'Club id. Unique club identifier.');
		$param->sub_param('eventId', 'Event id. Unique event identifier.');
		$param->sub_param('tournamentId', 'Tournament id. Unique tournament identifier. Event in this case is a tournament round - semifinal, final, etc.', 'this is not a tournament game.');
		$param->sub_param('rating', 'When false this is non-rating game played for fun. It is kept in the database for user stats but it is not used in rating calculation nor tournament/event scoring.', 'True. This is a rating game.');
		$param->sub_param('startTime', 'Game start in ISO-8601.');
		$param->sub_param('endTime', 'Game end in ISO-8601.');
		$param->sub_param('timezone', 'Timezone in text format. For example "America/New_York".');
		$param->sub_param('language', 'Two letter code of the language the game was played. For example: "en" for English; "ru" for Russian.');
		$param->sub_param('rules', 'Rules code for the rules used in the game. Use <a href="rules.php?help">Get Rules API</a> to convert it to something readable.');
		$param->sub_param('winner', 'Who won the game. Possible values: "civ", "maf", or "tie".');
		$param1 = $param->sub_param('moderator', 'Game moderator.');
		$param1->sub_param('id', 'User id in ' . PRODUCT_NAME . ' of the moderator.');
		$param1 = $param->sub_param('players', 'The array of players who played. Array size is always 10. Players index in the array matches their player number in the table from 1 to 10.');
			$param1->sub_param('id', 'User id in ' . PRODUCT_NAME, 'the player is unknown. There is no user account for this player.');
			$param1->sub_param('name', 'Name used in this game.');
			$param1->sub_param('role', 'One of: "civ", "maf", "sheriff", or "don".', 'use "civ"');
			$param1->sub_param('arranged', 'Was arranged by mafia to be shooted down in the round (starting from 0).', ' either the player was not arranged, or the game does not have arrangement information (<b>a</b> is missing from the "features").');
			$param2 = $param1->sub_param('death', 'Player death information. Here are the options:<ul><li>When only <b>d</b> is set in "features": "death" is boolean meaning if the player died in the game or not.</li><li>When only <b>d</b> and <b>u</b> are set: "death" is an integer containing round number when the player died.</li><li>When only <b>d</b> and <b>t</b> are set: "death" is a string explained in death.type.</li><li>In any other case: "death" is an object explaned below.</li></ul>', 'either the player survived the game, or the game does not have death info (<b>d</b> is missing from the "features")');
				$param2->sub_param('round', 'Round number when player died. Starting from 0.', 'the game does not contain death round information (<b>u</b> is missing from the "features").');
				$param2->sub_param('type', 'Player\'s death type. Possible types are: <ul><li>"day" - voted out during a day.</li><li>"night" - killed in night by mafia.</li><li>"warnings" - got 4 warnings.</li><li>"kickOut" - kicked out from the game by the moderator.</li><li>"teamKickOut" - kicked out from the game by the moderator and their team lost because of that.</li><li>"giveUp" - gave up.</li></ul>.', 'the game does not contain death type information (<b>t</b> is missing from the "features").');
				$param3 = $param2->sub_param('time', 'When the player died. There is no need to specify time when death type is "hight" or "day", so it is normally not set for these death types. But if death type is "warnings", "kickOut", "teamKickOut", or "giveUp", "death" field contains an object with "time" field where death time is specified.', 'either death.type is "day"/"night", or the game does not contain death time information (<b>c</b> is missing from the "features")');
				Game::add_time_help($param3, 'the player died');
			$param2 = $param1->sub_param('warnings', 'If <b>r</b> is set in "features" warnings contain an array of gametime objects specifying when each warning was received. If <b>r</b> is not set, it is just a number of warnings.', 'either the player has no warnings, or the game has no warnings information (<b>w</b> is missing from the "features")');
			Game::add_time_help($param2, 'the player got warning');
			$param1->sub_param('don', 'The round (starting from 0) when the don checked this player.', 'either the player was not checked by don, or the game has no don checking information (<b>g</b> is missing from the "features")');
			$param1->sub_param('sheriff', 'The round (starting from 0) when the sheriff checked this player.', 'either the player was not checked by sheriff, or the game has no sheriff checking information (<b>s</b> is missing from the "features")');
			$param1->sub_param('bonus', 'either number of bonus points for the game, or "bestPlayer", or "bestMove", or "worstMove", or an array with any combination of these three.', 'there is no bonus for the player');
			$param1->sub_param('legacy', 'When player dies the first night (or any other night by some rules) they can leave a legacy (aka best move, or prima nota). In this case a player will have a field "legacy" with an array of integers.', 'either the player was not shot the first night, or the player did not leave a legacy, or there is no legacy information in the game (<b>l</b> is missing from the "features").');
			$param1->sub_param('comment', 'Moderator comment on the player. It is normally set for the players who have bonus. But it can be set for any player.', 'there is no moderator comment');
			$param1->sub_param('voting', 'How the player was voting. An array per round. For example suppose "voting" is [null, 10, [5,5,true]]. The meaning of it is:<ul><li>In round 0 - null. The player did not vote. Apparently no one, or only one was nominated.</li><li>In round 1 - 10. The player voted for player number 10.</li><li>In round 2 - [5,5,true]. The player voted two times for player number 5 and then voted yes to kill all players in the split.</li></ul>', 'either the player never voted in the game, or the game does not contain voting information (<b>v</b> is missing from the "features")');
			$param1->sub_param('nominating', 'How the player was nominating. An array per round. For example if "nominating" is [null,10,null,7], this means that the player nominated player 10 in round 1, player 7 in round 3, and did not nominatin in rounds 0 and 2.', 'either player did not nominate, or the game does not contain nominating information (<b>n</b> is missing from the "features")');
			$param1->sub_param('shooting', 'For mafia only. An array of numbers per round. Unlike nomination and voting the array starts from round 1, because nobody is shooting in night 0. For example [2,null,10] means that the player was shooting 2 in night 1; 10 in night 3; and did not make a shot in night 2.', 'either player did not shoot, or the game does not contain shooting information (<b>h</b> is missing from the "features")');
	}
}

?>