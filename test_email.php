<?php

require_once 'include/page_base.php';
require_once 'include/email.php';

class Page extends PageBase
{
	public $email;
	public $message;

	protected function prepare()
	{
		$this->email = '';
		if (isset($_POST['email']))
		{
			$this->email = $_POST['email'];
		}
		
		$this->message = NULL;
		if (isset($_POST['message']))
		{
			$this->message = $_POST['message'];
		}
		
		if (isset($_POST['test']))
		{
			$body = '<form action="http://' . get_server_url() . '/test_email.php" method="post"><p>Testing <b>email</b>!!!</p><p>Send your message: <input name="message" value="' . $this->message . '"><input type="hidden" name="email" value="' . $this->email . '"></p><p><input type="submit" name="message_test" value="Send"></p></form>';
			$text_body = "Testing email!!!\r\nMafia ratings";
			$subject = 'Mafia ratings';
			send_email($this->email, $body, $text_body, $subject);
		}
	}
	
	protected function show_body()
	{
		if (isset($_POST['test']))
		{
			echo '<p>Email has been sent to ' . $this->email . '</p>';
		}
		if ($this->message != 'NULL')
		{
			echo '<p>' . $this->message . '</p>';
		}
		echo '<form method="post" name="emailForm">';
		echo '<p>Email: <input name="email" value="' . $this->email . '"></p>';
		echo '<p><input type="submit" name="test" value="Send"></p>';
	}
}

$page = new Page();
$page->run(get_label('Email test'), PERM_ALL);

?>