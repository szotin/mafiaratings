<?php

require_once __DIR__ . '/session.php';

define('PERMISSION_EVERYONE',            0x000001); // Does not even have to login
define('PERMISSION_USER',                0x000002); // Any logged-in user
define('PERMISSION_OWNER',               0x000004); // An owner of the object represented by the page or API
define('PERMISSION_CLUB_MEMBER',         0x000008); // Any user who has a club membership
define('PERMISSION_CLUB_REPRESENTATIVE', 0x000010); // Club users who tread this club as their main club
define('PERMISSION_CLUB_PLAYER',         0x000020); // Users having player permission in the club
define('PERMISSION_CLUB_REFEREE',        0x000040); // Users having referee permission in the club
define('PERMISSION_CLUB_MANAGER',        0x000080); // Users having manager permission in the club
define('PERMISSION_LEAGUE_MANAGER',      0x000100); // Users having manager permission in the league
define('PERMISSION_EVENT_PLAYER',        0x000200); // Users having player permission in the event
define('PERMISSION_EVENT_REFEREE',       0x000400); // Users having referee permission for the event
define('PERMISSION_EVENT_MANAGER',       0x000800); // Users having manager permission for the event
define('PERMISSION_TOURNAMENT_PLAYER',   0x001000); // Users having player permission in the tournament
define('PERMISSION_TOURNAMENT_REFEREE',  0x002000); // Users having referee permission for the tournament
define('PERMISSION_TOURNAMENT_MANAGER',  0x004000); // Users having manager permission for the tournament
define('PERMISSION_SERIES_PLAYER',       0x008000); // Users having player permission in the series
define('PERMISSION_SERIES_REFEREE',      0x010000); // Users having referee permission for the series
define('PERMISSION_SERIES_MANAGER',      0x020000); // Users having manager permission for the series
define('PERMISSION_ADMIN',               0x040000); // Mafia Ratings administrators

define('PERMISSION_MASK_CLUB',           0x0000f8); // PERMISSION_CLUB_MEMBER | PERMISSION_CLUB_REPRESENTATIVE | PERMISSION_CLUB_PLAYER | PERMISSION_CLUB_REFEREE | PERMISSION_CLUB_MANAGER
define('PERMISSION_MASK_LEAGUE',         0x000100); // PERMISSION_LEAGUE_MANAGER
define('PERMISSION_MASK_EVENT',          0x000f00); // PERMISSION_EVENT_PLAYER | PERMISSION_EVENT_REFEREE | PERMISSION_EVENT_MANAGER
define('PERMISSION_MASK_TOURNAMENT',     0x00f000); // PERMISSION_TOURNAMENT_PLAYER | PERMISSION_TOURNAMENT_REFEREE | PERMISSION_TOURNAMENT_MANAGER
define('PERMISSION_MASK_SERIES',         0x031000); // PERMISSION_SERIES_PLAYER | PERMISSION_SERIES_REFEREE | PERMISSION_SERIES_MANAGER
define('PERMISSION_MASK_OWNER',          0x000004); // PERMISSION_OWNER

define('PERMISSION_OFFSET_EVENT', 9);
define('PERMISSION_OFFSET_TOURNAMENT', 12);
define('PERMISSION_OFFSET_SERIES', 15);

function get_profile_event_permissions($event_id)
{
	global $_profile;
	
	if ($_profile != NULL)
	{
		if ($_profile->is_admin())
		{
			return PERMISSION_MASK_EVENT;
		}

		if (is_numeric($event_id) && $event_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM event_users WHERE event_id = ? AND user_id = ?', $event_id, $_profile->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags << PERMISSION_OFFSET_EVENT) & PERMISSION_MASK_EVENT;
			}
		}
	}
	return 0;
}

function get_profile_tournament_permissions($tournament_id)
{
	global $_profile;
	
	if ($_profile != NULL)
	{
		if ($_profile->is_admin())
		{
			return PERMISSION_MASK_TOURNAMENT;
		}

		if (is_numeric($tournament_id) && $tournament_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM tournament_users WHERE tournament_id = ? AND user_id = ?', $tournament_id, $_profile->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags << PERMISSION_OFFSET_TOURNAMENT) & PERMISSION_MASK_TOURNAMENT;
			}
		}
	}
	return 0;
}

function get_profile_series_permissions($series_id)
{
	global $_profile;
	
	if ($_profile != NULL)
	{
		if ($_profile->is_admin())
		{
			return PERMISSION_MASK_SERIES;
		}

		if (is_numeric($series_id) && $series_id > 0)
		{
			$query = new DbQuery('SELECT flags FROM series_users WHERE series_id = ? AND user_id = ?', $series_id, $_profile->user_id);
			if ($row = $query->next())
			{
				list($flags) = $row;
				return ($flags << PERMISSION_OFFSET_SERIES) & PERMISSION_MASK_SERIES;
			}
		}
	}
	return 0;
}

// This is a version of check_permissions(..) that is returning false instead of throwing exception
function is_permitted($permissions, $id1 = 0, $id2 = 0, $id3 = 0, $id4 = 0, $id5 = 0, $id6 = 0)
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
	
	$current_param = 1;
	$owner_id = 0;
	if ($permissions & PERMISSION_MASK_OWNER)
	{
		$owner_id = ${"id$current_param"};
		++$current_param;
	}
	$club_id = 0;
	if ($permissions & PERMISSION_MASK_CLUB)
	{
		$club_id = ${"id$current_param"};
		++$current_param;
	}
	$league_id = 0;
	if ($permissions & PERMISSION_MASK_LEAGUE)
	{
		$league_id = ${"id$current_param"};
		++$current_param;
	}
	$event_id = 0;
	$event_permissions = -1;
	if ($permissions & PERMISSION_MASK_EVENT)
	{
		$event_id = ${"id$current_param"};
		++$current_param;
	}
	$tournament_id = 0;
	$tournament_permissions = -1;
	if ($permissions & PERMISSION_MASK_TOURNAMENT)
	{
		$tournament_id = ${"id$current_param"};
		++$current_param;
	}
	$series_id = 0;
	$series_permissions = -1;
	if ($permissions & PERMISSION_MASK_SERIES)
	{
		$series_id = ${"id$current_param"};
		++$current_param;
	}
	
	while ($permissions)
	{
		$next_perm = ($permissions & ($permissions - 1));
		$flag = $permissions - $next_perm;
		switch ($flag)
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
				
			case PERMISSION_CLUB_REFEREE:
				if ($_profile->is_club_referee($club_id))
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
				
			case PERMISSION_EVENT_PLAYER:
			case PERMISSION_EVENT_REFEREE:
			case PERMISSION_EVENT_MANAGER:
				if ($event_permissions < 0)
				{
					$event_permissions = get_profile_event_permissions($event_id);
				}
				if (($event_permissions & $flag) != 0)
				{
					return true;
				}
				break;

			case PERMISSION_TOURNAMENT_PLAYER:
			case PERMISSION_TOURNAMENT_REFEREE:
			case PERMISSION_TOURNAMENT_MANAGER:
				if ($tournament_permissions < 0)
				{
					$tournament_permissions = get_profile_tournament_permissions($tournament_id);
				}
				if (($tournament_permissions & $flag) != 0)
				{
					return true;
				}
				break;
				
			case PERMISSION_SERIES_PLAYER:
			case PERMISSION_SERIES_REFEREE:
			case PERMISSION_SERIES_MANAGER:
				if ($series_permissions < 0)
				{
					$series_permissions = get_profile_series_permissions($series_id);
				}
				if (($series_permissions & $flag) != 0)
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

// Ids are club_id, league_id, owner_id, event_id, tournament_id, and series_id depending on what is needed by the permission mask.
// Owner always go last.
// League always go after club.
//
// Examlples:
//
// check_permissions(PERMISSION_OWNER, $user_id); // Where $user_id is the owner of an object protected by this security attribute.
// check_permissions(PERMISSION_CLUB_MEMBER, $club_id);
// check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_MANAGER, $user_id, $club_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_MANAGER, $club_id, $league_id);
// check_permissions(PERMISSION_OWNER | PERMISSION_LEAGUE_MANAGER, $user_id, $league_id);
// check_permissions(PERMISSION_OWNER | PERMISSION_LEAGUE_MANAGER | PERMISSION_CLUB_REPRESENTATIVE, $user_id, $club_id, $league_id);
// check_permissions(PERMISSION_LEAGUE_MANAGER | PERMISSION_TOURNAMENT_MANAGER, $league_id, $tournament_id);
// check_permissions(PERMISSION_OWNER | PERMISSION_CLUB_REFEREE | PERMISSION_EVENT_REFEREE, $user_id, $club_id, $event_id);
// check_permissions(PERMISSION_LEAGUE_REFEREE | PERMISSION_SERIES_REFEREE, $league_id, $series_id);
function check_permissions($permissions, $id1 = 0, $id2 = 0, $id3 = 0, $id4 = 0, $id5 = 0, $id6 = 0)
{
	if (!is_permitted($permissions, $id1, $id2, $id3, $id4, $id5, $id6))
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
		case PERMISSION_CLUB_REFEREE:
			return 'club-regeree';
		case PERMISSION_CLUB_MANAGER:
			return 'club-manager';
		case PERMISSION_LEAGUE_MANAGER:
			return 'league-manager';
		case PERMISSION_EVENT_PLAYER:
			return 'event-player';
		case PERMISSION_EVENT_REFEREE:
			return 'event-regeree';
		case PERMISSION_EVENT_MANAGER:
			return 'event-manager';
		case PERMISSION_TOURNAMENT_PLAYER:
			return 'tournament-player';
		case PERMISSION_TOURNAMENT_REFEREE:
			return 'tournament-regeree';
		case PERMISSION_TOURNAMENT_MANAGER:
			return 'tournament-manager';
		case PERMISSION_SERIES_PLAYER:
			return 'series-player';
		case PERMISSION_SERIES_REFEREE:
			return 'series-regeree';
		case PERMISSION_SERIES_MANAGER:
			return 'series-manager';
		case PERMISSION_ADMIN:
			return 'admin';
	}
	return '?';
}

?>