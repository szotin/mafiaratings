use mafia;

ALTER TABLE user_photos
  ADD COLUMN email_sent BOOL NOT NULL;

UPDATE user_photos SET email_sent = TRUE;

ALTER TABLE user_photos
  ADD KEY (email_sent, user_id);
