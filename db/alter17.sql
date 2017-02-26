use mafia;

RENAME TABLE forum_messages TO messages;

ALTER TABLE messages
  DROP KEY on_object;
ALTER TABLE messages
  DROP KEY visibility;
ALTER TABLE messages
  DROP KEY update_time;
ALTER TABLE messages
  DROP KEY user_id;

ALTER TABLE messages
  CHANGE on_object obj TINYINT(2) NOT NULL;
ALTER TABLE messages
  CHANGE object_id obj_id INT(11) NULL;
ALTER TABLE messages
  CHANGE visibility vis TINYINT(2) NOT NULL;
ALTER TABLE messages
  CHANGE visibility_id vis_id INT(11) NULL;
ALTER TABLE messages
  CHANGE user_id user_id INT(11) NOT NULL;

ALTER TABLE messages
  ADD KEY (`obj`, `obj_id`, `update_time`);
ALTER TABLE messages
  ADD KEY (`user_id`, `send_time`);
ALTER TABLE messages
  ADD KEY (`send_time`);

CREATE TABLE `messages_tree` (
  `message_id` INT(11) NOT NULL,
  `parent_id` INT(11) NOT NULL,
  `send_time` INT(11) NOT NULL,

  PRIMARY KEY (`message_id`, `parent_id`),
  KEY (`parent_id`, `send_time`),
  CONSTRAINT `tree_message` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`),
  CONSTRAINT `tree_parent` FOREIGN KEY (`parent_id`) REFERENCES `messages` (`id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

UPDATE messages SET obj = obj + 1;

INSERT INTO messages (obj, obj_id, vis, vis_id, user_id, body, language, send_time, update_time)
  SELECT 0, message_id, 0, NULL, user_id, body, language, send_time, send_time
  FROM forum_responses;*/

INSERT INTO messages_tree (message_id, parent_id, send_time)
  SELECT id, obj_id, send_time FROM messages WHERE obj = 0;
