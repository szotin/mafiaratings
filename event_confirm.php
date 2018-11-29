<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/game_stats.php';

class Page extends PageBase
{
	private $event;
	private $yes;
	private $join;
	
	private function update_reg($reg_id, $user_id, $incomer_id, $new_user_id)
	{
		Db::exec(get_label('registration'), 'UPDATE registrations SET user_id = ?, incomer_id = ? WHERE id = ?', $new_user_id, $incomer_id, $reg_id);
		
		if ($user_id == NULL)
		{
			$user_id = -$incomer_id;
		}
		
		if ($new_user_id == NULL)
		{
			$new_user_id = -$incomer_id;
		}
		
		$query = Db::exec(get_label('event'), 'SELECT id, log FROM games WHERE result > 0 AND result < 3 AND event_id = ?', $this->event->id);
		while($row = $query->next())
		{
			$gs = new GameState();
			$gs->init_existing($row[0], $row[1]);
			if ($gs->change_user($user_id, $new_user_id))
			{
				rebuild_game_stats($gs);
			}
		}
	}
	
	protected function prepare()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		if (!isset($_REQUEST['event']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		
		$this->event = new Event();
		$this->event->load($_REQUEST['event']);
		
		if ($this->event->timestamp + $this->event->duration + EVENT_NOT_DONE_TIME < time())
		{
			throw new FatalExc(get_label('Too late to claim this event.'));
		}
		
		$this->yes = isset($_REQUEST['yes']);
		$this->join = isset($_REQUEST['join']);
		
		Db::begin();
		
		$query = new DbQuery('SELECT id, incomer_id, nick_name FROM registrations WHERE event_id = ? AND user_id = ?', $this->event->id, $_profile->user_id);
		if ($row = $query->next())
		{
			list($reg_id, $incomer_id, $nick) = $row;
			if (!$this->yes)
			{
				if ($incomer_id == NULL)
				{
					Db::exec(get_label('user'), 'INSERT INTO incomers (event_id, name) VALUES (?, ?)', $this->event->id, $_profile->user_name);
					list ($incomer_id) = Db::record(get_label('user'), 'SELECT LAST_INSERT_ID()');
					Db::exec(get_label('player'), 'INSERT INTO incomer_suspects (reg_id, incomer_id, user_id) VALUES (?, ?, ?)', $reg_id, $incomer_id, $_profile->user_id);
				}
				$this->update_reg($reg_id, $_profile->user_id, $incomer_id, NULL);
			}
		}
		else
		{
			$query = new DbQuery('SELECT r.id, r.user_id, s.incomer_id, r.nick_name FROM incomer_suspects s JOIN registrations r ON s.reg_id = r.id WHERE r.event_id = ? AND s.user_id = ?', $this->event->id, $_profile->user_id);
			if (!($row = $query->next()))
			{
				throw new FatalExc(get_label('We can not find any records witnesing that you participated in this event.'));
			}
			
			list ($reg_id, $user_id, $incomer_id, $nick) = $row;
			if ($user_id == $_profile->user_id)
			{
				if (!$this->yes)
				{
					$this->update_reg($reg_id, $user_id, $incomer_id, NULL);
				}
			}
			else if ($user_id == NULL)
			{
				if ($this->yes)
				{
					$this->update_reg($reg_id, $user_id, $incomer_id, $_profile->user_id);
				}
			}
			else
			{
				$this->update_reg($reg_id, $user_id, $incomer_id, NULL);
				
				// complain
				$to = $_profile->user_name . '<' . $_profile->user_email . '>';
				list($user_name, $user_email) = Db::record(get_label('user'), 'SELECT name, email FROM users WHERE id = ?', $user_id);
				$to .= ', ' . $user_name . '<' . $user_email . '>';
				$query = new DbQuery('SELECT u.name, u.email FROM users u WHERE (u.flags & ' . USER_PERM_ADMIN . ') <> 0 OR u.id IN (SELECT c.user_id FROM user_clubs c WHERE c.club_id = ? AND (c.flags & ' . USER_CLUB_PERM_MANAGER . ') <> 0)', $this->event->club_id);
				while ($row = $query->next())
				{
					list($u_name, $u_email) = $row;
					$to .= ', ' . $u_name . '<' . $u_email . '>';
				}
				
				$l = get_next_lang(LANG_NO, $this->event->langs);
				if ($l == LANG_NO)
				{
					$l = LANG_ENGLISH;
				}
				$lang = get_lang_code($l);
				$tags = array(
					'root' => new Tag(get_server_url()),
					'event_name' => new Tag($this->event->name),
					'event_id' => new Tag($this->event->id),
					'event_date' => new Tag(format_date('l, F d, Y', $this->event->timestamp, $this->event->timezone, $l)),
					'event_time' => new Tag(format_date('H:i', $this->event->timestamp, $this->event->timezone, $l)),
					'address' => new Tag($this->event->addr),
					'address_url' => new Tag($this->event->addr_url),
					'club_name' => new Tag($this->event->club_name),
					'club_id' => new Tag($this->event->club_id),
					'nick' => new Tag($nick),
					'user_id1' => new Tag($user_id),
					'user_id2' => new Tag($_profile->user_id),
					'user_name1' => new Tag($user_name),
					'user_name2' => new Tag($_profile->user_name));
					
				list($subj, $body, $text_body) = include 'include/languages/' . $lang . '/email_event_conflict.php';
				$body = parse_tags($body, $tags);
				$text_body = parse_tags($text_body, $tags);
				send_email($to, $body, $text_body, $subj);
			}
		}
		
		$log_details = new stdClass();
		$log_details->event_id = $this->event->id;
		$log_details->registration_id = $reg_id;
		$log_details->incomer_id = $incomer_id;
		$log_details->nick = $nick;
		if ($this->yes)
		{
			db_log(LOG_OBJECT_USER, 'confirmed event', $log_details, $_profile->user_id, $this->event->club_id);
		}
		else
		{
			db_log(LOG_OBJECT_USER, 'declined event', $log_details, $_profile->user_id, $this->event->club_id);
		}
		
		if ($this->yes && $this->join)
		{
			list($count) = Db::record(get_label('user'), 'SELECT count(*) FROM user_clubs WHERE user_id = ? AND club_id = ?', $_profile->user_id, $this->event->club_id);
			if ($count == 0)
			{
				Db::exec(get_label('user'), 'INSERT INTO user_clubs (user_id, club_id, flags) VALUES (?, ?, ' . USER_CLUB_NEW_PLAYER_FLAGS . ')', $_profile->user_id, $this->event->club_id);
				db_log(LOG_OBJECT_USER, 'joined club', NULL, $_profile->user_id, $this->event->club_id);
				$_profile->update_clubs();
			}
			else
			{
				$this->join = false;
			}
		}
		
		Db::commit();
	}
	
	protected function show_body()
	{
		global $_profile;
		if ($this->yes)
		{
			echo get_label('Thank you for confirming that you played in [0] at [1].',
				$this->event->club_name, 
				format_date('l, F d, Y H:i', $this->event->timestamp, $this->event->timezone, $_profile->user_def_lang));
			if ($this->join)
			{
				echo '<p>' . get_label('And thank you for joining [0].', $this->event->club_name) . '</p>';
			}
		}
		else
		{
			echo get_label('Thank you for confirming that you <b>did not</b> play in [0] at [1].',
				$this->event->club_name, 
				format_date('l, F d, Y H:i', $this->event->timestamp, $this->event->timezone, $_profile->user_def_lang));
		}
	}
}

$page = new Page();
$page->run(get_label('Confirm event'));

?>
