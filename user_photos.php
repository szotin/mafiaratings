<?php

require_once 'include/user.php';
require_once 'include/pages.php';
require_once 'include/photo_album.php';

class Page extends UserPageBase
{
	protected function show_body()
	{
		PhotoAlbum::show_thumbnails(new SQL('JOIN user_photos u ON u.photo_id = p.id WHERE u.tag = TRUE AND u.user_id = ?', $this->id), '&user=' . $this->id);
	}
}

$page = new Page();
$page->run(get_label('Photos'));

?>