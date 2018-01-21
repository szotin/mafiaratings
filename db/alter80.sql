use mafia;

CREATE TABLE `snapshots` (

  `time` INT(11) NOT NULL,
  `snapshot` TEXT NOT NULL,

  PRIMARY KEY (`time`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

