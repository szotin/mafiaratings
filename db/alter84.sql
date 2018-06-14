use mafia;

ALTER TABLE news
  ADD COLUMN raw_message TEXT NOT NULL;
  
UPDATE news SET raw_message = message;
