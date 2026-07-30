use mafia;

-- Remove seating rows whose `seating` is a JSON object ({"11":...}) instead of a
-- 0-based JSON array. These arose because _seating_from_games (seating_optimization.php)
-- keyed rounds/tables by (game_num - 1)/(table_num - 1) without reindexing, so a
-- tournament not numbered from 1 produced non-contiguous keys that json_encode
-- serialized as an object. Reloaded without assoc mode such rows became stdClass and
-- crashed the optimizers' count() under PHP 8, so their numbers/tables were never
-- optimized and findSeating served them with non-0-based round keys.
--
-- The code fix (reindex_seating() in include/seating.php, applied on every seating
-- creation and load in include/seating.php and seating_optimization.php) stops these
-- from being created and normalizes any that are loaded. The seatings table is a
-- regenerable cache — findSeating() rebuilds a missing hash on demand and
-- ensure_seating_existance() re-creates it — and these object rows carry no real
-- optimization (their numbers/tables never ran), so dropping them is safe; they are
-- regenerated as proper 0-based arrays.
--
-- json_encode emits no leading whitespace, so a JSON array starts with '[' and a JSON
-- object with '{'. Data-only and replayable (a no-op on a freshly built table).
DELETE FROM seatings WHERE seating LIKE '{%';
