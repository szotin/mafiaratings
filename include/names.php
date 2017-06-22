<?php

require_once 'include/db.php';

function cut_long_name($name, $length)
{
	if (mb_strlen($name, 'UTF-8') > $length)
	{
		return mb_substr($name, 0, $length - 3, 'UTF-8') . '...';
	}
	return $name;
}

function is_valid_name($name)
{
	return preg_match('/^([a-zA-Z0-9 _\x80-\xFF-])+$/', $name);
}

function correct_name($name)
{
	return preg_replace("/[^a-zA-Z0-9 _\x80-\xFF-]/", '_', $name);
}

function check_name($name, $in)
{
	if (!is_valid_name($name))
	{
		throw new Exc(get_label('Invalid characters in [0]. Only alphanumeric, spaces, underscores and dashes are allowed.', $in));
	}
}

function check_password($password, $confirm)
{
//	return preg_match("/^.*(?=.{8,})(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).*$/", $password);
	if ($password != $confirm)
	{
		throw new Exc(get_label('Passwords do not match.'));
	}
	
	if (strlen($password) < 4)
	{
		throw new Exc(get_label('Password length must be at least 4 characters.'));
	}
}

function nick_name_chooser($user_id, $user_name, $nickname = NULL)
{
	$nicks = array();
	$query = new DbQuery('SELECT nick_name FROM registrations WHERE user_id = ? GROUP BY nick_name ORDER BY COUNT(*) DESC', $user_id);
	$nicks[] = $user_name;
	$nick = $user_name;
	if ($row = $query->next())
	{
		list ($nick) = $row;
		do
		{
			list ($n) = $row;
			if ($n != $user_name)
			{
				$nicks[] = $n;
			}
		
		} while ($row = $query->next());
	}
	$nicks_count = count($nicks);
	
	if ($nickname != NULL)
	{
		$nick = $nickname;
	}
	
	echo '<input name="nick" id="nick" value="' . $nick . '" onkeyup="nick_changed()">&nbsp';
	echo '<select name="nicks" id="nicks" onchange="nicks_changed()">';
	echo '<option value=""></option>';
	for ($i = 0; $i < $nicks_count; ++$i)
	{
		$n = $nicks[$i];
		if ($n == $nick)
		{
			echo '<option value="' . $n . '" selected>' . cut_long_name($n, 50) . '</option>';
		}
		else
		{
			echo '<option value="' . $n . '">' . cut_long_name($n, 50) . '</option>';
		}
	}
	echo '</select>';
?>
	<script language="JavaScript" type="text/javascript">
	<!--
		function nick_changed()
		{
			$('#nicks option[value=]').attr('selected', 'selected');
		}
		
		function nicks_changed()
		{
			$('#nick').val($('#nicks').val());
		}
	//-->
	</script>
<?php
}

function check_nickname($nick, $event_id)
{
	global $_profile;
	
	if ($nick == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('nick-name')));
	}

	check_name($nick, get_label('nick-name'));
	if ($event_id > 0)
	{
		list ($count) = Db::record(get_label('registration'), 'SELECT count(*) FROM registrations WHERE event_id = ? AND nick_name = ?', $event_id, $nick);
	}
	else
	{
		list ($count) = Db::record(
			get_label('registration'), 
			'SELECT count(*) FROM registrations WHERE event_id IS NULL AND club_id = ? AND nick_name = ? AND UNIX_TIMESTAMP() < start_time + duration AND UNIX_TIMESTAMP() >= start_time', -$event_id, $nick);
	}
	if ($count > 0)
	{
		throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('nick-name'), $nick));
	}
}

function check_user_name($name)
{
	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('user name')));
	}

	check_name($name, get_label('user name'));
	
	$query = new DbQuery('SELECT name FROM users WHERE name = ?', $name);
	if ($query->next())
	{
        throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('user name'), $name));
	}
}

function check_club_name($name, $club_id = -1)
{
	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('club name')));
	}

	check_name($name, get_label('club name'));

	if ($club_id > 0)
	{
		$query = new DbQuery('SELECT name FROM clubs WHERE name = ? AND id <> ?', $name, $club_id);
	}
	else
	{
		$query = new DbQuery('SELECT name FROM clubs WHERE name = ?', $name);
	}
	if ($query->next())
	{
        throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('club name'), $name));
	}
}

function check_address_name($name, $club_id, $address_id = -1)
{
	global $_profile;

	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('address name')));
	}

	if ($address_id > 0)
	{
		$query = new DbQuery('SELECT name FROM addresses WHERE name = ? AND club_id = ? AND id <> ?', $name, $club_id, $address_id);
	}
	else
	{
		$query = new DbQuery('SELECT name FROM addresses WHERE name = ? AND club_id = ?', $name, $club_id);
	}
	if ($query->next())
	{
        throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('address name'), $name));
	}
}

function check_email_template_name($name, $club_id, $template_id = -1)
{
	global $_profile;

	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('email template name')));
	}

	check_name($name,get_label('email template name'));

	if ($template_id > 0)
	{
		$query = new DbQuery('SELECT name FROM email_templates WHERE name = ? AND club_id = ? AND id <> ?', $name, $club_id, $template_id);
	}
	else
	{
		$query = new DbQuery('SELECT name FROM email_templates WHERE name = ? AND club_id = ?', $name, $club_id);
	}
	if ($query->next())
	{
        throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('email template name'), $name));
	}
}

function check_scoring_system_name($name, $club_id, $id = -1)
{
	global $_profile;

	if ($name == '')
	{
		throw new Exc(get_label('Please enter [0].', get_label('scoring system name')));
	}

	check_name($name, get_label('scoring system name'));

	if ($id > 0)
	{
		$query = new DbQuery('SELECT name FROM scoring_systems WHERE name = ? AND (club_id = ? OR club_id IS NULL) AND id <> ?', $name, $club_id, $id);
	}
	else
	{
		$query = new DbQuery('SELECT name FROM scoring_systems WHERE name = ? AND (club_id = ? OR club_id IS NULL)', $name, $club_id);
	}
	if ($query->next())
	{
        throw new Exc(get_label('The [0] "[1]" is already used. Please try another one.', get_label('scoring system name'), $name));
	}
}

?>