<?php

require_once 'include/event.php';
require_once 'include/club.php';
require_once 'include/pages.php';
require_once 'include/video.php';

class Page extends PageBase
{
	protected function show_body()
	{
		$query = new DbQuery('SELECT g.id, v.id, v.video FROM games g JOIN videos v ON v.id = g.video_id WHERE v.name = \'\'');
		while ($row = $query->next())
		{
			list ($game_id, $video_id, $video) = $row;
			$title = get_youtube_info($video)['title'];
			
			Db::exec(get_label('video'), 'UPDATE videos SET name = ? WHERE id = ?', $title, $video_id);
			echo $game_id . ': video = ' . $video . '; title = ' . $title . '<br>';
		}
	}
}

$page = new Page();
$page->run('Update videos', PERM_ALL);

?>