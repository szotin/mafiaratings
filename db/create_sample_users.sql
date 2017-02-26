use mafia;

BEGIN;

SET @club_id = 1;
SET @num = 1;

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, FALSE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

SET @name = CONCAT('player ', @num);
SET @email = CONCAT(CONCAT('player', @num), '@fakemail.com');
SET @num = @num + 1;
INSERT INTO `users` VALUES (NULL, @name, 'd41d8cd98f00b204e9800998ecf8427e', '', @club_id, @email, TRUE, FALSE, TRUE, FALSE, FALSE, FALSE, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, NULL);
INSERT INTO `registrations` VALUES (NULL, @club_id, LAST_INSERT_ID(), @name, 8, NOW());

COMMIT;