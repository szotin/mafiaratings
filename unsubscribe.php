<?php

require_once 'include/page_base.php';

class Page extends PageBase
{
	private $message;
	private $clubs;

	protected function prepare()
	{
		global $_profile;
		
		check_permissions(PERMISSION_USER);
		if ($_profile->is_admin())
		{
			$this->clubs = array();
			$query = new DbQuery('SELECT c.id, c.name, u.flags FROM user_clubs u JOIN clubs c ON c.id = u.club_id WHERE u.user_id = ?', $_profile->user_id);
			while ($row = $query->next())
			{
				$club = new ProfileClub();
				list ($club->id, $club->name, $club->flags) = $row;
				$this->clubs[] = $club;
			}
		}
		else
		{
			$this->clubs = $_profile->clubs;
		}
		
		if (isset($_REQUEST['save']))
		{
			foreach ($this->clubs as $club)
			{
				if (isset($_REQUEST['club' . $club->id]))
				{
					if (($club->flags & USER_CLUB_FLAG_SUBSCRIBED) == 0)
					{
						Db::begin();
						Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags | ' . USER_CLUB_FLAG_SUBSCRIBED . ') WHERE user_id = ? AND club_id = ?', $_profile->user_id, $club->id);
						if (Db::affected_rows() > 0)
						{
							db_log('user', 'Subscribed', NULL, $_profile->user_id, $club->id);
						}
						$club->flags |= USER_CLUB_FLAG_SUBSCRIBED;
						Db::commit();
					}
				}
				else if (($club->flags & USER_CLUB_FLAG_SUBSCRIBED) != 0)
				{
					Db::begin();
					Db::exec(get_label('user'), 'UPDATE user_clubs SET flags = (flags & ~' . USER_CLUB_FLAG_SUBSCRIBED . ') WHERE user_id = ? AND club_id = ?', $_profile->user_id, $club->id);
					if (Db::affected_rows() > 0)
					{
						db_log('user', 'Unsubscribed', NULL, $_profile->user_id, $club->id);
					}
					$club->flags &= ~USER_CLUB_FLAG_SUBSCRIBED;
					Db::commit();
				}
			}
			
			$flags = 0;
			if (isset($_REQUEST['mnot']))
			{
				$flags |= USER_FLAG_MESSAGE_NOTIFY;
			}
			if (isset($_REQUEST['pnot']))
			{
				$flags |= USER_FLAG_PHOTO_NOTIFY;
			}
			if (($_profile->user_flags & (USER_FLAG_PHOTO_NOTIFY | USER_FLAG_MESSAGE_NOTIFY)) != $flags)
			{
				Db::begin();
				Db::exec(get_label('user'), 'UPDATE users SET flags = ((flags & ~' . (USER_FLAG_PHOTO_NOTIFY | USER_FLAG_MESSAGE_NOTIFY) . ') | ' . $flags . ') WHERE id = ?', $_profile->user_id);
				$_profile->user_flags &= ~(USER_FLAG_PHOTO_NOTIFY | USER_FLAG_MESSAGE_NOTIFY);
				$_profile->user_flags |= $flags;
				if (Db::affected_rows() > 0)
				{
					db_log('user', 'Changed subscription', 'flags=' . $_profile->user_flags, $_profile->user_id);
				}
				Db::commit();
			}
			$this->message = get_label('Profile is saved.');
		}
	}
	
	protected function show_body()
	{
		global $_profile;
		
		echo '<form method="post" action="unsubscribe.php">';
		echo '<table class="bordered" width="100%">';
		if ($this->message != NULL)
		{
			echo '<tr><td class="light">' . $this->message . '</td></tr>';
		}
		
		echo '<tr><td>';
		foreach ($this->clubs as $club)
		{
			echo '<input type="checkbox" name="club' . $club->id . '"';
			if (($club->flags & USER_CLUB_FLAG_SUBSCRIBED) != 0)
			{
				echo ' checked';
			}
			echo '>' . get_label('I would like to receive emails about upcoming events in [0].', $club->name) . '<br>';
		}
		echo '</td></tr>';
		
		echo '<tr><td><input type="checkbox" name="mnot"';
		if (($_profile->user_flags & USER_FLAG_MESSAGE_NOTIFY) != 0)
		{
			echo ' checked';
		}
		echo '>' . get_label('I would like to receive emails when someone replies to me or sends me a private message.') . '<br>';
		
		echo '<input type="checkbox" name="pnot"';
		if (($_profile->user_flags & USER_FLAG_PHOTO_NOTIFY) != 0)
		{
			echo ' checked';
		}
		echo '>' . get_label('I would like to receive emails when someone tags me on a photo.') . '</td></tr>';
		
		echo '</table><input type="submit" name="save" value="' . get_label('Save') . '" class="btn norm"></form>';
	}
}

$page = new Page();
$page->run(get_label('Email subscription'));

?>