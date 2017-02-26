use mafia;

BEGIN;

-- create default club
INSERT INTO `clubs` VALUES (NULL, 'Vancouver Mafia Club', 'Vancouver', '', '', FALSE, 'America/Vancouver');
SET @club_id = LAST_INSERT_ID();
INSERT INTO `users` VALUES (NULL, 'Admin',  '85b2609770622fe1d6696a6e5fd5b8a2', '', @club_id, 'szotin@gmail.com', TRUE, FALSE, FALSE, FALSE, FALSE, TRUE,  0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `users` VALUES (NULL, 'szotin', '85b2609770622fe1d6696a6e5fd5b8a2', '', @club_id, 'szotin@gmail.com', TRUE, FALSE, FALSE, TRUE,  TRUE,  FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);

COMMIT;
