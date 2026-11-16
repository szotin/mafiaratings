-- Named rules can be deprecated, the way scoring systems, normalizers and gaining systems are (see
-- SYSTEM_FLAG_DEPRECATED in include/scoring.php): what is played by them goes on being played by
-- them, but no list offers them for anything new.
--
-- The rules had no flags to keep it in, so they get a column. 1 is RULES_FLAG_DEPRECATED - see
-- include/rules.php. The default rules of the site, the row without a name, are never deprecated.
ALTER TABLE rules
  ADD COLUMN flags INT(11) NOT NULL DEFAULT 0;
