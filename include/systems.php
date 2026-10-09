<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/error.php';

// Scoring systems, gaining systems and scoring normalizers are built the same way: a row in
// <kind>s carrying the name, the owner and the number of the current version, and a row per
// version in <kind>_versions holding one json column named after the kind. So are the names of
// all of it - scoring, scorings, scoring_versions, scoring_id, api/ops/scoring.php - which is
// why everything that needs no more than that shape lives here instead of being spelled out
// again in each of the three editor pages and in the json dialog.

function get_system_kind_names()
{
	return array(
		'scoring' => get_label('scoring system'),
		'gaining' => get_label('gaining system'),
		'normalizer' => get_label('scoring normalizer'));
}

// Table and column names cannot be parameterized, so a kind that came from a request is matched
// against the list above before any of it reaches a query. Anything else is refused rather than
// interpolated. Returns what to call the kind on screen.
function check_system_kind($kind)
{
	$kind_names = get_system_kind_names();
	if (!isset($kind_names[$kind]))
	{
		throw new FatalExc(get_label('Unknown [0]', get_label('type')));
	}
	return $kind_names[$kind];
}

// Every version of one system, oldest first. Versions are numbered from one and never deleted,
// but the list is read rather than counted, so a gap would not turn into a dead option in the
// version picker.
function get_system_versions($kind, $system_id)
{
	$versions = array();
	$query = new DbQuery('SELECT version FROM ' . $kind . '_versions WHERE ' . $kind . '_id = ? ORDER BY version', $system_id);
	while ($row = $query->next())
	{
		$versions[] = (int)$row[0];
	}
	return $versions;
}

// The versions of one system that something already uses. What counts as use differs per kind -
// tournaments and rounds are scored by a scoring system and normalized by a normalizer, while a
// gaining system is used by the series itself and not by the tournaments inside it - so the tables
// to look in are given by the caller. Returns a map version => true, lowest version first.
function get_used_system_versions($kind, $system_id, $tables)
{
	$used = array();
	foreach ($tables as $table)
	{
		$query = new DbQuery('SELECT DISTINCT ' . $kind . '_version FROM ' . $table .
			' WHERE ' . $kind . '_id = ?', $system_id);
		while ($row = $query->next())
		{
			$used[(int)$row[0]] = true;
		}
	}
	ksort($used);
	return $used;
}

// Which version the page was asked to show. A number that is not one of the versions that exist -
// an old link, a hand-typed one - shows the current version instead of failing.
function get_requested_system_version($versions, $current_version)
{
	if (isset($_REQUEST['version']))
	{
		$version = (int)$_REQUEST['version'];
		if (in_array($version, $versions, true))
		{
			return $version;
		}
	}
	return $current_version;
}

?>
