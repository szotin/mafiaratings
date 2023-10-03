<?php

require_once 'include/user.php';
require_once 'include/message.php';

// It is not used but I leave it as an example of using objections

class Page extends PageBase
{
	protected function show_body()
	{
		global $_lang;
		
		if (!isset($_REQUEST['id']))
		{
			throw new Exc(get_label('Unknown [0]', get_label('game')));
		}
		$is_empty = true;
		$game_id = (int)$_REQUEST['id'];
		list($owner_id, $club_id, $event_id, $tournament_id, $timezone) = Db::record(get_label('game'), 'SELECT g.user_id, g.club_id, g.event_id, g.tournament_id, c.timezone FROM games g JOIN events e ON e.id = g.event_id JOIN addresses a ON a.id = e.address_id JOIN cities c ON c.id = a.city_id WHERE g.id = ?', $game_id);
		
		echo '<table class="bordered light" width="100%">';
		echo '<tr class="darker">';
		if (is_permitted(PERMISSION_USER))
		{
			echo '<td width="84"><button class="icon" onclick="mr.createObjection(' . $game_id . ')" title="' . get_label('File an objection to the game [0] results.', $game_id) . '"><img src="images/objection.png" border="0"></button></td>';
		}
		echo '<td colspan="2"></td></tr>';
		// echo '<td width="80">' . get_label('User') . '</td>';
		// echo '<td>' . get_label('Reason') . '</td>';
		
		$query = new DbQuery(
			'SELECT o.id, o.timestamp, o.objection_id, u.id, nu.name, u.flags, o.message'.
			' FROM objections o'.
			' JOIN users u ON u.id = o.user_id'.
			' JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0'.
			' WHERE o.game_id = ?'.
			' ORDER BY o.timestamp', $game_id);
		while ($row = $query->next())
		{
			list ($objection_id, $timestamp, $parent_id, $user_id, $user_name, $user_flags, $message) = $row;
			echo '<tr';
			if (is_null($parent_id))
			{
				echo ' class="dark"';
			}
			echo '>';
			
			if (is_permitted(PERMISSION_USER))
			{
				echo '<td valign="center">';
				if (is_permitted(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER | PERMISSION_EVENT_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $owner_id, $club_id, $event_id, $tournament_id))
				{
					if (is_null($parent_id))
					{
						echo '<button class="icon" onclick="mr.deleteObjection(' . $objection_id . ', \'' . get_label('Are you sure you want to delete objection #[0]?', $objection_id) . '\')" title="' . get_label('Delete objection #[0].', $objection_id) . '"><img src="images/delete.png" border="0"></button>';
						echo '<button class="icon" onclick="mr.editObjection(' . $objection_id . ')" title="' . get_label('Edit objection #[0].', $objection_id) . '"><img src="images/edit.png" border="0"></button>';
						echo '<button class="icon" onclick="mr.replyObjection(' . $objection_id . ')" title="' . get_label('Reply to the objection #[0].', $objection_id) . '"><img src="images/reply.png" border="0"></button>';
					}
					else
					{
						echo '<button class="icon" onclick="mr.deleteObjection(' . $objection_id . ', \'' . get_label('Are you sure you want to delete response to the objection #[0]?', $parent_id) . '\')" title="' . get_label('Delete response to the objection #[0].', $objection_id) . '"><img src="images/delete.png" border="0"></button>';
						echo '<button class="icon" onclick="mr.editObjection(' . $objection_id . ')" title="' . get_label('Edit response to the objection #[0].', $parent_id) . '"><img src="images/edit.png" border="0"></button>';
					}
				}
				echo '</td>';
			}
			
			echo '<td width="80" align="center" valign="top">';
			$this->user_pic->set($user_id, $user_name, $user_flags);
			$this->user_pic->show(ICONS_DIR, true, 48);
			echo '<br>' . $user_name . '</td>';
			
			$message = stripslashes($message);
			$message = htmlspecialchars($message, ENT_QUOTES, "UTF-8");
			$message = replace_returns($message);
			
			echo '<td valign="top"><p><b>';
			if (is_null($parent_id))
			{
				echo get_label('Objection #[0]', $objection_id);
			}
			else
			{
				echo get_label('Responce to the objection #[0]', $parent_id);
			}
			echo '</b><br><i>' . format_date('l, F d, Y, H:i', $timestamp, $timezone) . '</i></p><p>' . $message . '</p></td></tr>';
			$is_empty = false;
		}
		echo '</table>';
		
		parent::show_body();
		
		if ($is_empty && isset($_REQUEST['auto']))
		{
			echo '<script>mr.createObjection(' . $game_id . ');</script>';
		}
	}
}

$page = new Page();
$page->run(get_label('Objections'));

?>