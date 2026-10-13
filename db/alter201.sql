-- Drop the OBS overlay security token.
--
-- The overlays shown as browser sources in OBS Studio used to be addressed by a secret: game_obs.php
-- made up a 32 character token for an event or a tournament, stored it here, and put it in the URL
-- the club manager copied. api/get/current_game.php would answer only when the token matched.
--
-- The overlays name a table now - event_id or tournament_id together with table_num - and no token
-- is asked for or made any more. What is played at a table goes out on the broadcast anyway. These
-- two columns were the only thing the token ever lived in, nothing else has ever read them, and
-- alter124.sql is where they came from.

ALTER TABLE `events` DROP COLUMN `security_token`;
ALTER TABLE `tournaments` DROP COLUMN `security_token`;
