ALTER TABLE scorings DROP COLUMN flags;

CREATE TABLE normalizers (
	id INT(11) NOT NULL AUTO_INCREMENT,
	club_id INT(11) NULL,
	league_id INT(11) NULL,
	name VARCHAR(128) NOT NULL,
	version INT(11) NULL,

	PRIMARY KEY (id),
	KEY (club_id, name),
	CONSTRAINT normalizer_club FOREIGN KEY (club_id) REFERENCES clubs (id),
	KEY (league_id, name),
	CONSTRAINT normalizer_league FOREIGN KEY (league_id) REFERENCES leagues (id),
	KEY(id, version)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE normalizer_versions (
	normalizer_id INT(11) NOT NULL,
	version INT(11) NOT NULL,
	normalizer TEXT NOT NULL,

	PRIMARY KEY (normalizer_id, version),
	CONSTRAINT version_normalizer FOREIGN KEY (normalizer_id) REFERENCES normalizers (id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE normalizers ADD CONSTRAINT normalizer_version FOREIGN KEY (id, version) REFERENCES normalizer_versions (normalizer_id, version);
