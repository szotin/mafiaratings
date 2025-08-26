<?php

require_once __DIR__ . '/utilities.php';
require_once __DIR__ . '/server.php';
require_once __DIR__ . '/message.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/languages.php';

define('EMAIL_OBJ_EVENT', 0);
define('EMAIL_OBJ_GAME', 1);
define('EMAIL_OBJ_PHOTO', 2);
define('EMAIL_OBJ_SIGN_IN', 3);
define('EMAIL_OBJ_TOURNAMENT', 4);
define('EMAIL_OBJ_EVENT_INVITATION', 5);
// define('EMAIL_OBJ_EVENT_NO_USER', 6); // 6 is available
define('EMAIL_OBJ_VIDEO', 7);

$_unsubs_strings = array(
	LANG_ENGLISH => array(
		'Click <a href="[url]">unsubscribe</a> if you do not want to receive these emails any more.',
		'Goto [url] if you do not want to receive these emails any more.'),
	LANG_RUSSIAN => array(
		'Нажмите <a href="[url]">отписаться</a>, если вы больше не хотите получать таких писем.',
		'Зайдите на страницу [url], если вы больше не хотите получать таких писем.'),
	LANG_UKRAINIAN => array(
		'Натисніть <a href="[url]">відписатися</a>, якщо ви більше не хочете отримувати ці електронні листи.',
		'Зайдіть на сторінку [url], якщо ви більше не хочете отримувати ці електронні листи.')
);

function user_unsubscribe_url($user_id)
{
	return get_server_url() . '/unsubscribe.php?user_id=' . $user_id;
}

function admin_unsubscribe_url($user_id)
{
	return get_server_url() . '/unsubscribe.php?user_id=a' . $user_id;
}

function show_email_tags($event_tags)
{
	echo '<table class="transp" width="100%">';
	echo '<tr><td width="150"><b>'.get_label('Email tags').':</b></td><td align="right"><input type="submit" class="btn norm" name="'.get_label('preview').'" value="'.get_label('Preview email').'"></td></tr>';
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

function send_email($email, $body, $text_body, $subject, $unsubs_url = NULL, $lang = 0)
{
	global $_lang, $_unsubs_strings;

	if ($unsubs_url != NULL)
	{
		if (!is_valid_lang($lang))
		{
			$lang = $_lang;
		}
		
		if (array_key_exists($lang, $_unsubs_strings))
		{
			$unsubs_strings = $_unsubs_strings[$lang];
			if (!is_null($body))
			{
				$body .= "<hr>\r\n<p>" . str_replace('[url]', $unsubs_url, $unsubs_strings[0]) . '</p>';
			}
			if (!is_null($text_body))
			{
				$text_body .= "\r\n\r\n" . str_replace('[url]', $unsubs_url, $unsubs_strings[1]);
			}
		}
	}
	
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
			$headers .= "List-Unsubscribe-Post: <" . $unsubs_url . ">\r\n";
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
		echo '<center><table class="bordered" border="1" width="100%"><tr><td>' . get_label('Email has been sent to') . ': ' . $email . '</td></tr>';
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
	private $lang;
	
	public function __construct($email, $body, $text_body, $subject, $unsubs_url, $lang)
	{
		$this->email = $email;
		$this->body = $body;
		$this->text_body = $text_body;
		$this->subject = $subject;
		$this->unsubs_url = $unsubs_url;
		$this->lang = $lang;
	}

	public function commit()
	{
		send_email($this->email, $this->body, $this->text_body, $this->subject, $this->unsubs_url, $this->lang);
	}
}

function send_notification($email, $body, $text_body, $subject, $user_id, $user_lang, $obj, $obj_id, $code)
{
	$email = trim($email);
	if ($email == '')
	{
		return false;
	}

	$base_url = get_server_url();
	$unsubs_url = $base_url . '/unsubscribe.php?code=' . $code;
	$body =
		'<form method="get" action="' . $base_url . '/email_request.php">' . 
		'<input type="hidden" name="user_id" value="' . $user_id . '">' .
		'<input type="hidden" name="code" value="' . $code . '">' . $body .
		'</form>';
		
	Db::exec(
		get_label('email'), 
		'INSERT INTO emails (user_id, code, send_time, obj, obj_id) VALUES (?, ?, ?, ?, ?)', 
		$user_id, $code, time(), $obj, $obj_id);
	
	Db::add_commiter(new EmailCommiter($email, $body, $text_body, $subject, $unsubs_url, $user_lang));
}

?>