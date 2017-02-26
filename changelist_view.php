<?php

require_once 'include/session.php';
require_once 'include/game_state.php';

initiate_session();

try
{
	dialog_title(get_label('Changelist'));
	
	if (!isset($_REQUEST['id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('changelist')));
	}
	$id = $_REQUEST['id'];
	
	list($data_str) = Db::record(get_label('changelist'), 'SELECT data FROM changelists WHERE id = ?', $id);
	$data = json_decode(str_replace('\"', '"', $data_str));
	
	echo '<table class="dialog_form" width="100%">';
	if (isset($data->data))
	{
		foreach ($data->data as $rec)
		{
			if ($rec->action == 'new-event')
			{
				$str = get_label('New event [0] [1]', $rec->name, format_date('M j Y, H:i', $rec->start, $_profile->timezone));
			}
			else if ($rec->action == 'reg')
			{
				list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $rec->id);
				if ($user_name != $rec->nick)
				{
					$user_name = $rec->nick . ' (' . $user_name . ')';
				}
				$str = get_label('Player [0] registered', $user_name);
			}
			else if ($rec->action == 'reg-incomer')
			{
				if ($rec->id > 0)
				{
					list($user_name) = Db::record(get_label('user'), 'SELECT name FROM users WHERE id = ?', $rec->id);
					if ($user_name != $rec->nick)
					{
						$user_name = $rec->nick . ' (' . $user_name . ')';
					}
				}
				else
				{
					$user_name = $rec->nick;
				}
				$str = get_label('Non-member player [0] registered', $user_name);
			}
			else if ($rec->action == 'new-user')
			{
				$str = get_label('Player [0] created and registered', $rec->name);
			}
			else if ($rec->action == 'submit-game')
			{
				$str = get_label('Game finished');
			}
			else if ($rec->action == 'extend-event')
			{
				$str = get_label('Event extended');
			}
			else
			{
				$str = get_label('Unknown action');
			}
			
			$time = '&nbsp;';
			if (isset($rec->time))
			{
				$time = format_date('M j Y, H:i', $rec->time, $_profile->timezone);
			}
			
			echo '<tr><td width="180">' . $time . '</td><td>' . $str . '</td></tr>';
		}
	}
	
	if ($data->game->gamestate != GAME_NOT_STARTED)
	{
		echo '<tr><td width="180">&nbsp;</td><td>' . get_label('Unfinished game.') . '</td></tr>';
	}
	echo '</table>';
	
	echo '<ok>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>