<?php

require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/event_mailing.php';

define('PAGE_SIZE', USERS_PAGE_SIZE);

class Page extends EventPageBase
{
	private function parse_sample_email()
	{
		global $_profile, $_lang;
		$code = generate_email_code();
		$base_url = get_server_url() . '/email_request.php?user_id=' . $_profile->user_id . '&code=' . $code;
		
		$lang = get_event_email_lang($_lang, $this->mailing_langs);
		list($subj, $body, $text_body) = get_event_email($this->mailing_type, $lang);

		$tags = get_bbcode_tags();
		$tags['root'] = new Tag(get_server_url());
		$tags['event_name'] = new Tag($this->name);
		$tags['event_id'] = new Tag($this->id);
		$tags['event_date'] = new Tag(format_date($this->start_time, $this->timezone, false, $lang));
		$tags['event_time'] = new Tag(date('H:i', $this->start_time));
		$tags['notes'] = new Tag($this->notes);
		$tags['langs'] = new Tag(get_langs_str($this->langs, ', ', LOWERCASE, $lang));
		$tags['address'] = new Tag($this->address);
		$tags['address_url'] = new Tag($this->address_url);
		$tags['address_id'] = new Tag($this->address_id);
		if ($this->id > 0)
		{
			$tags['address_image'] = new Tag('<img src="' . get_server_url() . '/' . ADDRESS_PICS_DIR . TNAILS_DIR . $this->address_id . '.jpg">');
		}
		else
		{
			$tags['address_image'] = new Tag('<img src="images/sample_address.jpg">');
		}
		$tags['user_name'] = new Tag($_profile->user_name);
		$tags['user_id'] = new Tag($_profile->user_id);
		$tags['email'] = new Tag($_profile->user_email);
		$tags['club_name'] = new Tag($this->club_name);
		$tags['club_id'] = new Tag($this->club_id);
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
		
		parent::prepare();
		
		if (!isset($_REQUEST['mailing_id']))
		{
			throw new FatalExc(get_label('Unknown [0]', get_label('mailing')));
		}
		$this->mailing_id = $_REQUEST['mailing_id'];
		
		list($event_id, $this->send_time, $this->mailing_type, $this->mailing_langs, $this->mailing_status) =
			Db::record(get_label('email'), 'SELECT event_id, send_time, type, langs, status FROM event_mailings WHERE id = ?', $this->mailing_id);

		$this->_title = get_label('Mailing for [0]', $this->get_full_name());
		check_permissions(PERMISSION_CLUB_MANAGER, $this->club_id);
	}
	
	protected function show_body()
	{
		global $_profile, $_page, $_lang;
		
		list($p_body, $p_subj, $this->lang) = $this->parse_sample_email();
		
		$timezone = get_timezone();
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="th darker"><td>' . $p_subj . '</td>';
		echo '<td width="100">' . get_lang_str($this->lang) . '</td>';
		echo '<td width="160">' . format_date($this->send_time, $timezone, true) . '</td></tr>';
		echo '<tr><td colspan="3">' . $p_body . '</td></tr>';
		echo '</table>';

		if ($this->mailing_status == MAILING_SENDING || $this->mailing_status == MAILING_COMPLETE)
		{
			list ($count) = Db::record(get_label('email'), 'SELECT count(*) FROM emails WHERE obj = ' . EMAIL_OBJ_EVENT_INVITATION . ' AND obj_id = ?', $this->mailing_id);
			show_pages_navigation(PAGE_SIZE, $count);
		
			echo '<table class="bordered light" width="100%">';
			echo '<tr class="darker" style="height:40px; font-weight:bold;"><td colspan="2">' . get_label('Sent to') . '</td><td width="160" align="center">' . get_label('Send time') . '</td></tr>';
			
			$query = new DbQuery(
				'SELECT u.id, nu.name, u.flags, e.send_time'.
				' FROM emails e'.
				' JOIN users u ON e.user_id = u.id'.
				' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
				' WHERE e.obj = ' . EMAIL_OBJ_EVENT_INVITATION . ' AND e.obj_id = ?'.
				' ORDER BY nu.name'.
				' LIMIT ' . ($_page * PAGE_SIZE) . ',' . PAGE_SIZE, 
				$this->mailing_id);
			while ($row = $query->next())
			{
				list($user_id, $user_name, $user_flags, $send_time) = $row;
				echo '<tr><td width="50">';
				$this->user_pic->set($user_id, $user_name, $user_flags);
				$this->user_pic->show(ICONS_DIR, true, 48);
				echo '</td><td>' . $user_name . '</td>';
				echo '<td class="dark" align="center">' . format_date($send_time, $timezone, true) . '</td></tr>';
			}
			
			echo '</table>';
			show_pages_navigation(PAGE_SIZE, $count);
		}
	}
}

$page = new Page();
$page->run(get_label('view event mailing'));

?>
