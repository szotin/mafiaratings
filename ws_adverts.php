<?php

require_once 'include/ws.php';

class Message
{
	public $id;
	public $timestamp;
	public $timezone;
	public $message;
	public $lang;
	public $date_str;
	
	function __construct($row, $date_format)
	{
		list($this->id, $this->timestamp, $this->timezone, $this->message, $this->lang) = $row;
		
		if ($date_format == '')
		{
			$this->date_str = '';
		}
		else
		{
			$this->date_str = format_date($date_format, $this->timestamp, $this->timezone);
		}
	}
}

class Adverts
{
	public $count;
	public $messages;
	
	function __construct($pos, $len, $date_format, $langs)
	{
		global $club_id;
	
		$condition = new SQL(' FROM news n JOIN clubs c ON c.id = n.club_id JOIN cities ct ON ct.id = c.city_id WHERE (n.lang & ?) <> 0 AND c.id = ?', $langs, $club_id);
		list ($this->count) = Db::record(get_label('advert'), 'SELECT count(*)', $condition);

		$query = new DbQuery('SELECT n.id, n.timestamp, ct.timezone, n.message, n.lang', $condition);
		$query->add(' ORDER BY n.timestamp DESC LIMIT ' . $pos . ',' . $len);

		$this->messages = array();
		while ($row = $query->next())
		{
			$this->messages[] = new Message($row, $date_format);
		}
	}
}

$pos = 0;
if (isset($_REQUEST['pos']))
{
	$pos = $_REQUEST['pos'];
}

$len = 15;
if (isset($_REQUEST['len']))
{
	$len = $_REQUEST['len'];
}

$date_format = '';
if (isset($_REQUEST['df']))
{
	$date_format = $_REQUEST['df'];
}

$langs = LANG_ALL;
if (isset($_REQUEST['l']))
{
	$date_format = $_REQUEST['l'];
}

try
{
	echo json_encode(new Adverts($pos, $len, $date_format, $langs));
}
catch (Exception $e)
{
	send_error($e);
}

?>