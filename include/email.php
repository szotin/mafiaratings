<?php

require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/server.php';
require_once __DIR__ . '/message.php';
require_once __DIR__ . '/db.php';

define('EMAIL_OBJ_EVENT', 0);
define('EMAIL_OBJ_GAME', 1);
define('EMAIL_OBJ_PHOTO', 2);
define('EMAIL_OBJ_SIGN_IN', 3);
define('EMAIL_OBJ_TOURNAMENT', 4);
define('EMAIL_JOIN_CLUB', 5);
// define('EMAIL_OBJ_EVENT_NO_USER', 6); // 6 is available
define('EMAIL_OBJ_VIDEO', 7);

function show_email_tags($event_tags)
{
	echo '<table class="transp" width="100%">';
	echo '<tr><td width="150"><b>'.get_label('Email tags').':</b></td><td align="right"><input type="submit" class="btn norm" name="'.get_label('preview').'" value="'.get_label('Preview email').'"></td></tr>';
	echo '<tr><td>'.get_label('Unsubscribe button').'</td><td><b>[unsub]</b>'.get_label('Button text').'<b>[/unsub]</b></td></tr>';
	if ($event_tags)
	{
		echo '<tr><td>'.get_label('Accept button').'</td><td><b>[accept]</b>'.get_label('Button text').'<b>[/accept]</b></td></tr>';
		echo '<tr><td>'.get_label('Decline button').'</td><td><b>[decline]</b>'.get_label('Button text').'<b>[/decline]</b></td></tr>';
		echo '<tr><td>'.get_label('Event name').'</td><td><b>[event_name]</b></td></tr>';
		echo '<tr><td>'.get_label('Event id').'</td><td><b>[event_id]</b></td></tr>';
		echo '<tr><td>'.get_label('Event date').'</td><td><b>[event_date]</b></td></tr>';
		echo '<tr><td>'.get_label('Event time').'</td><td><b>[event_time]</b></td></tr>';
		echo '<tr><td>'.get_label('Event address').'</td><td><b>[address]</b></td></tr>';
		echo '<tr><td>'.get_label('Event address URL').'</td><td><b>[address_url]</b></td></tr>';
		echo '<tr><td>'.get_label('Event address id').'</td><td><b>[address_id]</b></td></tr>';
		echo '<tr><td>'.get_label('Event address image').'</td><td><b>[address_image]</b></td></tr>';
		echo '<tr><td>'.get_label('Event notes').'</td><td><b>[notes]</b></td></tr>';
	}
	echo '<tr><td>'.get_label('User name').'</td><td><b>[user_name]</b></td></tr>';
	echo '<tr><td>'.get_label('User id').'</td><td><b>[user_id]</b></td></tr>';
	echo '<tr><td>'.get_label('User email').'</td><td><b>[email]</b></td></tr>';
	echo '<tr><td>'.get_label('Club name').'</td><td><b>[club_name]</b></td></tr>';
	echo '<tr><td>'.get_label('Club id').'</td><td><b>[club_id]</b></td></tr>';
	echo '<tr><td>'.get_label('Email code').'</td><td><b>[code]</b></td></tr>';
	echo '</table>';
}

function send_email($email, $body, $text_body, $subject, $unsubs_url = NULL)
{
	if (is_production_server())
	{
		$headers =
			"From: " . PRODUCT_EMAIL . "\r\n" .
			"Reply-To: " . PRODUCT_EMAIL . "\r\n" .
			"Precedence: bulk\r\n" .
			"Return-Path: <" . PRODUCT_EMAIL . ">\r\n" .
			"MIME-Version: 1.0\r\n" .
			"X-Mailer: PHP/" . phpversion() . "\r\n";
			
		if ($unsubs_url != NULL)
		{
			$headers .= "List-Unsubscribe: <" . $unsubs_url . ">\r\n";
		}

		if ($body == NULL)
		{
			$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
			$body = $text_body;
		}
		else if ($text_body == NULL)
		{
			$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
			$body = '<html><body>' . $body . '</body></html>';
		}
		else
		{
			$headers .= "Content-Type: multipart/alternative; boundary=c4d5d00c4725d9ed0b3c8b\r\n";
			$body = 
				"--c4d5d00c4725d9ed0b3c8b\r\n" . 
				"Content-Type: text/plain; charset=UTF-8\r\n\r\n" . $text_body . "\r\n\r\n" .
				"--c4d5d00c4725d9ed0b3c8b\r\n" . 
				"Content-Type: text/html; charset=UTF-8\r\n\r\n" . 
				"<html><body>" . $body . "</body></html>\r\n\r\n" . 
				"--c4d5d00c4725d9ed0b3c8b--\r\n";
		}
			
		if (!mail($email, $subject, $body, $headers))
		{
			throw new Exc(get_label('Failed to send email "[1]" to [0]', $email, $subject));
		}
	}
	else if (!defined('GATE'))
	{
		echo '<center><table class="bordered" width="100%"><tr><td>' . get_label('Email has been sent to') . ': ' . $email . '</td></tr>';
		if ($body != NULL)
		{
			echo '<tr><td>' . $body . '</td></tr>';
		}
		// if ($text_body != NULL)
		// {
			// echo '<tr><td><pre>' . $text_body . '</pre></td></tr>';
		// }
		echo '</table></center>';
	}
}

function generate_email_code()
{
	return md5(rand_string(10));
}

class EmailCommiter extends DbCommiter
{
	private $email;
	private $body;
	private $text_body;
	private $subject;
	private $unsubs_url;
	
	public function __construct($email, $body, $text_body, $subject, $unsubs_url)
	{
		$this->email = $email;
		$this->body = $body;
		$this->text_body = $text_body;
		$this->subject = $subject;
		$this->unsubs_url = $unsubs_url;
	}

	public function commit()
	{
		send_email($this->email, $this->body, $this->text_body, $this->subject, $this->unsubs_url);
	}
}

function send_notification($email, $body, $text_body, $subject, $user_id, $obj, $obj_id, $code)
{
	$email = trim($email);
	if ($email == '')
	{
		return false;
	}

	$url = get_server_url() . '/email_request.php';
	$unsubs_url = $url . '?user_id=' . $user_id . '&code=' . $code . '&unsub=1';
	$body =
		'<form method="get" action="' . $url . '">' . 
		'<input type="hidden" name="user_id" value="' . $user_id . '">' .
		'<input type="hidden" name="code" value="' . $code . '">' . $body .
		'</form>';
		
	Db::exec(
		get_label('email'), 
		'INSERT INTO emails (user_id, code, send_time, obj, obj_id) VALUES (?, ?, ?, ?, ?)', 
		$user_id, $code, time(), $obj, $obj_id);
	
	Db::add_commiter(new EmailCommiter($email, $body, $text_body, $subject, $unsubs_url));
}

?>