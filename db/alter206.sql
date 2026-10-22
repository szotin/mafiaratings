-- Switching OBS scenes without obs-websocket.
--
-- A browser source running inside OBS can change the scene itself - window.obsstudio has
-- setCurrentScene - so the pages already standing in the scene collection can do it, and
-- nothing has to be installed, enabled, port-forwarded or given a password. What a source
-- cannot do is know what to switch to, so it asks the site, and this is where the answer is
-- kept for the things the site cannot work out on its own.
--
-- scene and scene_time are a standing order from a person rather than from the game: the
-- moderator's own screen already decides the scene by the phase of the game and carries it in
-- the game itself. This is for when nobody is doing that - the moderator is not using the
-- automatic switching, or forgot - and somebody watching from the commentary box or from the
-- tournament desk switches by hand. The time is what tells a source that the order is new;
-- the order stands until the game moves the automatic scene on by itself.
--
-- scene_events is the other half of it, and the half that does the work most of the time: what
-- the game itself asks for, a scene against the phase of the game it belongs to. It used to be
-- kept per moderator in game_settings.obs_scenes, where it was reached through a page of its
-- own and carried out by talking to obs-websocket. It belongs to the table rather than to
-- whoever happens to be moderating at it - the OBS beside a table is the one with those scenes
-- in it - and now that a source can switch by itself there is nothing left for a websocket to
-- do. The game carries nothing but what is happening at the table; which scene that comes to is
-- worked out here, against this, when a source asks.
--
-- scenes and control_level are what the source reports back about the OBS it is running in:
-- the names of the scenes there, so the settings page has something to offer, and how much
-- that source is allowed to do, so the page can say when it is not allowed to switch at all.
-- Page permissions of a browser source have to be set to Advanced for switching to work.

ALTER TABLE `tournament_streams`
	ADD COLUMN `scene` varchar(128) NOT NULL DEFAULT '',
	ADD COLUMN `scene_time` int(11) NOT NULL DEFAULT '0',
	ADD COLUMN `scenes` text,
	ADD COLUMN `scene_events` text,
	ADD COLUMN `control_level` int(11) NOT NULL DEFAULT '-1';

ALTER TABLE `event_streams`
	ADD COLUMN `scene` varchar(128) NOT NULL DEFAULT '',
	ADD COLUMN `scene_time` int(11) NOT NULL DEFAULT '0',
	ADD COLUMN `scenes` text,
	ADD COLUMN `scene_events` text,
	ADD COLUMN `control_level` int(11) NOT NULL DEFAULT '-1';

-- And the per-moderator copy of it goes, together with the page that edited it. It was never
-- the right place for it: the scenes it names are the scenes of one OBS, which stands beside
-- one table, while a moderator moves between tables and tournaments. Nothing reads the column
-- any more.
ALTER TABLE `game_settings`
	DROP COLUMN `obs_scenes`;
