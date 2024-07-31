<?php

define('PAGE_WIDTH', 1020);
define('CONTENT_WIDTH', 986);

define('PAGE_STATE_EMPTY', 0);
define('PAGE_STATE_HEADER', 1);
define('PAGE_STATE_FOOTER', 2);

define('AGENT_BROWSER', 0);
define('AGENT_IPOD', 1);
define('AGENT_IPHONE', 2);
define('AGENT_IPAD', 3);
define('AGENT_ANDROID', 4);
define('AGENT_WEBOS', 5);

// permission flags - applicable to user-club; user-league; user-event; user-tournament; and user-series
define('USER_PERM_PLAYER', 0x1);
define('USER_PERM_REFEREE', 0x2);
define('USER_PERM_MANAGER', 0x4);
define('USER_PERM_ADMIN', 0x8);
define('USER_PERM_MASK', 0xf); // USER_PERM_ADMIN

// user-league flags
// 01 - 0x0001 -      1 - reserved (not to interfere with user-club perm flag player)
// 02 - 0x0002 -      2 - reserved (not to interfere with user-club perm flag referee)
// 03 - 0x0004 -      4 - reserved (not to interfere with user-club perm flag manager)
// 04 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 16 - 0x8000 -  32768 - perm manager
define('USER_LEAGUE_PERM_MANAGER', 0x8000);
define('USER_LEAGUE_PERM_MASK', 0x8000); // USER_LEAGUE_PERM_MANAGER

// user-club flags
// 01 - 0x0001 -      1 - perm player
// 02 - 0x0002 -      2 - perm mod
// 03 - 0x0004 -      4 - perm manager
// 04 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 05 - 0x0010 -     16 - subscribed
// 06 - 0x0020 -     32 - available
// 07 - 0x0040 -     64 - icon mask
// 08 - 0x0080 -    128 - icon mask
// 09 - 0x0100 -    256 - icon mask
// 16 - 0x8000 -  32768 - reserved (not to interfere with user-league perm flag manager)
define('USER_CLUB_FLAG_SUBSCRIBED', 0x10);
define('USER_CLUB_NEW_PLAYER_FLAGS', 0x11); // USER_PERM_PLAYER | USER_CLUB_FLAG_SUBSCRIBED

define('USER_CLUB_ICON_MASK', 0x1c0);
define('USER_CLUB_ICON_MASK_OFFSET', 6);
define('USER_CLUB_ICON_MAX_VERSION', 7);

// user-event flags
// 01 - 0x0001 -      1 - perm player
// 02 - 0x0002 -      2 - perm mod
// 03 - 0x0004 -      4 - perm manager
// 04 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 05 - 0x0010 -     16 - icon mask
// 06 - 0x0020 -     32 - icon mask
// 07 - 0x0040 -     64 - icon mask
define('USER_EVENT_NEW_PLAYER_FLAGS', 0x1); // USER_PERM_PLAYER

define('USER_EVENT_ICON_MASK', 0x70);
define('USER_EVENT_ICON_MASK_OFFSET', 4);
define('USER_EVENT_ICON_MAX_VERSION', 7);

// user-tournament flags
// 01 - 0x0001 -      1 - perm player
// 02 - 0x0002 -      2 - perm mod
// 03 - 0x0004 -      4 - perm manager
// 04 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 05 - 0x0010 -     16 - icon mask
// 06 - 0x0020 -     32 - icon mask
// 07 - 0x0040 -     64 - icon mask
// 08 - 0x0080 -    128 - user applied by themself. They is not accepted for the tournament yet. 
define('USER_TOURNAMENT_FLAG_NOT_ACCEPTED', 0x80);
define('USER_TOURNAMENT_NEW_PLAYER_FLAGS', 0x1); // USER_PERM_PLAYER

define('USER_TOURNAMENT_ICON_MASK', 0x70);
define('USER_TOURNAMENT_ICON_MASK_OFFSET', 4);
define('USER_TOURNAMENT_ICON_MAX_VERSION', 7);

// user-series flags
// 01 - 0x0001 -      1 - perm player
// 02 - 0x0002 -      2 - perm mod
// 03 - 0x0004 -      4 - perm manager
// 04 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 05 - 0x0800 -     16 - icon mask
// 06 - 0x1000 -     32 - icon mask
// 07 - 0x2000 -     64 - icon mask
define('USER_SERIES_NEW_PLAYER_FLAGS', 0x1); // USER_PERM_PLAYER

define('USER_SERIES_ICON_MASK', 0x70);
define('USER_SERIES_ICON_MASK_OFFSET', 4);
define('USER_SERIES_ICON_MAX_VERSION', 7);

// user flags
// 01 - 0x00001 -      1 - reserved (not to interfere with user-club perm flag player)
// 02 - 0x00002 -      2 - reserved (not to interfere with user-club perm flag referee)
// 03 - 0x00004 -      4 - reserved (not to interfere with user-club perm flag manager)
// 04 - 0x00008 -      8 - perm admin
// 05 - 0x00010 -     16 - reserved for future use
// 06 - 0x00020 -     32 - user never logged in so the password is not set. Account is not activated.
// 07 - 0x00040 -     64 - male
// 08 - 0x00080 -    128 - available
// 09 - 0x00100 -    256 - notify on comments
// 10 - 0x00200 -    512 - notify on photo
// 11 - 0x00400 -   1024 - immunity
// 12 - 0x00800 -   2048 - icon mask
// 13 - 0x01000 -   4096 - icon mask
// 14 - 0x02000 -   8192 - icon mask
// 15 - 0x04000 -  16384 - name was changed during registration
// 16 - 0x08000 -  32768 - reserved (not to interfere with user-league perm flag manager)
// 17 - 0x10000 -  65536 - user was imported from another system (for example MWT)
define('USER_FLAG_NO_PASSWORD', 0x20);
define('USER_FLAG_MALE', 0x40);
define('USER_FLAG_MESSAGE_NOTIFY', 0x100);
define('USER_FLAG_PHOTO_NOTIFY', 0x200);
define('USER_FLAG_IMMUNITY', 0x400);
define('USER_FLAG_NAME_CHANGED', 0x4000);
define('USER_FLAG_IMPORTED', 0x10000);

define('USER_INITIAL_RATING', 0);

define('USER_ICON_MASK', 0x3800);
define('USER_ICON_MASK_OFFSET', 11);
define('USER_ICON_MAX_VERSION', 7);

define('NEW_USER_FLAGS', 0x320); // USER_FLAG_MESSAGE_NOTIFY | USER_FLAG_PHOTO_NOTIFY | USER_FLAG_NO_PASSWORD

define('INCOMER_FLAGS_MALE', USER_FLAG_MALE);
define('INCOMER_FLAGS_EXISTING', 0x80);

define('PERM_OFFICER', 14); // admin, manager or referee
define('PERM_ALL', 0xffffffff);

define('FOR_EVERYONE', 0);
define('FOR_MEMBERS', 1);
define('FOR_MANAGERS', 2);
define('FOR_USER', 3);

define('SESSION_OK', 0);
//define('SESSION_TIMEOUT', 1);
define('SESSION_NO_USER', 2);
define('SESSION_LOGIN_FAILED',3);

define('PHOTOS_DIR', 'pics/photo/');
define('ADDRESS_PICS_DIR', 'pics/address/');
define('USER_PICS_DIR', 'pics/user/');
define('CLUB_PICS_DIR', 'pics/club/');
define('LEAGUE_PICS_DIR', 'pics/league/');
define('ALBUM_PICS_DIR', 'pics/album/');
define('EVENT_PICS_DIR', 'pics/event/');
define('TOURNAMENT_PICS_DIR', 'pics/tournament/');
define('SERIES_PICS_DIR', 'pics/series/');
define('SOUNDS_DIR', 'sounds/');

define('TNAILS_DIR', 'tnails/');
define('ICONS_DIR', 'icons/');
define('SOURCE_DIR', '');
define('PHOTO_ROW_COUNT',6);
define('PHOTO_COL_COUNT',5);

define('ADDRESS_PIC_CODE', 'a');
define('USER_PIC_CODE', 'u');
define('USER_CLUB_PIC_CODE', 'b');
define('USER_EVENT_PIC_CODE', 'v');
define('USER_TOURNAMENT_PIC_CODE', 'o');
define('USER_SERIES_PIC_CODE', 'r');
define('CLUB_PIC_CODE', 'c');
define('LEAGUE_PIC_CODE', 'l');
define('ALBUM_PIC_CODE', 'p');
define('EVENT_PIC_CODE', 'e');
define('TOURNAMENT_PIC_CODE', 't');
define('SERIES_PIC_CODE', 's');
define('PHOTO_CODE', 'h');

define('EVENT_PHOTO_WIDTH', (CONTENT_WIDTH / PHOTO_COL_COUNT - 20));
define('TOURNAMENT_PHOTO_WIDTH', (CONTENT_WIDTH / PHOTO_COL_COUNT - 20));
define('SERIES_PHOTO_WIDTH', (CONTENT_WIDTH / PHOTO_COL_COUNT - 20));
define('TNAIL_WIDTH', 280);
define('TNAIL_HEIGHT', 160);
define('ICON_WIDTH', 70);
define('ICON_HEIGHT', 70);

define('POINTS_ALL', 0);
define('POINTS_RED', 1);
define('POINTS_DARK', 2);
define('POINTS_CIVIL', 3);
define('POINTS_SHERIFF', 4);
define('POINTS_MAFIA', 5);
define('POINTS_DON', 6);

define('ADVERTS_PAGE_SIZE', 50);
define('EVENTS_PAGE_SIZE', 50);
define('TOURNAMENTS_PAGE_SIZE', 50);
define('SERIES_PAGE_SIZE', 50);
define('CITIES_PAGE_SIZE', 100);
define('COUNTRIES_PAGE_SIZE', 100);
define('CURRENCIES_PAGE_SIZE', 100);
define('USERS_PAGE_SIZE', 100);
define('GAMES_PAGE_SIZE', 100);
define('LOG_PAGE_SIZE', 100);
define('DEFAULT_ROW_COUNT', 20);
define('DEFAULT_COLUMN_COUNT', 5);

// event flags
//  1  - 0x0001 -      1 - event should not be shown in the event list before the end of the event
//  2  - 0x0002 -      2 - event should not be shown in the event list after the end of the event
//  3  - 0x0004 -      4 - canceled
//  4  - 0x0008 -      8 - everyone can referee
//  5  - 0x0010 -     16 - event is finished - all scoring is complete
//  6  - 0x0020 -     32 - event is for fun, most of the games are non-rating
//  7  - 0x0040 -     64 - icon mask
//  8  - 0x0080 -    128 - icon mask
//  9  - 0x0100 -    256 - icon mask
//  10 - 0x0200 -    512 - event is pinned to the front page of the site (only site admins can do it)
define('EVENT_FLAG_HIDDEN_BEFORE', 0x1);
define('EVENT_FLAG_HIDDEN_AFTER', 0x2);
define('EVENT_FLAG_CANCELED', 0x4);
define('EVENT_FLAG_ALL_CAN_REFEREE', 0x8);
define('EVENT_FLAG_FINISHED', 0x10);
define('EVENT_FLAG_FUN', 0x20);
define('EVENT_FLAG_PINNED', 0x200);
define('EVENT_MASK_HIDDEN', 0x3); // EVENT_FLAG_HIDDEN_BEFORE | EVENT_FLAG_HIDDEN_AFTER
define('EVENT_EDITABLE_MASK', 0x228); // EVENT_FLAG_ALL_CAN_REFEREE | EVENT_FLAG_FUN | EVENT_FLAG_PINNED

define('EVENT_ICON_MASK', 0x1c0);
define('EVENT_ICON_MASK_OFFSET', 6);
define('EVENT_ICON_MAX_VERSION', 7);

define('EVENT_ALIVE_TIME', 28800); // event can be extended during this time after being finished (8 hours)
define('EVENT_NOT_DONE_TIME', 1209600); // event is considered "recent" during this time after being finished (2 weeks)

// tournament flags
//  1 - 0x000001 -       1 - icon mask
//  2 - 0x000002 -       2 - icon mask
//  3 - 0x000004 -       4 - icon mask
//  4 - 0x000008 -       8 - canceled
//  5 - 0x000010 -      16 - long term tournament. Like a seasonal club championship.
//  6 - 0x000020 -      32 - single games from non-tournament events can be assigned to the tournament.
//  7 - 0x000040 -      64 - tournament is pinned to the front page of the site (only site admins can do it)
//  8 - 0x000080 -     128 - tournament is finished - all scoring is complete
//  9 - 0x000100 -     256 - teams tournament
// 10 - 0x000200 -     512 - we have no games information about this tournament - scores are entered manually
// 11 - 0x000400 -    1024 - this tournament has MVP as an award
// 12 - 0x000800 -    2096 - this tournament has best red as an award
// 13 - 0x001000 -    4096 - this tournament has best sheriff as an award
// 14 - 0x002000 -    8192 - this tournament has best black as an award
// 15 - 0x004000 -   16384 - this tournament has best don as an award
// 16 - 0x008000 -   32768 - reserved
// 17 - 0x010000 -   65536 - hide scoring table mask
// 18 - 0x020000 -  131072 - hide scoring table mask
// 19 - 0x040000 -  262144 - hide scoring table mask
// 20 - 0x080000 -  524288 - hide bonus mask
// 21 - 0x100000 - 1048576 - hide bonus mask
// 22 - 0x200000 - 2097152 - hide bonus mask
// 23 - 0x400000 - 4194304 - do not try to calculate number of players - just use what is specified in the db record
// 24 - 0x800000 - 8388608 - user registration for the tournament is closed. Only a manager can register users.
define('TOURNAMENT_FLAG_CANCELED', 0x8);
define('TOURNAMENT_FLAG_LONG_TERM', 0x10);
define('TOURNAMENT_FLAG_SINGLE_GAME', 0x20);
define('TOURNAMENT_FLAG_PINNED', 0x40);
define('TOURNAMENT_FLAG_FINISHED', 0x80);
define('TOURNAMENT_FLAG_TEAM', 0x100);
define('TOURNAMENT_FLAG_MANUAL_SCORE', 0x200);
define('TOURNAMENT_FLAG_AWARD_MVP', 0x400);
define('TOURNAMENT_FLAG_AWARD_RED', 0x800);
define('TOURNAMENT_FLAG_AWARD_SHERIFF', 0x1000);
define('TOURNAMENT_FLAG_AWARD_BLACK', 0x2000);
define('TOURNAMENT_FLAG_AWARD_DON', 0x4000);
define('TOURNAMENT_FLAG_FORCE_NUM_PLAYERS', 0x400000);
define('TOURNAMENT_FLAG_REGISTRATION_CLOSED', 0x800000);
define('TOURNAMENT_EDITABLE_MASK', 0xff7f70); // LONG_TERM | SINGLE_GAME | TOURNAMENT_FLAG_PINNED | TEAM | MANUAL_SCORE | AWARD_* | HIDE_MASK_* | TOURNAMENT_FLAG_FORCE_NUM_PLAYERS | TOURNAMENT_FLAG_REGISTRATION_CLOSED

define('TOURNAMENT_ICON_MASK', 0x7);
define('TOURNAMENT_ICON_MASK_OFFSET', 0);
define('TOURNAMENT_ICON_MAX_VERSION', 7);

define('TOURNAMENT_HIDE_TABLE_MASK', 0x70000);
define('TOURNAMENT_HIDE_TABLE_MASK_OFFSET', 16);
define('TOURNAMENT_HIDE_BONUS_MASK', 0x380000);
define('TOURNAMENT_HIDE_BONUS_MASK_OFFSET', 19);

// competition places flags
//  1 - 0x0001 -      1 - MVP
//  2 - 0x0002 -      2 - best red player
//  3 - 0x0004 -      3 - best sheriff
//  4 - 0x0008 -      4 - best black player
//  5 - 0x0010 -      5 - best don
define('COMPETITION_MVP', 0x1);
define('COMPETITION_BEST_RED', 0x2);
define('COMPETITION_BEST_SHERIFF', 0x4);
define('COMPETITION_BEST_BLACK', 0x8);
define('COMPETITION_BEST_DON', 0x10);

// series flags
//  1 - 0x0001 -      1 - icon mask
//  2 - 0x0002 -      2 - icon mask
//  3 - 0x0004 -      4 - icon mask
//  4 - 0x0008 -      8 - canceled
//  5 - 0x0010 -     16 - dirty flag - something is changed in series scoring. Tables must be rebuilt.
//  6 - 0x0020 -     32 - series is finished - all scoring is complete
//  7 - 0x0040 -     64 - series is pinned to the front page of the site (only site admins can do it)
define('SERIES_FLAG_CANCELED', 0x8);
define('SERIES_FLAG_DIRTY',    0x10);
define('SERIES_FLAG_FINISHED', 0x20);
define('SERIES_FLAG_PINNED', 0x40);
define('SERIES_EDITABLE_MASK', 0x40); // SERIES_FLAG_PINNED

define('SERIES_ICON_MASK', 0x7);
define('SERIES_ICON_MASK_OFFSET', 0);
define('SERIES_ICON_MAX_VERSION', 7);

// series tournament flags
//  1 - 0x0001 -      1 - not payed - shown in the series list as not payed, the results don't count in the series.
define('SERIES_TOURNAMENT_FLAG_NOT_PAYED', 0x1);

// series series flags
//  1 - 0x0001 -      1 - not payed - shown in the parent series list as not payed, the results don't count in the parent series.
define('SERIES_SERIES_FLAG_NOT_PAYED', 0x1);

// address flags
// 1 - 0x0001 -      1 - not used
// 2 - 0x0002 -      2 - generated
// 3 - 0x0004 -      4 - icon mask
// 4 - 0x0008 -      8 - icon mask
// 5 - 0x0010 -     16 - icon mask
define('ADDRESS_FLAG_NOT_USED', 0x1);
define('ADDRESS_FLAG_GENERATED', 0x2);

define('ADDRESS_ICON_MASK', 0x1c);
define('ADDRESS_ICON_MASK_OFFSET', 2);
define('ADDRESS_ICON_MAX_VERSION', 7);

define('MAILING_WAITING', 0);
define('MAILING_SENDING', 1);
define('MAILING_COMPLETE', 2);

define('MAILING_FLAG_TO_ATTENDED', 2); // when set the mailer sends emails to attended players
define('MAILING_FLAG_TO_DECLINED', 4); // when set the mailer sends emails to declined players
define('MAILING_FLAG_TO_DESIDING', 8); // when set the mailer sends emails to players who did not attend or decline yet.
define('MAILING_FLAG_TO_ALL', 14); // Combination of ATTENDED, DECLINED and DECIDING. When set the mailer sends emails to all players of the club.

define('EVENT_EMAIL_INVITE', 0);
define('EVENT_EMAIL_CANCEL', 1);
define('EVENT_EMAIL_CHANGE_ADDRESS', 2);
define('EVENT_EMAIL_CHANGE_TIME', 3);
define('EVENT_EMAIL_RESTORE', 4);
define('EVENT_EMAIL_COUNT', 5);

// club flags
// 1 - 0x0001 -      1 - retired
// 2 - 0x0002 -      2 - icon mask
// 3 - 0x0004 -      4 - icon mask
// 4 - 0x0008 -      8 - icon mask
define('CLUB_FLAG_RETIRED', 1);
define('NEW_CLUB_FLAGS', 0);

define('CLUB_ICON_MASK', 0xe);
define('CLUB_ICON_MASK_OFFSET', 1);
define('CLUB_ICON_MAX_VERSION', 7);

// league flags
// 1 - 0x0001 -      1 - retired
// 2 - 0x0002 -      2 - icon mask
// 3 - 0x0004 -      4 - icon mask
// 4 - 0x0008 -      8 - icon mask
define('LEAGUE_FLAG_RETIRED', 1);
define('NEW_LEAGUE_FLAGS', 0);

define('LEAGUE_ICON_MASK', 0xe);
define('LEAGUE_ICON_MASK_OFFSET', 1);
define('LEAGUE_ICON_MAX_VERSION', 7);

define('CITY_FLAG_NOT_CONFIRMED', 1);
define('COUNTRY_FLAG_NOT_CONFIRMED', 1);

// league-club flags
// 1 - 0x0001 -      1 - club membership is not approved by the league
// 2 - 0x0002 -      2 - club membership is not approved by the club
define('LEAGUE_CLUB_FLAGS_CLUB_APROVEMENT_NEEDED', 0x0001);
define('LEAGUE_CLUB_FLAGS_LEAGUE_APROVEMENT_NEEDED', 0x0002);

// album flags
// 1 - 0x0001 -      1 - icon mask
// 2 - 0x0002 -      2 - icon mask
// 3 - 0x0004 -      4 - icon mask
define('ALBUM_ICON_MASK', 7);
define('ALBUM_ICON_MASK_OFFSET', 0);
define('ALBUM_ICON_MAX_VERSION', 7);

// Club/City/Country filter constants
define('CCCF_CLUB', 'L');
define('CCCF_CITY', 'I');
define('CCCF_COUNTRY', 'O');

define('CCCF_ALL', -1);
define('CCCF_MY', 0);

define('CCCF_NO_CLUBS', 0x0001);
define('CCCF_NO_CITIES', 0x0002);
define('CCCF_NO_COUNTRIES', 0x0004);
define('CCCF_NO_ALL', 0x0008);
define('CCCF_NO_MY_CLUBS', 0x0010);
//define('CCCF_NO_MY_CITY', 0x0020);
//define('CCCF_NO_MY_COUNTRY', 0x0040);

define('EMAIL_EXPIRATION_TIME', 1209600); // two weeks

define('VIDEO_TYPE_NONE', -1);
define('VIDEO_TYPE_GAME', 0);
define('VIDEO_TYPE_LEARNING', 1);
define('VIDEO_TYPE_AWARD', 2);
define('VIDEO_TYPE_PARTY', 3);
define('VIDEO_TYPE_CUSTOM', 4);
define('VIDEO_TYPE_MIN', 0);
define('VIDEO_TYPE_MAX', 4);

define('TOURNAMENT_INVITATION_STATUS_NO_RESPONCE', 0);
define('TOURNAMENT_INVITATION_STATUS_ACCEPTED', 1);
define('TOURNAMENT_INVITATION_STATUS_DECLINED', 2);

function set_flag($flags, $flag, $value)
{
	if ($value)
	{
		return $flags | $flag;
	}
	return $flags & ~$flag;
}

define('UPLOAD_LOGO_MAX_SIZE', 2097152);
define('UPLOAD_SOUND_MAX_SIZE', 2097152);
define('UPLOAD_PHOTO_MAX_SIZE', 2097152);

define('ROLE_CIVILIAN', 0);
define('ROLE_SHERIFF', 1);
define('ROLE_MAFIA', 2);
define('ROLE_DON', 3);

define('KILL_TYPE_SURVIVED', 0);
define('KILL_TYPE_DAY', 1);
define('KILL_TYPE_NIGHT', 2);
define('KILL_TYPE_WARNINGS', 3);
define('KILL_TYPE_GIVE_UP', 4);
define('KILL_TYPE_KICK_OUT', 5);
define('KILL_TYPE_TEAM_KICK_OUT', 6);

?>
