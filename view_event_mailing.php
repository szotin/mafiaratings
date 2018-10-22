<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/pages.php';

define("PAGE_SIZE",40);

class Page extends PageBase
{
	private $id;
	private $event;
	private $send_time;
	private $subj;
	private $body;
	private $lang;
	private $flags;
	private $status;
	
	protected function prepare()
	{
		global $_profile;
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('mailing')));
		}
		$this->id = $_REQUEST['id'];
		
		list($event_id, $this->send_time, $this->subj, $this->body, $this->lang, $this->flags, $this->status) =
			Db::record(get_label('email'), 'SELECT event_id, send_time, subject, body, lang, flags, status FROM event_emails WHERE id = ?', $this->id);

		$this->event = new Event();
		$this->event->load($event_id);
		$this->_title = get_label('Mailing for [0]', $this->event->get_full_name());
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		list($p_body, $p_subj, $this->lang) = $this->event->parse_sample_email($_profile->user_email, $this->body, $this->subj, $this->lang);
		
		$timezone = get_timezone();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td>' . $p_subj . '</td>';
		echo '<td width="100">' . get_lang_str($this->lang) . '</td>';
		echo '<td width="160">' . format_date('F d, Y, H:i', $this->send_time, $timezone) . '</td></tr>';
		echo '<tr><td colspan="3">' . $p_body . '</td></tr>';
		echo '</table>';

		if ($this->status == MAILING_SENDING || $this->status == MAILING_COMPLETE)
		{
			list ($count) = Db::record(get_label('email'), 'SELECT count(*) FROM emails WHERE obj = ' . EMAIL_OBJ_EVENT . ' AND obj_id = ?', $this->id);
			echo '<p></p>';
			show_pages_navigation(PAGE_SIZE, $count);
		
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker" style="height:40px; font-weight:bold;"><td width="160">' . get_label('Time') . '</td><td>' . get_label('Sent to') . '</td></tr>';
			
			$query = new DbQuery(
				'SELECT u.id, u.name, e.send_time FROM emails e, users u WHERE e.user_id = u.id AND e.obj = ' . EMAIL_OBJ_EVENT .
					' AND e.obj_id = ? ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, 
				$this->id);
			while ($row = $query->next())
			{
				echo '<tr><td class="dark">' . format_date('F d, Y, H:i', $row[2], $timezone) . '</td>';
				echo '<td><a href="user_info.php?id=' . $row[0] . '&bck=1">' . $row[1] . '</a></td></tr>';
			}
			
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run(get_label('view event mailing'), USER_CLUB_PERM_MANAGER);

?>
