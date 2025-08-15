ALTER TABLE series_places ADD COLUMN total_cut_off FLOAT NOT NULL;
ALTER TABLE series_places ADD COLUMN cut_off FLOAT NOT NULL;

ALTER TABLE tournament_users ADD COLUMN city_id INT(11) NOT NULL;
ALTER TABLE tournament_users ADD COLUMN rating DOUBLE NOT NULL;
UPDATE tournament_users t JOIN users u ON u.id = t.user_id SET t.city_id = u.city_id;
ALTER TABLE tournament_users ADD CONSTRAINT `tournament_user_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

ALTER TABLE tournaments ADD COLUMN rating_sum DOUBLE NOT NULL;
ALTER TABLE tournaments ADD COLUMN rating_sum_20 DOUBLE NOT NULL;
ALTER TABLE tournaments ADD COLUMN traveling_distance DOUBLE NOT NULL;
ALTER TABLE tournaments ADD COLUMN guest_coeff DOUBLE NOT NULL;
ALTER TABLE tournaments ADD COLUMN num_regs INT(11) NOT NULL;
