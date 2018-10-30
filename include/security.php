<?php

require_once __DIR__ . '/session.php';

define('PERMISSION_EVERYONE', 0x0001);            // Does not even have to login
define('PERMISSION_USER', 0x0002);                // Any logged-in user
define('PERMISSION_OWNER', 0x0004);               // An owner of the object represented by the page or API
define('PERMISSION_CLUB_MEMBER', 0x0008);         // Any user who has a club membership
define('PERMISSION_CLUB_REPRESENTATIVE', 0x0010); // Club users who tread this club as their main club
define('PERMISSION_CLUB_PLAYER', 0x0020);         // Users having player permission in the club
define('PERMISSION_CLUB_MODERATOR', 0x0040);      // Users having moderator permission in the club
define('PERMISSION_CLUB_MANAGER', 0x0080);        // Users having manager permission in the club
define('PERMISSION_LEAGUE_MANAGER', 0x0100);      // Users having manager permission in the league
define('PERMISSION_ADMIN', 0x0200);               // Mafia Ratings administrators

define('PERMISSION_MASK_CLUB', 0x00f8); // PERMISSION_CLUB_MEMBER | PERMISSION_CLUB_REPRESENTATIVE | PERMISSION_CLUB_PLAYER | PERMISSION_CLUB_MODERATOR | PERMISSION_CLUB_MANAGER
define('PERMISSION_MASK_LEAGUE', 0x0100); // PERMISSION_LEAGUE_MANAGER
define('PERMISSION_MASK_OWNER', 0x0004); // PERMISSION_OWNER

// This is a version of check_permissions(..) that is returning false instead of throwing exception
function is_permitted($permissions, $id1 = 0, $id2 = 0, $id3 = 0)
{
	global $_profile;
	
	if (($permissions & PERMISSION_EVERYONE) != 0)
	{
		return true;
	}
	
	if ($_profile == NULL)
	{
		return false;
	}
	
	if ($_profile->is_admin())
	{
		return true;
	}
	
	$club_id = 0;
	$league_id = 0;
	$owner_id = 0;
	if ($permissions & PERMISSION_MASK_CLUB)
	{
		$club_id = $id1;
		if ($permissions & PERMISSION_MASK_LEAGUE)
		{
			$league_id = $id2;
			if ($permissions & PERMISSION_MASK_OWNER)
			{
				$owner_id = $id3;
			}
		}
		else if ($permissions & PERMISSION_MASK_OWNER)
		{
			$owner_id = $id2;
		}
	}
	else if ($permissions & PERMISSION_MASK_LEAGUE)
	{
		$league_id = $id1;
		if ($permissions & PERMISSION_MASK_OWNER)
		{
			$owner_id = $id2;
		}
	}
	else if ($permissions & PERMISSION_MASK_OWNER)
	{
		$owner_id = $id1;
	}
	
	while ($permissions)
	{
		$next_perm = ($permissions & ($permissions - 1));
		switch ($permissions - $next_perm)
		{
			case PERMISSION_USER:
				return true;
				
			case PERMISSION_OWNER:
				if ($owner_id == $_profile->user_id)
				{
					return true;
				}
				break;
				
			case PERMISSION_CLUB_MEMBER:
				if (isset($_profile->clubs[$club_id]))
				{
					return true;
				}
				break;
				
			case PERMISSION_CLUB_REPRESENTATIVE:
				if ($_profile->user_club_id == $club_id)
				{
					return true;
				}
				break;
				
			case PERMISSION_CLUB_PLAYER:
				if ($_profile->is_club_player($club_id))
				{
					return true;
				}
				break;
				
			case PERMISSION_CLUB_MODERATOR:
				if ($_profile->is_club_moder($club_id))
				{
					return true;
				}
				break;
				
			case PERMISSION_CLUB_MANAGER:
				if ($_profile->is_club_manager($club_id))
				{
					return true;
				}
				break;
				
			case PERMISSION_LEAGUE_MANAGER:
				if ($_profile->is_league_manager($league_id))
				{
					return true;
				}
				break;
		}
		$permissions = $next_perm;
	}
	return false;
}

function no_permission()
{
	global $_profile;
	
	if ($_profile == NULL)
	{
		throw new LoginExc(get_label('You do not have enough permissions. Please sign in.'));
	}
	throw new LoginExc(get_label('You do not have enough permissions. Please sign in as a different user.'));
}

// Ids are club_id, league_id, and owner_id depending on what is needed by the permission mask.
// Owner always go last.
// League always go after club.
//
// Examlples:
//
// check_permissions(PERMISSION_OWNER, $user_id); // Where $user_id is the owner of an object protected by this security attribute.
// check_permissions(PERMISSION_CLUB_MEMBER, $club_id);
// check_permissions(PERMISSION_CLUB_MANAGER | PERMISSION_OWNER, $club_id, $user_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, $club_id, $league_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_OWNER, $league_id, $user_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_REPRESENTATIVE |  PERMISSION_OWNER, $club_id, $league_id, $user_id);
function check_permissions($permissions, $id1 = 0, $id2 = 0, $id3 = 0)
{
	if (!is_permitted($permissions, $id1, $id2, $id3))
	{
		no_permission();
	}
}

function permission_name($perm)
{
	switch($perm)
	{
		case PERMISSION_EVERYONE:
			return 'everyone';
		case PERMISSION_USER:
			return 'user';
		case PERMISSION_OWNER:
			return 'object-owner';
		case PERMISSION_CLUB_MEMBER:
			return 'club-member';
		case PERMISSION_CLUB_REPRESENTATIVE:
			return 'club-representative';
		case PERMISSION_CLUB_PLAYER:
			return 'club-player';
		case PERMISSION_CLUB_MODERATOR:
			return 'club-moderator';
		case PERMISSION_CLUB_MANAGER:
			return 'club-manager';
		case PERMISSION_LEAGUE_MANAGER:
			return 'league-manager';
		case PERMISSION_ADMIN:
			return 'admin';
	}
	return '?';
}

?>