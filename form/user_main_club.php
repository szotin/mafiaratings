<?php

require_once '../include/session.php';
require_once '../include/user.php';

initiate_session();

try
{
	if (!isset($_REQUEST['user_id']))
	{
		throw new Exc(get_label('Unknown [0]', get_label('user')));
	}
	$user_id = (int)$_REQUEST['user_id'];
	list($club_id) = Db::record(get_label('user'), 'SELECT club_id FROM users WHERE id = ?', $user_id);
	if (is_null($club_id))
	{
		$club_id = 0;
	}

	// Someone editing another user's account can do it only as a manager of that user's main club.
	// Choosing a main club they do not manage, or none, takes the right to edit the account away from them.
	$keeps_rights = !is_null($_profile) && ($_profile->user_id == $user_id || $_profile->is_admin());

	list($membership_count) = Db::record(get_label('membership'), 'SELECT count(*) FROM club_regs WHERE user_id = ?', $user_id);
	if ($membership_count > 0)
	{
		// The clubs the user is a member of. The current main club is listed even if the user is not a member of it, so the select shows what is actually set.
		$query = new DbQuery('SELECT c.id, n.name FROM clubs c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE c.id IN (SELECT club_id FROM club_regs WHERE user_id = ?) OR c.id = ? ORDER BY n.name', $user_id, $club_id);
	}
	else
	{
		// Not a member of any club: choosing one makes the user its member.
		$query = new DbQuery('SELECT c.id, n.name FROM clubs c JOIN names n ON n.id = c.name_id AND (n.langs & '.$_lang.') <> 0 WHERE (c.flags & ' . CLUB_FLAG_CLOSED . ') = 0 OR c.id = ? ORDER BY n.name', $club_id);
	}

	echo '<select id="form-main-club" onChange="setMainClub()">';
	echo '<option value="0"' . ($club_id == 0 ? ' selected' : '') . ($keeps_rights ? '' : ' data-lose="1"') . '></option>';
	while ($row = $query->next())
	{
		list ($c_id, $c_name) = $row;
		$c_id = (int)$c_id;
		$loses_rights = !$keeps_rights && !$_profile->is_club_manager($c_id);
		echo '<option value="' . $c_id . '"' . ($c_id == $club_id ? ' selected' : '') . ($loses_rights ? ' data-lose="1"' : '') . '>' . htmlspecialchars($c_name, ENT_QUOTES) . '</option>';
	}
	echo '</select>';
}
catch (Exception $e)
{
	Exc::log($e);
	echo $e->getMessage();
}

?>
