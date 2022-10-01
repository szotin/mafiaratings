ALTER TABLE series DROP FOREIGN KEY series_gaining_version;
ALTER TABLE leagues DROP FOREIGN KEY leagues_gaining;

ALTER TABLE series MODIFY gaining_id INT(11) NOT NULL;
ALTER TABLE series MODIFY gaining_version INT(11) NOT NULL;
ALTER TABLE leagues MODIFY gaining_id INT(11) NOT NULL;

ALTER TABLE series ADD CONSTRAINT series_gaining_version FOREIGN KEY(gaining_id, gaining_version) REFERENCES gaining_versions(gaining_id, version);
ALTER TABLE leagues ADD CONSTRAINT leagues_gaining FOREIGN KEY(gaining_id) REFERENCES gainings(id);
