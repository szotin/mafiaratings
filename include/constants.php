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

define('SITE_STYLE_DESKTOP', 0);
define('SITE_STYLE_MOBILE', 1);

// user-club flags
// 1 - 0x0001 -      1 - perm player
// 2 - 0x0002 -      2 - perm mod
// 3 - 0x0004 -      4 - perm manager
// 4 - 0x0008 -      8 - reserved (not to interfere with user perm flag admin)
// 5 - 0x0010 -     16 - subscribed
// 6 - 0x0020 -     32 - banned
define('UC_PERM_PLAYER', 0x1);
define('UC_PERM_MODER', 0x2);
define('UC_PERM_MANAGER', 0x4);
define('UC_FLAG_SUBSCRIBED', 0x10);
define('UC_FLAG_BANNED', 0x20); 
define('UC_NEW_PLAYER_FLAGS', 0x11); // UC_PERM_PLAYER | UC_FLAG_SUBSCRIBED
define('UC_PERM_MASK', 0x7); // UC_PERM_PLAYER | UC_PERM_MODER | UC_PERM_MANAGER

// user flags
// 01 - 0x0001 -      1 - reserved (not to interfere with user-club perm flag player)
// 02 - 0x0002 -      2 - reserved (not to interfere with user-club perm flag moder)
// 03 - 0x0004 -      4 - reserved (not to interfere with user-club perm flag manager)
// 04 - 0x0008 -      8 - perm admin
// 05 - 0x0010 -     16 - reserved for future use
// 06 - 0x0020 -     32 - user never logged in so the password is not set. Account is not activated.
// 07 - 0x0040 -     64 - male
// 08 - 0x0080 -    128 - banned
// 09 - 0x0100 -    256 - notify on comments
// 10 - 0x0200 -    512 - notify on photo
// 11 - 0x0400 -   1024 - immunity
// 12 - 0x0800 -   2048 - icon mask
// 13 - 0x1000 -   4096 - icon mask
// 14 - 0x2000 -   8192 - icon mask
// 15 - 0x4000 -  16384 - name was changed during registration
define('U_PERM_ADMIN', 0x8);
define('U_FLAG_NO_PASSWORD', 0x20);
define('U_FLAG_MALE', 0x40);
define('U_FLAG_BANNED', 0x80);
define('U_FLAG_MESSAGE_NOTIFY', 0x100);
define('U_FLAG_PHOTO_NOTIFY', 0x200);
define('U_FLAG_IMMUNITY', 0x400);
define('U_FLAG_NAME_CHANGED', 0x4000);
define('U_PERM_MASK', 0x8); // U_PERM_ADMIN

define('USER_INITIAL_RATING', 0);

define('U_ICON_MASK', 0x3800);
define('U_ICON_MASK_OFFSET', 11);
define('U_ICON_MAX_VERSION', 7);

define('U_NEW_PLAYER_FLAGS', 0x320); // U_FLAG_MESSAGE_NOTIFY | U_FLAG_PHOTO_NOTIFY | U_FLAG_NO_PASSWORD

define('INCOMER_FLAGS_MALE', U_FLAG_MALE);
define('INCOMER_FLAGS_EXISTING', 0x80);

define('PERM_STRANGER', 0x8000000);
define('PERM_USER', 0x4000000);
define('PERM_OFFICER', 14); // admin, manager or moderator
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
define('ALBUM_PICS_DIR', 'pics/album/');
define('EVENT_PICS_DIR', 'pics/event/');

define('TNAILS_DIR', 'tnails/');
define('ICONS_DIR', 'icons/');
define('PHOTO_ROW_COUNT',6);
define('PHOTO_COL_COUNT',5);

define('ADDR_PIC_CODE', 'a');
define('USER_PIC_CODE', 'u');
define('CLUB_PIC_CODE', 'c');
define('ALBUM_PIC_CODE', 'p');
define('EVENT_PIC_CODE', 'e');
define('PHOTO_CODE', 'h');

define('EVENT_PHOTO_WIDTH', (CONTENT_WIDTH / PHOTO_COL_COUNT - 20));
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

// event flags
// 1 - 0x0001 -      1 - register on attend
// 2 - 0x0002 -      2 - password required to play
// 3 - 0x0004 -      4 - canceled
// 4 - 0x0008 -      8 - everyone can moderate
// 5 - 0x0010 -     16 - event is finished - it can not be extended any more
// 6 - 0x0020 -     32 - this event is an official tournament
// 7 - 0x0040 -     64 - icon mask
// 8 - 0x0080 -    128 - icon mask
// 9 - 0x0100 -    256 - icon mask
define('EVENT_FLAG_REG_ON_ATTEND', 0x1);
define('EVENT_FLAG_PWD_REQUIRED', 0x2);
define('EVENT_FLAG_CANCELED', 0x4);
define('EVENT_FLAG_ALL_MODERATE', 0x8);
define('EVENT_FLAG_DONE', 0x10);
define('EVENT_FLAG_TOURNAMENT', 0x20);

define('EVENT_ICON_MASK', 0x1c0);
define('EVENT_ICON_MASK_OFFSET', 6);
define('EVENT_ICON_MAX_VERSION', 7);

define('EVENT_ALIVE_TIME', 28800); // event can be extended during this time after being finished (8 hours)
define('EVENT_NOT_DONE_TIME', 1209600);

// address flags
// 1 - 0x0001 -      1 - not used
// 2 - 0x0002 -      2 - generated
// 3 - 0x0004 -      4 - icon mask
// 4 - 0x0008 -      8 - icon mask
// 5 - 0x0010 -     16 - icon mask
define('ADDR_FLAG_NOT_USED', 0x1);
define('ADDR_FLAG_GENERATED', 0x2);

define('ADDR_ICON_MASK', 0x1c);
define('ADDR_ICON_MASK_OFFSET', 2);
define('ADDR_ICON_MAX_VERSION', 7);

define('MAILING_WAITING', 0);
define('MAILING_SENDING', 1);
define('MAILING_COMPLETE', 2);
define('MAILING_CANCELED', 3);

define('MAILING_FLAG_AUTODETECT_LANG', 1);
define('MAILING_FLAG_TO_ATTENDED', 2); // when set the mailer sends emails to attended players
define('MAILING_FLAG_TO_DECLINED', 4); // when set the mailer sends emails to declined players
define('MAILING_FLAG_TO_DESIDING', 8); // when set the mailer sends emails to players who did not attend or decline yet.
define('MAILING_FLAG_TO_ALL', 14); // Combination of ATTENDED, DECLINED and DECIDING. When set the mailer sends emails to all players of the club.
define('MAILING_FLAG_LANG_TO_SET_ONLY', 16); // when set the mailer sends emails only to the players with the default language matching the email language.
define('MAILING_FLAG_LANG_TO_DEF_ONLY', 32); // when set the mailer sends emails only to the players who know the email language.
define('MAILING_FLAG_LANG_MASK', 48);

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

define('CITY_FLAG_NOT_CONFIRMED', 1);
define('COUNTRY_FLAG_NOT_CONFIRMED', 1);

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

define('VIDEO_TYPE_LEARNING', 0);
define('VIDEO_TYPE_GAME', 1);

function set_flag($flags, $flag, $value)
{
	if ($value)
	{
		return $flags | $flag;
	}
	return $flags & ~$flag;
}

?>