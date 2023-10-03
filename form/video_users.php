<?php

require_once '../include/session.php';
require_once '../include/user.php';

define('COLUMN_COUNT', 11);
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
				
	$can_manage = false;
	$self_id = 0;
	if ($_profile != NULL)
	{
		$self_id = $_profile->user_id;
		$can_manage = ($self_id == $user_id || $_profile->is_club_manager($club_id));
	}
	
	
	echo '<table class="bordered light" width="100%">';
	if ($can_manage && $game_id == NULL)
	{
		echo '<tr><td class="darker" colspan="' . COLUMN_COUNT . '">' . get_label('On this video: ');
		echo '<input type="text" id="tag_user" title="' . get_label('Tag a user on this video.') . '"/>';
		echo '</td></tr>';
		$show_title = false;
	}
	else
	{
		$show_title = true;
	}
	
	$user_pic = new Picture(USER_PICTURE);
	
	$remaining_columns = 0;
	if ($game_id == NULL)
	{
		$query = new DbQuery(
			'SELECT u.id, nu.name, u.flags, v.tagged_by_id, 0'.
			' FROM user_videos v'.
			' JOIN users u ON u.id = v.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE v.video_id = ?'.
			' ORDER BY nu.name', $video_id);
	}
	else
	{
		$query = new DbQuery(
			'(SELECT u.id, nu.name as name, u.flags, NULL, p.number as number'.
			' FROM players p'.
			' JOIN users u ON u.id = p.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE p.game_id = ?)'.
			' UNION ' .
			'(SELECT u.id, nu.name as name, u.flags, NULL, 0 as number'.
			' FROM games g'.
			' JOIN users u ON u.id = g.moderator_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE g.id = ?)'.
			' ORDER BY number', $game_id, $game_id);
	}
	while ($row = $query->next())
	{
		if ($show_title)
		{
			echo '<tr><td class="darker" colspan="' . COLUMN_COUNT . '">' . get_label('On this video: ') . '</td></tr>';
			$show_title = false;
		}
		if ($remaining_columns <= 0)
		{
			$remaining_columns = COLUMN_COUNT;
			echo '<tr>';
		}
		--$remaining_columns;
		
		list ($user_id, $user_name, $user_flags, $tagged_by, $number) = $row;
		
		echo '<td width="' . COLUMN_WIDTH . '%" align="center" valign="top">';
		$can_untag = false;
		if ($game_id == NULL)
		{
			$can_untag = ($can_manage || $user_id == $self_id);
		}
		else
		{
			echo '<p><b>';
			if ($number > 0)
			{
				echo $number;
			}
			else
			{
				echo get_label('Referee');
			}
			echo '</b></p>';
		}
		
		if ($can_untag)
		{
			echo '<table class="transp" width="100%"><tr><td align="center">';
		}
		
		echo '<p><a href="user_info.php?bck=1&id=' . $user_id . '">';
		$user_pic->set($user_id, $user_name, $user_flags);
		$user_pic->show(ICONS_DIR, false, 48);
		echo '<br>' . $user_name;
		echo '</a>';
		if ($can_untag)
		{
			echo '</tr><tr><td align="center">';
			echo '<br><button class="icon" onclick="mr.untagVideo(' . $user_id . ', ' . $video_id . ')" title="' . get_label('Untag [0]', $user_name) . '"><img src="images/delete.png" border="0"></button>';
			echo '</td></tr></table>';
		}
		echo '</p></td>';
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
	echo '<error=' . $e->getMessage() . '>';
}

?>