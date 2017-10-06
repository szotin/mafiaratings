<?php

require_once 'include/session.php';
require_once 'include/game_stats.php';
require_once 'include/event.php';
require_once 'include/email.php';

define('EVENTS_FUTURE_LIMIT', 1209600); // 2 weeks

define('GAME_SETTINGS_SIMPLIFIED_CLIENT', 0x1);
define('GAME_SETTINGS_START_TIMER', 0x2);
define('GAME_SETTINGS_NO_SOUND', 0x4);
define('GAME_SETTINGS_NO_BLINKING', 0x8);

ob_start();
$result = array();

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
			if ($club->flags & (UC_PERM_MODER | UC_PERM_MANAGER) == (UC_PERM_MODER | UC_PERM_MANAGER))
			{
				$club_id = $club->id;
				break;
			}
			else if ($club->flags & UC_PERM_MODER)
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

class GPlayer
{
	public $id;
	public $name;
	public $flags;
	public $nicks;

	function __construct($id, $name, $u_flags, $uc_flags)
	{
		$this->id = (int)$id;
		$this->name = $name; 
		$this->nicks = array();
		$this->flags = (int)(($uc_flags & (UC_PERM_PLAYER | UC_PERM_MODER)) + ($u_flags & (U_FLAG_MALE | U_FLAG_IMMUNITY)));
	}
}

class GAddr
{
	public $id;
	public $name;
	
	function __construct($row)
	{
		$this->id = (int)$row[0];
		$this->name = $row[1];
	}
}

class GEmptyReg
{
}

class GEvent
{
	public $id;
	public $rules_id;
	public $name;
	public $start_time;
	public $langs;
	public $duration;
	public $flags;
	public $reg;

	function __construct($row)
	{
		list ($this->id, $this->rules_id, $this->name, $this->start_time, $this->langs, $this->duration, $this->flags, $addr_id) = $row;
		$this->id = (int)$this->id;
		$this->rules_id = (int)$this->rules_id;
		$this->start_time = (int)$this->start_time;
		$this->langs = (int)$this->langs;
		$this->duration = (int)$this->duration;
		$this->flags = (int)$this->flags;
		$this->reg = new GEmptyReg();
	}
}

class GRules
{
	public $id;
	public $name;
	public $flags;
	public $st_free;
	public $spt_free;
	public $st_reg;
	public $spt_reg;
	public $st_killed;
	public $spt_killed;
	public $st_def;
	public $spt_def;
	
	function __construct($row)
	{
		$this->name = $row[0];
		$this->id = (int)$row[1];
		$this->flags = (int)$row[2];
		$this->st_free = (int)$row[3];
		$this->spt_free = (int)$row[4];
		$this->st_reg = (int)$row[5];
		$this->spt_reg = (int)$row[6];
		$this->st_killed = (int)$row[7];
		$this->spt_killed = (int)$row[8];
		$this->st_def = (int)$row[9];
		$this->spt_def = (int)$row[10];
	}
}

class GClubMin
{
	public $id;
	public $name;
	
	function __construct($id, $name)
	{
		$this->id = $id;
		$this->name = $name;
	}
};

class GClub
{
	public $id;
	public $name;
	public $city;
	public $country;
	public $langs;
	public $rules_id;
	public $players;
	public $haunters;
	public $events;
	public $rules;
	public $addrs;
	public $price;
	public $icon;
	
	function __construct($id, $game)
	{
		global $_profile;
		$club = $_profile->clubs[$id];
		$this->id = (int)$club->id;
		$this->name = $club->name;
		$this->rules_id = (int)$club->rules_id;
		$this->city = $club->city;
		$this->country = $club->country;
		$this->price = $club->price;
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
			'SELECT u.id, u.name, u.flags, c.flags FROM user_clubs c' .
				' JOIN users u ON u.id = c.user_id' .
				' WHERE (c.flags & ' . UC_FLAG_BANNED .
					') = 0 AND (c.flags & ' . (UC_PERM_PLAYER | UC_PERM_MODER) .
					') <> 0 AND (u.flags & ' . U_FLAG_BANNED .
					') = 0 AND c.club_id = ?' .
				' ORDER BY u.rating DESC',
			$id);
		while ($row = $query->next())
		{
			list ($user_id, $user_name, $u_flags, $uc_flags) = $row;
			$this->players[$user_id] = new GPlayer($user_id, $user_name, $u_flags, $uc_flags);
			if ($haunters_count < 50)
			{
				$this->haunters[] = (int)$user_id;
				++$haunters_count;
			}
		}
		
		$query = new DbQuery('SELECT u.user_id, r.nick_name, count(*) FROM user_clubs u JOIN registrations r ON r.user_id = u.user_id WHERE u.club_id = ? GROUP BY user_id, nick_name', $id);
		while ($row = $query->next())
		{
			list ($user_id, $nick, $count) = $row;
			if (isset($this->players[$user_id]))
			{
				$this->players[$user_id]->nicks[$nick] = $count;
			}
		}

		$this->events = array();
		if (isset($_profile->clubs[$this->id]) && ($_profile->clubs[$this->id]->flags & UC_PERM_MODER))
		{
			$events_str = '(0';
			$query = new DbQuery('SELECT id, rules_id, name, start_time, languages, duration, flags, address_id FROM events WHERE (start_time + duration + ' . EVENT_ALIVE_TIME . ' > UNIX_TIMESTAMP() AND start_time < UNIX_TIMESTAMP() + ' . EVENTS_FUTURE_LIMIT . ' AND (flags & ' . EVENT_FLAG_CANCELED . ') = 0 AND club_id = ?) OR id = ?', $id, $game->event_id);
			while ($row = $query->next())
			{
				$eid = $row[0];
				$this->events[$eid] = new GEvent($row);
				$events_str .= ', ' . $eid;
			}
			$events_str .= ')';
			
			$query = new DbQuery('SELECT r.user_id, r.event_id, r.nick_name, u.name, u.flags FROM registrations r JOIN users u ON u.id = r.user_id  WHERE r.event_id IN ' . $events_str);
			while ($row = $query->next())
			{
				list ($user_id, $event_id, $nick, $user_name, $user_flags) = $row;
				if (isset($this->events[$event_id]))
				{
					if (!isset($this->players[$user_id]))
					{
						$this->players[$user_id] = new GPlayer($user_id, $user_name, $user_flags, UC_PERM_PLAYER);
						if ($haunters_count < 50)
						{
							$this->haunters[] = (int)$user_id;
							++$haunters_count;
						}
					}
					if (!is_array($this->events[$event_id]->reg))
					{
						$this->events[$event_id]->reg = array($user_id => $nick);
					}
					else
					{
						$this->events[$event_id]->reg[$user_id] = $nick;
					}
				}
			}
			
			$query = new DbQuery('SELECT r.incomer_id, r.event_id, r.nick_name, i.name, i.flags FROM registrations r JOIN incomers i ON i.id = r.incomer_id WHERE r.event_id IN ' . $events_str);
			while ($row = $query->next())
			{
				list ($incomer_id, $event_id, $nick, $incomer_name, $incomer_flags) = $row;
				$incomer_id = -$incomer_id;
				if (isset($this->events[$event_id]))
				{
					$this->players[$incomer_id] = new GPlayer($incomer_id, $incomer_name, U_NEW_PLAYER_FLAGS, $incomer_flags | UC_PERM_PLAYER);
					if (!is_array($this->events[$event_id]->reg))
					{
						$this->events[$event_id]->reg = array($incomer_id => $nick);
					}
					else
					{
						$this->events[$event_id]->reg[$incomer_id] = $nick;
					}
					if ($haunters_count < 50)
					{
						$this->haunters[] = (int)$incomer_id;
						++$haunters_count;
					}
				}
			}
		}
		
		$this->rules = array();
		$query = new DbQuery('SELECT c.name, r.id, r.flags, r.st_free, r.spt_free, r.st_reg, r.spt_reg, r.st_killed, r.spt_killed, r.st_def, r.spt_def FROM rules r JOIN club_rules c ON r.id = c.rules_id WHERE c.club_id = ?', $id);
		while ($row = $query->next())
		{
			$this->rules[$row[1]] = new GRules($row);
		}
		
		$this->addrs = array();
		$query = new DbQuery('SELECT a.id, a.name FROM addresses a WHERE a.club_id = ? AND (a.flags & ' . ADDR_FLAG_NOT_USED . ') = 0 ORDER BY (SELECT count(*) FROM events WHERE address_id = a.id) DESC', $id);
		while ($row = $query->next())
		{
			$this->addrs[] = new GAddr($row);
		}
		
		$r = new GRules(Db::record(
			get_label('rules'),
			'SELECT \'\' AS name, r.id, r.flags, r.st_free, r.spt_free, r.st_reg, r.spt_reg, r.st_killed, r.spt_killed, r.st_def, r.spt_def FROM rules r JOIN clubs c ON r.id = c.rules_id WHERE c.id = ?', 
			$id));
		$this->rules[$r->id] = $r;
	}
}

class GUserSettings
{
	public $flags;
	public $l_autosave;
	public $g_autosave;
	
	function __construct($row)
	{
		if ($row)
		{
			$this->flags = (int)$row[0];
			$this->l_autosave = (int)$row[1];
			$this->g_autosave = (int)$row[2];
		}
		else
		{
			$this->flags = 0;
			$this->l_autosave = 10;
			$this->g_autosave = 60;
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
		$this->manager = ($_profile->clubs[$club_id]->flags & UC_PERM_MANAGER) ? 1 : 0;
		
		$query = new DbQuery('SELECT flags, l_autosave, g_autosave FROM game_settings WHERE user_id = ?', $this->id);
		$this->settings = new GUserSettings($query->next());
		
		$this->clubs = array();
		foreach ($_profile->clubs as $club)
		{
			$this->clubs[] = new GClubMin($club->id, $club->name);
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
	
	private function correct_game($game)
	{
		if (isset($this->events_map[$game->event_id]))
		{
			$game->event_id = $this->events_map[$game->event_id];
		}
		if (isset($this->users_map[$game->moder_id]))
		{
			$game->moder_id = $this->users_map[$game->moder_id];
		}
		for ($i = 0; $i < 10; ++$i)
		{
			$player = $game->players[$i];
			if (isset($this->users_map[$player->id]))
			{
				$player->id = $this->users_map[$player->id];
			}
		}
	}
	
	public function exec($queue, $game)
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
		
		$this->correct_game($game);
		return NULL;
	}
	
	private function new_event($rec)
	{
		global $_profile;

		if (
			!isset($rec->name) || !isset($rec->duration) || !isset($rec->start) || !isset($rec->price) ||
			!isset($rec->rules) || !isset($rec->flags) || !isset($rec->langs) || !isset($rec->id))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$club = $_profile->clubs[$this->club_id];
		if (($club->flags & UC_PERM_MANAGER) == 0)
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
			$event->price = $rec->price;
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
			$event->rules_id = $rec->rules;
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
		if (isset($this->events_map[$event_id]))
		{
			$event_id = $this->events_map[$event_id];
		}
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM registrations WHERE user_id = ? AND event_id = ?', $rec->id, $event_id);
		if ($count == 0)
		{
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, user_id, nick_name, event_id) values (?, ?, ?, ?)',
				$this->club_id, $rec->id, $rec->nick, $event_id);
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
				list($uid) = $row;
				$this->users_map[$user_id] = $uid;
				$user_id = $uid;
			}
			else
			{
				$incomer_id = -$user_id;
				$query = new DbQuery('SELECT id FROM incomers WHERE event_id = ? AND name = ?', $event_id, $rec->name);
				if ($row = $query->next())
				{
					list ($iid) = $row;
					$this->users_map[$user_id] = -$iid;
				}
				else
				{
					Db::exec(get_label('user'), 'INSERT INTO incomers (event_id, name, flags) VALUES (?, ?, ?)', $event_id, $rec->name, $rec->flags | INCOMER_FLAGS_EXISTING);
					list ($iid) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
					$this->users_map[$user_id] = -$iid;
					$incomer_id = $iid;
					
					Db::exec(
						get_label('registration'), 
						'INSERT INTO registrations (club_id, incomer_id, nick_name, event_id) values (?, ?, ?, ?)',
						$this->club_id, $incomer_id, $nick, $event_id);
					list ($reg_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
				
					$sql = new SQL(
						'INSERT INTO incomer_suspects (user_id, reg_id, incomer_id)' .
							' SELECT DISTINCT u.id, ?, ? FROM registrations r' .
							' JOIN users u ON r.user_id = u.id' . 
							' WHERE r.nick_name = ?',
						$reg_id, $incomer_id, $rec->name);
					if ($rec->name != $rec->nick)
					{
						$sql->add(' OR r.nick_name = ?', $rec->nick);
					}
					Db::exec(get_label('player'), $sql);
				}
				return;
			}
		}
		
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM registrations WHERE user_id = ? AND event_id = ?', $user_id, $event_id);
		if ($count == 0)
		{
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, user_id, nick_name, event_id) values (?, ?, ?, ?)',
				$this->club_id, $user_id, $rec->nick, $event_id);
		}
	}
	
	private function new_user($rec)
	{
		if (!isset($rec->name) || !isset($rec->event) || !isset($rec->flags) || !isset($rec->id))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$event_id = $rec->event;
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
		
		$flags = U_NEW_PLAYER_FLAGS;
		if ($rec->flags & INCOMER_FLAGS_MALE)
		{
			$flags |= U_FLAG_MALE;
		}
		
		$message = NULL;
		$name = $rec->name;
		if (!is_valid_name($name))
		{
			$name = correct_name($name);
			$flags |= U_FLAG_NAME_CHANGED;
			$message = get_label('User name [0] has been changed to [1] - illegal characters.', $rec->name, $name);
		}
		
		$email = $rec->email;
		if (!is_email($email))
		{
			$email = '';
		}
		
		if ($email != '')
		{
			$i = 1;
			$n = $name;
			while (true)
			{
				list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM users WHERE name = ?', $n);
				if ($count == 0)
				{
					$name = $n;
					break;
				}
				$n = $name . $i;
				++$i;
				$flags |= U_FLAG_NAME_CHANGED;
				$message = get_label('User name [0] has been changed to [1] - name already exists.', $rec->name, $n);
			}
		
			$user_id = create_user($name, $email, $flags, $this->club_id);
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, user_id, nick_name, event_id) values (?, ?, ?, ?)',
				$this->club_id, $user_id, $rec->nick, $event_id);
			$this->users_map[$rec->id] = $user_id;
		}
		else
		{
			Db::exec(get_label('user'), 'INSERT INTO incomers (event_id, name, flags) VALUES (?, ?, ?)', $event_id, $rec->name, $rec->flags & ~INCOMER_FLAGS_EXISTING);
			list ($incomer_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, incomer_id, nick_name, event_id) values (?, ?, ?, ?)',
				$this->club_id, $incomer_id, $rec->nick, $event_id);
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
		$game = new GameState();
		$game->create_from_json($rec->game);
		$this->correct_game($game);
		$game->save();
		save_game_results($game);
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
	
		if (!isset($rec->l_autosave) || !isset($rec->g_autosave) || !isset($rec->flags))
		{
			throw new Exc(get_label('Invalid request'));
		}
		
		$query = new DbQuery('SELECT user_id FROM game_settings WHERE user_id = ?', $_profile->user_id);
		if ($query->next())
		{
			Db::exec(get_label('user'),
				'UPDATE game_settings SET l_autosave = ?, g_autosave = ?, flags = ? WHERE user_id = ?',
				$rec->l_autosave, $rec->g_autosave, $rec->flags, $_profile->user_id);
		}
		else
		{
			Db::exec(get_label('user'),
				'INSERT INTO game_settings (user_id, l_autosave, g_autosave, flags) VALUES (?, ?, ?, ?)',
				$_profile->user_id, $rec->l_autosave, $rec->g_autosave, $rec->flags);
		}
	}
}

function accept_data($data, $game)
{
	global $result;
	
	$output = false;
	if ($data != NULL)
	{
		$output = true;
		$queue = new CommandQueue($game->club_id);
		$fail = $queue->exec($data, $game);
		if ($fail != NULL)
		{
			$result['fail'] = $fail;
		}
	}
	
	$gid = $game->id;
	$game->save();
	if ($game->id != $gid)
	{
		$output = true;
	}
	return $output;
}

try
{
	initiate_session();
	check_maintenance();

	if ($_profile == NULL)
	{
		if (isset($_REQUEST['game']))
		{
			$game_str = str_replace('\"', '"', $_REQUEST['game']);
			$game = new GameState();
			$game->create_from_json(json_decode($game_str));
			
			$query = new DbQuery('SELECT name FROM users WHERE id = ?', $game->user_id);
			if ($row = $query->next())
			{
				$result['uname'] = $row[0];
			}
		}
		throw new Exc('login');
	}
	$result['uname'] = $_profile->user_name;
	
/*	echo '<pre>';
	print_r($_REQUEST);
	echo '</pre>';*/
//	sleep(rand(0 ,10));
	
	if (isset($_REQUEST['sync']))
	{
		if (isset($_REQUEST['club']))
		{
			$club_id = $_REQUEST['club'];
		}
		else
		{
			$club_id = def_club();
		}
	
		$output = true;
		$game = NULL;
		// $console = array();
		if (isset($_REQUEST['game']))
		{
			$game_str = str_replace('\"', '"', $_REQUEST['game']);
			$game = new GameState();
			$game->create_from_json(json_decode($game_str));
			$output = ($club_id != $game->club_id);
			$data = NULL;
			if (isset($_REQUEST['data']))
			{
				$data = json_decode(str_replace('\"', '"', $_REQUEST['data']));
				if (count($data) <= 0)
				{
					$data = NULL;
				}
			}
			
			if (accept_data($data, $game))
			{
				$output = true;
			}
		}
		
		if ($output)
		{
			if (!isset($_profile->clubs[$club_id]))
			{
				$club_id = def_club();
			}
		
			$query = new DbQuery('SELECT id, log FROM games WHERE user_id = ? AND result = 0 AND club_id = ?', $_profile->user_id, $club_id);
			if ($row = $query->next())
			{
				list($game_id, $log) = $row;
				// $console[] = 'game id = ' . $game_id;
				// $console[] = 'log = ' . $log;
                $game = new GameState();
				$game->init_existing($game_id, $log);
			}
			else
			{
				$game = new GameState();
				$game->init_new($_profile->user_id, $club_id);
			}
			
			$result['user'] = new GUser($club_id);
			$result['club'] = new GClub($club_id, $game);
			$result['game'] = $game;
			$result['time'] = time();
		}
		
		// if (count($console) > 0)
		// {
			// $result['console'] = $console;
		// }
	}
	else if (isset($_REQUEST['ulist']))
	{
		if (!isset($_REQUEST['club']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('club')));
		}
		$club_id = $_REQUEST['club'];
		
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
		
		array();
		if ($name == '')
		{
			$query = new DbQuery('SELECT id, name, NULL, flags FROM users ORDER BY rating DESC');
		}
		else
		{
			$query = new DbQuery(
				'SELECT id, name, NULL, flags FROM users ' .
					' WHERE name LIKE ? AND (flags & ' . U_FLAG_BANNED . ') = 0' .
					' UNION' .
					' SELECT DISTINCT u.id, u.name, r.nick_name, u.flags FROM users u' . 
					' JOIN registrations r ON r.user_id = u.id' .
					' WHERE r.nick_name <> u.name AND (u.flags & ' . U_FLAG_BANNED . ') = 0 AND r.nick_name LIKE ?',
				'%' . $name . '%',
				'%' . $name . '%');
		}
		
		if ($num > 0)
		{
			$query->add(' ORDER BY name LIMIT ' . $num);
		}
		
		$list = array();
		while ($row = $query->next())
		{
			list ($uid, $uname, $nick, $uflags) = $row;
			$p = new GPlayer($uid, $uname, $uflags, UC_PERM_PLAYER);
			if ($nick != NULL && $nick != $uname)
			{
				$p->nicks[$nick] = 1; 
			}
			$list[] = $p;
		}
		$result['list'] = $list;
	}
	else if (isset($_REQUEST['replace_incomer']))
	{
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('player')));
		}
		$id = $_REQUEST['id'];
		
		if (!isset($_REQUEST['user']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('user')));
		}
		$user_id = $_REQUEST['user'];
		
		list ($reg_id, $old_user_id, $event_id, $club_id, $name) = Db::record(get_label('player'), 'SELECT r.id, r.user_id, e.id, e.club_id, i.name FROM incomers i JOIN registrations r ON r.incomer_id = i.id JOIN events e ON r.event_id = e.id WHERE i.id = ?', $id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		if ($user_id <= 0)
		{
			$user_name = get_label('[unknown]');
			$user_id = NULL;
		}
		else
		{
			list ($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $user_id);
		}
		
		if ($old_user_id != $user_id)
		{
			Db::begin();
			Db::exec(get_label('registration'), 'UPDATE registrations SET user_id = ? WHERE id = ?', $user_id, $reg_id);
			
			if ($old_user_id == NULL)
			{
				$old_user_id = -$id;
			}
			
			if ($user_id == NULL)
			{
				$user_id = -$id;
			}
			$query = new DbQuery('SELECT id, log FROM games WHERE result > 0 AND result < 3 AND event_id = ?', $event_id);
			while($row = $query->next())
			{
				$gs = new GameState();
				$gs->init_existing($row[0], $row[1]);
				if ($gs->change_user($old_user_id, $user_id))
				{
					rebuild_game_stats($gs);
				}
			}
			Db::commit();
		}
		echo get_label('Event information is updated. Thank you.<p>[0] is [1].</p>', $name, $user_name);
	}
	else if (isset($_REQUEST['get_club']))
	{
		$result['club_id'] = def_club();
	}
	else if (isset($_REQUEST['delete_game']))
	{
		$game_id = (int)$_REQUEST['delete_game'];
		list($club_id) = Db::record(get_label('game'), 'SELECT club_id FROM games WHERE id = ?', $game_id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		Db::exec(get_label('game'), 'DELETE FROM dons WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM mafiosos WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM sheriffs WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM players WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM games WHERE id = ?', $game_id);
		
		// send notification to admin
		$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . U_PERM_ADMIN . ') <> 0 and email <> \'\'');
		while ($row = $query->next())
		{
			list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
			$lang = get_lang_code($admin_def_lang);
			list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_game_changed.php';
			
			$tags = array(
				'action' => new Tag(get_label('deleted')),
				'uname' => new Tag($admin_name),
				'game' => new Tag($game_id),
				'sender' => new Tag($_profile->user_name));
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($admin_email, $body, $text_body, $subj);
		}
		
		db_log('game', 'deleted', '', $game_id, $club_id);
		$result['message'] = get_label('Please note that ratings will not be updated immediately. We will send an email to the site administrator to review the changes and update the scores.');
	}
	else if (isset($_REQUEST['edit_game']))
	{
		$game_id = (int)$_REQUEST['edit_game'];
		list($club_id, $club_name, $log) = Db::record(get_label('game'), 'SELECT c.id, c.name, g.log FROM games g JOIN clubs c ON c.id = g.club_id WHERE g.id = ?', $game_id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		$result['club_id'] = $club_id;
		
		$query = new DbQuery('SELECT id, log FROM games WHERE user_id = ? AND club_id = ? AND result = 0', $_profile->user_id, $club_id);
		while ($row = $query->next())
		{
			list($gid, $glog) = $row;
			$gs = new GameState();
			$gs->init_existing($game_id, $glog);
			if ($gs->gamestate == GAME_NOT_STARTED)
			{
				Db::exec(get_label('game'), 'DELETE FROM games WHERE id = ?', $gid);
			}
			else
			{
				$result['open_game_anyway'] = true;
				throw new Exc(get_label('You are already editing a game for [0]. Please finish it first.', $club_name));
			}
		}
		
		$gs = new GameState();
		$gs->init_existing($game_id, $log);
		$gs->user_id = $_profile->user_id;
		$gs->save();
		
		Db::exec(get_label('game'), 'DELETE FROM dons WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM mafiosos WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM sheriffs WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'DELETE FROM players WHERE game_id = ?', $game_id);
		Db::exec(get_label('game'), 'UPDATE games SET result = 0, user_id = ? WHERE id = ?', $_profile->user_id, $game_id);
		
		// send notification to admin
		$query = new DbQuery('SELECT id, name, email, def_lang FROM users WHERE (flags & ' . U_PERM_ADMIN . ') <> 0 and email <> \'\'');
		while ($row = $query->next())
		{
			list($admin_id, $admin_name, $admin_email, $admin_def_lang) = $row;
			$lang = get_lang_code($admin_def_lang);
			list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_game_changed.php';
			
			$tags = array(
				'action' => new Tag(get_label('changed')),
				'uname' => new Tag($admin_name),
				'game' => new Tag($game_id),
				'sender' => new Tag($_profile->user_name));
			$body = parse_tags($body, $tags);
			$text_body = parse_tags($text_body, $tags);
			send_email($admin_email, $body, $text_body, $subj);
		}
		
		db_log('game', 'changed', 'old_log: ' . $log, $game_id, $club_id);
		$result['message'] = get_label('Please note that ratings will not be updated immediately. We will send an email to the site administrator to review the changes and update the scores.');
	}
	else if (isset($_REQUEST['set_video']))
	{
		$game_id = $_REQUEST['id'];
		$video = $_REQUEST['set_video'];
		list($club_id, $old_video) = Db::record(get_label('game'), 'SELECT club_id, video FROM games WHERE id = ?', $game_id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		Db::exec(get_label('game'), 'UPDATE games SET video = ? WHERE id = ?', $video, $game_id);
		if ($old_video == NULL)
		{
			db_log('game', 'changed', 'add_video', $game_id, $club_id);
		}
		else
		{
			db_log('game', 'changed', 'old_video: ' . $old_video, $game_id, $club_id);
		}
	}
	else if (isset($_REQUEST['remove_video']))
	{
		$game_id = $_REQUEST['remove_video'];
		list($club_id, $old_video) = Db::record(get_label('game'), 'SELECT club_id, video FROM games WHERE id = ?', $game_id);
		if (!isset($_profile->clubs[$club_id]) || ($_profile->clubs[$club_id]->flags & UC_PERM_MODER) == 0)
		{
			throw new Exc(get_label('No permissions'));
		}
		
		Db::exec(get_label('game'), 'UPDATE games SET video = NULL WHERE id = ?', $game_id);
		if ($old_video == NULL)
		{
			db_log('game', 'changed', 'video_removed', $game_id, $club_id);
		}
		else
		{
			db_log('game', 'changed', 'video_removed: ' . $old_video, $game_id, $club_id);
		}
	}
}
catch (Exception $e)
{
	Db::rollback();
	if ($e->getMessage() == 'login')
	{
		$result['login'] = "";
	}
	else
	{
		Exc::log($e, true);
		if (isset($query))
		{
			$result['sql'] = $query->get_parsed_sql();
		}
		$result['error'] = $e->getMessage();
	}
}

$message = ob_get_contents();
ob_end_clean();
if ($message != '')
{
	if (isset($result['message']))
	{
		$message = '<p>' . $result['message'] . '</p><br><br>' . $message;
	}
	$result['message'] = $message;
}

echo json_encode($result);

?>