SET @user_id := 2871;
DELETE FROM club_users WHERE user_id = @user_id;
DELETE FROM event_users WHERE user_id = @user_id;
DELETE FROM log WHERE user_id = @user_id;
DELETE FROM emails WHERE user_id = @user_id;
DELETE FROM users WHERE id = @user_id;
