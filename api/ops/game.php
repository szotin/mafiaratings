<?php

require_once '../../include/api.php';
require_once '../../include/event.php';
require_once '../../include/email.php';
require_once '../../include/video.php';
require_once '../../include/game.php';
require_once '../../include/tournament.php';
require_once '../../include/user.php';
require_once '../../include/mwt_game.php';

define('EVENTS_FUTURE_LIMIT', 1209600); // 2 weeks

define('GAME_SETTINGS_SIMPLIFIED_CLIENT', 0x1);
define('GAME_SETTINGS_START_TIMER', 0x2);
define('GAME_SETTINGS_NO_BLINKING', 0x8); // 0x4 is available

function def_club()
{
	global $_profile;

	$club_id = $_profile->user_club_id;
	if (!isset($_profile->clubs[$club_id]))
	{
		$club_id = NULL;
	}
	
	if ($club_id == NULL)
	{
		$priority = 0;
		foreach ($_profile->clubs as $club)
		{
			if ($club->flags & (USER_PERM_REFEREE | USER_PERM_MANAGER) == (USER_PERM_REFEREE | USER_PERM_MANAGER))
			{
				$club_id = $club->id;
				break;
			}
			else if ($club->flags & USER_PERM_REFEREE)
			{
				$priority = 1;
				$club_id = $club->id;
			}
			else if ($priority <= 0)
			{
				$club_id = $club->id;
			}
		}
	}
	
	if ($club_id == NULL)
		throw new Exc(get_label('Please join at least one club.'));
		
	return $club_id;
}

function compare_players($player1, $player2)
{
	return strcmp($player1->lower_case, $player2->lower_case);
}

class GPlayer
{
	function __construct($id, $name, $club, $u_flags, $club_user_flags)
	{
		$this->id = (int)$id;
		$this->name = $name;
		$this->club = $club; 
		$this->nicks = array();
		$this->flags = (int)(($club_user_flags & (USER_PERM_PLAYER | USER_PERM_REFEREE)) + ($u_flags & (USER_FLAG_MALE | USER_FLAG_IMMUNITY)));
	}
}

class GClub
{
	public $id;
	public $name;
	public $city;
	public $country;
	public $langs;
	public $players;
	public $haunters;
	public $events;
	public $rules;
	public $addrs;
	public $icon;
	public $rules_code;
	
	function __construct($id, $gs)
	{
		global $_profile, $_lang;
		$club = $_profile->clubs[$id];
		$this->id = (int)$club->id;
		
		$club_rules = new stdClass();
		$this->name = $club_rules->name = $club->name;
		$this->rules_code = $club_rules->code = $club->rules_code;
		$this->rules = array($club_rules);
		
		$this->city = $club->city;
		$this->country = $club->country;
		$this->langs = (int)$club->langs;
		if (($club->club_flags & CLUB_ICON_MASK) != 0)
		{
			$this->icon = CLUB_PICS_DIR . ICONS_DIR . $club->id . '.png';
		}
		else
		{
			$this->icon = 'images/' . ICONS_DIR . 'club.png';
		}
		
		$haunters_count = 0;
		$this->haunters = array();
		$this->players = array();
		$query = new DbQuery(
			'SELECT u.id, nu.name, c.name, u.flags, uc.flags FROM club_users uc' .
				' JOIN users u ON u.id = uc.user_id' .
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
				' WHERE (uc.flags & ' . (USER_PERM_PLAYER | USER_PERM_REFEREE) .
					') <> 0 AND uc.club_id = ?' .
				' ORDER BY u.rating DESC',
			$id);
		while ($row = $query->next())
		{
			list ($user_id, $user_name, $user_club, $u_flags, $club_user_flags) = $row;
			$this->players[$user_id] = new GPlayer($user_id, $user_name, $user_club, $u_flags, $club_user_flags);
			if ($haunters_count < 50)
			{
				$this->haunters[] = (int)$user_id;
				++$haunters_count;
			}
		}
		
        $query = new DbQuery('SELECT u.user_id, r.nickname, e.club_id, count(*), MAX(e.start_time) FROM club_users u JOIN event_users r ON r.user_id = u.user_id JOIN events e ON e.id = r.event_id WHERE u.club_id = ? GROUP BY r.user_id, r.nickname, e.club_id', $id);
		while ($row = $query->next())
		{
			list ($user_id, $nick, $club_id, $count, $time) = $row;
			if ($club_id == $id)
			{
				$time += $count * 60 * 60 * 4;
			}
			else
			{
				$time += $count * 60 * 60;
			}
			
			if (isset($this->players[$user_id]))
			{
				if (!isset($this->players[$user_id]->nicks[$nick]) || $this->players[$user_id]->nicks[$nick] < $time)
				{
					$this->players[$user_id]->nicks[$nick] = $time;
				}
			}
		}
		
		foreach ($this->players as $user_id => $player)
		{
			arsort($player->nicks);
			$nicks = array();
			$nick_count = 0;
			foreach ($player->nicks as $nick => $count)
			{
				if ($nick_count++ == 8)
				{
					break;
				}
				$nicks[] = $nick;
			}
			$player->nicks = $nicks;
		}
		
		$this->tournaments = array();
		$this->events = array();
		if (isset($_profile->clubs[$this->id]) && ($_profile->clubs[$this->id]->flags & USER_PERM_REFEREE))
		{
			$query = new DbQuery('SELECT t.id, t.name FROM tournaments t WHERE t.start_time + t.duration >= UNIX_TIMESTAMP() AND t.start_time <= UNIX_TIMESTAMP() AND (t.flags & ' . (TOURNAMENT_FLAG_CANCELED | TOURNAMENT_FLAG_SINGLE_GAME) . ') = ' . TOURNAMENT_FLAG_SINGLE_GAME . ' AND t.club_id = ?', $id);
			while ($row = $query->next())
			{
				$t = new stdClass();
				list ($t->id, $t->name) = $row;
				$t->id = (int)$t->id;
				$this->tournaments[] = $t;
			}
			
			$events_str = '(0';
			$query = new DbQuery('SELECT e.id, e.rules, e.name, e.start_time, e.languages, e.duration, e.flags, e.security_token, t.id, t.name, t.security_token FROM events e LEFT OUTER JOIN tournaments t ON t.id = e.tournament_id WHERE (e.start_time + e.duration + ' . EVENT_ALIVE_TIME . ' > UNIX_TIMESTAMP() AND e.start_time < UNIX_TIMESTAMP() + ' . EVENTS_FUTURE_LIMIT . ' AND (e.flags & ' . EVENT_FLAG_CANCELED . ') = 0 AND e.club_id = ?) OR e.id = ?', $id, $gs->event_id);
			while ($row = $query->next())
			{
				$e = new stdClass();
				list ($e->id, $e->rules_code, $e->name, $e->start_time, $e->langs, $e->duration, $e->flags, $e->token, $e->tournament_id, $tournament_name, $tournament_token) = $row;
				$e->id = (int)$e->id;
				$e->start_time = (int)$e->start_time;
				$e->langs = (int)$e->langs;
				$e->duration = (int)$e->duration;
				$e->flags = (int)$e->flags;
				$e->reg = array();
				if (!is_null($e->tournament_id))
				{
					$e->tournament_id = (int)$e->tournament_id;
					$e->tournament_name = $tournament_name;
					if (!is_null($tournament_token))
					{
						$e->token = $tournament_token;
					}
					else
					{
						$e->token = rand_string(32);
						Db::exec(get_label('tournament'), 'UPDATE tournaments SET security_token = ? WHERE id = ?', $e->token, $e->tournament_id);
					}
				}
				else
				{
					$e->tournament_id = 0;
					if (is_null($e->token))
					{
						$e->token = rand_string(32);
						Db::exec(get_label('event'), 'UPDATE events SET security_token = ? WHERE id = ?', $e->token, $e->id);
					}
				}
				$this->events[$e->id] = $e;
				$events_str .= ', ' . $e->id;
			}
			$events_str .= ')';
			
			$query = new DbQuery(
				'SELECT r.user_id, r.event_id, r.nickname, nu.name, c.name, u.flags'.
				' FROM event_users r'.
				' JOIN users u ON u.id = r.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id'.
				' WHERE (r.coming_odds > 0 OR r.coming_odds IS NULL) AND r.event_id IN ' . $events_str);
			while ($row = $query->next())
			{
				list ($user_id, $event_id, $nick, $user_name, $club_name, $user_flags) = $row;
				if (is_null($nick))
				{
					$nick = $user_name;
				}
				if (isset($this->events[$event_id]))
				{
					if (!isset($this->players[$user_id]))
					{
						$this->players[$user_id] = new GPlayer($user_id, $user_name, $user_club, $user_flags, USER_PERM_PLAYER);
						if ($haunters_count < 50)
						{
							$this->haunters[] = (int)$user_id;
							++$haunters_count;
						}
					}
					$this->events[$event_id]->reg[$user_id] = $nick;
				}
			}
			
			$query = new DbQuery('SELECT i.id, i.event_id, i.name, i.flags FROM event_incomers i WHERE i.event_id IN ' . $events_str);
			while ($row = $query->next())
			{
				list ($incomer_id, $event_id, $incomer_name, $incomer_flags) = $row;
				$incomer_id = -$incomer_id;
				if (isset($this->events[$event_id]))
				{
					$this->players[$incomer_id] = new GPlayer($incomer_id, $incomer_name, $this->name, NEW_USER_FLAGS, $incomer_flags | USER_PERM_PLAYER);
					$this->events[$event_id]->reg[$incomer_id] = $incomer_name;
					if ($haunters_count < 50)
					{
						$this->haunters[] = (int)$incomer_id;
						++$haunters_count;
					}
				}
			}
		}
		
		$query = new DbQuery('SELECT name, rules FROM club_rules WHERE club_id = ?', $id);
		while ($row = $query->next())
		{
			$rules = new stdClass();
			list($rules->name, $rules->code) = $row;
			$this->rules[] = $rules;
		}
		
		$query = new DbQuery('SELECT l.name, c.rules FROM league_clubs c JOIN leagues l ON l.id = c.league_id WHERE c.club_id = ?', $id);
		while ($row = $query->next())
		{
			$rules = new stdClass();
			list($rules->name, $rules->code) = $row;
			if ($rules->code != $club->rules_code)
			{
				$this->rules[] = $rules->code;
			}
		}
		
		$this->addrs = array();
		$query = new DbQuery('SELECT a.id, a.name FROM addresses a WHERE a.club_id = ? AND (a.flags & ' . ADDRESS_FLAG_NOT_USED . ') = 0 ORDER BY (SELECT count(*) FROM events WHERE address_id = a.id) DESC', $id);
		while ($row = $query->next())
		{
			$a = new stdClass();
			$a->id = (int)$row[0];
			$a->name = $row[1];
			$this->addrs[] = $a;
		}
	}
}

class GUser
{
	public $id;
	public $name;
	public $flags;
	public $manager;
	public $settings;
	public $clubs;
	
	function __construct($club_id)
	{
		global $_profile;
		
		$this->id = (int)$_profile->user_id;
		$this->name = $_profile->user_name;
		$this->flags = (int)$_profile->user_flags;
		$this->manager = ($_profile->clubs[$club_id]->flags & USER_PERM_MANAGER) ? 1 : 0;
		
		$query = new DbQuery('SELECT flags, prompt_sound_id, end_sound_id FROM game_settings WHERE user_id = ?', $this->id);
		$this->settings = new stdClass();
		if ($row = $query->next())
		{
			$this->settings->flags = (int)$row[0];
			$this->settings->prompt_sound = $row[1];
			$this->settings->end_sound = $row[2];
		}
		else
		{
			$this->settings->flags = 0;
			$this->settings->prompt_sound = NULL;
			$this->settings->end_sound = NULL;
		}
		
		if (is_null($this->settings->prompt_sound))
		{
			if (is_null($this->settings->end_sound))
			{
				list($this->settings->prompt_sound, $this->settings->end_sound) = Db::record(get_label('club'), 'SELECT prompt_sound_id, end_sound_id FROM clubs WHERE id = ?', $club_id);
			}
			else
			{
				list($this->settings->prompt_sound) = Db::record(get_label('club'), 'SELECT prompt_sound_id FROM clubs WHERE id = ?', $club_id);
			}
		}
		else if (is_null($this->settings->end_sound))
		{
			list($this->settings->end_sound) = Db::record(get_label('club'), 'SELECT end_sound_id FROM clubs WHERE id = ?', $club_id);
		}
		
		if (is_null($this->settings->prompt_sound))
		{
			$this->settings->prompt_sound = 2;
		}
		else
		{
			$this->settings->prompt_sound = (int)$this->settings->prompt_sound;
		}
		
		if (is_null($this->settings->end_sound))
		{
			$this->settings->end_sound = 3;
		}
		else
		{
			$this->settings->end_sound = (int)$this->settings->end_sound;
		}
		
		$this->sounds = array();
		$query = new DbQuery('SELECT id, name FROM sounds WHERE (club_id IS NULL AND user_id IS NULL) OR club_id = ? OR user_id = ?', $club_id, $this->id);
		while ($row = $query->next())
		{
			$sound = new stdClass();
			$sound->id = (int)$row[0];
			$sound->name = $row[1];
			$this->sounds[] = $sound;
		}
		
		$this->clubs = array();
		foreach ($_profile->clubs as $club)
		{
			if (($club->club_flags & CLUB_FLAG_RETIRED) == 0)
			{
				$c = new stdClass();
				$c->id = $club->id;
				$c->name = $club->name;
				$this->clubs[] = $c;
			}
		}
	}
}

class CommandQueue
{
	public $events_map;
	public $users_map;
	public $club_id;
	
	public function __construct($club_id)
	{
		$this->club_id = $club_id;
		$this->events_map = array();
		$this->users_map = array();
	}
	
	private function correct_game($gs)
	{
		if (isset($this->events_map[$gs->event_id]))
		{
			$gs->event_id = $this->events_map[$gs->event_id];
		}
		if (isset($this->users_map[$gs->moder_id]))
		{
			$gs->moder_id = $this->users_map[$gs->moder_id];
		}
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $gs->players[$i];
			if (isset($this->users_map[$player->id]))
			{
				$player->id = $this->users_map[$player->id];
			}
		}
	}
	
	public function exec($queue, $gs)
	{
		try
		{
			Db::begin();
			foreach ($queue as $rec)
			{
				if (!isset($rec->action))
				{
					throw new Exc(get_label('Invalid request'));
				}
				
				if ($rec->action == 'new-event')
				{
					$this->new_event($rec);
				}
				else if ($rec->action == 'reg')
				{
					$this->register($rec);
				}
				else if ($rec->action == 'reg-incomer')
				{
					$this->reg_incomer($rec);
				}
				else if ($rec->action == 'new-user')
				{
					$this->new_user($rec);
				}
				else if ($rec->action == 'submit-game')
				{
					$this->submit_game($rec);
				}
				else if ($rec->action == 'extend-event')
				{
					$this->extend_event($rec);
				}
				else if ($rec->action == 'settings')
				{
					$this->settings($rec);
				}
				else
				{
					throw new Exc(get_label('Unknown action'));
				}
			}
			Db::commit();
		}
		catch (Exception $e)
		{
			Db::rollback();
			Exc::log($e, true);
			return get_label('Failed to submit data to the server: [0].<p>[1] administration will contact you ASAP to resolve this issue.</p><p>Sorry for the inconvenience.</p>', $e->getMessage(), PRODUCT_NAME);
		}
		
		$this->correct_game($gs);
		return NULL;
	}
	
	private function new_event($rec)
	{
		global $_profile;

		if (
			!isset($rec->name) || !isset($rec->duration) || !isset($rec->start) ||
			!isset($rec->flags) || !isset($rec->langs) || !isset($rec->id) || !isset($rec->rules_code))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$club = $_profile->clubs[$this->club_id];
		if (($club->flags & USER_PERM_MANAGER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		$query = new DbQuery('SELECT id FROM events WHERE club_id = ? AND name = ? AND duration = ? AND ABS(start_time - ?) < 60', $this->club_id, $rec->name, $rec->duration, $rec->start);
		if ($row = $query->next())
		{
			$this->events_map[$rec->id] = $row[0];
		}
		else
		{
			$event = new Event();
			$event->set_club($club);

			$event->name = $rec->name;
			$event->duration = $rec->duration;
			$event->timestamp = $rec->start;
			$event->rules_code = $rec->rules_code;
			if (isset($rec->addr_id))
			{
				$event->addr_id = $rec->addr_id;
			}
			else
			{
				if (!isset($rec->addr) || !isset($rec->city) || !isset($rec->country))
				{
					throw new Exc(get_label('Invalid request'));
				}
				$event->addr_id = -1;
				$event->addr = $rec->addr;
				$event->city = $rec->city;
				$event->country = $rec->country;
			}
			$event->notes = '';
			$event->flags = $rec->flags;
			$event->langs = $rec->langs;
			
			$this->events_map[$rec->id] = $event->create();
		}
	}
	
	private function register($rec)
	{
		if (!isset($rec->id) || !isset($rec->event) || !isset($rec->nick))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$event_id = $rec->event;
		if ($event_id == 0)
		{
			return true;
		}
		
		if (isset($this->events_map[$event_id]))
		{
			$event_id = $this->events_map[$event_id];
		}
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE user_id = ? AND event_id = ?', $rec->id, $event_id);
		if ($count == 0)
		{
			Db::exec(
				get_label('registration'), 
				'INSERT INTO event_users (event_id, user_id, nickname) VALUES (?, ?, ?)',
				$event_id, $rec->id, $rec->nick);
			return true;
		}
		return false;
	}
	
	private function reg_incomer($rec)
	{
		if (!isset($rec->id) || !isset($rec->event) || !isset($rec->nick) || !isset($rec->flags))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$nick = $rec->nick;
		$user_id = $rec->id;
		$event_id = $rec->event;
		if ($event_id == 0)
		{
			return;
		}
		
		if (isset($this->events_map[$event_id]))
		{
			$event_id = $this->events_map[$event_id];
		}
		
		if ($user_id <= 0)
		{
			if (!isset($rec->name))
			{
				throw new Exc(get_label('Invalid request'));
			}
			
			$query = new DbQuery('SELECT id FROM users WHERE name = ?', $rec->name);
			if ($row = $query->next())
			{
				list($u_id) = $row;
				$this->users_map[$user_id] = $u_id;
				$user_id = $u_id;
			}
			else
			{
				$incomer_id = -$user_id;
				$query = new DbQuery('SELECT id FROM event_incomers WHERE event_id = ? AND name = ?', $event_id, $rec->name);
				if ($row = $query->next())
				{
					list ($iid) = $row;
					$this->users_map[$user_id] = -$iid;
				}
				else
				{
					Db::exec(get_label('user'), 'INSERT INTO event_incomers (event_id, name, flags) VALUES (?, ?, ?)', $event_id, $rec->name, $rec->flags | INCOMER_FLAGS_EXISTING);
					list ($iid) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
					$this->users_map[$user_id] = -$iid;
					$incomer_id = $iid;
				}
				return;
			}
		}
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM event_users WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if ($count == 0)
		{
			Db::exec(
				get_label('registration'), 
				'INSERT INTO event_users (user_id, nickname, event_id) values (?, ?, ?)',
				$user_id, $rec->nick, $event_id);
		}
	}
	
	private function new_user($rec)
	{
		if (!isset($rec->name) || !isset($rec->event) || !isset($rec->flags) || !isset($rec->id))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$event_id = $rec->event;
		if ($event_id == 0)
		{
			return;
		}
		
		if (isset($this->events_map[$event_id]))
		{
			$event_id = $this->events_map[$event_id];
		}
		
		$nick = $rec->name;
		if (isset($rec->nick))
		{
			$nick = trim($rec->nick);
		}
		
		$email = '';
		if (isset($rec->email))
		{
			$email = trim($rec->email);
		}
		
		$flags = NEW_USER_FLAGS;
		if ($rec->flags & INCOMER_FLAGS_MALE)
		{
			$flags |= USER_FLAG_MALE;
		}
		
		$message = NULL;
		$name = $rec->name;
		if (!is_valid_name($name))
		{
			$name = correct_name($name);
			$flags |= USER_FLAG_NAME_CHANGED;
			$message = get_label('User name [0] has been changed to [1] - illegal characters.', $rec->name, $name);
		}
		
		$email = $rec->email;
		if (!is_email($email))
		{
			$email = '';
		}
		
		check_name($name, get_label('user name'));
		if ($email != '')
		{
			list($city_id) = Db::record(get_label('club'), 'SELECT city_id FROM clubs WHERE id = ?', $this->club_id);
			$i = 1;
			$n = $name;
			while (true)
			{
				try
				{
					$names = new Names(-1, get_label('user name'), 'users', 0, new SQL(' AND o.city_id = ?', $city_id), $n);
					$user_id = create_user($names, $email, $this->club_id, $city_id);
					break;
				}
				catch (Exception $e)
				{
					$flags |= USER_FLAG_NAME_CHANGED;
					$message = get_label('User name [0] has been changed to [1] - name already exists.', $rec->name, $n);
					$n = $name . '_' . $i;
					if (++$i == 51)
					{
						throw $e;
					}
				}
			}
		
			Db::exec(
				get_label('registration'), 
				'INSERT INTO event_users (user_id, nickname, event_id) VALUES (?, ?, ?)',
				$user_id, $rec->nick, $event_id);
			$this->users_map[$rec->id] = $user_id;
		}
		else
		{
			Db::exec(get_label('user'), 'INSERT INTO event_incomers (event_id, name, flags) VALUES (?, ?, ?)', $event_id, $rec->name, $rec->flags & ~INCOMER_FLAGS_EXISTING);
			list ($incomer_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
			$this->users_map[$rec->id] = -$incomer_id;
		}
		
		if ($message != NULL)
		{
			echo $message;
		}
	}
	
	function submit_game($rec)
	{
		if (!isset($rec->game))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		if ($rec->game->event_id > 0)
		{
			$game = new Game($rec->game);
			$game->update();
		}
		else
		{
			unset($_SESSION['demo_game']);
		}
	}
	
	function extend_event($rec)
	{
		if (!isset($rec->id) || !isset($rec->duration))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$id = $rec->id;
		if (isset($this->events_map[$id]))
		{
			$id = $this->events_map[$id];
		}
		Db::exec(get_label('event'), 'UPDATE events SET duration = ? WHERE id = ?', $rec->duration, $id);
	}
	
	function settings($rec)
	{
		global $_profile;
	
		if (!isset($rec->flags))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$query = new DbQuery('SELECT user_id FROM game_settings WHERE user_id = ?', $_profile->user_id);
		if ($query->next())
		{
			Db::exec(get_label('user'),
				'UPDATE game_settings SET flags = ?, prompt_sound_id = ?, end_sound_id = ? WHERE user_id = ?',
				$rec->flags, $rec->prompt_sound, $rec->end_sound, $_profile->user_id);
		}
		else
		{
			Db::exec(get_label('user'),
				'INSERT INTO game_settings (user_id, flags, prompt_sound_id, end_sound_id) VALUES (?, ?, ?, ?, ?, ?)',
				$_profile->user_id, $rec->flags, $rec->prompt_sound, $rec->end_sound);
		}
	}
}

define('CURRENT_VERSION', 3); // must match _version in js/src/game.js

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// sync
	//-------------------------------------------------------------------------------------------------------
	function sync_op()
	{
		global $_profile, $_lang;
		
		// to make sure $_profile is not NULL
		check_permissions(PERMISSION_USER);
		
		if (isset($_REQUEST['club_id']))
		{
			$club_id = $_REQUEST['club_id'];
		}
		else
		{
			$club_id = def_club();
		}
		
		$output = true;
		$gs = new stdClass();
		$console = array();
		if (isset($_REQUEST['game']))
		{
			$json_str = $_REQUEST['game'];
			$gs = json_decode($json_str);
			
			$output = ($club_id != $gs->club_id);
			$data = NULL;
			if (isset($_REQUEST['data']))
			{
				// $myfile = fopen("testfile.txt", "w");
				// fwrite($myfile, stripcslashes($_REQUEST['data']));
				// fclose($myfile);
				
				$data = json_decode($_REQUEST['data']);
				if ($data == NULL)
				{
					if (is_permitted(PERMISSION_ADMIN))
					{
						throw new Exc(get_label('Invalid json format.') . '<p>' . $_REQUEST['data'] . '</p>');
					}
					throw new Exc(get_label('Invalid json format.'));
				}
				if (count($data) <= 0)
				{
					$data = NULL;
				}
			}
			
			if ($data != NULL)
			{
				$output = true;
				$queue = new CommandQueue($gs->club_id);
				$fail = $queue->exec($data, $gs);
				if ($fail != NULL)
				{
					$this->response['fail'] = $fail;
				}
				$json_str = json_encode($gs);
			}
			
			$gid = $gs->id;
			if ($gs->event_id > 0)
			{
				$tournament_id = NULL;
				if ($gs->tournament_id > 0)
				{
					list($tournament_name, $tournament_flags, $event_id) = Db::record(get_label('tournament'), 'SELECT t.name, t.flags, e.id FROM tournaments t LEFT OUTER JOIN events e ON e.id = ? AND e.tournament_id = t.id WHERE t.id = ?', $gs->event_id, $gs->tournament_id);
					if (($tournament_flags | TOURNAMENT_FLAG_SINGLE_GAME) == 0 && is_null($event_id))
					{
						throw new Exc(get_label('Game [0] can not be played in the tournament [1]', $gs->id, $tournament_name));
					}
					$tournament_id = $gs->tournament_id;
				}
				
				$result_code = 0; // GAME_RESULT_PLAYING
				switch ($gs->gamestate)
				{
					case 17: // GAME_MAFIA_WON
						$result_code = 2; // GAME_RESULT_MAFIA;
					case 18: // GAME_CIVIL_WON
						$result_code = 1; // GAME_RESULT_TOWN;
				}
				
				list($count) = Db::record(get_label('game'), 'SELECT count(*) FROM games WHERE id = ?', $gs->id);
				if ($count <= 0)
				{
					$query = new DbQuery('SELECT id FROM games WHERE user_id = ? AND result = 0 AND club_id = ? ORDER BY id LIMIT 1', $gs->user_id, $gs->club_id);
					if ($row = $query->next())
					{
						$gs->id = (int)$row[0];
						$json_str = json_encode($gs);
						$count = 1;
					}
				}
				
				$moder_id = NULL;
				if ($gs->moder_id > 0)
				{
					$moder_id = $gs->moder_id;
				}
				
				if ($count > 0)
				{
					Db::exec(get_label('game'),
						'UPDATE games SET log = ?, end_time = ?, club_id = ?, event_id = ?, tournament_id = ?, moderator_id = ?, ' .
							'user_id = ?, language = ?, start_time = ?, end_time = ?, result = ?, ' .
							'rules = ? WHERE id = ?',
						$json_str, $gs->end_time, $gs->club_id, $gs->event_id, $tournament_id, $moder_id,
						$gs->user_id, $gs->lang, $gs->start_time, $gs->end_time, $result_code,
						$gs->rules_code, $gs->id);
					Db::exec(get_label('tournament'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $gs->event_id);
					Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $tournament_id);
				}
				else
				{
					Db::exec(get_label('game'),
						'INSERT INTO games (club_id, event_id, tournament_id, moderator_id, user_id, language, log, start_time, end_time, result, rules) ' .
							'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
						$gs->club_id, $gs->event_id, $tournament_id, $moder_id, $gs->user_id, $gs->lang,
						$json_str, $gs->start_time, $gs->end_time, $result_code, $gs->rules_code);
					list ($gs->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
					$gs->id = (int)$gs->id;
					$json_str = json_encode($gs);
					Db::exec(get_label('game'), 'UPDATE games SET log = ? WHERE id = ?', $json_str, $gs->id);
				}
				
			}
			else if ($gs->club_id == $club_id)
			{
				$_SESSION['demo_game'] = $json_str;
			}
			else
			{
				unset($_SESSION['demo_game']);
			}
			
			if ($gs->id != $gid)
			{
				$output = true;
			}
		}
		
		try
		{
			check_permissions(PERMISSION_CLUB_REFEREE, $club_id);
		}
		catch (LoginExc $e)
		{
			$query = new DbQuery('SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $gs->user_id);
			if ($row = $query->next())
			{
				$e->user_name = $row[0];
			}
			throw $e;
		}
		
		if ($output)
		{
			if (!isset($_profile->clubs[$club_id]))
			{
				$club_id = def_club();
			}
			
			Db::exec(get_label('game'), 'DELETE games FROM games JOIN events ON events.id = games.event_id WHERE games.user_id = ? AND games.result = 0 AND games.start_time = 0 AND events.start_time + events.duration + ' . EVENT_ALIVE_TIME . ' <= UNIX_TIMESTAMP()', $_profile->user_id);
			$query = new DbQuery('SELECT id, log, is_canceled FROM games WHERE user_id = ? AND result = 0 AND club_id = ? ORDER BY id LIMIT 1', $_profile->user_id, $club_id);
			if ($row = $query->next())
			{
				list($game_id, $json_str, $is_canceled) = $row;
				// $console[] = 'game id = ' . $game_id;
				// $console[] = 'log = ' . $log;
				$gs = json_decode($json_str);
				$gs->is_canceled = (bool)$is_canceled;
			}
			else if (isset($_SESSION['demo_game']))
			{
				$gs = json_decode($_SESSION['demo_game']);
			}
			else
			{
				$gs->gamestate = 0; // GAME_NOT_STARTED
				$gs->id = 0;
				$gs->club_id = $club_id;
				$gs->user_id = $_profile->user_id;
				$gs->moder_id = 0;
				$gs->lang = 0;
				$gs->event_id = 0;
				$gs->tournament_id = 0;
				$gs->start_time = 0;
				$gs->end_time = 0;
				$gs->guess3 = NULL;
				$gs->error = NULL;
				$gs->is_canceled = false;
				$gs->flags = 0;
				$gs->rules_code = default_rules_code();
				$gs->players = array();
				for ($i = 0; $i < 10; ++$i)
				{
					$player = new stdClass();
					$player->number = $i;
					$player->id = -1;
					$player->nick = '';
					$player->is_male = 1;
					$player->has_immunity = false;
					$player->role = ROLE_CIVILIAN;
					$player->warnings = 0;
					$player->state = 0; // PLAYER_STATE_ALIVE
					$player->kill_round = -1;
					$player->kill_reason = -1;
					$player->arranged = -1;
					$player->don_check = -1;
					$player->sheriff_check = -1;
					$player->mute = -1;
					$player->extra_points = 0;
					$player->comment = '';
					$gs->players[] = $player;
				}
				
				if ($club_id > 0 )
				{
					$query = new DbQuery('SELECT id, flags, languages, tournament_id FROM events WHERE start_time <= UNIX_TIMESTAMP() AND start_time + duration > UNIX_TIMESTAMP() AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0 AND club_id = ?', $club_id);
					if ($row = $query->next())
					{
						$gs->event_id = (int)$row[0];
						$event_flags = (int)$row[1];
						$event_langs = (int)$row[2];
						$gs->tournament_id = is_null($row[3]) ? 0 : (int)$row[3];
						if (($event_flags & EVENT_FLAG_ALL_CAN_REFEREE) == 0)
						{
							$gs->moder_id = $_profile->user_id;
						}
						if (is_valid_lang($event_langs))
						{
							$gs->lang = $event_langs;
						}
					}
				}
			}
			
			//throw new Exc(json_encode($gs));
			$this->response['site'] = get_server_url();
			$this->response['user'] = new GUser($club_id);
			$this->response['club'] = new GClub($club_id, $gs);
			$this->response['game'] = $gs;
			$this->response['time'] = time();
		}
		
		if (count($console) > 0)
		{
			$this->response['console'] = $console;
		}
		//print_json($this->response);
	}
	
	function sync_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_REFEREE, 'Sychronize game client data with the server.');
		$help->request_param('club_id', 'Club id.', 'default club is used, which is the main club of the logged user. If logged user does not have main club, then a random club where he/she has permissions is used.');
		$help->request_param('game', 'Json string fully describing current game state. TODO!!! Explain it is a separate document.');
		$help->request_param('data', 'Command queue with some additional actions.  TODO!!! Provide more details.
				<dl>
					<dt>new-event</dt>
						<dd></dd>
					<dt>reg</dt>
						<dd></dd>
					<dt>reg-incomer</dt>
						<dd></dd>
					<dt>new-user</dt>
						<dd></dd>
					<dt>submit-game</dt>
						<dd></dd>
					<dt>extend-event</dt>
						<dd></dd>
					<dt>settings</dt>
						<dd></dd>
				<dl>');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// ulist TODO: move it to get-API
	//-------------------------------------------------------------------------------------------------------
	function ulist_op()
	{
		global $_lang;
		
		$club_id = 0;
		if (isset($_REQUEST['club_id']))
		{
			$club_id = (int)$_REQUEST['club_id'];
		}
		
		$num = 0;
		if (isset($_REQUEST['num']))
		{
			$num = (int)$_REQUEST['num'];
		}
		
		$name = '';
		if (isset($_REQUEST['name']))
		{
			$name = $_REQUEST['name'];
		}
		
		$area_id = -1;
		$city_id = -1;
		if ($club_id > 0)
		{
			list($city_id, $area_id) = Db::record(get_label('area'), 'SELECT ct.id, ct.area_id FROM clubs c JOIN cities ct ON ct.id = c.city_id WHERE c.id = ?', $club_id);
		}
		
		
		$games_count_query = new SQL('SELECT count(*) FROM players p JOIN games g ON g.id = p.game_id WHERE p.user_id = u.id AND g.start_time > UNIX_TIMESTAMP() - ' . (24*60*60*365));
		if ($club_id > 0)
		{
			$games_count_query->add(' AND g.club_id = ?', $club_id);
		}
		
		array();
		$query = new DbQuery('SELECT DISTINCT u.id, nu.name, u.flags, c.id, c.name, a.id, na.name, ct.id, nct.name, (', $games_count_query);
		$query->add(
				') as games_count' .
				' FROM users u' .
				' JOIN names nu ON nu.id = u.name_id'.
				' LEFT OUTER JOIN clubs c ON c.id = u.club_id' .
				' JOIN cities ct ON ct.id = u.city_id' .
				' JOIN names nct ON nct.id = ct.name_id AND (nct.langs & '.$_lang.') <> 0 ' .
				' LEFT OUTER JOIN cities a ON a.id = ct.area_id' .
				' LEFT OUTER JOIN names na ON na.id = a.name_id AND (na.langs & '.$_lang.') <> 0 ' .
				' WHERE TRUE');
		if (!empty($name))
		{
			$name_wildcard = '%' . $name . '%';
			$query->add(
					' AND (nu.name LIKE ? OR' .
					' u.email LIKE ? OR' .
					' u.id IN (SELECT DISTINCT user_id FROM event_users WHERE nickname LIKE ?))',
				$name_wildcard,
				$name_wildcard,
				$name_wildcard);
		}
		else if ($club_id > 0)
		{
			$query->add(' AND u.id IN (SELECT DISTINCT user_id FROM club_users WHERE club_id = ?)', $club_id);
		}
		$query->add(' ORDER BY games_count DESC');
		
		if ($num > 0)
		{
			$query->add(' LIMIT ' . $num);
		}
		
//		echo $query->get_parsed_sql();
		$list = array();
		while ($row = $query->next())
		{
			list ($p_id, $p_name, $p_flags, $p_club_id, $p_club_name, $p_area_id, $p_area_name, $p_city_id, $p_city_name) = $row;
			$p = new stdClass();
			$p->id = (int)$p_id;
			$p->name = $p_name;
			$p->flags = (int)$p_flags;
			$p->city = $p_city_name;
			if (is_null($p_area_id))
			{
				$p_area_id = $p_city_id;
				$p_area_name = $p_city_name;
			}
			$p->full_name = $p->name;
			if ($p_area_id != $area_id)
			{
				$p->full_name .= ', ' . $p_city_name;
			}
			$p->lower_case = mb_strtolower($p->name, 'UTF-8');
			$list[$p_id] = $p;
		}
		
		// sort it by name
		usort($list, 'compare_players');
		
		// check for duplicate names, add city to help viewers choose between them
		for ($i = 1; $i < count($list); ++$i)
		{
			$prev = $list[$i-1];
			$curr = $list[$i];
			if ($prev->lower_case == $curr->lower_case)
			{
				$prev->full_name = $prev->name . ', ' . $prev->city;
				$curr->full_name = $curr->name . ', ' . $curr->city;
			}
		}
		
		// remove unnecessary fields
		for ($i = 0; $i < count($list); ++$i)
		{
			$p = $list[$i];
			unset($p->city);
			unset($p->lower_case);
		}
		
		$this->response['list'] = $list;
		//print_json($this->response);
	}
	
	function ulist_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_CLUB_REFEREE, 'Get user list for the game client application. TODO!!! Move it to get-API.');
		$help->request_param('club_id', 'Club id. It is used to filter users when <q>name</q> is missing or empty. Not required.');
		$help->request_param('num', 'Number of users to return.', 'all matching users are returned.');
		$help->request_param('name', 'Name filter. Only the users with matching nicknames are returned.', 'all users are returned.');
		$help->response_param('list', 'User list. An array of users where every item contains:
				<dl>
					<dt>id</dt>
						<dd>User id.</dd>
					<dt>name</dt>
						<dd>User name.</dd>
					<dt>club</dt>
						<dd>User club name.</dd>
					<dt>flags</dt>
						<dd>TODO: replace it with something user friendly.</dd>
					<dt>nicks</dt>
						<dd>Array of nicknames that were used by the user.</dd>
				<dl>');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// delete
	//-------------------------------------------------------------------------------------------------------
	function delete_op()
	{
		$game_id = (int)get_required_param('game_id');
		
		Db::begin();
		list($club_id, $user_id, $event_id, $tournament_id, $end_time, $is_rating) = Db::record(get_label('game'), 'SELECT club_id, user_id, event_id, tournament_id, end_time, is_rating FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		$prev_game_id = NULL;
		$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $end_time, $end_time, $game_id);
		if ($row = $query->next())
		{
			list($prev_game_id) = $row;
		}
		
		if ($is_rating)
		{
			Game::rebuild_ratings($prev_game_id, $end_time);
		}
		
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET game_id = ? WHERE game_id = ?', $prev_game_id, $game_id);
		Db::exec(get_label('game'), 'UPDATE rebuild_ratings SET current_game_id = ? WHERE current_game_id = ?', $prev_game_id, $game_id);
		Db::exec(get_label('game'), 'UPDATE mwt_games SET game_id = NULL WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM dons WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM mafiosos WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM sheriffs WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM players WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM games WHERE id = ?', $game_id);
		
		db_log(LOG_OBJECT_GAME, 'deleted', NULL, $game_id, $club_id);
		Db::commit();
	}
	
	function delete_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Delete game.');
		$help->request_param('game_id', 'Game id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		$game_id = (int)get_required_param('game_id');
		$json = get_required_param('json');
		if ($json == NULL)
		{
			throw new Exc(get_label('Invalid json format.'));
		}
		$json = check_json($json);
		
		Db::begin();
		list($club_id, $user_id, $event_id, $tournament_id) = Db::record(get_label('game'), 'SELECT club_id, user_id, event_id, tournament_id FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $user_id, $club_id, $event_id, $tournament_id);
		
		$feature_flags = GAME_FEATURE_MASK_MAFIARATINGS;
		$game = new Game($json, $feature_flags);
		$this->response['rebuild_ratings'] = $game->update();
		Db::commit();
		
		if (isset($game->issues))
		{
			$text = get_label('The game contains the next issues:') . '<ul>';
			foreach ($game->issues as $issue)
			{
				$text .= '<li>' . $issue . '</li>';
			}
			$text .= '</ul>' . get_label('They are all fixed but the original version of the game is also saved. Please check Game Issues in the management menu.');
			$this->response['message'] = $text;
		}
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, 'Change the game.');
		$help->request_param('game_id', 'Game id.');
		$param = $help->request_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		$param = $help->response_param('json', 'Game description in json format.');
		Game::api_help($param, true);
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// extra_points
	//-------------------------------------------------------------------------------------------------------
	function extra_points_op()
	{
		global $_lang;
		
		$game_id = (int)get_required_param('game_id');
		
        $user_id = (int)get_required_param('user_id');
        $points = (float)get_required_param('points');
		$reason = get_optional_param('reason');
		if ($points != 0 && empty($reason))
		{
			throw new Exc(get_label('Please enter the reason.'));
		}
		$reason = str_replace(":", "&#58;", $reason);
		
        list($json, $feature_flags, $club_id, $game_user_id, $is_canceled) = Db::record(get_label('game'), 'SELECT json, feature_flags, club_id, user_id, is_canceled FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $game_user_id, $club_id);

		$game = new Game($json, $feature_flags);
        foreach ($game->data->players as $player)
        {
            if ($user_id == $player->id)
            {
				Db::begin();
				if (!isset($player->bonus) || is_numeric($player->bonus))
				{
					$player->bonus = $points;
				}
				else if (is_array($player->bonus))
				{
					for ($i = 0; $i < count($player->bonus); ++$i)
					{
						if (is_numeric($player->bonus[$i]))
						{
							$player->bonus[$i] = $points;
							break;
						}
					}
					if ($i >= count($player->bonus))
					{
						$player->bonus[] = $points;
					}
				}
				else
				{
					$player->bonus = array($player->bonus, $points);
				}
				$player->comment = $reason;
				$game->update();
                Db::commit();
				return;
            }
        }

        list($user_name) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
        throw new Exc(get_label('[0] did not play in the game [1]', $user_name, $game_id));
	}
	
	function extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, 'Add extra points for a player.');
		$help->request_param('game_id', 'Game id.');
		$help->request_param('user_id', 'User id. User must be a player in this game.');
		$help->request_param('points', 'Extra points. Floating point number from -0.4 to 0.7');
		$help->request_param('reason', 'Reason why the points are added/subtracted. Must be non empty if points are non zero.', 'points must be 0.');
		return $help;
    }
	
	//-------------------------------------------------------------------------------------------------------
	// change_ex
	//-------------------------------------------------------------------------------------------------------
	function change_ex_op()
	{
		$game_id = (int)get_required_param('game_id');
		list ($club_id, $old_table, $old_number, $old_objection_user_id, $old_objection, $game_user_id) =
			Db::record(get_label('game'), 'SELECT club_id, table_name, game_number, objection_user_id, objection, user_id FROM games WHERE id = ?', $game_id);
		check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $game_user_id, $club_id);
		
		$table = get_optional_param('table', $old_table);
		if (empty($table))
		{
			$table = NULL;
		}
		
		$number = get_optional_param('number', $old_number);
		if (empty($number))
		{
			$number = NULL;
		}
		
		$objection_user_id = (int)get_optional_param('objection_user_id', $old_objection_user_id);
		if ($objection_user_id <= 0)
		{
			$objection_user_id = NULL;
		}
		
		$objection = get_optional_param('objection', $old_objection);
		if (empty($objection))
		{
			$objection = NULL;
		}
		
		Db::begin();
		Db::exec(get_label('game'), 'UPDATE games SET table_name = ?, game_number = ?, objection_user_id = ?, objection = ? WHERE id = ?', 
			$table, $number, $objection_user_id, $objection, $game_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($table != $old_table)
			{
				$log_details->table = $table;
			}
			if ($number != $old_number)
			{
				$log_details->number = $number;
			}
			if ($objection_user_id != $old_objection_user_id)
			{
				$log_details->objection_user_id = $objection_user_id;
			}
			if ($objection != $old_objection)
			{
				$log_details->objection = $objection;
			}
			db_log(LOG_OBJECT_GAME, 'changed', $log_details, $game_id, $club_id);
		}
		Db::commit();
	}
	
	function change_ex_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Comment game.');
		$help->request_param('game_id', 'Game id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// mwt_create
	//-------------------------------------------------------------------------------------------------------
	function mwt_create_op()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		
		$json_str = get_required_param('json');
		$json = json_decode($json_str);
		if ($json == NULL)
		{
			throw new Exc(get_label('Invalid json format.'));
		}
		$game_id = NULL;
		$throw_error = get_optional_param('throw_error', false);
		
		Db::begin();
		try
		{
			$game = convert_mwt_game($json);
			
			if ($game->winner == 'maf')
			{
				$result_code = 2; // GAME_RESULT_MAFIA;
			}
			else
			{
				$result_code = 1; // GAME_RESULT_TOWN;
			}
			Db::exec(get_label('game'),
				'INSERT INTO games (club_id, event_id, tournament_id, moderator_id, user_id, language, start_time, end_time, result, rules) ' .
					'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				$game->clubId, $game->eventId, $game->tournamentId, $game->moderator->id, $_profile->user_id, LANG_RUSSIAN,
				$game->startTime, $game->endTime, $result_code, $game->rules);
			list ($game->id) = Db::record(get_label('game'), 'SELECT LAST_INSERT_ID()');
			$game->id = (int)$game->id;
			
			$game = new Game($game, GAME_FEATURE_MASK_MWT);
			$game->update();
			
			Db::exec(get_label('tournament'), 'UPDATE tournaments SET flags = (flags & ~' . TOURNAMENT_FLAG_FINISHED . ') WHERE id = ?', $game->data->tournamentId);
			Db::exec(get_label('round'), 'UPDATE events SET flags = (flags & ~' . EVENT_FLAG_FINISHED . ') WHERE id = ?', $game->data->eventId);
			Db::exec(get_label('game'), 'INSERT INTO mwt_games (user_id, time, json, game_id) VALUES (?, UNIX_TIMESTAMP(), ?, ?)', $_profile->user_id, $json_str, $game->data->id);
			Db::commit();
		}
		catch (Exception $e)
		{
			Db::rollback();
			Db::exec(get_label('game'), 'INSERT INTO mwt_games (user_id, time, json) VALUES (?, UNIX_TIMESTAMP(), ?)', $_profile->user_id, $json_str);
			Exc::log($e);
			if ($throw_error)
			{
				throw $e;
			}
		}
	}
	
	function mwt_create_op_help()
	{
		$help = new ApiHelp(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_REFEREE, 'Add the game from MWT site.');
		$help->request_param('json', 'Game description in json format specific for mwt site.');
		$help->request_param('throw_error', '0 for not throwing errors; 1 for throwing.', '0');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	function comment_op()
	{
		global $_profile, $_lang;
		
		check_permissions(PERMISSION_USER);
		$game_id = (int)get_required_param('id');
		$comment = prepare_message(get_required_param('comment'));
		$lang = detect_lang($comment);
		if ($lang == LANG_NO)
		{
			$lang = $_lang;
		}
		
		Db::exec(get_label('comment'), 'INSERT INTO game_comments (time, user_id, comment, game_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $game_id, $lang);
		list($event_id, $event_name, $event_start_time, $event_timezone, $event_addr) = 
			Db::record(get_label('game'), 
				'SELECT e.id, e.name, e.start_time, c.timezone, a.address FROM games g' .
				' JOIN events e ON g.event_id = e.id' .
				' JOIN addresses a ON a.id = e.address_id' . 
				' JOIN cities c ON c.id = a.city_id' . 
				' WHERE g.id = ?', $game_id);
		
		$query = new DbQuery(
			'(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM players p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE p.game_id = ?)' .
			' UNION DISTINCT' .
			' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			' FROM game_comments c'.
			' JOIN users u ON c.user_id = u.id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			' WHERE c.game_id = ?)',
			$game_id, $game_id);
		while ($row = $query->next())
		{
			list($user_id, $user_name, $user_email, $user_flags, $user_lang) = $row;
		
			if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_MESSAGE_NOTIFY) == 0 || empty($user_email))
			{
				continue;
			}
			
			$code = generate_email_code();
			$request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			$tags = array(
				'root' => new Tag(get_server_url()),
				'user_id' => new Tag($user_id),
				'gid' => new Tag($game_id),
				'event_id' => new Tag($event_id),
				'event_name' => new Tag($event_name),
				'event_date' => new Tag(format_date('l, F d, Y', $event_start_time, $event_timezone, $user_lang)),
				'event_time' => new Tag(format_date('H:i', $event_start_time, $event_timezone, $user_lang)),
				'addr' => new Tag($event_addr),
				'code' => new Tag($code),
				'user_name' => new Tag($user_name),
				'sender' => new Tag($_profile->user_name),
				'message' => new Tag($comment),
				'url' => new Tag($request_base),
				'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_game.php';
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_notification($user_email, $body, $text_body, $subj, $user_id, EMAIL_OBJ_GAME, $game_id, $code);
		}
	}
	
	function comment_op_help()
	{
		$help = new ApiHelp(PERMISSION_USER, 'Comment game.');
		$help->request_param('id', 'Game id.');
		$help->request_param('comment', 'Comment text.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_issue
	//-------------------------------------------------------------------------------------------------------
	function delete_issue_op()
	{
		$game_id = (int)get_required_param('game_id');
		$feature_flags = (int)get_optional_param('features', -1);
		check_permissions(PERMISSION_ADMIN);
	
		Db::begin();
		if ($feature_flags < 0)
		{
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $game_id);
		}
		else
		{
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ? AND feature_flags = ?', $game_id, $feature_flags);
		}
		Db::commit();
	}
	
	// function delete_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_ADMIN, 'Delete game.');
		// $help->request_param('game_id', 'Game id.');
		// return $help;
	// }
}

$page = new ApiPage();
$page->run('Game Operations', CURRENT_VERSION);

?>