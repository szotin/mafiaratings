ALTER TABLE tournaments ADD COLUMN normalizer_id INT(11) NULL;
ALTER TABLE tournaments ADD COLUMN normalizer_version INT(11) NULL;
ALTER TABLE tournaments ADD KEY (normalizer_id, normalizer_version);
ALTER TABLE tournaments ADD CONSTRAINT tournament_normalizer_version FOREIGN KEY(normalizer_id, normalizer_version) REFERENCES normalizer_versions(normalizer_id, version);

ALTER TABLE clubs ADD COLUMN normalizer_id INT(11) NULL;
ALTER TABLE clubs ADD KEY (normalizer_id);
ALTER TABLE clubs ADD CONSTRAINT club_normalizer FOREIGN KEY(normalizer_id) REFERENCES normalizers(id);

ALTER TABLE leagues ADD COLUMN normalizer_id INT(11) NULL;
ALTER TABLE leagues ADD KEY (normalizer_id);
ALTER TABLE leagues ADD CONSTRAINT league_normalizer FOREIGN KEY(normalizer_id) REFERENCES normalizers(id);

