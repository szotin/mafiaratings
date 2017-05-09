use mafia;

ALTER TABLE email_templates
  ADD COLUMN `default_for` INT(11) NOT NULL;

UPDATE email_templates SET default_for = 1 WHERE name = 'Standard' OR name = 'Invite' OR name = 'Приглашение';
UPDATE email_templates SET default_for = 2 WHERE name = 'Cancel' OR name = 'Отмена';
UPDATE email_templates SET default_for = 3 WHERE name = 'Change address' OR name = 'Изменение адреса';
UPDATE email_templates SET default_for = 4 WHERE name = 'Change time' OR name = 'Изменение времени';
