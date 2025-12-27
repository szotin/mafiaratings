ALTER TABLE `leagues` ADD COLUMN `default_rules` CHAR(32) NOT NULL;

UPDATE leagues SET default_rules = '00020000100000' WHERE id = 1; -- ФИИМ
UPDATE leagues SET default_rules = '04320000011100' WHERE id = 2; -- AML
UPDATE leagues SET default_rules = '00020000100000' WHERE id = 3; -- МЛМ
UPDATE leagues SET default_rules = '10010000001000', flags = flags | 16 WHERE id = 4; -- мафклаб
UPDATE leagues SET default_rules = '00020000100000' WHERE id = 5; -- Balkan