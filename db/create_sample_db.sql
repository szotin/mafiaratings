-- Mafia Ratings - compact SAMPLE database
-- ---------------------------------------------------------------------------
-- Creates a tiny, self-consistent dataset for local testing:
--   * 1 club, 1 league, 1 season (series), 1 tournament, 1 event
--   * 11 games (game #1 plus 10 real games reused from production event 8553),
--     with all their players, shots and role data
--   * ~43 users. EVERY user's password is "1" (md5), including the admin.
--
-- Log in as  admin / 1  (site administrator), or as any player, e.g.  shrek / 1.
--
-- Schema and reference JSON (scoring/gaining/game) are taken from the full
-- production dump mafiawor_mafia.sql, so the structure matches production 1:1.
--
-- Usage: creates and fills the `mafia` database that include/server.php expects
-- for local development, so it can be loaded straight from the mysql client:
--     mysql -u root < db/create_sample_db.sql
-- ---------------------------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `mafia` DEFAULT CHARACTER SET utf8;
USE `mafia`;

START TRANSACTION;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

-- ============================================================
-- Schema (all tables; dropped first so the script is re-runnable)
-- ============================================================

DROP TABLE IF EXISTS `addresses`;
CREATE TABLE `addresses` (
  `id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `club_id` int(11) NOT NULL,
  `address` varchar(256) NOT NULL,
  `map_url` varchar(1024) NOT NULL,
  `flags` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `lat` double NOT NULL,
  `lon` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `bug_reports`;
CREATE TABLE `bug_reports` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `table_num` int(11) NOT NULL,
  `game_num` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game` text NOT NULL,
  `log` text,
  `comment` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `cities`;
CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `timezone` varchar(64) NOT NULL,
  `flags` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `name_id` int(11) NOT NULL,
  `lat` double NOT NULL,
  `lon` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `city_names`;
CREATE TABLE `city_names` (
  `city_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `clubs`;
CREATE TABLE `clubs` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `langs` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `web_site` varchar(256) NOT NULL,
  `city_id` int(11) NOT NULL,
  `email` varchar(256) NOT NULL,
  `phone` varchar(256) NOT NULL,
  `scoring_id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `rules` char(32) NOT NULL,
  `prompt_sound_id` int(11) DEFAULT NULL,
  `end_sound_id` int(11) DEFAULT NULL,
  `normalizer_id` int(11) DEFAULT NULL,
  `activated` int(11) NOT NULL,
  `fee` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `club_info`;
CREATE TABLE `club_info` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `name` varchar(256) NOT NULL,
  `value` text NOT NULL,
  `pos` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `club_pairs`;
CREATE TABLE `club_pairs` (
  `club_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `policy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `club_regs`;
CREATE TABLE `club_regs` (
  `user_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `club_rules`;
CREATE TABLE `club_rules` (
  `club_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `rules` char(32) NOT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `countries`;
CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `code` char(3) NOT NULL,
  `name_id` int(11) NOT NULL,
  `currency_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `country_names`;
CREATE TABLE `country_names` (
  `country_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `currencies`;
CREATE TABLE `currencies` (
  `id` int(11) NOT NULL,
  `name_id` int(11) NOT NULL,
  `pattern` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `current_games`;
CREATE TABLE `current_games` (
  `event_id` int(11) NOT NULL,
  `table_num` int(11) NOT NULL,
  `game_num` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `game` text NOT NULL,
  `log` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `dons`;
CREATE TABLE `dons` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `sheriff_found` tinyint(2) DEFAULT NULL,
  `sheriff_arranged` tinyint(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `emails`;
CREATE TABLE `emails` (
  `user_id` int(11) NOT NULL,
  `code` varchar(32) NOT NULL,
  `send_time` int(11) NOT NULL,
  `obj` tinyint(2) NOT NULL,
  `obj_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `events`;
CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `address_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `notes` text NOT NULL,
  `duration` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `languages` int(11) NOT NULL,
  `scoring_id` int(11) NOT NULL,
  `standings_settings` varchar(256) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `rules` char(32) NOT NULL,
  `scoring_version` int(11) NOT NULL,
  `scoring_options` varchar(256) NOT NULL,
  `security_token` char(32) DEFAULT NULL,
  `fee` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `round` int(11) NOT NULL,
  `misc` text,
  `players` int(11) DEFAULT NULL,
  `tables` int(11) DEFAULT NULL,
  `games` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_broadcasts`;
CREATE TABLE `event_broadcasts` (
  `event_id` int(11) NOT NULL,
  `day_num` int(11) NOT NULL,
  `table_num` int(11) NOT NULL,
  `part_num` int(11) NOT NULL,
  `url` varchar(1024) NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_comments`;
CREATE TABLE `event_comments` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `event_id` int(11) NOT NULL,
  `lang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_extra_points`;
CREATE TABLE `event_extra_points` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(128) NOT NULL,
  `details` text NOT NULL,
  `points` float NOT NULL,
  `mvp` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_incomers`;
CREATE TABLE `event_incomers` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `flags` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_mailings`;
CREATE TABLE `event_mailings` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `send_time` int(11) NOT NULL,
  `send_count` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `flags` int(11) NOT NULL,
  `langs` int(11) NOT NULL,
  `type` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_places`;
CREATE TABLE `event_places` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `place` int(11) NOT NULL,
  `importance` float NOT NULL,
  `main_points` float NOT NULL,
  `bonus_points` float DEFAULT NULL,
  `shot_points` float DEFAULT NULL,
  `games_count` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_regs`;
CREATE TABLE `event_regs` (
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `coming_odds` tinyint(2) DEFAULT NULL,
  `people_with_me` tinyint(2) DEFAULT NULL,
  `late` int(11) DEFAULT NULL,
  `nickname` varchar(128) DEFAULT NULL,
  `flags` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `event_scores_cache`;
CREATE TABLE `event_scores_cache` (
  `event_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `scoring_id` int(11) NOT NULL,
  `scoring_version` int(11) NOT NULL,
  `scores` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `gainings`;
CREATE TABLE `gainings` (
  `id` int(11) NOT NULL,
  `league_id` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `version` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `gaining_versions`;
CREATE TABLE `gaining_versions` (
  `gaining_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `gaining` text NOT NULL,
  `functions` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `games`;
CREATE TABLE `games` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `moderator_id` int(11) DEFAULT NULL,
  `result` tinyint(1) NOT NULL,
  `start_time` int(11) DEFAULT NULL,
  `end_time` int(11) DEFAULT NULL,
  `event_id` int(11) NOT NULL,
  `language` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `civ_odds` double DEFAULT NULL,
  `video_id` int(11) DEFAULT NULL,
  `rules` char(32) NOT NULL,
  `table_num` int(11) DEFAULT NULL,
  `game_num` int(11) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL,
  `json` text NOT NULL,
  `feature_flags` int(11) NOT NULL,
  `flags` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `game_comments`;
CREATE TABLE `game_comments` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `game_id` int(11) NOT NULL,
  `lang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `game_issues`;
CREATE TABLE `game_issues` (
  `game_id` int(11) NOT NULL,
  `json` text,
  `issues` text,
  `feature_flags` int(11) NOT NULL,
  `new_feature_flags` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `game_settings`;
CREATE TABLE `game_settings` (
  `user_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `prompt_sound_id` int(11) DEFAULT NULL,
  `end_sound_id` int(11) DEFAULT NULL,
  `feature_flags` int(11) NOT NULL,
  `obs_scenes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `gate_sessions`;
CREATE TABLE `gate_sessions` (
  `token` varchar(32) NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_activity` int(11) NOT NULL,
  `version` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `leagues`;
CREATE TABLE `leagues` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `langs` int(11) NOT NULL,
  `web_site` varchar(256) NOT NULL,
  `email` varchar(256) NOT NULL,
  `phone` varchar(256) NOT NULL,
  `scoring_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `rules` text NOT NULL,
  `normalizer_id` int(11) DEFAULT NULL,
  `gaining_id` int(11) NOT NULL,
  `default_rules` char(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `league_clubs`;
CREATE TABLE `league_clubs` (
  `league_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `rules` char(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `league_managers`;
CREATE TABLE `league_managers` (
  `league_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `league_pairs`;
CREATE TABLE `league_pairs` (
  `league_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `policy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `league_requests`;
CREATE TABLE `league_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `langs` int(11) NOT NULL,
  `web_site` varchar(256) NOT NULL,
  `email` varchar(256) NOT NULL,
  `phone` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `log`;
CREATE TABLE `log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `club_id` int(11) DEFAULT NULL,
  `time` int(11) NOT NULL,
  `obj` varchar(128) NOT NULL,
  `obj_id` int(11) DEFAULT NULL,
  `ip` varchar(32) DEFAULT NULL,
  `details` text,
  `page` varchar(256) NOT NULL,
  `message` varchar(256) NOT NULL,
  `league_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mafiosos`;
CREATE TABLE `mafiosos` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shots1_ok` tinyint(2) DEFAULT NULL,
  `shots1_miss` tinyint(2) DEFAULT NULL,
  `shots2_ok` tinyint(2) DEFAULT NULL,
  `shots2_miss` tinyint(2) DEFAULT NULL,
  `shots2_blank` tinyint(2) DEFAULT NULL,
  `shots2_rearrange` tinyint(2) DEFAULT NULL,
  `shots3_ok` tinyint(2) DEFAULT NULL,
  `shots3_miss` tinyint(2) DEFAULT NULL,
  `shots3_blank` tinyint(2) DEFAULT NULL,
  `shots3_fail` tinyint(2) DEFAULT NULL,
  `shots3_rearrange` tinyint(2) DEFAULT NULL,
  `is_don` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `maintenance_scripts`;
CREATE TABLE `maintenance_scripts` (
  `name` varchar(128) NOT NULL,
  `filename` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `maintenance_tasks`;
CREATE TABLE `maintenance_tasks` (
  `script_name` varchar(128) NOT NULL,
  `name` varchar(128) NOT NULL,
  `num` int(11) NOT NULL,
  `batches` bigint(20) NOT NULL,
  `runs` bigint(20) NOT NULL,
  `items` bigint(20) NOT NULL,
  `times` bigint(20) NOT NULL,
  `items_times` bigint(20) NOT NULL,
  `items_items` bigint(20) NOT NULL,
  `last_items_count` bigint(20) NOT NULL,
  `current_run_items` int(11) NOT NULL,
  `vars` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mr_bonus_stats`;
CREATE TABLE `mr_bonus_stats` (
  `game_id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `red_num` int(11) NOT NULL,
  `red_mean` float NOT NULL,
  `red_variance` float NOT NULL,
  `black_num` int(11) NOT NULL,
  `black_mean` float NOT NULL,
  `black_variance` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mwt_games`;
CREATE TABLE `mwt_games` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `time` int(11) NOT NULL,
  `json` text,
  `game_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `names`;
CREATE TABLE `names` (
  `id` int(11) NOT NULL,
  `langs` int(11) NOT NULL DEFAULT '16777215',
  `name` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `news`;
CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `message` text NOT NULL,
  `lang` int(11) NOT NULL,
  `expires` int(11) NOT NULL,
  `raw_message` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `normalizers`;
CREATE TABLE `normalizers` (
  `id` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `league_id` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `version` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `normalizer_versions`;
CREATE TABLE `normalizer_versions` (
  `normalizer_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `normalizer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `objections`;
CREATE TABLE `objections` (
  `id` int(11) NOT NULL,
  `timestamp` int(11) NOT NULL,
  `objection_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `game_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `accept` int(2) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `pairs`;
CREATE TABLE `pairs` (
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `policy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `photos`;
CREATE TABLE `photos` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `album_id` int(11) NOT NULL,
  `viewers` tinyint(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `photo_albums`;
CREATE TABLE `photo_albums` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `viewers` tinyint(2) NOT NULL,
  `adders` tinyint(2) NOT NULL,
  `tournament_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `photo_comments`;
CREATE TABLE `photo_comments` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `photo_id` int(11) NOT NULL,
  `lang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `players`;
CREATE TABLE `players` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nick_name` varchar(128) NOT NULL,
  `number` tinyint(2) NOT NULL,
  `role` tinyint(1) NOT NULL,
  `voted_civil` tinyint(2) DEFAULT NULL,
  `voted_mafia` tinyint(2) DEFAULT NULL,
  `voted_sheriff` tinyint(2) DEFAULT NULL,
  `voted_by_civil` tinyint(2) DEFAULT NULL,
  `voted_by_mafia` tinyint(2) DEFAULT NULL,
  `voted_by_sheriff` tinyint(2) DEFAULT NULL,
  `nominated_civil` tinyint(2) DEFAULT NULL,
  `nominated_mafia` tinyint(2) DEFAULT NULL,
  `nominated_sheriff` tinyint(2) DEFAULT NULL,
  `nominated_by_civil` tinyint(2) DEFAULT NULL,
  `nominated_by_mafia` tinyint(2) DEFAULT NULL,
  `nominated_by_sheriff` tinyint(2) DEFAULT NULL,
  `kill_round` tinyint(2) DEFAULT NULL,
  `kill_type` tinyint(2) DEFAULT NULL,
  `warns` tinyint(2) DEFAULT NULL,
  `was_arranged` tinyint(2) DEFAULT NULL,
  `checked_by_don` tinyint(2) DEFAULT NULL,
  `checked_by_sheriff` tinyint(2) DEFAULT NULL,
  `won` tinyint(1) NOT NULL,
  `flags` int(11) NOT NULL,
  `rating_before` double NOT NULL,
  `rating_earned` double NOT NULL,
  `extra_points` float NOT NULL,
  `extra_points_reason` text,
  `game_end_time` int(11) NOT NULL,
  `role_rating_before` double NOT NULL,
  `rating_lock_until` int(11) NOT NULL,
  `is_rating` tinyint(1) NOT NULL,
  `mr_points` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `profiling_ips`;
CREATE TABLE `profiling_ips` (
  `ip` char(64) NOT NULL,
  `agent` varchar(256) NOT NULL,
  `num` int(11) NOT NULL,
  `sum` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `profiling_pages`;
CREATE TABLE `profiling_pages` (
  `page` varchar(191) NOT NULL,
  `num` int(11) NOT NULL,
  `mean` double NOT NULL,
  `variance` double NOT NULL,
  `maximum` double NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `rebuild_ratings`;
CREATE TABLE `rebuild_ratings` (
  `id` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `end_time` int(11) NOT NULL,
  `game_id` int(11) DEFAULT NULL,
  `current_game_id` int(11) DEFAULT NULL,
  `average_game_proceeding_time` double NOT NULL,
  `batch_size` int(11) NOT NULL,
  `games_proceeded` int(11) NOT NULL,
  `ratings_changed` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `scorings`;
CREATE TABLE `scorings` (
  `id` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL,
  `league_id` int(11) DEFAULT NULL,
  `version` int(11) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `scoring_versions`;
CREATE TABLE `scoring_versions` (
  `scoring_id` int(11) NOT NULL,
  `version` int(11) NOT NULL,
  `scoring` text NOT NULL,
  `functions` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `seatings`;
CREATE TABLE `seatings` (
  `hash` varchar(255) NOT NULL,
  `seating` text,
  `players_runs` int(11) NOT NULL DEFAULT '0',
  `players_full_runs` int(11) NOT NULL DEFAULT '0',
  `players_void_runs` int(11) NOT NULL DEFAULT '0',
  `players_score` float NOT NULL DEFAULT '0',
  `players_skip_runs` int(11) NOT NULL DEFAULT '0',
  `players_state` text,
  `numbers_runs` int(11) NOT NULL DEFAULT '0',
  `numbers_full_runs` int(11) NOT NULL DEFAULT '0',
  `numbers_void_runs` int(11) NOT NULL DEFAULT '0',
  `numbers_score` float NOT NULL DEFAULT '0',
  `numbers_skip_runs` int(11) NOT NULL DEFAULT '0',
  `numbers_state` text,
  `tables_runs` int(11) NOT NULL DEFAULT '0',
  `tables_full_runs` int(11) NOT NULL DEFAULT '0',
  `tables_void_runs` int(11) NOT NULL DEFAULT '0',
  `tables_score` float NOT NULL DEFAULT '0',
  `tables_skip_runs` int(11) NOT NULL DEFAULT '0',
  `tables_state` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series`;
CREATE TABLE `series` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `league_id` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `langs` int(11) NOT NULL,
  `notes` text,
  `finals_id` int(11) DEFAULT NULL,
  `flags` int(11) NOT NULL,
  `rules` text NOT NULL,
  `gaining_id` int(11) NOT NULL,
  `gaining_version` int(11) NOT NULL,
  `per_player_fee` float NOT NULL,
  `fee` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series_extra_points`;
CREATE TABLE `series_extra_points` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `series_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `reason` varchar(128) NOT NULL,
  `details` text,
  `points` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series_places`;
CREATE TABLE `series_places` (
  `series_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `place` int(11) NOT NULL,
  `importance` float NOT NULL,
  `score` float NOT NULL,
  `tournaments` int(11) NOT NULL,
  `games` int(11) NOT NULL,
  `wins` int(11) NOT NULL,
  `total_cut_off` float NOT NULL,
  `cut_off` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series_regs`;
CREATE TABLE `series_regs` (
  `series_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series_series`;
CREATE TABLE `series_series` (
  `parent_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `stars` float DEFAULT NULL,
  `flags` int(11) NOT NULL,
  `fee` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `series_tournaments`;
CREATE TABLE `series_tournaments` (
  `tournament_id` int(11) NOT NULL,
  `series_id` int(11) NOT NULL,
  `stars` float DEFAULT NULL,
  `flags` int(11) NOT NULL,
  `fee` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `sheriffs`;
CREATE TABLE `sheriffs` (
  `game_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `civil_found` tinyint(2) DEFAULT NULL,
  `mafia_found` tinyint(2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `snapshots`;
CREATE TABLE `snapshots` (
  `time` int(11) NOT NULL,
  `snapshot` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `sounds`;
CREATE TABLE `sounds` (
  `id` int(11) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `name` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `stats_calculators`;
CREATE TABLE `stats_calculators` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `description` text NOT NULL,
  `code` text NOT NULL,
  `owner_id` int(11) NOT NULL,
  `published` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournaments`;
CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `club_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `start_time` int(11) NOT NULL,
  `duration` int(11) NOT NULL,
  `langs` int(11) NOT NULL,
  `notes` text,
  `scoring_id` int(11) NOT NULL,
  `rules` char(32) NOT NULL,
  `flags` int(11) NOT NULL,
  `scoring_version` int(11) NOT NULL,
  `standings_settings` varchar(256) DEFAULT NULL,
  `scoring_options` varchar(256) NOT NULL,
  `normalizer_id` int(11) DEFAULT NULL,
  `normalizer_version` int(11) DEFAULT NULL,
  `security_token` char(32) DEFAULT NULL,
  `type` int(11) NOT NULL,
  `num_players` int(11) NOT NULL,
  `fee` int(11) DEFAULT NULL,
  `currency_id` int(11) DEFAULT NULL,
  `mwt_id` int(11) DEFAULT NULL,
  `misc` text,
  `rating_sum` double NOT NULL,
  `rating_sum_20` double NOT NULL,
  `traveling_distance` double NOT NULL,
  `guest_coeff` double NOT NULL,
  `num_regs` int(11) NOT NULL,
  `imafia_id` int(11) DEFAULT NULL,
  `emo_id` int(11) DEFAULT NULL,
  `preparation_stage` int(11) NOT NULL DEFAULT '0',
  `team_size` int(11) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_approves`;
CREATE TABLE `tournament_approves` (
  `user_id` int(11) NOT NULL,
  `league_id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `stars` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_comments`;
CREATE TABLE `tournament_comments` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `lang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_invitations`;
CREATE TABLE `tournament_invitations` (
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_pairs`;
CREATE TABLE `tournament_pairs` (
  `tournament_id` int(11) NOT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `policy` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_places`;
CREATE TABLE `tournament_places` (
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `place` int(11) NOT NULL,
  `importance` float NOT NULL,
  `main_points` float NOT NULL,
  `bonus_points` float DEFAULT NULL,
  `shot_points` float DEFAULT NULL,
  `games_count` int(11) DEFAULT NULL,
  `flags` int(11) NOT NULL,
  `wins` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_regs`;
CREATE TABLE `tournament_regs` (
  `tournament_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `team_id` int(11) DEFAULT NULL,
  `city_id` int(11) NOT NULL,
  `rating` double NOT NULL,
  `reg_order` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_scores_cache`;
CREATE TABLE `tournament_scores_cache` (
  `tournament_id` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `scoring_id` int(11) NOT NULL,
  `scoring_version` int(11) NOT NULL,
  `normalizer_id` int(11) NOT NULL,
  `normalizer_version` int(11) NOT NULL,
  `scores` mediumtext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `tournament_teams`;
CREATE TABLE `tournament_teams` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `name` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `password` varchar(32) NOT NULL,
  `auth_key` varchar(32) NOT NULL,
  `email` varchar(256) NOT NULL,
  `games_moderated` int(11) NOT NULL,
  `flags` int(11) NOT NULL,
  `languages` int(11) NOT NULL,
  `reg_time` int(11) NOT NULL,
  `def_lang` int(11) NOT NULL,
  `city_id` int(11) NOT NULL,
  `phone` varchar(64) NOT NULL,
  `club_id` int(11) DEFAULT NULL,
  `games` int(11) NOT NULL,
  `games_won` int(11) NOT NULL,
  `rating` double NOT NULL,
  `name_id` int(11) NOT NULL,
  `mwt_id` int(11) DEFAULT NULL,
  `mwt_name` varchar(128) NOT NULL,
  `red_rating` double NOT NULL,
  `black_rating` double NOT NULL,
  `imafia_id` int(11) DEFAULT NULL,
  `imafia_name` varchar(128) NOT NULL,
  `emo_id` int(11) DEFAULT NULL,
  `emo_name` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `user_photos`;
CREATE TABLE `user_photos` (
  `user_id` int(11) NOT NULL,
  `photo_id` int(11) NOT NULL,
  `email_sent` tinyint(1) NOT NULL,
  `tag` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `user_videos`;
CREATE TABLE `user_videos` (
  `user_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `tagged_by_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `videos`;
CREATE TABLE `videos` (
  `id` int(11) NOT NULL,
  `name` varchar(256) NOT NULL,
  `video` varchar(1024) NOT NULL,
  `type` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `lang` int(11) NOT NULL,
  `post_time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `video_time` int(11) NOT NULL,
  `vtime` varchar(64) DEFAULT NULL,
  `tournament_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `video_comments`;
CREATE TABLE `video_comments` (
  `id` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `video_id` int(11) NOT NULL,
  `lang` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- ============================================================
-- Sample data
-- ============================================================

-- Reference: names (polymorphic name table; langs 16777215 = every UI language)
INSERT INTO `names` (`id`, `langs`, `name`) VALUES
(1, 16777215, 'Sample Country'),
(2, 16777215, 'US Dollar'),
(3, 16777215, 'Sample City'),
(10, 16777215, 'admin'),
(30, 16777215, 'shrek'),
(40, 16777215, 'lilya'),
(50, 16777215, 'Bacek'),
(60, 16777215, 'snake'),
(70, 16777215, 'medora'),
(80, 16777215, 'masha'),
(90, 16777215, 'Ladushka'),
(100, 16777215, 'Zheglova'),
(110, 16777215, 'aissp'),
(120, 16777215, 'Green'),
(130, 16777215, 'Anna'),
(140, 16777215, 'Boris'),
(150, 16777215, 'Clara'),
(160, 16777215, 'Dmitry'),
(170, 16777215, 'Elena'),
(180, 16777215, 'Fedor'),
(190, 16777215, 'Galina'),
(200, 16777215, 'Igor'),
(210, 16777215, 'Karina'),
(220, 16777215, 'Leonid'),
(250, 16777215, 'moderator');

-- Reference: geography and currency
INSERT INTO `currencies` (`id`, `name_id`, `pattern`) VALUES (1, 2, '$#');
INSERT INTO `countries` (`id`, `flags`, `code`, `name_id`, `currency_id`) VALUES (1, 0, 'US', 1, 1);
INSERT INTO `country_names` (`country_id`, `name`) VALUES (1, 'Sample Country');
INSERT INTO `cities` (`id`, `country_id`, `timezone`, `flags`, `area_id`, `name_id`, `lat`, `lon`) VALUES (1, 1, 'America/New_York', 0, 1, 3, 40.7128, -74.006);
INSERT INTO `city_names` (`city_id`, `name`) VALUES (1, 'Sample City');
INSERT INTO `addresses` (`id`, `name`, `club_id`, `address`, `map_url`, `flags`, `city_id`, `lat`, `lon`) VALUES (1, 'Sample Venue', 1, '123 Main St', '', 0, 1, 40.7128, -74.006);

-- Scoring (reused verbatim from the site 'mafiaratings' scoring, id 45 v1)
INSERT INTO `scorings` (`id`, `club_id`, `name`, `league_id`, `version`) VALUES (1, 1, 'Sample Scoring', NULL, 1);
INSERT INTO `scoring_versions` (`scoring_id`, `version`, `scoring`, `functions`) VALUES (1, 1, '{\"night1\":[{\"matter\":260,\"roles\":3,\"points\":\"max(min(counter(0,1)*1.2\\/counter(0,0)-0.18,0.3),0)\"}],\"counters\":[{\"matter\":1},{\"matter\":256,\"roles\":3}],\"main\":[{\"matter\":1,\"points\":1}],\"extra\":[{\"matter\":4194304,\"points\":\"bonus\",\"mvp\":true},{\"matter\":1,\"points\":\"mr_points\",\"mvp\":true,\"name\":\"MR\"}],\"penalty\":[{\"matter\":1,\"points\":\"matter(12) || matter(13) || matter(14) ? -0.8 : (matter(25) ? -1 : 0)\",\"mvp\":true}]}'
, 3312);

-- Gaining (reused verbatim from gaining 'Серийник', id 4 v1)
INSERT INTO `gainings` (`id`, `league_id`, `name`, `version`) VALUES (1, 1, 'Sample Gaining', 1);
INSERT INTO `gaining_versions` (`gaining_id`, `version`, `gaining`, `functions`) VALUES (1, 1, '{\"maxTournaments\":1,\"points\":\"table(stars-1, place-1)\",\"table\":[[20,19,18,17,16,15,14,13,12,11,10,9,8,7,6,5,4,3,2,1],[40,39,38,37,36,35,34,33,32,31,30,29,28,27,26,25,24,23,22,21],[60,59,58,57,56,55,54,53,52,51,50,49,48,47,46,45,44,43,42,41]]}'
, 576);

-- Users (all passwords are md5('1')). Id 1 is the site admin (USER_PERM_ADMIN | male).
INSERT INTO `users` (`id`, `password`, `auth_key`, `email`, `games_moderated`, `flags`, `languages`, `reg_time`, `def_lang`, `city_id`, `phone`, `club_id`, `games`, `games_won`, `rating`, `name_id`, `mwt_id`, `mwt_name`, `red_rating`, `black_rating`, `imafia_id`, `imafia_name`, `emo_id`, `emo_name`) VALUES
(1, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1)), 'user1@example.com', 0, 72, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 10, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(3, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 3)), 'user3@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 30, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(4, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 4)), 'user4@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 40, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(5, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 5)), 'user5@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 50, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(6, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 6)), 'user6@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 60, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(7, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 7)), 'user7@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 70, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(8, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 8)), 'user8@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 80, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(9, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 9)), 'user9@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 90, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(10, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 10)), 'user10@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 100, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(11, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 11)), 'user11@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 110, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(12, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 12)), 'user12@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 120, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(13, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 13)), 'user13@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 130, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(14, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 14)), 'user14@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 140, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(15, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 15)), 'user15@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 150, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(16, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 16)), 'user16@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 160, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(17, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 17)), 'user17@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 170, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(18, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 18)), 'user18@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 180, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(19, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 19)), 'user19@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 190, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(20, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 20)), 'user20@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 200, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(21, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 21)), 'user21@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 210, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(22, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 22)), 'user22@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 220, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(25, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 25)), 'user25@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 250, NULL, '', 1000, 1000, NULL, '', NULL, '');

-- Club membership (0x11 = player|subscribed; admin also gets manager 0x4)
INSERT INTO `club_regs` (`user_id`, `club_id`, `flags`) VALUES
(1, 1, 21),
(3, 1, 17),
(4, 1, 17),
(5, 1, 17),
(6, 1, 17),
(7, 1, 17),
(8, 1, 17),
(9, 1, 17),
(10, 1, 17),
(11, 1, 17),
(12, 1, 17),
(13, 1, 17),
(14, 1, 17),
(15, 1, 17),
(16, 1, 17),
(17, 1, 17),
(18, 1, 17),
(19, 1, 17),
(20, 1, 17),
(21, 1, 17),
(22, 1, 17),
(25, 1, 17);

-- Club, league, season(series), their links
INSERT INTO `clubs` (`id`, `name`, `langs`, `flags`, `web_site`, `city_id`, `email`, `phone`, `scoring_id`, `parent_id`, `rules`, `prompt_sound_id`, `end_sound_id`, `normalizer_id`, `activated`, `fee`, `currency_id`) VALUES (1, 'Sample Club', 7, 0, '', 1, 'club@example.com', '', 1, NULL, '0002000010000', NULL, NULL, NULL, 1284784660, NULL, NULL);
INSERT INTO `leagues` (`id`, `name`, `langs`, `web_site`, `email`, `phone`, `scoring_id`, `flags`, `rules`, `normalizer_id`, `gaining_id`, `default_rules`) VALUES (1, 'Sample League', 7, '', 'league@example.com', '', 1, 0, '{}', NULL, 1, '0002000010000');
INSERT INTO `league_clubs` (`league_id`, `club_id`, `flags`, `rules`) VALUES (1, 1, 0, '0002000010000');
INSERT INTO `league_managers` (`league_id`, `user_id`) VALUES (1, 1);
INSERT INTO `series` (`id`, `name`, `league_id`, `start_time`, `duration`, `langs`, `notes`, `finals_id`, `flags`, `rules`, `gaining_id`, `gaining_version`, `per_player_fee`, `fee`, `currency_id`) VALUES (1, 'Season 2024', 1, 1284784660, 31536000, 7, '', NULL, 0, '{}', 1, 1, 0, NULL, NULL);

-- Tournament (finished) and its link to the season
INSERT INTO `tournaments` (`id`, `name`, `club_id`, `address_id`, `start_time`, `duration`, `langs`, `notes`, `scoring_id`, `rules`, `flags`, `scoring_version`, `standings_settings`, `scoring_options`, `normalizer_id`, `normalizer_version`, `security_token`, `type`, `num_players`, `fee`, `currency_id`, `mwt_id`, `misc`, `rating_sum`, `rating_sum_20`, `traveling_distance`, `guest_coeff`, `num_regs`, `imafia_id`, `emo_id`, `preparation_stage`, `team_size`) VALUES (1, 'Sample Tournament', 1, 1, 1284784660, 86400, 7, '', 1, '0000100101000', 128, 1, NULL, '{}', NULL, NULL, NULL, 0, 10, NULL, NULL, NULL, NULL, 0, 0, 0, 1, 10, NULL, NULL, 0, 1);
INSERT INTO `series_tournaments` (`tournament_id`, `series_id`, `stars`, `flags`, `fee`) VALUES (1, 1, 1, 0, NULL);

-- Event (finished), belongs to the tournament
INSERT INTO `events` (`id`, `name`, `address_id`, `club_id`, `start_time`, `notes`, `duration`, `flags`, `languages`, `scoring_id`, `standings_settings`, `tournament_id`, `rules`, `scoring_version`, `scoring_options`, `security_token`, `fee`, `currency_id`, `round`, `misc`, `players`, `tables`, `games`) VALUES (1, 'Sample Event', 1, 1, 1284784660, '', 21600, 16, 7, 1, NULL, 1, '0000100101000', 1, '{}', NULL, NULL, NULL, 0, NULL, 10, 1, 1);

-- Registrations for the 10 players who played the game (users 3..12)
INSERT INTO `event_regs` (`event_id`, `user_id`, `coming_odds`, `people_with_me`, `late`, `nickname`, `flags`) VALUES
(1, 3, NULL, NULL, NULL, NULL, 1),
(1, 4, NULL, NULL, NULL, NULL, 1),
(1, 5, NULL, NULL, NULL, NULL, 1),
(1, 6, NULL, NULL, NULL, NULL, 1),
(1, 7, NULL, NULL, NULL, NULL, 1),
(1, 8, NULL, NULL, NULL, NULL, 1),
(1, 9, NULL, NULL, NULL, NULL, 1),
(1, 10, NULL, NULL, NULL, NULL, 1),
(1, 11, NULL, NULL, NULL, NULL, 1),
(1, 12, NULL, NULL, NULL, NULL, 1);

INSERT INTO `tournament_regs` (`tournament_id`, `user_id`, `flags`, `team_id`, `city_id`, `rating`, `reg_order`) VALUES
(1, 3, 1, NULL, 1, 1000, 1),
(1, 4, 1, NULL, 1, 1000, 2),
(1, 5, 1, NULL, 1, 1000, 3),
(1, 6, 1, NULL, 1, 1000, 4),
(1, 7, 1, NULL, 1, 1000, 5),
(1, 8, 1, NULL, 1, 1000, 6),
(1, 9, 1, NULL, 1, 1000, 7),
(1, 10, 1, NULL, 1, 1000, 8),
(1, 11, 1, NULL, 1, 1000, 9),
(1, 12, 1, NULL, 1, 1000, 10);

INSERT INTO `series_regs` (`series_id`, `user_id`, `flags`) VALUES
(1, 3, 1),
(1, 4, 1),
(1, 5, 1),
(1, 6, 1),
(1, 7, 1),
(1, 8, 1),
(1, 9, 1),
(1, 10, 1),
(1, 11, 1),
(1, 12, 1);

-- The game itself (reused verbatim from real game id 1; event/tournament ids remapped)
INSERT INTO `games` (`id`, `club_id`, `moderator_id`, `result`, `start_time`, `end_time`, `event_id`, `language`, `user_id`, `civ_odds`, `video_id`, `rules`, `table_num`, `game_num`, `tournament_id`, `json`, `feature_flags`, `flags`) VALUES
(1, 1, 25, 2, 1284784660, 1284786870, 1, 2, 25, 0.5, NULL, '000010010100', NULL, NULL, 1, '{\"id\":1,\"clubId\":1,\"eventId\":1,\"startTime\":1284784660,\"endTime\":1284786870,\"language\":\"ru\",\"rules\":\"0000100101000\",\"features\":\"agsdutchvnwr\",\"winner\":\"maf\",\"moderator\":{\"id\":25},\"players\":[{\"id\":7,\"name\":\"medora\",\"arranged\":1,\"role\":\"sheriff\",\"death\":{\"round\":1,\"type\":\"night\"},\"warnings\":[{\"round\":1,\"time\":\"night kill speaking\"}]},{\"id\":10,\"name\":\"Zheglova\",\"role\":\"maf\",\"voting\":[null,8,6,6,9],\"nominating\":[null,null,null,null,10],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":5},{\"round\":2,\"votingRound\":0,\"time\":\"voting\"}],\"shooting\":[1,3,5,4]},{\"id\":3,\"name\":\"shrek\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[null,8]},{\"id\":9,\"name\":\"Ladushka\",\"arranged\":2,\"don\":1,\"death\":{\"round\":4,\"type\":\"night\"},\"voting\":[null,8,7,6],\"nominating\":[null,null,null,6],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":10},{\"round\":3,\"time\":\"day kill speaking\",\"speaker\":6}]},{\"id\":12,\"name\":\"Green\",\"arranged\":3,\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[null,8,6],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":9}]},{\"id\":11,\"name\":\"aissp\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[null,8,5,9],\"nominating\":[null,null,7,9],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":4}]},{\"id\":6,\"name\":\"snake\",\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"nominating\":[null,5,5],\"voting\":[null,5,5],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":10}],\"shooting\":[1,3]},{\"id\":8,\"name\":\"masha\",\"role\":\"don\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[null,5],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":9}],\"shooting\":[1]},{\"id\":4,\"name\":\"lilya\",\"death\":{\"round\":4,\"type\":\"day\"},\"nominating\":[null,8,6,null,2],\"voting\":[null,8,7,6,10],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":2}]},{\"id\":5,\"name\":\"Bacek\",\"sheriff\":1,\"voting\":[null,8,7,6,9],\"nominating\":[null,null,null,null,9]}]}', 15231, 1);

INSERT INTO `players` (`game_id`, `user_id`, `nick_name`, `number`, `role`, `voted_civil`, `voted_mafia`, `voted_sheriff`, `voted_by_civil`, `voted_by_mafia`, `voted_by_sheriff`, `nominated_civil`, `nominated_mafia`, `nominated_sheriff`, `nominated_by_civil`, `nominated_by_mafia`, `nominated_by_sheriff`, `kill_round`, `kill_type`, `warns`, `was_arranged`, `checked_by_don`, `checked_by_sheriff`, `won`, `flags`, `rating_before`, `rating_earned`, `extra_points`, `extra_points_reason`, `game_end_time`, `role_rating_before`, `rating_lock_until`, `is_rating`, `mr_points`) VALUES
(1, 3, 'shrek', 3, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2, 0, -1, -1, -1, 0, 524805, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0.131693411942),
(1, 4, 'lilya', 9, 0, 2, 2, 0, 2, 1, 0, 1, 2, 0, 2, 0, 0, 4, 1, 1, -1, -1, -1, 0, 524293, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0.0376530582592),
(1, 5, 'Bacek', 10, 0, 2, 2, 0, 1, 0, 0, 1, 0, 0, 0, 1, 0, -1, 0, 0, -1, -1, 1, 0, 524293, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, -0.1970408012),
(1, 6, 'snake', 7, 2, 2, 0, 0, 3, 0, 0, 2, 0, 0, 1, 0, 0, 2, 1, 1, -1, -1, -1, 1, 524291, 0, 2, 0, NULL, 1284786870, 0, 1285996470, 1, -0.802358834015),
(1, 7, 'medora', 1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 1, 1, -1, -1, 0, 525061, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0),
(1, 8, 'masha', 8, 3, 1, 0, 0, 6, 1, 0, 0, 0, 0, 1, 0, 0, 1, 1, 1, -1, -1, -1, 1, 524291, 0, 2, 0, NULL, 1284786870, 0, 1285996470, 1, -0.790160471651),
(1, 9, 'Ladushka', 4, 0, 1, 2, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 4, 2, 2, 2, 1, -1, 0, 524805, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0.399146356614),
(1, 10, 'Zheglova', 2, 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, -1, 0, 2, -1, -1, -1, 1, 524419, 0, 2, 0, NULL, 1284786870, 0, 1285996470, 1, 0),
(1, 11, 'aissp', 6, 0, 2, 1, 0, 4, 2, 0, 1, 1, 0, 2, 0, 0, 3, 1, 1, -1, -1, -1, 0, 524293, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0.131693411942),
(1, 12, 'Green', 5, 0, 1, 1, 0, 1, 3, 0, 0, 0, 0, 0, 2, 0, 3, 2, 1, 3, -1, -1, 0, 524805, 0, -1.5, 0, NULL, 1284786870, 0, 1285996470, 1, 0.131693411942);

INSERT INTO `mafiosos` (`game_id`, `user_id`, `shots1_ok`, `shots1_miss`, `shots2_ok`, `shots2_miss`, `shots2_blank`, `shots2_rearrange`, `shots3_ok`, `shots3_miss`, `shots3_blank`, `shots3_fail`, `shots3_rearrange`, `is_don`) VALUES
(1, 6, 0, 0, 1, 0, 0, 1, 1, 0, 0, 0, 0, 0),
(1, 8, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1),
(1, 10, 2, 0, 1, 0, 0, 1, 1, 0, 0, 0, 0, 0);


-- ============================================================
-- Additional games (10 real games from production event 8553,
-- rehomed into this sample's club/event/tournament) and the
-- extra players/moderators they reference.
-- ============================================================

-- Names for the extra users
INSERT INTO `names` (`id`, `langs`, `name`) VALUES
(2761, 16777215, 'Strelok'),
(2805, 16777215, 'Moderator805'),
(2815, 16777215, 'Comic'),
(2826, 16777215, 'Zvezdochka'),
(2832, 16777215, 'Убийца'),
(2838, 16777215, 'Siberian'),
(2895, 16777215, 'фрукт'),
(3020, 16777215, 'Geralt'),
(3043, 16777215, 'Scorpion'),
(3139, 16777215, 'Moderator1139'),
(3141, 16777215, 'Donna-Roza'),
(3143, 16777215, 'Stalker'),
(3147, 16777215, 'Гудвин'),
(3200, 16777215, 'Djuffin'),
(3577, 16777215, 'Ярославна'),
(3797, 16777215, 'Matilda'),
(3837, 16777215, 'Flanker'),
(3860, 16777215, 'Metamorphosis'),
(3862, 16777215, 'Шнурок'),
(3954, 16777215, 'Hyyy6'),
(3959, 16777215, 'Hulk');

-- Extra users (password '1'); flags 64 = male
INSERT INTO `users` (`id`, `password`, `auth_key`, `email`, `games_moderated`, `flags`, `languages`, `reg_time`, `def_lang`, `city_id`, `phone`, `club_id`, `games`, `games_won`, `rating`, `name_id`, `mwt_id`, `mwt_name`, `red_rating`, `black_rating`, `imafia_id`, `imafia_name`, `emo_id`, `emo_name`) VALUES
(761, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 761)), 'user761@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2761, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(805, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 805)), 'user805@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2805, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(815, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 815)), 'user815@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2815, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(826, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 826)), 'user826@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2826, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(832, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 832)), 'user832@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2832, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(838, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 838)), 'user838@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2838, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(895, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 895)), 'user895@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 2895, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1020, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1020)), 'user1020@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3020, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1043, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1043)), 'user1043@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3043, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1139, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1139)), 'user1139@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3139, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1141, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1141)), 'user1141@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3141, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1143, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1143)), 'user1143@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3143, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1147, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1147)), 'user1147@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3147, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1200, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1200)), 'user1200@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3200, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1577, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1577)), 'user1577@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3577, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1797, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1797)), 'user1797@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3797, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1837, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1837)), 'user1837@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3837, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1860, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1860)), 'user1860@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3860, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1862, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1862)), 'user1862@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3862, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1954, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1954)), 'user1954@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3954, NULL, '', 1000, 1000, NULL, '', NULL, ''),
(1959, 'c4ca4238a0b923820dcc509a6f75849b', MD5(CONCAT('auth', 1959)), 'user1959@example.com', 0, 64, 7, 1284784660, 1, 1, '', 1, 0, 0, 1000, 3959, NULL, '', 1000, 1000, NULL, '', NULL, '');

-- Club membership for the extra users (0x11 player|subscribed; 0x13 adds referee for moderators)
INSERT INTO `club_regs` (`user_id`, `club_id`, `flags`) VALUES
(761, 1, 17),
(805, 1, 19),
(815, 1, 17),
(826, 1, 17),
(832, 1, 17),
(838, 1, 17),
(895, 1, 17),
(1020, 1, 17),
(1043, 1, 17),
(1139, 1, 19),
(1141, 1, 17),
(1143, 1, 17),
(1147, 1, 17),
(1200, 1, 17),
(1577, 1, 17),
(1797, 1, 17),
(1837, 1, 17),
(1860, 1, 17),
(1862, 1, 17),
(1954, 1, 17),
(1959, 1, 17);

-- Register the extra players in the event, tournament and season
INSERT INTO `event_regs` (`event_id`, `user_id`, `coming_odds`, `people_with_me`, `late`, `nickname`, `flags`) VALUES
(1, 761, NULL, NULL, NULL, NULL, 1),
(1, 815, NULL, NULL, NULL, NULL, 1),
(1, 826, NULL, NULL, NULL, NULL, 1),
(1, 832, NULL, NULL, NULL, NULL, 1),
(1, 838, NULL, NULL, NULL, NULL, 1),
(1, 895, NULL, NULL, NULL, NULL, 1),
(1, 1020, NULL, NULL, NULL, NULL, 1),
(1, 1043, NULL, NULL, NULL, NULL, 1),
(1, 1141, NULL, NULL, NULL, NULL, 1),
(1, 1143, NULL, NULL, NULL, NULL, 1),
(1, 1147, NULL, NULL, NULL, NULL, 1),
(1, 1200, NULL, NULL, NULL, NULL, 1),
(1, 1577, NULL, NULL, NULL, NULL, 1),
(1, 1797, NULL, NULL, NULL, NULL, 1),
(1, 1837, NULL, NULL, NULL, NULL, 1),
(1, 1860, NULL, NULL, NULL, NULL, 1),
(1, 1862, NULL, NULL, NULL, NULL, 1),
(1, 1954, NULL, NULL, NULL, NULL, 1),
(1, 1959, NULL, NULL, NULL, NULL, 1);
INSERT INTO `tournament_regs` (`tournament_id`, `user_id`, `flags`, `team_id`, `city_id`, `rating`, `reg_order`) VALUES
(1, 761, 1, NULL, 1, 1000, 11),
(1, 815, 1, NULL, 1, 1000, 12),
(1, 826, 1, NULL, 1, 1000, 13),
(1, 832, 1, NULL, 1, 1000, 14),
(1, 838, 1, NULL, 1, 1000, 15),
(1, 895, 1, NULL, 1, 1000, 16),
(1, 1020, 1, NULL, 1, 1000, 17),
(1, 1043, 1, NULL, 1, 1000, 18),
(1, 1141, 1, NULL, 1, 1000, 19),
(1, 1143, 1, NULL, 1, 1000, 20),
(1, 1147, 1, NULL, 1, 1000, 21),
(1, 1200, 1, NULL, 1, 1000, 22),
(1, 1577, 1, NULL, 1, 1000, 23),
(1, 1797, 1, NULL, 1, 1000, 24),
(1, 1837, 1, NULL, 1, 1000, 25),
(1, 1860, 1, NULL, 1, 1000, 26),
(1, 1862, 1, NULL, 1, 1000, 27),
(1, 1954, 1, NULL, 1, 1000, 28),
(1, 1959, 1, NULL, 1, 1000, 29);
INSERT INTO `series_regs` (`series_id`, `user_id`, `flags`) VALUES
(1, 761, 1),
(1, 815, 1),
(1, 826, 1),
(1, 832, 1),
(1, 838, 1),
(1, 895, 1),
(1, 1020, 1),
(1, 1043, 1),
(1, 1141, 1),
(1, 1143, 1),
(1, 1147, 1),
(1, 1200, 1),
(1, 1577, 1),
(1, 1797, 1),
(1, 1837, 1),
(1, 1860, 1),
(1, 1862, 1),
(1, 1954, 1),
(1, 1959, 1);

-- The 10 games (json kept verbatim; club/event/tournament ids remapped to 1)
INSERT INTO `games` (`id`, `club_id`, `moderator_id`, `result`, `start_time`, `end_time`, `event_id`, `language`, `user_id`, `civ_odds`, `video_id`, `rules`, `table_num`, `game_num`, `tournament_id`, `json`, `feature_flags`, `flags`) VALUES
(4842, 1, 1139, 1, 1579985050, 1579988247, 1, 2, 805, 0.492839306161, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4842,\"clubId\":1,\"eventId\":1,\"startTime\":1579985050,\"endTime\":1579988247,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":1139},\"players\":[{\"id\":1577,\"name\":\"\\u042f\\u0440\\u043e\\u0441\\u043b\\u0430\\u0432\\u043d\\u0430\",\"voting\":[[2,2],4,2,8],\"nominating\":[null,8]},{\"id\":9,\"name\":\"Ladushka\",\"sheriff\":1,\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"nominating\":[1,4],\"voting\":[[1,1],8,5],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":3}],\"shooting\":[10,9]},{\"id\":1797,\"name\":\"Matilda\",\"role\":\"sheriff\",\"voting\":[[1,1],4,2,8],\"nominating\":[null,5,2]},{\"id\":1837,\"name\":\"Flanker\",\"sheriff\":3,\"role\":\"maf\",\"death\":{\"round\":1,\"type\":\"day\"},\"nominating\":[2,1],\"voting\":[[1,1],1],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":5},{\"round\":1,\"time\":\"speaking\",\"speaker\":8}],\"shooting\":[10]},{\"id\":838,\"name\":\"Siberian\",\"don\":2,\"bonus\":0.2,\"comment\":\"\\u0421\\u0434\\u0435\\u043b\\u0430\\u043b 3 \\u0447\\u0435\\u0440\\u043d\\u044b\\u0435 \\u043f\\u0440\\u043e\\u0432\\u0435\\u0440\\u043a\\u0438\",\"voting\":[[1,1],2,2,8],\"nominating\":[null,2,null,8],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":4}]},{\"id\":815,\"name\":\"Comic\",\"don\":1,\"voting\":[[1,1],4,5,5]},{\"id\":826,\"name\":\"Zvezdochka\",\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[[2,2],4,2],\"nominating\":[null,null,5],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":9},{\"round\":2,\"time\":\"speaking\",\"speaker\":6}]},{\"id\":1141,\"name\":\"Donna-Roza\",\"sheriff\":2,\"role\":\"don\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[[2,2],8,5,5],\"nominating\":[null,9,null,5],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":3}],\"shooting\":[10,9,7]},{\"id\":1020,\"name\":\"Geralt\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[[2,2],4]},{\"id\":1147,\"name\":\"\\u0413\\u0443\\u0434\\u0432\\u0438\\u043d\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[4,5,8],\"voting\":[[2,2]]}]}', 15358, 1),
(4868, 1, 805, 2, 1579984440, 1579988962, 1, 2, 838, 0.575796576662, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4868,\"clubId\":1,\"eventId\":1,\"startTime\":1579984440,\"endTime\":1579988962,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"maf\",\"moderator\":{\"id\":805},\"players\":[{\"id\":761,\"name\":\"Strelok\",\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[3,3],[5,5],8,10,7],\"nominating\":[null,7,null,7,7],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":2},{\"round\":3,\"time\":\"speaking\",\"speaker\":2},{\"round\":3,\"time\":\"voting\",\"votingRound\":0,\"nominee\":7}]},{\"id\":1860,\"name\":\"Metamorphosis\",\"nominating\":[1,6,null,null,1],\"voting\":[[1,1],[8,8],8,10,1],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":1},{\"round\":3,\"time\":\"speaking\",\"speaker\":7},{\"round\":3,\"time\":\"voting\",\"votingRound\":0,\"nominee\":7}]},{\"id\":1143,\"name\":\"Stalker\",\"don\":1,\"sheriff\":2,\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[[1,1],[5,5],8],\"nominating\":[null,5,8],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1}]},{\"id\":1862,\"name\":\"\\u0428\\u043d\\u0443\\u0440\\u043e\\u043a\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[3,5,6],\"voting\":[[1,1]],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":4}]},{\"id\":1959,\"name\":\"Hulk\",\"role\":\"don\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[1,1],[7,8]],\"shooting\":[4]},{\"id\":895,\"name\":\"\\u0444\\u0440\\u0443\\u043a\\u0442\",\"role\":\"sheriff\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[[1,1],[8,8]],\"nominating\":[null,10]},{\"id\":1954,\"name\":\"Hyyy6\",\"role\":\"maf\",\"voting\":[[3,3],[1,5],8,10,1],\"nominating\":[null,9,null,1],\"shooting\":[4,6,3,9]},{\"id\":1043,\"name\":\"Scorpion\",\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"voting\":[[3,3],[6,5],7],\"nominating\":[null,null,7],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":9},{\"round\":2,\"time\":\"speaking\",\"speaker\":1}],\"shooting\":[4,6],\"bonus\":\"bestMove\"},{\"id\":1200,\"name\":\"Djuffin\",\"sheriff\":1,\"death\":{\"round\":4,\"type\":\"night\"},\"voting\":[[3,3],[8,8],8,10],\"nominating\":[null,8,null,10],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":1,\"speaker\":8}]},{\"id\":832,\"name\":\"\\u0423\\u0431\\u0438\\u0439\\u0446\\u0430\",\"death\":{\"round\":3,\"type\":\"day\"},\"nominating\":[3,1],\"voting\":[[3,3],[5,5],8,1],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":1}]}]}', 15358, 1),
(4870, 1, 1139, 1, 1579989931, 1579992853, 1, 2, 805, 0.472840077788, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4870,\"clubId\":1,\"eventId\":1,\"startTime\":1579989931,\"endTime\":1579992853,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":1139},\"players\":[{\"id\":1959,\"name\":\"Hulk\",\"death\":{\"round\":0,\"type\":\"day\"},\"voting\":[[2,2]]},{\"id\":1954,\"name\":\"Hyyy6\",\"death\":{\"round\":0,\"type\":\"day\"},\"voting\":[[1,1]]},{\"id\":832,\"name\":\"\\u0423\\u0431\\u0438\\u0439\\u0446\\u0430\",\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"nominating\":[1,9],\"voting\":[[1,1],9,7],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":5}],\"shooting\":[6,8]},{\"id\":1020,\"name\":\"Geralt\",\"voting\":[[1,1],9,3,10],\"nominating\":[null,null,10],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":5}]},{\"id\":1577,\"name\":\"\\u042f\\u0440\\u043e\\u0441\\u043b\\u0430\\u0432\\u043d\\u0430\",\"sheriff\":2,\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[[1,1],9,3],\"nominating\":[null,null,3]},{\"id\":1141,\"name\":\"Donna-Roza\",\"death\":{\"round\":1,\"type\":\"night\"},\"voting\":[[1,1]]},{\"id\":1147,\"name\":\"\\u0413\\u0443\\u0434\\u0432\\u0438\\u043d\",\"don\":1,\"voting\":[[2,2],9,3,10],\"nominating\":[null,null,null,10],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":10}]},{\"id\":815,\"name\":\"Comic\",\"role\":\"sheriff\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[[2,2],9]},{\"id\":761,\"name\":\"Strelok\",\"sheriff\":1,\"role\":\"don\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[2,2],9],\"shooting\":[6]},{\"id\":1043,\"name\":\"Scorpion\",\"role\":\"maf\",\"death\":{\"round\":3,\"type\":\"day\"},\"nominating\":[2,null,7,7],\"voting\":[[2,2],9,3,7],\"shooting\":[6,8,5]}]}', 15358, 1),
(4871, 1, 805, 1, 1579989908, 1579993311, 1, 2, 838, 0.482345087911, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4871,\"clubId\":1,\"eventId\":1,\"startTime\":1579989908,\"endTime\":1579993311,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":805},\"players\":[{\"id\":1837,\"name\":\"Flanker\",\"sheriff\":4,\"voting\":[[5,5],8,6,5,[4,9]],\"nominating\":[null,8],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":1},{\"round\":3,\"time\":\"voting\",\"votingRound\":0,\"nominee\":4},{\"round\":4,\"votingRound\":1,\"time\":\"voting\"}]},{\"id\":1862,\"name\":\"\\u0428\\u043d\\u0443\\u0440\\u043e\\u043a\",\"death\":{\"round\":1,\"type\":\"day\"},\"nominating\":[1],\"voting\":[[1,1],9],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":2}]},{\"id\":838,\"name\":\"Siberian\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[2,4,9],\"voting\":[[1,1]]},{\"id\":1860,\"name\":\"Metamorphosis\",\"role\":\"maf\",\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[1,1],4,6,5,[9,4]],\"nominating\":[null,5,6,null,8],\"warnings\":[{\"round\":0,\"time\":\"voting\",\"votingRound\":0,\"nominee\":5},{\"round\":4,\"time\":\"speaking\",\"speaker\":1}],\"shooting\":[3,10,9,7]},{\"id\":1200,\"name\":\"Djuffin\",\"sheriff\":3,\"role\":\"don\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[[1,1],2,6,5],\"nominating\":[null,2,null,4],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":8}],\"shooting\":[3,10,7]},{\"id\":9,\"name\":\"Ladushka\",\"sheriff\":2,\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"voting\":[[1,1],8,7],\"nominating\":[null,4,7],\"warnings\":[{\"round\":2,\"time\":\"night kill speaking\"}],\"shooting\":[3,10]},{\"id\":1143,\"name\":\"Stalker\",\"don\":3,\"role\":\"sheriff\",\"death\":{\"round\":4,\"type\":\"night\"},\"voting\":[[5,5],4,6,5],\"nominating\":[null,9,null,5],\"bonus\":\"bestMove\"},{\"id\":826,\"name\":\"Zvezdochka\",\"voting\":[[5,5],2,6,5,[4,4]],\"nominating\":[null,6,null,null,9],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1},{\"round\":1,\"time\":\"day kill speaking\",\"speaker\":2},{\"round\":4,\"time\":\"speaking\",\"speaker\":1}]},{\"id\":1797,\"name\":\"Matilda\",\"don\":1,\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[5,5],2,6,5,[9,9]],\"nominating\":[null,null,null,null,4],\"warnings\":[{\"round\":4,\"time\":\"night kill speaking\"},{\"round\":4,\"time\":\"speaking\",\"speaker\":1}]},{\"id\":895,\"name\":\"\\u0444\\u0440\\u0443\\u043a\\u0442\",\"don\":2,\"sheriff\":1,\"death\":{\"round\":2,\"type\":\"night\"},\"nominating\":[5],\"voting\":[[5,5],2],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":2}]}]}', 15358, 1),
(4872, 1, 1139, 2, 1579994261, 1579996767, 1, 2, 805, 0.605015432796, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4872,\"clubId\":1,\"eventId\":1,\"startTime\":1579994261,\"endTime\":1579996767,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"maf\",\"moderator\":{\"id\":1139},\"players\":[{\"id\":832,\"name\":\"\\u0423\\u0431\\u0438\\u0439\\u0446\\u0430\",\"voting\":[null,[5,7]],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":2}]},{\"id\":1147,\"name\":\"\\u0413\\u0443\\u0434\\u0432\\u0438\\u043d\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[1,3,8],\"voting\":[null]},{\"id\":1200,\"name\":\"Djuffin\",\"sheriff\":2,\"role\":\"maf\",\"voting\":[null,[5,7]],\"nominating\":[null,5,1],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":9}],\"shooting\":[2,8]},{\"id\":761,\"name\":\"Strelok\",\"sheriff\":1,\"voting\":[null,[7,7]],\"nominating\":[null,1,3],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1}]},{\"id\":826,\"name\":\"Zvezdochka\",\"role\":\"maf\",\"voting\":[null,[7,7]],\"nominating\":[null,8,9],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":6},{\"round\":2,\"time\":\"speaking\",\"speaker\":10}],\"shooting\":[2,8]},{\"id\":1143,\"name\":\"Stalker\",\"voting\":[null,[5,5]]},{\"id\":815,\"name\":\"Comic\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[null,[10,7]],\"nominating\":[null,10],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":1,\"speaker\":7}]},{\"id\":9,\"name\":\"Ladushka\",\"don\":1,\"role\":\"sheriff\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[null,[1,7]],\"nominating\":[null,7]},{\"id\":1959,\"name\":\"Hulk\",\"role\":\"don\",\"voting\":[null,[1,7]],\"nominating\":[2],\"shooting\":[2,8]},{\"id\":1837,\"name\":\"Flanker\",\"death\":{\"round\":2,\"type\":\"warnings\",\"time\":{\"round\":2,\"time\":\"voting\",\"votingRound\":0,\"nominee\":3}},\"voting\":[null,[7,7]],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":10},{\"round\":2,\"time\":\"speaking\",\"speaker\":1},{\"round\":2,\"time\":\"speaking\",\"speaker\":1},{\"round\":2,\"time\":\"voting\",\"votingRound\":0,\"nominee\":3}]}]}', 15358, 1),
(4873, 1, 805, 1, 1579994033, 1579997417, 1, 2, 838, 0.490131816949, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4873,\"clubId\":1,\"eventId\":1,\"startTime\":1579994033,\"endTime\":1579997417,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":805},\"players\":[{\"id\":1141,\"name\":\"Donna-Roza\",\"sheriff\":2,\"death\":{\"round\":4,\"type\":\"night\"},\"voting\":[[6,6],6,5,4]},{\"id\":895,\"name\":\"\\u0444\\u0440\\u0443\\u043a\\u0442\",\"death\":{\"round\":2,\"type\":\"night\"},\"nominating\":[1,10],\"voting\":[[6,6],6]},{\"id\":1043,\"name\":\"Scorpion\",\"sheriff\":1,\"role\":\"don\",\"death\":{\"round\":2,\"type\":\"day\"},\"voting\":[[6,6],10,8],\"nominating\":[null,null,8],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1},{\"round\":2,\"time\":\"speaking\",\"speaker\":8},{\"round\":2,\"time\":\"speaking\",\"speaker\":1}],\"shooting\":[7,2]},{\"id\":1020,\"name\":\"Geralt\",\"role\":\"maf\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[[6,6],3,3,5],\"nominating\":[null,3,3,5],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":5},{\"round\":1,\"time\":\"speaking\",\"speaker\":9},{\"round\":3,\"time\":\"speaking\",\"speaker\":1}],\"shooting\":[7,2,10]},{\"id\":1860,\"name\":\"Metamorphosis\",\"voting\":[[6,6],4,3,4,9],\"nominating\":[null,4,null,4,9],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1},{\"round\":2,\"time\":\"speaking\",\"speaker\":8}]},{\"id\":1797,\"name\":\"Matilda\",\"death\":{\"round\":1,\"type\":\"day\"},\"nominating\":[4],\"voting\":[[4,4],4],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":9}]},{\"id\":1577,\"name\":\"\\u042f\\u0440\\u043e\\u0441\\u043b\\u0430\\u0432\\u043d\\u0430\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[3,4,9],\"voting\":[[4,4]]},{\"id\":838,\"name\":\"Siberian\",\"don\":1,\"sheriff\":3,\"nominating\":[6,6,5,null,5],\"voting\":[[4,4],6,3,4,9]},{\"id\":1954,\"name\":\"Hyyy6\",\"role\":\"maf\",\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[4,4],5,3,5,5],\"nominating\":[null,5],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1},{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":6}],\"shooting\":[7,2,10,1]},{\"id\":1862,\"name\":\"\\u0428\\u043d\\u0443\\u0440\\u043e\\u043a\",\"don\":2,\"role\":\"sheriff\",\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[[4,4],3,3],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1}]}]}', 15358, 1),
(4874, 1, 1139, 1, 1579998849, 1580001817, 1, 2, 805, 0.500304076344, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4874,\"clubId\":1,\"eventId\":1,\"startTime\":1579998849,\"endTime\":1580001817,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":1139},\"players\":[{\"id\":1954,\"name\":\"Hyyy6\",\"don\":2,\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[[8,8],5],\"warnings\":[{\"round\":2,\"time\":\"shooting\"}],\"bonus\":\"bestMove\"},{\"id\":1837,\"name\":\"Flanker\",\"role\":\"sheriff\",\"voting\":[[8,8],5,10,7],\"nominating\":[null,5]},{\"id\":1577,\"name\":\"\\u042f\\u0440\\u043e\\u0441\\u043b\\u0430\\u0432\\u043d\\u0430\",\"sheriff\":2,\"voting\":[[8,8],5,10,7],\"nominating\":[null,7,7]},{\"id\":9,\"name\":\"Ladushka\",\"voting\":[[8,8],5,10,7],\"nominating\":[null,6,10,7]},{\"id\":895,\"name\":\"\\u0444\\u0440\\u0443\\u043a\\u0442\",\"sheriff\":1,\"role\":\"maf\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[8,8],2],\"nominating\":[null,4],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":10},{\"round\":1,\"time\":\"speaking\",\"speaker\":1}],\"shooting\":[8]},{\"id\":1147,\"name\":\"\\u0413\\u0443\\u0434\\u0432\\u0438\\u043d\",\"don\":1,\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[[2,2],5,10],\"nominating\":[null,2],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":9},{\"round\":1,\"time\":\"speaking\",\"speaker\":1},{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":4}]},{\"id\":1043,\"name\":\"Scorpion\",\"sheriff\":3,\"role\":\"maf\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[[2,2],5,2,2],\"nominating\":[null,null,null,2],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":2},{\"round\":2,\"time\":\"day kill speaking\",\"speaker\":10},{\"round\":3,\"time\":\"speaking\",\"speaker\":2}],\"shooting\":[8,1,6]},{\"id\":1797,\"name\":\"Matilda\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[4,5,9],\"voting\":[[2,2]]},{\"id\":832,\"name\":\"\\u0423\\u0431\\u0438\\u0439\\u0446\\u0430\",\"nominating\":[2],\"voting\":[[2,2],5,10,7],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":10},{\"round\":2,\"time\":\"speaking\",\"speaker\":3}]},{\"id\":1143,\"name\":\"Stalker\",\"role\":\"don\",\"death\":{\"round\":2,\"type\":\"day\"},\"nominating\":[8,null,2],\"voting\":[[2,2],2,2],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":10}],\"shooting\":[8,1]}]}', 15358, 1),
(4875, 1, 805, 1, 1579998742, 1580001360, 1, 2, 838, 0.468601902214, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4875,\"clubId\":1,\"eventId\":1,\"startTime\":1579998742,\"endTime\":1580001360,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":805},\"players\":[{\"id\":1020,\"name\":\"Geralt\",\"role\":\"don\",\"death\":{\"round\":2,\"type\":\"day\"},\"voting\":[[3,3],[5,3],1],\"shooting\":[7,10]},{\"id\":826,\"name\":\"Zvezdochka\",\"voting\":[[1,1],[9,9],1],\"nominating\":[null,3],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":10},{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":3}]},{\"id\":1959,\"name\":\"Hulk\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[1,1],[9,9]],\"nominating\":[null,9]},{\"id\":1200,\"name\":\"Djuffin\",\"role\":\"sheriff\",\"nominating\":[1,5,1],\"voting\":[[1,1],[3,5],1]},{\"id\":1862,\"name\":\"\\u0428\\u043d\\u0443\\u0440\\u043e\\u043a\",\"role\":\"maf\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[1,1],[5,9]],\"nominating\":[null,4],\"shooting\":[7]},{\"id\":838,\"name\":\"Siberian\",\"don\":1,\"voting\":[[1,1],[3,3],1],\"nominating\":[null,8,2],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":10}]},{\"id\":1141,\"name\":\"Donna-Roza\",\"sheriff\":1,\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[9,3,5],\"voting\":[[3,3]]},{\"id\":761,\"name\":\"Strelok\",\"sheriff\":2,\"nominating\":[3,null,6],\"voting\":[[3,3],[5,5],1],\"warnings\":[{\"round\":2,\"time\":\"night kill speaking\"}]},{\"id\":815,\"name\":\"Comic\",\"role\":\"maf\",\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[[3,3],[3,3]],\"shooting\":[7]},{\"id\":1860,\"name\":\"Metamorphosis\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[[3,3],[9,5]],\"nominating\":[null,1],\"warnings\":[{\"round\":1,\"time\":\"voting\",\"votingRound\":0,\"nominee\":3}]}]}', 15358, 1),
(4876, 1, 805, 1, 1580001969, 1580006364, 1, 2, 838, 0.434218450383, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4876,\"clubId\":1,\"eventId\":1,\"startTime\":1580001969,\"endTime\":1580006364,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"civ\",\"moderator\":{\"id\":805},\"players\":[{\"id\":815,\"name\":\"Comic\",\"voting\":[[4,4],[10,10],10,3,[9,9]],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":7}]},{\"id\":1797,\"name\":\"Matilda\",\"death\":{\"round\":3,\"type\":\"night\"},\"nominating\":[1,10],\"voting\":[[1,1],[10,10],10]},{\"id\":1141,\"name\":\"Donna-Roza\",\"sheriff\":2,\"role\":\"maf\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[[1,1],[10,10],10,4],\"nominating\":[null,null,10],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":6}],\"shooting\":[9,8,2]},{\"id\":895,\"name\":\"\\u0444\\u0440\\u0443\\u043a\\u0442\",\"role\":\"don\",\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[1,1],[10,10],10,3,[9,9]],\"nominating\":[null,9],\"warnings\":[{\"round\":2,\"time\":\"speaking\",\"speaker\":7},{\"round\":3,\"time\":\"speaking\",\"speaker\":8}],\"shooting\":[9,2,2,8]},{\"id\":761,\"name\":\"Strelok\",\"don\":1,\"voting\":[[1,1],[10,10],10,3,[4,4]],\"nominating\":[null,null,null,3,9]},{\"id\":1577,\"name\":\"\\u042f\\u0440\\u043e\\u0441\\u043b\\u0430\\u0432\\u043d\\u0430\",\"voting\":[[1,1],[4,4],3,3,[9,9]],\"nominating\":[null,4,4,null,4]},{\"id\":1837,\"name\":\"Flanker\",\"sheriff\":3,\"voting\":[[4,4],[4,4],3,3,[4,4]],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":7}]},{\"id\":1862,\"name\":\"\\u0428\\u043d\\u0443\\u0440\\u043e\\u043a\",\"don\":2,\"sheriff\":1,\"death\":{\"round\":4,\"type\":\"night\"},\"voting\":[[4,4],[4,4],3,3],\"nominating\":[null,null,3]},{\"id\":1143,\"name\":\"Stalker\",\"don\":3,\"role\":\"sheriff\",\"death\":{\"round\":4,\"type\":\"day\"},\"voting\":[[4,4],[4,4],3,3,[4,4]],\"nominating\":[null,null,null,4],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":5}]},{\"id\":1959,\"name\":\"Hulk\",\"role\":\"maf\",\"death\":{\"round\":2,\"type\":\"day\"},\"nominating\":[4],\"voting\":[[4,4],[4,4],10],\"shooting\":[8,9]}]}', 15358, 1),
(4877, 1, 1139, 2, 1580003004, 1580006037, 1, 2, 805, 0.492756241933, NULL, '000000000000', NULL, NULL, 1, '{\"id\":4877,\"clubId\":1,\"eventId\":1,\"startTime\":1580003004,\"endTime\":1580006037,\"language\":\"ru\",\"rules\":\"0000000000000\",\"features\":\"gsdutclhvnwr\",\"tournamentId\":32,\"winner\":\"maf\",\"moderator\":{\"id\":1139},\"players\":[{\"id\":826,\"name\":\"Zvezdochka\",\"voting\":[null,7,4,[5,6]],\"nominating\":[null,null,8,6],\"warnings\":[{\"round\":1,\"time\":\"night kill speaking\"},{\"round\":3,\"time\":\"voting\",\"votingRound\":0,\"nominee\":5}]},{\"id\":1043,\"name\":\"Scorpion\",\"role\":\"sheriff\",\"death\":{\"round\":1,\"type\":\"night\"},\"legacy\":[1,3,9],\"nominating\":[1],\"voting\":[null]},{\"id\":1860,\"name\":\"Metamorphosis\",\"sheriff\":1,\"role\":\"don\",\"death\":{\"round\":2,\"type\":\"night\"},\"voting\":[null,7],\"nominating\":[null,7],\"shooting\":[2],\"bonus\":\"bestMove\"},{\"id\":1954,\"name\":\"Hyyy6\",\"death\":{\"round\":2,\"type\":\"day\"},\"voting\":[null,3,9],\"nominating\":[null,3,9]},{\"id\":832,\"name\":\"\\u0423\\u0431\\u0438\\u0439\\u0446\\u0430\",\"role\":\"maf\",\"voting\":[null,7,4,[6,6]],\"nominating\":[null,null,4,9],\"warnings\":[{\"round\":3,\"time\":\"speaking\",\"speaker\":9}],\"shooting\":[2,3,8]},{\"id\":1020,\"name\":\"Geralt\",\"death\":{\"round\":3,\"type\":\"day\"},\"voting\":[null,7,4,[5,5]],\"nominating\":[null,null,null,5],\"warnings\":[{\"round\":1,\"time\":\"night kill speaking\"}]},{\"id\":9,\"name\":\"Ladushka\",\"don\":1,\"death\":{\"round\":1,\"type\":\"day\"},\"voting\":[null,3],\"warnings\":[{\"round\":1,\"time\":\"shooting\"},{\"round\":1,\"time\":\"night kill speaking\"}]},{\"id\":1200,\"name\":\"Djuffin\",\"death\":{\"round\":3,\"type\":\"night\"},\"voting\":[null,3,9],\"warnings\":[{\"round\":0,\"time\":\"speaking\",\"speaker\":5},{\"round\":2,\"time\":\"shooting\"},{\"round\":2,\"time\":\"speaking\",\"speaker\":10}]},{\"id\":1147,\"name\":\"\\u0413\\u0443\\u0434\\u0432\\u0438\\u043d\",\"voting\":[null,7,4,[1,5]],\"warnings\":[{\"round\":1,\"time\":\"speaking\",\"speaker\":1}]},{\"id\":838,\"name\":\"Siberian\",\"role\":\"maf\",\"voting\":[null,3,4,[6,6]],\"nominating\":[null,null,null,1],\"warnings\":[{\"round\":3,\"time\":\"voting\",\"votingRound\":0,\"nominee\":9}],\"shooting\":[2,3,8],\"bonus\":\"bestPlayer\"}]}', 15358, 1);

INSERT INTO `players` (`game_id`, `user_id`, `nick_name`, `number`, `role`, `voted_civil`, `voted_mafia`, `voted_sheriff`, `voted_by_civil`, `voted_by_mafia`, `voted_by_sheriff`, `nominated_civil`, `nominated_mafia`, `nominated_sheriff`, `nominated_by_civil`, `nominated_by_mafia`, `nominated_by_sheriff`, `kill_round`, `kill_type`, `warns`, `was_arranged`, `checked_by_don`, `checked_by_sheriff`, `won`, `flags`, `rating_before`, `rating_earned`, `extra_points`, `extra_points_reason`, `game_end_time`, `role_rating_before`, `rating_lock_until`, `is_rating`, `mr_points`) VALUES
(4842, 9, 'Ladushka', 2, 2, 1, 1, 0, 4, 0, 1, 1, 1, 0, 1, 1, 1, 2, 1, 1, -1, -1, 1, 0, 1048597, 141.3640617772099, -6.08592832607, 0, NULL, 1579988247, 92.31161950408004, 1611524247, 1, -0.700729242677),
(4842, 815, 'Comic', 6, 0, 2, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 0, -1, 1, -1, 1, 1048715, 32.54730686119998, 8.11457110143, 0, NULL, 1579988247, 32.06496965123998, 1609814247, 1, 0.116087171908),
(4842, 826, 'Zvezdochka', 7, 0, 0, 2, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 3, 2, 2, -1, -1, -1, 1, 1049099, 4.752197869240005, 8.11457110143, 0, NULL, 1579988247, -7.436334632040039, 1611524247, 1, 0.281046747112),
(4842, 838, 'Siberian', 5, 0, 0, 3, 0, 2, 3, 0, 0, 2, 0, 1, 1, 1, -1, 0, 1, -1, 2, -1, 1, 5275787, 143.99850265938105, 8.11457110143, 0.2, 'Сделал 3 черные проверки', 1579988247, -19.12997816299901, 1611524247, 1, 0.395870795272),
(4842, 1020, 'Geralt', 9, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 2, 2, 0, -1, -1, -1, 1, 1049099, 322.8067064447829, 8.11457110143, 0, NULL, 1579988247, 164.9125475855831, 1611524247, 1, 0.116087171908),
(4842, 1141, 'Donna-Roza', 8, 3, 2, 1, 0, 2, 2, 1, 2, 0, 0, 2, 0, 0, 3, 1, 1, -1, -1, 2, 0, 1048597, 214.92470244720306, -6.08592832607, 0, NULL, 1579988247, 163.43130804381002, 1611524247, 1, -1.01528014589),
(4842, 1147, 'Гудвин', 10, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, -1, 1, 1051403, 277.244674157903, 8.11457110143, 0, NULL, 1579988247, 214.713500864103, 1611524247, 1, 0.445219311265),
(4842, 1577, 'Ярославна', 1, 0, 0, 3, 0, 0, 1, 0, 0, 1, 0, 0, 2, 0, -1, 0, 0, -1, -1, -1, 1, 1081483, 47.21489039898, 8.11457110143, 0, NULL, 1579988247, 52.97203565074001, 1611524247, 1, 0.471067025319),
(4842, 1797, 'Matilda', 3, 1, 0, 3, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, -1, 0, 0, -1, -1, -1, 1, 1081483, 214.74533107325703, 8.11457110143, 0, NULL, 1579988247, 131.856226187977, 1611524247, 1, 0.471067025319),
(4842, 1837, 'Flanker', 4, 2, 1, 0, 0, 4, 0, 1, 1, 1, 0, 0, 1, 0, 1, 1, 2, -1, -1, 3, 0, 1048597, 11.267540633507995, -6.08592832607, 0, NULL, 1579988247, 3.450929605159999, 1611524247, 1, -1.02565517081),
(4868, 761, 'Strelok', 1, 0, 1, 4, 0, 2, 2, 0, 0, 3, 0, 3, 1, 0, 4, 1, 3, -1, -1, -1, 0, 5, 281.61105721896996, -6.90955891994, 0, NULL, 1579988962, 64.70903261008999, 1601957000, 1, 0.592453811835),
(4868, 832, 'Убийца', 10, 0, 1, 3, 0, 3, 1, 0, 2, 0, 0, 1, 0, 1, 3, 1, 1, -1, -1, -1, 0, 5, 206.99001901179994, -6.90955891994, 0, NULL, 1579988962, 161.48979168477, 1611524962, 1, 0.328625030322),
(4868, 895, 'фрукт', 6, 1, 0, 2, 0, 0, 1, 0, 1, 0, 0, 1, 0, 0, 2, 2, 0, -1, -1, -1, 0, 517, 225.448613562883, -6.90955891994, 0, NULL, 1579988962, 62.018840275292995, 1610608172, 1, 0.207159596098),
(4868, 1043, 'Scorpion', 8, 2, 0, 2, 1, 9, 2, 2, 0, 1, 0, 2, 0, 0, 2, 1, 2, -1, -1, -1, 1, 4194371, 55.129737121312985, 9.21274522659, 0, NULL, 1579988962, 7.429155273469995, 1611524962, 1, -0.843989220646),
(4868, 1143, 'Stalker', 3, 0, 0, 3, 0, 0, 0, 0, 0, 2, 0, 1, 0, 0, 3, 2, 1, -1, 1, 2, 0, 33285, 210.3969648416231, -6.90955891994, 0, NULL, 1579988962, 109.66272173677298, 1611524962, 1, 0.328625030322),
(4868, 1200, 'Djuffin', 9, 0, 1, 3, 0, 0, 0, 0, 1, 1, 0, 0, 1, 0, 4, 2, 1, -1, -1, 1, 0, 517, 12.84228504907696, -6.90955891994, 0, NULL, 1579988962, -31.768908429482984, 1611524962, 1, 0.207159596098),
(4868, 1860, 'Metamorphosis', 2, 0, 2, 3, 0, 0, 0, 0, 2, 0, 1, 0, 0, 0, -1, 0, 3, -1, -1, -1, 0, 5, 133.20516457646994, -6.90955891994, 0, NULL, 1579988962, 109.40536570383998, 1611524962, 1, -0.288638956905),
(4868, 1862, 'Шнурок', 4, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 1, -1, -1, -1, 0, 8389381, -5.717267595108998, -6.90955891994, 0, NULL, 1579988962, -13.495136807678989, 1611524962, 1, 0),
(4868, 1954, 'Hyyy6', 7, 2, 3, 2, 0, 1, 2, 0, 2, 0, 0, 3, 1, 0, -1, 0, 0, -1, -1, -1, 1, 131, -9.927733806607003, 9.21274522659, 0, NULL, 1579988962, 26.894626290518996, 1611524962, 1, -0.263828781513),
(4868, 1959, 'Hulk', 5, 3, 0, 2, 0, 6, 2, 0, 0, 0, 0, 1, 0, 0, 1, 1, 0, -1, -1, -1, 1, 3, 11.523585453227998, 9.21274522659, 0, NULL, 1579988962, 4.451629904080001, 1611524962, 1, -0.763364658613),
(4870, 761, 'Strelok', 9, 3, 0, 1, 0, 3, 3, 1, 0, 0, 0, 0, 1, 0, 1, 1, 0, -1, -1, 1, 0, 5, 274.70149829902994, -6.32591906655, 0, NULL, 1579992853, 216.90202460887988, 1604376200, 1, -0.494619749827),
(4870, 815, 'Comic', 8, 1, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2, 0, -1, -1, -1, 1, 515, 40.66187796262998, 8.43455875539, 0, NULL, 1579992853, 40.17954075266998, 1611528853, 1, 0.123654937457),
(4870, 832, 'Убийца', 3, 2, 1, 1, 0, 3, 1, 0, 1, 1, 0, 1, 0, 0, 2, 1, 1, -1, -1, -1, 0, 5, 200.08046009185995, -6.32591906655, 0, NULL, 1579992853, 45.50022732703, 1611528853, 1, -0.820217863148),
(4870, 1020, 'Geralt', 4, 0, 0, 3, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, -1, 0, 1, -1, -1, -1, 1, 32899, 330.9212775462129, 8.43455875539, 0, NULL, 1579992853, 173.0271186870131, 1611528853, 1, 0.730117966862),
(4870, 1043, 'Scorpion', 10, 2, 1, 2, 0, 2, 0, 0, 3, 0, 0, 2, 0, 0, 3, 1, 0, -1, -1, -1, 0, 5, 64.34248234790299, -6.32591906655, 0, NULL, 1579992853, 16.641900500059997, 1611528853, 1, -0.666114150046),
(4870, 1141, 'Donna-Roza', 6, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, -1, 1, 771, 208.83877412113307, 8.43455875539, 0, NULL, 1579992853, 51.49339440339296, 1611528853, 1, 0),
(4870, 1147, 'Гудвин', 7, 0, 0, 3, 0, 0, 2, 0, 0, 1, 0, 0, 2, 0, -1, 0, 1, -1, 1, -1, 1, 32899, 285.35924525933297, 8.43455875539, 0, NULL, 1579992853, 222.82807196553298, 1611528853, 1, 0.730117966862),
(4870, 1577, 'Ярославна', 5, 0, 0, 2, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 3, 2, 0, -1, -1, 2, 1, 515, 55.329461500410005, 8.43455875539, 0, NULL, 1579992853, 61.08660675217001, 1611528853, 1, 0.397060891839),
(4870, 1954, 'Hyyy6', 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, -1, -1, -1, 1, 3, -0.7149885800170033, 8.43455875539, 0, NULL, 1579992853, -36.82236009712601, 1611528853, 1, 0),
(4870, 1959, 'Hulk', 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 1, 0, -1, -1, -1, 1, 3, 20.736330679817996, 8.43455875539, 0, NULL, 1579992853, 7.071955549148001, 1611528853, 1, 0),
(4871, 9, 'Ladushka', 6, 2, 1, 0, 1, 3, 2, 1, 0, 1, 1, 1, 1, 0, 2, 1, 1, -1, -1, 2, 0, 131077, 135.27813345113992, -6.21185894507, 0, NULL, 1579993311, 86.22569117801004, 1611529311, 1, -0.435127985373),
(4871, 826, 'Zvezdochka', 8, 0, 1, 4, 0, 1, 1, 0, 1, 1, 0, 1, 1, 0, -1, 0, 3, -1, -1, -1, 1, 131203, 12.866768970670005, 8.28247859342, 0, NULL, 1579993311, 0.6782364693899607, 1611529311, 1, 0.659199601394),
(4871, 838, 'Siberian', 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, -1, 1, 8520451, 152.11307376081103, 8.28247859342, 0, NULL, 1579993311, -11.01540706156901, 1611529311, 1, 0),
(4871, 895, 'фрукт', 10, 0, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 2, 2, 1, -1, 2, 1, 1, 131587, 218.539054642943, 8.28247859342, 0, NULL, 1579993311, 55.10928135535299, 1611529311, 1, 0),
(4871, 1143, 'Stalker', 7, 1, 0, 3, 0, 0, 1, 0, 1, 1, 0, 0, 1, 0, 4, 2, 0, -1, 3, -1, 1, 4358723, 203.4874059216831, 8.28247859342, 0, NULL, 1579993311, 102.75316281683298, 1611529311, 1, 0.532185881563),
(4871, 1200, 'Djuffin', 5, 3, 1, 2, 0, 3, 2, 1, 1, 1, 0, 1, 1, 1, 3, 1, 1, -1, -1, 3, 0, 131077, 5.9327261291369595, -6.21185894507, 0, NULL, 1579993311, 44.611193478560004, 1611529311, 1, -0.454904534763),
(4871, 1797, 'Matilda', 9, 0, 3, 2, 0, 4, 1, 0, 0, 1, 0, 1, 0, 1, 4, 1, 2, -1, 1, -1, 1, 131075, 222.85990217468702, 8.28247859342, 0, NULL, 1579993311, 139.970797289407, 1611529311, 1, 0.222508130034),
(4871, 1837, 'Flanker', 1, 0, 2, 3, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, -1, 0, 3, -1, -1, 4, 1, 131203, 5.1816123074379945, 8.28247859342, 0, NULL, 1579993311, 7.816611028347996, 1611529311, 1, -0.0238366086695),
(4871, 1860, 'Metamorphosis', 4, 2, 1, 4, 0, 3, 2, 1, 1, 2, 0, 1, 2, 0, 4, 1, 2, -1, -1, -1, 0, 131077, 126.29560565652994, -6.21185894507, 0, NULL, 1579993311, 23.799798872630006, 1611529311, 1, -0.746369222888),
(4871, 1862, 'Шнурок', 2, 0, 1, 0, 0, 3, 1, 0, 1, 0, 0, 0, 1, 0, 1, 1, 1, -1, -1, -1, 1, 131075, -12.626826515048998, 8.28247859342, 0, NULL, 1579993311, -20.40469572761899, 1611529311, 1, 0),
(4872, 9, 'Ladushka', 8, 1, 2, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, 2, 2, 0, -1, 1, -1, 0, 393749, 129.06627450606993, -7.26018519355, 0, NULL, 1579996767, 49.05244227313009, 1611532767, 1, 0),
(4872, 761, 'Strelok', 4, 0, 2, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, -1, 0, 1, -1, -1, 1, 0, 393237, 268.37557923247994, -7.26018519355, 0, NULL, 1579996767, 57.799473690149995, 1606795400, 1, 0),
(4872, 815, 'Comic', 7, 0, 2, 0, 0, 6, 4, 1, 1, 0, 0, 0, 0, 1, 1, 1, 1, -1, -1, -1, 0, 393237, 49.09643671801998, -7.26018519355, 0, NULL, 1579996767, 48.61409950805998, 1611532767, 1, 0),
(4872, 826, 'Zvezdochka', 5, 2, 2, 0, 0, 3, 1, 0, 0, 1, 1, 0, 1, 0, -1, 0, 2, -1, -1, -1, 1, 393355, 21.149247564090004, 9.68024692473, 0, NULL, 1579996767, 12.18853250128001, 1611532767, 1, -0.309677751528),
(4872, 832, 'Убийца', 1, 0, 1, 1, 0, 0, 1, 1, 0, 0, 0, 1, 1, 0, -1, 0, 1, -1, -1, -1, 0, 393237, 193.75454102530995, -7.26018519355, 0, NULL, 1579996767, 154.58023276483001, 1611532767, 1, 0),
(4872, 1143, 'Stalker', 6, 0, 0, 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 0, -1, -1, -1, 0, 393237, 211.7698845151031, -7.26018519355, 0, NULL, 1579996767, 111.03564141025298, 1611532767, 1, 0.309677751528),
(4872, 1147, 'Гудвин', 2, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 1, 2, 0, -1, -1, -1, 0, 8782613, 293.793804014723, -7.26018519355, 0, NULL, 1579996767, 231.26263072092297, 1611532767, 1, 0),
(4872, 1200, 'Djuffin', 3, 2, 1, 1, 0, 0, 0, 0, 1, 1, 0, 1, 0, 0, -1, 0, 1, -1, -1, 2, 1, 393355, -0.27913281593304085, 9.68024692473, 0, NULL, 1579996767, 38.399334533490006, 1611532767, 1, 0),
(4872, 1837, 'Flanker', 10, 0, 2, 0, 0, 1, 0, 0, 0, 0, 0, 1, 0, 0, 2, 3, 4, -1, -1, -1, 0, 397333, 13.464090900857995, -7.26018519355, 0, NULL, 1579996767, 16.099089621767995, 1611532767, 1, 0),
(4872, 1959, 'Hulk', 9, 3, 2, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0, -1, 0, 0, -1, -1, -1, 1, 393355, 29.170889435207997, 9.68024692473, 0, NULL, 1579996767, 13.66437513067, 1611532767, 1, 0),
(4873, 838, 'Siberian', 8, 0, 1, 3, 0, 0, 1, 0, 4, 0, 0, 0, 1, 0, -1, 0, 0, -1, 1, 3, 1, 131203, 160.39555235423103, 8.15789092882, 0, NULL, 1579997417, -2.7329284681490087, 1611533417, 1, 0.688018348062),
(4873, 895, 'фрукт', 2, 0, 1, 0, 0, 0, 0, 0, 1, 0, 1, 0, 0, 0, 2, 2, 0, -1, -1, -1, 1, 131587, 226.821533236363, 8.15789092882, 0, NULL, 1579997417, 63.39175994877299, 1611533417, 1, 0),
(4873, 1020, 'Geralt', 4, 2, 1, 2, 0, 5, 0, 0, 1, 2, 0, 3, 0, 0, 3, 1, 3, -1, -1, -1, 0, 131077, 339.3558363016029, -6.11841819662, 0, NULL, 1579997417, 157.89415885919993, 1611533417, 1, -1.23485460015),
(4873, 1043, 'Scorpion', 3, 3, 1, 0, 1, 2, 3, 2, 1, 0, 0, 0, 2, 0, 2, 1, 3, -1, -1, 1, 0, 131077, 58.01656328135299, -6.11841819662, 0, NULL, 1579997417, 10.315981433509997, 1611533417, 1, -0.875964920867),
(4873, 1141, 'Donna-Roza', 1, 0, 2, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 4, 2, 0, -1, -1, 2, 1, 131587, 217.27333287652306, 8.15789092882, 0, NULL, 1579997417, 59.92795315878296, 1611533417, 1, -0.251523281325),
(4873, 1577, 'Ярославна', 7, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, -1, 1, 132867, 63.764020255800006, 8.15789092882, 0, NULL, 1579997417, 69.52116550756001, 1611533417, 1, 1.15284441772),
(4873, 1797, 'Matilda', 6, 0, 0, 1, 0, 3, 0, 0, 0, 1, 0, 2, 0, 0, 1, 1, 1, -1, -1, -1, 1, 131075, 231.14238076810702, 8.15789092882, 0, NULL, 1579997417, 148.25327588282698, 1611533417, 1, 0.187979447069),
(4873, 1860, 'Metamorphosis', 5, 0, 0, 4, 0, 1, 4, 0, 0, 3, 0, 2, 2, 0, -1, 0, 2, -1, -1, -1, 1, 163971, 120.08374671145994, 8.15789092882, 0, NULL, 1579997417, 102.49580678389998, 1611533417, 1, 0.688018348062),
(4873, 1862, 'Шнурок', 10, 1, 0, 2, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 3, 2, 1, -1, 2, -1, 1, 131587, -4.344347921628998, 8.15789092882, 0, NULL, 1579997417, -12.122217134198989, 1611533417, 1, 0.273623153544),
(4873, 1954, 'Hyyy6', 9, 2, 3, 1, 0, 2, 0, 0, 1, 0, 0, 1, 0, 0, 4, 1, 2, -1, -1, -1, 0, 131077, 7.719570175372997, -6.11841819662, 0, NULL, 1579997417, 36.107371517108994, 1611533417, 1, -1.08021614071),
(4874, 9, 'Ladushka', 4, 0, 0, 3, 0, 0, 0, 0, 1, 2, 0, 0, 1, 0, -1, 0, 0, -1, -1, -1, 1, 32907, 121.80608931251993, 7.9951347785, 0, NULL, 1580001817, 41.79225707958009, 1611537817, 1, 0.381061894423),
(4874, 832, 'Убийца', 9, 0, 0, 3, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, -1, 0, 2, -1, -1, -1, 1, 32907, 186.49435583175995, 7.9951347785, 0, NULL, 1580001817, 147.32004757128, 1611537817, 1, 0.381061894423),
(4874, 895, 'фрукт', 5, 2, 0, 0, 1, 5, 1, 1, 1, 0, 0, 0, 0, 1, 1, 1, 2, -1, -1, 1, 0, 21, 234.979424165183, -5.99635108388, 0, NULL, 1580001817, 163.42977328759002, 1611537817, 1, -0.4986443704),
(4874, 1043, 'Scorpion', 7, 2, 0, 1, 2, 3, 0, 1, 0, 0, 1, 3, 0, 0, 3, 1, 3, -1, -1, 3, 0, 21, 51.89814508473299, -5.99635108388, 0, NULL, 1580001817, 4.197563236889996, 1611537817, 1, -0.498135369044),
(4874, 1143, 'Stalker', 10, 3, 0, 0, 2, 4, 0, 1, 1, 0, 1, 1, 0, 0, 2, 1, 1, -1, -1, -1, 0, 21, 204.5096993215531, -5.99635108388, 0, NULL, 1580001817, 100.73424310485002, 1611537817, 1, -0.867103285479),
(4874, 1147, 'Гудвин', 6, 0, 0, 2, 0, 0, 0, 0, 0, 0, 1, 1, 0, 0, 3, 2, 3, -1, 1, -1, 1, 523, 286.533618821173, 7.9951347785, 0, NULL, 1580001817, 224.00244552737297, 1611537817, 1, 0.256528052162),
(4874, 1577, 'Ярославна', 3, 0, 0, 3, 0, 0, 0, 0, 0, 2, 0, 0, 0, 0, -1, 0, 0, -1, -1, 2, 1, 32907, 71.92191118462, 7.9951347785, 0, NULL, 1580001817, 77.67905643638001, 1611537817, 1, 0.381061894423),
(4874, 1797, 'Matilda', 8, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, 1, 2, 0, -1, -1, -1, 1, 8389387, 239.300271696927, 7.9951347785, 0, NULL, 1580001817, 156.41116681164698, 1611537817, 1, 0),
(4874, 1837, 'Flanker', 2, 1, 0, 3, 0, 0, 5, 0, 0, 1, 0, 2, 2, 0, -1, 0, 0, -1, -1, -1, 1, 32907, 6.203905707307995, 7.9951347785, 0, NULL, 1580001817, 8.838904428217994, 1611537817, 1, 0.381061894423),
(4874, 1954, 'Hyyy6', 1, 0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2, 2, 1, -1, 2, -1, 1, 4194891, 1.6011519787529966, 7.9951347785, 0, NULL, 1580001817, -28.387801341736008, 1611537817, 1, 0.0831073950666),
(4875, 761, 'Strelok', 8, 0, 0, 3, 0, 0, 0, 0, 2, 0, 0, 1, 0, 0, -1, 0, 1, -1, -1, 2, 1, 32899, 261.11539403892994, 8.50236956458, 0, NULL, 1580001360, 50.539288496599994, 1609214600, 1, 0.40621271876),
(4875, 815, 'Comic', 9, 2, 2, 0, 0, 5, 1, 0, 0, 0, 0, 1, 0, 0, 1, 1, 0, -1, -1, -1, 0, 5, 41.83625152446998, -6.37677717343, 0, NULL, 1580001360, 0.4823372099600003, 1611537360, 1, -0.811038727975),
(4875, 826, 'Zvezdochka', 2, 0, 0, 3, 0, 0, 0, 0, 1, 0, 0, 1, 0, 0, -1, 0, 2, -1, -1, -1, 1, 32899, 30.829494488820004, 8.50236956458, 0, NULL, 1580001360, 8.96071506280996, 1611537360, 1, 0.422594563182),
(4875, 838, 'Siberian', 6, 0, 2, 1, 0, 0, 0, 0, 2, 0, 0, 1, 0, 0, -1, 0, 1, -1, 1, -1, 1, 131, 168.55344328305102, 8.50236956458, 0, NULL, 1580001360, 5.424962460670992, 1611537360, 1, 0.236915401747),
(4875, 1020, 'Geralt', 1, 3, 1, 2, 0, 3, 1, 1, 0, 0, 0, 1, 0, 2, 2, 1, 0, -1, -1, -1, 0, 5, 333.2374181049829, -6.37677717343, 0, NULL, 1580001360, 151.77574066257992, 1611537360, 1, -0.947661606987),
(4875, 1141, 'Donna-Roza', 7, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, 1, 1, 2819, 225.43122380534305, 8.50236956458, 0, NULL, 1580001360, 68.08584408760296, 1611537360, 1, 0.439680405104),
(4875, 1200, 'Djuffin', 4, 1, 1, 2, 0, 0, 0, 0, 0, 3, 0, 0, 1, 0, -1, 0, 0, -1, -1, -1, 1, 131, 9.401114108796959, 8.50236956458, 0, NULL, 1580001360, -38.67846734942299, 1611537360, 1, 0.40621271876),
(4875, 1860, 'Metamorphosis', 10, 0, 0, 2, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 2, 2, 1, -1, -1, -1, 1, 515, 128.24163764027995, 8.50236956458, 0, NULL, 1580001360, 110.65369771271997, 1611537360, 1, 0.169297317013),
(4875, 1862, 'Шнурок', 5, 2, 0, 2, 0, 3, 2, 1, 0, 0, 1, 0, 0, 1, 1, 1, 0, -1, -1, -1, 0, 5, 3.8135430071910026, -6.37677717343, 0, NULL, 1580001360, 7.77786921257001, 1611537360, 1, -0.947572356143),
(4875, 1959, 'Hulk', 3, 0, 0, 2, 0, 2, 3, 1, 0, 1, 0, 2, 0, 0, 1, 1, 0, -1, -1, -1, 1, 3, 38.851136359937996, 8.50236956458, 0, NULL, 1580001360, 15.506514304538001, 1611537360, 1, 0.185679161435),
(4876, 761, 'Strelok', 5, 0, 0, 6, 0, 0, 0, 0, 0, 1, 1, 0, 0, 0, -1, 0, 0, -1, 1, -1, 1, 32899, 269.61776360350996, 9.05250479387, 0, NULL, 1580006364, 59.041658061179994, 1611542364, 1, 0.445011567468),
(4876, 815, 'Comic', 1, 0, 0, 4, 2, 0, 0, 0, 0, 0, 0, 1, 0, 0, -1, 0, 1, -1, -1, -1, 1, 131, 35.45947435103998, 9.05250479387, 0, NULL, 1580006364, 41.35391431450998, 1611542364, 1, 0.261841207665),
(4876, 895, 'фрукт', 4, 3, 0, 4, 2, 10, 3, 4, 0, 0, 1, 3, 1, 1, 4, 1, 2, -1, -1, -1, 0, 5, 228.983073081303, -6.7893785954, 0, NULL, 1580006364, 157.43342220371002, 1611542364, 1, -0.980655575168),
(4876, 1141, 'Donna-Roza', 3, 2, 0, 4, 0, 8, 1, 2, 0, 1, 0, 2, 0, 0, 3, 1, 1, -1, -1, 2, 0, 32773, 233.93359336992305, -6.7893785954, 0, NULL, 1580006364, 157.34537971774003, 1611542364, 1, -0.468555911495),
(4876, 1143, 'Stalker', 9, 1, 0, 6, 0, 4, 2, 0, 0, 1, 0, 1, 1, 0, 4, 1, 1, -1, 3, -1, 1, 32771, 198.51334823767309, 9.05250479387, 0, NULL, 1580006364, 103.77545621670298, 1611542364, 1, 0.29550898253),
(4876, 1577, 'Ярославна', 6, 0, 0, 4, 2, 0, 0, 0, 0, 3, 0, 0, 0, 0, -1, 0, 0, -1, -1, -1, 1, 131, 79.91704596312, 9.05250479387, 0, NULL, 1580006364, 85.67419121488001, 1611542364, 1, 0.29550898253),
(4876, 1797, 'Matilda', 2, 0, 0, 3, 0, 0, 0, 0, 1, 1, 0, 0, 0, 0, 3, 2, 0, -1, -1, -1, 1, 33283, 247.295406475427, 9.05250479387, 0, NULL, 1580006364, 164.40630159014697, 1611542364, 1, 0.219838609295),
(4876, 1837, 'Flanker', 7, 0, 0, 6, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, -1, 0, 1, -1, -1, 3, 1, 32899, 14.199040485807995, 9.05250479387, 0, NULL, 1580006364, 16.834039206717993, 1611542364, 1, 0.29550898253),
(4876, 1862, 'Шнурок', 8, 0, 0, 4, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 4, 2, 0, -1, 2, 1, 1, 33283, -2.5632341662389972, 9.05250479387, 0, NULL, 1580006364, -3.9643262053789883, 1611542364, 1, 0.29550898253),
(4876, 1959, 'Hulk', 10, 2, 0, 3, 0, 9, 7, 0, 0, 1, 0, 1, 1, 0, 2, 1, 0, -1, -1, -1, 0, 32773, 47.353505924518, -6.7893785954, 0, NULL, 1580006364, 23.344622055400002, 1611542364, 1, -0.659515827886),
(4877, 9, 'Ladushka', 7, 0, 0, 1, 0, 3, 2, 0, 0, 0, 0, 0, 1, 0, 1, 1, 2, -1, 1, -1, 0, 524309, 129.80122409101992, -5.91307490319, 0, NULL, 1580006037, 49.78739185808009, 1611542037, 1, 0.130741155404),
(4877, 826, 'Zvezdochka', 1, 0, 3, 1, 0, 1, 0, 0, 2, 0, 0, 0, 1, 1, -1, 0, 2, -1, -1, -1, 0, 524309, 39.331864053400004, -5.91307490319, 0, NULL, 1580006037, 17.46308462738996, 1611542037, 1, -0.876118079234),
(4877, 832, 'Убийца', 5, 2, 4, 0, 0, 4, 0, 0, 2, 0, 0, 1, 0, 0, -1, 0, 1, -1, -1, -1, 1, 589963, 194.48949061025994, 7.88409987092, 0, NULL, 1580006037, 39.17430826048, 1611542037, 1, -0.548052603839),
(4877, 838, 'Siberian', 10, 2, 3, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, -1, 0, 1, -1, -1, -1, 1, 4718763, 177.05581284763102, 7.88409987092, 0, NULL, 1580006037, 163.12848082238, 1611542037, 1, 0),
(4877, 1020, 'Geralt', 6, 0, 2, 2, 0, 1, 4, 0, 0, 1, 0, 1, 0, 0, 3, 1, 1, -1, -1, -1, 0, 524309, 326.8606409315529, -5.91307490319, 0, NULL, 1580006037, 181.46167744240307, 1611542037, 1, 0.27402630192),
(4877, 1043, 'Scorpion', 2, 1, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 0, 0, 1, 2, 0, -1, -1, -1, 0, 8913685, 45.90179400085299, -5.91307490319, 0, NULL, 1580006037, 47.700581847843, 1611542037, 1, 0),
(4877, 1147, 'Гудвин', 9, 0, 3, 1, 0, 2, 0, 0, 0, 0, 0, 1, 1, 0, -1, 0, 1, -1, -1, -1, 0, 524309, 294.528753599673, -5.91307490319, 0, NULL, 1580006037, 231.99758030587296, 1611542037, 1, 0.27402630192),
(4877, 1200, 'Djuffin', 8, 0, 1, 1, 0, 0, 0, 0, 0, 0, 0, 1, 0, 0, 3, 2, 3, -1, -1, -1, 0, 524821, 17.90348367337696, -5.91307490319, 0, NULL, 1580006037, -30.176097784842987, 1611542037, 1, 0.130741155404),
(4877, 1860, 'Metamorphosis', 3, 3, 1, 0, 0, 3, 1, 0, 1, 0, 0, 1, 0, 0, 2, 2, 0, -1, -1, 1, 1, 4719179, 136.74400720485994, 7.88409987092, 0, NULL, 1580006037, 17.587939927560008, 1611542037, 1, -0.392223466213),
(4877, 1954, 'Hyyy6', 4, 0, 1, 1, 0, 3, 2, 0, 1, 1, 0, 0, 1, 0, 2, 1, 0, -1, -1, -1, 0, 524309, 9.596286757252997, -5.91307490319, 0, NULL, 1580006037, -20.39266656323601, 1611542037, 1, 0.130741155404);

INSERT INTO `mafiosos` (`game_id`, `user_id`, `shots1_ok`, `shots1_miss`, `shots2_ok`, `shots2_miss`, `shots2_blank`, `shots2_rearrange`, `shots3_ok`, `shots3_miss`, `shots3_blank`, `shots3_fail`, `shots3_rearrange`, `is_don`) VALUES
(4842, 9, 0, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4842, 1141, 1, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 1),
(4842, 1837, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0),
(4868, 1043, 0, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4868, 1954, 2, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4868, 1959, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1),
(4870, 761, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1),
(4870, 832, 0, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4870, 1043, 1, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4871, 9, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 2, 0),
(4871, 1200, 0, 0, 0, 1, 0, 0, 2, 0, 0, 0, 2, 1),
(4871, 1860, 1, 0, 0, 1, 0, 0, 2, 0, 0, 0, 2, 0),
(4872, 826, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 2, 0),
(4872, 1200, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 2, 0),
(4872, 1959, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 2, 1),
(4873, 1020, 0, 0, 1, 0, 0, 1, 2, 0, 0, 0, 2, 0),
(4873, 1043, 0, 0, 0, 0, 0, 0, 2, 0, 0, 0, 2, 1),
(4873, 1954, 1, 0, 1, 0, 0, 1, 2, 0, 0, 0, 2, 0),
(4874, 895, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0),
(4874, 1043, 1, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 0),
(4874, 1143, 0, 0, 1, 0, 0, 1, 1, 0, 0, 0, 1, 1),
(4875, 815, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0),
(4875, 1020, 1, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 1),
(4875, 1862, 0, 0, 0, 0, 0, 0, 1, 0, 0, 0, 1, 0),
(4876, 895, 1, 0, 1, 0, 0, 1, 0, 2, 0, 0, 0, 1),
(4876, 1141, 0, 0, 1, 0, 0, 1, 0, 2, 0, 0, 0, 0),
(4876, 1959, 0, 0, 0, 0, 0, 0, 0, 1, 0, 1, 0, 0),
(4877, 832, 0, 0, 1, 0, 0, 1, 1, 1, 0, 0, 1, 0),
(4877, 838, 0, 0, 1, 0, 0, 1, 1, 1, 0, 0, 1, 0),
(4877, 1860, 0, 0, 0, 0, 0, 0, 1, 0, 1, 0, 1, 1);

INSERT INTO `dons` (`game_id`, `user_id`, `sheriff_found`, `sheriff_arranged`) VALUES
(4842, 1141, -1, -1),
(4868, 1959, -1, -1),
(4870, 761, -1, -1),
(4871, 1200, 3, -1),
(4872, 1959, 1, -1),
(4873, 1043, 2, -1),
(4874, 1143, -1, -1),
(4875, 1020, -1, -1),
(4876, 895, 3, -1),
(4877, 1860, -1, -1);

INSERT INTO `sheriffs` (`game_id`, `user_id`, `civil_found`, `mafia_found`) VALUES
(4842, 1797, 0, 3),
(4868, 895, 2, 0),
(4870, 815, 1, 1),
(4871, 1143, 2, 2),
(4872, 9, 1, 1),
(4873, 1862, 2, 1),
(4874, 1837, 1, 2),
(4875, 1200, 2, 0),
(4876, 1143, 2, 1),
(4877, 1043, 0, 1);

-- Refresh the event's summary counters
UPDATE `events` SET `games` = 11, `players` = 29, `tables` = 2 WHERE `id` = 1;
UPDATE `tournaments` SET `num_players` = 29, `num_regs` = 29 WHERE `id` = 1;

-- ============================================================
-- Keys, indexes, auto-increment and foreign-key constraints
-- ============================================================

-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `address_club` (`club_id`,`name`),
  ADD KEY `city_id` (`city_id`);

--
-- Indexes for table `bug_reports`
--
ALTER TABLE `bug_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_id` (`name_id`),
  ADD KEY `country_id` (`country_id`),
  ADD KEY `country_id_2` (`country_id`),
  ADD KEY `area` (`area_id`);

--
-- Indexes for table `city_names`
--
ALTER TABLE `city_names`
  ADD PRIMARY KEY (`city_id`,`name`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `clubs`
--
ALTER TABLE `clubs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `club_name` (`name`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `system_id` (`scoring_id`),
  ADD KEY `parent_id` (`parent_id`,`name`),
  ADD KEY `prompt_sound_id` (`prompt_sound_id`),
  ADD KEY `end_sound_id` (`end_sound_id`),
  ADD KEY `normalizer_id` (`normalizer_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `club_info`
--
ALTER TABLE `club_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `club_id` (`club_id`,`pos`);

--
-- Indexes for table `club_pairs`
--
ALTER TABLE `club_pairs`
  ADD PRIMARY KEY (`club_id`,`user1_id`,`user2_id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `club_regs`
--
ALTER TABLE `club_regs`
  ADD PRIMARY KEY (`user_id`,`club_id`),
  ADD KEY `user_club_club` (`club_id`);

--
-- Indexes for table `club_rules`
--
ALTER TABLE `club_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`club_id`,`name`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name_id` (`name_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `country_names`
--
ALTER TABLE `country_names`
  ADD PRIMARY KEY (`country_id`,`name`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name_id` (`name_id`),
  ADD KEY `currency_pattern` (`pattern`);

--
-- Indexes for table `current_games`
--
ALTER TABLE `current_games`
  ADD PRIMARY KEY (`event_id`,`table_num`,`game_num`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `dons`
--
ALTER TABLE `dons`
  ADD PRIMARY KEY (`game_id`,`user_id`);

--
-- Indexes for table `emails`
--
ALTER TABLE `emails`
  ADD KEY `user_id` (`user_id`),
  ADD KEY `obj` (`obj`,`obj_id`),
  ADD KEY `send_time` (`send_time`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `i_events_start` (`start_time`,`id`),
  ADD KEY `i_events_address` (`address_id`,`start_time`),
  ADD KEY `i_events_club` (`club_id`,`start_time`),
  ADD KEY `i_events_scoring` (`scoring_id`,`start_time`),
  ADD KEY `i_events_tournament` (`tournament_id`,`start_time`),
  ADD KEY `scoring_id` (`scoring_id`,`scoring_version`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `event_broadcasts`
--
ALTER TABLE `event_broadcasts`
  ADD PRIMARY KEY (`event_id`,`day_num`,`table_num`,`part_num`),
  ADD KEY `event_id` (`event_id`,`table_num`,`status`);

--
-- Indexes for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_comment_event` (`event_id`,`time`),
  ADD KEY `event_comment_user` (`user_id`,`time`);

--
-- Indexes for table `event_extra_points`
--
ALTER TABLE `event_extra_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`event_id`,`time`),
  ADD KEY `event_id` (`event_id`,`time`),
  ADD KEY `reason` (`reason`);

--
-- Indexes for table `event_incomers`
--
ALTER TABLE `event_incomers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_id` (`event_id`,`name`);

--
-- Indexes for table `event_mailings`
--
ALTER TABLE `event_mailings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`,`send_time`),
  ADD KEY `send_time` (`send_time`);

--
-- Indexes for table `event_places`
--
ALTER TABLE `event_places`
  ADD PRIMARY KEY (`user_id`,`event_id`),
  ADD KEY `event_id` (`event_id`,`place`),
  ADD KEY `user_id` (`user_id`,`importance`);

--
-- Indexes for table `event_regs`
--
ALTER TABLE `event_regs`
  ADD PRIMARY KEY (`event_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_scores_cache`
--
ALTER TABLE `event_scores_cache`
  ADD PRIMARY KEY (`event_id`,`flags`,`scoring_id`,`scoring_version`);

--
-- Indexes for table `gainings`
--
ALTER TABLE `gainings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `league_id` (`league_id`,`name`);

--
-- Indexes for table `gaining_versions`
--
ALTER TABLE `gaining_versions`
  ADD PRIMARY KEY (`gaining_id`,`version`);

--
-- Indexes for table `games`
--
ALTER TABLE `games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_video` (`video_id`),
  ADD KEY `event_id` (`event_id`,`end_time`),
  ADD KEY `club_id` (`club_id`,`end_time`),
  ADD KEY `moderator_id` (`moderator_id`,`end_time`),
  ADD KEY `user_id` (`user_id`,`end_time`) USING BTREE,
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `end_time` (`end_time`,`id`);

--
-- Indexes for table `game_comments`
--
ALTER TABLE `game_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_comment_game` (`game_id`,`time`),
  ADD KEY `game_comment_user` (`user_id`,`time`);

--
-- Indexes for table `game_issues`
--
ALTER TABLE `game_issues`
  ADD PRIMARY KEY (`game_id`,`feature_flags`);

--
-- Indexes for table `game_settings`
--
ALTER TABLE `game_settings`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `prompt_sound_id` (`prompt_sound_id`),
  ADD KEY `end_sound_id` (`end_sound_id`);

--
-- Indexes for table `gate_sessions`
--
ALTER TABLE `gate_sessions`
  ADD PRIMARY KEY (`token`),
  ADD KEY `gate_session_user` (`user_id`);

--
-- Indexes for table `leagues`
--
ALTER TABLE `leagues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `scoring_id` (`scoring_id`),
  ADD KEY `normalizer_id` (`normalizer_id`),
  ADD KEY `leagues_gaining` (`gaining_id`);

--
-- Indexes for table `league_clubs`
--
ALTER TABLE `league_clubs`
  ADD PRIMARY KEY (`league_id`,`club_id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `league_managers`
--
ALTER TABLE `league_managers`
  ADD PRIMARY KEY (`league_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `league_pairs`
--
ALTER TABLE `league_pairs`
  ADD PRIMARY KEY (`league_id`,`user1_id`,`user2_id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `league_requests`
--
ALTER TABLE `league_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `log`
--
ALTER TABLE `log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`time`),
  ADD KEY `club_id` (`club_id`,`time`),
  ADD KEY `time` (`time`),
  ADD KEY `obj` (`obj`,`time`),
  ADD KEY `obj_id` (`obj_id`,`obj`,`time`),
  ADD KEY `league_id` (`league_id`);

--
-- Indexes for table `mafiosos`
--
ALTER TABLE `mafiosos`
  ADD PRIMARY KEY (`game_id`,`user_id`);

--
-- Indexes for table `maintenance_scripts`
--
ALTER TABLE `maintenance_scripts`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD PRIMARY KEY (`script_name`,`name`);

--
-- Indexes for table `mr_bonus_stats`
--
ALTER TABLE `mr_bonus_stats`
  ADD PRIMARY KEY (`game_id`),
  ADD KEY `time` (`time`,`game_id`);

--
-- Indexes for table `mwt_games`
--
ALTER TABLE `mwt_games`
  ADD PRIMARY KEY (`id`),
  ADD KEY `game_id` (`game_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `names`
--
ALTER TABLE `names`
  ADD PRIMARY KEY (`id`,`langs`),
  ADD KEY `name` (`name`(255));

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`);

--
-- Indexes for table `normalizers`
--
ALTER TABLE `normalizers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`,`name`),
  ADD KEY `league_id` (`league_id`,`name`),
  ADD KEY `id` (`id`,`version`);

--
-- Indexes for table `normalizer_versions`
--
ALTER TABLE `normalizer_versions`
  ADD PRIMARY KEY (`normalizer_id`,`version`);

--
-- Indexes for table `objections`
--
ALTER TABLE `objections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `objection_id` (`objection_id`),
  ADD KEY `game_id` (`game_id`);

--
-- Indexes for table `pairs`
--
ALTER TABLE `pairs`
  ADD PRIMARY KEY (`user1_id`,`user2_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `photos`
--
ALTER TABLE `photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `album_id` (`album_id`);

--
-- Indexes for table `photo_albums`
--
ALTER TABLE `photo_albums`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tournament_id` (`tournament_id`);

--
-- Indexes for table `photo_comments`
--
ALTER TABLE `photo_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `photo_comment_photo` (`photo_id`,`time`),
  ADD KEY `photo_comment_user` (`user_id`,`time`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`game_id`,`user_id`),
  ADD KEY `player_role` (`role`),
  ADD KEY `player_user_role` (`user_id`,`role`),
  ADD KEY `extra_points_reason` (`extra_points_reason`(255)),
  ADD KEY `player_user_time_game` (`user_id`,`game_end_time`,`game_id`);

--
-- Indexes for table `profiling_ips`
--
ALTER TABLE `profiling_ips`
  ADD PRIMARY KEY (`ip`);

--
-- Indexes for table `profiling_pages`
--
ALTER TABLE `profiling_pages`
  ADD PRIMARY KEY (`page`);

--
-- Indexes for table `rebuild_ratings`
--
ALTER TABLE `rebuild_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `start` (`start_time`),
  ADD KEY `game` (`game_id`),
  ADD KEY `end` (`end_time`);

--
-- Indexes for table `scorings`
--
ALTER TABLE `scorings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`,`name`),
  ADD KEY `league_id` (`league_id`),
  ADD KEY `id` (`id`,`version`);

--
-- Indexes for table `scoring_versions`
--
ALTER TABLE `scoring_versions`
  ADD PRIMARY KEY (`scoring_id`,`version`);

--
-- Indexes for table `seatings`
--
ALTER TABLE `seatings`
  ADD PRIMARY KEY (`hash`),
  ADD KEY `idx_players_runs` (`players_void_runs`,`players_runs`),
  ADD KEY `idx_numbers_runs` (`numbers_void_runs`,`numbers_runs`),
  ADD KEY `idx_tables_runs` (`tables_void_runs`,`tables_runs`);

--
-- Indexes for table `series`
--
ALTER TABLE `series`
  ADD PRIMARY KEY (`id`),
  ADD KEY `start_time` (`start_time`),
  ADD KEY `league_id` (`league_id`,`start_time`),
  ADD KEY `finals_id` (`finals_id`),
  ADD KEY `gaining_id` (`gaining_id`,`gaining_version`);

--
-- Indexes for table `series_extra_points`
--
ALTER TABLE `series_extra_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`series_id`,`time`),
  ADD KEY `series_id` (`series_id`,`time`),
  ADD KEY `reason` (`reason`);

--
-- Indexes for table `series_places`
--
ALTER TABLE `series_places`
  ADD PRIMARY KEY (`user_id`,`series_id`),
  ADD KEY `series_id` (`series_id`,`place`),
  ADD KEY `user_id` (`user_id`,`importance`);

--
-- Indexes for table `series_regs`
--
ALTER TABLE `series_regs`
  ADD PRIMARY KEY (`series_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `series_series`
--
ALTER TABLE `series_series`
  ADD PRIMARY KEY (`parent_id`,`child_id`),
  ADD KEY `child_id` (`child_id`);

--
-- Indexes for table `series_tournaments`
--
ALTER TABLE `series_tournaments`
  ADD PRIMARY KEY (`tournament_id`,`series_id`),
  ADD KEY `series_id` (`series_id`);

--
-- Indexes for table `sheriffs`
--
ALTER TABLE `sheriffs`
  ADD PRIMARY KEY (`game_id`,`user_id`);

--
-- Indexes for table `snapshots`
--
ALTER TABLE `snapshots`
  ADD PRIMARY KEY (`time`);

--
-- Indexes for table `sounds`
--
ALTER TABLE `sounds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `club_id` (`club_id`,`name`),
  ADD KEY `user_id` (`user_id`,`name`);

--
-- Indexes for table `stats_calculators`
--
ALTER TABLE `stats_calculators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `name` (`name`),
  ADD KEY `owner_id` (`owner_id`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mwt_id` (`mwt_id`),
  ADD UNIQUE KEY `imafia_id` (`imafia_id`),
  ADD UNIQUE KEY `emo_id` (`emo_id`),
  ADD KEY `start_time` (`start_time`),
  ADD KEY `league_id` (`start_time`),
  ADD KEY `club_id` (`club_id`,`start_time`),
  ADD KEY `address_id` (`address_id`,`start_time`),
  ADD KEY `rules_id` (`start_time`),
  ADD KEY `scoring_id` (`scoring_id`,`start_time`),
  ADD KEY `scoring_id_2` (`scoring_id`,`scoring_version`),
  ADD KEY `normalizer_id` (`normalizer_id`,`normalizer_version`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `tournament_approves`
--
ALTER TABLE `tournament_approves`
  ADD PRIMARY KEY (`user_id`,`league_id`,`tournament_id`),
  ADD KEY `tournament_id` (`tournament_id`,`league_id`,`user_id`),
  ADD KEY `league_id` (`league_id`,`user_id`,`tournament_id`);

--
-- Indexes for table `tournament_comments`
--
ALTER TABLE `tournament_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `i_tournament_comments_tournament` (`tournament_id`,`time`),
  ADD KEY `i_tournament_comments_user` (`user_id`,`time`);

--
-- Indexes for table `tournament_invitations`
--
ALTER TABLE `tournament_invitations`
  ADD PRIMARY KEY (`tournament_id`,`user_id`),
  ADD KEY `i_tournament_invitations_user` (`user_id`);

--
-- Indexes for table `tournament_pairs`
--
ALTER TABLE `tournament_pairs`
  ADD PRIMARY KEY (`tournament_id`,`user1_id`,`user2_id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `tournament_places`
--
ALTER TABLE `tournament_places`
  ADD PRIMARY KEY (`user_id`,`tournament_id`),
  ADD KEY `tournament_id` (`tournament_id`,`place`),
  ADD KEY `user_id` (`user_id`,`importance`);

--
-- Indexes for table `tournament_regs`
--
ALTER TABLE `tournament_regs`
  ADD PRIMARY KEY (`tournament_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `team_id` (`team_id`),
  ADD KEY `tournament_user_city` (`city_id`),
  ADD KEY `idx_tournament_regs_order` (`tournament_id`,`reg_order`);

--
-- Indexes for table `tournament_scores_cache`
--
ALTER TABLE `tournament_scores_cache`
  ADD PRIMARY KEY (`tournament_id`,`flags`,`scoring_id`,`scoring_version`,`normalizer_id`,`normalizer_version`);

--
-- Indexes for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`,`name`(255));

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mwt_id` (`mwt_id`),
  ADD UNIQUE KEY `imafia_id` (`imafia_id`),
  ADD UNIQUE KEY `emo_id` (`emo_id`),
  ADD KEY `user_moderated` (`games_moderated`),
  ADD KEY `city_id` (`city_id`),
  ADD KEY `club_id` (`club_id`),
  ADD KEY `rating` (`rating`,`games`),
  ADD KEY `max_rating` (`games`),
  ADD KEY `name_id` (`name_id`),
  ADD KEY `red_rating` (`red_rating`),
  ADD KEY `black_rating` (`black_rating`);

--
-- Indexes for table `user_photos`
--
ALTER TABLE `user_photos`
  ADD PRIMARY KEY (`user_id`,`photo_id`),
  ADD KEY `photo_id` (`photo_id`),
  ADD KEY `email_sent` (`email_sent`,`user_id`);

--
-- Indexes for table `user_videos`
--
ALTER TABLE `user_videos`
  ADD PRIMARY KEY (`user_id`,`video_id`),
  ADD KEY `video` (`video_id`),
  ADD KEY `video_tagged_by` (`tagged_by_id`,`user_id`,`video_id`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_club` (`club_id`,`type`,`post_time`),
  ADD KEY `video_event` (`event_id`,`type`,`post_time`),
  ADD KEY `video_type` (`type`,`post_time`),
  ADD KEY `video_user` (`user_id`,`type`,`post_time`),
  ADD KEY `video_type_time` (`type`,`video_time`),
  ADD KEY `tournament_id` (`tournament_id`);

--
-- Indexes for table `video_comments`
--
ALTER TABLE `video_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `video_comment_video` (`video_id`,`time`),
  ADD KEY `video_comment_user` (`user_id`,`time`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bug_reports`
--
ALTER TABLE `bug_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clubs`
--
ALTER TABLE `clubs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `club_info`
--
ALTER TABLE `club_info`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `club_rules`
--
ALTER TABLE `club_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_comments`
--
ALTER TABLE `event_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_extra_points`
--
ALTER TABLE `event_extra_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_incomers`
--
ALTER TABLE `event_incomers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_mailings`
--
ALTER TABLE `event_mailings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gainings`
--
ALTER TABLE `gainings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `games`
--
ALTER TABLE `games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `game_comments`
--
ALTER TABLE `game_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leagues`
--
ALTER TABLE `leagues`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `league_requests`
--
ALTER TABLE `league_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log`
--
ALTER TABLE `log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mwt_games`
--
ALTER TABLE `mwt_games`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `names`
--
ALTER TABLE `names`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `normalizers`
--
ALTER TABLE `normalizers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `objections`
--
ALTER TABLE `objections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photos`
--
ALTER TABLE `photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photo_albums`
--
ALTER TABLE `photo_albums`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `photo_comments`
--
ALTER TABLE `photo_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rebuild_ratings`
--
ALTER TABLE `rebuild_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scorings`
--
ALTER TABLE `scorings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `series`
--
ALTER TABLE `series`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `series_extra_points`
--
ALTER TABLE `series_extra_points`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sounds`
--
ALTER TABLE `sounds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stats_calculators`
--
ALTER TABLE `stats_calculators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_comments`
--
ALTER TABLE `tournament_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_comments`
--
ALTER TABLE `video_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `address_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `address_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `bug_reports`
--
ALTER TABLE `bug_reports`
  ADD CONSTRAINT `bug_report_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `bug_report_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `area_city` FOREIGN KEY (`area_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `city_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `city_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);

--
-- Constraints for table `city_names`
--
ALTER TABLE `city_names`
  ADD CONSTRAINT `city_names_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `clubs`
--
ALTER TABLE `clubs`
  ADD CONSTRAINT `club_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `club_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `club_end_sound` FOREIGN KEY (`end_sound_id`) REFERENCES `sounds` (`id`),
  ADD CONSTRAINT `club_normalizer` FOREIGN KEY (`normalizer_id`) REFERENCES `normalizers` (`id`),
  ADD CONSTRAINT `club_prompt_sound` FOREIGN KEY (`prompt_sound_id`) REFERENCES `sounds` (`id`),
  ADD CONSTRAINT `club_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`),
  ADD CONSTRAINT `parent_club` FOREIGN KEY (`parent_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `club_info`
--
ALTER TABLE `club_info`
  ADD CONSTRAINT `info_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `club_pairs`
--
ALTER TABLE `club_pairs`
  ADD CONSTRAINT `club_pair_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `club_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `club_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `club_regs`
--
ALTER TABLE `club_regs`
  ADD CONSTRAINT `user_club_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `user_club_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `club_rules`
--
ALTER TABLE `club_rules`
  ADD CONSTRAINT `club_rules_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `countries`
--
ALTER TABLE `countries`
  ADD CONSTRAINT `country_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `country_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);

--
-- Constraints for table `country_names`
--
ALTER TABLE `country_names`
  ADD CONSTRAINT `country_names_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);

--
-- Constraints for table `currencies`
--
ALTER TABLE `currencies`
  ADD CONSTRAINT `currency_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);

--
-- Constraints for table `current_games`
--
ALTER TABLE `current_games`
  ADD CONSTRAINT `current_game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `current_game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `dons`
--
ALTER TABLE `dons`
  ADD CONSTRAINT `don_player` FOREIGN KEY (`game_id`,`user_id`) REFERENCES `mafiosos` (`game_id`, `user_id`);

--
-- Constraints for table `emails`
--
ALTER TABLE `emails`
  ADD CONSTRAINT `email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `event_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `event_scoring_version` FOREIGN KEY (`scoring_id`,`scoring_version`) REFERENCES `scoring_versions` (`scoring_id`, `version`),
  ADD CONSTRAINT `fk_events_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `fk_events_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `fk_events_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

--
-- Constraints for table `event_broadcasts`
--
ALTER TABLE `event_broadcasts`
  ADD CONSTRAINT `broadcast_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `event_comments`
--
ALTER TABLE `event_comments`
  ADD CONSTRAINT `event_comment_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `event_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_extra_points`
--
ALTER TABLE `event_extra_points`
  ADD CONSTRAINT `points_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_incomers`
--
ALTER TABLE `event_incomers`
  ADD CONSTRAINT `incomer_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `event_mailings`
--
ALTER TABLE `event_mailings`
  ADD CONSTRAINT `event_emails_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `event_places`
--
ALTER TABLE `event_places`
  ADD CONSTRAINT `event_place_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `event_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_regs`
--
ALTER TABLE `event_regs`
  ADD CONSTRAINT `c_event_users_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `c_event_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `event_scores_cache`
--
ALTER TABLE `event_scores_cache`
  ADD CONSTRAINT `event_scores_cache_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `gainings`
--
ALTER TABLE `gainings`
  ADD CONSTRAINT `gaining_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`);

--
-- Constraints for table `gaining_versions`
--
ALTER TABLE `gaining_versions`
  ADD CONSTRAINT `gaining_fk` FOREIGN KEY (`gaining_id`) REFERENCES `gainings` (`id`);

--
-- Constraints for table `games`
--
ALTER TABLE `games`
  ADD CONSTRAINT `game_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `game_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `game_moderator` FOREIGN KEY (`moderator_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `game_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `game_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`);

--
-- Constraints for table `game_comments`
--
ALTER TABLE `game_comments`
  ADD CONSTRAINT `game_comment_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `game_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `game_issues`
--
ALTER TABLE `game_issues`
  ADD CONSTRAINT `issue_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

--
-- Constraints for table `game_settings`
--
ALTER TABLE `game_settings`
  ADD CONSTRAINT `game_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_end_sound` FOREIGN KEY (`end_sound_id`) REFERENCES `sounds` (`id`),
  ADD CONSTRAINT `user_prompt_sound` FOREIGN KEY (`prompt_sound_id`) REFERENCES `sounds` (`id`);

--
-- Constraints for table `gate_sessions`
--
ALTER TABLE `gate_sessions`
  ADD CONSTRAINT `gate_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `leagues`
--
ALTER TABLE `leagues`
  ADD CONSTRAINT `league_normalizer` FOREIGN KEY (`normalizer_id`) REFERENCES `normalizers` (`id`),
  ADD CONSTRAINT `league_scoring` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`),
  ADD CONSTRAINT `leagues_gaining` FOREIGN KEY (`gaining_id`) REFERENCES `gainings` (`id`);

--
-- Constraints for table `league_clubs`
--
ALTER TABLE `league_clubs`
  ADD CONSTRAINT `club_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `league_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `league_managers`
--
ALTER TABLE `league_managers`
  ADD CONSTRAINT `league_manager` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `manager_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`);

--
-- Constraints for table `league_pairs`
--
ALTER TABLE `league_pairs`
  ADD CONSTRAINT `league_pair_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `league_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `league_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `league_requests`
--
ALTER TABLE `league_requests`
  ADD CONSTRAINT `league_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `log`
--
ALTER TABLE `log`
  ADD CONSTRAINT `log_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `log_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `mafiosos`
--
ALTER TABLE `mafiosos`
  ADD CONSTRAINT `mafioso_player` FOREIGN KEY (`game_id`,`user_id`) REFERENCES `players` (`game_id`, `user_id`);

--
-- Constraints for table `maintenance_tasks`
--
ALTER TABLE `maintenance_tasks`
  ADD CONSTRAINT `script_fk` FOREIGN KEY (`script_name`) REFERENCES `maintenance_scripts` (`name`);

--
-- Constraints for table `mr_bonus_stats`
--
ALTER TABLE `mr_bonus_stats`
  ADD CONSTRAINT `mr_bonus_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

--
-- Constraints for table `mwt_games`
--
ALTER TABLE `mwt_games`
  ADD CONSTRAINT `mwt_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `mwt_game_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `news`
--
ALTER TABLE `news`
  ADD CONSTRAINT `news_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`);

--
-- Constraints for table `normalizers`
--
ALTER TABLE `normalizers`
  ADD CONSTRAINT `normalizer_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `normalizer_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `normalizer_version` FOREIGN KEY (`id`,`version`) REFERENCES `normalizer_versions` (`normalizer_id`, `version`);

--
-- Constraints for table `normalizer_versions`
--
ALTER TABLE `normalizer_versions`
  ADD CONSTRAINT `version_normalizer` FOREIGN KEY (`normalizer_id`) REFERENCES `normalizers` (`id`);

--
-- Constraints for table `objections`
--
ALTER TABLE `objections`
  ADD CONSTRAINT `objection_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `objection_objection` FOREIGN KEY (`objection_id`) REFERENCES `objections` (`id`),
  ADD CONSTRAINT `objection_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `pairs`
--
ALTER TABLE `pairs`
  ADD CONSTRAINT `pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `photos`
--
ALTER TABLE `photos`
  ADD CONSTRAINT `photo_album` FOREIGN KEY (`album_id`) REFERENCES `photo_albums` (`id`),
  ADD CONSTRAINT `photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `photo_albums`
--
ALTER TABLE `photo_albums`
  ADD CONSTRAINT `album_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `album_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `album_owner` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `photo_album_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

--
-- Constraints for table `photo_comments`
--
ALTER TABLE `photo_comments`
  ADD CONSTRAINT `photo_comment_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`),
  ADD CONSTRAINT `photo_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `player_game` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`),
  ADD CONSTRAINT `player_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `rebuild_ratings`
--
ALTER TABLE `rebuild_ratings`
  ADD CONSTRAINT `game_fk` FOREIGN KEY (`game_id`) REFERENCES `games` (`id`);

--
-- Constraints for table `scorings`
--
ALTER TABLE `scorings`
  ADD CONSTRAINT `system_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `system_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `system_version` FOREIGN KEY (`id`,`version`) REFERENCES `scoring_versions` (`scoring_id`, `version`);

--
-- Constraints for table `scoring_versions`
--
ALTER TABLE `scoring_versions`
  ADD CONSTRAINT `scoring_fk` FOREIGN KEY (`scoring_id`) REFERENCES `scorings` (`id`);

--
-- Constraints for table `series`
--
ALTER TABLE `series`
  ADD CONSTRAINT `series_final` FOREIGN KEY (`finals_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `series_gaining_version` FOREIGN KEY (`gaining_id`,`gaining_version`) REFERENCES `gaining_versions` (`gaining_id`, `version`),
  ADD CONSTRAINT `series_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`);

--
-- Constraints for table `series_extra_points`
--
ALTER TABLE `series_extra_points`
  ADD CONSTRAINT `points_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `series_points_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `series_places`
--
ALTER TABLE `series_places`
  ADD CONSTRAINT `series_place_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `series_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `series_regs`
--
ALTER TABLE `series_regs`
  ADD CONSTRAINT `c_series_regs_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `c_series_regs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `series_series`
--
ALTER TABLE `series_series`
  ADD CONSTRAINT `series_child` FOREIGN KEY (`child_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `series_parent` FOREIGN KEY (`parent_id`) REFERENCES `series` (`id`);

--
-- Constraints for table `series_tournaments`
--
ALTER TABLE `series_tournaments`
  ADD CONSTRAINT `series_tournaments_series` FOREIGN KEY (`series_id`) REFERENCES `series` (`id`),
  ADD CONSTRAINT `series_tournaments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

--
-- Constraints for table `sheriffs`
--
ALTER TABLE `sheriffs`
  ADD CONSTRAINT `sheriff_player` FOREIGN KEY (`game_id`,`user_id`) REFERENCES `players` (`game_id`, `user_id`);

--
-- Constraints for table `sounds`
--
ALTER TABLE `sounds`
  ADD CONSTRAINT `sound_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `sound_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `stats_calculators`
--
ALTER TABLE `stats_calculators`
  ADD CONSTRAINT `stats_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `tournament_address` FOREIGN KEY (`address_id`) REFERENCES `addresses` (`id`),
  ADD CONSTRAINT `tournament_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `tournament_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `tournament_normalizer_version` FOREIGN KEY (`normalizer_id`,`normalizer_version`) REFERENCES `normalizer_versions` (`normalizer_id`, `version`),
  ADD CONSTRAINT `tournament_scoring_version` FOREIGN KEY (`scoring_id`,`scoring_version`) REFERENCES `scoring_versions` (`scoring_id`, `version`);

--
-- Constraints for table `tournament_approves`
--
ALTER TABLE `tournament_approves`
  ADD CONSTRAINT `approve_league` FOREIGN KEY (`league_id`) REFERENCES `leagues` (`id`),
  ADD CONSTRAINT `approve_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `approve_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournament_comments`
--
ALTER TABLE `tournament_comments`
  ADD CONSTRAINT `fk_tournament_comments_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `fk_tournament_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournament_invitations`
--
ALTER TABLE `tournament_invitations`
  ADD CONSTRAINT `fk_tournament_invitations_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `fk_tournament_invitations_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournament_pairs`
--
ALTER TABLE `tournament_pairs`
  ADD CONSTRAINT `tournament_pair_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `tournament_pair_user1` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `tournament_pair_user2` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournament_places`
--
ALTER TABLE `tournament_places`
  ADD CONSTRAINT `tournament_place_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `tournament_place_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `tournament_regs`
--
ALTER TABLE `tournament_regs`
  ADD CONSTRAINT `c_tournament_users_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `c_tournament_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `c_user_team` FOREIGN KEY (`team_id`) REFERENCES `tournament_teams` (`id`),
  ADD CONSTRAINT `tournament_user_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`);

--
-- Constraints for table `tournament_scores_cache`
--
ALTER TABLE `tournament_scores_cache`
  ADD CONSTRAINT `tournament_scores_cache_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

--
-- Constraints for table `tournament_teams`
--
ALTER TABLE `tournament_teams`
  ADD CONSTRAINT `c_team_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `user_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `user_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `user_name` FOREIGN KEY (`name_id`) REFERENCES `names` (`id`);

--
-- Constraints for table `user_photos`
--
ALTER TABLE `user_photos`
  ADD CONSTRAINT `user_photo_photo` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`),
  ADD CONSTRAINT `user_photo_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `user_videos`
--
ALTER TABLE `user_videos`
  ADD CONSTRAINT `user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`),
  ADD CONSTRAINT `video_tagged_by` FOREIGN KEY (`tagged_by_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `video_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`),
  ADD CONSTRAINT `video_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `video_tournament` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `video_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `video_comments`
--
ALTER TABLE `video_comments`
  ADD CONSTRAINT `video_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `video_comment_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
