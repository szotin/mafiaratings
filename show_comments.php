<?php

require_once 'include/session.php';
require_once 'include/user.php';

initiate_session();

try
{
	$object_type = NULL;
	if (isset($_REQUEST['event']))
	{
		$object_type = 'event';
		$id = $_REQUEST['event'];
	}
	else if (isset($_REQUEST['tournament']))
	{
		$object_type = 'tournament';
		$id = $_REQUEST['tournament'];
	}
	else if (isset($_REQUEST['photo']))
	{
		$object_type = 'photo';
		$id = $_REQUEST['photo'];
	}
	else if (isset($_REQUEST['game']))
	{
		$object_type = 'game';
		$id = $_REQUEST['game'];
	}
	else if (isset($_REQUEST['video']))
	{
		$object_type = 'video';
		$id = $_REQUEST['video'];
	}
	else
	{
		throw new Exception(get_label('Unknown [0]', get_label('object')));
	}

	$limit = 5;
	if (isset($_REQUEST['limit']))
	{
		$limit = (int)$_REQUEST['limit'];
		if ($limit <= 0)
		{
			$limit = 5;
		}
	}
	
	$edit_class = "comment";
	if (isset($_REQUEST['class']))
	{
		$edit_class = $_REQUEST['class'];
	}
	
	$show_all = isset($_REQUEST['all']);
	$no_content = true;
	$more_than_max = false;
	
	list ($count) = Db::record(get_label('comment'), 'SELECT count(*) FROM ' . $object_type . '_comments WHERE ' . $object_type . '_id = ?', $id);
	$more_than_max = ($count > $limit);
	
	echo '<table class="bordered" width="100%">'; 
	echo '<tr><td><table width="100%" class="transp light">';
	if ($show_all || !$more_than_max)
	{
		$query = new DbQuery(
			'SELECT c.user_id, nu.name, u.flags, c.comment, c.time, c.lang'.
			' FROM ' . $object_type . '_comments c'.
			' JOIN users u ON u.id = c.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE c.' . $object_type . '_id = ?'.
			' ORDER BY c.time', $id);
	}
	else
	{
		$query = new DbQuery(
			'SELECT * FROM ('.
			'SELECT c.user_id, nu.name, u.flags, c.comment, c.time as time, c.lang'.
			' FROM ' . $object_type . '_comments c'.
			' JOIN users u ON u.id = c.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE c.' . $object_type . '_id = ?'.
			' ORDER BY c.time'.
			' DESC LIMIT ' . $limit . ') sub'.
			' ORDER BY time', $id);
	}
	
	if ($more_than_max && !$show_all)
	{
		echo '<tr><td colspan="2" style="padding:10px;">';
		echo '<a href="javascript:mr.showComments(\'' . $object_type . '\', ' . $id . ', ' . $limit . ', true)">' . get_label('View all comments') . '</a>';
		echo '</td></tr>';
	}
	
	$user_pic = new Picture(USER_PICTURE);
	$class = 'darker';
	$other_class = 'dark';
	while ($row = $query->next())
	{
		list ($user_id, $user_name, $user_flags, $comment, $time, $lang) = $row;
		echo '<tr class="' . $class . '">';
		echo '<td width="32" valign="top" style="padding:5px;" class="' . $class . '" align="center">';
		$user_pic->set($user_id, $user_name, $user_flags);
		$user_pic->show(ICONS_DIR, true, 32);
		echo '</td>';
		
		echo '<td width="100%" valign="top" style="padding:8px;">';
		echo '<a href="user_info.php?id=' . $user_id . '&bck=1">' .  $user_name . '</a>: '; // . ', ' . format_date($time, $timezone, true);
		echo $comment;
		echo '</td></tr>';
		$no_content = false;
		
		$tmp = $other_class;
		$other_class = $class;
		$class = $tmp;
	}
	
	if ($more_than_max && $show_all)
	{
		echo '<tr><td colspan="2" style="padding:10px;">';
		echo '<a href="javascript:mr.showComments(\'' . $object_type . '\', ' . $id . ', ' . $limit . ')">' . get_label('Hide older comments') . '</a>';
		echo '</td></tr>';
	}
	
	echo '<tr class=' . $class . '><td width="32" valign="top" class="darker" align="center">';
	if ($_profile != NULL)
	{
		$user_pic->set($_profile->user_id, $_profile->user_name, $_profile->user_flags);
		$user_pic->show(ICONS_DIR, false, 32);
	}
	echo '</td><td style="padding:5px;"><textarea class="' . $edit_class . '" id="comment" onkeyup="mr.checkCommentArea()" placeholder="' . get_label('Write a comment...') . '"></textarea></td><tr>';
	
	echo '</table></td></tr></table><ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo '<error=' . $e->getMessage() . '>';
}

?>