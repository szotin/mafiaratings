<?php

require_once 'include/page_base.php';
require_once 'include/player_stats.php';
require_once 'include/club.php';
require_once 'include/languages.php';
require_once 'include/address.php';
require_once 'include/pages.php';
require_once 'include/photo_album.php';

define("PAGE_SIZE",30);

class Page extends AddressPageBase
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
		
		$condition = new SQL('a.event_id IN (SELECT id FROM events WHERE address_id = ?)', $this->id);
		PhotoAlbum::show_list($condition, NULL, $this->link_params, ALBUM_SHOW_OWNER);
	}
}

$page = new Page();
$page->run(get_label('Photo Albums'));

?>