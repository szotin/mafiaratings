<?php

require_once 'include/page_base.php';
require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/event_mailing.php';

define("PAGE_SIZE",40);

class Page extends PageBase
{
	private $id;
	private $event;
	private $send_time;
	private $langs;
	private $flags;
	private $status;
	
	private function parse_sample_email($email_addr, $type, $langs)
	{
		global $_profile;
		$code = generate_email_code();
		$base_url = get_server_url() . '/email_request.php?user_id=' . $_profile->user_id . '&code=' . $code;
		
		$lang = get_event_email_lang($_profile->user_def_lang, $langs);
		list($subj, $body, $text_body) = get_event_email($type, $lang);

		$tags = get_bbcode_tags();
		$tags['root'] = new Tag(get_server_url());
		$tags['event_name'] = new Tag($this->event->name);
		$tags['event_id'] = new Tag($this->event->id);
		$tags['event_date'] = new Tag(format_date('l, F d, Y', $this->event->timestamp, $this->event->timezone, $lang));
		$tags['event_time'] = new Tag(format_date('H:i', $this->event->timestamp, $this->event->timezone, $lang));
		$tags['notes'] = new Tag($this->event->notes);
		$tags['langs'] = new Tag(get_langs_str($this->event->langs, ', ', LOWERCASE, $lang));
		$tags['address'] = new Tag($this->event->addr);
		$tags['address_url'] = new Tag($this->event->addr_url);
		$tags['address_id'] = new Tag($this->event->addr_id);
		if ($this->event->id > 0)
		{
			$tags['address_image'] = new Tag('<img src="' . get_server_url() . '/' . ADDRESS_PICS_DIR . TNAILS_DIR . $this->event->addr_id . '.jpg">');
		}
		else
		{
			$tags['address_image'] = new Tag('<img src="images/sample_address.jpg">');
		}
		$tags['user_name'] = new Tag($_profile->user_name);
		$tags['user_id'] = new Tag($_profile->user_id);
		$tags['email'] = new Tag($email_addr);
		$tags['club_name'] = new Tag($this->event->club_name);
		$tags['club_id'] = new Tag($this->event->club_id);
		$tags['code'] = new Tag($code);
		$tags['accept'] = new Tag('<a href="' . $base_url . '&accept=1" target="_blank">', '</a>');
		$tags['decline'] = new Tag('<a href="' . $base_url . '&decline=1" target="_blank">', '</a>');
		$tags['unsub'] = new Tag('<a href="' . $base_url . '&unsub=1" target="_blank">', '</a>');
		$tags['accept_btn'] = new Tag('<input type="submit" name="accept" value="#">');
		$tags['decline_btn'] = new Tag('<input type="submit" name="decline" value="#">');
		$tags['unsub_btn'] = new Tag('<input type="submit" name="unsub" value="#">');
	
		return array(
			parse_tags($body, $tags),
			parse_tags($subj, $tags),
			$lang);
	}
	
	protected function prepare()
	{
		global $_profile;
		if (!isset($_REQUEST['id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('mailing')));
		}
		$this->id = $_REQUEST['id'];
		
		list($event_id, $this->send_time, $this->type, $this->langs, $this->flags, $this->status) =
			Db::record(get_label('email'), 'SELECT event_id, send_time, type, langs, flags, status FROM event_mailings WHERE id = ?', $this->id);

		$this->event = new Event();
		$this->event->load($event_id);
		$this->_title = get_label('Mailing for [0]', $this->event->get_full_name());
		check_permissions(PERMISSION_CLUB_MANAGER, $this->event->club_id);
	}
	
	protected function show_body()
	{
		global $_profile, $_page;
		
		list($p_body, $p_subj, $this->lang) = $this->parse_sample_email($_profile->user_email, $this->type, $this->langs);
		
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
			echo '<tr class="darker" style="height:40px; font-weight:bold;"><td colspan="2">' . get_label('Sent to') . '</td><td width="160" align="center">' . get_label('Send time') . '</td></tr>';
			
			$query = new DbQuery(
				'SELECT u.id, u.name, u.flags, e.send_time FROM emails e, users u WHERE e.user_id = u.id AND e.obj = ' . EMAIL_OBJ_EVENT .
					' AND e.obj_id = ? ORDER BY u.name LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, 
				$this->id);
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_flags, $send_time) = $row;
				echo '<tr><td width="50"><a href="user_info.php?id=' . $row[0] . '&bck=1">';
				$this->user_pic->set($user_id, $user_name, $user_flags);
				$this->user_pic->show(ICONS_DIR, 48);
				echo '</a></td><td>' . $user_name . '</td>';
				echo '<td class="dark" align="center">' . format_date('F d, Y, H:i', $send_time, $timezone) . '</td></tr>';
			}
			
			echo '</table>';
		}
	}
}

$page = new Page();
$page->run(get_label('view event mailing'));

?>
