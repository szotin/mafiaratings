<?php

require_once 'include/general_page_base.php';
require_once 'include/forum.php';
require_once 'include/ccc_filter.php';

class Page extends GeneralPageBase
{
	private $message;

	protected function prepare()
	{
		global $_profile, $_page;
		
		parent::prepare();
		
		$message_id = -1;
		if (isset($_REQUEST['id']))
		{
			$message_id = $_REQUEST['id'];
		}

		$this->message = NULL;
		if ($message_id > 0)
		{
			$this->message = new ForumMessage($message_id);
			$mname = get_label('[0] at [1]', cut_long_name($this->message->user_name, 30), format_date('H:i, d M y', $this->message->send_time, $this->message->timezone));
			$this->_title = get_label('Message by [0]', $mname);
			$send_result = ForumMessage::proceed_send(FORUM_OBJ_REPLY, $this->message->id, $this->message->club_id);
		}
		else if ($this->ccc_filter->get_type() == CCCF_CLUB)
		{
			$send_result = ForumMessage::proceed_send(FORUM_OBJ_NO, 0, $this->ccc_filter->get_id());
		}
		else
		{
			$send_result = ForumMessage::proceed_send(FORUM_OBJ_NO, 0);
		}
		
		if ($send_result)
		{
			redirect_back('fsent=');
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$params = array('ccc' => $this->ccc_filter->get_code());
		if ($this->message != NULL)
		{
			$params['id'] = $this->message->id;
			$this->message->show_history(false);
			echo '<hr>';
			ForumMessage::show_send_form($params, get_label('Reply to [0]', $this->message->user_name) . ':', FORUM_SEND_FLAG_SHOW_PRIVATE);
			ForumMessage::show_messages($params, FORUM_OBJ_REPLY, $this->message->id);
		}
		else
		{
			ForumMessage::show_messages($params, FORUM_OBJ_NO, -1, $this->ccc_filter, false);
			ForumMessage::show_send_form($params, get_label('Send a message') . ':');
		}
	}
}

$page = new Page();
$page->set_ccc(CCCS_ALL);
$page->run(get_label('Forum'), PERM_ALL);

?>