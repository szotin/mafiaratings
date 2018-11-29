use mafia;

CREATE TABLE `rebuild_stats` (
  `time` INT(11),
  `action` VARCHAR(128) NOT NULL,
  `email_sent` INT(1) NOT NULL,

  PRIMARY KEY (`time`, `action`),
  KEY (`email_sent`)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



