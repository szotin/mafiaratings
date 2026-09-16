use mafia;

-- Remove single-table seatings whose schedule is invalid. Two kinds of damage were found,
-- both produced by an older version of generateInitialSeating() (the current one builds valid
-- schedules for all of these configurations - verified over repeated generations):
--
--   12_1_5_0-1_0:2_1:3_2-3   players get 3,3,3,3,6,6,... games instead of 5 each
--   15_1_4_0-1               players get 2,4,5,3,3,5,... games instead of 4 each
--   15_1_4_0-1_1-2_1:3       players get 3,2,3,3,5,5,... games instead of 4 each
--   14_1_10_0-1_..._1:13     9 places where the same player sits at the table twice in one game
--
-- These are not merely cosmetic: such a seating served to a real event would give players an
-- unequal number of games, and the last one would seat somebody at the same table twice.
--
-- They are also self-perpetuating. An invalid schedule scores far better than any correct one,
-- because a player who plays fewer games simply meets fewer opponents, so the players optimizer
-- regenerates a valid seating every run, finds it "worse" and never saves it. Measured against
-- the best correct seating reachable for the same hash:
--
--   15_1_4_0-1               stored 115.21  vs  425.95 for a valid schedule
--   15_1_4_0-1_1-2_1:3       stored 142.47  vs 1228.58
--   12_1_5_0-1_0:2_1:3_2-3   stored 238.05  vs 6431.87
--
-- That is why all of them sit at players_runs == players_void_runs: every run is wasted. Only
-- deleting the rows breaks the deadlock. The seatings table is a regenerable cache -
-- findSeating() rebuilds a missing hash on demand - so they come back as valid schedules and
-- the new single-table optimization in seating_optimization.php can then improve them.
--
-- Data-only and replayable (a no-op once the rows are gone or on a freshly built table).
DELETE FROM seatings WHERE hash IN (
	'12_1_5_0-1_0:2_1:3_2-3',
	'15_1_4_0-1',
	'15_1_4_0-1_1-2_1:3',
	'14_1_10_0-1_1-2_1:3_1:4_1:5_1:6_1:7_1:8_1:9_1:10_1:11_1:12_1:13');
