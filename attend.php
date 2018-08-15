<?php

require_once 'include/page_base.php';
require_once 'include/event.php';

define('COMMENTS_WIDTH', 300);

class Page extends PageBase
{
	private $event_id;
	private $nickname;
	private $odds;
	private $friends;
	private $late;
	private $submit_name;
	private $user_id;
	private $event;

	function register()
	{
		global $_profile;

		if ($this->nickname == NULL)
		{
			$this->nickname = $_profile->user_name;
		}

		Db::exec(
			get_label('registration'),
			'DELETE FROM registrations WHERE event_id = ?  AND user_id = ?', 
			$this->event_id, 
			$this->user_id);
		
		if ($this->odds >= 100)
		{
			check_nickname($this->nickname, $this->event_id);
			
			Db::exec(
				get_label('registration'), 
				'INSERT INTO registrations (club_id, user_id, nick_name, duration, start_time, event_id) ' .
					'SELECT club_id, ?, ?, duration, start_time, id ' .
					'FROM events WHERE id = ?',
				$this->user_id, $this->nickname, $this->event_id);
		}
		throw new RedirectExc('event_info.php?id=' . $this->event_id);
	}

	protected function prepare()
	{
		global $_profile;
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('event')));
		}
		$this->event_id = $_REQUEST['id'];
		
		if (isset($_POST['cancel']))
		{
			throw new RedirectExc('event_info.php?id=' . $this->event_id);
		}
		
		$this->nickname = NULL;
		if (isset($_POST['nick']))
		{
			$this->nickname = $_POST['nick'];
		}

		$this->odds = 100;
		if (isset($_REQUEST['odds']))
		{
			$this->odds = $_POST['odds'];
		}
		$this->friends = 0;
		if (isset($_REQUEST['friends']))
		{
			$this->friends = $_POST['friends'];
		}
		$this->late = 0;
		if (isset($_REQUEST['late']))
		{
			$this->late = $_POST['late'];
		}
		
		$this->submit_name = 'create';
		$this->user_id = $_profile->user_id;
		if (isset($_POST['create']))
		{
			Db::exec(
				get_label('registration'), 
				'INSERT INTO event_users (event_id, user_id, coming_odds, people_with_me, late) VALUES (?, ?, ?, ?, ?)',
				$this->event_id, $this->user_id, $this->odds, $this->friends, $this->late);
			$this->submit_name = 'update';
			$this->register();
		}
		else if (isset($_POST['update']))
		{
			Db::exec(
				get_label('event'),
				'UPDATE event_users SET coming_odds = ?, people_with_me = ?, late = ? WHERE event_id = ? AND user_id = ?',
				$this->odds, $this->friends, $this->late, $this->event_id, $this->user_id);
			$this->submit_name = 'update';
			$this->register();
		}
		else
		{
			if (isset($_POST['odds']))
			{
				$this->odds = $_POST['odds'];
				$this->friends = 0;
				if (isset($_POST['friends']))
				{
					$this->friends = (int)$_POST['friends'];
				}
				$this->late = $_POST['late'];
				$query = new DbQuery('SELECT user_id FROM event_users WHERE event_id = ? AND user_id = ?', $this->event_id, $this->user_id);
				if ($query->next())
				{
					$this->submit_name = 'update';
				}
			}
			else
			{
				$query = new DbQuery('SELECT coming_odds, people_with_me, late FROM event_users WHERE event_id = ? AND user_id = ?', $this->event_id, $this->user_id);
				if ($row = $query->next())
				{
					list ($this->odds, $this->friends, $this->late) = $row;
					$this->submit_name = 'update';
				}
			}
			
			if ($this->nickname == NULL)
			{
				$query = new DbQuery('SELECT nick_name FROM registrations WHERE event_id = ? AND user_id = ?', $this->event_id, $this->user_id);
				if ($row = $query->next())
				{
					list ($this->nickname) = $row;
				}
			}
		}
		
		$this->event = new Event();
		$this->event->load($this->event_id);
		$this->_title = get_label('Attend [0]', $this->event->name);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<form method="post" name="playerForm">';
		echo '<input type="hidden" name="id" value="' . $this->event_id . '">';
		echo '<table class="bordered" width="100%">';
		if ($this->event->flags & EVENT_FLAG_TOURNAMENT)
		{
			echo '<tr><td>'.get_label('My nickname for this event is').':</td><td>';
			nick_name_chooser($this->user_id, $_profile->user_name, $this->nickname);
			echo '</td></tr>';
		}
		else
		{
			echo '<tr><td width="210">'.get_label('The chance that I am coming is').':</td><td><select name="odds" onChange = "document.playerForm.submit()">';
			for ($i = 0; $i <= 100; $i += 10)
			{
				show_option($i, $this->odds, $i . '%');
			}
			echo '</select></td></tr>';
			if ($this->odds > 0)
			{
				echo '<tr><td>'.get_label('I am bringing').':</td><td><select name="friends">';
				for ($i = 0; $i <= 10; ++$i)
				{
					show_option($i, $this->friends, $i);
				}
				echo '</select>&nbsp;&nbsp;'.get_label('friends with me.').'</td></tr>';
			}
			if ($this->odds == 100)
			{
				echo '<tr><td>'.get_label('My nickname for this event is').':</td><td>';
				nick_name_chooser($this->user_id, $_profile->user_name, $this->nickname);
				echo '</td></tr>';
			}
			echo '<tr><td>' . get_label('I will be there') . ':</td><td><select name="late">';
			show_option(0, $this->late, get_label('On time'));
			show_option(30, $this->late, get_label('[0] minutes late', 30));
			show_option(60, $this->late, get_label('1 hour late'));
			show_option(90, $this->late, get_label('[0] and a half hours late', '1'));
			show_option(120, $this->late, get_label('[0] hours late', '2'));
			show_option(150, $this->late, get_label('[0] and a half hours late', '2'));
			show_option(180, $this->late, get_label('[0] hours late', '3'));
			show_option(-1, $this->late, get_label('More than 3 hours late'));
			echo '</select></td></tr>';
		}
		echo '</table>';
		
		echo '<br><input value="'.get_label('Attend').'" type="submit" class="btn norm" name="' . $this->submit_name . '"><input value="'.get_label('Cancel').'" type="submit" class="btn norm" name="cancel"></form>';
		echo '</form>';
		
		echo '<table width="100%"><tr valign="top"><td>';
		$this->event->show_details();
		echo '</td><td id="comments" width="' . COMMENTS_WIDTH . '"></td></tr></table>';
?>
		<script type="text/javascript">
			mr.showComments("event", <?php echo $this->event->id; ?>, 5);
		</script>
<?php
	}
}

$page = new Page();
$page->run(get_label('Attend event'), PERM_USER);

?>