<?php

require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

class Page extends ClubPageBase
{
	private $link_params;

	protected function prepare()
	{
		parent::prepare();
		$this->link_params = array('id' => $this->id);
		PhotoAlbum::prepare_list($this->link_params);
		$this->_title = get_label('[0] photo albums', $this->name);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$create_link = NULL;
		if ($_profile != NULL && isset($_profile->clubs[$this->id]))
		{
			$create_link = 'create_album.php?bck=1&club=' . $this->id;
		}
		
		$condition = new SQL('a.club_id = ?', $this->id);
		PhotoAlbum::show_list($condition, $create_link, $this->link_params, ALBUM_SHOW_OWNER);
	}
}

$page = new Page();
$page->run('', PERM_ALL);

?>