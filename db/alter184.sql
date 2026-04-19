ALTER TABLE `seatings`
  ADD COLUMN `numbers_skip_runs` INT NOT NULL DEFAULT 0 AFTER `numbers_score`,
  ADD COLUMN `tables_skip_runs` INT NOT NULL DEFAULT 0 AFTER `tables_score`;
