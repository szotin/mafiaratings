ALTER TABLE `events`
  ADD COLUMN `players` INT NULL DEFAULT NULL,
  ADD COLUMN `tables` INT NULL DEFAULT NULL,
  ADD COLUMN `games` INT NULL DEFAULT NULL;

ALTER TABLE `tournaments`
  ADD COLUMN `preparation_stage` INT NOT NULL DEFAULT 0;

ALTER TABLE `tournament_regs`
  ADD COLUMN `reg_order` INT NOT NULL DEFAULT 0,
  ADD INDEX `idx_tournament_regs_order` (`tournament_id`, `reg_order`);
