CREATE TABLE `event_broadcasts` (
  `event_id` INT(11) NOT NULL,
  `day_num` INT(11) NOT NULL,
  `table_num` INT(11) NOT NULL,
  `part_num` INT(11) NOT NULL,
  `url` VARCHAR(1024) NOT NULL,
  `status` INT(11) NOT NULL, 

  PRIMARY KEY (`event_id`, `day_num`, `table_num`, `part_num`),
  CONSTRAINT `broadcast_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  KEY (`event_id`, `table_num`, `status`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;
