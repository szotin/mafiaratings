use mafia;

-- 6025 - ЗАКРЫТЫЙ ЧЕМПИОНАТ РОССИИ
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 3 FROM events WHERE id = 6025;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 6025;

-- 7323 - ОТКРЫТЫЙ ЧЕМПИОНАТ РОССИИ
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 2 FROM events WHERE id = 7323;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 7323;

-- 7564 - III Открытый Чемпионат Казани по мафии
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 7564;

-- 7565 - Финал III Открытого Чемпионата Казани
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 7565;

-- 7793 - 5 лет клубу и открытый чемпионат Ванкувера
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 3 FROM events WHERE id = 7793;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 7793;

-- 7834 - Sunshine Coast
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 7834;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 7834;

-- 7927 - VaWaCa
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 7927;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 7927;

-- 7943 - Июньский Турнир
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 3 FROM events WHERE id = 7943;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 7943;

-- 8012 - West Coast Express
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 3.5 FROM events WHERE id = 8012;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8012;

-- 8095 - VaWaCa-2018
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8095;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8095;

-- 8135 - July Mini-Tournament
-- 8141 - August Mini-Tournament
-- 8160 - September Mini-Tournament
-- 8194 - Nov Mini-Tournament
-- 8206 - December Mini-Tournament
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT 'Mini-Tournaments 2018', club_id, address_id, 1514764800, 31536000, languages, notes, price, scoring_id, rules, 80, 2 FROM events WHERE id = 8135;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8135;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8141;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8160;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8194;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8206;

-- 8145 - Alcatraz Cup Qualifications 1
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8145;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8145;

-- 8146 - Alcatraz Cup Qualifications 2
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8146;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8146;

-- 8167 - Alcatraz Cup (Полуфинал-Финал)
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8167;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8167;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Полуфинал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.25, @id, rules FROM events WHERE id = 8167;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8167 AND round_num = 1;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Финал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.5, @id, rules FROM events WHERE id = 8167;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8167 AND round_num = 2;

-- 8199 - Seattle Mafia Challenge
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8199;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8199;

-- 8239 - Jan Mini-Tournament
-- 8252 - February mini tournament
-- 8281 - March Mini Tournament
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT 'Mini-Tournaments 2019', club_id, address_id, 1514764800, 31536000, languages, notes, price, scoring_id, rules, 80, 2 FROM events WHERE id = 8239;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8239;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8252;
UPDATE events SET flags = flags &~ 32, tournament_id = @id WHERE id = 8281;

-- 8247 - Seattle Mafia January Challenge
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8247;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8247;

-- 8244 - NY/SF Tournament
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 3 FROM events WHERE id = 8244;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8244;

-- 8257 - West Coast Express 2019 (Полуфинал-Финал)
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8257;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8257;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Полуфинал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.2, @id, rules FROM events WHERE id = 8257;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8257 AND round_num = 1;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Финал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.5, @id, rules FROM events WHERE id = 8257;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8257 AND round_num = 2;

-- 8289 - Seattle Mafia Challenge
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8289;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8289;

-- 8345 - VaWaCa-2019 (Полуфинал-Финал)
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8345;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8345;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Полуфинал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1, @id, rules FROM events WHERE id = 8345;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8345 AND round_num = 1;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Финал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.5, @id, rules FROM events WHERE id = 8345;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8345 AND round_num = 2;

-- 8362 - Играем на корову
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 1 FROM events WHERE id = 8362;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8362;

-- 8412 - Columbia Cup (финал)
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8412;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8412;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Финал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.5, @id, rules FROM events WHERE id = 8412;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8412 AND round_num = 2;

-- 8439 - Alcatraz-2019
INSERT INTO tournaments (name, club_id, address_id, start_time, duration, langs, notes, price, scoring_id, rules, flags, stars) 
SELECT name, club_id, address_id, start_time, duration, languages, notes, price, scoring_id, rules, 384, 4 FROM events WHERE id = 8439;
SELECT @id := LAST_INSERT_ID();
UPDATE events SET name = 'Основной раунд', flags = (flags &~ 32) | 3, tournament_id = @id WHERE id = 8439;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Полуфинал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1, @id, rules FROM events WHERE id = 8439;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8439 AND round_num = 1;

INSERT INTO events (name, address_id, club_id, start_time, duration, flags, languages, scoring_id, scoring_weight, tournament_id, rules)
SELECT 'Финал', address_id, club_id, start_time, duration, 27, languages, scoring_id, 1.5, @id, rules FROM events WHERE id = 8439;
SELECT @event_id := LAST_INSERT_ID();
UPDATE games SET event_id = @event_id WHERE event_id = 8439 AND round_num = 2;
