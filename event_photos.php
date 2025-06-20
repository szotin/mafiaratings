<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/image.php';
require_once 'include/pages.php';
require_once 'include/photo_album.php';

class Page extends EventPageBase
{
	protected function show_body()
	{
		PhotoAlbum::show_thumbnails(new SQL('WHERE p.album_id = a.id AND a.event_id = ?', $this->id), '&event=' . $this->id);
	}
}

$page = new Page();
$page->run(get_label('Photos'));

?>