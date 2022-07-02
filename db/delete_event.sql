SELECT @id := 9083;
DELETE FROM event_users WHERE event_id = @id;
DELETE FROM event_incomers WHERE event_id = @id;
DELETE FROM games WHERE event_id = @id;
DELETE FROM events WHERE id = @id;