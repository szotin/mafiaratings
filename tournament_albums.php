<?php

require_once 'include/tournament.php';
require_once 'include/pages.php';
require_once 'include/image.php';
require_once 'include/photo_album.php';

class Page extends TournamentPageBase
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
		if (is_permitted(PERMISSION_CLUB_MEMBER | PERMISSION_TOURNAMENT_MANAGER | PERMISSION_TOURNAMENT_REFEREE, $this->club_id, $this->id))
		{
			$create_link = 'create_album.php?bck=1&club=' . $this->club_id . '&tournament=' . $this->id;
		}
		
		$condition = new SQL('a.tournament_id = ?', $this->id);
		PhotoAlbum::show_list($condition, $create_link, $this->link_params, ALBUM_SHOW_OWNER);
	}
}

$page = new Page();
$page->run(get_label('Photo Albums'));

?>