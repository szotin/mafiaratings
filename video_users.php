<?php

require_once 'include/session.php';
require_once 'include/user.php';

define('COLUMN_COUNT', 12);
define('COLUMN_WIDTH', (100 / COLUMN_COUNT));

initiate_session();

try
{
	if (!isset($_REQUEST['id']))
	{
		throw new Exception(get_label('Unknown [0]', get_label('video')));
	}
	$video_id = (int)$_REQUEST['id'];
	
	list($game_id, $club_id, $user_id) = 
			Db::record(get_label('video'), 
				'SELECT g.id, v.club_id, v.user_id FROM videos v ' .
				' LEFT OUTER JOIN games g ON g.video_id = v.id' .
				' WHERE v.id = ?', $video_id);
	
	$can_manage = ($_profile != NULL && ($_profile->user_id == $user_id || $_profile->is_manager($club_id)));
	
	echo '<table class="bordered light" width="100%">';
	echo '<tr><td class="darker" colspan="' . COLUMN_COUNT . '">' . get_label('On this video: ');
	if ($can_manage && $game_id == NULL)
	{
		echo '<input type="text" id="tag_user" title="' . get_label('Tag a user on this video.') . '"/>';
	}
	echo '</td></tr>';
	
	$remaining_columns = 0;
	if ($game_id == NULL)
	{
		$query = new DbQuery('SELECT u.id, u.name, u.flags, v.tagged_by_id FROM user_videos v JOIN users u ON u.id = v.user_id WHERE v.video_id = ? ORDER BY u.name', $video_id);
	}
	else
	{
		$query = new DbQuery(
			'(SELECT u.id, u.name as name, u.flags, NULL FROM players p JOIN users u ON u.id = p.user_id WHERE p.game_id = ?) UNION ' .
			'(SELECT u.id, u.name as name, u.flags, NULL FROM games g  JOIN users u ON u.id = g.moderator_id WHERE g.id = ?) ORDER BY name', $game_id, $game_id);
	}
	while ($row = $query->next())
	{
		if ($remaining_columns <= 0)
		{
			$remaining_columns = COLUMN_COUNT;
			echo '<tr>';
		}
		--$remaining_columns;
		
		list ($user_id, $user_name, $user_flags, $tagged_by) = $row;
		echo '<td width="' . COLUMN_WIDTH . '%" align="center"><a href="user_info.php?bck=1&id=' . $user_id . '">';
		show_user_pic($user_id, $user_name, $user_flags, ICONS_DIR, 48, 48);
		echo '<br>' . $user_name;
		echo '</a></td>';
	}
	if ($remaining_columns > 0)
	{
		echo '<td colspan="' . $remaining_columns . '" width="' . (COLUMN_WIDTH * $remaining_columns) . '%"></td>';
	}
	echo '</tr></table><ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>