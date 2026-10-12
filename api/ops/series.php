<?php

require_once '../../include/api.php';
require_once '../../include/series.php';
require_once '../../include/email.php';
require_once '../../include/message.php';
require_once '../../include/datetime.php';
require_once '../../include/scoring.php';
require_once '../../include/image.php';
require_once '../../include/game.php';

define('CURRENT_VERSION', 0);

class ApiPage extends OpsApiPageBase
{
	//-------------------------------------------------------------------------------------------------------
	// create
	//-------------------------------------------------------------------------------------------------------
	function create_op()
	{
		global $_profile;
		$league_id = (int)get_optional_param('league_id', -1);
		$club_id = (int)get_optional_param('club_id', -1);
		if ($league_id > 0 && $club_id > 0)
		{
			throw new Exc(get_label('A series belongs either to a league or to a club, but not to both.'));
		}

		if ($club_id > 0)
		{
			check_permissions(PERMISSION_CLUB_MANAGER, $club_id);
			$league_id = NULL;
			// A club has no rules filter the way a league does - it has a plain rules code. Both
			// are accepted by show_rules(), so the code is stored as is and the series rulebook
			// shows exactly the rules of the club.
			list($owner_name, $owner_rules, $owner_langs) = Db::record(get_label('club'), 'SELECT name, rules, langs FROM clubs WHERE id = ?', $club_id);
			$owner_rules = json_encode($owner_rules);
			$owner_flags = 0;
		}
		else if ($league_id > 0)
		{
			check_permissions(PERMISSION_LEAGUE_MANAGER, $league_id);
			$club_id = NULL;
			list($owner_name, $owner_rules, $owner_langs, $owner_flags) = Db::record(get_label('league'), 'SELECT name, rules, langs, flags FROM leagues WHERE id = ?', $league_id);
		}
		else
		{
			throw new Exc(get_label('Please enter [0].', get_label('league')));
		}

		$gaining_id = (int)get_optional_param('gaining_id');
		list($gaining_version) = Db::record(get_label('gaining system'), 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
		$gaining_version = (int)get_optional_param('gaining_version', $gaining_version);
		
		$parent_series = json_decode(get_optional_param('parent_series', '[]'));
		
		$name = get_required_param('name');
		if (empty($name))
		{
			throw new Exc(get_label('Please enter [0].', get_label('Series name')));
		}
		
		$fee = (int)get_optional_param('fee', -1);
		if ($fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = (int)get_optional_param('currency_id', -1);
		if ($currency_id <= 0)
		{
			$currency_id = NULL;
		}
		
		$notes = get_optional_param('notes', '');

		// A series is made of three properties: how many stars it gives its tournaments, how many
		// stages it has, and whether it has dates. Its type follows from the first and the third
		// and is not stored at all - see series_type().
		//
		// A type may still be passed, and then it stands for the shape such a series usually has:
		// it fills in whichever of the three properties the caller left out. That is all it does.
		$preset = (int)get_optional_param('type', SERIES_TYPE_SERIES);
		if (!is_series_type_valid($preset))
		{
			throw new Exc(get_label('Unknown [0]', get_label('series type')));
		}
		$preset = get_series_type_defaults($preset);
		$max_stars = series_max_stars(get_optional_param('max_stars', $preset['max_stars']));
		$stages = series_stage_count(get_optional_param('stages', $preset['stages']));
		$infinite = (int)get_optional_param('infinite', $preset['infinite']) != 0;

		// A series with no dates is inside nothing - see the same rule in change_op.
		if ($infinite)
		{
			$parent_series = array();
		}

		$given_flags = get_optional_param('flags', NULL);
		$flags = (int)(is_null($given_flags) ? NEW_SERIES_FLAGS : $given_flags);
		$flags = ($flags & SERIES_EDITABLE_MASK) + (NEW_SERIES_FLAGS & ~SERIES_EDITABLE_MASK);
		// What is played in the series is two bits of the flags.
		if (($flags & SERIES_FLAGS_PLAYS_MASK) == 0)
		{
			// Carrying neither of the two bits that say what is played in the series is not the same
			// as leaving them out. A request that names the flags and names neither of these is
			// describing a series nothing can be entered into, and there is no such series.
			if (!is_null($given_flags))
			{
				throw new Exc(get_label('A series has to take tournaments, subseries, or both.'));
			}
			$flags |= $preset['plays'];
		}
		// Elite is a property of elite leagues. A club series is never elite.
		if (($owner_flags & LEAGUE_FLAG_ELITE) == 0)
		{
			$flags &= ~SERIES_FLAG_ELITE;
		}
		if (!is_permitted(PERMISSION_ADMIN))
		{
			$flags &= ~SERIES_ADMIN_EDITABLE_MASK;
		}

		$langs = get_optional_param('langs', $owner_langs);
		$timezone = get_timezone();
		
		Db::begin();
		
		if ($infinite)
		{
			// No dates at all: infinite in both directions. See SERIES_INFINITE_START - that is
			// what every query about the dates of the series reads.
			$start = SERIES_INFINITE_START;
			$duration = SERIES_ETERNAL_DURATION;
		}
		else
		{
			$start_datetime = get_datetime(get_required_param('start'), $timezone);
			$end_datetime = get_datetime(get_required_param('end'), $timezone);
			$start = $start_datetime->getTimestamp();
			$end = $end_datetime->getTimestamp();
			if ($end <= $start)
			{
				throw new Exc(get_label('Series end before or right after the start.'));
			}
			$duration = $end - $start;
		}

		Db::exec(
			get_label('sеriеs'),
			'INSERT INTO series (name, league_id, club_id, start_time, duration, max_stars, stages, langs, notes, fee, currency_id, flags, rules, gaining_id, gaining_version) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			$name, $league_id, $club_id, $start, $duration, $max_stars, $stages, $langs, $notes, $fee, $currency_id, $flags, $owner_rules, $gaining_id, $gaining_version);
		list ($series_id) = Db::record(get_label('sеriеs'), 'SELECT LAST_INSERT_ID()');

		$log_details = new stdClass();
		$log_details->name = $name;
		$log_details->league_id = $league_id;
		$log_details->club_id = $club_id;
		$log_details->start = $start;
		$log_details->duration = $duration;
		$log_details->max_stars = $max_stars;
		$log_details->stages = $stages;
		$log_details->langs = $langs;
		$log_details->notes = $notes;
		$log_details->fee = $fee;
		$log_details->currency_id = $currency_id;
		$log_details->rules_code = $owner_rules;
		$log_details->flags = $flags;
		$log_details->gaining_id = $gaining_id;
		$log_details->gaining_version = $gaining_version;
		$log_details->parent_series = json_encode($parent_series);
		db_log(LOG_OBJECT_SERIES, 'created', $log_details, $series_id, $club_id, $league_id);
		
		// create parent series records
		foreach ($parent_series as $s)
		{
			list($sflags, $sleague_id, $sclub_id) = Db::record(
				get_label('sеriеs'), 'SELECT flags, league_id, club_id FROM series WHERE id = ?', $s->id);
			if ((series_plays($sflags) & SERIES_FLAG_PLAYS_SERIES) == 0)
			{
				throw new Exc(get_label('This series does not take [0].', get_series_plays_name(SERIES_FLAG_PLAYS_SERIES)));
			}
			if (!can_enter_series($sflags, new SeriesOwner($sleague_id, $sclub_id), $s->id))
			{
				throw new Exc(get_label('Only the people who run this series may enter a tournament or a subseries into it.'));
			}
			Db::exec(
				get_label('sеriеs'), 
				'INSERT INTO series_series (child_id, parent_id, stars) values (?, ?, ?)',
				$series_id, $s->id, $s->stars);
			// todo send notification for series too:
			// send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
		}
		
		Db::commit();
		$this->response['series_id'] = $series_id;
	}
	
	function create_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER, 'Create series. A series belongs either to a league or to a club - pass exactly one of <q>league_id</q> and <q>club_id</q>.');
		$help->request_param('name', 'Series name.');
		$help->request_param('league_id', 'League id. Requires manager permissions in that league.', 'the series belongs to the club passed in <q>club_id</q>.');
		$help->request_param('club_id', 'Club id. Requires manager permissions in that club.', 'the series belongs to the league passed in <q>league_id</q>.');
		$series_help = $help->request_param('parent_series', 'Json array of series that this series belongs to. For example "[{id:2,stars:3},{id:4,stars:1}]". Ignored for a series with no dates: a parent series is one this series runs within, and such a series has no period to run within.', 'series does not belong to any series - same as "[]".');
			$series_help->sub_param('id', 'Series id');
			$series_help->sub_param('stars', 'Number of stars for this series.');
		$help->request_param('max_stars', 'How many stars this series gives a tournament or a subseries entered into it, from 0 to ' . SERIES_MAX_STARS_LIMIT . '. Zero means the series does not grade its tournaments at all: it says which stage each of them belongs to instead.', 'whatever <q>type</q> implies.');
		$help->request_param('flags', 'Series flags. What is played in the series is two of them, as a bit combination of: ' . SERIES_FLAG_PLAYS_TOURNAMENTS . ' - tournaments; ' . SERIES_FLAG_PLAYS_SERIES . ' - subseries. Naming the flags and naming neither of these is refused: there is no series that takes nothing. Leaving <q>flags</q> out altogether leaves the question to <q>type</q>. A series takes nothing else: entering a tournament into a series of subseries is refused, and so is the other way round.', 'whatever <q>type</q> implies.');
		$help->request_param('stages', 'How many stages the series has, from 1 to ' . SERIES_MAX_STAGES . '. One means there is no ladder - every tournament is a tournament of the series. The stages of a series with n of them are ' . SERIES_STAGE_BASE . ' - the base stage - and 1..n-1, numbered the way tournaments number the rounds of their own playoff: ' . SERIES_STAGE_FINAL . ' - the final, ' . SERIES_STAGE_SEMIFINAL . ' - the semifinal, ' . SERIES_STAGE_QUARTERFINAL . ' - the quarterfinal, and so on.', 'whatever <q>type</q> implies.');
		$help->request_param('infinite', 'Pass 1 for a series with no dates at all - one that is infinite in both directions, which is what a recurring tournament is. <q>start</q> and <q>end</q> are then ignored.', 'whatever <q>type</q> implies.');
		$help->request_param('type', 'Not a property of a series and not stored: the type is derived from <q>max_stars</q> and <q>infinite</q>. Passing it here only supplies the shape such a series usually has, for whichever of those three is left out: ' . SERIES_TYPE_SEASON . ' - a season, ' . SERIES_TYPE_SERIES . ' - a series of tournaments, ' . SERIES_TYPE_RECURRING . ' - a recurring tournament.', SERIES_TYPE_SERIES . ' - a series of tournaments.');
		$help->request_param('start', 'Series start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds. Not used by a series with no dates.');
		$help->request_param('end', 'Series end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds. Not used by a series with no dates.');
		$help->request_param('notes', 'Series notes. Just a text.', 'empty.');
		$help->request_param('fee', 'Admission rate per player-tournament. Send -1 if unknown.', '-1.');
		$help->request_param('currency_id', 'Currency id for the admission rate. Send -1 if unknown.', '-1.');
		$help->request_param('langs', 'Languages on this series. A bit combination of language ids.', 'all league languages are used.');
		$help->request_param('flags', 'Series flags. Currently not used');
		$help->request_param('gaining_id', 'Gaining system id.', 'Default gaining of the league is used.');
		$help->request_param('gaining_version', 'Gaining system version.', 'Latest gaining system version is used.');
/*		
									'A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'<li value="128">Tournament rounds must use this tournament game rules.</li>' .
									'<li value="256">Tournament rounds must use this tournament scoring system.</li>' .
									'</ol>', '384 (=128+256) is used, which is a short term tournament enforcing rules and scoring system.');*/
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// change
	//-------------------------------------------------------------------------------------------------------
	function change_op()
	{
		global $_profile;
		$series_id = (int)get_required_param('series_id');
		$timezone = get_timezone();
		Db::begin();
		
		list ($league_id, $club_id, $old_name, $old_start, $old_duration, $old_max_stars, $old_stages, $old_langs, $old_notes, $old_fee, $old_currency_id, $old_flags, $old_gaining_id, $old_gaining_version) =
			Db::record(get_label('sеriеs'), 'SELECT league_id, club_id, name, start_time, duration, max_stars, stages, langs, notes, fee, currency_id, flags, gaining_id, gaining_version FROM series WHERE id = ?', $series_id);
		$old_max_stars = (int)$old_max_stars;
		$old_stages = (int)$old_stages;

		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);
		$owner_flags = 0;
		if ($owner->is_league())
		{
			list($owner_flags) = Db::record(get_label('league'), 'SELECT flags FROM leagues WHERE id = ?', $owner->id);
		}

		$gaining_id = (int)get_optional_param('gaining_id', $old_gaining_id);
		if ($gaining_id != $old_gaining_id)
		{
			list($old_gaining_version) = Db::record(get_label('gaining system'), 'SELECT version FROM gainings WHERE id = ?', $gaining_id);
		}
		$gaining_version = (int)get_optional_param('gaining_version', $old_gaining_version);
		
		$name = get_optional_param('name', $old_name);
		$notes = get_optional_param('notes', $old_notes);
		$langs = get_optional_param('langs', $old_langs);
		$given_flags = get_optional_param('flags', NULL);
		$flags = (int)(is_null($given_flags) ? $old_flags : $given_flags);
		$flags = ($flags & SERIES_EDITABLE_MASK) + ($old_flags & ~SERIES_EDITABLE_MASK);
		if (($flags & SERIES_FLAGS_PLAYS_MASK) == 0)
		{
			// Carrying neither of the two bits that say what is played in the series is not the same
			// as leaving them out. A request that names the flags and names neither of these is
			// describing a series nothing can be entered into, and there is no such series.
			if (!is_null($given_flags))
			{
				throw new Exc(get_label('A series has to take tournaments, subseries, or both.'));
			}
			$flags |= series_plays($old_flags);
		}
		
		// A series cannot stop taking a kind of competition it already holds: the members would
		// stay in series_tournaments or series_series and keep being counted, while the series
		// said it takes none of them. They have to be taken off it first.
		if (($flags & SERIES_FLAG_PLAYS_TOURNAMENTS) == 0)
		{
			list ($held) = Db::record(get_label('tournament'), 'SELECT count(*) FROM series_tournaments WHERE series_id = ?', $series_id);
			if ($held > 0)
			{
				throw new Exc(get_label('There are [0] in the series already. Take them off it before saying that it does not take them.', get_series_plays_name(SERIES_FLAG_PLAYS_TOURNAMENTS)));
			}
		}
		if (($flags & SERIES_FLAG_PLAYS_SERIES) == 0)
		{
			list ($held) = Db::record(get_label('sеriеs'), 'SELECT count(*) FROM series_series WHERE parent_id = ?', $series_id);
			if ($held > 0)
			{
				throw new Exc(get_label('There are [0] in the series already. Take them off it before saying that it does not take them.', get_series_plays_name(SERIES_FLAG_PLAYS_SERIES)));
			}
		}
		// Elite is a property of elite leagues. A club series is never elite.
		if (($owner_flags & LEAGUE_FLAG_ELITE) == 0)
		{
			$flags &= ~SERIES_FLAG_ELITE;
		}
		if (!is_permitted(PERMISSION_ADMIN))
		{
			$flags = ($flags & ~SERIES_ADMIN_EDITABLE_MASK) + ($old_flags & SERIES_ADMIN_EDITABLE_MASK);
		}
		
		$fee = get_optional_param('fee', $old_fee);
		if (!is_null($fee) && $fee < 0)
		{
			$fee = NULL;
		}
		$currency_id = get_optional_param('currency_id', $old_currency_id);
		if (!is_null($currency_id) && $currency_id <= 0)
		{
			$currency_id = NULL;
		}
		
		// A series is made of three properties - how many stars it gives, how many stages it has,
		// and whether it has dates - and its type follows from them rather than being stored. Each
		// of the three keeps its stored value when it is not passed.
		//
		// A type may be passed instead, and then it stands for the shape such a series usually has
		// and supplies whichever of the three the caller left out, exactly as in create. That is
		// how an editor offering a type to pick works, and all it does.
		$preset = get_optional_param('type', NULL);
		if (is_null($preset))
		{
			$preset = array(
				'max_stars' => $old_max_stars,
				'stages' => $old_stages,
				'infinite' => series_is_infinite($old_start) ? 1 : 0);
		}
		else if (is_series_type_valid($preset))
		{
			$preset = get_series_type_defaults($preset);
		}
		else
		{
			throw new Exc(get_label('Unknown [0]', get_label('series type')));
		}

		// Lowering the maximum leaves the stars already handed out alone: they were given under the
		// maximum of the day, and rewriting them is not this operation's business. Same for the
		// stages: a membership left on a stage the series no longer has reads as the base stage.
		$max_stars = series_max_stars(get_optional_param('max_stars', $preset['max_stars']));
		$stages = series_stage_count(get_optional_param('stages', $preset['stages']));
		$infinite = (int)get_optional_param('infinite', $preset['infinite']) != 0;

		if ($infinite)
		{
			if (series_is_infinite($old_start))
			{
				// It has no dates to edit, so leave the ones it has alone. That is also what keeps
				// a finished recurring tournament finished - finishing one cuts its duration short,
				// and handing it the eternal duration again would bring it back to life.
				$start = (int)$old_start;
				$duration = (int)$old_duration;
			}
			else
			{
				// It loses its dates.
				$start = SERIES_INFINITE_START;
				$duration = SERIES_ETERNAL_DURATION;
			}
		}
		else
		{
			// A series that had no dates has none to fall back on, so whoever gives it dates has
			// to say what they are.
			if (series_is_infinite($old_start))
			{
				$start_datetime = get_datetime(get_required_param('start'), $timezone);
				$end_datetime = get_datetime(get_required_param('end'), $timezone);
			}
			else
			{
				$old_start_datetime = get_datetime($old_start, $timezone);
				$old_end_datetime = get_datetime($old_start + $old_duration, $timezone);
				$start_datetime = get_datetime(get_optional_param('start', datetime_to_string($old_start_datetime)), $timezone);
				$end_datetime = get_datetime(get_optional_param('end', datetime_to_string($old_end_datetime)), $timezone);
			}
			$start = $start_datetime->getTimestamp();
			$end = $end_datetime->getTimestamp();
			$duration = $end - $start;
			if ($duration <= 0)
			{
				throw new Exc(get_label('Series end before or right after the start.'));
			}
		}

		$logo_uploaded = false;
		if (isset($_FILES['logo']))
		{
			upload_logo('logo', '../../' . SERIES_PICS_DIR, $series_id);
			
			$icon_version = (($flags & SERIES_ICON_MASK) >> SERIES_ICON_MASK_OFFSET) + 1;
			if ($icon_version > SERIES_ICON_MAX_VERSION)
			{
				$icon_version = 1;
			}
			$flags = ($flags & ~SERIES_ICON_MASK) + ($icon_version << SERIES_ICON_MASK_OFFSET);
			$logo_uploaded = true;
		}
		
		// update parent series records
		$parent_series = json_decode(get_optional_param('parent_series', NULL));

		// A series with no dates is inside nothing. A parent series is one this series runs within,
		// and a series with no dates has no period to run within - which is why the editor stops
		// offering parents for it.
		//
		// That by itself is not enough. Leaving parent_series out is what tells this operation to
		// keep the parents a series already has, and the editor leaves it out exactly when the
		// series has no dates - so a series given dates, put inside another and then made eternal
		// again would quietly keep a link it can no longer have. Being eternal clears them instead.
		if ($infinite)
		{
			$parent_series = array();
		}
		$parent_series_changed = false;
		if (!is_null($parent_series))
		{
			$old_parent_series = array();
			$query = new DbQuery(
				'SELECT ss.parent_id, ss.stars, s.flags, s.league_id, s.club_id FROM series_series ss' .
				' JOIN series s ON s.id = ss.parent_id WHERE ss.child_id = ?', $series_id);
			while ($row = $query->next())
			{
				$s = new stdClass();
				list($s->id, $s->stars, $s->flags, $s->league_id, $s->club_id) = $row;
				$old_parent_series[$s->id] = $s;
			}
			
			foreach ($parent_series as $s)
			{
				if (isset($old_parent_series[$s->id]))
				{
					$os = $old_parent_series[$s->id];
					if ($os->stars != $s->stars)
					{
						Db::exec(
							get_label('sеriеs'), 
							'UPDATE series_series SET stars = ? WHERE parent_id = ? AND child_id = ?', $s->stars, $s->id, $series_id);
						// todo send notification for series too:
						// send_series_notification('tournament_series_change', $tournament_id, $name, $club_id, $club->name, $s);
						$parent_series_changed = true;
					}
					unset($old_parent_series[$s->id]);
				}
				else
				{
					list($sflags, $sleague_id, $sclub_id) = Db::record(
						get_label('sеriеs'), 'SELECT flags, league_id, club_id FROM series WHERE id = ?', $s->id);
					if ((series_plays($sflags) & SERIES_FLAG_PLAYS_SERIES) == 0)
					{
						throw new Exc(get_label('This series does not take [0].', get_series_plays_name(SERIES_FLAG_PLAYS_SERIES)));
					}
					if (!can_enter_series($sflags, new SeriesOwner($sleague_id, $sclub_id), $s->id))
					{
						throw new Exc(get_label('Only the people who run this series may enter a tournament or a subseries into it.'));
					}
					Db::exec(
						get_label('sеriеs'), 
						'INSERT INTO series_series (child_id, parent_id, stars) values (?, ?, ?)',
						$series_id, $s->id, $s->stars);
					// todo send notification for series too:
					// send_series_notification('tournament_series_add', $tournament_id, $name, $club_id, $club->name, $s);
					$parent_series_changed = true;
				}
			}
			
			foreach ($old_parent_series as $parent_id => $s)
			{
				// A link to a private series stays where it is unless the one taking it off runs that
				// series. Whoever cannot add a subseries to it cannot take one out either.
				if (!can_enter_series($s->flags, new SeriesOwner($s->league_id, $s->club_id), $parent_id))
				{
					continue;
				}
				Db::exec(
					get_label('sеriеs'), 
					'DELETE FROM series_series WHERE child_id = ? AND parent_id = ?', $series_id, $parent_id);
				// todo send notification for series too:
				// send_series_notification('tournament_series_remove', $tournament_id, $name, $club_id, $club->name, $s);
			}
		}
		
		// Update child tournaments when elite flag changed
		if (($flags & SERIES_FLAG_ELITE) != ($old_flags & SERIES_FLAG_ELITE))
		{
			$query = new DbQuery(
				'SELECT g.id, g.end_time FROM games g'.
				' JOIN series_tournaments t ON t.tournament_id = g.tournament_id'.
				' WHERE t.series_id = ? AND t.stars > 1 AND (g.flags & '.(GAME_FLAG_RATING | GAME_FLAG_CANCELED).') = '.GAME_FLAG_RATING.
				' ORDER BY g.end_time, g.id'.
				' LIMIT 1', $series_id);
			if ($row = $query->next())
			{
				list ($game_id, $game_end_time) = $row;
				$prev_game_id = NULL;
				$query = new DbQuery('SELECT id FROM games WHERE end_time < ? OR (end_time = ? AND id < ?) ORDER BY end_time DESC, id DESC', $game_end_time, $game_end_time, $game_id);
				if ($row = $query->next())
				{
					list($prev_game_id) = $row;
				}
				Game::rebuild_ratings($prev_game_id, $game_end_time);
			}
			
			if ($flags & SERIES_FLAG_ELITE)
			{
				Db::exec(get_label('tournaments'), 'UPDATE series_tournaments st JOIN tournaments t ON t.id = st.tournament_id SET t.flags = t.flags | ' . TOURNAMENT_FLAG_ELITE . ' WHERE st.series_id = ? AND st.stars > 1', $series_id);
			}
			else
			{
				Db::exec(get_label('tournaments'), 
					'UPDATE series_tournaments st'.
					' JOIN tournaments t ON t.id = st.tournament_id'.
					' SET t.flags = t.flags & ~' . TOURNAMENT_FLAG_ELITE. 
					' WHERE st.series_id = ? AND t.id NOT IN ('.
						'SELECT st1.tournament_id'.
						' FROM series_tournaments st1'.
						' JOIN series s1 ON s1.id = st1.series_id'.
						' WHERE s1.id <> st.series_id AND (s1.flags & ' . SERIES_FLAG_ELITE . ') <> 0 AND st1.stars > 1'.
					')', $series_id);
			}
		}
		
		if ($gaining_id != $old_gaining_id || $gaining_version != $old_gaining_version)
		{
			$flags |= SERIES_FLAG_DIRTY;
		}
		
		if ($start + $duration > time())
		{
			$flags &= ~SERIES_FLAG_FINISHED;
		}
			
		Db::exec(
			get_label('sеriеs'), 
			'UPDATE series SET name = ?, start_time = ?, duration = ?, max_stars = ?, stages = ?, langs = ?, notes = ?, fee = ?, currency_id = ?, flags = ?, gaining_id = ?, gaining_version = ? WHERE id = ?',
			$name, $start, $duration, $max_stars, $stages, $langs, $notes, $fee, $currency_id, $flags, $gaining_id, $gaining_version, $series_id);
		if (Db::affected_rows() > 0 || $parent_series_changed)
		{
			$log_details = new stdClass();
			if ($name != $old_name)
			{
				$log_details->name = $name;
			}
			if ($start != $old_start)
			{
				$log_details->start = $start;
			}
			if ($duration != $old_duration)
			{
				$log_details->duration = $duration;
			}
			if ($max_stars != $old_max_stars)
			{
				$log_details->max_stars = $max_stars;
			}
			if ($stages != $old_stages)
			{
				$log_details->stages = $stages;
			}
			if ($langs != $old_langs)
			{
				$log_details->langs = $langs;
			}
			if ($notes != $old_notes)
			{
				$log_details->notes = $notes;
			}
			if ($fee != $old_fee)
			{
				$log_details->fee = $fee;
			}
			if ($currency_id != $old_currency_id)
			{
				$log_details->currency_id = $currency_id;
			}
			if ($flags != $old_flags)
			{
				$log_details->flags = $flags;
			}
			if ($gaining_id != $old_gaining_id)
			{
				$log_details->gaining_id = $gaining_id;
				$log_details->gaining_version = $gaining_version;
			}
			else if ($gaining_version != $old_gaining_version)
			{
				$log_details->gaining_version = $gaining_version;
			}
			if ($logo_uploaded)
			{
				$log_details->logo_uploaded = true;
			}
			if ($parent_series_changed)
			{
				$log_details->parent_series = json_encode($parent_series);
			}
			db_log(LOG_OBJECT_SERIES, 'changed', $log_details, $series_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function change_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Change series.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('name', 'Series name.', 'remains the same.');
		$help->request_param('max_stars', 'How many stars this series gives a tournament or a subseries entered into it, from 0 to ' . SERIES_MAX_STARS_LIMIT . '. Zero means the series does not grade its tournaments at all: it says which stage each of them belongs to instead. Lowering it leaves the stars already handed out alone.', 'remains the same.');
		$help->request_param('flags', 'Series flags. What is played in the series is two of them, as a bit combination of: ' . SERIES_FLAG_PLAYS_TOURNAMENTS . ' - tournaments; ' . SERIES_FLAG_PLAYS_SERIES . ' - subseries. Naming the flags and naming neither of these is refused: there is no series that takes nothing. Leaving <q>flags</q> out altogether leaves the question alone. A kind the series already holds cannot be taken away: the tournaments or the subseries have to be taken off it first.', 'remain the same.');
		$help->request_param('stages', 'How many stages the series has, from 1 to ' . SERIES_MAX_STAGES . '. See the create operation for the numbering. Lowering it leaves a tournament on a stage the series no longer has, and that tournament reads as the base stage from then on.', 'remains the same.');
		$help->request_param('infinite', 'Pass 1 for a series with no dates at all - one that is infinite in both directions, which is what a recurring tournament is. Such a series ignores <q>start</q> and <q>end</q>; a series that stops being one must be given both.', 'remains the same.');
		$help->request_param('type', 'Not a property of a series and not stored: the type is derived from <q>max_stars</q> and <q>infinite</q>. Passing it here only supplies the shape such a series usually has, for whichever of those three is left out - so passing it alone resets all three: ' . SERIES_TYPE_SEASON . ' - a season, ' . SERIES_TYPE_SERIES . ' - a series of tournaments, ' . SERIES_TYPE_RECURRING . ' - a recurring tournament.', 'the three properties remain the same.');
		$help->request_param('start', 'Series start date. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('end', 'Series end date. Exclusive. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.', 'remains the same.');
		$help->request_param('notes', 'Series notes. Just a text.', 'remains the same.');
		$help->request_param('fee', 'Admission rate per player-tournament. Send -1 if unknown.', 'remains the same.');
		$help->request_param('currency_id', 'Currency for admission rate. Send -1 if unknown.', 'remains the same.');
		$help->request_param('langs', 'Languages on this series. A bit combination of language ids.' . valid_langs_help(), 'remains the same.');
		$help->request_param('flags', 'Series flags. Not used yet');
		$help->request_param('gaining_id', 'Gaining system id.', 'remains the same.');
		$help->request_param('gaining_version', 'Gaining system version.', 'remains the same, or latest version of the gaining system is used if gaining system was changed.');
/*									'A bit cobination of:<ol>' .
									'<li value="16">This is a long term tournament when set. Long term tournament is something like a season championship. Short-term tournament is a one day to one week competition.</li>' .
									'<li value="32">When a moderator starts a new game, they can assign it to the tournament even if the game is in a non-tournament or in any other tournament event.</li>' .
									'<li value="64">When a custom event is created, it can be assigned to this tournament as a round.</li>' .
									'</ol>', 'remain the same.');*/
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// cancel
	//-------------------------------------------------------------------------------------------------------
	function cancel_op()
	{
		$series_id = (int)get_required_param('series_id');
		
		Db::begin();
		list($league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		Db::exec(get_label('sеriеs'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_CANCELED . ') WHERE id = ?', $series_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_SERIES, 'canceled', NULL, $series_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function cancel_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Cancel series.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// restore
	//-------------------------------------------------------------------------------------------------------
	function restore_op()
	{
		$series_id = (int)get_required_param('series_id');
		
		Db::begin();
		list($league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		Db::exec(get_label('sеriеs'), 'UPDATE series SET flags = (flags & ~' . SERIES_FLAG_CANCELED . ') WHERE id = ?', $series_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_SERIES, 'restored', NULL, $series_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function restore_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Restore canceled series.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// rebuild_places
	//-------------------------------------------------------------------------------------------------------
	function rebuild_places_op()
	{
		$series_id = (int)get_optional_param('series_id', 0);
		
		Db::begin();
		
		if ($series_id > 0)
		{
			list($league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
			$owner = new SeriesOwner($league_id, $club_id);
			check_series_permissions($owner, $series_id);
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $series_id);
		}
		else
		{
			check_permissions(PERMISSION_ADMIN);
			Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY);
		}
		db_log(LOG_OBJECT_SERIES, 'rebuild_places', NULL, $series_id);
		Db::commit();
	}
	
	function rebuild_places_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Schedules series places for rebuild. It is needed when in user series view the place taken is wrong.');
		$help->request_param('series_id', 'Series id to rebuild places.', 'places are rebuilt for all series.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// finish
	//-------------------------------------------------------------------------------------------------------
	function finish_op()
	{
		$series_id = (int)get_required_param('series_id');
		$now = time();
		
		Db::begin();
		list($league_id, $club_id, $start_time, $duration, $flags) = Db::record(get_label('series'), 'SELECT league_id, club_id, start_time, duration, flags FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		// Finishing a series cuts it short of the end it was given, so that its results reach its
		// parents without waiting for that end to arrive. A series with no dates was never given
		// one: there is nothing to cut short, and cutting its stored duration anyway would leave a
		// series that reads as eternal and is over at the same time.
		if (series_is_infinite($start_time))
		{
			throw new Exc(get_label('A series with no dates cannot be finished.'));
		}

		if (($flags & SERIES_FLAG_FINISHED) == 0)
		{
			if ($now < $start_time)
			{
				$start_time = $now;
			}
			if ($start_time + $duration > $now)
			{
				$duration = $now - $start_time;
			}
			Db::exec(get_label('series'), 'UPDATE series SET start_time = ?, duration = ? WHERE id = ?', $start_time, $duration, $series_id);
			db_log(LOG_OBJECT_SERIES, 'finished', NULL, $series_id, $club_id, $league_id);
		}
		Db::commit();
	}
	
	function finish_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Finish the series. After finishing the series within one hour players will get all parent series points for this series. Finish series functionality lets not to wait until the time expires and get the results quicker. A series with no dates has no time to wait for and cannot be finished.');
		$help->request_param('series_id', 'Series id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// transfer
	//-------------------------------------------------------------------------------------------------------
	function transfer_op()
	{
		$series_id = (int)get_required_param('series_id');
		$new_league_id = (int)get_optional_param('league_id', 0);
		$new_club_id = (int)get_optional_param('club_id', 0);
		if (($new_league_id > 0) == ($new_club_id > 0))
		{
			throw new Exc(get_label('A series belongs either to a league or to a club, but not to both.'));
		}

		Db::begin();
		list ($league_id, $club_id, $flags) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id, flags FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $club_id);

		// Authorized against the owner it is leaving. Whoever can manage the series can give it
		// away, and after that they may well have no say over it at all.
		check_series_permissions($owner, $series_id);

		if ($new_league_id > 0)
		{
			list($new_owner_name, $new_owner_flags) = Db::record(get_label('league'), 'SELECT name, flags FROM leagues WHERE id = ?', $new_league_id);
			$new_owner = new SeriesOwner($new_league_id, NULL);
			$is_elite_owner = ($new_owner_flags & LEAGUE_FLAG_ELITE) != 0;
			$new_club_id = NULL;
		}
		else
		{
			list($new_owner_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $new_club_id);
			$new_owner = new SeriesOwner(NULL, $new_club_id);
			$is_elite_owner = false;
			$new_league_id = NULL;
		}

		if ($new_owner->kind == $owner->kind && $new_owner->id == $owner->id)
		{
			throw new Exc(get_label('[0] already owns this series.', $new_owner_name));
		}

		// Elite is a property of elite leagues, and turning it off is not a rename: the rating of
		// every game of every two-star tournament of the series was computed with it on and has to
		// be rebuilt. That is what the series editor does when the flag is cleared, so this
		// operation refuses rather than doing half of it silently.
		if (($flags & SERIES_FLAG_ELITE) != 0 && !$is_elite_owner)
		{
			throw new Exc(get_label('This is an elite series. Turn the elite flag off before giving it to [0].', $new_owner_name));
		}

		Db::exec(get_label('sеriеs'), 'UPDATE series SET league_id = ?, club_id = ? WHERE id = ?', $new_league_id, $new_club_id, $series_id);

		// Whatever the new owner brings with it is no longer an invitation.
		$permanent_clubs = array_keys(get_series_permanent_clubs($new_owner));
		if (count($permanent_clubs) > 0)
		{
			Db::exec(get_label('club'),
				'DELETE FROM series_clubs WHERE series_id = ? AND club_id IN (' . implode(',', $permanent_clubs) . ')', $series_id);
		}

		$log_details = new stdClass();
		$log_details->league_id = $new_league_id;
		$log_details->club_id = $new_club_id;
		db_log(LOG_OBJECT_SERIES, 'transferred', $log_details, $series_id, $new_club_id, $new_league_id);

		Db::commit();
	}

	function transfer_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER,
			'Give the series to another owner. Pass exactly one of <q>league_id</q> and <q>club_id</q>. Authorized against the current owner, so whoever does it may lose every right over the series. Refused while the series is elite and the new owner is not an elite league.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('league_id', 'Id of the league to give the series to.', 'the series goes to the club passed in <q>club_id</q>.');
		$help->request_param('club_id', 'Id of the club to give the series to.', 'the series goes to the league passed in <q>league_id</q>.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// add_user
	//-------------------------------------------------------------------------------------------------------
	function add_user_op()
	{
		$series_id = (int)get_required_param('series_id');
		$user_id = (int)get_required_param('user_id');
		
		list ($league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		check_series_permissions(new SeriesOwner($league_id, $club_id), $series_id);
		
		$flags = (int)get_optional_param('access_flags', USER_PERM_REFEREE) & USER_PERM_MASK;
		if ($flags == 0)
		{
			throw new Exc(get_label('Please choose at least one role for the user.'));
		}
		
		Db::begin();
		list ($count) = Db::record(get_label('user'), 'SELECT count(*) FROM series_regs WHERE series_id = ? AND user_id = ?', $series_id, $user_id);
		if ($count == 0)
		{
			Db::exec(get_label('user'), 'INSERT INTO series_regs (series_id, user_id, flags) values (?, ?, ?)', $series_id, $user_id, $flags);
			$log_details = new stdClass();
			$log_details->series_id = $series_id;
			$log_details->series_flags = $flags;
			db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id, $league_id);
		}
		Db::commit();
		
		$this->response['series_id'] = $series_id;
		$this->response['user_id'] = $user_id;
	}
	
	function add_user_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Give a user permissions in a series. A manager of the series may do with it everything the managers of the league or the club running it may do; a referee of the series referees every tournament played in it. Neither reaches a subseries: a subseries is a series of its own and has its own people.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('user_id', 'User id.');
		$help->request_param('access_flags', 'What the user becomes in the series, as a bit combination of: ' . USER_PERM_PLAYER . ' - a player; ' . USER_PERM_REFEREE . ' - a referee; ' . USER_PERM_MANAGER . ' - a manager. At least one of them is required. A user who is on the series already is left exactly as they were - use the <q>access</q> operation of api/ops/user.php to change them.', 'is ' . USER_PERM_REFEREE . ' - a referee.');
		$help->response_param('series_id', 'Series id.');
		$help->response_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// remove_user
	//-------------------------------------------------------------------------------------------------------
	function remove_user_op()
	{
		$series_id = (int)get_required_param('series_id');
		$user_id = (int)get_required_param('user_id');
		
		list ($league_id, $club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		check_series_permissions(new SeriesOwner($league_id, $club_id), $series_id);
		
		Db::begin();
		// The exhibition flag lives on the same row, and it is what keeps a player out of the
		// standings, so the series is asked to count itself again.
		list ($old_flags) = Db::record(get_label('user'), 'SELECT flags FROM series_regs WHERE series_id = ? AND user_id = ?', $series_id, $user_id);
		Db::exec(get_label('user'), 'DELETE FROM series_regs WHERE series_id = ? AND user_id = ?', $series_id, $user_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->series_id = $series_id;
			$log_details->series_flags = 0;
			db_log(LOG_OBJECT_USER, 'permissions changed', $log_details, $user_id, $club_id, $league_id);
			if (($old_flags & USER_FLAG_EXHIBITION_PLAYER) != 0)
			{
				Db::exec(get_label('series'), 'UPDATE series SET flags = flags | ' . SERIES_FLAG_DIRTY . ' WHERE id = ?', $series_id);
			}
		}
		Db::commit();
	}
	
	function remove_user_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Take a user off a series, with every permission they had in it.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('user_id', 'User id.');
		return $help;
	}
	
	//-------------------------------------------------------------------------------------------------------
	// add_club
	//-------------------------------------------------------------------------------------------------------
	function add_club_op()
	{
		$series_id = (int)get_required_param('series_id');
		$club_id = (int)get_required_param('club_id');

		Db::begin();
		list ($league_id, $owner_club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $owner_club_id);
		check_series_permissions($owner, $series_id);

		list ($club_name) = Db::record(get_label('club'), 'SELECT name FROM clubs WHERE id = ?', $club_id);

		// The clubs that are on the series by ownership are already there and cannot be invited.
		$permanent_clubs = get_series_permanent_clubs($owner);
		if (isset($permanent_clubs[$club_id]))
		{
			throw new Exc(get_label('[0] is already in the series and cannot be invited to it.', $club_name));
		}

		Db::exec(get_label('club'), 'INSERT IGNORE INTO series_clubs (series_id, club_id) VALUES (?, ?)', $series_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->club_id = $club_id;
			db_log(LOG_OBJECT_SERIES, 'club added', $log_details, $series_id, $owner_club_id, $league_id);
		}
		Db::commit();
	}

	function add_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER,
			'Invite a club to the series. Its managers can then enter tournaments of that club into the series. It grants nothing else - the club gets no permissions over the series itself.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// remove_club
	//-------------------------------------------------------------------------------------------------------
	function remove_club_op()
	{
		$series_id = (int)get_required_param('series_id');
		$club_id = (int)get_required_param('club_id');

		Db::begin();
		list ($league_id, $owner_club_id) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $owner_club_id);
		check_series_permissions($owner, $series_id);

		// Only an invitation can be withdrawn. A club that is on the series because it owns it, or
		// because it belongs to the owning league, is not in this table in the first place.
		Db::exec(get_label('club'), 'DELETE FROM series_clubs WHERE series_id = ? AND club_id = ?', $series_id, $club_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			$log_details->club_id = $club_id;
			db_log(LOG_OBJECT_SERIES, 'club removed', $log_details, $series_id, $owner_club_id, $league_id);
		}
		Db::commit();
	}

	function remove_club_op_help()
	{
		$help = new ApiHelp(PERMISSION_CLUB_MANAGER | PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER,
			'Withdraw the invitation of a club to the series. The tournaments it already entered stay in the series. A club that is on the series by ownership cannot be removed this way.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('club_id', 'Club id.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// comment
	//-------------------------------------------------------------------------------------------------------
	// function comment_op()
	// {
		// global $_profile, $_lang;
		
		// check_permissions(PERMISSION_USER);
		// $series_id = (int)get_required_param('id');
		// $comment = prepare_message(get_required_param('comment'));
		// $lang = detect_lang($comment);
		// if ($lang == LANG_NO)
		// {
			// $lang = $_lang;
		// }
		
		// Db::exec(get_label('comment'), 'INSERT INTO series_comments (time, user_id, comment, series_id, lang) VALUES (UNIX_TIMESTAMP(), ?, ?, ?, ?)', $_profile->user_id, $comment, $series_id, $lang);
		
		// $timezone = get_timezone();
		// list($series_id, $series_name, $series_start_time, $series_duration) = Db::record(get_label('sеriеs'), 'SELECT id, name, start_time, duration FROM series WHERE id = ?', $series_id);
		
		// $query = new DbQuery(
			// '(SELECT u.id, nu.name, u.email, u.flags, u.def_lang'.
			// ' FROM users u' .
			// ' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			// ' JOIN tournament_invitations ti ON u.id = ti.user_id' .
			// ' WHERE ti.status <> ' . TOURNAMENT_INVITATION_STATUS_DECLINED . ')' .
			// ' UNION DISTINCT ' .
			// ' (SELECT DISTINCT u.id, nu.name, u.email, u.flags, u.def_lang'.
			// ' FROM users u' .
			// ' JOIN names nu ON nu.id = u.name_id AND (nu.langs & u.def_lang) <> 0'.
			// ' JOIN tournament_comments c ON c.user_id = u.id' .
			// ' WHERE c.tournament_id = ?)', $tournament_id, $tournament_id);
		// //echo $query->get_parsed_sql();
		// while ($row = $query->next())
		// {
			// list($user_id, $user_email, $user_flags, $user_lang) = $row;
			// if ($user_id == $_profile->user_id || ($user_flags & USER_FLAG_NOTIFY) == 0 || empty($user_email))
			// {
				// continue;
			// }
		
			// $code = generate_email_code();
			// $request_base = get_server_url() . '/email_request.php?code=' . $code . '&user_id=' . $user_id;
			// $tags = array(
				// 'root' => new Tag(get_server_url()),
				// 'user_id' => new Tag($user_id),
				// 'user_name' => new Tag($user_name),
				// 'tournament_id' => new Tag($tournament_id),
				// 'tournament_name' => new Tag($tournament_name),
				// 'series_date' => new Tag(format_date_period($series_start_time, $series_duration, $series_timezone, false, $user_lang)),
				// 'addr' => new Tag($tournament_addr),
				// 'code' => new Tag($code),
				// 'sender' => new Tag($_profile->user_name),
				// 'message' => new Tag($comment),
				// 'url' => new Tag($request_base),
				// 'unsub' => new Tag('<a href="' . $request_base . '&unsub=1" target="_blank">', '</a>'));
			
			// list($subj, $body, $text_body) = include '../../include/languages/' . get_lang_code($user_lang) . '/email/comment_tournament.php';
			// $body = parse_tags($body, $tags);
			// $text_body = parse_tags($text_body, $tags);
			// send_notification($user_email, $body, $text_body, $subj, $user_id, $user_lang, EMAIL_OBJ_SERIES, $tournament_id, $code);
		// }
	// }
	
	// function comment_op_help()
	// {
		// $help = new ApiHelp(PERMISSION_USER, 'Leave a comment on the tournament.');
		// $help->request_param('id', 'Tournament id.');
		// $help->request_param('comment', 'Comment text.');
		// return $help;
	// }
	
	//-------------------------------------------------------------------------------------------------------
	// add_extra_points
	//-------------------------------------------------------------------------------------------------------
	function add_extra_points_op()
	{
		global $_lang;
		
		$series_id = (int)get_required_param('series_id');
		$user_id = (int)get_required_param('user_id');
		$reason = get_required_param('reason');
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		$details = get_optional_param('details');
		$points = (float)get_required_param('points');
		$timezone = get_timezone();
		$time = get_datetime(get_required_param('time'), $timezone)->getTimestamp();
		
		Db::begin();
		
		list($league_id, $club_id, $stages) = Db::record(get_label('sеriеs'), 'SELECT league_id, club_id, stages FROM series WHERE id = ?', $series_id);
		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		// Extra points are not a competition of the series, so which stage they count in is said
		// outright rather than following from anything. The base stage is where points for the
		// series as a whole belong, and that is what they are by default.
		$stage = series_stage($stages, get_optional_param('stage', SERIES_STAGE_BASE));

		Db::exec(get_label('points'), 'INSERT INTO series_extra_points (time, series_id, user_id, reason, details, points, stage) VALUES (?, ?, ?, ?, ?, ?, ?)', $time, $series_id, $user_id, $reason, $details, $points, $stage);
		list ($points_id) = Db::record(get_label('points'), 'SELECT LAST_INSERT_ID()');
		
		list($user_name) = Db::record(get_label('user'), 'SELECT nu.name FROM users u JOIN names nu ON nu.id = u.name_id AND (nu.langs & '.$_lang.') <> 0 WHERE u.id = ?', $user_id);
		$log_details = new stdClass();
		$log_details->time = $time;
		$log_details->user = $user_name;
		$log_details->user_is = $user_id;
		$log_details->series_id = $series_id;
		$log_details->points = $points;
		$log_details->reason = $reason;
		$log_details->stage = $stage;
		if (!empty($details))
		{
			$log_details->details = $details;
		}
		db_log(LOG_OBJECT_EXTRA_POINTS, 'created', $log_details, $points_id, $club_id, $league_id);
		
		Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		
		Db::commit();
		
		$this->response['points_id'] = $points_id;
	}
	
	function add_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Add extra points.');
		$help->request_param('series_id', 'Series id.');
		$help->request_param('user_id', 'User id. The user who is receiving or loosing points.');
		$help->request_param('time', 'Time when point are rewarded. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract.');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.');
		$help->request_param('stage', 'Which stage of the series these points count in - see the create operation for the numbering. Extra points are not a competition of the series, so this is said outright rather than following from anything.', SERIES_STAGE_BASE . ' - the base stage.');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'empty.');
		
		$help->response_param('points_id', 'Id of the created extra points object.');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// change_extra_points
	//-------------------------------------------------------------------------------------------------------
	function change_extra_points_op()
	{
		$points_id = (int)get_required_param('points_id');
		
		Db::begin();
		list($user_id, $series_id, $league_id, $club_id, $old_reason, $old_details, $old_points, $old_time, $old_stage, $stages) =
			Db::record(get_label('points'), 'SELECT p.user_id, p.series_id, e.league_id, e.club_id, p.reason, p.details, p.points, p.time, p.stage, e.stages FROM series_extra_points p JOIN series e ON e.id = p.series_id WHERE p.id = ?', $points_id);

		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		$reason = get_optional_param('reason', $old_reason);
		if (empty($reason))
		{
			throw new Exc(get_label('Please enter reason.'));
		}
		
		$details = get_optional_param('details', $old_details);
		$points = (float)get_optional_param('points', $old_points);
		$timezone = get_timezone();
		$time = get_datetime(get_optional_param('time', $old_time), $timezone)->getTimestamp();
		$old_stage = series_stage($stages, $old_stage);
		$stage = series_stage($stages, get_optional_param('stage', $old_stage));
		
		Db::exec(get_label('points'), 'UPDATE series_extra_points SET time = ?, reason = ?, details = ?, points = ?, stage = ? WHERE id = ?', $time, $reason, $details, $points, $stage, $points_id);
		if (Db::affected_rows() > 0)
		{
			$log_details = new stdClass();
			if ($reason != $old_reason)
			{
				$log_details->reason = $reason;
			}
			if ($details != $old_details)
			{
				$log_details->details = $details;
			}
			if ($points != $old_points)
			{
				$log_details->points = $points;
			}
			if ($time != $old_time)
			{
				$log_details->time = $time;
			}
			if ($stage != $old_stage)
			{
				$log_details->stage = $stage;
			}
			db_log(LOG_OBJECT_EXTRA_POINTS, 'changed', $log_details, $points_id, $club_id, $league_id);
			
			Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		}
		Db::commit();
	}
	
	function change_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Change extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		$help->request_param('time', 'Time when point are rewarded. The preferred format is either timestamp or "yyyy-mm-dd". It tries to interpret any other date format but there is no guarantee it succeeds.');
		$help->request_param('points', 'Floating number of points to add. Negative means substract.', 'remains the same');
		$help->request_param('reason', 'Reason for adding/substracting points. Must be not empty.', 'remains the same');
		$help->request_param('stage', 'Which stage of the series these points count in - see the create operation for the numbering.', 'remains the same');
		$help->request_param('details', 'Detailed explanation why user recieves or loses points.', 'remains the same');
		return $help;
	}

	//-------------------------------------------------------------------------------------------------------
	// delete_extra_points
	//-------------------------------------------------------------------------------------------------------
	function delete_extra_points_op()
	{
		$points_id = (int)get_required_param('points_id');
		
		Db::begin();
		list($league_id, $club_id, $series_id) = Db::record(get_label('points'), 'SELECT s.league_id, s.club_id, s.id FROM series_extra_points p JOIN series s ON s.id = p.series_id WHERE p.id = ?', $points_id);
		$owner = new SeriesOwner($league_id, $club_id);
		check_series_permissions($owner, $series_id);

		Db::exec(get_label('points'), 'DELETE FROM series_extra_points WHERE id = ?', $points_id);
		if (Db::affected_rows() > 0)
		{
			db_log(LOG_OBJECT_EXTRA_POINTS, 'deleted', NULL, $points_id, $club_id, $league_id);
			Db::exec(get_label('series'), 'UPDATE series SET flags = (flags | ' . SERIES_FLAG_DIRTY . ') WHERE id = ?', $series_id);
		}
		Db::commit();
	}
	
	function delete_extra_points_op_help()
	{
		$help = new ApiHelp(PERMISSION_LEAGUE_MANAGER | PERMISSION_SERIES_MANAGER, 'Delete extra points.');
		$help->request_param('points_id', 'Id of extra points object.');
		return $help;
	}
}

$page = new ApiPage();
$page->run('Series Operations', CURRENT_VERSION);

?>