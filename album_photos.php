<?php

require_once 'include/page_base.php';
require_once 'include/photo_album.php';

class Page extends AlbumPageBase
{
	protected function prepare()
	{
		parent::prepare();
	}
	
	protected function show_body()
	{
		PhotoAlbum::show_thumbnails(new SQL('WHERE p.album_id = ?', $this->album->id), '&album=' . $this->album->id);
	}
}

$page = new Page();
$page->run(get_label('Photos'), PERM_ALL);

?>