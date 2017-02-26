use mafia;

ALTER TABLE emails
  DROP INDEX obj;

ALTER TABLE emails
  ADD KEY(obj, obj_id, send_time);