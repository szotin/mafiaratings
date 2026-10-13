-- Keep the roles off the overlays at events played outside a tournament.
--
-- Whether the OBS overlays show who is who is now decided by the event, and a game takes it from
-- its event when it starts. EVENT_FLAG_HIDE_ROLES is 0x40000 and TOURNAMENT_FLAG_HIDE_ROLES is
-- 0x20000000 - see include/constants.php.
--
-- A tournament is played in a room of its own where the players have no phones on them, so the
-- roles can be shown there and the flag stays off: every tournament and every round of one is
-- left exactly as it is. An ordinary club event is the other way round - the players are sitting
-- at the table with their phones and could simply look the roles up - so those get the flag.
--
-- Only events outside a tournament are touched. New ones are created this way too, in
-- api/ops/event.php; a round of a tournament takes the flag from the tournament instead.

UPDATE `events` SET `flags` = `flags` | 0x40000 WHERE `tournament_id` IS NULL;
