ALTER TABLE games ADD COLUMN flags INT(11) NOT NULL;
UPDATE games SET flags = flags | 1 WHERE is_rating <> 0;
UPDATE games SET flags = flags | 2 WHERE is_canceled <> 0;
UPDATE games SET flags = flags | 4 WHERE is_fiim_exported <> 0;
ALTER TABLE games DROP COLUMN is_rating;
ALTER TABLE games DROP COLUMN is_canceled;
ALTER TABLE games DROP COLUMN is_fiim_exported;

