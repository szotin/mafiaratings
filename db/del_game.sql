USE mafia;

SET @game_id = 629;

DELETE FROM sheriffs WHERE game_id = @game_id;
DELETE FROM dons WHERE game_id = @game_id;
DELETE FROM mafiosos WHERE game_id = @game_id;
DELETE FROM players WHERE game_id = @game_id;
DELETE FROM games WHERE id = @game_id;
