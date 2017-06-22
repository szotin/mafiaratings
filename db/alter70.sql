use mafia;

UPDATE scoring_points p, scorings s SET p.points = p.points * 100 WHERE s.id = p.scoring_id AND s.digits = 0;
UPDATE scoring_points p, scorings s SET p.points = p.points * 10 WHERE s.id = p.scoring_id AND s.digits = 1;
ALTER TABLE scorings DROP COLUMN digits;

SET @def_id = (SELECT id from scorings WHERE name = '');
UPDATE clubs SET scoring_id = 10 WHERE scoring_id = @def_id;
UPDATE events SET scoring_id = 10 WHERE scoring_id = @def_id;
DELETE FROM scoring_points WHERE scoring_id = @def_id;
DELETE FROM scorings WHERE id = @def_id;