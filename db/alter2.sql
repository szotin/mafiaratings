use mafia;

ALTER TABLE mafiosos
   ADD COLUMN `is_don` BOOL NOT NULL;

UPDATE mafiosos m SET is_don = (SELECT IF(p.role = 3, true, false) FROM players p WHERE p.game_id = m.game_id AND p.user_id = m.user_id);
