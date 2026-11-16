<?php

require_once 'include/updater.php';
require_once 'include/series.php';
require_once 'include/tournament.php';

//-------------------------------------------------------------------------------------------------------
// Puts Atlantic Series (11), Pacific Series (12) and Mind Games 2024 (13) back the way they were
// before they were turned into series of tournaments with stages, and brings back the gaining
// system Mind Games 2024 was scored by.
//
// What they were is taken from the backup of 2026-09-09 (mafiawor_mafia_26_09_09.sql). That backup
// predates the columns max_stars and stages and the flags that say what is played in a series;
// those are what alter196, alter197 and alter199 gave every series of the backup - five stars,
// one stage, and both the tournaments and the subseries bit, as for any season with stars.
//
//  - Gaining system 7, "Серийник ☆☆☆ 4x10-2x10-1x10" of the AML, is created again with its only
//    version, under the set of names it had - that set is still in "names".
//  - Atlantic and Pacific are scored by gaining system 5 v1 again, Mind Games 2024 by 7 v1.
//  - All three grade with five stars and have a single stage, and take subseries again.
//  - Their finals and semi-finals get their stars back - 2 for the finals of Atlantic and
//    Pacific, 3 for the final of Mind Games and 2 for its two semi-finals - on the base stage,
//    and the elite flag that more than one star gives a tournament.
//  - Pacific gets its note back.
//  - The three series and the two seasons they are in are marked to be counted again.
//
// Every step looks at what is there first and does nothing when it is already right, so a second
// run changes nothing and says so.
//
//     php restore_aml_subseries.php -test -dry -log_level 3
//     php restore_aml_subseries.php -test -log_level 3
//
//     https://<site>/restore_aml_subseries.php?dry=1&log_level=3
//     https://<site>/restore_aml_subseries.php?log_level=3
//
// A one-off: delete it, and its logs, once it has run on production.
//-------------------------------------------------------------------------------------------------------
class RestoreAmlSubseries extends Updater
{
	private $dryRun = false;

	const GAINING_ID = 7;
	const GAINING_LEAGUE_ID = 2;
	const GAINING_NAME_ID = 11378;
	const GAINING_NAME = 'Серийник ☆☆☆ 4x10-2x10-1x10';
	const GAINING_FUNCTIONS = 832;
	const GAINING = '{"maxTournaments":1,"points":"table(stars-1) + (table(stars) - table(stars-1))*(((numPlayers-place)/(numPlayers-1))^3)","table":[1,10,21,34]}';

	const SEASONS = array(9, 10);

	function __construct()
	{
		parent::__construct(__FILE__);

		if (isset($_REQUEST['dry']))
		{
			$this->dryRun = (bool)$_REQUEST['dry'];
		}
		if (isset($_SERVER['argv']))
		{
			foreach ($_SERVER['argv'] as $arg)
			{
				if ($arg == '-dry')
				{
					$this->dryRun = true;
				}
			}
		}
	}

	// series id => what it was
	private function series()
	{
		return array(
			11 => (object)array('name' => 'Atlantic Series', 'gaining_id' => 5, 'gaining_version' => 1, 'notes' => NULL,
				'tournaments' => array(263 => 2)),
			12 => (object)array('name' => 'Pacific Series', 'gaining_id' => 5, 'gaining_version' => 1, 'notes' => 'Stars might change',
				'tournaments' => array(336 => 2)),
			13 => (object)array('name' => 'Mind Games', 'gaining_id' => self::GAINING_ID, 'gaining_version' => 1, 'notes' => NULL,
				'tournaments' => array(368 => 3, 375 => 2, 376 => 2)));
	}

	private function write($what, $sql)
	{
		$args = func_get_args();
		array_shift($args);
		if ($this->dryRun)
		{
			return;
		}
		call_user_func_array(array('Db', 'exec'), array_merge(array($what), $args));
	}

	function restore_task_start()
	{
		$this->vars->changes = 0;
		if ($this->dryRun)
		{
			$this->log('DRY RUN - nothing will be written.');
		}
	}

	function restore_task($items_count)
	{
		Db::begin();
		$this->restore_gaining();
		foreach ($this->series() as $series_id => $s)
		{
			$this->restore_series($series_id, $s);
		}
		$this->mark_seasons();
		Db::commit();
		return 0;
	}

	function restore_task_end()
	{
		if ($this->vars->changes == 0)
		{
			$this->log('Nothing to change - everything is as it was.');
		}
		else
		{
			$this->log(($this->dryRun ? 'Changes that would be made: ' : 'Changes made: ') . $this->vars->changes . '.');
		}
	}

	private function change($str)
	{
		++$this->vars->changes;
		$this->log(($this->dryRun ? 'Would: ' : '') . $str);
	}

	private function restore_gaining()
	{
		$query = new DbQuery('SELECT name FROM names WHERE id = ?', self::GAINING_NAME_ID);
		$row = $query->next();
		if (!$row || $row[0] != self::GAINING_NAME)
		{
			throw new Exception('Names ' . self::GAINING_NAME_ID . ' is not "' . self::GAINING_NAME . '" - refusing to guess the name of the gaining system.');
		}

		$query = new DbQuery('SELECT league_id, name_id FROM gainings WHERE id = ?', self::GAINING_ID);
		if ($row = $query->next())
		{
			if ((int)$row[0] != self::GAINING_LEAGUE_ID || (int)$row[1] != self::GAINING_NAME_ID)
			{
				throw new Exception('Gaining system ' . self::GAINING_ID . ' exists and is not the one this script restores - leaving it alone.');
			}
			$this->log('Gaining system ' . self::GAINING_ID . ' is there.');
		}
		else
		{
			$this->change('create gaining system ' . self::GAINING_ID . ' "' . self::GAINING_NAME . '".');
			$this->write('gaining system', 'INSERT INTO gainings (id, league_id, name_id, version, club_id, flags) VALUES (?, ?, ?, 1, NULL, 0)',
				self::GAINING_ID, self::GAINING_LEAGUE_ID, self::GAINING_NAME_ID);
		}

		$query = new DbQuery('SELECT gaining FROM gaining_versions WHERE gaining_id = ? AND version = 1', self::GAINING_ID);
		if ($row = $query->next())
		{
			if ($row[0] != self::GAINING)
			{
				throw new Exception('Version 1 of gaining system ' . self::GAINING_ID . ' is there with different rules - leaving it alone.');
			}
			$this->log('Version 1 of gaining system ' . self::GAINING_ID . ' is there.');
		}
		else
		{
			$this->change('create version 1 of gaining system ' . self::GAINING_ID . '.');
			$this->write('gaining system', 'INSERT INTO gaining_versions (gaining_id, version, gaining, functions) VALUES (?, 1, ?, ?)',
				self::GAINING_ID, self::GAINING, self::GAINING_FUNCTIONS);
		}
	}

	private function restore_series($series_id, $s)
	{
		list($gaining_id, $gaining_version, $max_stars, $stages, $flags, $notes) = Db::record(get_label('series'),
			'SELECT gaining_id, gaining_version, max_stars, stages, flags, notes FROM series WHERE id = ?', $series_id);
		$prefix = 'series ' . $series_id . ' (' . $s->name . '): ';
		$dirty = false;

		if ((int)$gaining_id != $s->gaining_id || (int)$gaining_version != $s->gaining_version)
		{
			$this->change($prefix . 'gaining system ' . $gaining_id . ' v' . $gaining_version . ' -> ' . $s->gaining_id . ' v' . $s->gaining_version . '.');
			$this->write('series', 'UPDATE series SET gaining_id = ?, gaining_version = ? WHERE id = ?', $s->gaining_id, $s->gaining_version, $series_id);
			$dirty = true;
		}
		if ((int)$max_stars != SERIES_DEFAULT_MAX_STARS || (int)$stages != 1)
		{
			$this->change($prefix . 'max stars ' . $max_stars . ' -> ' . SERIES_DEFAULT_MAX_STARS . ', stages ' . $stages . ' -> 1.');
			$this->write('series', 'UPDATE series SET max_stars = ?, stages = 1 WHERE id = ?', SERIES_DEFAULT_MAX_STARS, $series_id);
			$dirty = true;
		}
		$plays = SERIES_FLAG_PLAYS_TOURNAMENTS | SERIES_FLAG_PLAYS_SERIES;
		if (((int)$flags & $plays) != $plays)
		{
			$this->change($prefix . 'takes tournaments and subseries again.');
			$this->write('series', 'UPDATE series SET flags = flags | ? WHERE id = ?', $plays, $series_id);
		}
		if (!is_null($s->notes) && $notes != $s->notes)
		{
			$this->change($prefix . 'note "' . $notes . '" -> "' . $s->notes . '".');
			$this->write('series', 'UPDATE series SET notes = ? WHERE id = ?', $s->notes, $series_id);
		}

		// Stages go first: with one stage left, a tournament on a stage above the base one has
		// nowhere to be.
		$query = new DbQuery('SELECT tournament_id, stage FROM series_tournaments WHERE series_id = ? AND stage <> 0', $series_id);
		while ($row = $query->next())
		{
			$this->change($prefix . 'tournament ' . $row[0] . ' stage ' . $row[1] . ' -> 0.');
			$dirty = true;
		}
		$this->write('series', 'UPDATE series_tournaments SET stage = 0 WHERE series_id = ? AND stage <> 0', $series_id);

		foreach ($s->tournaments as $tournament_id => $stars)
		{
			$query = new DbQuery('SELECT stars FROM series_tournaments WHERE series_id = ? AND tournament_id = ?', $series_id, $tournament_id);
			$row = $query->next();
			if (!$row)
			{
				$this->error($prefix . 'tournament ' . $tournament_id . ' is not in the series any more - its stars are left alone.');
				continue;
			}
			if ((float)$row[0] != $stars)
			{
				$this->change($prefix . 'tournament ' . $tournament_id . ' stars ' . $row[0] . ' -> ' . $stars . '.');
				$this->write('series', 'UPDATE series_tournaments SET stars = ? WHERE series_id = ? AND tournament_id = ?', $stars, $series_id, $tournament_id);
				$dirty = true;
			}

			// More than one star in any series is what makes a tournament elite - see the change op of
			// api/ops/tournament.php.
			list($t_flags) = Db::record(get_label('tournament'), 'SELECT flags FROM tournaments WHERE id = ?', $tournament_id);
			if ($stars > 1 && ((int)$t_flags & TOURNAMENT_FLAG_ELITE) == 0)
			{
				$this->change($prefix . 'tournament ' . $tournament_id . ' is elite again.');
				$this->write('tournament', 'UPDATE tournaments SET flags = flags | ? WHERE id = ?', TOURNAMENT_FLAG_ELITE, $tournament_id);
			}
		}

		if ($dirty)
		{
			$this->write('series', 'UPDATE series SET flags = flags | ? WHERE id = ?', SERIES_FLAG_DIRTY, $series_id);
			$this->vars->recount = true;
		}
	}

	// The seasons count the subseries by what the subseries scored, so they are counted again too.
	private function mark_seasons()
	{
		if (empty($this->vars->recount))
		{
			return;
		}
		foreach (self::SEASONS as $season_id)
		{
			$this->log(($this->dryRun ? 'Would mark' : 'Marked') . ' season ' . $season_id . ' to be counted again.');
			$this->write('series', 'UPDATE series SET flags = flags | ? WHERE id = ?', SERIES_FLAG_DIRTY, $season_id);
		}
	}
}

$updater = new RestoreAmlSubseries();
$updater->run();

?>
