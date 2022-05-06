<?php

require_once 'include/event.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

class Page extends EventPageBase
{
	private $link_params;

	protected function prepare()
	{
		parent::prepare();
		$this->link_params = array('id' => $this->event->id);
		PhotoAlbum::prepare_list($this->link_params);
	}

	protected function show_body()
	{
		$create_link = NULL;
		if (is_permitted(PERMISSION_CLUB_MEMBER | PERMISSION_EVENT_MANAGER | PERMISSION_EVENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->event->club_id, $this->event->id, $this->event->tournament_id))
		{
			$create_link = 'create_album.php?bck=1&club=' . $this->event->club_id . '&event=' . $this->event->id;
		}
		
		$condition = new SQL('a.event_id = ?', $this->event->id);
		PhotoAlbum::show_list($condition, $create_link, $this->link_params, ALBUM_SHOW_OWNER);
	}
}

$page = new Page();
$page->run(get_label('Photo Albums'));

?>