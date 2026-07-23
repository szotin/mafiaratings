use mafia;

-- No-op: this migration was an exact duplicate of alter13.sql (it added the same
-- `languages`/`language` columns to users/events/games/signup). Applying it after
-- alter13 always failed with "Duplicate column name", so it has been neutralized.
-- Kept as a placeholder to preserve migration numbering.
