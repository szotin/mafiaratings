<?php

require_once 'include/ws.php';

class Album
{
	public $id;
	public $name;
	public $icon_url;
	public $user_id;
	public $user_name;
	
	function __construct($row)
	{
		list($this->id, $this->name, $flags, $this->user_id, $this->user_name) = $row;
		
		$this->icon_url = '';
		if (($flags & ALBUM_ICON_MASK) != 0)
		{
			$this->icon_url = get_server_url() . '/' . ALBUM_PICS_DIR . TNAILS_DIR . $this->id . '.png';
		}
	}
}

class Albums
{
	public $count;
	public $albums;
	
	function __construct($pos, $len)
	{
		global $club_id;
		
		$where = new SQL('a.viewers = ' . FOR_EVERYONE . ' AND a.club_id = ?', $club_id);
		
		list ($this->count) = Db::record(get_label('photo album'), 'SELECT count(*) FROM photo_albums a WHERE ', $where);
		
		$query = new DbQuery('SELECT a.id, a.name, a.flags, u.id, u.name FROM photo_albums a JOIN users u ON u.id = a.user_id WHERE ', $where); 
		$query->add(' ORDER BY a.id DESC LIMIT ' . $pos . ',' . $len);
		
		$this->albums = array();
		while ($row = $query->next())
		{
			$this->albums[] = new Album($row);
		}
	}
}

$pos = 0;
if (isset($_REQUEST['pos']))
{
	$pos = $_REQUEST['pos'];
}

$len = 20;
if (isset($_REQUEST['len']))
{
	$len = $_REQUEST['len'];
}

try
{
	echo json_encode(new Albums($pos, $len));
}
catch (Exception $e)
{
	send_error($e);
}

?>