<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/club.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

define("CUT_NAME",45);

class Page extends UserPageBase
{
	private $link_params;

	protected function prepare()
	{
		parent::prepare();
		$this->link_params = array('id' => $this->id);
		PhotoAlbum::prepare_list($this->link_params);
	}
	
	protected function show_body()
	{
		global $_profile;
		
		$create_link = NULL;
		if ($_profile != NULL && $_profile->user_id == $this->id)
		{
			$create_link = 'create_album.php?bck=1';
		}
		
		$condition = new SQL('a.user_id = ?', $this->id);
		PhotoAlbum::show_list($condition, $create_link, $this->link_params, ALBUM_SHOW_CLUB);
	}
}

$page = new Page();
$page->run(get_label('Photo Albums'));

?>