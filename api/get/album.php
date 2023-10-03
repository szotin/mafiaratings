<?php

require_once '../../include/api.php';

$_base_url = get_server_url() . '/';

class Photo
{
	public $url;
	public $tnail_url;
	
	function __construct($id)
	{
		global $_base_url;
	
		$this->url = $_base_url . PHOTOS_DIR . $id . '.jpg';
		$this->tnail_url = $_base_url . PHOTOS_DIR . TNAILS_DIR . $id . '.jpg';
	}
}

class Album
{
	public $id;
	public $name;
	public $icon_url;
	public $user_id;
	public $user_name;
	public $photos;
	public $photo_count;
	
	function __construct($id, $pos, $len)
	{
		global $_lang;
		
		$this->id = $id;
		
		$row = Db::record(get_label('photo album'),
			'SELECT a.id, a.name, a.flags, u.id, nu.name'.
			' FROM photo_albums a'.
			' JOIN users u ON u.id = a.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE a.viewers = ' . FOR_EVERYONE . ' AND a.id = ' . $id);
			
		list($this->id, $this->name, $flags, $this->user_id, $this->user_name) = $row;
		$this->icon_url = '';
		if (($flags & ALBUM_ICON_MASK) != 0)
		{
			$this->icon_url = get_server_url() . '/' . ALBUM_PICS_DIR . TNAILS_DIR . $this->id . '.png';
		}
			
			
		list ($this->photo_count) = Db::record(get_label('photo'), 'SELECT count(*) FROM photos WHERE viewers = ' . FOR_EVERYONE . ' AND album_id = ' . $id);
		$query = new DbQuery('SELECT id FROM photos WHERE viewers = ' . FOR_EVERYONE . ' AND album_id = ' . $id . ' ORDER BY id DESC LIMIT ' . $pos . ',' . $len);
		
		$this->photos = array();
		while ($row = $query->next())
		{
			$this->photos[] = new Photo($row[0]);
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

initiate_session();
$response = NULL;
try
{
	if (!isset($_REQUEST['id']))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('photo album')));
	}
	$response = new Album($_REQUEST['id'], $pos, $len);
}
catch (Exception $e)
{
	Exc::log($e, true);
	$response = new stdClass();
	$response->error = $e->getMessage();
}

echo json_encode($response);

?>