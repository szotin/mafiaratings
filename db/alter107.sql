ALTER TABLE players ADD COLUMN `extra_points_reason` VARCHAR(1024) NULL;
-- Index an explicit 255 character prefix rather than the whole column. Indexing all 1024
-- characters only fits where the large index prefix is available (utf8 makes it 3072 bytes,
-- against the 767 byte limit of the older row formats), and alter108 then turns this column
-- into TEXT, which cannot be indexed at all without a prefix length. 255 is what production
-- ends up with, so the chain reproduces the same schema on any MySQL version.
ALTER TABLE players ADD KEY(extra_points_reason(255));
