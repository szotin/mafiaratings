use mafia;

-- Repair seating hashes corrupted by the empty("0") separator bug in
-- SeatingDef::generateHash() (include/seating.php). When a restriction group's
-- lowest normalized player index was 0 and its next member was >= 2 (a gap after
-- 0), the ':' separator after the leading "0" was dropped, so a group like [0,2]
-- was written as "02" instead of "0:2" (and [0,28] as "028" instead of "0:28").
--
-- The corruption is a single dropped ':' at the start of every affected segment.
-- Because segments are '_'-separated and neither players/tables/games nor a valid
-- range segment ("0-1", "0-2", ...) can produce "_0<digit>", the substring
-- "_0<digit>" uniquely marks a corrupt segment start. A valid range keeps its '-'
-- ("_0-2"), so it is never matched. The fix inserts ':' after that leading 0.
--
-- MySQL 5.7 (production) has no REGEXP_REPLACE, so the substitution is done with a
-- chain of REPLACE calls, one per possible first digit (1-9; 0 can never follow the
-- leading 0). Each corrupt spot becomes "_0:<digit>", which contains no further
-- "_0<digit>", so the replacements are independent and order does not matter.
--
-- This is a data-only repair (no schema change); on a freshly built, empty seatings
-- table it is a harmless no-op, so it stays replayable from scratch.

-- Step 1: hash is the PRIMARY KEY. If a corrupt row's repaired hash already exists
-- as a correctly-hashed row, updating in place would violate the key. Drop the
-- corrupt duplicate and keep the correct twin (the background optimizer will
-- re-optimize it under the now-correct restrictions).
DELETE c FROM seatings c
JOIN seatings g ON g.hash =
  REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
    c.hash, '_09', '_0:9'), '_08', '_0:8'), '_07', '_0:7'), '_06', '_0:6'),
    '_05', '_0:5'), '_04', '_0:4'), '_03', '_0:3'), '_02', '_0:2'), '_01', '_0:1')
WHERE c.hash REGEXP '_0[0-9]'
  AND g.hash NOT REGEXP '_0[0-9]';

-- Step 2: repair the remaining corrupt hashes in place. The corrupt rows were
-- optimized under a mis-parsed (weaker) restriction set, so their stored seating
-- may violate the real restrictions and their scores are stale. Reset the players
-- optimization bookkeeping and force players_score > 0 so the background optimizer
-- re-optimizes each row from scratch under the corrected restrictions. The first
-- players save cascades a reset of the numbers/tables scores, so those need no
-- explicit reset here.
UPDATE seatings
SET hash =
      REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        hash, '_09', '_0:9'), '_08', '_0:8'), '_07', '_0:7'), '_06', '_0:6'),
        '_05', '_0:5'), '_04', '_0:4'), '_03', '_0:3'), '_02', '_0:2'), '_01', '_0:1'),
    players_state = '',
    players_runs = 0,
    players_full_runs = 0,
    players_void_runs = 0,
    players_skip_runs = 0,
    players_score = GREATEST(players_score, 1)
WHERE hash REGEXP '_0[0-9]';
