<?php

require_once 'include/updater.php';
require_once 'include/game.php';

define('ONE_YEAR', 31536000);
define('ONE_WEEK', 604800);
define('ONE_DAY', 86400);

define('CLUB_IDLE_TIME', 60 * 60 * 24 * 365); // one year

class GarbageCollector extends Updater
{
	function __construct()
	{
		parent::__construct(__FILE__);
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.incomplete_games
	//-------------------------------------------------------------------------------------------------------
	function incomplete_games_task($items_count)
	{
		if (!isset($this->vars->event_id))
		{
			$this->vars->event_id = 0;
		}
		if (!isset($this->vars->table_num))
		{
			$this->vars->table_num = 0;
		}
		if (!isset($this->vars->game_num))
		{
			$this->vars->game_num = 0;
		}
		
		$count = 0;
		$query = new DbQuery(
			'SELECT event_id, table_num, game_num, game'.
			' FROM current_games'.
			' WHERE event_id > ? OR (event_id = ? AND (table_num > ? OR (table_num = ? AND game_num > ?)))'.
			' ORDER BY event_id, table_num, game_num'.
			' LIMIT '.$items_count, 
			$this->vars->event_id, $this->vars->event_id, $this->vars->table_num, $this->vars->table_num, $this->vars->game_num);
		while ($row = $query->next())
		{
			list ($this->vars->event_id, $this->vars->table_num, $this->vars->game_num, $json) = $row;
			$json = json_decode($json);
			
			$start_time = 0;
			if (isset($json->startTime))
			{
				$start_time = $json->startTime;
			}
			
			// We immediatly delete the games that are not started after two weeks. 
			// We also delete all incomplete games that are older than one year. One year is enough to find them and complete.
			if (isset($json->time))
			{
				$timeout = ONE_YEAR;
			}
			else
			{
				$timeout = ONE_WEEK * 2;
			}
			
			if ($start_time + $timeout < time())
			{
				Db::exec('game', 'DELETE FROM current_games WHERE event_id = ? AND table_num = ? AND game_num = ?', $this->vars->event_id, $this->vars->table_num, $this->vars->game_num);
				$this->log('Deleted the game '.$this->vars->game_num.' table '.$this->vars->table_num.' from the event '.$this->vars->event_id);
			}
			++$count;
		}
		return $count;
	}

	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.no_players_games
	//-------------------------------------------------------------------------------------------------------
	function no_players_games_task($items_count)
	{
		if (!isset($this->vars->game_id))
		{
			$this->vars->game_id = 0;
		}
		
		$count = 0;
		$query = new DbQuery(
			'SELECT g.id, COUNT(p.user_id) as uid'.
			' FROM games g'.
			' LEFT OUTER JOIN players p ON p.game_id = g.id'.
			' WHERE g.end_time < UNIX_TIMESTAMP() - '.(ONE_WEEK * 2).' AND g.id > ?'.
			' GROUP BY g.id'.
			' HAVING uid = 0'.
			' ORDER BY g.id'.
			' LIMIT '.$items_count, 
			$this->vars->game_id);
		while ($row = $query->next())
		{
			list ($this->vars->game_id, $c) = $row;
			Db::exec(get_label('game'), 'DELETE FROM objections WHERE game_id = ?', $this->vars->game_id);
			Db::exec(get_label('game'), 'DELETE FROM game_issues WHERE game_id = ?', $this->vars->game_id);
			Db::exec(get_label('game'), 'DELETE FROM mr_bonus_stats WHERE game_id = ?', $this->vars->game_id);
			Db::exec('game', 'DELETE FROM games WHERE id = ?', $this->vars->game_id);
			$this->log('Deleted the game #'.$this->vars->game_id);
			++$count;
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.emails
	//-------------------------------------------------------------------------------------------------------
	function emails_task($items_count)
	{
		if (!isset($this->vars->user_id))
		{
			$this->vars->user_id = 0;
		}
		if (!isset($this->vars->code))
		{
			$this->vars->code = '';
		}
		
		$count = 0;
		$query = new DbQuery(
			'SELECT user_id, code'.
			' FROM emails'.
			' WHERE send_time < UNIX_TIMESTAMP() - '.ONE_YEAR.' AND (user_id > ? OR (user_id = ? AND code > ?))'.
			' ORDER BY user_id, code'.
			' LIMIT '.$items_count, 
			$this->vars->user_id, $this->vars->user_id, $this->vars->code);
		while ($row = $query->next())
		{
			list ($this->vars->user_id, $this->vars->code) = $row;
			Db::exec('email', 'DELETE FROM emails WHERE user_id = ? AND code = ?', $this->vars->user_id, $this->vars->code);
			++$count;
		}
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.events
	//-------------------------------------------------------------------------------------------------------
	function events_task($items_count)
	{
		if (!isset($this->vars->event_id))
		{
			$this->vars->event_id = 0;
		}
		
		$event_id = 0;
		$count = 0;
		Db::begin();
		$query = new DbQuery(
			'SELECT e.id, COUNT(g.id) as gid, COUNT(p.id) as pid, COUNT(v.id) as vid, COUNT(pl.user_id) as plid'.
			' FROM events e'.
			' LEFT OUTER JOIN games g ON g.event_id = e.id'.
			' LEFT OUTER JOIN photo_albums p ON p.event_id = e.id'.
			' LEFT OUTER JOIN videos v ON v.event_id = e.id'.
			' LEFT OUTER JOIN event_places pl ON pl.event_id = e.id'.
			' WHERE e.start_time + e.duration < UNIX_TIMESTAMP() - '.ONE_YEAR.' AND e.id > ? AND e.tournament_id IS NULL'.
			' GROUP BY e.id'.
			' HAVING gid = 0 AND pid = 0 AND vid = 0 AND plid = 0'.
			' ORDER BY e.id'.
			' LIMIT '.$items_count, 
			$this->vars->event_id);
		while ($row = $query->next())
		{
			list ($event_id) = $row;
			Db::exec('game', 'DELETE FROM current_games WHERE event_id = ?', $event_id);
			Db::exec('comment', 'DELETE FROM event_comments WHERE event_id = ?', $event_id);
			Db::exec('points', 'DELETE FROM event_extra_points WHERE event_id = ?', $event_id);
			Db::exec('mailing', 'DELETE FROM event_mailings WHERE event_id = ?', $event_id);
			Db::exec('registration', 'DELETE FROM event_incomers WHERE event_id = ?', $event_id); 
			Db::exec('registration', 'DELETE FROM event_regs WHERE event_id = ?', $event_id); 
			Db::exec('event', 'DELETE FROM events WHERE id = ?', $event_id);
			++$count;
		}
		Db::commit();
		$this->vars->event_id = $event_id;
		return $count;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// GarbageCollector.clubs
	//-------------------------------------------------------------------------------------------------------
	function clubs_task($items_count)
	{
		if (!isset($this->vars->club_id))
		{
			$this->vars->club_id = 0;
		}
		
		$club_id = 0;
		$count = 0;
		Db::begin();
		
		$query = new DbQuery(
			'SELECT c.id, c.name FROM clubs c'.
			' WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0'.
			' AND c.id > ?'.
			' AND c.activated < UNIX_TIMESTAMP() - '.CLUB_IDLE_TIME.
			' AND NOT EXISTS (SELECT g.id FROM games g WHERE g.club_id = c.id AND g.start_time > UNIX_TIMESTAMP() - '.CLUB_IDLE_TIME.')'.
			' ORDER BY c.id'.
			' LIMIT '.$items_count, $club_id);
		while ($row = $query->next())
		{
			list ($club_id, $club_name) = $row;
			Db::exec(get_label('club'), 'UPDATE clubs SET flags = flags | ' . CLUB_FLAG_CLOSED . ' WHERE id = ?', $club_id);
			$this->log('Club "' . $club_name . '" is closed due to more than one year of inactivity (id='.$club_id.')');
			
			// Send notifications
			$query1 = new DbQuery(
				'SELECT u.id, nu.name, u.email, u.def_lang'.
				' FROM club_regs cr'.
				' JOIN users u ON u.id = cr.user_id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
				' WHERE cr.club_id = ? AND (cr.flags & '.USER_PERM_MANAGER.') <> 0 AND (u.flags & '.USER_FLAG_ADMIN_NOTIFY.') <> 0', $club_id);
			while ($row1 = $query1->next())
			{
				list($user_id, $user_name, $user_email, $user_lang) = $row1;
				if (!is_valid_lang($user_lang))
				{
					$user_lang = get_lang($league_langs);
					if (!is_valid_lang($user_lang))
					{
						$user_lang = LANG_RUSSIAN;
					}
				}
				list($subj, $body, $text_body) = include 'include/languages/' . get_lang_code($user_lang) . '/email/club_closed.php';
				
				$tags = array(
					'root' => new Tag(get_server_url()),
					'user_id' => new Tag($user_id),
					'user_name' => new Tag($user_name),
					'club_id' => new Tag($club_id),
					'club_name' => new Tag($club_name));
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($user_email, $body, $text_body, $subj, admin_unsubscribe_url($user_id), $user_lang);
			}
			++$count;
		}
		Db::commit();
		$this->vars->club_id = $club_id;
		return $count;
	}
}

$updater = new GarbageCollector();
$updater->run();

?>