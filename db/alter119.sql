UPDATE videos SET type = 2 WHERE type = 1;
UPDATE videos SET type = 1 WHERE type = 0;
UPDATE videos SET type = 0 WHERE type = 2;
UPDATE videos v SET v.tournament_id = (SELECT e.tournament_id FROM events e WHERE e.id = v.event_id) WHERE v.event_id IS NOT NULL;